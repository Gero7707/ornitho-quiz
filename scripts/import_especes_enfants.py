"""
Import des espèces manquantes de France métropolitaine
======================================================
Pour chaque espèce dans especes_a_importer.csv :
  1. Crée la fiche en BDD (table oiseaux) si elle n'existe pas
  2. Interroge l'API Xeno-canto (box France métropolitaine)
  3. Télécharge les MP3
  4. Les uploade sur Cloudflare R2
  5. Insère les métadonnées en BDD (table sons)

Usage : python scripts/import_especes_manquantes.py
"""

import boto3
import requests
import mysql.connector
import csv
import time
import re
import os
from pathlib import Path
from dotenv import load_dotenv

load_dotenv()

# ─── CONFIGURATION ────────────────────────────────────────────────────────────
XENO_API_URL   = "https://xeno-canto.org/api/3/recordings"
XC_API_KEY   = "ee1d41e190bb158119033ad5dadb13800a663428"
QUALITES       = ["A", "B"]
MAX_PAR_ESPECE = 5
BOX_METROPOLE  = "41.3,-5.2,51.1,9.6"
PAUSE          = 0.5

R2_ENDPOINT          = os.getenv('R2_ENDPOINT')
R2_ACCESS_KEY_ID     = os.getenv('R2_ACCESS_KEY_ID')
R2_SECRET_ACCESS_KEY = os.getenv('R2_SECRET_ACCESS_KEY')
R2_BUCKET            = os.getenv('R2_BUCKET')
R2_PUBLIC_URL        = os.getenv('R2_PUBLIC_URL')

DB_CONFIG = {
    'host':     os.getenv('DB_HOST'),
    'port':     int(os.getenv('DB_PORT', 3306)),
    'database': os.getenv('DB_NAME'),
    'user':     os.getenv('DB_USER'),
    'password': os.getenv('DB_PASSWORD'),
}

TEMP_DIR = Path("temp_audio")

# ─── CONNEXIONS ───────────────────────────────────────────────────────────────
r2 = boto3.client(
    "s3",
    endpoint_url          = R2_ENDPOINT,
    aws_access_key_id     = R2_ACCESS_KEY_ID,
    aws_secret_access_key = R2_SECRET_ACCESS_KEY,
    region_name           = "auto",
)

conn   = mysql.connector.connect(**DB_CONFIG)
cursor = conn.cursor(dictionary=True)

# ─── FONCTIONS ────────────────────────────────────────────────────────────────

def determiner_type_son(type_xeno: str) -> str:
    t = type_xeno.lower()
    if "song" in t or "chant" in t:
        return "Chant"
    if "call" in t or "cri" in t or "alarm" in t or "flight" in t:
        return "Cri"
    if "drum" in t:
        return "Tambourinage"
    return "Autre"


def nettoyer_nom_fichier(nom: str) -> str:
    return re.sub(r'[^\w\-.]', '_', nom)


def telecharger_mp3(url: str, chemin_local: Path) -> bool:
    try:
        reponse = requests.get(url, stream=True, timeout=30, headers={
            "User-Agent": "OrnithoQuiz/1.0 (ornitho-quiz.fr)"
        })
        reponse.raise_for_status()
        with open(chemin_local, "wb") as f:
            for morceau in reponse.iter_content(chunk_size=8192):
                f.write(morceau)
        return True
    except Exception as e:
        print(f"      ❌ Erreur téléchargement : {e}")
        return False


def uploader_r2(chemin_local: Path, chemin_r2: str) -> bool:
    try:
        r2.upload_file(
            str(chemin_local),
            R2_BUCKET,
            chemin_r2,
            ExtraArgs={"ContentType": "audio/mpeg"}
        )
        return True
    except Exception as e:
        print(f"      ❌ Erreur upload R2 : {e}")
        return False


# ─── PROGRAMME PRINCIPAL ──────────────────────────────────────────────────────

TEMP_DIR.mkdir(exist_ok=True)

especes = [
    {"nom_latin": "Gallus gallus domesticus", "nom_commun": "Poule / Coq"},
    {"nom_latin": "Meleagris gallopavo",      "nom_commun": "Dindon"},
    {"nom_latin": "Aptenodytes patagonicus",  "nom_commun": "Manchot royal"},
    {"nom_latin": "Pelecanus onocrotalus",    "nom_commun": "Pélican blanc"},
]

# ── TEST : décommenter pour tester sur 3 espèces seulement ──
# especes = especes[:1]

print(f"=== Import espèces manquantes métropole ===")
print(f"{len(especes)} espèces à traiter\n")

total_especes_creees = 0
total_sons_ajoutes   = 0
total_ignores        = 0
total_erreurs        = 0

for espece in especes:
    nom_latin  = espece["nom_latin"]
    nom_commun = espece["nom_commun"]
    print(f"🔍 {nom_latin} ({nom_commun})...")

    # ── 1. Créer la fiche en BDD si elle n'existe pas ──
    cursor.execute("SELECT id, nom_commun FROM oiseaux WHERE nom_latin = %s", (nom_latin,))
    oiseau = cursor.fetchone()

    if not oiseau:
        # Nom commun français = nom anglais pour l'instant
        # (on pourra compléter avec import_descriptions.py plus tard)
        cursor.execute("""
            INSERT INTO oiseaux (nom_commun, nom_latin, region, quiz_enfants)
            VALUES (%s, %s, 'enfants', 1)
            """, (nom_commun, nom_latin))
        conn.commit()
        oiseau_id  = cursor.lastrowid
        total_especes_creees += 1
        print(f"   ➕ Fiche créée : {nom_commun}")
    else:
        oiseau_id  = oiseau["id"]
        nom_commun = oiseau["nom_commun"]
        print(f"   ✅ Fiche existante : {nom_commun}")

    # ── 2. Appel API Xeno-canto ──
    parties = nom_latin.strip().split(" ")
    genre   = parties[0] if len(parties) > 0 else ""
    sp      = parties[1] if len(parties) > 1 else ""

    params = {
        "query": f"gen:{genre} sp:{sp}",
        "page":  1,
        "key":   XC_API_KEY,
    }

    try:
        reponse_api = requests.get(XENO_API_URL, params=params, timeout=10, headers={
            "User-Agent": "OrnithoQuiz/1.0 (ornitho-quiz.fr)"
        })
        reponse_api.raise_for_status()
        data = reponse_api.json()
    except Exception as e:
        print(f"   ❌ Erreur API : {e}")
        total_erreurs += 1
        time.sleep(PAUSE)
        continue

    enregistrements = data.get("recordings", [])

    if not enregistrements:
        print(f"   ⚠️  Aucun enregistrement trouvé en métropole")
        total_ignores += 1
        time.sleep(PAUSE)
        continue

    # Filtre qualité A/B, trie (A en premier), limite à MAX_PAR_ESPECE
    enregistrements = [r for r in enregistrements if r.get("q") in QUALITES]
    enregistrements.sort(key=lambda r: r.get("q", "Z"))
    enregistrements = enregistrements[:MAX_PAR_ESPECE]

    if not enregistrements:
        print(f"   ⚠️  Aucun enregistrement de qualité A/B")
        total_ignores += 1
        time.sleep(PAUSE)
        continue

    # ── 3. Téléchargement et upload ──
    nb_ajoutes = 0

    for rec in enregistrements:
        url_mp3  = rec.get("file", "")
        xc_id    = rec.get("id", "")
        auteur   = rec.get("rec", "").strip()
        type_son = determiner_type_son(rec.get("type", ""))

        if not url_mp3:
            continue

        nom_fichier = f"XC{xc_id}.mp3"
        chemin_r2   = f"Par_espece/{nettoyer_nom_fichier(nom_commun)}/{nom_fichier}"
        chemin_bdd  = chemin_r2

        # Vérifie si déjà en BDD
        cursor.execute("SELECT COUNT(*) AS nb FROM sons WHERE chemin_fichier = %s", (chemin_bdd,))
        if cursor.fetchone()["nb"] > 0:
            continue

        type_xeno = rec.get("type", "son").lower()
        titre     = f"{type_xeno.capitalize()} — {nom_commun}"

        chemin_local = TEMP_DIR / nom_fichier
        print(f"   ⬇️  Téléchargement {nom_fichier}...")

        if not telecharger_mp3(url_mp3, chemin_local):
            total_erreurs += 1
            continue

        print(f"   ☁️  Upload vers R2...")

        if not uploader_r2(chemin_local, chemin_r2):
            total_erreurs += 1
            chemin_local.unlink(missing_ok=True)
            continue

        cursor.execute("""
            INSERT INTO sons (oiseau_id, titre, chemin_fichier, auteur, type_son)
            VALUES (%s, %s, %s, %s, %s)
        """, (oiseau_id, titre, chemin_bdd, auteur, type_son))
        conn.commit()

        chemin_local.unlink(missing_ok=True)
        nb_ajoutes        += 1
        total_sons_ajoutes += 1

    print(f"   ➕ {nb_ajoutes} son(s) ajouté(s)")
    time.sleep(PAUSE)

# ─── RÉSUMÉ ───────────────────────────────────────────────────────────────────
print(f"\n=== Terminé ===")
print(f"🐦 Espèces créées : {total_especes_creees}")
print(f"✅ Sons ajoutés   : {total_sons_ajoutes}")
print(f"⏭️  Ignorés        : {total_ignores}")
print(f"❌ Erreurs        : {total_erreurs}")

cursor.close()
conn.close()

try:
    TEMP_DIR.rmdir()
except OSError:
    pass