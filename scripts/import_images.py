import requests
import mysql.connector
import time
import os
from dotenv import load_dotenv

load_dotenv()

HEADERS = {
    "User-Agent": "OrnithoQuiz/1.0 (ornitho-quiz.fr; contact@ornitho-quiz.fr)"
}

conn = mysql.connector.connect(
    host=os.getenv("DB_HOST"),
    user=os.getenv("DB_USER"),
    password=os.getenv("DB_PASSWORD"),
    database=os.getenv("DB_NAME")
)
cursor = conn.cursor(dictionary=True)

def get_images_wikimedia(nom_latin, max_images=5):
    url = "https://commons.wikimedia.org/w/api.php"
    params = {
        "action": "query",
        "generator": "search",
        "gsrnamespace": 6,
        "gsrsearch": f"{nom_latin} bird photo",
        "gsrlimit": 20,
        "prop": "imageinfo",
        "iiprop": "url",
        "iiurlwidth": 800,
        "format": "json",
        "formatversion": "2"
    }
    try:
        r = requests.get(url, params=params, headers=HEADERS, timeout=10)
        data = r.json()
        pages = data.get("query", {}).get("pages", [])
        urls = []
        for page in pages:
            titre = page.get("title", "").lower()
            if any(mot in titre for mot in ["dist", "iucn", "map", "range", "distribution", "flag", "logo", "icon", "svg"]):
                continue
            if titre.endswith(".svg"):
                continue
            imageinfo = page.get("imageinfo", [])
            if imageinfo:
                urls.append(imageinfo[0]["thumburl"])
        return urls[:max_images]
    except Exception as e:
        print(f"  Erreur Wikimedia : {e}")
        return []

def get_image_url(titre):
    """Résout le titre Wikimedia en URL directe."""
    url = "https://en.wikipedia.org/w/api.php"
    params = {
        "action": "query",
        "titles": titre,
        "prop": "imageinfo",
        "iiprop": "url",
        "format": "json"
    }
    try:
        r = requests.get(url, params=params, headers=HEADERS, timeout=10)
        data = r.json()
        pages = data["query"]["pages"]
        page = next(iter(pages.values()))
        imageinfo = page.get("imageinfo", [])
        if imageinfo:
            return imageinfo[0]["url"]
    except:
        pass
    return None

# Récupérer toutes les espèces
cursor.execute("SELECT id, nom_commun, nom_latin FROM oiseaux WHERE nom_latin IS NOT NULL")
especes = cursor.fetchall()

print(f"{len(especes)} espèces à traiter\n")

for i, oiseau in enumerate(especes):
    print(f"[{i+1}/{len(especes)}] {oiseau['nom_commun']}...")

    # Vérifier si déjà traité
    cursor.execute("SELECT COUNT(*) as nb FROM oiseaux_images WHERE oiseau_id = %s", (oiseau['id'],))
    if cursor.fetchone()['nb'] > 0:
        print("  → déjà traité, skip")
        continue

    urls = get_images_wikimedia(oiseau['nom_latin'])

    if urls:
        for rang, url in enumerate(urls, start=1):
            cursor.execute(
                "INSERT INTO oiseaux_images (oiseau_id, url, rang) VALUES (%s, %s, %s)",
                (oiseau['id'], url, rang)
            )
        conn.commit()
        print(f"  → {len(urls)} image(s) insérée(s)")
    else:
        print("  → aucune image trouvée")

    time.sleep(1)  # respecter l'API Wikimedia

cursor.close()
conn.close()
print("\nTerminé !")