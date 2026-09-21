"""
train_puissance.py
==================
Entraînement des modèles de RÉGRESSION de la PUISSANCE NOMINALE (kW).
Modèles : RandomForestRegressor + SVR (Support Vector Regressor).
Après l'entraînement, exporte DEUX artefacts :
  1. Les modèles et encodeurs .pkl  (utilisés par predict_puissance.py)
  2. Un fichier metrics_puissance.json contenant :
       - Les métriques de régression (MAE, RMSE, R²) pour RF et SVR
       - L'importance des features issue du Random Forest
       - La date d'entraînement
Usage :
    python train_puissance.py
    (Le fichier export_IA.csv doit être dans le même répertoire)
Fichiers produits :
    modele_random_forest_puissance.pkl
    modele_svm_puissance.pkl
    encoders_puissance.pkl
    metrics_puissance.json   ← NOUVEAU
"""
import json
import os
import sys
from datetime import datetime, timezone
import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder
from sklearn.svm import SVR
# ─────────────────────────────────────────────────────────────────────────────
# CONSTANTES
# ─────────────────────────────────────────────────────────────────────────────
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
CSV_PATH = os.path.join(SCRIPT_DIR, "export_IA.csv")
PATH_RF           = os.path.join(SCRIPT_DIR, "modele_random_forest_puissance.pkl")
PATH_SVM          = os.path.join(SCRIPT_DIR, "modele_svm_puissance.pkl")
PATH_ENC_X        = os.path.join(SCRIPT_DIR, "encoders_puissance.pkl")
PATH_METRICS_JSON = os.path.join(SCRIPT_DIR, "metrics_puissance.json")
# Variable cible
TARGET_COL = "puissance_nominale"
# Colonnes explicatives — ordre EXACT requis par predict_puissance.py
# Note : puissance_nominale est la CIBLE → elle n'est pas dans les features
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
BOOL_COLS = [
    "prise_type_2",
    "prise_type_combo_ccs",
    "prise_type_chademo",
    "paiement_acte",
    "reservation",
]
# Paramètres d'entraînement
TEST_SIZE      = 0.20
RANDOM_SEED    = 42
RF_N_ESTIMATORS = 100
# Labels lisibles pour les features
FEATURE_LABELS = {
    "nbre_pdc":              "Nb points de charge",
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
    Charge le CSV, sélectionne les features et la cible numérique,
    applique nettoyage et encodage préliminaire.
    Returns:
        X (DataFrame) : features nettoyées en str (avant encodage LabelEncoder)
        y (Series)    : cible numérique (float) — puissance nominale en kW
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
    if TARGET_COL not in data.columns:
        print(f"[ERREUR] Colonne cible '{TARGET_COL}' absente du CSV.", file=sys.stderr)
        sys.exit(1)
    manquantes = [c for c in FEATURES_ORDRE if c not in data.columns]
    if manquantes:
        print(f"[ERREUR] Colonnes features manquantes : {manquantes}", file=sys.stderr)
        sys.exit(1)
    X = data[FEATURES_ORDRE].copy()
    y = pd.to_numeric(data[TARGET_COL], errors="coerce")
    # Supprimer les lignes où la cible est nulle ou non numérique
    masque_valide = y.notna() & y.ge(0)
    X = X[masque_valide].copy()
    y = y[masque_valide].copy()
    n_supprimes = (~masque_valide).sum()
    if n_supprimes > 0:
        print(f"[INFO] {n_supprimes} lignes supprimées (cible nulle ou négative).")
    # Remplissage des booléens
    for col in BOOL_COLS:
        X.loc[:, col] = X[col].fillna("FAUX")
    X = X.fillna("inconnu")
    # Normalisation booléens
    X = X.replace({
        "VRAI":  1,
        "FAUX":  0,
        "True":  1,
        "False": 0,
        True:    1,
        False:   0,
    })
    # Forcer tout en str pour LabelEncoder cohérent
    for col in X.columns:
        X[col] = X[col].astype(str)
    print(f"[INFO] Dataset : {len(X)} lignes × {len(X.columns)} features.")
    print(f"[INFO] Puissance cible — min: {y.min():.1f} kW | max: {y.max():.1f} kW | moy: {y.mean():.1f} kW")
    return X, y
# ─────────────────────────────────────────────────────────────────────────────
# ENCODAGE
# ─────────────────────────────────────────────────────────────────────────────
def encoder_features(X: pd.DataFrame) -> tuple[pd.DataFrame, dict]:
    """
    Applique un LabelEncoder par colonne catégorielle.
    Returns:
        X_encoded (DataFrame) : features numériques
        encoders  (dict)      : { nom_colonne: LabelEncoder }
    """
    encoders: dict = {}
    for col in X.columns:
        le = LabelEncoder()
        X[col] = le.fit_transform(X[col])
        encoders[col] = le
    return X, encoders
# ─────────────────────────────────────────────────────────────────────────────
# ENTRAÎNEMENT
# ─────────────────────────────────────────────────────────────────────────────
def entrainer_modeles(
    X_train: np.ndarray,
    y_train: np.ndarray,
) -> tuple[RandomForestRegressor, SVR]:
    """
    Entraîne RandomForestRegressor et SVR.
    Returns:
        rf_model  (RandomForestRegressor)
        svm_model (SVR)
    """
    print("[INFO] Entraînement Random Forest Regressor...")
    rf_model = RandomForestRegressor(
        n_estimators=RF_N_ESTIMATORS,
        random_state=RANDOM_SEED,
        n_jobs=-1,
    )
    rf_model.fit(X_train, y_train)
    print("[INFO] Random Forest Regressor entraîné.")
    print("[INFO] Entraînement SVR...")
    svm_model = SVR(kernel="rbf")
    svm_model.fit(X_train, y_train)
    print("[INFO] SVR entraîné.")
    return rf_model, svm_model
# ─────────────────────────────────────────────────────────────────────────────
# ÉVALUATION ET EXPORT DES MÉTRIQUES
# ─────────────────────────────────────────────────────────────────────────────
def calculer_metriques(
    rf_model:  RandomForestRegressor,
    svm_model: SVR,
    X_test:    np.ndarray,
    y_test:    np.ndarray,
    noms_cols: list[str],
) -> dict:
    """
    Calcule les métriques de régression sur le jeu de test :
      - MAE  (Mean Absolute Error)   → erreur moyenne en kW
      - RMSE (Root Mean Square Error)→ erreur quadratique moyenne en kW
      - R²   (coefficient de détermination)
    Returns:
        dict contenant toutes les métriques et l'importance des features.
    """
    # ── Prédictions ──
    y_pred_rf  = rf_model.predict(X_test)
    y_pred_svm = svm_model.predict(X_test)
    # ── Métriques Random Forest ──
    rf_mae  = float(mean_absolute_error(y_test, y_pred_rf))
    rf_rmse = float(np.sqrt(mean_squared_error(y_test, y_pred_rf)))
    rf_r2   = float(r2_score(y_test, y_pred_rf))
    # ── Métriques SVR ──
    svm_mae  = float(mean_absolute_error(y_test, y_pred_svm))
    svm_rmse = float(np.sqrt(mean_squared_error(y_test, y_pred_svm)))
    svm_r2   = float(r2_score(y_test, y_pred_svm))
    # ── Importance des features (RF uniquement — SVR ne les expose pas) ──
    importances_brutes: np.ndarray = rf_model.feature_importances_
    feature_importances: dict[str, float] = {}
    for nom_col, importance in zip(noms_cols, importances_brutes):
        label = FEATURE_LABELS.get(nom_col, nom_col)
        feature_importances[label] = round(float(importance), 6)
    metriques = {
        # Métriques Random Forest Regressor
        "rf_mae":  round(rf_mae,  4),
        "rf_rmse": round(rf_rmse, 4),
        "rf_r2":   round(rf_r2,   4),
        # Métriques SVR
        "svm_mae":  round(svm_mae,  4),
        "svm_rmse": round(svm_rmse, 4),
        "svm_r2":   round(svm_r2,   4),
        # Importance des features
        "feature_importances": feature_importances,
        # Statistiques de la cible (pour affichage dans le front)
        "target_min":  round(float(y_test.min()),  2),
        "target_max":  round(float(y_test.max()),  2),
        "target_mean": round(float(y_test.mean()), 2),
        # Métadonnées
        "n_test":     int(len(y_test)),
        "trained_at": datetime.now(timezone.utc).isoformat(),
        "type":       "puissance",
    }
    # ── Rapport console ──
    print("\n" + "-" * 50)
    print("MÉTRIQUES RANDOM FOREST REGRESSOR (jeu de test)")
    print(f"  MAE  : {rf_mae:.2f} kW")
    print(f"  RMSE : {rf_rmse:.2f} kW")
    print(f"  R²   : {rf_r2:.4f}")
    print("\nMÉTRIQUES SVR (jeu de test)")
    print(f"  MAE  : {svm_mae:.2f} kW")
    print(f"  RMSE : {svm_rmse:.2f} kW")
    print(f"  R²   : {svm_r2:.4f}")
    print("\nIMPORTANCE DES FEATURES (Random Forest)")
    for feat, val in sorted(feature_importances.items(), key=lambda x: -x[1]):
        barre = "#" * int(val * 30)
        print(f"  {feat:<30} {val:.4f}  {barre}")
    print("-" * 50 + "\n")
    return metriques

def exporter_metriques(metriques: dict) -> None:
    """
    Sauvegarde les métriques dans metrics_puissance.json.
    """
    try:
        with open(PATH_METRICS_JSON, "w", encoding="utf-8") as f:
            json.dump(metriques, f, ensure_ascii=False, indent=2)
        print(f"[INFO] Métriques exportées -> {PATH_METRICS_JSON}")
    except OSError as exc:
        print(f"[ERREUR] Impossible d'écrire {PATH_METRICS_JSON} : {exc}", file=sys.stderr)
        sys.exit(1)

# ─────────────────────────────────────────────────────────────────────────────
# PROGRAMME PRINCIPAL
# ─────────────────────────────────────────────────────────────────────────────
def main() -> None:
    # 1. Chargement + nettoyage
    X, y = charger_donnees()
    # 2. Encodage des features
    X_enc, encoders = encoder_features(X)
    # 3. Split train / test (pas de stratify pour la régression)
    X_train, X_test, y_train, y_test = train_test_split(
        X_enc,
        y,
        test_size=TEST_SIZE,
        random_state=RANDOM_SEED,
    )
    print(f"[INFO] Train : {len(X_train)} | Test : {len(X_test)}")
    # 4. Entraînement
    rf_model, svm_model = entrainer_modeles(X_train, y_train)
    # 5. Évaluation + export métriques JSON
    metriques = calculer_metriques(
        rf_model, svm_model,
        X_test.values if hasattr(X_test, 'values') else X_test,
        y_test.values if hasattr(y_test, 'values') else y_test,
        noms_cols=FEATURES_ORDRE,
    )
    exporter_metriques(metriques)
    # 6. Sauvegarde des artefacts .pkl
    joblib.dump(rf_model,  PATH_RF)
    joblib.dump(svm_model, PATH_SVM)
    joblib.dump(encoders,  PATH_ENC_X)
    print(f"[INFO] Modèles sauvegardés :")
    print(f"       {PATH_RF}")
    print(f"       {PATH_SVM}")
    print(f"       {PATH_ENC_X}")
    print("\n[SUCCES] Entraînement puissance terminé.")
if __name__ == "__main__":
    main()