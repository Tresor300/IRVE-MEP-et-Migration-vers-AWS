<?php
/**
 * config.php (fonctionnalité 5)
 * ==============================
 * Toute la configuration (connexion MySQL, interpréteur Python, délais) est
 * désormais centralisée dans config.php à la RACINE du projet, partagé par
 * les 5 fonctionnalités. Ce fichier ne fait que le charger et définir les
 * quelques constantes propres à la fonctionnalité 5.
 *
 * Fournis par le config racine : DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS,
 * DB_CHARSET, PYTHON_BIN, PYTHON_TIMEOUT, get_db_connection(),
 * executer_python(), extraire_json_python().
 */

require_once __DIR__ . '/../../config.php';


/* =====================================================
   SCRIPTS IA DE LA FONCTIONNALITÉ 5
===================================================== */

/** Dossier contenant les scripts Python et les modèles entraînés (.pkl). */
define("PYTHON_DIR", PROJET_ROOT . "/fonctionnalite_5/python");

define("SCRIPT_IMPLANTATION", "predict_implantation.py");

define("SCRIPT_PUISSANCE", "predict_puissance.py");
