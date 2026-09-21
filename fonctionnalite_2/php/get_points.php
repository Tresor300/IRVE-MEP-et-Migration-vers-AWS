<?php
// Fichier : php/get_points.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'db_connect.php';
    
    // NOUVELLE REQUÊTE : Adaptée exactement à tes colonnes phpMyAdmin
    $query = "
        SELECT 
            s.id_station, 
            s.nom_enseigne, 
            s.adresse_station, 
            s.latitude, 
            s.longitude,
            GROUP_CONCAT(p.puissance_nominale SEPARATOR ' / ') AS puissance,
            GROUP_CONCAT(
                CASE 
                    WHEN p.prise_type_2 = 1 THEN 'Type 2'
                    WHEN p.prise_type_combo_ccs = 1 THEN 'Combo CCS'
                    WHEN p.prise_type_chademo = 1 THEN 'CHAdeMO'
                    ELSE 'Standard'
                END
            SEPARATOR ' / ') AS prise,
            GROUP_CONCAT(p.condition_acces SEPARATOR ' / ') AS statut
        FROM STATION s
        LEFT JOIN POINT_DE_CHARGE p ON s.id_station = p.id_station
        WHERE s.latitude IS NOT NULL AND s.longitude IS NOT NULL
        GROUP BY s.id_station
        LIMIT 250
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);

} catch(Exception $e) {
    echo json_encode(['error' => "Erreur : " . $e->getMessage()]);
}
?>