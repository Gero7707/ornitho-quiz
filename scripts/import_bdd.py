import csv
import re
import mysql.connector
from pathlib import Path

# ─── Configuration ────────────────────────────────────────────────────────────
DB_CONFIG = {
    'host':     'localhost',
    'port':     3310,
    'database': 'ornitho_db',
    'user':     'root',
    'password': 'Jfuapuq.48apdlo',  # ← à adapter selon ton .env
}

CSV_ESPECES     = r'C:\Env\workspace\OrnithoQuizz\public\audio\liste_especes_clean.csv'
CSV_THEMATIQUES = r'C:\Env\workspace\OrnithoQuizz\public\audio\liste_thematiques_clean.csv'

# Préfixe du chemin audio stocké en BDD (chemin relatif depuis public/audio/)
PREFIXE_ESPECES     = 'Par_espece'
PREFIXE_THEMATIQUES = 'Thematiques'

# ─── Parsing du nom de fichier ─────────────────────────────────────────────────
def parse_fichier(fichier):
    """
    Extrait nom_latin, auteur et type_son depuis le nom de fichier.

    Exemples :
      C1 - Accenteur alpin (Prunella collaris) - Chant.mp3
        → nom_latin='Prunella collaris', auteur=None, type_son='Chant'

      D2 - Accenteur mouchet (Prunella modularis) - Cris aberrants (Sean Ronayne).mp3
        → nom_latin='Prunella modularis', auteur='Sean Ronayne', type_son='Cris aberrants'
    """
    nom_latin = None
    auteur    = None

    # Toutes les parenthèses du nom de fichier
    matches = re.findall(r'\(\s*([^)]+?)\s*\)', fichier)
    for m in matches:
        m = m.strip()
        if re.match(r'^[A-Z][a-z]+ [a-z]+$', m):
            # Deux mots, 2ème en minuscule → nom latin
            nom_latin = m
        elif re.match(r'^\d+$', m):
            # Chiffre seul → variante numérotée, on ignore
            pass
        else:
            # Auteur ou note descriptive
            auteur = m

    # Type de son : segment après le dernier ' - ', sans les parenthèses, sans .mp3
    type_match = re.search(r' - ([^-]+?)(?:\s*\([^)]*\))?\s*\.mp3$', fichier)
    type_son = type_match.group(1).strip() if type_match else None

    return nom_latin, auteur, type_son


# ─── Import ────────────────────────────────────────────────────────────────────
def main():
    conn   = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor()

    # Désactiver les FK le temps de l'import pour éviter les conflits d'ordre
    cursor.execute("SET FOREIGN_KEY_CHECKS = 0")
    cursor.execute("TRUNCATE TABLE sons_thematiques")
    cursor.execute("TRUNCATE TABLE sons")
    cursor.execute("TRUNCATE TABLE thematiques")
    cursor.execute("TRUNCATE TABLE oiseaux")
    cursor.execute("SET FOREIGN_KEY_CHECKS = 1")
    conn.commit()

    # ── 1. Import espèces + sons ───────────────────────────────────────────────
    print("Import des espèces et sons...")

    oiseaux_map = {}  # nom_commun → id BDD

    with open(CSV_ESPECES, newline='', encoding='utf-8-sig') as f:
        reader = csv.DictReader(f)
        for row in reader:
            nom_commun = row['espece'].strip()
            fichier    = row['fichier'].strip()

            # Insérer l'oiseau si pas encore vu
            if nom_commun not in oiseaux_map:
                nom_latin, _, _ = parse_fichier(fichier)
                cursor.execute(
                    "INSERT INTO oiseaux (nom_commun, nom_latin) VALUES (%s, %s)",
                    (nom_commun, nom_latin)
                )
                oiseaux_map[nom_commun] = cursor.lastrowid

            oiseau_id = oiseaux_map[nom_commun]
            _, auteur, type_son = parse_fichier(fichier)

            chemin = f"{PREFIXE_ESPECES}/{nom_commun}/{fichier}"

            cursor.execute(
                """INSERT INTO sons (oiseau_id, titre, chemin_fichier, auteur, type_son)
                   VALUES (%s, %s, %s, %s, %s)""",
                (oiseau_id, fichier, chemin, auteur, type_son)
            )

    conn.commit()
    print(f"  → {len(oiseaux_map)} espèces importées")

    # ── 2. Import thématiques + leurs sons propres ────────────────────────────
    print("Import des thématiques...")

    thematiques_map = {}  # nom → id BDD

    with open(CSV_THEMATIQUES, newline='', encoding='utf-8-sig') as f:
        reader = csv.DictReader(f)
        for row in reader:
            nom_thematique = row['thematique'].strip()
            fichier        = row['fichier'].strip()

            # Insérer la thématique si pas encore vue
            if nom_thematique not in thematiques_map:
                cursor.execute(
                    "INSERT INTO thematiques (nom) VALUES (%s)",
                    (nom_thematique,)
                )
                thematiques_map[nom_thematique] = cursor.lastrowid

            thematique_id = thematiques_map[nom_thematique]

            # Chercher si ce son existe déjà (commun avec espèces)
            cursor.execute(
                "SELECT id FROM sons WHERE chemin_fichier LIKE %s",
                (f"%{fichier}",)
            )
            result = cursor.fetchone()
            cursor.fetchall()

            if result:
                # Son déjà importé via espèces → juste la liaison
                son_id = result[0]
            else:
                # Son propre à la thématique → on l'insère
                nom_latin, auteur, type_son = parse_fichier(fichier)
                chemin = f"{PREFIXE_THEMATIQUES}/{nom_thematique}/{fichier}"
                cursor.execute(
                    """INSERT INTO sons (oiseau_id, titre, chemin_fichier, auteur, type_son)
                       VALUES (%s, %s, %s, %s, %s)""",
                    (None, fichier, chemin, auteur, type_son)
                )
                son_id = cursor.lastrowid

            cursor.execute(
                """INSERT IGNORE INTO sons_thematiques (son_id, thematique_id)
                   VALUES (%s, %s)""",
                (son_id, thematique_id)
            )

    conn.commit()
    print(f"  → {len(thematiques_map)} thématiques importées")

    cursor.close()
    conn.close()
    print("\nImport terminé ✓")


if __name__ == '__main__':
    main()
