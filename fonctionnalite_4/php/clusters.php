<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INFRA-CHARGE — Clusters IA</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <link rel="stylesheet" href="../css/style.css" />
</head>
<body>
    <?php
$page_active = 'ia_clusters';
require __DIR__ . '/../../fonctionnalite_1/includes/menu.php';
?>

    <div id="loading-overlay">
        <div class="spinner"></div>
        <p>Calcul des clusters en cours…</p>
        
    </div>

    <div id="error-overlay" hidden>
        <div class="error-icon">⚠️</div>
        <p id="error-text">Une erreur est survenue.</p>
        <a href="../../fonctionnalite_2/visualisation.php">← Retour à la visualisation</a>
    </div>

    <div class="main-wrapper">
        <div class="page-header">
            <h1>Prédiction des clusters géographiques</h1>
            <p>Clustering K-Means </p>
        </div>

        <div class="stats-bar" id="stats-bar">
            <div class="stat"><span class="stat-value" id="stat-total">—</span><span class="stat-label">Points de charge</span></div>
            <div class="stat"><span class="stat-value" id="stat-clusters">—</span><span class="stat-label">Clusters</span></div>
            <div class="stat"><span class="stat-value" id="stat-stations">—</span><span class="stat-label">Stations</span></div>
        </div>

        <div class="content-area">
            <div id="map"></div>
            <aside class="legend-panel">
                <h3>Clusters</h3>
                <div id="legend-list"></div>
            </aside>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../js/clusters.js"></script>
</body>
</html>