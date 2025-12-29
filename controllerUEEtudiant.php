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
    $sql = "SELECT DISTINCT ue.code, ue.id, ue.nom as nomUE, maquette.nom as nomMaquette,
    COUNT(DISTINCT sipu.matricule) as nombreEtudiants
    FROM ue 
    JOIN maquette_ue ON ue.id = maquette_ue.id_ue
    JOIN maquette ON maquette_ue.id_maquette = maquette.id
    LEFT JOIN scolarite_inscription_pedagogique_ue sipu ON sipu.idUE = ue.id
    JOIN scolarite_inscription_pedagogique sip ON sip.id = sipu.idInscriptionPedagogique AND sip.statut = 1
    WHERE maquette.idEtat = 3 AND sip.idAnneeUniversitaire = (SELECT MAX(id) FROM scolarite_anneeuniversitaire)
    GROUP BY ue.code, ue.id, ue.nom, maquette.nom";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour récupérer les etudiants inscrits à une UE spécifique
function getEtudiantsByUE($pdo, $idUE) {
    $sql = "SELECT e.* FROM scolarite_etudiants e
    JOIN scolarite_inscription_pedagogique_ue sipu ON e.matricule = sipu.matricule
    WHERE sipu.idUE = :idUE";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':idUE', $idUE, PDO::PARAM_INT);
    $stmt->execute();
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
