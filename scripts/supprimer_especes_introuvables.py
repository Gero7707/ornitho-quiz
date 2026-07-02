"""
Suppression des espèces introuvables (sans nom français valide)
===============================================================
Pour chaque espèce listée :
  1. Supprime les fichiers sur R2
  2. Supprime les sons en BDD
  3. Supprime la fiche oiseau en BDD

Usage : python3 scripts/supprimer_especes_introuvables.py

Passer DRY_RUN = False pour appliquer les suppressions.
"""

import boto3
import mysql.connector
import os
from dotenv import load_dotenv

load_dotenv()

# ─── DRY RUN : passer à False pour appliquer ─────────────────────────────────
DRY_RUN = False

DB_CONFIG = {
    'host':     os.getenv('DB_HOST'),
    'port':     int(os.getenv('DB_PORT', 3306)),
    'database': os.getenv('DB_NAME'),
    'user':     os.getenv('DB_USER'),
    'password': os.getenv('DB_PASSWORD'),
}

R2_ENDPOINT          = os.getenv('R2_ENDPOINT')
R2_ACCESS_KEY_ID     = os.getenv('R2_ACCESS_KEY_ID')
R2_SECRET_ACCESS_KEY = os.getenv('R2_SECRET_ACCESS_KEY')
R2_BUCKET            = os.getenv('R2_BUCKET')

# ─── Liste des espèces à supprimer ───────────────────────────────────────────
ESPECES_A_SUPPRIMER = [
    {"id": 1136, "nom_latin": "Bernieria madagascariensis",  "nom_commun": "Bernieria à long bec"},
    {"id": 1110, "nom_latin": "Ceblepyris cinereus",         "nom_commun": "Échenilleur de Madagascar"},
    {"id": 1111, "nom_latin": "Ceblepyris cucullatus",       "nom_commun": "Échenilleur des Comores"},
    {"id": 1157, "nom_latin": "Hartlaubius auratus",         "nom_commun": "Étourneau de Madagascar"},
    {"id": 1130, "nom_latin": "Nesillas longicaudata",       "nom_commun": "Fauvette des broussailles d'Anjouan"},
    {"id": 1131, "nom_latin": "Nesillas mariae",             "nom_commun": "Fauvette des broussailles de Mohéli"},
    {"id": 1129, "nom_latin": "Nesillas lantzii",            "nom_commun": "Fauvette des broussailles du sous-désert"},
    {"id": 1161, "nom_latin": "Monticola sharpei",           "nom_commun": "Grive des rochers"},
    {"id": 1180, "nom_latin": "Mystery mystery",             "nom_commun": "Identité inconnue"},
    {"id": 1138, "nom_latin": "Hartertula flavoviridis",     "nom_commun": "Jerry à queue en coin"},
    {"id": 1147, "nom_latin": "Neomixis viridis",            "nom_commun": "Jerry vert"},
    {"id": 1124, "nom_latin": "Hypsipetes moheliensis",      "nom_commun": "Moheli Bulbul"},
    {"id": 1108, "nom_latin": "Pseudobias wardi",            "nom_commun": "Moucherolle de Ward"},
    {"id": 1107, "nom_latin": "Newtonia fanovanae",          "nom_commun": "Newtonia à queue rouge"},
    {"id": 1104, "nom_latin": "Newtonia amphichroa",         "nom_commun": "Newtonie sombre"},
    {"id": 1140, "nom_latin": "Xanthomixis zosterops",      "nom_commun": "Tétraka à lunettes"},
    {"id": 1141, "nom_latin": "Xanthomixis apperti",         "nom_commun": "Tétraka d'Appert"},
    {"id": 1162, "nom_latin": "Saxicola sibilla",            "nom_commun": "Traquet de Madagascar"},
    {"id": 1109, "nom_latin": "Mystacornis crossleyi",       "nom_commun": "Vanga de Crossley"},
]

# ─── Connexions ───────────────────────────────────────────────────────────────
r2 = boto3.client(
    "s3",
    endpoint_url          = R2_ENDPOINT,
    aws_access_key_id     = R2_ACCESS_KEY_ID,
    aws_secret_access_key = R2_SECRET_ACCESS_KEY,
    region_name           = "auto",
)

conn   = mysql.connector.connect(**DB_CONFIG)
cursor = conn.cursor(dictionary=True)

print(f"=== Suppression espèces introuvables ===")
print(f"{'⚠️  DRY RUN — aucune modification' if DRY_RUN else '🗑️  MODE SUPPRESSION — modifications appliquées'}\n")
print(f"{len(ESPECES_A_SUPPRIMER)} espèces à traiter\n")

total_sons_supprimes    = 0
total_fichiers_supprimes = 0
total_especes_supprimees = 0

for esp in ESPECES_A_SUPPRIMER:
    oiseau_id  = esp["id"]
    nom_commun = esp["nom_commun"]
    nom_latin  = esp["nom_latin"]

    print(f"🔍 [{oiseau_id}] {nom_commun} ({nom_latin})")

    # ── 1. Récupérer les sons en BDD ──
    cursor.execute(
        "SELECT id, chemin_fichier FROM sons WHERE oiseau_id = %s",
        (oiseau_id,)
    )
    sons = cursor.fetchall()
    print(f"   📁 {len(sons)} son(s) trouvé(s) en BDD")

    # ── 2. Supprimer les fichiers sur R2 ──
    for son in sons:
        chemin_r2 = son["chemin_fichier"]
        print(f"   🗑️  R2 : {chemin_r2}")
        if not DRY_RUN:
            try:
                r2.delete_object(Bucket=R2_BUCKET, Key=chemin_r2)
                total_fichiers_supprimes += 1
            except Exception as e:
                print(f"      ❌ Erreur R2 : {e}")

    # ── 3. Supprimer les sons en BDD ──
    print(f"   🗑️  BDD : DELETE FROM sons WHERE oiseau_id = {oiseau_id}")
    if not DRY_RUN:
        cursor.execute("DELETE FROM sons WHERE oiseau_id = %s", (oiseau_id,))
        total_sons_supprimes += len(sons)

    # ── 4. Supprimer la fiche oiseau ──
    print(f"   🗑️  BDD : DELETE FROM oiseaux WHERE id = {oiseau_id}")
    if not DRY_RUN:
        cursor.execute("DELETE FROM oiseaux WHERE id = %s", (oiseau_id,))
        total_especes_supprimees += 1

    print()

if not DRY_RUN:
    conn.commit()

# ─── Résumé ───────────────────────────────────────────────────────────────────
print(f"=== Résumé ===")
if DRY_RUN:
    print(f"⚠️  Dry run — rien n'a été modifié")
    print(f"\n👉 Passe DRY_RUN = False pour appliquer les suppressions")
else:
    print(f"🐦 Espèces supprimées : {total_especes_supprimees}")
    print(f"🎵 Sons supprimés     : {total_sons_supprimes}")
    print(f"☁️  Fichiers R2        : {total_fichiers_supprimes}")

cursor.close()
conn.close()