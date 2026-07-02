"""
nettoyer_sons.py — Limite à 6 sons par espèce
==============================================
- Sélectionne les 6 meilleurs sons par espèce (diversité des types, priorité originaux)
- Supprime les fichiers excédentaires sur Cloudflare R2
- Supprime les lignes correspondantes en BDD

Usage:
  python nettoyer_sons.py              # Dry run (affiche ce qui serait supprimé)
  python nettoyer_sons.py --execute    # Exécution réelle
"""

import os
import re
import sys
import boto3
import mysql.connector
from dotenv import load_dotenv
from collections import defaultdict

load_dotenv()

# ─── Configuration ───────────────────────────────────────────────────
MAX_SONS = 4

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


# ─── Extraction du type de son depuis le titre ──────────────────────
def extraire_type_son(titre):
    """
    Extrait le type de son depuis le titre.
    Exemples :
      "Pinson des arbres (Fringilla coelebs) - Chant nuptial" → "chant"
      "Pinson des arbres (Fringilla coelebs) - Cri d'alarme"  → "cri d'alarme"
      "Pinson des arbres (Fringilla coelebs) - Son"            → "son"
    """
    titre_lower = titre.lower()

    # Format normalisé : "Espèce (nom_latin) - Type"
    match = re.search(r'\)\s*-\s*(.+)$', titre)
    if match:
        type_brut = match.group(1).strip().lower()
    else:
        # Fallback : chercher après le dernier tiret
        match2 = re.search(r'-\s*([^-]+)$', titre)
        if match2:
            type_brut = match2.group(1).strip().lower()
        else:
            type_brut = "inconnu"

    # Normalisation en catégories
    if 'alarme' in type_brut:
        return 'cri d\'alarme'
    elif 'vol' in type_brut:
        return 'cri de vol'
    elif 'chant' in type_brut:
        return 'chant'
    elif 'cri' in type_brut:
        return 'cri'
    elif 'quémand' in type_brut or 'juvénile' in type_brut:
        return 'quémandage'
    elif 'tambourin' in type_brut:
        return 'tambourinage'
    elif 'son' in type_brut:
        return 'son'
    else:
        return type_brut


def est_fichier_original(son):
    """Les fichiers originaux n'ont pas 'XC' dans le chemin."""
    return 'XC' not in son['chemin_fichier']


# ─── Sélection intelligente des 6 sons à garder ─────────────────────
def selectionner_sons(sons):
    """
    Sélectionne MAX_SONS sons parmi la liste, en priorisant :
    1. La diversité des types de son
    2. Les fichiers originaux (pas Xeno-canto) en priorité
    
    Retourne la liste des sons à GARDER.
    """
    if len(sons) <= MAX_SONS:
        return sons

    # Tagger chaque son avec son type
    for s in sons:
        s['type_son'] = extraire_type_son(s['titre'])
        s['est_original'] = est_fichier_original(s)

    # Grouper par type de son
    par_type = defaultdict(list)
    for s in sons:
        par_type[s['type_son']].append(s)

    # Trier chaque groupe : originaux d'abord, puis par ID (anciens d'abord)
    for type_son in par_type:
        par_type[type_son].sort(key=lambda s: (not s['est_original'], s['id']))

    garder = []
    types_restants = list(par_type.keys())

    # Phase 1 : prendre 1 son de chaque type (diversité maximale)
    for type_son in types_restants:
        if len(garder) >= MAX_SONS:
            break
        if par_type[type_son]:
            garder.append(par_type[type_son].pop(0))

    # Phase 2 : compléter jusqu'à MAX_SONS avec les restants
    # Prioriser les originaux, puis les types sous-représentés
    restants = []
    for type_son in par_type:
        restants.extend(par_type[type_son])
    
    # Trier restants : originaux d'abord, puis par ID
    restants.sort(key=lambda s: (not s['est_original'], s['id']))

    while len(garder) < MAX_SONS and restants:
        garder.append(restants.pop(0))

    return garder


# ─── Main ────────────────────────────────────────────────────────────
def main():
    if DRY_RUN:
        print("=" * 60)
        print("  MODE DRY RUN — Rien ne sera supprimé")
        print("  Relancer avec --execute pour supprimer")
        print("=" * 60)
    else:
        print("=" * 60)
        print("  ⚠️  MODE EXÉCUTION — Suppression réelle !")
        print("=" * 60)
        confirm = input("Confirmer ? (oui/non) : ")
        if confirm.lower() != 'oui':
            print("Annulé.")
            return

    # Connexion BDD
    print("\nConnexion à la BDD...")
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor(dictionary=True)

    # Connexion R2
    print("Connexion à Cloudflare R2...")
    s3 = boto3.client(
        's3',
        endpoint_url=R2_ENDPOINT,
        aws_access_key_id=R2_ACCESS_KEY_ID,
        aws_secret_access_key=R2_SECRET_ACCESS_KEY,
        region_name='auto'
    )

    # Récupérer tous les sons groupés par espèce
    cursor.execute("""
        SELECT s.id, s.titre, s.chemin_fichier, s.auteur, s.oiseau_id, o.nom_commun
        FROM sons s
        JOIN oiseaux o ON s.oiseau_id = o.id
        ORDER BY s.oiseau_id, s.id
    """)
    tous_les_sons = cursor.fetchall()

    # Grouper par espèce
    par_espece = defaultdict(list)
    for son in tous_les_sons:
        par_espece[son['oiseau_id']].append(son)

    # Statistiques
    total_a_supprimer = 0
    total_gardes = 0
    especes_touchees = 0
    sons_a_supprimer = []
    fichiers_r2_a_supprimer = []

    print(f"\n{len(par_espece)} espèces en BDD, {len(tous_les_sons)} sons au total\n")

    for oiseau_id, sons in sorted(par_espece.items()):
        nom = sons[0]['nom_commun']

        if len(sons) <= MAX_SONS:
            total_gardes += len(sons)
            continue

        especes_touchees += 1
        garder = selectionner_sons(sons)
        ids_garder = {s['id'] for s in garder}

        supprimer = [s for s in sons if s['id'] not in ids_garder]
        total_a_supprimer += len(supprimer)
        total_gardes += len(garder)

        # Afficher le détail
        print(f"🐦 {nom} : {len(sons)} sons → garder {len(garder)}, supprimer {len(supprimer)}")
        
        if DRY_RUN:
            print(f"   Gardés :")
            for s in garder:
                type_tag = s.get('type_son', '?')
                orig = '📁' if s.get('est_original') else '🌐'
                print(f"     {orig} [{type_tag}] {s['titre'][:70]}")
            print(f"   Supprimés :")
            for s in supprimer:
                print(f"     ❌ {s['titre'][:70]}")
            print()

        for s in supprimer:
            sons_a_supprimer.append(s['id'])
            fichiers_r2_a_supprimer.append(s['chemin_fichier'])

    # Résumé
    print("=" * 60)
    print(f"  RÉSUMÉ")
    print(f"  Espèces touchées : {especes_touchees}")
    print(f"  Sons à garder    : {total_gardes}")
    print(f"  Sons à supprimer : {total_a_supprimer}")
    print(f"  Fichiers R2      : {len(fichiers_r2_a_supprimer)}")
    print("=" * 60)

    if DRY_RUN:
        print("\n👆 Mode dry run — rien n'a été supprimé.")
        print("   Relancer avec --execute pour exécuter.\n")
        return

    # ─── Exécution réelle ────────────────────────────────────────────

    # 1. Supprimer les fichiers sur R2
    print(f"\nSuppression de {len(fichiers_r2_a_supprimer)} fichiers sur R2...")
    r2_succes = 0
    r2_echecs = 0

    # R2 supporte la suppression par lots de 1000
    for i in range(0, len(fichiers_r2_a_supprimer), 1000):
        batch = fichiers_r2_a_supprimer[i:i+1000]
        objects = [{'Key': chemin} for chemin in batch]
        try:
            response = s3.delete_objects(
                Bucket=R2_BUCKET,
                Delete={'Objects': objects, 'Quiet': True}
            )
            errors = response.get('Errors', [])
            r2_succes += len(batch) - len(errors)
            r2_echecs += len(errors)
            for err in errors:
                print(f"   ⚠️ Erreur R2 : {err['Key']} — {err['Message']}")
        except Exception as e:
            print(f"   ❌ Erreur batch R2 : {e}")
            r2_echecs += len(batch)

    print(f"   R2 : {r2_succes} supprimés, {r2_echecs} erreurs")

    # 2. Supprimer d'abord les références dans sons_thematiques
    print(f"\nSuppression des références thématiques...")
    if sons_a_supprimer:
        placeholders = ','.join(['%s'] * len(sons_a_supprimer))
        cursor.execute(
            f"DELETE FROM sons_thematiques WHERE son_id IN ({placeholders})",
            sons_a_supprimer
        )
        print(f"   {cursor.rowcount} liaisons thématiques supprimées")

    # 3. Supprimer d'abord les références dans quiz_questions
    print(f"Suppression des références quiz...")
    if sons_a_supprimer:
        cursor.execute(
            f"DELETE FROM quiz_questions WHERE son_id IN ({placeholders})",
            sons_a_supprimer
        )
        print(f"   {cursor.rowcount} questions quiz supprimées")

    # 4. Supprimer les sons en BDD
    print(f"Suppression des sons en BDD...")
    if sons_a_supprimer:
        cursor.execute(
            f"DELETE FROM sons WHERE id IN ({placeholders})",
            sons_a_supprimer
        )
        print(f"   {cursor.rowcount} sons supprimés")

    conn.commit()
    print(f"\n✅ Nettoyage terminé !")
    print(f"   {r2_succes} fichiers supprimés sur R2")
    print(f"   {len(sons_a_supprimer)} sons supprimés en BDD")

    cursor.close()
    conn.close()


if __name__ == '__main__':
    main()