"""
predict_puissance.py
====================
Script de prédiction de la PUISSANCE NOMINALE (kW) d'une borne IRVE.
Utilise deux modèles entraînés : Random Forest (régresseur) et SVR (régresseur).

Entrée  : objet JSON, soit en argument (sys.argv[1]), soit sur l'entrée
          standard (stdin) — c'est ainsi que PHP l'envoie, car sous Windows
          les guillemets d'un argument JSON sont altérés par le shell.
Sortie  : objet JSON sur stdout
            { "random_forest": 22.0, "svm": 20.5, "moyenne": 21.25, "ecart": 1.5 }
Erreur  : objet JSON sur stdout  { "error": "message" }  + code retour 1
"""

import sys
import json
import os

import joblib
import pandas as pd
import numpy as np

# Force l'UTF-8 sur les flux standard : sous Windows, Python utilise sinon le
# code page de la console (cp1252) et les accents ("Parking privé", "Accès
# libre") sont altérés à la lecture comme à l'écriture, ce qui casse le JSON
# échangé avec PHP.
for _flux in (sys.stdin, sys.stdout, sys.stderr):
    if hasattr(_flux, "reconfigure"):
        _flux.reconfigure(encoding="utf-8", errors="replace")

# ─────────────────────────────────────────────────────────────────────────────
# CONSTANTES
# ─────────────────────────────────────────────────────────────────────────────

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))

PATH_RF    = os.path.join(SCRIPT_DIR, "modele_random_forest_puissance.pkl")
PATH_SVM   = os.path.join(SCRIPT_DIR, "modele_svm_puissance.pkl")
PATH_ENC_X = os.path.join(SCRIPT_DIR, "encoders_puissance.pkl")

# Ordre exact des features utilisées lors de l'entraînement
FEATURES_ORDRE = [
    "nbre_pdc",
    "prise_type_2",
    "prise_type_combo_ccs",
    "prise_type_chademo",
    "paiement_acte",
    "condition_acces",
    "reservation",
    "accessibilite_pmr",
    "restriction_gabarit",
    "horaires",
]

# Colonnes booléennes
BOOL_COLS = [
    "prise_type_2",
    "prise_type_combo_ccs",
    "prise_type_chademo",
    "paiement_acte",
    "reservation",
]

# Puissance nominale plancher raisonnable (kW)
PUISSANCE_MIN_KW = 0.0
PUISSANCE_MAX_KW = 999.0

# ─────────────────────────────────────────────────────────────────────────────
# UTILITAIRES
# ─────────────────────────────────────────────────────────────────────────────

def sortie_erreur(message: str) -> None:
    """Affiche un JSON d'erreur sur stdout et termine avec code 1."""
    print(json.dumps({"error": message}, ensure_ascii=False))
    sys.exit(1)


def charger_modeles():
    """
    Charge les trois artefacts depuis le disque.
    """
    for chemin in [PATH_RF, PATH_SVM, PATH_ENC_X]:
        if not os.path.isfile(chemin):
            sortie_erreur(
                f"Fichier modèle introuvable : {chemin}. "
                "Exécutez train_puissance.py avant d'utiliser ce script."
            )

    try:
        rf_model   = joblib.load(PATH_RF)
        svm_model  = joblib.load(PATH_SVM)
        encoders_X = joblib.load(PATH_ENC_X)
    except Exception as exc:
        sortie_erreur(f"Erreur lors du chargement des modèles : {exc}")

    return rf_model, svm_model, encoders_X


def lire_arguments() -> dict:
    """
    Lit le JSON d'entrée : en argument (sys.argv[1]) ou, à défaut, sur stdin.
    """
    if len(sys.argv) > 2:
        sortie_erreur(
            "Usage : python predict_puissance.py '<JSON>'  ou  echo '<JSON>' | python predict_puissance.py\n"
            "Exemple : python predict_puissance.py '{\"nbre_pdc\":4,\"prise_type_2\":\"VRAI\",...}'"
        )

    # JSON en argument (usage manuel) ou sur stdin (appel depuis PHP)
    brut = sys.argv[1] if len(sys.argv) == 2 else sys.stdin.read()

    if not brut.strip():
        sortie_erreur("Aucune donnée JSON reçue (ni en argument, ni sur l'entrée standard).")

    try:
        data = json.loads(brut)
    except json.JSONDecodeError as exc:
        sortie_erreur(f"JSON invalide en entrée : {exc}")

    if not isinstance(data, dict):
        sortie_erreur("L'entrée JSON doit être un objet (dictionnaire).")

    manquantes = [col for col in FEATURES_ORDRE if col not in data]
    if manquantes:
        sortie_erreur(
            f"Champs manquants dans le JSON : {manquantes}. "
            f"Tous les champs requis : {FEATURES_ORDRE}"
        )

    return data


def valider_types(data: dict) -> dict:
    """
    Convertit et valide les types de chaque champ.
    """
    propre = {}

    # Champ numérique
    try:
        propre["nbre_pdc"] = float(data["nbre_pdc"])
        if not np.isfinite(propre["nbre_pdc"]) or propre["nbre_pdc"] < 0:
            sortie_erreur(f"'nbre_pdc' doit être un entier positif, reçu : {data['nbre_pdc']}")
    except (TypeError, ValueError):
        sortie_erreur(f"'nbre_pdc' doit être numérique, reçu : {repr(data['nbre_pdc'])}")

    # Champs texte
    for col in FEATURES_ORDRE:
        if col == "nbre_pdc":
            continue
        valeur = data[col]
        propre[col] = "" if valeur is None else str(valeur).strip()

    # Normalisation booléens
    for col in BOOL_COLS:
        v = propre.get(col, "").upper()
        if v in ("1", "TRUE", "OUI", "YES"):
            propre[col] = "VRAI"
        elif v in ("0", "FALSE", "NON", "NO"):
            propre[col] = "FAUX"

    return propre


def encoder_features(df: pd.DataFrame, encoders_X: dict) -> pd.DataFrame:
    """
    Applique les LabelEncoders colonne par colonne avec fallback robuste.
    """
    for col, encoder in encoders_X.items():
        if col not in df.columns:
            continue

        valeur = str(df[col].iloc[0])
        classes_connues: list = list(encoder.classes_)

        if valeur in classes_connues:
            valeur_encodee = valeur
        elif "inconnu" in classes_connues:
            valeur_encodee = "inconnu"
        else:
            valeur_encodee = classes_connues[0]

        try:
            df[col] = encoder.transform([valeur_encodee])
        except Exception as exc:
            sortie_erreur(f"Échec d'encodage de '{col}' (valeur={valeur_encodee}) : {exc}")

    return df


def clamp_puissance(valeur: float) -> float:
    """
    Borne la puissance prédite dans un intervalle physiquement réaliste (évite les kW négatifs).
    """
    return max(PUISSANCE_MIN_KW, min(PUISSANCE_MAX_KW, valeur))


# ─────────────────────────────────────────────────────────────────────────────
# PROGRAMME PRINCIPAL
# ─────────────────────────────────────────────────────────────────────────────

def main() -> None:
    # 1. Chargement des modèles
    rf_model, svm_model, encoders_X = charger_modeles()

    # 2. Lecture et validation de l'entrée
    data_brut   = lire_arguments()
    data_propre = valider_types(data_brut)

    # 3. Construction du DataFrame dans l'ordre exact d'entraînement
    try:
        df = pd.DataFrame([data_propre])[FEATURES_ORDRE]
    except KeyError as exc:
        sortie_erreur(f"Colonne manquante lors de la construction du DataFrame : {exc}")

    # Convertir les colonnes non-numériques en str
    for col in df.columns:
        if col != "nbre_pdc":
            df[col] = df[col].astype(str)

    # 4. Encodage des features catégorielles
    df = encoder_features(df, encoders_X)

    # 5. Vérification : aucune valeur nulle
    if df.isnull().any().any():
        nulles = df.columns[df.isnull().any()].tolist()
        sortie_erreur(f"Valeurs manquantes après encodage dans les colonnes : {nulles}")

    # 6. Prédictions
    try:
        val_rf  = float(rf_model.predict(df)[0])
        val_svm = float(svm_model.predict(df)[0])
    except Exception as exc:
        sortie_erreur(f"Erreur lors de la prédiction : {exc}")

    # 7. Borne dans un intervalle physique
    val_rf  = clamp_puissance(val_rf)
    val_svm = clamp_puissance(val_svm)

    # 8. Métriques complémentaires
    moyenne = round((val_rf + val_svm) / 2, 2)
    ecart   = round(abs(val_rf - val_svm), 2)

    # 9. Résultat structuré
    resultat = {
        "random_forest": round(val_rf, 2),
        "svm":           round(val_svm, 2),
        "moyenne":       moyenne,
        "ecart":         ecart,
    }

    print(json.dumps(resultat, ensure_ascii=False))


if __name__ == "__main__":
    main()