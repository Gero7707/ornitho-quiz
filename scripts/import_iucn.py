"""
Script d'import IUCN Red List v4
=================================
Pour chaque espèce en BDD :
  1. Interroge l'API IUCN pour obtenir l'assessment_id
  2. Récupère les détails de l'évaluation
  3. Met à jour la table oiseaux avec les données IUCN

Usage : python scripts/import_iucn.py
"""

import requests
import mysql.connector
import time
import os
from dotenv import load_dotenv

# ─── CONFIGURATION ────────────────────────────────────────────────────────────
load_dotenv()

IUCN_TOKEN = os.getenv('IUCN_TOKEN')
IUCN_API   = "https://api.iucnredlist.org/api/v4"
PAUSE      = 2  # secondes entre chaque requête (recommandé par l'IUCN)

DB_CONFIG = {
    'host':     os.getenv('DB_HOST'),
    'port':     int(os.getenv('DB_PORT', 3306)),
    'database': os.getenv('DB_NAME'),
    'user':     os.getenv('DB_USER'),
    'password': os.getenv('DB_PASSWORD'),
}

# Headers d'authentification Bearer
HEADERS = {
    "Authorization": f"Bearer {IUCN_TOKEN}",
    "Content-Type":  "application/json",
}

# ─── TRADUCTION DES STATUTS ───────────────────────────────────────────────────
STATUTS = {
    "LC": "Préoccupation mineure",
    "NT": "Quasi menacée",
    "VU": "Vulnérable",
    "EN": "En danger",
    "CR": "En danger critique",
    "EW": "Éteinte à l'état sauvage",
    "EX": "Éteinte",
    "DD": "Données insuffisantes",
    "NE": "Non évaluée",
}

TENDANCES = {
    "Decreasing": "En déclin",
    "Increasing": "En augmentation",
    "Stable":     "Stable",
    "Unknown":    "Inconnue",
}

# ─── CONNEXION BDD ────────────────────────────────────────────────────────────
conn   = mysql.connector.connect(**DB_CONFIG)
cursor = conn.cursor(dictionary=True)

# ─── FONCTIONS ────────────────────────────────────────────────────────────────

def get_assessment_id(nom_latin: str) -> int | None:
    """
    Étape 1 : récupère l'assessment_id depuis le nom latin.
    Retourne None si l'espèce n'est pas trouvée.
    """
    # Sépare genre et espèce
    parties = nom_latin.strip().split(" ")
    if len(parties) < 2:
        return None

    url = f"{IUCN_API}/taxa/scientific_name"
    params = {
        "genus_name":   parties[0],
        "species_name": parties[1],
    }

    try:
        r = requests.get(url, headers=HEADERS, params=params, timeout=10)
        r.raise_for_status()
        data = r.json()

        # On cherche la dernière évaluation (latest=True)
        assessments = data.get("assessments", [])
        for a in assessments:
            if a.get("latest") is True:
                return a.get("assessment_id")

        # Si pas de latest, prend le premier
        if assessments:
            return assessments[0].get("assessment_id")

        return None

    except Exception as e:
        print(f"      ❌ Erreur taxa : {e}")
        return None


def get_assessment_details(assessment_id: int) -> dict | None:
    """
    Étape 2 : récupère les détails complets d'une évaluation.
    Retourne un dict avec statut, tendance, année, critères.
    """
    url = f"{IUCN_API}/assessment/{assessment_id}"
    print(f"      URL : {url}")
    try:
        r = requests.get(url, headers=HEADERS, timeout=10)
        r.raise_for_status()
        data = r.json()

        # Extraction des données utiles
        statut   = data.get("red_list_category", {}).get("code")
        tendance_raw = data.get("population_trend")
        if isinstance(tendance_raw, dict):
            description = tendance_raw.get("description")
            if isinstance(description, dict):
                tendance = description.get("en")
            else:
                tendance = description
        else:
            tendance = None
        annee    = data.get("year_published")
        criteres = data.get("criteria")

        # Si criteres est un dict ou une liste, on le convertit en string
        if isinstance(criteres, dict):
            criteres = criteres.get("code") or criteres.get("description")
        elif isinstance(criteres, list):
            criteres = ", ".join([c.get("code", "") for c in criteres])

        return {
            "statut":   statut,
            "tendance": tendance,
            "annee":    annee,
            "criteres": criteres,
        }

    except Exception as e:
        print(f"      ❌ Erreur assessment : {e}")
        return None


# ─── PROGRAMME PRINCIPAL ──────────────────────────────────────────────────────

# Récupère les espèces sans données IUCN
cursor.execute("""
    SELECT id, nom_commun, nom_latin 
    FROM oiseaux 
    WHERE nom_latin IS NOT NULL 
    AND iucn_statut IS NULL
    ORDER BY nom_commun
""")
especes = cursor.fetchall()

# ── TEST : décommente pour tester sur 3 espèces ──
# especes = especes[:3]

print(f"=== Import IUCN Red List v4 ===")
print(f"{len(especes)} espèces à traiter\n")

total_ok      = 0
total_ignores = 0
total_erreurs = 0

for espece in especes:

    print(f"🔍 {espece['nom_commun']} ({espece['nom_latin']})...")

    # Étape 1 : assessment_id
    assessment_id = get_assessment_id(espece["nom_latin"])

    if not assessment_id:
        print(f"   ⚠️  Espèce non trouvée dans l'IUCN")
        total_ignores += 1
        time.sleep(PAUSE)
        continue

    # Étape 2 : détails
    details = get_assessment_details(assessment_id)

    if not details:
        print(f"   ❌ Impossible de récupérer les détails")
        total_erreurs += 1
        time.sleep(PAUSE)
        continue
    
    # Mise à jour BDD
    cursor.execute("""
        UPDATE oiseaux 
        SET iucn_statut   = %s,
            iucn_tendance = %s,
            iucn_annee    = %s,
            iucn_criteres = %s
        WHERE id = %s
    """, (
        details["statut"],
        details["tendance"],
        details["annee"],
        details["criteres"],
        espece["id"],
    ))
    conn.commit()

    # Affichage lisible
    statut_fr  = STATUTS.get(details["statut"], details["statut"])
    tendance_fr = TENDANCES.get(details["tendance"], details["tendance"] or "Inconnue")

    print(f"   ✅ {details['statut']} — {statut_fr} | {tendance_fr} ({details['annee']})")
    total_ok += 1

    time.sleep(PAUSE)

# ─── RÉSUMÉ ───────────────────────────────────────────────────────────────────
print(f"\n=== Terminé ===")
print(f"✅ Mis à jour : {total_ok}")
print(f"⏭️  Ignorés   : {total_ignores}")
print(f"❌ Erreurs   : {total_erreurs}")

cursor.close()
conn.close()