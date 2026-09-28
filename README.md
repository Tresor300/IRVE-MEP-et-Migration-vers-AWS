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