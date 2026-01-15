<?php
require_once '../config.php';

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
    case 'listFilieres':
        $filieres = getFilere($pdo);
        header('Content-Type: application/json');
        echo json_encode($filieres);
        break;
    case 'listOptionsByFiliere':
        $idFiliere = $_GET['idFiliere'] ?? 0;
            $options = getOptionByFiliere($pdo, $idFiliere);
            header('Content-Type: application/json');
            echo json_encode($options);
        
        break;
    case 'getNiveauFormationByCycle':
        $idCycleFormation = $_GET['idCycleFormation'] ?? 0;
            $niveaux = getNiveauFormationByCycle($pdo, $idCycleFormation);
            header('Content-Type: application/json');
            echo json_encode($niveaux);
        
        break;
    case 'listMaquettesByOption':
        $idOption = $_GET['idOption'] ?? null;
        if ($idOption) {
            $maquettes = getMaquetteByOption($pdo, $idOption);
            header('Content-Type: application/json');
            echo json_encode($maquettes);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'idOption parameter is required']);
        }
        break;
    case 'getMaquetteUEs':
            $idcycle = $_GET['idcycle'] ?? null;
            $idNiveauFormation = $_GET['idNiveauFormation'] ?? null;
            $idOption = $_GET['idOption'] ?? null;
            $idSemestre = $_GET['idSemestre'] ?? null;
            $ues = getMaquetteUEs($pdo, $idcycle, $idNiveauFormation, $idOption, $idSemestre);
            header('Content-Type: application/json');
            echo json_encode($ues);
        
        break;
    case 'getEtudiantByUE':
        $idUE = $_GET['idUE'] ?? null;
        if ($idUE) {
            $ecs = getEtudiantByUE($pdo, $idUE);
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

function getEtudiantsByInscriptionPedagogique($pdo, $idInscriptionPedagogique) {
    $sql = "SELECT * FROM etudiant WHERE idInscriptionPedagogique = :idInscriptionPedagogique";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':idInscriptionPedagogique', $idInscriptionPedagogique, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getFilere($pdo) {
    $sql = "SELECT * FROM filieres";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getOptionByFiliere($pdo, $idFiliere) {
    if($idFiliere == 0){
        $sql = "SELECT * FROM options where code_option != 'TC' GROUP BY code_option";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $sql = "SELECT * FROM options WHERE idFilieres = :idFiliere AND code_option != 'TC' GROUP BY code_option";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':idFiliere', $idFiliere, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getMaquetteByOption($pdo, $idOption) {
    $sql = "SELECT * FROM maquette WHERE idOption = :idOption";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':idOption', $idOption, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getNiveauFormationByCycle($pdo, $idCycleFormation) {
    
    $sql = "SELECT DISTINCT niv.* FROM niveauformation niv
    JOIN options o ON niv.id = o.idNiveauFormation
    WHERE niv.idCycleFormation = :idCycleFormation";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':idCycleFormation', $idCycleFormation, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getMaquetteUEs($pdo,  $idcycle = null, $idNiveauFormation = null, $idOption = null, $idSemestre = null) {
    $sql = "SELECT 
    ue.id AS idUE,
    ue.code, 
    ue.nom AS nomUE, 
    cyc.cycle,
    m.nom AS nomMaquette,
    niv.niveau,
    m.idOption,
    m.id AS idMaquette,
    o.idNiveauFormation,
    sem.numInYear as numeroSemestre
FROM maquette_ue mue
JOIN ue ON mue.id_ue = ue.id
JOIN semestre sem ON ue.id_semestre = sem.id
JOIN maquette m ON mue.id_maquette = m.id
JOIN options o ON m.idOption = o.id
JOIN niveauformation niv on o.idNiveauFormation = niv.id
JOIN cycleformation cyc ON cyc.id = niv.idCycleFormation
WHERE m.idEtat = 3";

    if ($idcycle !== null) {
        $sql .= " AND cyc.id = :idcycle";
    }
    if ($idNiveauFormation !== null) {
        $sql .= " AND niv.id = :idNiveauFormation";
    }
    if ($idOption !== null) {
        $sql .= " AND m.idOption = :idOption";
    }
    if ($idSemestre !== null) {
        $sql .= " AND sem.numInYear = :idSemestre";
    }

    $sql .= " GROUP BY 
    ue.id,
    ue.code, 
    ue.nom, 
    m.nom,
    m.idOption,
    sem.numInYear,
    niv.niveau;";

    $stmt = $pdo->prepare($sql);
    
    if ($idcycle !== null) {
        $stmt->bindParam(':idcycle', $idcycle, PDO::PARAM_INT);
    }
    if ($idNiveauFormation !== null) {
        $stmt->bindParam(':idNiveauFormation', $idNiveauFormation, PDO::PARAM_INT);
    }
    if ($idOption !== null) {
        $stmt->bindParam(':idOption', $idOption, PDO::PARAM_INT);
    }
    if ($idSemestre !== null) {
        $stmt->bindParam(':idSemestre', $idSemestre, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getEtudiantByUE($pdo, $idUE) {
    $sql = "SELECT se.matricule, se.prenom, se.nom, pn.note, ec.nom FROM scolarite_inscription_pedagogique_ue sipu
    JOIN scolarite_inscription_pedagogique sip ON sipu.idInscriptionPedagogique = sip.id
    JOIN scolarite_etudiants se ON sipu.matricule = se.matricule
    JOIN scolarite_inscription si on sip.idInscription = si.id
    AND sip.statut = 1
    JOIN pedagogie_notes pn ON sip.id = pn.idInscription
    JOIN ec ON ec.id = pn.idEc
    WHERE pn.idUe = :idUE";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':idUE', $idUE, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
