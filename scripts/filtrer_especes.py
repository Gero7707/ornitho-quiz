"""
Filtre le fichier especes_manquantes.csv pour ne garder que
les espèces sauvages réelles observées en France métropolitaine.

Usage : python scripts/filtrer_especes.py
"""

import csv

# ─── ESPÈCES À EXCLURE ────────────────────────────────────────────────────────
# Espèces captives, introduites, parasites, non-oiseaux

EXCLURE = {
    # Entrées parasites Xeno-canto
    "Mystery mystery",       # identité inconnue
    "Sonus naturalis",       # paysage sonore

    # Non-oiseaux
    "Vulpes vulpes",         # Renard roux

    # Espèces domestiques / captives
    "Gallus gallus",         # Coq domestique
    "Coturnix japonica",     # Caille du Japon
    "Meleagris gallopavo",   # Dindon sauvage (introduit)
    "Pavo cristatus",        # Paon

    # Perroquets et psittacidés captifs
    "Melopsittacus undulatus",   # Perruche ondulée
    "Nymphicus hollandicus",     # Calopsitte
    "Taeniopygia guttata",       # Diamant mandarin
    "Psittacus erithacus",       # Perroquet gris
    "Agapornis roseicollis",     # Inséparable
    "Amazona aestiva",           # Amazone à front bleu
    "Amazona oratrix",           # Amazone à tête jaune
    "Cacatua sulphurea",         # Cacatoès soufré
    "Cyanoliseus patagonus",     # Conure de Patagonie
    "Psittacara mitratus",       # Conure mitrée

    # Anatidés exotiques captifs
    "Aix sponsa",                # Canard carolin
    "Callonetta leucophrys",     # Callonette à collier
    "Chloephaga picta",          # Ouette de Magellan
    "Dendrocygna autumnalis",    # Dendrocygne à ventre noir
    "Dendrocygna viduata",       # Dendrocygne veuf
    "Anser cygnoides",           # Oie cygnoïde
    "Anser indicus",             # Oie à tête barrée
    "Balearica regulorum",       # Grue royale
    "Branta hutchinsii",         # Bernache de Hutchins

    # Autres espèces captives
    "Serinus canaria",           # Canari (domestique)
    "Colinus virginianus",       # Colin de Virginie (introduit)
    "Gubernatrix cristata",      # Cardinal jaune
    "Passer luteus",             # Moineau doré
    "Sinosuthora alphonsiana",   # Paradoxornis (captif)
    "Sinosuthora webbiana",      # Paradoxornis (captif)
    "Chrysolophus pictus",       # Faisan doré (introduit, semi-captif)
    "Cyanopica cooki",           # Pie bleue ibérique (pas en France)
    "Geronticus eremita",        # Ibis chauve (réintroduction expérimentale)
    "Phoeniconaias minor",       # Flamant nain (pas en France métro)
    "Quiscalus mexicanus",       # Quiscale à longue queue
    "Mimus polyglottos",         # Moqueur polyglotte (accidentel rare)
    "Geothlypis trichas",        # Paruline masked (accidentel très rare)
    "Zonotrichia albicollis",    # Bruant à gorge blanche (très rare)
    "Chersophilus duponti",      # Sirli de Dupont (espagnol)
    "Streptopelia roseogrisea",  # Tourterelle africaine (captifs échappés)
    "Loxia scotica",             # Bec-croisé d'Écosse (endémique Écosse)
    "Larus dominicanus",         # Goéland dominicain (hémisphère sud)
}

# ─── LECTURE ET FILTRAGE ──────────────────────────────────────────────────────

especes_retenues = []
especes_exclues  = []

with open("especes_manquantes.csv", encoding="utf-8") as f:
    reader = csv.DictReader(f)
    for row in reader:
        nom_latin = row["nom_latin"].strip()
        nom_en    = row["nom_anglais"].strip()

        if nom_latin in EXCLURE:
            especes_exclues.append((nom_latin, nom_en))
        else:
            especes_retenues.append((nom_latin, nom_en))

# ─── RÉSULTATS ────────────────────────────────────────────────────────────────

print(f"✅ Espèces retenues : {len(especes_retenues)}")
print(f"❌ Espèces exclues  : {len(especes_exclues)}\n")

print("=== Espèces retenues ===\n")
for nom_latin, nom_en in sorted(especes_retenues, key=lambda x: x[1]):
    print(f"  {nom_latin:<45} {nom_en}")

# ─── EXPORT ───────────────────────────────────────────────────────────────────

with open("especes_a_importer.csv", "w", encoding="utf-8") as f:
    f.write("nom_latin,nom_anglais\n")
    for nom_latin, nom_en in sorted(especes_retenues, key=lambda x: x[1]):
        f.write(f'"{nom_latin}","{nom_en}"\n')

print(f"\n💾 Résultats exportés dans especes_a_importer.csv")