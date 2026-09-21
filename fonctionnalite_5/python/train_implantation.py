"""
train_implantation.py
=====================
Entraînement des modèles de classification du TYPE D'IMPLANTATION.
Modèles : RandomForestClassifier + SVC (Support Vector Machine).
Après l'entraînement, exporte DEUX artefacts :
  1. Les modèles et encodeurs .pkl  (utilisés par predict_implantation.py)
  2. Un fichier metrics_implantation.json contenant :
       - Les métriques d'évaluation (précision, rappel, F1) pour RF et SVM
       - L'importance des features issue du Random Forest
       - La date d'entraînement
Usage :
    python train_implantation.py
    (Le fichier export_IA.csv doit être dans le même répertoire)
Fichiers produits :
    modele_random_forest.pkl
    modele_svm.pkl
    encoders.pkl
    encoder_y.pkl
    metrics_implantation.json   ← NOUVEAU
"""
import json
import os
import sys
from datetime import datetime, timezone
import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import (
    accuracy_score,
    classification_report,
    f1_score,
    precision_score,
    recall_score,
)
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder
from sklearn.svm import SVC
# ─────────────────────────────────────────────────────────────────────────────
# CONSTANTES
# ─────────────────────────────────────────────────────────────────────────────
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
# Fichier de données source (produit par la partie Big Data)
CSV_PATH = os.path.join(SCRIPT_DIR, "export_IA.csv")
# Chemins de sauvegarde des artefacts
PATH_RF          = os.path.join(SCRIPT_DIR, "modele_random_forest.pkl")
PATH_SVM         = os.path.join(SCRIPT_DIR, "modele_svm.pkl")
PATH_ENC_X       = os.path.join(SCRIPT_DIR, "encoders.pkl")
PATH_ENC_Y       = os.path.join(SCRIPT_DIR, "encoder_y.pkl")
PATH_METRICS_JSON = os.path.join(SCRIPT_DIR, "metrics_implantation.json")
# Variable cible
TARGET_COL = "implantation_station"
# Colonnes explicatives — ordre EXACT requis par predict_implantation.py
FEATURES_ORDRE = [
    "nbre_pdc",
    "puissance_nominale",
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
# Colonnes booléennes → valeur par défaut "FAUX" si manquante
BOOL_COLS = [
    "prise_type_2",
    "prise_type_combo_ccs",
    "prise_type_chademo",
    "paiement_acte",
    "reservation",
]
# Paramètres d'entraînement
TEST_SIZE   = 0.20
RANDOM_SEED = 42
RF_N_ESTIMATORS = 100     # Nombre d'arbres dans la forêt aléatoire
# Labels lisibles pour chaque feature (utilisés dans le JSON exporté)
FEATURE_LABELS = {
    "nbre_pdc":              "Nb points de charge",
    "puissance_nominale":    "Puissance nominale",
    "prise_type_2":          "Prise Type 2",
    "prise_type_combo_ccs":  "Combo CCS",
    "prise_type_chademo":    "CHAdeMO",
    "paiement_acte":         "Paiement à l'acte",
    "condition_acces":       "Condition accès",
    "reservation":           "Réservation",
    "accessibilite_pmr":     "Accessibilité PMR",
    "restriction_gabarit":   "Restriction gabarit",
    "horaires":              "Horaires",
}
# ─────────────────────────────────────────────────────────────────────────────
# CHARGEMENT ET NETTOYAGE DES DONNÉES
# ─────────────────────────────────────────────────────────────────────────────
def charger_donnees() -> tuple[pd.DataFrame, pd.Series]:
    """
    Charge le CSV, sélectionne les features et la cible,
    applique les remplacements et nettoyages nécessaires.
    Returns:
        X (DataFrame) : features nettoyées et en str
        y (Series)    : variable cible (str)
    """
    if not os.path.isfile(CSV_PATH):
        print(f"[ERREUR] Fichier introuvable : {CSV_PATH}", file=sys.stderr)
        sys.exit(1)

    print(f"[INFO] Chargement de {CSV_PATH}...")
    try:
        data = pd.read_csv(CSV_PATH, sep=";", encoding="latin-1")
    except Exception as exc:
        print(f"[ERREUR] Lecture CSV : {exc}", file=sys.stderr)
        sys.exit(1)
    # Vérification de la colonne cible
    if TARGET_COL not in data.columns:
        print(f"[ERREUR] Colonne cible '{TARGET_COL}' absente du CSV.", file=sys.stderr)
        sys.exit(1)
    # Vérification des colonnes features
    manquantes = [c for c in FEATURES_ORDRE if c not in data.columns]
    if manquantes:
        print(f"[ERREUR] Colonnes features manquantes : {manquantes}", file=sys.stderr)
        sys.exit(1)
    # Copie sécurisée des features
    X = data[FEATURES_ORDRE].copy()
    y = data[TARGET_COL].copy()
    # Supprimer les lignes où la cible est nulle
    masque_valide = y.notna()
    X = X[masque_valide].copy()
    y = y[masque_valide].copy()
    n_supprimes = (~masque_valide).sum()
    if n_supprimes > 0:
        print(f"[INFO] {n_supprimes} lignes supprimées (cible nulle).")
    # Remplissage des colonnes booléennes
    for col in BOOL_COLS:
        X.loc[:, col] = X[col].fillna("FAUX")
    # Remplissage du reste
    X = X.fillna("inconnu")
    # Normalisation booléens textuels → 1 / 0
    X = X.replace({
        "VRAI":  1,
        "FAUX":  0,
        "True":  1,
        "False": 0,
        True:    1,
        False:   0,
    })
    # Conversion forcée en str (garantit LabelEncoder cohérent)
    for col in X.columns:
        X[col] = X[col].astype(str)
    # Filtrage des classes avec moins de 2 membres (requis pour train_test_split stratifié)
    counts = y.value_counts()
    classes_rares = counts[counts < 2].index.tolist()
    if classes_rares:
        print(f"[AVERTISSEMENT] Classes avec moins de 2 membres supprimées : {classes_rares}")
        masque_filtrage = ~y.isin(classes_rares)
        X = X[masque_filtrage].reset_index(drop=True)
        y = y[masque_filtrage].reset_index(drop=True)
    print(f"[INFO] Dataset : {len(X)} lignes × {len(X.columns)} colonnes.")
    print(f"[INFO] Classes cibles : {sorted(y.unique())}")
    return X, y

    # ─────────────────────────────────────────────────────────────────────────────
# ENCODAGE
# ─────────────────────────────────────────────────────────────────────────────
def encoder_features(X: pd.DataFrame) -> tuple[pd.DataFrame, dict]:
    """
    Applique un LabelEncoder par colonne.
    Returns:
        X_encoded (DataFrame) : features encodées
        encoders  (dict)      : { nom_colonne: LabelEncoder }
    """
    encoders = {}
    for col in X.columns:
        le = LabelEncoder()
        X[col] = le.fit_transform(X[col])
        encoders[col] = le
    return X, encoders
def encoder_cible(y: pd.Series) -> tuple[np.ndarray, LabelEncoder]:
    """
    Encode la variable cible.
    Returns:
        y_encoded (ndarray)    : cible encodée
        le_y      (LabelEncoder)
    """
    le_y = LabelEncoder()
    y_encoded = le_y.fit_transform(y.astype(str))
    return y_encoded, le_y
# ─────────────────────────────────────────────────────────────────────────────
# ENTRAÎNEMENT
# ─────────────────────────────────────────────────────────────────────────────
def entrainer_modeles(
    X_train: np.ndarray,
    y_train: np.ndarray,
) -> tuple[RandomForestClassifier, SVC]:
    """
    Entraîne RandomForestClassifier et SVC sur les données d'entraînement.
    Returns:
        rf_model  (RandomForestClassifier)
        svm_model (SVC)
    """
    print("[INFO] Entraînement Random Forest...")
    rf_model = RandomForestClassifier(
        n_estimators=RF_N_ESTIMATORS,
        random_state=RANDOM_SEED,
        n_jobs=-1,          # Utiliser tous les cœurs disponibles
    )
    rf_model.fit(X_train, y_train)
    print("[INFO] Random Forest entraîné.")
    print("[INFO] Entraînement SVM...")
    svm_model = SVC(
        kernel="rbf",
        random_state=RANDOM_SEED,
        probability=False,  # Pas besoin de probabilités pour predict()
    )
    svm_model.fit(X_train, y_train)
    print("[INFO] SVM entraîné.")
    return rf_model, svm_model
# ─────────────────────────────────────────────────────────────────────────────
# ÉVALUATION ET EXPORT DES MÉTRIQUES
# ─────────────────────────────────────────────────────────────────────────────
def calculer_metriques(
    rf_model:  RandomForestClassifier,
    svm_model: SVC,
    le_y:      LabelEncoder,
    X_test:    np.ndarray,
    y_test:    np.ndarray,
    noms_cols: list[str],
) -> dict:
    """
    Calcule les métriques de performance sur le jeu de test.
    Utilise la moyenne pondérée (weighted) pour tenir compte du déséquilibre
    éventuel entre les classes.
    Returns:
        dict contenant toutes les métriques et l'importance des features.
    """
    # ── Prédictions ──
    y_pred_rf  = rf_model.predict(X_test)
    y_pred_svm = svm_model.predict(X_test)
    # ── Métriques RF ──
    rf_accuracy  = float(accuracy_score(y_test, y_pred_rf))
    rf_precision = float(precision_score(y_test, y_pred_rf, average="weighted", zero_division=0))
    rf_recall    = float(recall_score(y_test, y_pred_rf, average="weighted", zero_division=0))
    rf_f1        = float(f1_score(y_test, y_pred_rf, average="weighted", zero_division=0))
    # ── Métriques SVM ──
    svm_accuracy  = float(accuracy_score(y_test, y_pred_svm))
    svm_precision = float(precision_score(y_test, y_pred_svm, average="weighted", zero_division=0))
    svm_recall    = float(recall_score(y_test, y_pred_svm, average="weighted", zero_division=0))
    svm_f1        = float(f1_score(y_test, y_pred_svm, average="weighted", zero_division=0))
    # ── Importance des features (RF uniquement → classifieur à arbres) ──
    importances_brutes: np.ndarray = rf_model.feature_importances_
    # Associer chaque importance à son label lisible
    feature_importances: dict[str, float] = {}
    for nom_col, importance in zip(noms_cols, importances_brutes):
        label = FEATURE_LABELS.get(nom_col, nom_col)
        feature_importances[label] = round(float(importance), 6)
    # ── Classes prédites ──
    classes_connues = [str(c) for c in le_y.classes_]
    metriques = {
        # Métriques Random Forest
        "rf_accuracy":  round(rf_accuracy,  4),
        "rf_precision": round(rf_precision, 4),
        "rf_recall":    round(rf_recall,    4),
        "rf_f1":        round(rf_f1,        4),
        # Métriques SVM
        "svm_accuracy":  round(svm_accuracy,  4),
        "svm_precision": round(svm_precision, 4),
        "svm_recall":    round(svm_recall,    4),
        "svm_f1":        round(svm_f1,        4),
        # Importance des features (Random Forest)
        "feature_importances": feature_importances,
        # Métadonnées
        "classes":    classes_connues,
        "n_test":     int(len(y_test)),
        "trained_at": datetime.now(timezone.utc).isoformat(),
        "type":       "implantation",
    }
    # ── Rapport console ──
    print("\n" + "-" * 50)
    print("MÉTRIQUES RANDOM FOREST (jeu de test)")
    print(f"  Accuracy  : {rf_accuracy:.4f}")
    print(f"  Precision : {rf_precision:.4f}")
    print(f"  Rappel    : {rf_recall:.4f}")
    print(f"  F1-Score  : {rf_f1:.4f}")
    print("\nMÉTRIQUES SVM (jeu de test)")
    print(f"  Accuracy  : {svm_accuracy:.4f}")
    print(f"  Precision : {svm_precision:.4f}")
    print(f"  Rappel    : {svm_recall:.4f}")
    print(f"  F1-Score  : {svm_f1:.4f}")
    print("\nIMPORTANCE DES FEATURES (Random Forest)")
    for feat, val in sorted(feature_importances.items(), key=lambda x: -x[1]):
        barre = "#" * int(val * 30)    
        print(f"  {feat:<30} {val:.4f}  {barre}")
    print("-" * 50 + "\n")
    return metriques

    def exporter_metriques(metriques: dict) -> None:
    """
    Sauvegarde le dictionnaire de métriques en JSON.
    Fichier : metrics_implantation.json (même répertoire que les .pkl)
    """
    try:
        with open(PATH_METRICS_JSON, "w", encoding="utf-8") as f:
            json.dump(metriques, f, ensure_ascii=False, indent=2)
        print(f"[INFO] Métriques exportées -> {PATH_METRICS_JSON}")
    except OSError as exc:
        print(f"[ERREUR] Impossible d'écrire {PATH_METRICS_JSON} : {exc}", file=sys.stderr)
        sys.exit(1)
    print(f"       {PATH_SVM}")
    print(f"       {PATH_ENC_X}")
    print(f"       {PATH_ENC_Y}")
    print("\n[SUCCES] Entraînement implantation terminé.")
if __name__ == "__main__":
    main()