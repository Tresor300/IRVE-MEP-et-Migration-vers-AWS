================================================================================
README - Fonctionnalité : Statistiques par département

================================================================================

DESCRIPTION
-----------
Cette fonctionnalité affiche des statistiques détaillées sur les infrastructures
de recharge électrique (IRVE) pour un département français sélectionné par
l'utilisateur. Elle repose sur deux API PHP qui interrogent la base de données
et retournent les données en JSON, affichées ensuite sous forme de cartes et
de graphiques interactifs.

CONTENU DU DOSSIER
------------------
  - statistiques.php       : Page principale (vue HTML + intégration PHP)
  - get_departements.php   : API – retourne la liste de tous les départements
  - stats.php              : API – retourne les statistiques d'un département
  - js/statistiques.js     : Logique front-end (appels fetch + rendu Plotly)
  - css/style.css          : Feuille de style partagée avec les autres fonctionnalités
  - README.txt             : Ce fichier

UTILISATION
-----------
L'utilisateur accède à la page statistiques.php, choisit un département dans
le menu déroulant, et les indicateurs ainsi que les graphiques s'affichent
automatiquement sans rechargement de page.

  Appel API département :
    GET get_departements.php
    → [{"code_dept":"29","nom_dept":"Finistère"}, ...]

  Appel API statistiques :
    GET stats.php?dept=CODE_DEPT   (ex : stats.php?dept=29)
    → {"stations":142, "pdc":310, "power":22.4, "pct_gratuit":38, ...}

DONNÉES RETOURNÉES PAR stats.php
---------------------------------
  - stations      : Nombre de stations de recharge dans le département
  - pdc           : Nombre total de points de charge
  - power         : Puissance nominale moyenne (kW)
  - pct_gratuit   : Pourcentage de points de charge à accès libre
  - power_dist    : Répartition par tranche de puissance (≤7 / 8–22 / 23–50 /
                    51–100 / >100 kW)
  - prise_dist    : Répartition par type de prise (Type 2, CCS Combo 2, CHAdeMO)
  - access_dist   : Top 5 des conditions d'accès
  - payment_dist  : Répartition gratuit / payant
  - pmr_dist      : Accessibilité PMR (top 4 valeurs)
  - operateurs    : Top 5 des opérateurs (nom_enseigne)

GRAPHIQUES AFFICHÉS
--------------------
  - Répartition par puissance (kW)  : graphique en barres
  - Types de prises                 : graphique en camembert
  - Top 5 opérateurs                : graphique en barres horizontales

  Les graphiques sont rendus via Plotly.js (chargé depuis CDN).
  Référence : https://plotly.com/javascript/

TABLES DE LA BASE DE DONNÉES UTILISÉES
----------------------------------------
  - DEPARTEMENT       : code_dept, nom_dept
  - STATION           : id_station, code_departement, nom_enseigne
  - POINT_DE_CHARGE   : id_station, nbre_pdc, puissance_nominale, paiement_acte,
                        condition_acces, accessibilite_pmr, prise_type_2,
                        prise_type_combo_ccs, prise_type_chademo

  Le filtrage par département repose sur : LEFT(code_departement, 2) = CODE_DEPT

SÉCURITÉ
---------
  - Toutes les requêtes SQL utilisent des requêtes préparées PDO (protection
    contre les injections SQL).
  - Le paramètre dept est vérifié avant toute exécution (HTTP 400 si absent).
  - Les erreurs de connexion BDD retournent un HTTP 500 sans exposer de détails
    sensibles côté client.



REMARQUES
----------
  - Le filtre LEFT(code_departement, 2) suppose que le code département est
    toujours en début de la valeur code_departement dans la table STATION.
  - Si aucune station n'existe pour un département, les compteurs retournent 0
    et les graphiques s'affichent vides.
  - Le fichier statistiques.js doit être adapté si de nouveaux indicateurs
    sont ajoutés à stats.php.

================================================================================