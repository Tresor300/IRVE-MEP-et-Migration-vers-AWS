<?php
// Le menu est inclus par des pages situées à des profondeurs différentes
// (fonctionnalite_1/, fonctionnalite_3/php/, fonctionnalite_5/html/...) :
// les liens sont donc préfixés par BASE_URL (ex. "/projet_web"), calculé
// automatiquement dans config.php à la racine du projet.
require_once __DIR__ . '/../../config.php';
?>
<aside class="sidebar">
    <h2>INFRA-CHARGE</h2>
    <nav>
        <ul>
            <!-- ACCUEIL : F1 -->
            <li class="<?php echo (isset($page_active) && $page_active == 'accueil') ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>/fonctionnalite_1/accueil.php">
                    <span class="icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    </span> 
                    Accueil
                </a>
            </li>
            
            <!-- VISUALISATION : F2 -->
            <li class="<?php echo (isset($page_active) && $page_active == 'visualisation') ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>/fonctionnalite_2/visualisation.php">
                    <span class="icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                    </span> 
                    Visualisation
                </a>
            </li>
            
            <!-- STATISTIQUES : F3 -->
            <li class="<?php echo (isset($page_active) && $page_active == 'statistiques') ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>/fonctionnalite_3/php/statistiques.php">
                    <span class="icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="M18 17V9"></path><path d="M13 17V5"></path><path d="M8 17v-3"></path></svg>
                    </span> 
                    Statistiques
                </a>
            </li>

            <!-- IA Clusters : F4 -->
            <li class="<?php echo (isset($page_active) && $page_active == 'ia_clusters') ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>/fonctionnalite_4/php/clusters.php">
                    <span class="icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                    </span> 
                    IA Clusters
                </a>
            </li>

            <!-- IA Classification : F5 -->
            <li class="<?php echo (isset($page_active) && $page_active == 'ia_classification') ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>/fonctionnalite_5/html/prediction.php">
                    <span class="icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="14" x2="23" y2="14"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="14" x2="4" y2="14"></line></svg>
                    </span> 
                    IA Classification
                </a>
            </li>
            
        </ul>
    </nav>
</aside>
