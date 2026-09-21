<?php
// Fichier : php/db_connect.php
// Les identifiants ne sont plus codés ici : ils viennent du config.php à la
// racine du projet, partagé par les 5 fonctionnalités.
require_once __DIR__ . '/../../config.php';

// Une éventuelle PDOException remonte jusqu'à get_points.php, qui la traduit
// en JSON d'erreur pour que map.js puisse l'afficher dans le tableau.
$pdo = get_db_connection();
