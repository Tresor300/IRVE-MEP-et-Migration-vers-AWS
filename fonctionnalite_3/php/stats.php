<?php
/**
 * stats.php
 * Retourne les statistiques d'un département en JSON
 * Appel : stats.php?dept=CODE_DEPT  (ex: stats.php?dept=29)
 */

// Définition des entêtes HTTP : le contenu est du JSON et l'API est accessible en cross-domain (CORS)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ── Connexion BDD ─────────────────────────────────────────────────────────
// Les identifiants sont centralisés dans config.php à la racine du projet.
require_once __DIR__ . '/../../config.php';

try {
    // Connexion PDO (UTF-8 + exceptions activées, voir get_db_connection)
    $pdo = get_db_connection();
} catch (PDOException $e) {
    // En cas d'échec de connexion : envoi d'un code HTTP 500 (Erreur serveur) et arrêt du script
    http_response_code(500);
    echo json_encode(['error' => 'Connexion BDD impossible : ' . $e->getMessage()]);
    exit;
}

// ── Validation du paramètre ───────────────────────────────────────────────
// Récupération sécurisée et nettoyage des espaces pour le paramètre 'dept'
$code_dept = trim($_GET['dept'] ?? '');
if ($code_dept === '') {
    // Si le paramètre est absent ou vide : envoi d'un code HTTP 400 (Requête incorrecte) et arrêt
    http_response_code(400);
    echo json_encode(['error' => 'Paramètre dept manquant']);
    exit;
}

// ── 1. Nombre de stations ─────────────────────────────────────────────────
// Compte le nombre total de stations de recharge situées dans le département ciblé
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM STATION WHERE LEFT(code_departement, 2) = ?
");
$stmt->execute([$code_dept]);
$nb_stations = (int) $stmt->fetchColumn(); // Transtypage en entier pour un JSON propre

// ── 2. Nombre total de points de charge ──────────────────────────────────
// Somme la colonne 'nbre_pdc' de tous les points de charge associés aux stations du département
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(pdc.nbre_pdc), 0)
    FROM POINT_DE_CHARGE pdc
    JOIN STATION s ON pdc.id_station = s.id_station
    WHERE LEFT(s.code_departement, 2) = ?
");
$stmt->execute([$code_dept]);
$nb_pdc = (int) $stmt->fetchColumn();

// ── 3. Puissance nominale moyenne ────────────────────────────────────────
// Calcule la moyenne de la puissance délivrée par les bornes du département (arrondie à 1 décimale)
$stmt = $pdo->prepare("
    SELECT ROUND(AVG(pdc.puissance_nominale), 1)
    FROM POINT_DE_CHARGE pdc
    JOIN STATION s ON pdc.id_station = s.id_station
    WHERE LEFT(s.code_departement, 2) = ?
      AND pdc.puissance_nominale IS NOT NULL
");
$stmt->execute([$code_dept]);
$avg_power = (float) ($stmt->fetchColumn() ?? 0); // Transtypage en flottant

// ── 4. % accès libre (paiement_acte = 0) ────────────────────────────────
// Récupère le volume total et agrège le nombre de points de charge gratuits (paiement_acte à 0)
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN pdc.paiement_acte = 0 THEN 1 ELSE 0 END) AS gratuit
    FROM POINT_DE_CHARGE pdc
    JOIN STATION s ON pdc.id_station = s.id_station
    WHERE LEFT(s.code_departement, 2) = ?
");
$stmt->execute([$code_dept]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
// Calcul dynamique du pourcentage arrondi pour éviter une division par zéro si le département n'a pas de bornes
$pct_gratuit = $row['total'] > 0
    ? round($row['gratuit'] / $row['total'] * 100)
    : 0;

// ── 5. Répartition par puissance nominale ────────────────────────────────
// Catégorise les points de charge par paliers de puissance (kW) via une agrégation conditionnelle (méthode Pivot)
$stmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN pdc.puissance_nominale <= 7   THEN 1 ELSE 0 END) AS lte7,
        SUM(CASE WHEN pdc.puissance_nominale > 7
                  AND pdc.puissance_nominale <= 22   THEN 1 ELSE 0 END) AS bt7_22,
        SUM(CASE WHEN pdc.puissance_nominale > 22
                  AND pdc.puissance_nominale <= 50   THEN 1 ELSE 0 END) AS bt22_50,
        SUM(CASE WHEN pdc.puissance_nominale > 50
                  AND pdc.puissance_nominale <= 100  THEN 1 ELSE 0 END) AS bt50_100,
        SUM(CASE WHEN pdc.puissance_nominale > 100  THEN 1 ELSE 0 END) AS gt100
    FROM POINT_DE_CHARGE pdc
    JOIN STATION s ON pdc.id_station = s.id_station
    WHERE LEFT(s.code_departement, 2) = ?
      AND pdc.puissance_nominale IS NOT NULL
");
$stmt->execute([$code_dept]);
$pw = $stmt->fetch(PDO::FETCH_ASSOC);

// Structuration des données au format "Labels/Data", idéal pour l'intégration de graphiques (ex: Chart.js)
$power_dist = [
    'labels' => ['≤7 kW', '8–22 kW', '23–50 kW', '51–100 kW', '>100 kW'],
    'data'   => [
        (int)($pw['lte7']     ?? 0),
        (int)($pw['bt7_22']   ?? 0),
        (int)($pw['bt22_50']  ?? 0),
        (int)($pw['bt50_100'] ?? 0),
        (int)($pw['gt100']    ?? 0),
    ]
];

// ── 6. Répartition par type de prise ─────────────────────────────────────
// Comptabilise la présence des trois grands types de connecteurs du marché (Champs booléens ou semi-booléens à 1)
$stmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN prise_type_2         = 1 THEN 1 ELSE 0 END) AS type2,
        SUM(CASE WHEN prise_type_combo_ccs = 1 THEN 1 ELSE 0 END) AS ccs,
        SUM(CASE WHEN prise_type_chademo   = 1 THEN 1 ELSE 0 END) AS chademo
    FROM POINT_DE_CHARGE pdc
    JOIN STATION s ON pdc.id_station = s.id_station
    WHERE LEFT(s.code_departement, 2) = ?
");
$stmt->execute([$code_dept]);
$pr = $stmt->fetch(PDO::FETCH_ASSOC);

$prise_dist = [
    'labels' => ['Type 2', 'CCS Combo 2', 'CHAdeMO'],
    'data'   => [
        (int)($pr['type2']   ?? 0),
        (int)($pr['ccs']     ?? 0),
        (int)($pr['chademo'] ?? 0),
    ]
];

// ── 7. Conditions d'accès ─────────────────────────────────────────────────
// Regroupe les points de charge selon leurs modalités d'accès et extrait le Top 5 des conditions récurrentes
$stmt = $pdo->prepare("
    SELECT condition_acces, COUNT(*) AS nb
    FROM POINT_DE_CHARGE pdc
    JOIN STATION s ON pdc.id_station = s.id_station
    WHERE LEFT(s.code_departement, 2) = ?
      AND pdc.condition_acces IS NOT NULL
    GROUP BY pdc.condition_acces
    ORDER BY nb DESC
    LIMIT 5
");
$stmt->execute([$code_dept]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Remplissage dynamique des listes à partir du jeu de résultats de la base
$access_dist = ['labels' => [], 'data' => []];
foreach ($rows as $r) {
    $access_dist['labels'][] = $r['condition_acces'];
    $access_dist['data'][]   = (int)$r['nb'];
}

// ── 8. Répartition accès payant / gratuit ────────────────────────────────
// Comptage brut des points de charge selon le mode de tarification à l'acte
$stmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN paiement_acte = 0 THEN 1 ELSE 0 END) AS gratuit,
        SUM(CASE WHEN paiement_acte = 1 THEN 1 ELSE 0 END) AS payant
    FROM POINT_DE_CHARGE pdc
    JOIN STATION s ON pdc.id_station = s.id_station
    WHERE LEFT(s.code_departement, 2) = ?
");
$stmt->execute([$code_dept]);
$ac = $stmt->fetch(PDO::FETCH_ASSOC);

$payment_dist = [
    'labels' => ['Gratuit', 'Payant'],
    'data'   => [(int)($ac['gratuit'] ?? 0), (int)($ac['payant'] ?? 0)]
];

// ── 9. Accessibilité PMR ─────────────────────────────────────────────────
// Extrait les valeurs du champ 'accessibilite_pmr' pour dégager les volumes d'aménagement du Top 4
$stmt = $pdo->prepare("
    SELECT accessibilite_pmr, COUNT(*) AS nb
    FROM POINT_DE_CHARGE pdc
    JOIN STATION s ON pdc.id_station = s.id_station
    WHERE LEFT(s.code_departement, 2) = ?
      AND pdc.accessibilite_pmr IS NOT NULL
    GROUP BY pdc.accessibilite_pmr
    ORDER BY nb DESC
    LIMIT 4
");
$stmt->execute([$code_dept]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pmr_dist = ['labels' => [], 'data' => []];
foreach ($rows as $r) {
    $pmr_dist['labels'][] = $r['accessibilite_pmr'];
    $pmr_dist['data'][]   = (int)$r['nb'];
}

// ── 10. Top 5 opérateurs (nom_enseigne) ──────────────────────────────────
// Identifie les 5 enseignes/réseaux possédant le plus grand nombre de stations physiques dans le département
$stmt = $pdo->prepare("
    SELECT nom_enseigne, COUNT(*) AS nb
    FROM STATION
    WHERE LEFT(code_departement, 2) = ?
      AND nom_enseigne IS NOT NULL
    GROUP BY nom_enseigne
    ORDER BY nb DESC
    LIMIT 5
");
$stmt->execute([$code_dept]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$operateurs = ['labels' => [], 'data' => []];
foreach ($rows as $r) {
    $operateurs['labels'][] = $r['nom_enseigne'];
    $operateurs['data'][]   = (int)$r['nb'];
}

// ── Réponse JSON ─────────────────────────────────────────────────────────
// Agrégation finale de toutes les métriques et encodage JSON. 
// JSON_UNESCAPED_UNICODE évite la conversion des caractères accentués français en entités Unicode type \u00e9.
echo json_encode([
    'stations'     => $nb_stations,
    'pdc'          => $nb_pdc,
    'power'        => $avg_power,
    'pct_gratuit'  => $pct_gratuit,
    'power_dist'   => $power_dist,
    'prise_dist'   => $prise_dist,
    'access_dist'  => $access_dist,
    'payment_dist' => $payment_dist,
    'pmr_dist'     => $pmr_dist,
    'operateurs'   => $operateurs,
], JSON_UNESCAPED_UNICODE);
?>