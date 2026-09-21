<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config.php";

function erreur($msg, $code = 500)
{
    http_response_code($code);

    echo json_encode([
        "success" => false,
        "error" => $msg
    ]);

    exit;
}

try {

    $pdo = get_db_connection();

}

catch(PDOException $e){

    erreur("Erreur connexion BDD : " . $e->getMessage());

}


try {

    $stmt = $pdo->query("

        SELECT

            p.id_pdc,
            p.nbre_pdc,
            p.puissance_nominale,
            p.prise_type_2,
            p.prise_type_combo_ccs,
            p.prise_type_chademo,
            p.paiement_acte,
            p.condition_acces,
            p.reservation,
            p.accessibilite_pmr,
            p.restriction_gabarit,
            p.horaires
        FROM POINT_DE_CHARGE p
        ORDER BY p.id_pdc ASC

    ");

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

}

catch(PDOException $e){

    erreur("Erreur SQL : " . $e->getMessage());

}

echo json_encode([

    "success" => true,

    "count" => count($data),

    "data" => $data

], JSON_UNESCAPED_UNICODE);