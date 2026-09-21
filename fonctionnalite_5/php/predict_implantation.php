<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

function repondre_erreur(string $message, int $code = 400): never {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function repondre_succes(array $data): never {
    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    repondre_erreur('Méthode invalide. Utilisez POST.', 405);
}

/* Accepte JSON OU formulaire classique */
$id_pdc = null;

if (isset($_POST['id_pdc'])) {
    $id_pdc = (int) $_POST['id_pdc'];
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['id_pdc'])) {
        $id_pdc = (int) $input['id_pdc'];
    }
}

if (!$id_pdc || $id_pdc <= 0) {
    repondre_erreur('id_pdc manquant ou invalide.');
}

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare("
        SELECT
            id_pdc,
            nbre_pdc,
            puissance_nominale,
            prise_type_2,
            prise_type_combo_ccs,
            prise_type_chademo,
            paiement_acte,
            condition_acces,
            reservation,
            accessibilite_pmr,
            restriction_gabarit,
            horaires
        FROM POINT_DE_CHARGE
        WHERE id_pdc = ?
        LIMIT 1
    ");

    $stmt->execute([$id_pdc]);
    $pdc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pdc) {
        repondre_erreur("Point de charge introuvable.", 404);
    }

} catch (PDOException $e) {
    repondre_erreur("Erreur BDD : " . $e->getMessage(), 500);
}

$project_dir = realpath(__DIR__ . '/..');
$script_path = $project_dir . '/python/predict_implantation.py';

if (!is_file($script_path)) {
    repondre_erreur("Script Python introuvable : " . $script_path, 500);
}

// Le script Python attend un objet JSON (et non des arguments positionnels),
// transmis par l'entrée standard : voir executer_python() dans config.php.
$payload = [
    "nbre_pdc"              => (float)$pdc["nbre_pdc"],
    "puissance_nominale"    => (float)$pdc["puissance_nominale"],
    "prise_type_2"          => (string)$pdc["prise_type_2"],
    "prise_type_combo_ccs"  => (string)$pdc["prise_type_combo_ccs"],
    "prise_type_chademo"    => (string)$pdc["prise_type_chademo"],
    "paiement_acte"         => (string)$pdc["paiement_acte"],
    "condition_acces"       => (string)$pdc["condition_acces"],
    "reservation"           => (string)$pdc["reservation"],
    "accessibilite_pmr"     => (string)$pdc["accessibilite_pmr"],
    "restriction_gabarit"   => (string)$pdc["restriction_gabarit"],
    "horaires"              => (string)$pdc["horaires"],
];

$execution = executer_python([$script_path], json_encode($payload, JSON_UNESCAPED_UNICODE), $project_dir);
$sortie    = $execution['sortie'];

if ($execution['timeout']) {
    repondre_erreur('Le script Python a dépassé le délai de ' . PYTHON_TIMEOUT . ' s.', 500);
}

if (empty($sortie)) {
    repondre_erreur("Aucune sortie Python.", 500);
}

$resultat = extraire_json_python($sortie);

if (!is_array($resultat)) {
    repondre_erreur("Réponse Python invalide : " . implode(' | ', array_slice($sortie, -3)), 500);
}

if (isset($resultat['error'])) {
    repondre_erreur("Erreur Python : " . $resultat['error'], 500);
}

$rf = $resultat['random_forest'] ?? null;
$svm = $resultat['svm'] ?? null;

if ($rf === null || $svm === null) {
    repondre_erreur("Résultat IA incomplet.", 500);
}

$accord = ($rf === $svm);

try {
    $insert = $pdo->prepare("
        INSERT INTO PREDICTION_IMPLANTATION
        (
            random_forest,
            prediction_rf,
            accuracy_rf,
            svm,
            prediction_svm,
            accuracy_svm,
            date_prediction
        )
        VALUES
        (?, ?, NULL, ?, ?, NULL, NOW())
    ");

    $insert->execute([$rf, $rf, $svm, $svm]);
    $id_prediction = (int)$pdo->lastInsertId();

} catch (PDOException $e) {
    $id_prediction = null;
}

repondre_succes([
    'id_pdc' => $id_pdc,
    'random_forest' => $rf,
    'svm' => $svm,
    'accord' => $accord,
    'id_prediction' => $id_prediction
]);