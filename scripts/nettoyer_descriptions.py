import mysql.connector
import os
from bs4 import BeautifulSoup
from dotenv import load_dotenv

load_dotenv()

DB_CONFIG = {
    'host': os.getenv('DB_HOST'),
    'user': os.getenv('DB_USER'),
    'password': os.getenv('DB_PASSWORD'),
    'database': os.getenv('DB_NAME')
}

def nettoyer_description(texte):
    # Si pas de HTML, on enveloppe juste dans des <p>
    if '<' not in texte:
        # Séparer par les doubles sauts de ligne si présents
        paragraphes = [p.strip() for p in texte.split('\n\n') if p.strip()]
        if len(paragraphes) > 1:
            return '\n'.join([f'<p>{p}</p>' for p in paragraphes])
        else:
            return f'<p>{texte.strip()}</p>'
    
    # Si HTML, nettoyer avec BeautifulSoup
    soup = BeautifulSoup(texte, 'html.parser')
    
    resultat = []
    paragraphe_en_cours = []
    
    for element in soup.recursiveChildGenerator():
        if element.name == 'strong':
            if paragraphe_en_cours:
                t = ' '.join(paragraphe_en_cours).strip()
                if t:
                    resultat.append(f'<p>{t}</p>')
                paragraphe_en_cours = []
            titre = element.get_text().strip()
            if titre:
                resultat.append(f'<p><strong>{titre} :</strong></p>')
        
        elif element.name == 'br':
            if paragraphe_en_cours:
                t = ' '.join(paragraphe_en_cours).strip()
                if t:
                    resultat.append(f'<p>{t}</p>')
                paragraphe_en_cours = []
        
        elif element.name == 'em':
            t = element.get_text().strip()
            if t:
                paragraphe_en_cours.append(f'<em>{t}</em>')
        
        elif isinstance(element, str) and element.parent.name not in ['strong', 'em']:
            t = element.strip()
            if t:
                paragraphe_en_cours.append(t)
    
    if paragraphe_en_cours:
        t = ' '.join(paragraphe_en_cours).strip()
        if t:
            resultat.append(f'<p>{t}</p>')
    
    return '\n'.join(resultat)

# Connexion BDD
conn = mysql.connector.connect(**DB_CONFIG)
cursor = conn.cursor()

cursor.execute("SELECT id, nom_commun, description_courte FROM oiseaux WHERE description_courte IS NOT NULL")
especes = cursor.fetchall()

print(f"{len(especes)} descriptions à traiter")

succes = 0
for oiseau_id, nom_commun, description in especes:
    try:
        description_propre = nettoyer_description(description)
        cursor.execute(
            "UPDATE oiseaux SET description_courte = %s WHERE id = %s",
            (description_propre, oiseau_id)
        )
        conn.commit()
        succes += 1
        print(f"  ✓ {nom_commun}")
    except Exception as e:
        print(f"  ✗ {nom_commun} : {e}")

cursor.close()
conn.close()

print(f"\n=== Résumé ===")
print(f"Succès : {succes}/{len(especes)}")
