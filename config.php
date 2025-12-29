<?php
// On définit des constantes
define('DB_HOST', 'u441bc.myd.infomaniak.com');
define('DB_NAME', 'u441bc_Pedagogie');
define('DB_USER', 'u441bc_yacouba');
define('DB_PASS', 'P@ss3r2026'); // Pense à changer ce mot de passe s'il est compromis !
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Erreur de connexion.");
}