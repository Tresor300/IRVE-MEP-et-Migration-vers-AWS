<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function erreur(string $message, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function succes(array $data): never {
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    erreur('Méthode invalide. Utilisez GET.', 405);
}

$type = strtolower(trim($_GET['type'] ?? ''));

$fichiers = [
    'implantation' => 'metrics_implantation.json',
    'puissance' => 'metrics_puissance.json'
];

if (!isset($fichiers[$type])) {
    erreur("Type invalide. Utilisez implantation ou puissance.");
}

$base_dir = realpath(__DIR__ . '/..');

$chemins_possibles = [
    $base_dir . '/python/' . $fichiers[$type],
    $base_dir . '/' . $fichiers[$type]
];

$chemin = null;

foreach ($chemins_possibles as $c) {
    if (is_file($c)) {
        $chemin = $c;
        break;
    }
}

if ($chemin === null) {
    erreur("Fichier de métriques introuvable : " . $fichiers[$type], 404);
}

$contenu = file_get_contents($chemin);

if ($contenu === false || trim($contenu) === '') {
    erreur("Fichier de métriques vide ou illisible.", 500);
}

$data = json_decode($contenu, true);

if (!is_array($data)) {
    erreur("JSON métriques invalide.", 500);
}

$data['file_mtime'] = date('c', filemtime($chemin));

succes($data);