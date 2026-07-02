"""
Explorer IUCN Red List API v4 — voir TOUT ce qui est disponible
================================================================
Lance ce script en local avec ton token IUCN.

Usage :
    python explorer_iucn_v4.py

Il va :
1. Appeler /taxa/scientific_name pour récupérer les infos taxon + liste des assessments
2. Prendre le dernier assessment (latest=true) et récupérer TOUTES les données
3. Sauvegarder les deux JSON complets dans des fichiers pour inspection

Par défaut, il teste avec Fratercula arctica (Macareux moine) — espèce bien documentée.
Tu peux changer GENUS / SPECIES en bas du script.
"""

import requests
import json
import os
import sys
from dotenv import load_dotenv

load_dotenv()

# --- Configuration ---
TOKEN = os.environ.get("IUCN_TOKEN") or os.environ.get("IUCN_REDLIST_KEY")
BASE_URL = "https://api.iucnredlist.org/api/v4"
HEADERS = {"Authorization": f"Bearer {TOKEN}"}

# --- Espèce à tester ---
GENUS = "Fratercula"
SPECIES = "arctica"
# Autres exemples à tester :
# GENUS, SPECIES = "Parus", "major"          # Mésange charbonnière (LC)
# GENUS, SPECIES = "Gypaetus", "barbatus"    # Gypaète barbu (NT)
# GENUS, SPECIES = "Numenius", "arquata"     # Courlis cendré (NT)
# GENUS, SPECIES = "Vanellus", "vanellus"    # Vanneau huppé (NT)


def fetch_json(url, params=None):
    """Requête GET avec gestion d'erreurs."""
    r = requests.get(url, headers=HEADERS, params=params, timeout=30)
    print(f"  → {r.status_code} {url}")
    if r.status_code == 401:
        print("  ❌ Token invalide ou expiré. Vérifie IUCN_TOKEN dans ton .env")
        sys.exit(1)
    if r.status_code == 404:
        print("  ❌ Espèce non trouvée sur IUCN")
        return None
    r.raise_for_status()
    return r.json()


def save_json(data, filename):
    """Sauvegarde un JSON formaté."""
    with open(filename, "w", encoding="utf-8") as f:
        json.dump(data, f, indent=2, ensure_ascii=False)
    print(f"  💾 Sauvegardé : {filename} ({os.path.getsize(filename)} octets)")


def print_structure(data, prefix="", max_depth=3, current_depth=0):
    """Affiche la structure d'un JSON de manière lisible (clés + types)."""
    if current_depth >= max_depth:
        return
    
    if isinstance(data, dict):
        for key, value in data.items():
            if isinstance(value, dict):
                print(f"{prefix}📁 {key}: {{...}} ({len(value)} clés)")
                print_structure(value, prefix + "  │ ", max_depth, current_depth + 1)
            elif isinstance(value, list):
                if len(value) > 0:
                    item_type = type(value[0]).__name__
                    print(f"{prefix}📋 {key}: [{item_type}, ...] ({len(value)} éléments)")
                    if isinstance(value[0], dict) and current_depth < max_depth - 1:
                        print(f"{prefix}  │ Premier élément :")
                        print_structure(value[0], prefix + "  │   ", max_depth, current_depth + 1)
                else:
                    print(f"{prefix}📋 {key}: [] (vide)")
            elif isinstance(value, str):
                preview = value[:80] + "..." if len(value) > 80 else value
                print(f"{prefix}📝 {key}: \"{preview}\"")
            elif isinstance(value, bool):
                print(f"{prefix}✅ {key}: {value}")
            elif isinstance(value, (int, float)):
                print(f"{prefix}🔢 {key}: {value}")
            elif value is None:
                print(f"{prefix}⬜ {key}: null")
            else:
                print(f"{prefix}❓ {key}: {type(value).__name__} = {value}")


def main():
    if not TOKEN:
        print("❌ Token IUCN non trouvé.")
        print("   Ajoute IUCN_TOKEN=ton_token dans ton fichier .env")
        print("   ou lance avec : IUCN_TOKEN=xxx python explorer_iucn_v4.py")
        sys.exit(1)
    
    print(f"\n{'='*70}")
    print(f"  IUCN Red List API v4 — Exploration complète")
    print(f"  Espèce : {GENUS} {SPECIES}")
    print(f"{'='*70}\n")
    
    # ─────────────────────────────────────────────
    # ÉTAPE 1 : /taxa/scientific_name
    # ─────────────────────────────────────────────
    print("📌 ÉTAPE 1 : Infos taxonomiques + liste des assessments")
    print("-" * 50)
    
    taxon_data = fetch_json(
        f"{BASE_URL}/taxa/scientific_name",
        params={"genus_name": GENUS, "species_name": SPECIES}
    )
    
    if not taxon_data:
        return
    
    save_json(taxon_data, f"iucn_taxon_{GENUS}_{SPECIES}.json")
    
    print(f"\n  Structure du JSON taxon :")
    print_structure(taxon_data, "    ", max_depth=3)
    
    # Trouver le dernier assessment
    assessments = taxon_data.get("assessments", [])
    latest = None
    for a in assessments:
        if a.get("latest") is True:
            latest = a
            break
    
    if not latest:
        print("\n  ⚠️ Aucun assessment 'latest' trouvé")
        if assessments:
            latest = assessments[0]
            print(f"  → Utilisation du premier assessment : id={latest.get('assessment_id')}")
        else:
            print("  ❌ Aucun assessment du tout")
            return
    
    assessment_id = latest.get("assessment_id")
    print(f"\n  ✅ Assessment le plus récent : id={assessment_id}")
    print(f"     Année : {latest.get('year_published')}")
    print(f"     Catégorie : {latest.get('red_list_category', {}).get('code')} — {latest.get('red_list_category', {}).get('name')}")
    
    # ─────────────────────────────────────────────
    # ÉTAPE 2 : /assessment/{id} — TOUT
    # ─────────────────────────────────────────────
    print(f"\n{'='*70}")
    print(f"📌 ÉTAPE 2 : Données COMPLÈTES de l'assessment {assessment_id}")
    print("-" * 50)
    
    assessment_data = fetch_json(f"{BASE_URL}/assessment/{assessment_id}")
    
    if not assessment_data:
        return
    
    save_json(assessment_data, f"iucn_assessment_{GENUS}_{SPECIES}.json")
    
    print(f"\n  Structure COMPLÈTE du JSON assessment :")
    print_structure(assessment_data, "    ", max_depth=4)
    
    # ─────────────────────────────────────────────
    # RÉSUMÉ : toutes les clés de premier niveau
    # ─────────────────────────────────────────────
    print(f"\n{'='*70}")
    print(f"📌 RÉSUMÉ — Toutes les clés disponibles dans l'assessment")
    print("-" * 50)
    
    if isinstance(assessment_data, dict):
        for key, value in assessment_data.items():
            if isinstance(value, dict):
                sous_cles = list(value.keys())
                print(f"  📁 {key} → {len(sous_cles)} sous-clés : {sous_cles}")
            elif isinstance(value, list):
                print(f"  📋 {key} → {len(value)} éléments")
            elif isinstance(value, str):
                taille = len(value)
                print(f"  📝 {key} → {taille} caractères")
            elif value is None:
                print(f"  ⬜ {key} → null")
            else:
                print(f"  🔢 {key} → {value}")
    
    # ─────────────────────────────────────────────
    # BONUS : ce que tu utilises déjà vs ce qui est dispo
    # ─────────────────────────────────────────────
    print(f"\n{'='*70}")
    print(f"📌 BONUS — Comparaison avec ce que tu stockes déjà en BDD")
    print("-" * 50)
    
    deja_utilise = ["red_list_category", "population_trend", "criteria", "description"]
    potentiellement_utile = [
        "rationale", "habitat", "threats", "conservation_actions",
        "countries", "biogeographical_realms", "range", "population",
        "use_and_trade", "stresses", "systems", "research_needed",
        "bibliography", "habitats"
    ]
    
    print("\n  ✅ Ce que tu utilises déjà :")
    for key in deja_utilise:
        present = "✓ présent" if key in (assessment_data or {}) else "✗ absent"
        print(f"     {key} — {present}")
    
    print("\n  💡 Ce que tu pourrais exploiter en plus :")
    for key in potentiellement_utile:
        if key in (assessment_data or {}):
            value = assessment_data[key]
            if isinstance(value, list):
                print(f"     {key} — {len(value)} éléments")
            elif isinstance(value, dict):
                print(f"     {key} — {len(value)} sous-clés")
            elif isinstance(value, str):
                print(f"     {key} — {len(value)} caractères")
            else:
                print(f"     {key} — {value}")
        else:
            print(f"     {key} — pas dans cet assessment")
    
    print(f"\n{'='*70}")
    print(f"  Fichiers générés :")
    print(f"  • iucn_taxon_{GENUS}_{SPECIES}.json")
    print(f"  • iucn_assessment_{GENUS}_{SPECIES}.json")
    print(f"\n  Ouvre-les dans ton éditeur pour voir la totalité du JSON !")
    print(f"{'='*70}\n")


if __name__ == "__main__":
    main()