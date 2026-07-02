"""
Applique les corrections de noms communs depuis verifier_noms_guyane.csv.

Lit la colonne 'nom_commun_correct' et met à jour la BDD pour les lignes remplies.

Usage : python scripts/appliquer_noms_guyane.py
"""

import mysql.connector
import csv
import os
from dotenv import load_dotenv

load_dotenv(r'C:\Env\workspace\OrnithoQuizz\.env')

DB_CONFIG = {
    'host':     os.getenv('DB_HOST'),
    'port':     int(os.getenv('DB_PORT')),
    'database': os.getenv('DB_NAME'),
    'user':     os.getenv('DB_USER'),
    'password': os.getenv('DB_PASSWORD'),
}

# ─── Lecture CSV ──────────────────────────────────────────────────────────────

corrections = []
with open("verifier_noms_guyane.csv", encoding="utf-8") as f:
    reader = csv.DictReader(f)
    for row in reader:
        nom_correct = row['nom_commun_correct'].strip()
        if nom_correct:  # seulement les lignes remplies
            corrections.append({
                'id':           int(row['id']),
                'nom_ancien':   row['nom_commun_actuel'],
                'nom_correct':  nom_correct,
            })

print(f"=== Application des corrections de noms ===\n")
print(f"{len(corrections)} corrections à appliquer\n")

if not corrections:
    print("Aucune correction trouvée. Vérifie que tu as bien rempli la colonne 'nom_commun_correct'.")
    exit()

# ─── Dry run ─────────────────────────────────────────────────────────────────

print("Aperçu des modifications :")
for c in corrections[:10]:
    print(f"  id {c['id']:<5} {c['nom_ancien']:<45} → {c['nom_correct']}")
if len(corrections) > 10:
    print(f"  ... et {len(corrections) - 10} autres")

print(f"\nConfirmer ? (o/n) ", end="")
reponse = input().strip().lower()
if reponse != 'o':
    print("Annulé.")
    exit()

# ─── Mise à jour BDD ──────────────────────────────────────────────────────────

conn   = mysql.connector.connect(**DB_CONFIG)
cursor = conn.cursor()

succes = 0
for c in corrections:
    cursor.execute(
        "UPDATE oiseaux SET nom_commun = %s WHERE id = %s",
        (c['nom_correct'], c['id'])
    )
    conn.commit()
    succes += 1
    print(f"  ✅ {c['nom_ancien']} → {c['nom_correct']}")

cursor.close()
conn.close()

print(f"\n=== Terminé ===")
print(f"✅ {succes} noms mis à jour")
print(f"\n👉 Tu peux maintenant lancer import_descriptions_wikipedia_guyane.py")