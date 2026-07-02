#!/usr/bin/env python3
"""
Comptage des enregistrements Xeno-canto pour Réunion et Mayotte
Sans téléchargement — uniquement pour estimer le volume
"""

import requests
import time
import os
from dotenv import load_dotenv

load_dotenv()
XC_API_KEY = os.getenv('XC_API_KEY')

ZONES = {
    "Zone Réunion-Madagascar-Mayotte": {
        "box": "-26.0,43.0,-12.0,58.0",
    }
}

def compter_zone(nom, box):
    print(f"\n{'='*50}")
    print(f"Zone : {nom}")
    print(f"Box  : {box}")
    print('='*50)

    url = "https://xeno-canto.org/api/3/recordings"
    params = {
        "query": f"box:{box} grp:birds",
        "key": XC_API_KEY,
        "page": 1
    }

    response = requests.get(url, params=params)
    data = response.json()

    nb_enregistrements = int(data.get("numRecordings", 0))
    nb_especes = int(data.get("numSpecies", 0))
    nb_pages = int(data.get("numPages", 0))

    print(f"Enregistrements totaux : {nb_enregistrements}")
    print(f"Espèces totales        : {nb_especes}")
    print(f"Pages                  : {nb_pages}")

    # Comptage qualité A/B uniquement
    print(f"\nComptage qualité A/B (toutes pages)...")

    especes_ab = {}
    total_ab = 0

    for page in range(1, nb_pages + 1):
        params["page"] = page
        r = requests.get(url, params=params)
        d = r.json()

        for rec in d.get("recordings", []):
            if rec.get("q") in ["A", "B"]:
                total_ab += 1
                nom_latin = f"{rec.get('gen', '')} {rec.get('sp', '')}".strip()
                nom_commun = rec.get("en", "")
                if nom_latin not in especes_ab:
                    especes_ab[nom_latin] = {
                        "nom_commun_en": nom_commun,
                        "count": 0
                    }
                especes_ab[nom_latin]["count"] += 1

        print(f"  Page {page}/{nb_pages} — {total_ab} enregistrements A/B trouvés jusqu'ici")
        time.sleep(0.5)  # respecter l'API

    print(f"\nRésultats qualité A/B :")
    print(f"  Enregistrements A/B : {total_ab}")
    print(f"  Espèces avec A/B    : {len(especes_ab)}")

    # Espèces avec 3 sons ou plus (quota atteint)
    especes_3_plus = {k: v for k, v in especes_ab.items() if v["count"] >= 3}
    especes_moins_3 = {k: v for k, v in especes_ab.items() if v["count"] < 3}

    sons_a_importer = sum(min(v["count"], 3) for v in especes_ab.values())
    taille_estimee_go = sons_a_importer * 1 / 1024  # ~1 Mo par son

    print(f"  Espèces avec ≥3 sons A/B  : {len(especes_3_plus)}")
    print(f"  Espèces avec <3 sons A/B  : {len(especes_moins_3)}")
    print(f"\n  Sons à importer (max 3/espèce) : {sons_a_importer}")
    print(f"  Taille estimée               : ~{taille_estimee_go:.2f} Go")

    # Top 20 espèces les mieux couvertes
    print(f"\nTop 20 espèces les mieux couvertes :")
    top20 = sorted(especes_ab.items(), key=lambda x: x[1]["count"], reverse=True)[:20]
    for nom_latin, info in top20:
        print(f"  {nom_latin:<35} {info['nom_commun_en']:<30} {info['count']} sons")

    # Espèces sans aucun son A/B
    print(f"\nEspèces sans son A/B : {nb_especes - len(especes_ab)}")

    return {
        "zone": nom,
        "total_enregistrements": nb_enregistrements,
        "total_especes": nb_especes,
        "enregistrements_ab": total_ab,
        "especes_ab": len(especes_ab),
        "sons_a_importer": sons_a_importer,
        "taille_estimee_go": taille_estimee_go
    }

if __name__ == "__main__":
    resultats = []

    for nom, config in ZONES.items():
        res = compter_zone(nom, config["box"])
        resultats.append(res)
        time.sleep(1)

    # Récapitulatif final
    print(f"\n{'='*50}")
    print("RÉCAPITULATIF")
    print('='*50)
    total_sons = 0
    total_go = 0
    for r in resultats:
        print(f"\n{r['zone']} :")
        print(f"  Espèces totales XC  : {r['total_especes']}")
        print(f"  Espèces avec A/B    : {r['especes_ab']}")
        print(f"  Sons à importer     : {r['sons_a_importer']}")
        print(f"  Taille estimée      : ~{r['taille_estimee_go']:.2f} Go")
        total_sons += r['sons_a_importer']
        total_go += r['taille_estimee_go']

    print(f"\nTOTAL Réunion + Mayotte :")
    print(f"  Sons à importer : {total_sons}")
    print(f"  Taille estimée  : ~{total_go:.2f} Go")
    print(f"\nR2 actuel        : ~6.15 Go")
    print(f"R2 après import  : ~{6.15 + total_go:.2f} Go")