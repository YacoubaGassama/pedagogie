<?php
require_once '../config.php';
$action = $_GET['action'];
switch ($action) {
    case 'searchEtudiant':
        $matricule = $_GET['matricule'] ?? '';
        $etudiant = searchEtudiant($pdo, $matricule);
        header('Content-Type: application/json');
        echo json_encode($etudiant);
        break;
    case 'getUEsByEtudiant':
        $matricule = $_GET['matricule'] ?? '';  
        $ues = getUEsByEtudiant($pdo, $matricule);
        header('Content-Type: application/json');
        echo json_encode($ues);;
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
// Fonction pour rechercher un étudiant par matricule
function searchEtudiant($pdo, $matricule) {
    $sql = "SELECT scolarite_etudiants.*,options.option, niveau FROM scolarite_etudiants 
    join scolarite_inscription_pedagogique on scolarite_etudiants.matricule = scolarite_inscription_pedagogique.matricule
    JOIN options ON scolarite_inscription_pedagogique.idOption = options.id
    join niveauformation niv on options.idNiveauFormation = niv.id
    WHERE scolarite_etudiants.matricule = :matricule";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['matricule' => $matricule]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour récupérer les UE auquellesl'étudiant est inscrit à partir de son matricule
function getUEsByEtudiant($pdo, $matricule) {
    $sql = "SELECT ue.code, ue.nom as nomUE, maquette.nom as nomMaquette, ue.id, ue.id_nature, ue.nombre_credit as credits
    FROM ue 
    JOIN maquette_ue ON ue.id = maquette_ue.id_ue
    JOIN maquette ON maquette_ue.id_maquette = maquette.id
    JOIN scolarite_inscription_pedagogique_ue sipu ON sipu.idUE = ue.id
    JOIN scolarite_inscription_pedagogique sip ON sip.id = sipu.idInscriptionPedagogique
    WHERE sip.matricule = :matricule AND sip.statut = 1
    AND maquette.idEtat = 3 AND sip.idAnneeUniversitaire = (SELECT MAX(id) FROM scolarite_anneeuniversitaire)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['matricule' => $matricule]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour afficher les EC d'une UE
function getECByUE($pdo, $idUE) {
    $sql = "SELECT ec.code, ec.nom as nomEC, ec.nombre_credit as credits
    FROM ec 
    JOIN ue ON ue.id = ec.id_ue
    WHERE ec.id_ue = :idUE";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['idUE' => $idUE]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}