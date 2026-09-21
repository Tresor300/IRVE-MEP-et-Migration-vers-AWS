📍 Fonctionnalité 2 : Visualisation Interactive (Projet IRVE)

📖 Description

Ce module constitue le cœur visuel du projet IRVE. Il permet de cartographier les infrastructures de recharge pour véhicules électriques en France via une interface moderne (Split-Screen & Glassmorphism). Il relie directement la base de données MySQL à une carte interactive et à un tableau de bord dynamique.

✨ Fonctionnalités Principales

🗺️ Carte géographique (Leaflet) : Placement dynamique de marqueurs selon les coordonnées GPS (Latitude/Longitude).

📊 Tableau de bord synchronisé : Affichage en temps réel des données brutes issues de la base de données.

💬 Pop-ups détaillés : Au clic sur un marqueur, affichage des caractéristiques clés de la borne : Enseigne, Adresse, Puissance (kW), Type de prise (Type 2, Combo CCS, etc.) et Statut.

⚡ Flux de données asynchrone : Utilisation de l'API fetch (JavaScript) pour interroger le serveur sans recharger la page.

📂 Arborescence du module

fonctionnalite_2/
├── visualisation.php       # Point d'entrée du module (Interface HTML/PHP)
│
├── css/
│   └── style.css           # Charte graphique spécifique à la carte et au panneau
│
├── includes/
│   └── menu.php            # Barre de navigation latérale (liens absolus)
│
├── js/
│   └── map.js              # Logique Front-End (Génération Leaflet et Tableau HTML)
│
├── php/
│   ├── db_connect.php      # Fichier de configuration PDO (Connexion MySQL)
│   └── get_points.php      # API Back-End (Requête SQL avec LEFT JOIN et agrégation)
│
└── README.md               # Documentation de la fonctionnalité




🚀 Installation & Configuration

Configuration de la Base de Données :
Les identifiants sont centralisés dans config.php à la RACINE du projet
(commun aux 5 fonctionnalités) ; php/db_connect.php ne fait que le charger :

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'tv_fowet');
define('DB_USER', 'root');
define('DB_PASS', '');

Voir INSTALLATION_XAMPP.md à la racine pour une installation locale.


Structure des Données :
Ce module nécessite la présence d'au moins deux tables dans la base de données :

STATION (id_station, nom_enseigne, adresse_station, latitude, longitude)

POINT_DE_CHARGE (id_pdc, id_station, puissance_nominale, prise_type_2, condition_acces, etc.)

Lancement :
Accédez simplement à la page visualisation.php depuis un serveur web (Apache/Nginx via XAMPP ou serveur distant). Le script JavaScript se chargera de récupérer et d'afficher les points automatiquement.

