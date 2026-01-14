<?php
require_once 'config.php';

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
    case 'listEtudiantsByInscription':
        $idInscriptionPedagogique = $_GET['idInscriptionPedagogique'] ?? null;
        if ($idInscriptionPedagogique) {
            $etudiants = getEtudiantsByInscriptionPedagogique($pdo, $idInscriptionPedagogique);
            header('Content-Type: application/json');
            echo json_encode($etudiants);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'idInscriptionPedagogique parameter is required']);
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
    END) AS etudiantsNiveauDifferent
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
    $sql = "SELECT sipu.matricule from scolarite_inscription_pedagogique_ue sipu
WHERE sipu.idUE = :idUE";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':idUE', $idUE, PDO::PARAM_INT);
    $stmt->execute();
    $matricules = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $etudiants = [];
    foreach ($matricules as $matricule) {
        $etudiant = getEtudiant($pdo, $matricule);
        if ($etudiant) {
            $etudiants[] = $etudiant[0];
        }
    }
    return $etudiants;
}
// Fonction pour récupérer les informations d'un étudiant par son matricule
function getEtudiant($pdo, $matricule) {
    $sql = "SELECT scolarite_etudiants.*,options.option, niveau FROM scolarite_etudiants 
    join scolarite_inscription_pedagogique on scolarite_etudiants.matricule = scolarite_inscription_pedagogique.matricule
    JOIN options ON scolarite_inscription_pedagogique.idOption = options.id
    join niveauformation niv on options.idNiveauFormation = niv.id
    WHERE scolarite_etudiants.matricule = :matricule
    ORDER BY scolarite_inscription_pedagogique.dateEnregistrement LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['matricule' => $matricule]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

function getEtudiantsByInscriptionPedagogique($pdo, $idInscriptionPedagogique) {
    $sql = "SELECT * FROM etudiant WHERE idInscriptionPedagogique = :idInscriptionPedagogique";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':idInscriptionPedagogique', $idInscriptionPedagogique, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
