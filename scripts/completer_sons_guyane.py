#!/usr/bin/env python3
"""
completer_sons_guyane.py
------------------------
Pour les espèces déjà en BDD avec moins de 3 sons ou moins de 3 types distincts,
cherche sur Xeno-canto par nom latin (sans filtre géographique) et complète
jusqu'à 3 sons de types distincts.

Usage :
    python3 completer_sons_guyane.py --dry-run --limite 5
    python3 completer_sons_guyane.py
"""

import os
import time
import argparse
import requests
import boto3
import mysql.connector
from dotenv import load_dotenv

# ── Chargement .env ──────────────────────────────────────────────────────────
load_dotenv()
for key in ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD',
            'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY',
            'R2_BUCKET', 'R2_ENDPOINT', 'R2_PUBLIC_URL', 'XC_API_KEY']:
    val = os.getenv(key)
    if val:
        os.putenv(key, val)

# ── Config ───────────────────────────────────────────────────────────────────
XC_API_BASE = "https://xeno-canto.org/api/3/recordings"
XC_API_KEY  = os.getenv('XC_API_KEY', '')
MAX_SONS    = 3

TYPE_MAPPING = {
    'song':         'Chant',
    'call':         'Cri',
    'alarm call':   "Cri d'alarme",
    'flight call':  'Cri de vol',
    'begging call': 'Quémandage',
    'drumming':     'Tambourinage',
    'dawn song':    "Chant de l'aube",
    'subsong':      'Sous-chant',
}

TYPE_PRIORITE = ['song', 'call', 'alarm call', 'flight call', 'begging call', 'dawn song', 'drumming', 'subsong']

def type_fr(type_en: str) -> str:
    type_en = type_en.lower().strip()
    for key, val in TYPE_MAPPING.items():
        if key in type_en:
            return val
    return type_en.capitalize()

def normaliser_type(type_en: str) -> str:
    type_en = type_en.lower().strip().rstrip('?').strip()
    for key in sorted(TYPE_MAPPING.keys(), key=len, reverse=True):
        if key in type_en:
            return key
    return type_en

TYPE_MAPPING_FR = {v.lower(): k for k, v in TYPE_MAPPING.items()}

def normaliser_type_fr(type_fr_str: str) -> str:
    type_fr_str = type_fr_str.lower().strip()
    for fr, en in sorted(TYPE_MAPPING_FR.items(), key=lambda x: len(x[0]), reverse=True):
        if fr in type_fr_str:
            return en
    return normaliser_type(type_fr_str)

# ── Connexions ───────────────────────────────────────────────────────────────
def get_db():
    return mysql.connector.connect(
        host=os.getenv('DB_HOST'),
        database=os.getenv('DB_NAME'),
        user=os.getenv('DB_USER'),
        password=os.getenv('DB_PASSWORD'),
        charset='utf8mb4'
    )

def get_r2():
    return boto3.client(
        's3',
        endpoint_url=os.getenv('R2_ENDPOINT'),
        aws_access_key_id=os.getenv('R2_ACCESS_KEY_ID'),
        aws_secret_access_key=os.getenv('R2_SECRET_ACCESS_KEY'),
    )

# ── Xeno-canto : recherche par nom latin, toutes pages ──────────────────────
def fetch_xc_espece(nom_latin: str) -> list:
    all_recordings = []
    page = 1
    while True:
        params = {'query': f'sp:"{nom_latin}" grp:birds', 'page': page}
        if XC_API_KEY:
            params['key'] = XC_API_KEY

        resp = requests.get(XC_API_BASE, params=params, timeout=30)
        resp.raise_for_status()
        data = resp.json()

        all_recordings.extend(data.get('recordings', []))
        if page >= int(data.get('numPages', 1)):
            break
        page += 1
        time.sleep(0.5)

    return all_recordings

# ── DB helpers ───────────────────────────────────────────────────────────────
def fetch_especes_bdd(cursor) -> list:
    cursor.execute("SELECT id, nom_latin, nom_commun FROM oiseaux ORDER BY nom_commun")
    return cursor.fetchall()

def fetch_sons_existants(cursor, oiseau_id: int) -> list:
    cursor.execute(
        "SELECT id, titre, chemin_fichier FROM sons WHERE oiseau_id = %s",
        (oiseau_id,)
    )
    return cursor.fetchall()

# ── R2 upload ────────────────────────────────────────────────────────────────
def upload_son_r2(r2_client, url: str, nom_commun: str, filename: str, dry_run: bool) -> str:
    dossier = nom_commun.replace(' ', '_')
    r2_key = f"{dossier}/{filename}"

    if dry_run:
        return r2_key

    print(f"    Téléchargement {url} ...", end=' ', flush=True)
    resp = requests.get(url, timeout=60)
    resp.raise_for_status()

    r2_client.put_object(
        Bucket=os.getenv('R2_BUCKET'),
        Key=r2_key,
        Body=resp.content,
        ContentType='audio/mpeg',
    )
    print(f"OK ({len(resp.content) // 1024} Ko)")
    return r2_key

def inserer_son(cursor, oiseau_id: int, titre: str, chemin: str, dry_run: bool):
    if dry_run:
        print(f"    [DRY-RUN] INSERT : {titre} → {chemin}")
        return
    cursor.execute(
        "INSERT INTO sons (oiseau_id, titre, chemin_fichier) VALUES (%s, %s, %s)",
        (oiseau_id, titre, chemin)
    )

# ── Programme principal ──────────────────────────────────────────────────────
def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--dry-run', action='store_true')
    parser.add_argument('--limite', type=int, default=0, help="Nombre max d'espèces à traiter")
    args = parser.parse_args()

    dry_run = args.dry_run
    if dry_run:
        print("=== MODE DRY-RUN — aucune modification ===\n")

    db = get_db()
    cursor = db.cursor(dictionary=True)
    r2 = None if dry_run else get_r2()

    print("Chargement des espèces en BDD...")
    especes = fetch_especes_bdd(cursor)
    print(f"  {len(especes)} espèces\n")

    stats = {'traitees': 0, 'sons_ajoutes': 0, 'ignorees': 0}
    traites = 0

    for oiseau in especes:
        if args.limite and traites >= args.limite:
            break

        oiseau_id  = oiseau['id']
        nom_latin  = oiseau['nom_latin']
        nom_commun = oiseau['nom_commun']

        sons_existants = fetch_sons_existants(cursor, oiseau_id)
        nb_sons = len(sons_existants)

        if nb_sons >= MAX_SONS:
            stats['ignorees'] += 1
            continue

        # Types déjà présents en BDD
        types_existants = set()
        for son in sons_existants:
            titre = son['titre']
            if ' - ' in titre:
                type_part = titre.split(' - ')[-1]
            else:
                type_part = titre
            types_existants.add(normaliser_type_fr(type_part))

        traites += 1
        stats['traitees'] += 1
        print(f"[{nom_commun}] ({nom_latin}) — {nb_sons} sons, types : {types_existants or '∅'}")

        # Requête XC par nom latin uniquement
        try:
            recs = fetch_xc_espece(nom_latin)
        except Exception as e:
            print(f"  ERREUR XC : {e}")
            continue

        if not recs:
            print(f"  Aucun enregistrement XC trouvé")
            continue

        # Trier : qualité A d'abord, puis par type prioritaire
        recs_tries = sorted(
            recs,
            key=lambda r: (
                0 if r.get('q') == 'A' else 1,
                TYPE_PRIORITE.index(r.get('type', '').lower()) if r.get('type', '').lower() in TYPE_PRIORITE else 99
            )
        )

        types_ajoutes = set()
        for rec in recs_tries:
            if nb_sons + len(types_ajoutes) >= MAX_SONS:
                break

            type_norm = normaliser_type(rec.get('type', ''))

            # Skip si type inconnu ou vide
            if type_norm not in TYPE_MAPPING:
                continue

            # Skip si type déjà présent
            if type_norm in types_existants or type_norm in types_ajoutes:
                continue

            audio_url = rec.get('file', '')
            if not audio_url:
                continue

            xc_id    = rec.get('id', '')
            filename = f"XC{xc_id}.mp3"
            titre    = f"{nom_commun} ({nom_latin}) - {type_fr(rec.get('type', ''))}"

            try:
                r2_key = upload_son_r2(r2, audio_url, nom_commun, filename, dry_run)
                inserer_son(cursor, oiseau_id, titre, r2_key, dry_run)
                types_ajoutes.add(type_norm)
                stats['sons_ajoutes'] += 1
                print(f"  + {titre}")
                if not dry_run:
                    db.commit()
                time.sleep(0.3)
            except Exception as e:
                print(f"  ERREUR {filename} : {e}")
                continue

        if not types_ajoutes:
            print(f"  Aucun type nouveau disponible sur XC")

    print(f"""
=== Résumé ===
Espèces traitées  : {stats['traitees']}
Espèces ignorées  : {stats['ignorees']} (déjà {MAX_SONS} sons)
Sons ajoutés      : {stats['sons_ajoutes']}
{"(dry-run — rien n'a été modifié)" if dry_run else ""}
""")

    cursor.close()
    db.close()

if __name__ == '__main__':
    main()