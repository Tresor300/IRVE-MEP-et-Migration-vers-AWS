<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

function erreur(string $message, int $code = 400): never {
    http_response_code($code);
    // JSON_INVALID_UTF8_SUBSTITUTE : un message contenant des octets non UTF-8
    // (sortie Python mal encodée) ne doit pas faire échouer json_encode.
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function succes(array $data): never {
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function charger_metriques(string $type): ?array {
    $base_dir = realpath(__DIR__ . '/..');

    $nom = $type === 'implantation'
        ? 'metrics_implantation.json'
        : 'metrics_puissance.json';

    $chemins = [
        $base_dir . '/python/' . $nom,
        $base_dir . '/' . $nom
    ];

    foreach ($chemins as $chemin) {
        if (is_file($chemin)) {
            $json = file_get_contents($chemin);
            $data = json_decode($json, true);
            return is_array($data) ? $data : null;
        }
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    erreur('Méthode invalide. Utilisez GET.', 405);
}

$id_pdc = isset($_GET['id_pdc']) ? (int) $_GET['id_pdc'] : 0;
$type = $_GET['type'] ?? '';

if ($id_pdc <= 0) {
    erreur('id_pdc manquant ou invalide.');
}

if (!in_array($type, ['implantation', 'puissance'], true)) {
    erreur("type invalide. Utilisez implantation ou puissance.");
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
        erreur('Point de charge introuvable.', 404);
    }

} catch (PDOException $e) {
    erreur('Erreur BDD : ' . $e->getMessage(), 500);
}

$project_dir = realpath(__DIR__ . '/..');

if ($type === 'implantation') {
    $script_path = $project_dir . '/python/predict_implantation.py';

    $payload = [
        "nbre_pdc" => (float)$pdc["nbre_pdc"],
        "puissance_nominale" => (float)$pdc["puissance_nominale"],
        "prise_type_2" => (string)$pdc["prise_type_2"],
        "prise_type_combo_ccs" => (string)$pdc["prise_type_combo_ccs"],
        "prise_type_chademo" => (string)$pdc["prise_type_chademo"],
        "paiement_acte" => (string)$pdc["paiement_acte"],
        "condition_acces" => (string)$pdc["condition_acces"],
        "reservation" => (string)$pdc["reservation"],
        "accessibilite_pmr" => (string)$pdc["accessibilite_pmr"],
        "restriction_gabarit" => (string)$pdc["restriction_gabarit"],
        "horaires" => (string)$pdc["horaires"]
    ];
} else {
    $script_path = $project_dir . '/python/predict_puissance.py';

    $payload = [
        "nbre_pdc" => (float)$pdc["nbre_pdc"],
        "prise_type_2" => (string)$pdc["prise_type_2"],
        "prise_type_combo_ccs" => (string)$pdc["prise_type_combo_ccs"],
        "prise_type_chademo" => (string)$pdc["prise_type_chademo"],
        "paiement_acte" => (string)$pdc["paiement_acte"],
        "condition_acces" => (string)$pdc["condition_acces"],
        "reservation" => (string)$pdc["reservation"],
        "accessibilite_pmr" => (string)$pdc["accessibilite_pmr"],
        "restriction_gabarit" => (string)$pdc["restriction_gabarit"],
        "horaires" => (string)$pdc["horaires"]
    ];
}

if (!is_file($script_path)) {
    erreur('Script Python introuvable : ' . $script_path, 500);
}

$json_payload = json_encode($payload, JSON_UNESCAPED_UNICODE);

// Le JSON est transmis au script Python par son entrée standard (stdin) et
// non en argument : sous Windows, escapeshellarg() remplacerait les guillemets
// par des espaces. executer_python() (config.php) gère aussi l'interpréteur,
// l'encodage UTF-8 et le délai maximal.
$execution = executer_python([$script_path], $json_payload, $project_dir);
$sortie    = $execution['sortie'];
$code_exit = $execution['code'];

if ($execution['timeout']) {
    erreur('Le script Python a dépassé le délai de ' . PYTHON_TIMEOUT . ' s.', 500);
}

if (empty($sortie)) {
    erreur('Aucune sortie Python. Code retour : ' . $code_exit, 500);
}

$resultat = extraire_json_python($sortie);

if (!is_array($resultat)) {
    erreur('Réponse Python invalide : ' . implode(' | ', array_slice($sortie, -3)), 500);
}

if (isset($resultat['error'])) {
    erreur('Erreur Python : ' . $resultat['error'], 500);
}

$metriques = charger_metriques($type);

if ($type === 'implantation') {
    $rf = $resultat['random_forest'] ?? null;
    $svm = $resultat['svm'] ?? null;
    $accord = isset($resultat['accord']) ? (bool)$resultat['accord'] : ($rf === $svm);

    if ($rf === null || $svm === null) {
        erreur('Résultat implantation incomplet.', 500);
    }

    $accuracy_rf = $metriques['rf_accuracy'] ?? null;
    $accuracy_svm = $metriques['svm_accuracy'] ?? null;

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
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $insert->execute([$rf, $rf, $accuracy_rf, $svm, $svm, $accuracy_svm]);
    $id_prediction = (int)$pdo->lastInsertId();

    succes([
        'type' => 'implantation',
        'id_pdc' => $id_pdc,
        'random_forest' => $rf,
        'svm' => $svm,
        'accord' => $accord,
        'id_prediction' => $id_prediction
    ]);
}

$rf = isset($resultat['random_forest']) ? (float)$resultat['random_forest'] : null;
$svm = isset($resultat['svm']) ? (float)$resultat['svm'] : null;

if ($rf === null || $svm === null) {
    erreur('Résultat puissance incomplet.', 500);
}

$moyenne = isset($resultat['moyenne'])
    ? (float)$resultat['moyenne']
    : round(($rf + $svm) / 2, 2);

$ecart = isset($resultat['ecart'])
    ? (float)$resultat['ecart']
    : round(abs($rf - $svm), 2);

$accuracy_rf = $metriques['rf_r2'] ?? null;
$accuracy_svm = $metriques['svm_r2'] ?? null;

$insert = $pdo->prepare("
    INSERT INTO PREDICTION_PUISSANCE
    (
        random_forest,
        accuracy_rf,
        prediction_rf,
        svm,
        accuracy_svm,
        date_prediction,
        erreur_modele
    )
    VALUES (?, ?, ?, ?, ?, NOW(), ?)
");

$insert->execute([$rf, $accuracy_rf, $rf, $svm, $accuracy_svm, $ecart]);
$id_prediction = (int)$pdo->lastInsertId();

succes([
    'type' => 'puissance',
    'id_pdc' => $id_pdc,
    'random_forest' => round($rf, 2),
    'svm' => round($svm, 2),
    'moyenne' => round($moyenne, 2),
    'ecart' => round($ecart, 2),
    'id_prediction' => $id_prediction
]);