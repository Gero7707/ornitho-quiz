"""
Vérifie quels oiseaux n'ont pas de page Wikipedia française valide
en testant avec le nom commun actuel.

Génère verifier_noms_wikipedia.csv avec :
- id, nom_latin, nom_commun_actuel, region, wikipedia_trouve, titre_wikipedia

Usage : python3 scripts/verifier_noms_wikipedia.py [region]

Exemples :
  python3 scripts/verifier_noms_wikipedia.py              → toutes les régions
  python3 scripts/verifier_noms_wikipedia.py reu_mad_may  → une région spécifique
  python3 scripts/verifier_noms_wikipedia.py metropole    → métropole seulement
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

# Région passée en argument, ou toutes si absent
region_filtre = sys.argv[1] if len(sys.argv) > 1 else None


def chercher_wikipedia(nom_commun: str) -> tuple:
    """
    Retourne (trouve: bool, titre_wikipedia: str)
    """
    url = "https://fr.wikipedia.org/api/rest_v1/page/summary/" + nom_commun.replace(' ', '_')
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
        ORDER BY region, nom_commun
    """, (region_filtre,))
    print(f"=== Vérification Wikipedia — région : {region_filtre} ===\n")
else:
    cursor.execute("""
        SELECT id, nom_commun, nom_latin, region
        FROM oiseaux
        ORDER BY region, nom_commun
    """)
    print(f"=== Vérification Wikipedia — toutes régions ===\n")

especes = cursor.fetchall()
cursor.close()
conn.close()

print(f"{len(especes)} espèces à tester\n")

resultats    = []
nb_trouves   = 0
nb_manquants = 0

for esp in especes:
    trouve, titre_wiki = chercher_wikipedia(esp['nom_commun'])

    status = "✅" if trouve else "❌"
    if not trouve:
        print(f"  {status} [{esp['region']}] {esp['nom_commun']:<45} → {titre_wiki}")

    resultats.append({
        'id':                esp['id'],
        'nom_latin':         esp['nom_latin'],
        'nom_commun_actuel': esp['nom_commun'],
        'region':            esp['region'],
        'wikipedia_trouve':  'oui' if trouve else 'non',
        'titre_wikipedia':   titre_wiki,
        'nom_commun_correct': '',  # à remplir manuellement si 'non'
    })

    if trouve:
        nb_trouves += 1
    else:
        nb_manquants += 1

    time.sleep(PAUSE)

# ─── Export CSV ───────────────────────────────────────────────────────────────

nom_fichier_csv = f"verifier_noms_{region_filtre or 'toutes_regions'}.csv"

with open(nom_fichier_csv, "w", encoding="utf-8", newline='') as f:
    writer = csv.DictWriter(f, fieldnames=[
        'id', 'nom_latin', 'nom_commun_actuel', 'region',
        'wikipedia_trouve', 'titre_wikipedia', 'nom_commun_correct'
    ])
    writer.writeheader()
    # N'exporte que les lignes sans Wikipedia valide
    writer.writerows([r for r in resultats if r['wikipedia_trouve'] == 'non'])

print(f"\n=== Résumé ===")
print(f"✅ Trouvés   : {nb_trouves}")
print(f"❌ Manquants : {nb_manquants}")
print(f"\n💾 Lignes sans Wikipedia exportées dans {nom_fichier_csv}")
print(f"\n👉 Remplis la colonne 'nom_commun_correct' pour chaque ligne")
print(f"   puis lance appliquer_noms.py pour mettre à jour la BDD")