<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config.php";

function erreur(string $msg, int $code = 400): never
{
    http_response_code($code);

    echo json_encode([
        "success" => false,
        "error" => $msg
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    erreur("Méthode GET obligatoire.",405);
}

$type = $_GET["type"] ?? "";

if($type!="implantation" && $type!="puissance"){
    erreur("Type invalide.");
}

try{

    $pdo=get_db_connection();

}catch(PDOException $e){

    erreur($e->getMessage(),500);

}

if($type=="implantation"){

    $sql="SELECT *
          FROM PREDICTION_IMPLANTATION
          ORDER BY id_prediction DESC
          LIMIT 1";

}else{

    $sql="SELECT *
          FROM PREDICTION_PUISSANCE
          ORDER BY id_prediction DESC
          LIMIT 1";

}

try{

    $stmt=$pdo->query($sql);

    $prediction=$stmt->fetch(PDO::FETCH_ASSOC);

    if(!$prediction){
        erreur("Aucune prédiction enregistrée.",404);
    }

}catch(PDOException $e){

    erreur($e->getMessage(),500);

}

echo json_encode([
    "success"=>true,
    "data"=>$prediction
],JSON_UNESCAPED_UNICODE);