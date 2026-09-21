# Fonctionnalité 5 — Prédiction IA

## Objectif
Cette fonctionnalité permet de sélectionner un point de charge puis de prédire son type d’implantation et sa puissance nominale.

Les résultats de deux modèles sont affichés et comparés : Random Forest et SVM.

## Prérequis
Pour utiliser cette fonctionnalité, il faut :
* un serveur web avec PHP ;
* une base de données MySQL ou MariaDB ;
* Python 3 ;
* les bibliothèques Python suivantes :
pip install pandas numpy scikit-learn joblib
Le serveur PHP doit aussi pouvoir lancer Python (`python3` sous Linux ; sous
Windows/XAMPP le `python.exe` est détecté automatiquement par `config.php`).

## Installation

1. Copier le dossier `fonctionnalite_5` dans le projet web.
2. Importer la base de données et vérifier la présence des tables :
POINT_DE_CHARGE
PREDICTION_IMPLANTATION
PREDICTION_PUISSANCE

3. Vérifier que les scripts Python et les modèles entraînés sont présents dans le dossier :
fonctionnalite_5/python/
Les fichiers importants sont :
predict_implantation.py
predict_puissance.py
modele_random_forest.pkl
modele_svm.pkl
modele_random_forest_puissance.pkl
modele_svm_puissance.pkl
metrics_implantation.json
metrics_puissance.json

4. Configurer la connexion à la base de données et l'interpréteur Python dans
le fichier **config.php à la racine du projet** (commun aux 5 fonctionnalités) :
DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, PYTHON_EXE
(`fonctionnalite_5/php/config.php` ne fait que charger ce fichier.)

Voir INSTALLATION_XAMPP.md à la racine pour une installation locale sous Windows.
## Lancement
La page principale est accessible ici :
/projet_web/fonctionnalite_5/html/prediction.php

Exemple :
http://localhost/projet_web/fonctionnalite_5/html/prediction.php

## Utilisation

1. Rechercher ou sélectionner un point de charge dans le tableau.
2. Cliquer sur :
Prédire l’implantation
ou :
Prédire la puissance nominale

3. La page envoie les données du point de charge au serveur.
4. PHP appelle le script Python correspondant.
5. Le résultat est enregistré dans MySQL et affiché sur la page.

Le tableau contient une recherche et une pagination de 10 lignes afin de faciliter la sélection des points de charge.

## Fichiers principaux

| Fichier                           | Rôle                                                 |
| --------------------------------- | ---------------------------------------------------- |
| `html/prediction.html`            | Page affichée à l’utilisateur                        |
| `css/style.css`                   | Mise en forme de la page                             |
| `js/prediction.js`                | Recherche, pagination, appels vers PHP et graphiques |
| `php/get_pdc.php`                 | Récupère les points de charge                        |
| `php/predire_caracteristique.php` | Lance la prédiction choisie                          |
| `php/get_prediction.php`          | Récupère la dernière prédiction enregistrée          |
| `php/get_metrics.php`             | Récupère les métriques des modèles                   |
| `python/predict_implantation.py`  | Prédiction du type d’implantation                    |
| `python/predict_puissance.py`     | Prédiction de la puissance                           |

## Vérification rapide
Pour vérifier que la récupération des points de charge fonctionne :
GET php/get_pdc.php
Pour tester une prédiction directement :

GET php/predire_caracteristique.php?id_pdc=1&type=implantation
ou :
GET php/predire_caracteristique.php?id_pdc=1&type=puissance