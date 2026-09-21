<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualisation - Dashboard IRVE</title>
    
    <!-- Intégration de la bibliothèque Leaflet (CSS) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- Polices Google (Assistant & Open Sans) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Assistant:wght@400;600;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Lien direct vers le CSS (puisque le fichier est à la racine) -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <!-- On définit quelle page est active et on inclut le menu directement -->
    <?php 
        $page_active = 'visualisation';
        require __DIR__ . '/../fonctionnalite_1/includes/menu.php';
    ?>

    <!-- CONTENU DE DROITE -->
    <div class="main-wrapper" style="position: relative; display: flex; flex-direction: column; height: 100vh;">
        
        <!-- Le conteneur pour la carte Leaflet -->
        <div id="map"></div>

        <!-- Le Panneau Coulissant avec le Tableau (à droite) -->
        <div class="side-panel">
            <div style="display: flex; align-items: center; justify-content: space-between; padding-right: 16px;">
    <h2>Points de charge (Tableau)</h2>
   
</div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Station / Adresse</th>
                            <th>Enseigne</th>
                            <th>Puissance</th>
                            <th>Prise</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody id="points-table-body">
                        <tr>
                            <td colspan="6" style="text-align: center;">Chargement des données...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
                <div style="padding: 16px; text-align: center;">
        <a href="../fonctionnalite_4/php/clusters.php" class="btn-clusters">
            Prédire les clusters
        </a>
    </div>
        </div>
        

    </div>

    <!-- Intégration de la bibliothèque Leaflet (JavaScript) -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Appel de TON fichier map.js (C'est ÇA qui manquait !) -->
    <script src="js/map.js?v=3"></script>
</body>
</html>