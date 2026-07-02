"""
Script d'import Xeno-canto → Cloudflare R2 → MySQL
====================================================
Pour chaque espèce en BDD :
  1. Interroge l'API Xeno-canto (sons enregistrés en France)
  2. Télécharge les MP3
  3. Les uploade sur Cloudflare R2
  4. Insère les métadonnées en BDD

Usage : python scripts/import_xenocanto.py
"""

import boto3          # pour uploader sur R2 (compatible S3)
import requests       # pour appeler l'API et télécharger les MP3
import mysql.connector # pour la BDD
import time
import re
import os
from pathlib import Path
from dotenv import load_dotenv

# Charger les variables d'environnement depuis le fichier .env
load_dotenv()

# ─── CONFIGURATION ────────────────────────────────────────────────────────────
# Xeno-canto
XENO_API_URL   = "https://xeno-canto.org/api/3/recordings"
XC_API_KEY   = "ee1d41e190bb158119033ad5dadb13800a663428"
QUALITES       = ["A", "B"]   # on garde seulement les meilleurs enregistrements
MAX_PAR_ESPECE = 5            # nombre max de sons à ajouter par espèce
PAYS           = "France"      # on se concentre sur la France pour l'instant

# Cloudflare R2
R2_ENDPOINT          = os.getenv('R2_ENDPOINT')
R2_ACCESS_KEY_ID     = os.getenv('R2_ACCESS_KEY_ID')
R2_SECRET_ACCESS_KEY = os.getenv('R2_SECRET_ACCESS_KEY')
R2_BUCKET            = os.getenv('R2_BUCKET')
R2_PUBLIC_URL        = os.getenv('R2_PUBLIC_URL')
    
# Base de données (Alwaysdata en prod, localhost en local)
DB_CONFIG = {
    'host':     os.getenv('DB_HOST'),
    'port':     int(os.getenv('DB_PORT', 3306)),
    'database': os.getenv('DB_NAME'),
    'user':     os.getenv('DB_USER'),
    'password': os.getenv('DB_PASSWORD'),
}

# Dossier temporaire pour stocker les MP3 avant upload
TEMP_DIR = Path("temp_audio")
PAUSE_SECONDES = 0.5  # pause entre chaque requête API

# ─── CONNEXION R2 ─────────────────────────────────────────────────────────────
# boto3 est la librairie Amazon S3 — R2 est compatible S3
# on lui dit d'utiliser l'endpoint Cloudflare au lieu d'Amazon
r2 = boto3.client(
    "s3",
    endpoint_url         = R2_ENDPOINT,
    aws_access_key_id    = R2_ACCESS_KEY_ID,
    aws_secret_access_key = R2_SECRET_ACCESS_KEY,
    region_name          = "auto",
)

# ─── CONNEXION BDD ────────────────────────────────────────────────────────────
conn   = mysql.connector.connect(**DB_CONFIG)
cursor = conn.cursor(dictionary=True)  # dictionary=True → résultats en dict comme PHP

# ─── FONCTIONS ────────────────────────────────────────────────────────────────

def determiner_type_son(type_xeno: str) -> str:
    """Convertit le type Xeno-canto en type_son de notre BDD."""
    t = type_xeno.lower()
    if "song" in t or "chant" in t:
        return "Chant"
    if "call" in t or "cri" in t or "alarm" in t or "flight" in t:
        return "Cri"
    if "drum" in t:
        return "Tambourinage"
    return "Autre"


def nettoyer_nom_fichier(nom: str) -> str:
    """Supprime les caractères interdits dans un nom de fichier."""
    # Remplace tout ce qui n'est pas lettre, chiffre, tiret, underscore, point
    return re.sub(r'[^\w\-.]', '_', nom)


def telecharger_mp3(url: str, chemin_local: Path) -> bool:
    """
    Télécharge un fichier MP3 depuis une URL vers un fichier local.
    Retourne True si succès, False sinon.
    """
    try:
        # stream=True → télécharge par morceaux, évite de charger tout en mémoire
        reponse = requests.get(url, stream=True, timeout=30, headers={
            "User-Agent": "OrnithoQuiz/1.0 (ornitho-quiz.fr)"
        })
        reponse.raise_for_status()  # lève une exception si erreur HTTP

        with open(chemin_local, "wb") as f:
            for morceau in reponse.iter_content(chunk_size=8192):
                f.write(morceau)
        return True

    except Exception as e:
        print(f"      ❌ Erreur téléchargement : {e}")
        return False


def uploader_r2(chemin_local: Path, chemin_r2: str) -> bool:
    """
    Uploade un fichier local sur Cloudflare R2.
    chemin_r2 = chemin dans le bucket (ex: 'Par_espece/Merle/XC123.mp3')
    Retourne True si succès, False sinon.
    """
    try:
        r2.upload_file(
            str(chemin_local),  # fichier local
            R2_BUCKET,          # nom du bucket
            chemin_r2,          # chemin dans le bucket
            ExtraArgs={"ContentType": "audio/mpeg"}  # type MIME
        )
        return True

    except Exception as e:
        print(f"      ❌ Erreur upload R2 : {e}")
        return False


# ─── PROGRAMME PRINCIPAL ──────────────────────────────────────────────────────

# Crée le dossier temporaire s'il n'existe pas
TEMP_DIR.mkdir(exist_ok=True)

# Récupère toutes les espèces de la BDD
cursor.execute("SELECT id, nom_commun, nom_latin FROM oiseaux ORDER BY nom_commun")
especes = cursor.fetchall()

# ── TEST : décommente pour tester sur 3 espèces seulement ──
# especes = especes[:3]

print(f"=== Import Xeno-canto → R2 ===")
print(f"{len(especes)} espèces trouvées en BDD\n")

total_ajoutes = 0
total_ignores = 0
total_erreurs = 0

for espece in especes:

    # Ignore si nom latin manquant
    if not espece["nom_latin"]:
        print(f"⚠️  {espece['nom_commun']} — nom latin manquant, ignoré")
        total_ignores += 1
        continue

    print(f"🔍 {espece['nom_commun']} ({espece['nom_latin']})...")

    # Compte les sons déjà en BDD pour cette espèce
    cursor.execute("SELECT COUNT(*) AS nb FROM sons WHERE oiseau_id = %s", (espece["id"],))
    nb_existants = cursor.fetchone()["nb"]

    if nb_existants >= 20:
        print(f"   ✅ Déjà {nb_existants} sons, on passe")
        total_ignores += 1
        continue

    # ── Appel API Xeno-canto ──
    # On sépare le nom latin en genre + espèce pour la requête
    parties = espece["nom_latin"].strip().split(" ")
    genre   = parties[0] if len(parties) > 0 else ""
    sp      = parties[1] if len(parties) > 1 else ""

    params = {
        "query": f"gen:{genre} sp:{sp} cnt:{PAYS}",
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
        time.sleep(PAUSE_SECONDES)
        continue

    enregistrements = data.get("recordings", [])

    if not enregistrements:
        print(f"   ⚠️  Aucun enregistrement trouvé en France")
        total_ignores += 1
        time.sleep(PAUSE_SECONDES)
        continue

    # Filtre qualité A/B et trie (A en premier)
    enregistrements = [r for r in enregistrements if r.get("q") in QUALITES]
    enregistrements.sort(key=lambda r: r.get("q", "Z"))
    enregistrements = enregistrements[:MAX_PAR_ESPECE]

    if not enregistrements:
        print(f"   ⚠️  Aucun enregistrement de qualité A/B")
        total_ignores += 1
        time.sleep(PAUSE_SECONDES)
        continue

    # ── Traitement de chaque enregistrement ──
    nb_ajoutes = 0

    for rec in enregistrements:

        url_mp3  = rec.get("file", "")
        xc_id    = rec.get("id", "")
        auteur   = rec.get("rec", "").strip()
        type_son = determiner_type_son(rec.get("type", ""))

        if not url_mp3:
            continue

        # Nom du fichier dans R2 : XC123456.mp3
        nom_fichier = f"XC{xc_id}.mp3"
        chemin_r2   = f"Par_espece/{nettoyer_nom_fichier(espece['nom_commun'])}/{nom_fichier}"
        chemin_bdd  = chemin_r2  # chemin relatif stocké en BDD (sans l'URL de base)

        # Vérifie si ce son est déjà en BDD
        cursor.execute("SELECT COUNT(*) AS nb FROM sons WHERE chemin_fichier = %s", (chemin_bdd,))
        if cursor.fetchone()["nb"] > 0:
            continue

        # Titre lisible
        type_xeno = rec.get("type", "son").lower()
        titre = f"{type_xeno.capitalize()} — {espece['nom_commun']}"

        # ── Téléchargement ──
        chemin_local = TEMP_DIR / nom_fichier
        print(f"   ⬇️  Téléchargement {nom_fichier}...")

        if not telecharger_mp3(url_mp3, chemin_local):
            total_erreurs += 1
            continue

        # ── Upload R2 ──
        print(f"   ☁️  Upload vers R2...")

        if not uploader_r2(chemin_local, chemin_r2):
            total_erreurs += 1
            chemin_local.unlink(missing_ok=True)  # supprime le fichier temporaire
            continue

        # ── Insertion BDD ──
        cursor.execute("""
            INSERT INTO sons (oiseau_id, titre, chemin_fichier, auteur, type_son)
            VALUES (%s, %s, %s, %s, %s)
        """, (espece["id"], titre, chemin_bdd, auteur, type_son))
        conn.commit()

        # Supprime le fichier temporaire local
        chemin_local.unlink(missing_ok=True)

        nb_ajoutes    += 1
        total_ajoutes += 1

    print(f"   ➕ {nb_ajoutes} son(s) ajouté(s)")
    time.sleep(PAUSE_SECONDES)

# ─── RÉSUMÉ ───────────────────────────────────────────────────────────────────
print(f"\n=== Terminé ===")
print(f"✅ Ajoutés  : {total_ajoutes}")
print(f"⏭️  Ignorés  : {total_ignores}")
print(f"❌ Erreurs  : {total_erreurs}")

# Nettoyage
cursor.close()
conn.close()

# Supprime le dossier temporaire si vide
try:
    TEMP_DIR.rmdir()
except OSError:
    pass  # pas vide, on laisse