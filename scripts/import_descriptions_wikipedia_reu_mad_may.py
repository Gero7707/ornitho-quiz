"""
Import des descriptions Wikipedia pour les espèces Réunion, Madagascar, Mayotte sans description IUCN.

Pour chaque espèce concernée :
  1. Appelle l'API Wikipedia en texte brut (explaintext=true)
  2. Prend l'intro + tronque à MAX_CHARS caractères
  3. Stocke dans description_courte en BDD

Usage : python3 scripts/import_descriptions_wikipedia_reu_mad_may.py
"""

import requests
import mysql.connector
import time
import os
from dotenv import load_dotenv

load_dotenv()

DB_CONFIG = {
    'host':     os.getenv('DB_HOST'),
    'port':     int(os.getenv('DB_PORT', 3306)),
    'database': os.getenv('DB_NAME'),
    'user':     os.getenv('DB_USER'),
    'password': os.getenv('DB_PASSWORD'),
}

MAX_CHARS = 700  # ~4-5 phrases
PAUSE     = 0.3

def get_description_wikipedia(nom_commun: str) -> str | None:
    """
    Récupère le texte d'intro Wikipedia en texte brut.
    Retourne None si la page n'existe pas ou est une homonymie.
    """
    url = "https://fr.wikipedia.org/w/api.php"
    params = {
        "action":       "query",
        "prop":         "extracts",
        "explaintext":  True,   # texte brut, pas de HTML
        "exintro":      True,   # intro seulement (avant le premier titre de section)
        "titles":       nom_commun,
        "format":       "json",
        "redirects":    1,      # suit les redirections
    }

    try:
        resp = requests.get(url, params=params, timeout=10, headers={
            "User-Agent": "OrnithoQuiz/1.0 (ornitho-quiz.fr)"
        })
        resp.raise_for_status()
        data = resp.json()

        pages = data.get("query", {}).get("pages", {})
        page  = next(iter(pages.values()))

        # Page inexistante
        if "missing" in page:
            return None

        texte = page.get("extract", "").strip()

        if not texte:
            return None

        # Tronque proprement à la fin d'une phrase
        if len(texte) > MAX_CHARS:
            texte = texte[:MAX_CHARS]
            # Coupe à la dernière phrase complète
            dernier_point = max(texte.rfind('.'), texte.rfind('!'), texte.rfind('?'))
            if dernier_point > MAX_CHARS // 2:
                texte = texte[:dernier_point + 1]

        return texte

    except Exception as e:
        print(f"      ❌ Erreur Wikipedia : {e}")
        return None


# ─── Connexion BDD ────────────────────────────────────────────────────────────

conn   = mysql.connector.connect(**DB_CONFIG)
cursor = conn.cursor(dictionary=True)

cursor.execute("""
    SELECT id, nom_commun, nom_latin 
    FROM oiseaux 
    WHERE region = 'antilles' 
    AND description_courte IS NULL
    ORDER BY nom_commun
""")
especes = cursor.fetchall()

# ── TEST : décommenter pour tester sur 3 espèces seulement ──
# especes = especes[:3]

print(f"=== Import descriptions Wikipedia — Antilles françaises ===\n")
print(f"{len(especes)} espèces sans description\n")

succes  = 0
echecs  = []

for esp in especes:
    nom_commun = esp['nom_commun']
    print(f"🔍 {nom_commun}...")

    description = get_description_wikipedia(nom_commun)

    if not description:
        print(f"   ❌ Pas de page Wikipedia")
        echecs.append(nom_commun)
        time.sleep(PAUSE)
        continue

    cursor.execute(
        "UPDATE oiseaux SET description_courte = %s WHERE id = %s",
        (description, esp['id'])
    )
    conn.commit()
    succes += 1
    print(f"   ✅ {len(description)} caractères stockés")
    time.sleep(PAUSE)

cursor.close()
conn.close()

print(f"\n=== Résumé ===")
print(f"✅ Succès  : {succes}")
print(f"❌ Échecs  : {len(echecs)}")
if echecs:
    print(f"\nEspèces sans description Wikipedia :")
    for nom in echecs:
        print(f"  - {nom}")