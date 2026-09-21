# INFRA-CHARGE — Lancer le projet en local avec XAMPP

Le projet tournait sur un serveur Linux distant (SSH). Il a été rendu **portable** :
toute la configuration (MySQL, Python, préfixe URL) est dans **`config.php` à la
racine**, et plus aucun chemin absolu (`/var/www/...`, `/projet_web/...`) n'est
codé en dur dans les fonctionnalités.

---

## 1. Prérequis

| Outil | Version testée | Remarque |
|---|---|---|
| XAMPP (Apache + MySQL/MariaDB + PHP) | PHP 8.2 | PHP ≥ 8.1 requis (`never`, `declare(strict_types)`) |
| Python 3 | 3.13 | installé pour l'utilisateur ou pour tous |
| Packages Python | — | `pip install pandas numpy scikit-learn joblib mysql-connector-python` |

> **Apache doit pouvoir lancer Python.** `config.php` détecte automatiquement le
> `python.exe` installé (`%LOCALAPPDATA%\Programs\Python\Python3xx\`, `C:\Program Files\Python3xx\`…).
> Si la détection échoue, renseigner le chemin complet dans `PYTHON_EXE`.

---

## 2. Mettre le projet dans `htdocs`

Deux possibilités, au choix :

**A. Jonction (recommandé en développement)** — le dossier reste sur le Bureau,
Apache le voit sous `htdocs\projet_web`, aucune recopie à faire après chaque modification :

```powershell
New-Item -ItemType Junction -Path "C:\xampp\htdocs\projet_web" -Target "C:\Users\PC\Desktop\IRVE MEP et Migration vers AWS"
```

**B. Copie classique** — copier le dossier du projet dans `C:\xampp\htdocs\projet_web`.

Le nom du dossier n'a pas d'importance : le préfixe URL (`BASE_URL`) est déduit
automatiquement de l'adresse de la page.

---

## 3. Créer la base de données

Démarrer **MySQL** dans le panneau XAMPP, puis **au choix** :

**A. Importer le dump (10 secondes)**

```powershell
C:\xampp\mysql\bin\mysql.exe -u root < "bdd\tv_fowet_dump.sql"
```
ou via phpMyAdmin (http://localhost/phpmyadmin) → *Importer* → `bdd/tv_fowet_dump.sql`.

**B. Regénérer depuis le CSV (quelques minutes)**

```powershell
cd partie_1
py import_fichier_csv.py
```
Le script crée la base `tv_fowet` si besoin, les tables `DEPARTEMENT`, `STATION`,
`POINT_DE_CHARGE`, la vue `vue_dataset_ia` et les tables `PREDICTION_*`.

Identifiants par défaut (XAMPP) : `root` sans mot de passe sur `127.0.0.1:3306`.
Pour d'autres identifiants, modifier `config.php` (section 2) et, pour l'import CSV,
définir `IRVE_DB_USER` / `IRVE_DB_PASS` en variables d'environnement.

---

## 4. Lancer

Démarrer **Apache** dans le panneau XAMPP, puis ouvrir :

**http://localhost/projet_web/fonctionnalite_1/accueil.php**

| Fonctionnalité | URL |
|---|---|
| 1 – Accueil | `/projet_web/fonctionnalite_1/accueil.php` |
| 2 – Carte interactive | `/projet_web/fonctionnalite_2/visualisation.php` |
| 3 – Statistiques | `/projet_web/fonctionnalite_3/php/statistiques.php` |
| 4 – Clusters K-Means | `/projet_web/fonctionnalite_4/php/clusters.php` |
| 5 – Classification RF / SVM | `/projet_web/fonctionnalite_5/html/prediction.php` |

---

## 5. Ce qui a changé par rapport à la version serveur

| Avant (serveur Linux) | Maintenant |
|---|---|
| Identifiants MySQL copiés dans 6 fichiers | **`config.php`** unique → `get_db_connection()` |
| `include('/var/www/tv_fowet/projet_web/.../menu.php')` | `require __DIR__ . '/../fonctionnalite_1/includes/menu.php'` |
| Liens et `fetch()` en `/projet_web/...` | liens relatifs ; menu préfixé par `BASE_URL` auto-détecté |
| `exec("python3 script.py '<JSON>'")` | `executer_python()` : interpréteur détecté, **JSON envoyé par stdin** |
| Sortie Python en cp1252 sous Windows | `PYTHONUTF8=1` + `sys.stdout.reconfigure("utf-8")` |
| `predict_implantation.php` / `predict_puissance.php` passaient 11 arguments positionnels à un script attendant un JSON | alignés sur `predire_caracteristique.php` |
| Bouton « Ouvrir la carte » de l'accueil pointait sur `visualisation.php` (404) | `../fonctionnalite_2/visualisation.php` |

### Pourquoi le JSON passe par stdin ?
Sous Windows, `escapeshellarg()` de PHP remplace les guillemets `"` (ainsi que `%` et `!`)
par des espaces : un JSON passé en argument de ligne de commande arrive illisible.
Les scripts `predict_*.py` acceptent donc le JSON **en argument** (usage manuel, Linux)
**ou sur l'entrée standard** (appel depuis PHP, portable).

---

## 6. Redéployer sur le serveur Linux

Remettre dans `config.php` les valeurs du serveur (commentées dans le fichier) :
`DB_HOST = 'localhost'`, `DB_USER = 'tv_fowet'`, `DB_PASS = '...'`. `PYTHON_EXE = ''`
donne `python3` sous Linux ; `BASE_URL` se calcule tout seul. Rien d'autre à toucher.

---

## 7. Dépannage

| Symptôme | Cause / solution |
|---|---|
| `Erreur de connexion BDD` / `SQLSTATE[HY000] [2002]` | MySQL n'est pas démarré dans XAMPP, ou le port 3306 est occupé par un autre MySQL (EasyPHP, WAMP, service Windows…). Vérifier avec `netstat -ano \| findstr :3306`. |
| `Unknown database 'tv_fowet'` | Étape 3 non faite (dump ou import CSV). |
| `Script Python introuvable` / `Aucune sortie Python` | Vérifier `PYTHON_EXE` dans `config.php` ; tester `py -c "import sklearn, pandas, joblib"`. |
| `Réponse Python invalide : ModuleNotFoundError` | `pip install pandas numpy scikit-learn joblib` **avec le même Python** que celui détecté (`PYTHON_BIN`). |
| Prédiction lente (≈ 6 s) | Normal : chargement de scikit-learn + des modèles `.pkl` à chaque appel. |
| Menu latéral avec liens cassés | `BASE_URL` : forcer `BASE_URL_FORCEE` dans `config.php` (ex. `'/projet_web'`). |
