<?php
// Indique au navigateur ou au client qui appelle l'API que le contenu renvoyé est au format JSON et encodé en UTF-8
header('Content-Type: application/json; charset=utf-8');

try {
    // Connexion à la base de données MySQL via l'extension PDO.
    // Les identifiants sont centralisés dans config.php à la racine du projet.
    require_once __DIR__ . '/../../config.php';
    $bdd = get_db_connection();
    
    // Exécution de la requête SQL pour récupérer le code et le nom de tous les départements, triés par code croissant
    $req = $bdd->query("SELECT code_dept, nom_dept FROM DEPARTEMENT ORDER BY code_dept ASC");
    
    // Récupération de toutes les lignes sous forme de tableau associatif (clés = noms des colonnes)
    // puis conversion de ce tableau en chaîne de caractères au format JSON pour l'afficher (echo)
    echo json_encode($req->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    // Si une erreur survient dans le bloc "try" (connexion échouée, mauvaise requête...), 
    // le script l'attrape ici et renvoie un objet JSON contenant le message d'erreur.
    echo json_encode(["error" => $e->getMessage()]);
}
?>