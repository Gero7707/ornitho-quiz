"""
Compare les espèces d'oiseaux disponibles sur Xeno-canto (France métropolitaine)
avec les espèces déjà en BDD OrnithoQuizz.

Résultat : liste des espèces présentes sur XC mais absentes de la BDD.

Usage : python scripts/comparer_especes.py
"""

import requests
import mysql.connector
import time
import os
from dotenv import load_dotenv

load_dotenv(r'C:\Env\workspace\OrnithoQuizz\.env')

# ─── CONFIG ───────────────────────────────────────────────────────────────────
XENO_API_URL = "https://xeno-canto.org/api/3/recordings"
XENO_API_KEY = "ee1d41e190bb158119033ad5dadb13800a663428"

# Bounding box France métropolitaine + Corse
BOX_METROPOLE = "41.3,-5.2,51.1,9.6"

DB_CONFIG = {
    'host':     os.getenv('DB_HOST'),
    'port':     int(os.getenv('DB_PORT')),
    'database': os.getenv('DB_NAME'),
    'user':     os.getenv('DB_USER'),
    'password': os.getenv('DB_PASSWORD'),
}

PAUSE = 0.3  # secondes entre chaque page

# ─── ÉTAPE 1 : Récupérer toutes les espèces XC pour la métropole ──────────────

print("=== Récupération des espèces Xeno-canto (France métropolitaine) ===\n")

especes_xc = {}  # { "Turdus merula": "Common Blackbird", ... }
page = 1

while True:
    params = {
        "query": f"box:{BOX_METROPOLE} grp:birds",
        "page":  page,
        "key":   XENO_API_KEY,
    }

    try:
        resp = requests.get(XENO_API_URL, params=params, timeout=15, headers={
            "User-Agent": "OrnithoQuiz/1.0 (ornitho-quiz.fr)"
        })
        resp.raise_for_status()
        data = resp.json()
    except Exception as e:
        print(f"❌ Erreur page {page} : {e}")
        break

    num_pages = int(data.get("numPages", 1))
    recordings = data.get("recordings", [])

    for rec in recordings:
        genre = rec.get("gen", "").strip()
        sp    = rec.get("sp", "").strip()
        en    = rec.get("en", "").strip()

        if genre and sp:
            nom_latin = f"{genre} {sp}"
            if nom_latin not in especes_xc:
                especes_xc[nom_latin] = en

    print(f"  Page {page}/{num_pages} — {len(especes_xc)} espèces uniques trouvées", end="\r")

    if page >= num_pages:
        break

    page += 1
    time.sleep(PAUSE)

print(f"\n\n✅ Total espèces XC métropole : {len(especes_xc)}\n")

# ─── ÉTAPE 2 : Récupérer les espèces en BDD ───────────────────────────────────

print("=== Récupération des espèces en BDD ===\n")

conn   = mysql.connector.connect(**DB_CONFIG)
cursor = conn.cursor(dictionary=True)

cursor.execute("SELECT nom_latin FROM oiseaux WHERE nom_latin IS NOT NULL")
especes_bdd = set(row["nom_latin"].strip() for row in cursor.fetchall())

print(f"✅ Total espèces BDD : {len(especes_bdd)}\n")

# ─── ÉTAPE 3 : Comparer ───────────────────────────────────────────────────────

print("=== Espèces présentes sur XC mais absentes de la BDD ===\n")

manquantes = {
    nom_latin: en
    for nom_latin, en in especes_xc.items()
    if nom_latin not in especes_bdd
}

print(f"📋 {len(manquantes)} espèces manquantes :\n")

for nom_latin, en in sorted(manquantes.items(), key=lambda x: x[1]):
    print(f"  {nom_latin:<40} {en}")

# ─── ÉTAPE 4 : Export CSV ─────────────────────────────────────────────────────

with open("especes_manquantes.csv", "w", encoding="utf-8") as f:
    f.write("nom_latin,nom_anglais\n")
    for nom_latin, en in sorted(manquantes.items(), key=lambda x: x[1]):
        f.write(f'"{nom_latin}","{en}"\n')

print(f"\n💾 Résultats exportés dans especes_manquantes.csv")

cursor.close()
conn.close()