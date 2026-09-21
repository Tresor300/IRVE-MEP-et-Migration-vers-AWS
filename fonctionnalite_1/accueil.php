<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - Dashboard IRVE</title>
    
    <!-- Polices Google (Assistant & Open Sans) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Assistant:wght@400;600;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Lien vers notre fichier de style -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <!-- INJECTION DU MENU LATÉRAL -->
    <?php include('includes/menu.php'); ?>

    <!-- CONTENU DE DROITE -->
    <div class="main-wrapper">
        
        <!-- Haut de page (Hero) avec la grande image -->
        <section class="hero-section">
            <div class="hero-content">
                <h1>Bienvenue sur le Dashboard IRVE</h1>
                <p>L'outil d'analyse, de supervision et de prédiction pour le déploiement<br>des bornes de recharge en France.</p>
                <a href="../fonctionnalite_2/visualisation.php" class="btn-primary">Ouvrir la carte interactive</a>
            </div>
        </section>

        <!-- Bas de page avec les 3 cartes blanches -->
        <section class="cards-section">
            
            <div class="card">
                <div class="card-icon">
                    <!-- Icône SVG : Marqueur de Carte -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                </div>
                <h3>Visualisation interactive</h3>
                
                <p>Cartographie temps réel des bornes, disponibilité, types de prises.</p>
                <a href="../fonctionnalite_2/visualisation.php" class="btn-primary">Voir la visualisation</a>
            </div>

            <div class="card">
                <div class="card-icon">
                    <!-- Icône SVG : Graphique Statistiques -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" x2="18" y1="20" y2="10"/>
                        <line x1="12" x2="12" y1="20" y2="4"/>
                        <line x1="6" x2="6" y1="20" y2="14"/>
                    </svg>
                </div>
                <h3>Statistiques et Rapports</h3>
                <p>Analyse de l'utilisation, taux d'occupation, pannes.</p>
                <a href="../fonctionnalite_3/php/statistiques.php" class="btn-primary">Voir les statistiques</a>
            </div>

            <div class="card">
                <div class="card-icon">
                    <!-- Icône SVG : IA / Étincelles technologiques -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/>
                    </svg>
                </div>
                <h3>Prédictions IA</h3>
                <p>Anticipation des futurs clusters de recharge.</p>
                <a href="../fonctionnalite_5/html/prediction.php" class="btn-primary">Obtenir les prédictions</a>
                
            </div>

        </section>

        <!-- Footer -->
        <footer class="main-footer">
            <p>© 2026 INFRA-CHARGE</p>
        </footer>

    </div>

</body>
</html>