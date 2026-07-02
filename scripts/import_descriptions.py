import requests
import mysql.connector
import time
import os
from dotenv import load_dotenv

load_dotenv()

# Configuration
IUCN_TOKEN = os.getenv('IUCN_TOKEN')
GOOGLE_API_KEY = os.getenv('GOOGLE_API_KEY')
MAX_CHARS = 490000

DB_CONFIG = {
    'host': os.getenv('DB_HOST'),
    'user': os.getenv('DB_USER'),
    'password': os.getenv('DB_PASSWORD'),
    'database': os.getenv('DB_NAME')
}

def traduire(texte):
    url = "https://translation.googleapis.com/language/translate/v2"
    response = requests.post(url, params={
        'key': GOOGLE_API_KEY,
        'q': texte,
        'source': 'en',
        'target': 'fr',
        'format': 'text'
    })
    data = response.json()
    return data['data']['translations'][0]['translatedText']

def get_iucn_description(nom_latin):
    genre, espece = nom_latin.strip().split(' ')[:2]
    
    r1 = requests.get(
        "https://api.iucnredlist.org/api/v4/taxa/scientific_name",
        params={'genus_name': genre, 'species_name': espece},
        headers={'Authorization': IUCN_TOKEN}
    )
    data1 = r1.json()
    
    if 'assessments' not in data1 or not data1['assessments']:
        return None
    
    assessments = [a for a in data1['assessments'] if a.get('latest')]
    if not assessments:
        return None
    
    assessment_id = assessments[0]['assessment_id']
    
    r2 = requests.get(
        f"https://api.iucnredlist.org/api/v4/assessment/{assessment_id}",
        headers={'Authorization': IUCN_TOKEN}
    )
    data2 = r2.json()
    
    habitats = data2.get('documentation', {}).get('habitats', '')
    return habitats if habitats else None

# Connexion BDD
conn = mysql.connector.connect(**DB_CONFIG)
cursor = conn.cursor()

cursor.execute("SELECT id, nom_commun, nom_latin FROM oiseaux WHERE description_courte IS NULL AND nom_latin IS NOT NULL AND region = 'guyane'")
especes = cursor.fetchall()
# especes = especes[:3]
print(f"{len(especes)} espèces à traiter")

total_chars = 0
succes = 0
echecs = []

for oiseau_id, nom_commun, nom_latin in especes:
    print(f"Traitement : {nom_commun} ({nom_latin})")
    
    try:
        # Traduire le nom commun si encore en anglais
        if nom_commun.isascii():
            nom_fr = traduire(nom_commun)
            cursor.execute("UPDATE oiseaux SET nom_commun = %s WHERE id = %s", (nom_fr, oiseau_id))
            conn.commit()
            print(f"  → Nom traduit : {nom_commun} → {nom_fr}")
            nom_commun = nom_fr

        description_en = get_iucn_description(nom_latin)
        
        if not description_en:
            print(f"  → Pas de description IUCN")
            echecs.append(nom_commun)
            time.sleep(1)
            continue
        
        if total_chars + len(description_en) > MAX_CHARS:
            print(f"\n⚠️ Quota Google presque atteint ({total_chars} caractères). Arrêt.")
            break
        
        description_fr = traduire(description_en)
        total_chars += len(description_en)
        
        cursor.execute(
            "UPDATE oiseaux SET description_courte = %s WHERE id = %s",
            (description_fr, oiseau_id)
        )
        conn.commit()
        succes += 1
        print(f"  ✓ {len(description_en)} caractères traduits (total: {total_chars})")
        
        time.sleep(0.5)
        
    except Exception as e:
        print(f"  ✗ Erreur : {e}")
        echecs.append(nom_commun)
        time.sleep(1)

cursor.close()
conn.close()

print(f"\n=== Résumé ===")
print(f"Succès : {succes}")
print(f"Échecs : {len(echecs)}")
print(f"Caractères traduits : {total_chars}")
if echecs:
    print(f"Espèces sans description : {', '.join(echecs)}")