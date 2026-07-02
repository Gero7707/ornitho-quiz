"""
Import des espèces zone Réunion-Madagascar-Mayotte
===================================================
Pour chaque espèce trouvée sur Xeno-canto dans la zone :
  1. Skip si l'espèce existe déjà en BDD
  2. Crée la fiche en BDD (table oiseaux) avec region='reu_mad_may'
  3. Télécharge les MP3 (max 3 par espèce, qualité A/B)
  4. Les uploade sur Cloudflare R2 sous Par_espece/{nom_commun_fr}/
  5. Insère les métadonnées en BDD (table sons)
  6. Traduit les noms anglais en français via Google Translate

Usage : python3 scripts/import_reu_mad_may.py
"""

import boto3
import requests
import mysql.connector
import time
import re
import os
from pathlib import Path
from dotenv import load_dotenv

load_dotenv()
for key, value in __import__('dotenv').dotenv_values().items():
    os.environ.setdefault(key, value)

# ─── CONFIGURATION ────────────────────────────────────────────────────────────
XENO_API_URL   = "https://xeno-canto.org/api/3/recordings"
XENO_API_KEY   = os.getenv('XC_API_KEY')
QUALITES       = ["A", "B"]
MAX_PAR_ESPECE = 3
BOX_ZONE       = "-26.0,43.0,-12.0,58.0"
REGION         = "reu_mad_may"
PAUSE          = 0.5

GOOGLE_API_KEY       = os.getenv('GOOGLE_API_KEY')
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

def traduire_nom(nom_anglais: str) -> str:
    """Traduit un nom anglais en français via Google Translate."""
    if not GOOGLE_API_KEY:
        return nom_anglais
    try:
        url = "https://translation.googleapis.com/language/translate/v2"
        params = {
            "q": nom_anglais,
            "source": "en",
            "target": "fr",
            "key": GOOGLE_API_KEY,
        }
        r = requests.post(url, params=params, timeout=10)
        r.raise_for_status()
        data = r.json()
        return data["data"]["translations"][0]["translatedText"]
    except Exception as e:
        print(f"      ⚠️  Erreur traduction : {e}")
        return nom_anglais


def determiner_type_son(type_xeno: str) -> str:
    t = type_xeno.lower()
    if "song" in t or "chant" in t:
        return "Chant"
    if "alarm" in t:
        return "Cri d'alarme"
    if "flight" in t:
        return "Cri de vol"
    if "call" in t or "cri" in t:
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


def recuperer_especes_xc() -> list:
    """Récupère toutes les espèces disponibles dans la zone via XC."""
    print("🌍 Récupération des espèces depuis Xeno-canto...")
    especes = {}
    page = 1

    while True:
        params = {
            "query": f"box:{BOX_ZONE} grp:birds",
            "key": XENO_API_KEY,
            "page": page,
        }
        try:
            r = requests.get(XENO_API_URL, params=params, timeout=15, headers={
                "User-Agent": "OrnithoQuiz/1.0 (ornitho-quiz.fr)"
            })
            r.raise_for_status()
            data = r.json()
        except Exception as e:
            print(f"❌ Erreur API page {page} : {e}")
            break

        nb_pages = int(data.get("numPages", 0))

        for rec in data.get("recordings", []):
            if rec.get("q") not in QUALITES:
                continue
            nom_latin = f"{rec.get('gen', '')} {rec.get('sp', '')}".strip()
            nom_anglais = rec.get("en", "")
            if nom_latin and nom_latin not in especes:
                especes[nom_latin] = nom_anglais

        print(f"  Page {page}/{nb_pages} — {len(especes)} espèces A/B trouvées")

        if page >= nb_pages:
            break
        page += 1
        time.sleep(PAUSE)

    return [{"nom_latin": k, "nom_anglais": v} for k, v in especes.items()]


# ─── PROGRAMME PRINCIPAL ──────────────────────────────────────────────────────

TEMP_DIR.mkdir(exist_ok=True)

# ── TEST : décommenter pour tester sur 3 espèces seulement ──

especes = recuperer_especes_xc()
# especes = especes[:3]

print(f"\n=== Import zone Réunion-Madagascar-Mayotte ===")
print(f"{len(especes)} espèces à traiter\n")

total_especes_creees  = 0
total_especes_skippees = 0
total_sons_ajoutes    = 0
total_ignores         = 0
total_erreurs         = 0

for espece in especes:
    nom_latin   = espece["nom_latin"]
    nom_anglais = espece["nom_anglais"]

    print(f"🔍 {nom_latin} ({nom_anglais})...")

    # ── 1. Skip si l'espèce existe déjà en BDD (toutes régions) ──
    cursor.execute("SELECT id, nom_commun FROM oiseaux WHERE nom_latin = %s", (nom_latin,))
    oiseau = cursor.fetchone()

    if oiseau:
        print(f"   ⏭️  Déjà en BDD ({oiseau['nom_commun']}) — skip")
        total_especes_skippees += 1
        time.sleep(PAUSE)
        continue

    # ── 2. Traduction du nom commun ──
    nom_commun = traduire_nom(nom_anglais)
    print(f"   🌐 Nom traduit : {nom_commun}")

    # ── 3. Créer la fiche en BDD ──
    cursor.execute("""
        INSERT INTO oiseaux (nom_commun, nom_latin, region)
        VALUES (%s, %s, %s)
    """, (nom_commun, nom_latin, REGION))
    conn.commit()
    oiseau_id = cursor.lastrowid
    total_especes_creees += 1
    print(f"   ➕ Fiche créée (id={oiseau_id})")

    # ── 4. Appel API Xeno-canto pour récupérer les sons ──
    parties = nom_latin.strip().split(" ")
    genre   = parties[0] if len(parties) > 0 else ""
    sp      = parties[1] if len(parties) > 1 else ""

    params = {
        "query": f"gen:{genre} sp:{sp} box:{BOX_ZONE}",
        "page":  1,
        "key":   XENO_API_KEY,
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
    enregistrements = [r for r in enregistrements if r.get("q") in QUALITES]
    enregistrements.sort(key=lambda r: r.get("q", "Z"))
    enregistrements = enregistrements[:MAX_PAR_ESPECE]

    if not enregistrements:
        print(f"   ⚠️  Aucun enregistrement de qualité A/B")
        total_ignores += 1
        time.sleep(PAUSE)
        continue

    # ── 5. Téléchargement et upload ──
    nb_ajoutes = 0
    nom_dossier = nettoyer_nom_fichier(nom_commun)

    for rec in enregistrements:
        url_mp3  = rec.get("file", "")
        xc_id    = rec.get("id", "")
        auteur   = rec.get("rec", "").strip()
        type_son = determiner_type_son(rec.get("type", ""))
        type_xeno = rec.get("type", "son")

        if not url_mp3:
            continue

        nom_fichier = f"XC{xc_id}.mp3"
        chemin_r2   = f"Par_espece/{nom_dossier}/{nom_fichier}"
        chemin_bdd  = chemin_r2

        cursor.execute("SELECT COUNT(*) AS nb FROM sons WHERE chemin_fichier = %s", (chemin_bdd,))
        if cursor.fetchone()["nb"] > 0:
            continue

        titre = f"{nom_commun} - {type_son}"

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

    print(f"   ✅ {nb_ajoutes} son(s) ajouté(s)")
    time.sleep(PAUSE)

# ─── RÉSUMÉ ───────────────────────────────────────────────────────────────────
print(f"\n=== Terminé ===")
print(f"🐦 Espèces créées  : {total_especes_creees}")
print(f"⏭️  Déjà en BDD     : {total_especes_skippees}")
print(f"🎵 Sons ajoutés    : {total_sons_ajoutes}")
print(f"⚠️  Sans son A/B    : {total_ignores}")
print(f"❌ Erreurs         : {total_erreurs}")

cursor.close()
conn.close()

try:
    TEMP_DIR.rmdir()
except OSError:
    pass