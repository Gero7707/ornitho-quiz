"""
nettoyer_r2.py — Supprime les fichiers R2 non référencés en BDD
================================================================
Liste tous les fichiers sur R2, compare avec les chemin_fichier en BDD,
et supprime tout ce qui n'est plus référencé.

Usage:
  python nettoyer_r2.py              # Dry run
  python nettoyer_r2.py --execute    # Exécution réelle
"""

import os
import sys
import boto3
import mysql.connector
from dotenv import load_dotenv

load_dotenv()

# ─── Configuration ───────────────────────────────────────────────────
DB_CONFIG = {
    'host': os.getenv('DB_HOST'),
    'user': os.getenv('DB_USER'),
    'password': os.getenv('DB_PASSWORD'),
    'database': os.getenv('DB_NAME'),
    'port': int(os.getenv('DB_PORT', 3306))
}

R2_ENDPOINT = os.getenv('R2_ENDPOINT')
R2_ACCESS_KEY_ID = os.getenv('R2_ACCESS_KEY_ID')
R2_SECRET_ACCESS_KEY = os.getenv('R2_SECRET_ACCESS_KEY')
R2_BUCKET = os.getenv('R2_BUCKET', 'ornitho-quiz-audio')

DRY_RUN = '--execute' not in sys.argv


def main():
    if DRY_RUN:
        print("=" * 60)
        print("  MODE DRY RUN — Rien ne sera supprimé")
        print("  Relancer avec --execute pour supprimer")
        print("=" * 60)
    else:
        print("=" * 60)
        print("  ⚠️  MODE EXÉCUTION — Suppression réelle sur R2 !")
        print("=" * 60)
        confirm = input("Confirmer ? (oui/non) : ")
        if confirm.lower() != 'oui':
            print("Annulé.")
            return

    # 1. Récupérer tous les chemins référencés en BDD
    print("\nConnexion à la BDD...")
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor()
    cursor.execute("SELECT chemin_fichier FROM sons")
    chemins_bdd = {row[0] for row in cursor.fetchall()}
    print(f"  {len(chemins_bdd)} fichiers référencés en BDD")
    cursor.close()
    conn.close()

    # 2. Lister tous les fichiers sur R2
    print("\nConnexion à Cloudflare R2...")
    s3 = boto3.client(
        's3',
        endpoint_url=R2_ENDPOINT,
        aws_access_key_id=R2_ACCESS_KEY_ID,
        aws_secret_access_key=R2_SECRET_ACCESS_KEY,
        region_name='auto'
    )

    print("Listage de tous les fichiers sur R2 (peut prendre un moment)...")
    fichiers_r2 = []
    taille_totale_r2 = 0
    paginator = s3.get_paginator('list_objects_v2')

    for page in paginator.paginate(Bucket=R2_BUCKET):
        for obj in page.get('Contents', []):
            fichiers_r2.append({
                'key': obj['Key'],
                'size': obj['Size']
            })
            taille_totale_r2 += obj['Size']

    print(f"  {len(fichiers_r2)} fichiers sur R2 ({taille_totale_r2 / (1024**3):.2f} Go)")

    # 3. Comparer : trouver les fichiers R2 non référencés en BDD
    a_supprimer = []
    taille_a_liberer = 0
    a_garder = []
    taille_gardee = 0

    for f in fichiers_r2:
        if f['key'] in chemins_bdd:
            a_garder.append(f)
            taille_gardee += f['size']
        else:
            a_supprimer.append(f)
            taille_a_liberer += f['size']

    # 4. Résumé
    print(f"\n{'=' * 60}")
    print(f"  RÉSUMÉ")
    print(f"  Fichiers R2 total      : {len(fichiers_r2)} ({taille_totale_r2 / (1024**3):.2f} Go)")
    print(f"  Fichiers à garder      : {len(a_garder)} ({taille_gardee / (1024**3):.2f} Go)")
    print(f"  Fichiers à supprimer   : {len(a_supprimer)} ({taille_a_liberer / (1024**3):.2f} Go)")
    print(f"  Espace libéré          : {taille_a_liberer / (1024**3):.2f} Go")
    print(f"{'=' * 60}")

    if DRY_RUN:
        # Afficher quelques exemples
        print(f"\nExemples de fichiers à supprimer (20 premiers) :")
        for f in a_supprimer[:20]:
            print(f"  ❌ {f['key']} ({f['size'] / 1024:.0f} Ko)")
        if len(a_supprimer) > 20:
            print(f"  ... et {len(a_supprimer) - 20} autres")
        print(f"\n👆 Mode dry run — rien n'a été supprimé.")
        print(f"   Relancer avec --execute pour exécuter.\n")
        return

    # 5. Suppression par lots de 1000
    print(f"\nSuppression de {len(a_supprimer)} fichiers sur R2...")
    r2_succes = 0
    r2_echecs = 0

    keys_a_supprimer = [f['key'] for f in a_supprimer]

    for i in range(0, len(keys_a_supprimer), 1000):
        batch = keys_a_supprimer[i:i+1000]
        objects = [{'Key': key} for key in batch]
        try:
            response = s3.delete_objects(
                Bucket=R2_BUCKET,
                Delete={'Objects': objects, 'Quiet': True}
            )
            errors = response.get('Errors', [])
            r2_succes += len(batch) - len(errors)
            r2_echecs += len(errors)
            for err in errors:
                print(f"   ⚠️ Erreur : {err['Key']} — {err['Message']}")
            print(f"   Batch {i//1000 + 1} : {len(batch) - len(errors)} supprimés")
        except Exception as e:
            print(f"   ❌ Erreur batch : {e}")
            r2_echecs += len(batch)

    print(f"\n✅ Nettoyage R2 terminé !")
    print(f"   {r2_succes} fichiers supprimés")
    print(f"   {r2_echecs} erreurs")
    print(f"   ~{taille_a_liberer / (1024**3):.2f} Go libérés")


if __name__ == '__main__':
    main()