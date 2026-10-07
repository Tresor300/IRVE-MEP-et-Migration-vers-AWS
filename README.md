# IRVE — Migration et industrialisation vers AWS

Application web de suivi des infrastructures de recharge pour véhicules
électriques (tableau de bord INFRA-CHARGE) : cartographie des points de
charge, statistiques, et deux modules de prédiction s'appuyant sur des
modèles de machine learning.

Ce dépôt suit la migration de cette application, initialement déployée à
la main sur un serveur, vers une infrastructure conteneurisée puis AWS.

## Prérequis

- Docker
- Docker Compose

## Lancement

1. Copier le fichier d'exemple et renseigner les mots de passe :

```bash
cp .env.example .env
```

Renseigner `MARIADB_ROOT_PASSWORD` et `APP_DB_PASSWORD`. Pour générer une
valeur : `openssl rand -base64 12`

2. Construire et démarrer :

```bash
docker compose up -d --build
```

Au premier démarrage, la base est créée et le fichier
`bdd/tv_fowet_dump.sql` est importé automatiquement. Compter une trentaine
de secondes avant que l'application réponde.

3. Ouvrir l'application :

http://localhost:8080/fonctionnalite_1/accueil.php

## Arrêt

```bash
docker compose down
```

Les données sont conservées dans un volume et restent disponibles au
redémarrage. L'option `-v` supprimerait ce volume, donc la base.

## Architecture

| Service | Rôle |
|---|---|
| `web` | PHP 8.2 et Apache, construit depuis le `Dockerfile`. Contient également Python et les bibliothèques nécessaires aux modules de prédiction. |
| `bdd` | MariaDB 11.4, image officielle. Données conservées dans le volume `donnees_bdd`. |

L'application joint la base par le nom du service `bdd`, sur le réseau
interne de Docker. Tous les identifiants sont lus dans des variables
d'environnement, aucun n'est écrit dans le code.

## Déploiement sur Kubernetes (local)

Prérequis : Docker, kubectl, k3d.

1. Créer le cluster et y importer l'image :

```bash
k3d cluster create irve -p "8081:80@loadbalancer"
docker build -t irve-app .
k3d image import irve-app:latest -c irve
```

2. Créer le namespace et les deux objets qui ne viennent pas d'un fichier :

```bash
kubectl create namespace recette

kubectl create secret generic irve-db -n recette \
  --from-literal=MARIADB_ROOT_PASSWORD='...' \
  --from-literal=APP_DB_PASSWORD='...'

kubectl create configmap irve-dump -n recette \
  --from-file=init.sql=bdd/tv_fowet_dump.sql
```

3. Déployer :

```bash
kubectl apply -f k8s/ -n recette
kubectl get pods -n recette
```

4. Accéder à l'application :

```bash
kubectl port-forward -n recette service/irve-app 8083:80
```

Puis http://localhost:8083/fonctionnalite_1/accueil.php

Les fichiers de `k8s/` ne contiennent aucun namespace : la cible est choisie
à la commande avec `-n`. Les mêmes fichiers servent donc pour `recette` et
`production`.