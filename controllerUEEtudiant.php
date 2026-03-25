<?php
require_once 'config.php';
require_once 'Crypto.php';
$action = $_GET['action'] ?? 'listUEs';
switch ($action) {
    case 'listUEs':
        $ues = getUEsWithInscriptions($pdo);
        header('Content-Type: application/json');
        echo json_encode($ues);
        break;
    case 'listEtudiantsByUE':
        $idUE = $_GET['idUE'] ?? null;
        if ($idUE) {
            $etudiants = getEtudiantsByUE($pdo, $idUE);
            header('Content-Type: application/json');
            echo json_encode($etudiants);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'idUE parameter is required']);
        }
        break;
    case 'listInscriptions':
        $inscriptions = getScolariteInscriptionPedagogique($pdo);
        header('Content-Type: application/json');
        echo json_encode($inscriptions);
        break;
    case 'listECByUE':
        $idUE = $_GET['idUE'] ?? null;
        if ($idUE) {
            $ecs = getECByUE($pdo, $idUE);
            header('Content-Type: application/json');
            echo json_encode($ecs);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'idUE parameter is required']);
        }
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Action not found']);
        break;
}
// Fonction pour récupérer les UE et leurs inscriptions pédagogiques
function getUEsWithInscriptions($pdo) {
    $sql = "SELECT 
    ue.id AS idUE,
    ue.code, 
    ue.nom AS nomUE, 
    m.nom AS nomMaquette,
    m.idOption,
    m.id AS idMaquette,
    o.idNiveauFormation,
    COUNT(DISTINCT sipu.id) AS nombreEtudiantsTotal,
    -- Calcul des étudiants en rattrapage / niveau différent
    COUNT(DISTINCT CASE 
        WHEN si.idOption != m.idOption THEN sipu.matricule 
    END) AS etudiantsNiveauDifferent,
    s.numInYear AS numSemestre,
    s.id AS idSemestre
FROM ue
-- On part de l'UE et on joint les maquettes (une UE peut être dans plusieurs maquettes)
JOIN maquette_ue mue ON ue.id = mue.id_ue
JOIN maquette m ON mue.id_maquette = m.id
Join options o on m.idOption = o.id
-- LEFT JOIN pour ne pas perdre les UE sans inscriptions
LEFT JOIN scolarite_inscription_pedagogique_ue sipu ON ue.id = sipu.idUE
LEFT JOIN scolarite_inscription_pedagogique sip ON sipu.idInscriptionPedagogique = sip.id 
LEFT JOIN scolarite_inscription si on sip.idInscription = si.id
    AND sip.statut = 1
join semestre s on s.id = ue.id_semestre
WHERE m.idEtat = 3
GROUP BY 
    ue.id,
    ue.code, 
    ue.nom, 
    m.nom,
    m.idOption;";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getEtudiantsByUE($pdo, $idUE) {

    $sql = "SELECT 
                se.*,
                o.option,
                niv.niveau,
                sip.id      AS idInscriptionPedagogique,
                m.id        AS idMaquette
            FROM scolarite_inscription_pedagogique_ue sipu
            JOIN scolarite_etudiants se 
                ON sipu.matricule = se.matricule
            JOIN (
                SELECT *,
                       ROW_NUMBER() OVER (
                           PARTITION BY matricule 
                           ORDER BY dateEnregistrement DESC
                       ) AS rn
                FROM scolarite_inscription_pedagogique
            ) sip ON se.matricule = sip.matricule AND sip.rn = 1
            JOIN options o 
                ON sip.idOption = o.id
            JOIN niveauformation niv 
                ON o.idNiveauFormation = niv.id
            JOIN maquette m 
                ON o.id = m.idOption
            WHERE sipu.idUE = :idUE
              AND m.idEtat = 3";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':idUE', $idUE, PDO::PARAM_INT);
    $stmt->execute();
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $crypto = new Crypto();
    foreach ($etudiants as &$etudiant) {
        $etudiant['idInscriptionPedagogique'] = $crypto->encrypt(
            (string) $etudiant['idInscriptionPedagogique']
        );
    }
    unset($etudiant);

    return $etudiants;
}

function getScolariteInscriptionPedagogique($pdo) {
    $sql = "SELECT * FROM scolarite_inscription_pedagogique_ue sipu
    JOIN ue ON sipu.idUE = ue.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getECByUE($pdo, $idUE) {
    $sql = "SELECT * FROM ec WHERE idUE = :idUE";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':idUE', $idUE, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

