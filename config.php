<?php
/**
 * config.php — Configuration centrale du projet INFRA-CHARGE
 * ==========================================================
 *
 * Fichier UNIQUE à modifier pour changer d'environnement (serveur Linux
 * distant, XAMPP sous Windows, AWS...). Il fournit aux 5 fonctionnalités :
 *
 *   - les identifiants MySQL et la fonction get_db_connection()
 *   - l'interpréteur Python et la fonction executer_python()
 *   - BASE_URL, le préfixe URL du projet, utilisé par le menu latéral
 *
 * Plus aucun identifiant, chemin serveur ("/var/www/...") ni préfixe URL
 * ("/projet_web/...") n'est codé en dur ailleurs dans le projet.
 */


/* =====================================================
   1. CHEMINS DU PROJET
===================================================== */

/** Racine du projet sur le disque (le dossier qui contient ce fichier). */
define('PROJET_ROOT', __DIR__);

/** Menu latéral partagé par toutes les pages. */
define('MENU_PATH', PROJET_ROOT . '/fonctionnalite_1/includes/menu.php');

/** Vrai sous Windows (XAMPP) : l'appel de Python y diffère d'Unix. */
define('EST_WINDOWS', DIRECTORY_SEPARATOR === '\\');


/* =====================================================
   2. BASE DE DONNÉES
===================================================== */

/*
   XAMPP en local : utilisateur root sans mot de passe.

   On utilise 127.0.0.1 plutôt que "localhost" : sous Windows, "localhost"
   peut être résolu en IPv6 (::1) alors que MySQL n'écoute qu'en IPv4, ce qui
   provoque une longue attente suivie d'une erreur de connexion.

*/
define('DB_HOST',    '127.0.0.1');
define('DB_PORT',    3306);
define('DB_NAME',    'tv_fowet');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');


/* =====================================================
   3. PYTHON
===================================================== */

/*
   Interpréteur Python utilisé par les fonctionnalités 4 et 5.

   '' = détection automatique :
       - Linux : "python3"
       - Windows : le python.exe le plus récent trouvé dans les emplacements
         d'installation habituels (Apache n'hérite pas forcément du PATH de ta
         session Windows, donc un simple "python" échouerait silencieusement).

   Pour forcer un interpréteur précis, indiquer son chemin complet, ex. :
       'C:/Users/PC/AppData/Local/Programs/Python/Python313/python.exe'
*/
define('PYTHON_EXE', '');

/** Durée maximale tolérée pour un script Python (secondes). */
define('PYTHON_TIMEOUT', 60);


/* =====================================================
   4. URL
===================================================== */

/*
   Préfixe URL du projet, ex. "/projet_web" pour
   http://localhost/projet_web/fonctionnalite_1/accueil.php

   null = détection automatique à partir de l'URL de la page en cours.
   Mettre '' si un hôte virtuel pointe directement sur la racine du projet.
*/
define('BASE_URL_FORCEE', null);


/* =====================================================
   5. FONCTIONS UTILITAIRES
===================================================== */

/**
 * Détermine l'interpréteur Python à utiliser (voir PYTHON_EXE).
 */
function detecter_python(): string
{
    if (PYTHON_EXE !== '') {
        return PYTHON_EXE;
    }

    if (!EST_WINDOWS) {
        return 'python3';
    }

    $local_app_data = str_replace('\\', '/', (string) getenv('LOCALAPPDATA'));

    $motifs = [
        $local_app_data . '/Programs/Python/Python3*/python.exe',
        'C:/Users/*/AppData/Local/Programs/Python/Python3*/python.exe',
        'C:/Program Files/Python3*/python.exe',
        'C:/Python3*/python.exe',
    ];

    foreach ($motifs as $motif) {
        $trouves = glob($motif);
        if (!empty($trouves)) {
            natcasesort($trouves);            // Python39 < Python313
            return end($trouves);             // version la plus récente
        }
    }

    return 'py';                              // lanceur officiel Windows
}


/**
 * Préfixe URL du projet, déduit de l'URL de la page en cours.
 *
 * Toutes les pages du projet vivent dans un dossier "fonctionnalite_N" :
 * pour /projet_web/fonctionnalite_3/php/statistiques.php on renvoie
 * "/projet_web". Fonctionne quel que soit le nom du dossier dans htdocs,
 * avec un Alias Apache ou un hôte virtuel.
 */
function base_url(): string
{
    if (BASE_URL_FORCEE !== null) {
        return rtrim(BASE_URL_FORCEE, '/');
    }

    $script_url = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $pos = stripos($script_url, '/fonctionnalite_');

    return $pos === false ? '' : substr($script_url, 0, $pos);
}


/**
 * Ouvre une connexion PDO vers la base du projet.
 *
 * Laisse volontairement remonter la PDOException : les appelants la
 * rattrapent pour répondre en JSON. Un die() ici casserait le JSON attendu
 * par le JavaScript côté navigateur.
 *
 * @throws PDOException si la connexion échoue
 */
function get_db_connection(): PDO
{
    $dsn = 'mysql:host=' . DB_HOST
         . ';port=' . DB_PORT
         . ';dbname=' . DB_NAME
         . ';charset=' . DB_CHARSET;

    $pdo = new PDO($dsn, DB_USER, DB_PASS);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    return $pdo;
}


/**
 * Lance un script Python et récupère sa sortie (stdout + stderr).
 *
 * Portable Linux / Windows :
 *
 *  - Chaque argument passe par escapeshellarg(). Sous Windows cette fonction
 *    remplace les guillemets (ainsi que % et !) par des espaces : on ne peut
 *    donc PAS passer un JSON en argument. Les données structurées sont
 *    envoyées par l'entrée standard ($stdin), que rien n'altère.
 *
 *  - PYTHONUTF8=1 force Python à lire et écrire en UTF-8. Sans cela, sous
 *    Windows, il écrit en cp1252 et les accents ("Parking privé") arrivent
 *    illisibles côté PHP, ce qui fait échouer json_decode/json_encode.
 *
 * @param string[]    $arguments  Chemin du script, puis ses arguments.
 * @param string|null $stdin      Données à écrire sur l'entrée standard.
 * @param string|null $repertoire Répertoire de travail du script.
 * @return array{sortie: string[], code: int, timeout: bool}
 */
function executer_python(array $arguments, ?string $stdin = null, ?string $repertoire = null): array
{
    $commande = implode(' ', array_map('escapeshellarg', array_merge([PYTHON_BIN], $arguments)))
              . ' 2>&1';

    // Environnement complet du serveur (Windows exige SystemRoot) + réglages Python
    $env = getenv();
    $env['PYTHONUTF8']              = '1';
    $env['PYTHONIOENCODING']        = 'utf-8';
    $env['PYTHONDONTWRITEBYTECODE'] = '1';   // pas de __pycache__ dans le dossier web

    $descripteurs = [
        0 => ['pipe', 'r'],   // stdin
        1 => ['pipe', 'w'],   // stdout (+ stderr via 2>&1)
    ];

    $processus = proc_open($commande, $descripteurs, $pipes, $repertoire, $env);

    if (!is_resource($processus)) {
        return ['sortie' => ['Impossible de lancer : ' . $commande], 'code' => -1, 'timeout' => false];
    }

    if ($stdin !== null) {
        fwrite($pipes[0], $stdin);
    }
    fclose($pipes[0]);

    stream_set_blocking($pipes[1], false);

    $sortie  = '';
    $debut   = microtime(true);
    $timeout = false;
    $code    = -1;

    while (true) {
        $morceau = stream_get_contents($pipes[1]);
        if ($morceau !== false && $morceau !== '') {
            $sortie .= $morceau;
        }

        $statut = proc_get_status($processus);
        if (!$statut['running']) {
            $sortie .= (string) stream_get_contents($pipes[1]);
            $code = $statut['exitcode'];
            break;
        }

        if (microtime(true) - $debut > PYTHON_TIMEOUT) {
            $timeout = true;
            proc_terminate($processus);
            break;
        }

        usleep(50000);
    }

    fclose($pipes[1]);
    $code_close = proc_close($processus);
    if ($code === -1) {
        $code = $code_close;
    }

    $lignes = preg_split('/\r\n|\r|\n/', rtrim($sortie));

    return ['sortie' => $lignes === false ? [] : $lignes, 'code' => $code, 'timeout' => $timeout];
}


/**
 * Extrait l'objet JSON renvoyé par un script Python.
 *
 * Les scripts IA écrivent leur résultat JSON sur la dernière ligne, mais
 * scikit-learn peut afficher des avertissements après coup : on parcourt
 * donc les lignes en partant de la fin jusqu'à trouver un JSON valide.
 *
 * @param string[] $lignes Sortie de executer_python()
 */
function extraire_json_python(array $lignes): ?array
{
    for ($i = count($lignes) - 1; $i >= 0; $i--) {
        $ligne = trim($lignes[$i]);
        if ($ligne === '' || ($ligne[0] !== '{' && $ligne[0] !== '[')) {
            continue;
        }
        $decode = json_decode($ligne, true);
        if (is_array($decode)) {
            return $decode;
        }
    }
    return null;
}


/* =====================================================
   6. CONSTANTES DÉRIVÉES
===================================================== */

/** Interpréteur Python effectivement utilisé pour les appels IA. */
define('PYTHON_BIN', detecter_python());

/** Préfixe URL du projet, utilisé par le menu latéral. */
define('BASE_URL', base_url());
