"""

ScriptBesoin_2.py
====================
Besoin Client 2 — Clustering selon la position géographique

Usage :
    # Mode simple (un point)
    python ScriptBesoin_2.py --latitude 48.8566 --longitude 2.3522

    # Mode batch (tous les points d'un coup — utilisé par PHP)
    python ScriptBesoin_2.py --batch /tmp/irve_in.json --output /tmp/irve_out.json

Description :
    Prend en entrée les coordonnées GPS d'une borne de recharge et renvoie
    le numéro du cluster auquel elle appartient, en chargeant le modèle
    K-Means préalablement entraîné. Aucun réentraînement n'est effectué.

Prérequis :
    modeles/kmeans_clustering.pkl
    modeles/scaler_clustering.pkl
    modeles/meta_clustering.json

"""

import argparse
import sys
import os
import json
import numpy as np
import warnings
warnings.filterwarnings('ignore')

# Force l'UTF-8 en sortie : sous Windows, Python écrit sinon en cp1252 et les
# accents des messages ("Cluster associé") sont altérés côté PHP.
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

try:
    import joblib
except ImportError:
    print("Package 'joblib' manquant. Installez-le : pip install joblib")
    sys.exit(1)

try:
    import pandas as pd
except ImportError:
    print("Package 'pandas' manquant. Installez-le : pip install pandas")
    sys.exit(1)

BASE_DIR      = os.path.dirname(os.path.abspath(__file__))
MODELE_KMEANS = os.path.join(BASE_DIR, "modeles", "kmeans_clustering.pkl")
MODELE_SCALER = os.path.join(BASE_DIR, "modeles", "scaler_clustering.pkl")
META_FILE     = os.path.join(BASE_DIR, "modeles", "meta_clustering.json")


def verifier_fichiers():
    for chemin in [MODELE_KMEANS, MODELE_SCALER]:
        if not os.path.exists(chemin):
            print(f"Fichier manquant : {chemin}")
            print("Exécutez d'abord le notebook d'entraînement.")
            sys.exit(1)


def charger_modele():
    return joblib.load(MODELE_KMEANS), joblib.load(MODELE_SCALER)


def charger_meta():
    if os.path.exists(META_FILE):
        with open(META_FILE, "r") as f:
            return json.load(f)
    return {}


def predire_cluster(latitude: float, longitude: float) -> int:
    """Prédit le cluster d'une borne à partir de ses coordonnées GPS."""
    verifier_fichiers()
    kmeans, scaler = charger_modele()

    coords_df = pd.DataFrame([[latitude, longitude]], columns=['latitude', 'longitude'])
    coords_scaled = scaler.transform(coords_df)
    return int(kmeans.predict(coords_scaled)[0])


def predire_batch(input_file: str, output_file: str):
    """
    Mode batch : lit un fichier JSON contenant une liste de points
    [{"id_pdc": 1, "latitude": 48.8, "longitude": 2.3}, ...]
    et écrit les résultats dans output_file :
    [{"id_pdc": 1, "cluster": 2}, ...]
    """
    verifier_fichiers()
    kmeans, scaler = charger_modele()

    # Lecture du fichier d'entrée
    with open(input_file, "r") as f:
        points = json.load(f)

    if not points:
        with open(output_file, "w") as f:
            json.dump([], f)
        return

    # Construction du DataFrame pour prédiction vectorisée (très rapide)
    df = pd.DataFrame(points)
    coords_scaled = scaler.transform(df[['latitude', 'longitude']])
    clusters = kmeans.predict(coords_scaled)

    # Construction des résultats
    resultats = []
    for i, point in enumerate(points):
        resultats.append({
            "id_pdc": point["id_pdc"],
            "cluster": int(clusters[i])
        })

    # Écriture du fichier de sortie
    with open(output_file, "w") as f:
        json.dump(resultats, f)

    print(f"Batch terminé : {len(resultats)} points prédits.")


def main():
    parser = argparse.ArgumentParser(
        description="Prédit le cluster géographique d'une borne IRVE."
    )
    parser.add_argument("--latitude",  type=float, help="Latitude de la borne")
    parser.add_argument("--longitude", type=float, help="Longitude de la borne")
    parser.add_argument("--info",      action="store_true", help="Affiche les infos du modèle")
    parser.add_argument("--batch",     type=str, help="Fichier JSON d'entrée (mode batch)")
    parser.add_argument("--output",    type=str, help="Fichier JSON de sortie (mode batch)")

    args, unknown = parser.parse_known_args()

    # ── Mode batch (appelé par PHP pour tous les points d'un coup) ──
    if args.batch and args.output:
        predire_batch(args.batch, args.output)
        return

    # ── Mode simple (un seul point) ──
    if args.latitude is None or args.longitude is None:
        print("\n[Avis] Aucun argument détecté.")
        print("-> Exécution avec les coordonnées par défaut (Paris).\n")
        args.latitude  = 48.8566
        args.longitude = 2.3522
        args.info      = True

    if args.info:
        meta = charger_meta()
        if meta:
            print("\nInformations sur le modèle :")
            print(f"  k (clusters)            : {meta.get('k_optimal')}")
            print(f"  Silhouette Coefficient  : {meta.get('silhouette')}")
            print(f"  Calinski-Harabasz       : {meta.get('calinski_harabasz')}")
            print(f"  Davies-Bouldin          : {meta.get('davies_bouldin')}")
            print(f"  Bornes (train)          : {meta.get('n_bornes')}\n")

    print(f"Coordonnées : lat={args.latitude}, lon={args.longitude}")
    cluster = predire_cluster(args.latitude, args.longitude)
    print(f"Cluster associé : {cluster}")


if __name__ == "__main__":
    main()