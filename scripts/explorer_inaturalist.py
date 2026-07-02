"""
Explorer iNaturalist API v1 — Photos d'oiseaux sous licence CC
================================================================
Pas besoin de clé API ! L'API v1 d'iNaturalist est publique.

Usage :
    python explorer_inaturalist.py

Il va tester plusieurs espèces (métropole + Antilles) et montrer :
- Nombre de photos disponibles sous licence CC
- URLs des meilleures photos
- Infos d'attribution (auteur, licence)
- Tailles disponibles (small, medium, large, original)

Limite API : 100 requêtes/minute, pas de clé nécessaire.
"""

import requests
import json
import time

BASE_URL = "https://api.inaturalist.org/v1"

# Espèces à tester — mélange métropole + Antilles + Guyane
ESPECES_TEST = [
    ("Fratercula arctica", "Macareux moine — métropole"),
    ("Parus major", "Mésange charbonnière — métropole"),
    ("Mimus gilvus", "Moqueur des savanes — Antilles"),
    ("Quiscalus lugubris", "Quiscale merle — Antilles"),
    ("Coereba flaveola", "Sucrier à ventre jaune — Antilles"),
    ("Orthorhyncus cristatus", "Colibri huppé — Antilles"),
    ("Ramphastos tucanus", "Toucan à bec rouge — Guyane"),
    ("Ara macao", "Ara rouge — Guyane"),
]

# Licences CC acceptables pour un usage non-commercial avec attribution
LICENCES_OK = "cc0,cc-by,cc-by-nc,cc-by-sa,cc-by-nc-sa"


def rechercher_photos(nom_latin, description, max_results=5):
    """Recherche des photos CC Research Grade pour une espèce."""
    print(f"\n{'='*70}")
    print(f"  🐦 {nom_latin} — {description}")
    print(f"{'='*70}")

    # 1. D'abord, compter le total disponible
    params_count = {
        "taxon_name": nom_latin,
        "photos": "true",
        "photo_license": LICENCES_OK,
        "quality_grade": "research",
        "per_page": 0,  # juste le count
    }

    try:
        r = requests.get(f"{BASE_URL}/observations", params=params_count, timeout=15)
        r.raise_for_status()
        data = r.json()
        total = data.get("total_results", 0)
        print(f"\n  📊 Total observations Research Grade avec photo CC : {total}")
    except Exception as e:
        print(f"  ❌ Erreur comptage : {e}")
        return

    if total == 0:
        # Essayer sans filtre licence pour voir s'il y a des photos tout court
        params_no_license = {
            "taxon_name": nom_latin,
            "photos": "true",
            "quality_grade": "research",
            "per_page": 0,
        }
        r2 = requests.get(f"{BASE_URL}/observations", params=params_no_license, timeout=15)
        total_all = r2.json().get("total_results", 0)
        print(f"  ⚠️  Total SANS filtre licence : {total_all}")
        print(f"  → Toutes les photos sont 'All Rights Reserved' ou pas de Research Grade")
        return

    # 2. Récupérer les meilleures observations (triées par votes)
    params_fetch = {
        "taxon_name": nom_latin,
        "photos": "true",
        "photo_license": LICENCES_OK,
        "quality_grade": "research",
        "per_page": max_results,
        "order_by": "votes",
        "order": "desc",
    }

    r = requests.get(f"{BASE_URL}/observations", params=params_fetch, timeout=15)
    r.raise_for_status()
    data = r.json()

    results = data.get("results", [])
    print(f"  📷 {len(results)} meilleures observations récupérées :\n")

    for i, obs in enumerate(results, 1):
        obs_id = obs.get("id")
        user = obs.get("user", {})
        user_login = user.get("login", "?")
        user_name = user.get("name", user_login)
        place = obs.get("place_guess", "Lieu inconnu")
        observed_on = obs.get("observed_on_string", "?")
        faves = obs.get("faves_count", 0)

        photos = obs.get("photos", [])
        if not photos:
            continue

        photo = photos[0]  # première photo de l'observation
        photo_id = photo.get("id")
        license_code = photo.get("license_code", "?")
        attribution = photo.get("attribution", "?")

        # iNaturalist fournit des URLs de tailles différentes
        # Le format est : https://inaturalist-open-data.s3.amazonaws.com/photos/{id}/{size}.{ext}
        # ou via le CDN : https://static.inaturalist.org/photos/{id}/{size}.jpg
        url_original = photo.get("url", "")
        # L'URL par défaut est en "square" (75x75)
        # On peut remplacer par : small (240x240), medium (500x500), large (1024x1024), original
        url_medium = url_original.replace("square", "medium") if url_original else "?"
        url_large = url_original.replace("square", "large") if url_original else "?"
        url_small = url_original.replace("square", "small") if url_original else "?"

        print(f"  {i}. Observation #{obs_id}")
        print(f"     📍 {place} — {observed_on}")
        print(f"     👤 {user_name} (@{user_login})")
        print(f"     📜 Licence : {license_code}")
        print(f"     ⭐ {faves} favoris")
        print(f"     🔗 https://www.inaturalist.org/observations/{obs_id}")
        print(f"     📷 Photo #{photo_id} ({len(photos)} photo(s) dans l'obs)")
        print(f"        small  : {url_small}")
        print(f"        medium : {url_medium}")
        print(f"        large  : {url_large}")
        print(f"     📝 Attribution : {attribution}")
        print()

    # 3. Résumé des licences disponibles
    print(f"  📊 Répartition des licences (sur les {len(results)} résultats) :")
    licences = {}
    for obs in results:
        for photo in obs.get("photos", []):
            lc = photo.get("license_code", "none")
            licences[lc] = licences.get(lc, 0) + 1
    for lc, count in sorted(licences.items(), key=lambda x: -x[1]):
        print(f"     {lc or 'All Rights Reserved'} : {count} photos")


def tester_taxon_search(nom_latin):
    """Vérifie que le taxon existe dans iNaturalist et montre l'ID."""
    r = requests.get(f"{BASE_URL}/taxa", params={
        "q": nom_latin,
        "rank": "species",
        "per_page": 1,
    }, timeout=15)
    data = r.json()
    results = data.get("results", [])
    if results:
        taxon = results[0]
        return {
            "id": taxon.get("id"),
            "name": taxon.get("name"),
            "common_name": taxon.get("preferred_common_name", "?"),
            "observations_count": taxon.get("observations_count", 0),
            "default_photo": taxon.get("default_photo", {}),
        }
    return None


def main():
    print(f"\n{'#'*70}")
    print(f"  iNaturalist API v1 — Exploration photos d'oiseaux")
    print(f"  Pas de clé API nécessaire !")
    print(f"  Licences filtrées : {LICENCES_OK}")
    print(f"{'#'*70}")

    # D'abord, vérifier les taxons
    print(f"\n{'='*70}")
    print(f"  ÉTAPE 1 : Vérification des taxons dans iNaturalist")
    print(f"{'='*70}\n")

    for nom_latin, description in ESPECES_TEST:
        taxon = tester_taxon_search(nom_latin)
        if taxon:
            default_photo = taxon.get("default_photo", {})
            photo_url = default_photo.get("medium_url", "aucune") if default_photo else "aucune"
            photo_license = default_photo.get("license_code", "?") if default_photo else "?"
            print(f"  ✅ {nom_latin} → iNat ID: {taxon['id']}")
            print(f"     Nom commun (EN): {taxon['common_name']}")
            print(f"     Observations: {taxon['observations_count']:,}")
            print(f"     Photo par défaut: {photo_license} — {photo_url}")
        else:
            print(f"  ❌ {nom_latin} — non trouvé dans iNaturalist")
        time.sleep(0.5)  # respecter la limite API

    # Ensuite, chercher les photos
    print(f"\n{'='*70}")
    print(f"  ÉTAPE 2 : Recherche de photos CC Research Grade")
    print(f"{'='*70}")

    for nom_latin, description in ESPECES_TEST:
        rechercher_photos(nom_latin, description)
        time.sleep(1)  # 1 seconde entre chaque espèce

    # Bonus : montrer la structure JSON brute d'une observation
    print(f"\n{'='*70}")
    print(f"  BONUS : Structure JSON d'une observation (1ère espèce)")
    print(f"{'='*70}\n")

    nom_latin = ESPECES_TEST[0][0]
    r = requests.get(f"{BASE_URL}/observations", params={
        "taxon_name": nom_latin,
        "photos": "true",
        "photo_license": "cc-by-nc",
        "quality_grade": "research",
        "per_page": 1,
    }, timeout=15)
    data = r.json()
    results = data.get("results", [])
    if results:
        obs = results[0]
        # Extraire juste les clés intéressantes
        print("  Clés de premier niveau dans une observation :")
        for key in sorted(obs.keys()):
            val = obs[key]
            if isinstance(val, list):
                print(f"    📋 {key}: [{len(val)} éléments]")
            elif isinstance(val, dict):
                print(f"    📁 {key}: {{{len(val)} clés}}")
            elif isinstance(val, str) and len(val) > 60:
                print(f"    📝 {key}: \"{val[:60]}...\"")
            else:
                print(f"    🔹 {key}: {val}")

        # Sauvegarder le JSON complet
        with open("inaturalist_sample_observation.json", "w", encoding="utf-8") as f:
            json.dump(obs, f, indent=2, ensure_ascii=False)
        print(f"\n  💾 JSON complet sauvegardé : inaturalist_sample_observation.json")

    # Résumé final
    print(f"\n{'#'*70}")
    print(f"  RÉSUMÉ")
    print(f"{'#'*70}")
    print(f"""
  Pour utiliser les photos iNaturalist dans OrnithoQuizz :

  ✅ Licence CC BY-NC = OK pour usage non-commercial
  ✅ Pas de clé API nécessaire
  ✅ Filtrage par qualité (Research Grade = ID validée)
  ✅ Photos en plusieurs tailles (small/medium/large/original)
  ✅ Hotlink autorisé (pas besoin de stocker sur R2)

  ⚠️  Obligations :
  - Afficher l'attribution (nom de l'auteur + licence)
  - Ne pas dépasser 100 requêtes/minute
  - Ne pas télécharger plus de 5 Go/heure

  💡 URL type pour une photo medium :
  https://inaturalist-open-data.s3.amazonaws.com/photos/{{ID}}/medium.jpg

  💡 Endpoint utile pour ton script d'import :
  GET /v1/observations?taxon_name={{nom_latin}}&photos=true
      &photo_license=cc-by-nc,cc-by,cc0
      &quality_grade=research&per_page=5&order_by=votes
""")


if __name__ == "__main__":
    main()