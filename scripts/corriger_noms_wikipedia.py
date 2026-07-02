"""
Corrige automatiquement les noms communs via Wikipedia
=======================================================
Pour chaque espèce d'une région :
  1. Cherche sur Wikipedia FR avec le nom commun actuel
  2. Si pas trouvé → cherche avec le nom latin
  3. Si trouvé via nom latin → met à jour nom_commun en BDD
  4. Si toujours pas trouvé → exporte dans CSV pour correction manuelle

Usage : python3 scripts/corriger_noms_wikipedia.py [region]

Exemples :
  python3 scripts/corriger_noms_wikipedia.py reu_mad_may
  python3 scripts/corriger_noms_wikipedia.py metropole
  python3 scripts/corriger_noms_wikipedia.py              → toutes régions
"""

import requests
import mysql.connector
import time
import os
import csv
import sys
from dotenv import load_dotenv

load_dotenv()

DB_CONFIG = {
    'host':     os.getenv('DB_HOST'),
    'port':     int(os.getenv('DB_PORT', 3306)),
    'database': os.getenv('DB_NAME'),
    'user':     os.getenv('DB_USER'),
    'password': os.getenv('DB_PASSWORD'),
}

PAUSE = 0.3

region_filtre = sys.argv[1] if len(sys.argv) > 1 else None

# ─── DRY RUN : passer à False pour appliquer les modifications ────────────────
DRY_RUN = False


def chercher_wikipedia(terme: str) -> tuple:
    """
    Retourne (trouve: bool, titre_wikipedia: str)
    """
    url = "https://fr.wikipedia.org/api/rest_v1/page/summary/" + terme.replace(' ', '_')
    try:
        resp = requests.get(url, timeout=10, headers={
            "User-Agent": "OrnithoQuiz/1.0 (ornitho-quiz.fr)"
        })
        if resp.status_code == 200:
            data = resp.json()
            if data.get('type') == 'disambiguation':
                return False, 'page homonymie'
            return True, data.get('title', '')
        return False, f'HTTP {resp.status_code}'
    except Exception as e:
        return False, f'erreur: {e}'


# ─── Connexion BDD ────────────────────────────────────────────────────────────

conn   = mysql.connector.connect(**DB_CONFIG)
cursor = conn.cursor(dictionary=True)

if region_filtre:
    cursor.execute("""
        SELECT id, nom_commun, nom_latin, region
        FROM oiseaux
        WHERE region = %s
        ORDER BY nom_commun
    """, (region_filtre,))
    print(f"=== Correction noms Wikipedia — région : {region_filtre} ===")
else:
    cursor.execute("""
        SELECT id, nom_commun, nom_latin, region
        FROM oiseaux
        ORDER BY region, nom_commun
    """)
    print(f"=== Correction noms Wikipedia — toutes régions ===")

especes = cursor.fetchall()

print(f"{len(especes)} espèces à traiter")
print(f"{'⚠️  DRY RUN — aucune modification en BDD' if DRY_RUN else '✏️  MODE ÉCRITURE — modifications appliquées'}\n")

nb_ok          = 0
nb_corriges    = 0
nb_introuvables = 0
corrections_manuelles = []

for esp in especes:
    nom_commun = esp['nom_commun']
    nom_latin  = esp['nom_latin']

    # ── Étape 1 : chercher avec le nom commun actuel ──
    trouve, titre_wiki = chercher_wikipedia(nom_commun)

    if trouve:
        nb_ok += 1
        time.sleep(PAUSE)
        continue

    # ── Étape 2 : chercher avec le nom latin ──
    trouve_latin, titre_latin = chercher_wikipedia(nom_latin)

    if trouve_latin:
        print(f"  🔄 {nom_commun:<40} → {titre_latin}  [{nom_latin}]")

        if not DRY_RUN:
            cursor.execute(
                "UPDATE oiseaux SET nom_commun = %s WHERE id = %s",
                (titre_latin, esp['id'])
            )
            conn.commit()

        nb_corriges += 1
        time.sleep(PAUSE)
        continue

    # ── Étape 3 : toujours pas trouvé → correction manuelle ──
    print(f"  ❌ {nom_commun:<40} [{nom_latin}] → introuvable")
    corrections_manuelles.append({
        'id':                esp['id'],
        'nom_latin':         nom_latin,
        'nom_commun_actuel': nom_commun,
        'region':            esp['region'],
        'nom_commun_correct': '',
    })
    nb_introuvables += 1
    time.sleep(PAUSE)

# ─── Export CSV corrections manuelles ────────────────────────────────────────

if corrections_manuelles:
    nom_csv = f"corrections_manuelles_{region_filtre or 'toutes_regions'}.csv"
    with open(nom_csv, "w", encoding="utf-8", newline='') as f:
        writer = csv.DictWriter(f, fieldnames=[
            'id', 'nom_latin', 'nom_commun_actuel', 'region', 'nom_commun_correct'
        ])
        writer.writeheader()
        writer.writerows(corrections_manuelles)
    print(f"\n💾 {nb_introuvables} espèces à corriger manuellement → {nom_csv}")

# ─── Résumé ───────────────────────────────────────────────────────────────────
print(f"\n=== Résumé ===")
print(f"✅ Déjà corrects       : {nb_ok}")
print(f"🔄 Corrigés via latin  : {nb_corriges}")
print(f"❌ Correction manuelle : {nb_introuvables}")

if DRY_RUN and nb_corriges > 0:
    print(f"\n👉 Dry run terminé — passe DRY_RUN = False pour appliquer les {nb_corriges} corrections")

cursor.close()
conn.close()
