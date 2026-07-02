#!/usr/bin/env python3
"""
import_antilles.py
------------------
Importe les espèces des Antilles françaises (Guadeloupe, Martinique,
Saint-Barthélemy, Saint-Martin) depuis Avibase + Xeno-canto.

Logique :
1. Scrape Avibase pour chaque territoire → liste des espèces (nom latin, nom fr)
2. Exclut les espèces Rare/Accidentel
3. Pour chaque espèce :
   - Si déjà en BDD → on ignore (pas de doublon, pas de modification)
   - Si absente → crée la fiche (region='antilles') + importe jusqu'à 3 sons XC
4. Traduction Google Translate si le nom commun est en anglais

Usage :
    python3 import_antilles.py --dry-run
    python3 import_antilles.py
"""

import os
import time
import argparse
import requests
import boto3
import mysql.connector
from bs4 import BeautifulSoup
from dotenv import load_dotenv

# ── Chargement .env ──────────────────────────────────────────────────────────
load_dotenv()
for key in ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD',
            'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY',
            'R2_BUCKET', 'R2_ENDPOINT', 'R2_PUBLIC_URL',
            'XC_API_KEY', 'GOOGLE_API_KEY']:
    val = os.getenv(key)
    if val:
        os.putenv(key, val)

# ── Config ───────────────────────────────────────────────────────────────────
XC_API_BASE  = "https://xeno-canto.org/api/3/recordings"
XC_API_KEY   = os.getenv('XC_API_KEY', '')
GOOGLE_KEY   = os.getenv('GOOGLE_API_KEY', '')
MAX_SONS     = 3

TERRITOIRES = {
    'Guadeloupe':      'GP',
    'Martinique':      'MQ',
    'Saint-Barthélemy':'BL',
    'Saint-Martin':    'MF',
}

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
    for key, val in sorted(TYPE_MAPPING.items(), key=lambda x: len(x[0]), reverse=True):
        if key in type_en:
            return val
    return type_en.capitalize()

def normaliser_type(type_en: str) -> str:
    type_en = type_en.lower().strip().rstrip('?').strip()
    for key in sorted(TYPE_MAPPING.keys(), key=len, reverse=True):
        if key in type_en:
            return key
    return type_en

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

# ── Google Translate ─────────────────────────────────────────────────────────
def traduire_fr(texte: str) -> str:
    if not GOOGLE_KEY:
        return texte
    try:
        resp = requests.post(
            'https://translation.googleapis.com/language/translate/v2',
            params={'key': GOOGLE_KEY},
            json={'q': texte, 'target': 'fr', 'source': 'en'},
            timeout=10
        )
        return resp.json()['data']['translations'][0]['translatedText']
    except Exception as e:
        print(f"    Erreur traduction : {e}")
        return texte

# ── Avibase scraping ─────────────────────────────────────────────────────────
def scraper_avibase(code_region: str) -> list:
    """Retourne une liste de dicts {nom_latin, nom_commun, nom_en}."""
    url = (f"https://avibase.bsc-eoc.org/checklist.jsp"
           f"?lang=FR&p2=1&list=avibase&synlang=FR"
           f"&region={code_region}&version=text&lifelist=&highlight=0")

    resp = requests.get(url, headers={'User-Agent': 'Mozilla/5.0'}, timeout=30)
    resp.raise_for_status()
    soup = BeautifulSoup(resp.text, 'html.parser')

    especes = []
    for tr in soup.select('table tr.highlight1, table tr.highlight2'):
        tds = tr.find_all('td')
        if len(tds) < 3:
            continue

        nom_en     = tds[0].get_text(strip=True)
        nom_latin  = tds[1].get_text(strip=True)
        nom_fr     = tds[2].get_text(strip=True)
        statut     = tds[3].get_text(strip=True) if len(tds) > 3 else ''

        # Exclure Rare/Accidentel
        if 'Rare' in statut or 'Accidentel' in statut:
            continue

        # Exclure les entrées non valides
        if not nom_latin or 'sp.' in nom_latin:
            continue
        # Exclure sous-espèces, familles, hybrides
        if len(nom_latin.split()) != 2:
            continue

        especes.append({
            'nom_latin':  nom_latin,
            'nom_commun': nom_fr if nom_fr else nom_en,
            'nom_en':     nom_en,
        })

    return especes

# ── Xeno-canto ───────────────────────────────────────────────────────────────
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

# ── R2 upload ────────────────────────────────────────────────────────────────
def upload_son_r2(r2_client, url: str, nom_commun: str, filename: str, dry_run: bool) -> str:
    dossier = nom_commun.replace(' ', '_')
    r2_key = f"{dossier}/{filename}"

    if dry_run:
        return r2_key

    print(f"      Téléchargement...", end=' ', flush=True)
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

# ── DB helpers ───────────────────────────────────────────────────────────────
def espece_existe(cursor, nom_latin: str) -> bool:
    cursor.execute("SELECT id FROM oiseaux WHERE nom_latin = %s", (nom_latin,))
    return cursor.fetchone() is not None

def creer_espece(cursor, nom_commun: str, nom_latin: str, dry_run: bool) -> int:
    if dry_run:
        print(f"    [DRY-RUN] INSERT espèce : {nom_commun} ({nom_latin})")
        return -1
    cursor.execute(
        "INSERT INTO oiseaux (nom_commun, nom_latin, region) VALUES (%s, %s, 'antilles')",
        (nom_commun, nom_latin)
    )
    return cursor.lastrowid

def inserer_son(cursor, oiseau_id: int, titre: str, chemin: str, dry_run: bool):
    if dry_run:
        print(f"      [DRY-RUN] INSERT son : {titre}")
        return
    cursor.execute(
        "INSERT INTO sons (oiseau_id, titre, chemin_fichier) VALUES (%s, %s, %s)",
        (oiseau_id, titre, chemin)
    )

# ── Programme principal ──────────────────────────────────────────────────────
def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--dry-run', action='store_true')
    parser.add_argument('--limite', type=int, default=0)
    args = parser.parse_args()

    dry_run = args.dry_run
    if dry_run:
        print("=== MODE DRY-RUN — aucune modification ===\n")

    db = get_db()
    cursor = db.cursor(dictionary=True)
    r2 = None if dry_run else get_r2()

    stats = {
        'especes_nouvelles': 0,
        'especes_existantes': 0,
        'especes_ignorees': 0,
        'sons_ajoutes': 0,
    }

    # Collecter toutes les espèces uniques des 4 territoires
    toutes_especes = {}  # nom_latin → dict

    for territoire, code in TERRITOIRES.items():
        print(f"Scraping Avibase — {territoire}...")
        try:
            especes = scraper_avibase(code)
            print(f"  {len(especes)} espèces trouvées")
            for e in especes:
                if e['nom_latin'] not in toutes_especes:
                    toutes_especes[e['nom_latin']] = e
        except Exception as ex:
            print(f"  ERREUR : {ex}")
        time.sleep(1)

    print(f"\n{len(toutes_especes)} espèces uniques au total\n")

    traites = 0
    for nom_latin, espece in sorted(toutes_especes.items()):
        if args.limite and traites >= args.limite:
            break

        nom_commun = espece['nom_commun']
        nom_en     = espece['nom_en']

        # Traduire si nom commun est en anglais (ascii pur)
        if nom_commun.isascii():
            nom_commun = traduire_fr(nom_en)
            print(f"  Traduit : {nom_en} → {nom_commun}")

        # Vérifier si l'espèce existe déjà en BDD
        if espece_existe(cursor, nom_latin):
            stats['especes_existantes'] += 1
            continue

        traites += 1
        print(f"\n[NOUVELLE] {nom_commun} ({nom_latin})")

        # Créer la fiche espèce
        oiseau_id = creer_espece(cursor, nom_commun, nom_latin, dry_run)
        if not dry_run:
            db.commit()
        stats['especes_nouvelles'] += 1

        # Chercher les sons sur XC
        try:
            recs = fetch_xc_espece(nom_latin)
        except Exception as e:
            print(f"  ERREUR XC : {e}")
            continue

        if not recs:
            print(f"  Aucun enregistrement XC")
            continue

        # Trier : qualité A d'abord, puis par type prioritaire
        def sort_key(r):
            type_brut = r.get('type', '').split(',')[0].strip().lower()
            q = 0 if r.get('q') == 'A' else 1
            t = TYPE_PRIORITE.index(type_brut) if type_brut in TYPE_PRIORITE else 99
            return (t, q)

        recs_tries = sorted(recs, key=sort_key)

        types_ajoutes = set()
        for rec in recs_tries:
            if len(types_ajoutes) >= MAX_SONS:
                break

            type_brut = rec.get('type', '').split(',')[0].strip()
            type_norm = normaliser_type(type_brut)
            if type_norm not in TYPE_MAPPING:
                continue
            type_fr_str = type_fr(type_brut)
            if type_fr_str in types_ajoutes:
                continue
            audio_url = rec.get('file', '')
            if not audio_url:
                continue

            xc_id    = rec.get('id', '')
            filename = f"XC{xc_id}.mp3"
            titre    = f"{nom_commun} ({nom_latin}) - {type_fr(type_brut)}"

            try:
                r2_key = upload_son_r2(r2, audio_url, nom_commun, filename, dry_run)
                inserer_son(cursor, oiseau_id, titre, r2_key, dry_run)
                types_ajoutes.add(type_fr_str)
                stats['sons_ajoutes'] += 1
                print(f"  + {titre}")
                if not dry_run:
                    db.commit()
                time.sleep(0.3)
            except Exception as e:
                print(f"  ERREUR {filename} : {e}")
                continue

        if not types_ajoutes:
            print(f"  Aucun son disponible sur XC")

    print(f"""
=== Résumé ===
Espèces nouvelles créées  : {stats['especes_nouvelles']}
Espèces déjà en BDD       : {stats['especes_existantes']}
Sons ajoutés              : {stats['sons_ajoutes']}
{"(dry-run — rien n'a été modifié)" if dry_run else ""}
""")

    cursor.close()
    db.close()

if __name__ == '__main__':
    main()