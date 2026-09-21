================================================================================
README - Fonctionnalité 4 : Prédiction des clusters géographiques
================================================================================

DESCRIPTION
-----------
Cette fonctionnalité prédit le cluster géographique auquel appartient chaque
borne de recharge électrique (IRVE) à partir de ses coordonnées GPS
(latitude / longitude).

Elle repose sur un modèle K-Means préalablement entraîné. Aucun réentraînement
n'est effectué à l'exécution : le modèle est simplement chargé puis utilisé
pour la prédiction.

IMPORTANT : Le script ne relance PAS l'entraînement à chaque exécution.
Il charge les modèles (.pkl) préalablement sauvegardés.


CONTENU DU DOSSIER
------------------
  predict_cluster.php       : Point d'entrée HTTP (API REST côté serveur).
                              Récupère les bornes en base, appelle le script
                              Python et retourne les résultats au format JSON.

  IA/ScriptBesoin_2.py      : Script Python de prédiction. Supporte deux modes :
                                - Mode batch  : traite tous les points en une
                                                seule passe (utilisé par PHP).
                                - Mode simple : traite un seul point à la fois
                                                (usage manuel / test).

  IA/modeles/
    kmeans_clustering.pkl   : Modèle K-Means entraîné et sérialisé.
    scaler_clustering.pkl   : Scaler de normalisation des coordonnées GPS.
    meta_clustering.json    : Métadonnées du modèle (k optimal, métriques,
                              nombre de bornes utilisées à l'entraînement).


FONCTIONNEMENT GÉNÉRAL
-----------------------
  1. Le script PHP interroge la base de données et récupère jusqu'à 200 bornes
     avec leurs coordonnées GPS.
  2. Les coordonnées sont transmises au script Python via un fichier JSON
     temporaire (mode batch).
  3. Le script Python charge le modèle K-Means, normalise les coordonnées et
     prédit le cluster pour chaque borne.
  4. Les résultats sont retournés au format JSON avec, pour chaque borne :
     son identifiant, ses informations de station et son numéro de cluster.

  En cas d'échec du mode batch, un fallback automatique effectue la prédiction
  borne par borne (mode simple).

  Un cluster à -1 indique qu'aucune prédiction n'a pu être effectuée.


MÉTRIQUES UTILISÉES POUR ÉVALUER LE CLUSTERING
------------------------------------------------
  - Silhouette Coefficient  : mesure la cohésion et la séparation des clusters
                              (valeur entre -1 et 1, plus proche de 1 = meilleur)
  - Calinski-Harabasz Index : ratio dispersion inter/intra cluster
                              (plus la valeur est élevée = meilleur)
  - Davies-Bouldin Index    : moyenne des similarités entre clusters
                              (plus la valeur est faible = meilleur)

  Ces métriques sont consultables via le flag --info du script Python,
  ou directement dans le fichier meta_clustering.json.


ALGORITHME UTILISÉ
-------------------
  Algorithme : K-Means (clustering par partitionnement)
  Données d'entrée : latitude et longitude des bornes de recharge.
  Le nombre optimal de clusters (k) a été déterminé lors de la phase
  d'entraînement.

  Référence : https://scikit-learn.org/stable/modules/clustering.html


PRÉREQUIS
----------
  PHP :
    - PHP 7.4 ou supérieur
    - Extension PDO MySQL activée
    - Accès en lecture/écriture sur le répertoire temporaire système

  Python :
    - Python 3.x
    - Packages requis : joblib, pandas, numpy
      Installation : pip install joblib pandas numpy

  Base de données :
    - Tables utilisées : POINT_DE_CHARGE et STATION
    - Les stations sans coordonnées GPS sont automatiquement exclues.


