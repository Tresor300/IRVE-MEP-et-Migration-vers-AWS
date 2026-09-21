<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques – INFRA-CHARGE</title>
    <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<?php 
$page_active = 'statistiques';
require __DIR__ . '/../../fonctionnalite_1/includes/menu.php';
?>

<div class="main-content">

    <h2>Statistiques par département</h2>

    <div class="select-wrapper">
        <label for="select-dept">Choisir un département</label>
        <select id="select-dept">
            <option value="">-- Chargement... --</option>
        </select>
    </div>

    <p id="placeholder" class="placeholder-msg">Sélectionnez un département pour afficher les statistiques.</p>

    <div id="content" hidden>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Stations de recharge</h3>
                <p class="value" id="stat-stations">-</p>
            </div>
            <div class="stat-card accent">
                <h3>Points de charge</h3>
                <p class="value" id="stat-pdc">-</p>
            </div>
            <div class="stat-card tech">
                <h3>Puissance moyenne</h3>
                <p><span class="value" id="stat-power">-</span><span class="unit">kW</span></p>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-box">
                <h3>Répartition par puissance (kW)</h3>
                <div id="chart-power"></div>
            </div>
            <div class="chart-box">
                <h3>Types de prises</h3>
                <div id="chart-prises"></div>
            </div>
            <div class="chart-box">
                <h3>Top 5 opérateurs</h3>
                <div id="chart-operateurs"></div>
            </div>
        </div>

    </div>
</div>

<script src="../js/statistiques.js"></script>
</body>
</html>