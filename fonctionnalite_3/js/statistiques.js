// Attend que tout le DOM (HTML) soit complètement chargé avant d'exécuter le script
document.addEventListener("DOMContentLoaded", function () {

    // ── Éléments du DOM ───────────────────────────────────────────────────
    // Récupération des éléments HTML clés de l'interface
    const selectDept = document.getElementById('select-dept'); // Le menu déroulant des départements
    const placeholder = document.getElementById('placeholder'); // Message/visuel d'attente ("Veuillez choisir...")
    const content     = document.getElementById('content');     // Le conteneur principal des statistiques et graphiques

    // ── Configurations Globales Plotly ────────────────────────────────────
    // Mise en page par défaut des graphiques (marges, hauteur, arrière-plan transparent)
    const CHART_LAYOUT = { 
        margin: { t: 10, b: 40, l: 40, r: 10 }, 
        height: 300, 
        autosize: true, 
        paper_bgcolor: 'rgba(0,0,0,0)', // Fond du composant transparent
        plot_bgcolor: 'rgba(0,0,0,0)'   // Fond de la zone de dessin transparent
    };
    
    // Configuration comportementale (graphiques réactifs, barre d'outils cachée, non interactifs au clic/glissé)
    const CHART_CONFIG = { responsive: true, displayModeBar: false, staticPlot: true, dragmode: false };

    // ── Chargement de la liste des départements ───────────────────────────
    // Appel de votre premier script PHP pour peupler le menu déroulant <select>
    // (chemins relatifs à statistiques.php, situé dans le même dossier php/ :
    //  le projet fonctionne quel que soit le dossier où il est installé)
    fetch('get_departements.php')
        .then(r => r.json()) // Conversion de la réponse brute en objet/tableau JavaScript
        .then(data => {
            // Option par défaut invitant l'utilisateur à faire un choix
            selectDept.innerHTML = '<option value="">-- Sélectionnez un département --</option>';
            
            // Boucle sur chaque département retourné par la base de données
            data.forEach(dept => {
                const opt = document.createElement('option');
                opt.value       = dept.code_dept; // Le code (ex: "29") servira de valeur technique
                opt.textContent = `${dept.code_dept} – ${dept.nom_dept}`; // Texte visible (ex: "29 – Finistère")
                selectDept.appendChild(opt); // Ajout de l'option dans le select
            });
        })
        .catch(() => {
            // En cas de panne réseau ou erreur PHP, affichage d'un message d'erreur dans le select
            selectDept.innerHTML = '<option value="">Erreur de chargement</option>';
        });

    // ── Événement de changement de département ────────────────────────────
    // Déclenché à chaque fois que l'utilisateur sélectionne un département différent
    selectDept.addEventListener('change', function () {
        const code = this.value; // Récupère le code du département sélectionné

        // Si l'utilisateur clique sur l'option vide (option par défaut)
        if (!code) {
            content.hidden     = true;  // Masque les statistiques et graphiques
            placeholder.hidden = false; // Réaffiche l'écran d'attente
            return; // Arrête l'exécution ici
        }

        // Appel de votre second script PHP (stats.php) en lui passant le code en paramètre GET
        fetch(`stats.php?dept=${encodeURIComponent(code)}`)
            .then(r => r.json())
            .then(s => {
                // Cache l'écran d'attente et affiche les conteneurs de données
                placeholder.hidden = true;
                content.hidden     = false;

                // ── Mise à jour des compteurs textuels ────────────────────────
                // Injection des valeurs numériques brutes formatées selon les normes françaises (ex: 1 250 au lieu de 1250)
                document.getElementById('stat-stations').textContent = s.stations.toLocaleString('fr-FR');
                document.getElementById('stat-pdc').textContent      = s.pdc.toLocaleString('fr-FR');
                document.getElementById('stat-power').textContent    = s.power;
                

                // ── Graphique 1 : Répartition par Puissance (Histogramme vertical) ──
                Plotly.newPlot('chart-power', [{
                    type: 'bar',
                    x: s.power_dist.labels, // Les tranches de puissance ("≤7 kW", etc.)
                    y: s.power_dist.data,   // Le nombre de bornes associées
                    marker: { color: '#3b82f6' } // Couleur bleue
                }], { 
                    ...CHART_LAYOUT, // Importation de la configuration globale de mise en page
                    height: 350,
                    margin: { t: 10, b: 80, l: 40, r: 10 }, // Marge basse augmentée pour ne pas couper les labels
                    xaxis: { title: 'Puissance', tickangle: -30 }, // Légère rotation des textes de l'axe X pour lisibilité
                    yaxis: { title: 'Nb bornes' }
                }, CHART_CONFIG);
                
                // ── Graphique 2 : Répartition par Type de Prise (Donut / Camembert) ──
                Plotly.newPlot('chart-prises', [{
                    type: 'pie',
                    labels: s.prise_dist.labels, // ["Type 2", "CCS Combo 2", ...]
                    values: s.prise_dist.data,   // Volumes numériques
                    hole: 0.35,                  // Crée l'effet "Donut" en évidant le centre à 35%
                    marker: { colors: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6'] } // Palette de couleurs personnalisée
                }], { ...CHART_LAYOUT }, CHART_CONFIG);

                // ── Graphique 3 : Top 5 des Opérateurs (Barres horizontales) ──
                Plotly.newPlot('chart-operateurs', [{
                    type: 'bar',
                    orientation: 'h',         // Force l'orientation horizontale des barres
                    x: s.operateurs.data,     // Les valeurs numériques passent sur l'axe X
                    y: s.operateurs.labels,   // Les noms des enseignes passent sur l'axe Y
                    marker: { color: '#e67e22' } // Couleur orange
                }], { 
                    ...CHART_LAYOUT, 
                    margin: { t: 10, b: 40, l: 120, r: 10 } // Grande marge à gauche pour éviter que le nom des opérateurs soit rogné
                }, CHART_CONFIG);
            })
            .catch(err => {
                // Gestion des erreurs en cas d'échec de la requête vers stats.php
                console.error("Erreur stats:", err);
                alert("Impossible de récupérer les statistiques.");
            });
    });

});