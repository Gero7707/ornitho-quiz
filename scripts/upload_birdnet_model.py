"""
upload_birdnet_model.py — Upload les fichiers modèle BirdNET sur R2
===================================================================
Usage: python scripts/upload_birdnet_model.py
"""

import os
import boto3
from dotenv import load_dotenv

load_dotenv()

R2_ENDPOINT = os.getenv('R2_ENDPOINT')
R2_ACCESS_KEY_ID = os.getenv('R2_ACCESS_KEY_ID')
R2_SECRET_ACCESS_KEY = os.getenv('R2_SECRET_ACCESS_KEY')
R2_BUCKET = os.getenv('R2_BUCKET', 'ornitho-quiz-audio')

# Chemin vers le repo birdnet-web cloné
BIRDNET_WEB_PATH = r'C:\Env\workspace\birdnet-web'

# Fichiers à uploader sur R2 (sous le préfixe birdnet-model/)
FILES_TO_UPLOAD = []

# 1. Modèle principal (model.json + shards)
model_dir = os.path.join(BIRDNET_WEB_PATH, 'models', 'birdnet')
for f in os.listdir(model_dir):
    filepath = os.path.join(model_dir, f)
    if os.path.isfile(filepath) and (f.endswith('.json') or f.endswith('.bin')):
        FILES_TO_UPLOAD.append((filepath, f'birdnet-model/{f}'))

# 2. Modèle géographique (area-model)
area_dir = os.path.join(model_dir, 'area-model')
for f in os.listdir(area_dir):
    filepath = os.path.join(area_dir, f)
    if os.path.isfile(filepath):
        FILES_TO_UPLOAD.append((filepath, f'birdnet-model/area-model/{f}'))

# 3. Labels (uniquement fr et en_us)
labels_dir = os.path.join(model_dir, 'labels')
for lang in ['fr.txt', 'en_us.txt']:
    filepath = os.path.join(labels_dir, lang)
    if os.path.exists(filepath):
        FILES_TO_UPLOAD.append((filepath, f'birdnet-model/labels/{lang}'))


def main():
    print(f"Connexion à R2...")
    s3 = boto3.client(
        's3',
        endpoint_url=R2_ENDPOINT,
        aws_access_key_id=R2_ACCESS_KEY_ID,
        aws_secret_access_key=R2_SECRET_ACCESS_KEY,
        region_name='auto'
    )

    print(f"\n{len(FILES_TO_UPLOAD)} fichiers à uploader :\n")
    
    total_size = 0
    for local_path, r2_key in FILES_TO_UPLOAD:
        size = os.path.getsize(local_path)
        total_size += size
        print(f"  {r2_key} ({size / (1024*1024):.1f} Mo)")

    print(f"\n  Total : {total_size / (1024*1024):.1f} Mo")
    
    confirm = input("\nUploader ? (oui/non) : ")
    if confirm.lower() != 'oui':
        print("Annulé.")
        return

    for i, (local_path, r2_key) in enumerate(FILES_TO_UPLOAD):
        content_type = 'application/json' if r2_key.endswith('.json') else 'application/octet-stream'
        if r2_key.endswith('.txt'):
            content_type = 'text/plain; charset=utf-8'
        
        print(f"  [{i+1}/{len(FILES_TO_UPLOAD)}] {r2_key}...", end=' ')
        s3.upload_file(
            local_path, R2_BUCKET, r2_key,
            ExtraArgs={'ContentType': content_type}
        )
        print("OK")

    print(f"\n✅ {len(FILES_TO_UPLOAD)} fichiers uploadés sur R2 !")
    print(f"   Préfixe : birdnet-model/")
    print(f"   Taille totale : {total_size / (1024*1024):.1f} Mo")


if __name__ == '__main__':
    main()