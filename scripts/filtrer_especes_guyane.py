"""
Filtre les espèces parasites du CSV especes_manquantes_guyane.csv
(captifs, non-oiseaux, espèces hors territoire, noms suspects)

Génère especes_a_importer_guyane.csv, prêt pour import_especes_guyane.py

Usage : python scripts/filtrer_especes_guyane.py
"""

import csv

# ─── Espèces à exclure explicitement ─────────────────────────────────────────
# Même logique que filtrer_especes.py métropole :
# captifs, non-oiseaux, noms génériques XC, perroquets de cage, etc.

EXCLUSIONS = {
    # Non-oiseaux / sons naturels
    "Mystery mystery",
    "Sonus naturalis",
    # Noms génériques Xeno-canto
    "Aves aves",
    # Autres à compléter manuellement si nécessaire
}

# Mots-clés suspects dans le nom anglais (captifs, domestiques, hybrides)
MOTS_SUSPECTS = [
    "hybrid",
    "domestic",
    "captive",
    "undetermined",
    "unidentified",
    "unknown",
]

# ─── Lecture du CSV source ────────────────────────────────────────────────────

with open("especes_manquantes_guyane.csv", encoding="utf-8") as f:
    reader = csv.DictReader(f)
    especes = list(reader)

print(f"=== Filtrage espèces Guyane ===\n")
print(f"📋 Espèces à analyser : {len(especes)}\n")

retenues  = []
exclues   = []

for esp in especes:
    nom_latin  = esp["nom_latin"].strip()
    nom_anglais = esp["nom_anglais"].strip().lower()

    # Exclusion explicite
    if nom_latin in EXCLUSIONS:
        exclues.append((nom_latin, esp["nom_anglais"], "exclusion explicite"))
        continue

    # Exclusion par mot-clé
    raison = next((m for m in MOTS_SUSPECTS if m in nom_anglais), None)
    if raison:
        exclues.append((nom_latin, esp["nom_anglais"], f"mot-clé : {raison}"))
        continue

    retenues.append(esp)

# ─── Résumé ───────────────────────────────────────────────────────────────────

print(f"✅ Espèces retenues  : {len(retenues)}")
print(f"🚫 Espèces exclues   : {len(exclues)}\n")

if exclues:
    print("Exclues :")
    for nom_latin, nom_anglais, raison in exclues:
        print(f"  {nom_latin:<40} {nom_anglais:<35} ({raison})")

# ─── Export CSV ───────────────────────────────────────────────────────────────

with open("especes_a_importer_guyane.csv", "w", encoding="utf-8") as f:
    f.write("nom_latin,nom_anglais\n")
    for esp in retenues:
        f.write(f'"{esp["nom_latin"]}","{esp["nom_anglais"]}"\n')

print(f"\n💾 Résultats exportés dans especes_a_importer_guyane.csv")
print(f"\n⚠️  Pense à ouvrir especes_a_importer_guyane.csv et vérifier manuellement")
print(f"   avant de lancer import_especes_guyane.py !")