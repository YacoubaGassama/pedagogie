<?php

// ─── Base principale (Yacouba) ────────────────────────────────────────────────
define('DB_HOST', 'u441bc.myd.infomaniak.com');
define('DB_NAME', 'u441bc_Pedagogie');
define('DB_USER', 'u441bc_yacouba');
define('DB_PASS', 'P@ss3r2026');

// ─── Base secondaire (Moustapha) ─────────────────────────────────────────────
define('DBM_HOST', 'o86fy.myd.infomaniak.com');
define('DBM_NAME', 'o86fy_Pedagogie');
define('DBM_USER', 'o86fy_paye');
define('DBM_PASS', 'Passercriat2022');

function createPDO(string $host, string $dbname, string $user, string $pass, string $label = 'DB'): PDO {
    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    } catch (PDOException $e) {
        error_log("[$label] Erreur de connexion : " . $e->getMessage());
        http_response_code(500);
        die(json_encode([
            'success' => false,
            'message' => "Erreur de connexion à la base $label."
        ]));
    }
}

// $pdo  = createPDO(DB_HOST,  DB_NAME,  DB_USER,  DB_PASS,  'Principale');
// $pdoM = $pdo;
$pdoM = createPDO(DBM_HOST, DBM_NAME, DBM_USER, DBM_PASS, 'Moustapha');
$pdo = $pdoM;