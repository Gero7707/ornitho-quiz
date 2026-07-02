"""
Vérifie quels oiseaux de Guyane ont une page Wikipedia française
en testant avec le nom commun actuel.

Génère verifier_noms_guyane.csv avec :
- id, nom_latin, nom_commun_actuel, wikipedia_trouve, titre_wikipedia

Usage : python scripts/verifier_noms_guyane.py
"""

import requests
import mysql.connector
import time
import os
import csv
from dotenv import load_dotenv

load_dotenv(r'C:\Env\workspace\OrnithoQuizz\.env')

DB_CONFIG = {
    'host':     os.getenv('DB_HOST'),
    'port':     int(os.getenv('DB_PORT')),
    'database': os.getenv('DB_NAME'),
    'user':     os.getenv('DB_USER'),
    'password': os.getenv('DB_PASSWORD'),
}

PAUSE = 0.3

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
            # Vérifie que c'est bien une page sur un oiseau (pas une page d'homonymie)
            if data.get('type') == 'disambiguation':
                return False, 'page homonymie'
            return True, data.get('title', '')
        return False, f'HTTP {resp.status_code}'
    except Exception as e:
        return False, f'erreur: {e}'

# ─── Connexion BDD ────────────────────────────────────────────────────────────

conn   = mysql.connector.connect(**DB_CONFIG)
cursor = conn.cursor(dictionary=True)

cursor.execute("""
    SELECT id, nom_commun, nom_latin 
    FROM oiseaux 
    WHERE region = 'guyane'
    ORDER BY nom_commun
""")
especes = cursor.fetchall()

cursor.close()
conn.close()

print(f"=== Vérification Wikipedia — Guyane ===\n")
print(f"{len(especes)} espèces à tester\n")

resultats   = []
nb_trouves  = 0
nb_manquants = 0

for esp in especes:
    trouve, titre_wiki = chercher_wikipedia(esp['nom_commun'])

    status = "✅" if trouve else "❌"
    print(f"  {status} {esp['nom_commun']:<45} {titre_wiki}", end="\r" if trouve else "\n")

    resultats.append({
        'id':               esp['id'],
        'nom_latin':        esp['nom_latin'],
        'nom_commun_actuel': esp['nom_commun'],
        'wikipedia_trouve': 'oui' if trouve else 'non',
        'titre_wikipedia':  titre_wiki,
        'nom_commun_correct': '',  # à remplir manuellement si 'non'
    })

    if trouve:
        nb_trouves += 1
    else:
        nb_manquants += 1

    time.sleep(PAUSE)

# ─── Export CSV ───────────────────────────────────────────────────────────────

with open("verifier_noms_guyane.csv", "w", encoding="utf-8", newline='') as f:
    writer = csv.DictWriter(f, fieldnames=[
        'id', 'nom_latin', 'nom_commun_actuel',
        'wikipedia_trouve', 'titre_wikipedia', 'nom_commun_correct'
    ])
    writer.writeheader()
    writer.writerows(resultats)

print(f"\n\n=== Résumé ===")
print(f"✅ Trouvés    : {nb_trouves}")
print(f"❌ Manquants  : {nb_manquants}")
print(f"\n💾 Résultats exportés dans verifier_noms_guyane.csv")
print(f"\n👉 Remplis la colonne 'nom_commun_correct' pour les lignes 'non'")
print(f"   puis lance appliquer_noms_guyane.py pour mettre à jour la BDD")