<?php
require_once '../config.php';
$input = json_decode(file_get_contents('php://input'), true);
$getAction = $_GET['action'] ?? null;
$postAction = $input['action'] ?? null;

// Priorité : GET d'abord, puis POST
$action = $getAction ?? $postAction;

if (!$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Action parameter is required']);
    exit;
}
if ($postAction) {
    switch ($action) {
    case 'appliquerRepechage':
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['idUE']) || !isset($data['simulations']) || !isset($data['intervalle'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Données invalides']);
            break;
        }

        $idUE = $data['idUE'];
        $simulations = $data['simulations'];
        $intervalle = $data['intervalle'];
        $barre = floatval($intervalle['min']);
        $campagne = date('Y') . '-S' . date('n') <= 6 ? '1' : '2'; // S1 ou S2 selon la date
        $idUser = $_SESSION['idUser'] ?? 0; // À adapter selon votre système d'authentification
        $idSem = $data['idSemestre'] ?? null; // Récupérer depuis les filtres

        try {
            $pdo->beginTransaction();

            // 1. Enregistrer le repêchage dans la table repêchage
            $sqlRep = "INSERT INTO `repechage`(`idUe`, `idSem`, `barre`, `campagne`, `idUser`, `dateCreation`) 
                   VALUES (:idUe, :idSem, :barre, :campagne, :idUser, NOW())";

            $stmtRep = $pdo->prepare($sqlRep);
            $stmtRep->execute([
                ':idUe' => $idUE,
                ':idSem' => $idSem,
                ':barre' => $barre,
                ':campagne' => $campagne,
                ':idUser' => $idUser
            ]);

            $idRepechage = $pdo->lastInsertId();

            // 2. Mettre à jour les notes dans pedagogie_notes
            $notesModifiees = 0;

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Repêchage appliqué avec succès. $notesModifiees note(s) modifiée(s) pour " . count($simulations) . " étudiant(s).",
                'idRepechage' => $idRepechage
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Action not found']);
        break;
}
}
elseif($getAction){
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
        $session_id = $_GET['session_id'] ?? 1;
        if ($idUE) {
            $ecs = getEtudiantByUE($pdo, $idUE, $session_id);
            header('Content-Type: application/json');
            echo json_encode($ecs);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'idUE parameter is required']);
        }
        break;
    case 'getStatUE':
        $ueId = $_GET['ueId'] ?? null;
        if ($ueId) {
            $stats = getStatUE($pdo, $ueId);
            header('Content-Type: application/json');
            echo json_encode($stats);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'ueId parameter is required']);
        }
        break;
    // Modifier le case 'simulerRepechage' dans deliberationUeController.php

    case 'simulerRepechage':
        // Récupérer les données POST si elles existent
        $input = json_decode(file_get_contents('php://input'), true);

        if ($input) {
            // Utiliser les données POST
            $idUE = $input['idUE'] ?? null;
            $minMoy = floatval($input['minMoyenne'] ?? 8.0);
            $strategy = $input['strategy'] ?? 'neutral';
            $lockGE10 = (isset($input['lock_ge10']) && $input['lock_ge10'] === 'true');
            $displayStep = floatval($input['rounding_step'] ?? 0.01);
            $etudiantsEligibles = $input['etudiantsEligibles'] ?? null;
        } else {
            // Fallback sur les paramètres GET (pour compatibilité)
            $idUE = $_GET['idUE'] ?? null;
            $minMoy = floatval($_GET['minMoyenne'] ?? 8.0);
            $strategy = $_GET['strategy'] ?? 'neutral';
            $lockGE10 = (isset($_GET['lock_ge10']) && $_GET['lock_ge10'] === 'true');
            $displayStep = floatval($_GET['rounding_step'] ?? 0.01);
            $etudiantsEligibles = null;
        }

        if ($idUE) {
            if ($etudiantsEligibles) {
                // Utiliser les étudiants envoyés depuis le front-end
                $data = appliquerRepechageSurEtudiants($etudiantsEligibles, $minMoy, $strategy, $lockGE10, $displayStep);
            } else {
                // Récupérer tous les étudiants de l'UE et filtrer
                $data = appliquerRepechageUE($pdo, $idUE, $minMoy, $strategy, $lockGE10, $displayStep);
            }
            header('Content-Type: application/json');
            echo json_encode(["success" => true, "simulations" => $data]);
        } else {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "ID UE manquant"]);
        }
        break;
    // Ajoutez ce case dans le switch de deliberationUeController.php


    // Ajouter dans le switch de deliberationUeController.php

    case 'verifierRepêchage':
        $idUE = $_GET['idUE'] ?? null;
        if ($idUE) {
            $sql = "SELECT * FROM repechage 
                WHERE idUe = :idUe 
                ORDER BY dateCreation DESC 
                LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':idUe', $idUE, PDO::PARAM_INT);
            $stmt->execute();

            $repechage = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($repechage) {
                // Formater la date
                $repechage['dateCreation'] = date('d/m/Y H:i', strtotime($repechage['dateCreation']));
            }

            echo json_encode([
                'success' => true,
                'repechage' => $repechage
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID UE manquant']);
        }
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Action not found']);
        break;
}
}

// Ajouter cette fonction dans deliberationUeController.php

/**
 * Appliquer le repêchage sur une liste d'étudiants spécifique
 */
function appliquerRepechageSurEtudiants($etudiantsEligibles, $minMoyenne, $strategy = 'neutral', $lockGE10 = false, $displayStep = 0.01)
{
    $targetAvg = 10.0;
    $maxNote = 20.0;
    $simulations = [];

    foreach ($etudiantsEligibles as $etudiantData) {
        // Vérifier que l'étudiant a bien des EC
        if (!isset($etudiantData['ec']) || !is_array($etudiantData['ec']) || count($etudiantData['ec']) === 0) {
            continue;
        }

        // Calculer la moyenne actuelle
        $totalPoints = 0;
        $totalCoefs = 0;

        foreach ($etudiantData['ec'] as $ec) {
            $note = floatval($ec['note'] ?? 0);
            $coef = floatval($ec['coef'] ?? 1);
            $totalPoints += ($note * $coef);
            $totalCoefs += $coef;
        }

        $avgBefore = $totalCoefs > 0 ? ($totalPoints / $totalCoefs) : 0;

        // Vérification de l'éligibilité (moyenne >= seuil et < 10)
        if ($avgBefore >= $minMoyenne && $avgBefore < $targetAvg) {

            $ecModifies = $etudiantData['ec'];

            // ÉTAPE 1 : Préparer les données et stocker la note initiale pour le "lock"
            foreach ($ecModifies as &$e) {
                $e["note_initial"] = $e["note"];
            }
            unset($e);

            // ÉTAPE 2 : Calcul des points UE manquants
            $sumC = sumCoef($ecModifies);
            $pointsMissing = ($targetAvg - $avgBefore) * $sumC;

            // ÉTAPE 3 : Redistribution continue avec option LOCK
            redistributeContinuous($ecModifies, $pointsMissing, $strategy, $lockGE10, $maxNote);

            // ÉTAPE 4 : Force le 10.00 exact (le lock est "soft" ici si nécessaire)
            $fix = forceExactTargetByResidual($ecModifies, $targetAvg, $lockGE10, $maxNote);

            // ÉTAPE 5 : Calcul des notes d'affichage (arrondi visuel)
            foreach ($ecModifies as &$e) {
                $e["note_affichage"] = number_format(displayRound($e["note"], $displayStep), 2);
            }
            unset($e);

            $simulations[] = [
                "matricule" => $etudiantData['matricule'],
                "nom" => $etudiantData['matricule'], // Le nom sera complété côté front
                "moyenne_avant" => round($avgBefore, 4),
                "moyenne_apres" => round(weightedAverage($ecModifies), 4),
                "info_fix" => $fix['reason'],
                "details_ec" => $ecModifies
            ];
        }
    }
    return $simulations;
}
// Fonction pour récupérer les UE et leurs inscriptions pédagogiques
function getUEsWithInscriptions($pdo)
{
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

function getEtudiantsByUE($pdo, $idUE)
{
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
function getEtudiant($pdo, $matricule)
{
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

function getScolariteInscriptionPedagogique($pdo)
{
    $sql = "SELECT * FROM scolarite_inscription_pedagogique_ue sipu
    JOIN ue ON sipu.idUE = ue.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEtudiantsByInscriptionPedagogique($pdo, $idInscriptionPedagogique)
{
    $sql = "SELECT * FROM etudiant WHERE idInscriptionPedagogique = :idInscriptionPedagogique";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':idInscriptionPedagogique', $idInscriptionPedagogique, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getFilere($pdo)
{
    $sql = "SELECT * FROM filieres";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getOptionByFiliere($pdo, $idFiliere)
{
    if ($idFiliere == 0) {
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
function getMaquetteByOption($pdo, $idOption)
{
    $sql = "SELECT * FROM maquette WHERE idOption = :idOption";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':idOption', $idOption, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getNiveauFormationByCycle($pdo, $idCycleFormation)
{

    $sql = "SELECT DISTINCT niv.* FROM niveauformation niv
    JOIN options o ON niv.id = o.idNiveauFormation
    WHERE niv.idCycleFormation = :idCycleFormation";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':idCycleFormation', $idCycleFormation, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getMaquetteUEs($pdo,  $idcycle = null, $idNiveauFormation = null, $idOption = null, $idSemestre = null)
{
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
    sem.numInYear as numeroSemestre,
    rep.idUe as repechage
FROM maquette_ue mue
JOIN ue ON mue.id_ue = ue.id
JOIN semestre sem ON ue.id_semestre = sem.id
JOIN maquette m ON mue.id_maquette = m.id
JOIN options o ON m.idOption = o.id
JOIN niveauformation niv on o.idNiveauFormation = niv.id
JOIN cycleformation cyc ON cyc.id = niv.idCycleFormation
LEFT JOIN repechage rep on rep.idUe = ue.id

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
function getEtudiantByUE($pdo, $idUE, $session_id = 1)
{
    $sql = "SELECT DISTINCT 
                se.matricule, 
                se.prenom, 
                se.nom as nomEtudiant, 
                ec.coefficient as coef,
                pn.note, 
                ec.nom as nomEc 
            FROM scolarite_inscription_pedagogique_ue sipu
            JOIN scolarite_inscription_pedagogique sip ON sipu.idInscriptionPedagogique = sip.id
            JOIN scolarite_etudiants se ON sipu.matricule = se.matricule
            JOIN scolarite_inscription si on sip.idInscription = si.id
            JOIN pedagogie_notes pn ON sip.id = pn.idInscription
            JOIN ec ON ec.id = pn.idEc
            WHERE pn.idUe = :idUE AND sip.statut = 1 AND session_id = :session_id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':idUE', $idUE, PDO::PARAM_INT);
    $stmt->bindParam(':session_id', $session_id, PDO::PARAM_INT);
    $stmt->execute();

    $resultatsBruts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $etudiants = [];

    foreach ($resultatsBruts as $ligne) {
        $m = $ligne['matricule'];

        // Si l'étudiant n'est pas encore dans notre tableau, on l'initialise
        if (!isset($etudiants[$m])) {
            $etudiants[$m] = [
                "matricule" => $m,
                "prenom"    => $ligne['prenom'],
                "nom"       => $ligne['nomEtudiant'],
                "ec"        => [] // Tableau qui contiendra les évaluations
            ];
        }

        // On ajoute l'EC au tableau de l'étudiant avec les contraintes demandées
        $etudiants[$m]["ec"][] = [
            "name" => $ligne['nomEc'],
            "coef" => max(1, (float)$ligne['coef']),
            "note" => max(0, min(20, (float)$ligne['note'])) // Équivalent de clamp(note, 0, 20)
        ];
    }

    // On utilise array_values pour réindexer le tableau numériquement (0, 1, 2...)
    return array_values($etudiants);
}

function getStatUE($pdo, $ueId)
{
    $sql = "SELECT
    COUNT(DISTINCT CASE WHEN pn.non_compose = 1 THEN sipu.matricule END) AS nombreNonComposes,
    COUNT(DISTINCT CASE WHEN pn.non_compose = 0 THEN sipu.matricule END) AS nombreComposes
FROM scolarite_inscription_pedagogique_ue sipu
    JOIN scolarite_inscription_pedagogique sip ON sipu.idInscriptionPedagogique = sip.id
    JOIN scolarite_etudiants se ON sipu.matricule = se.matricule
    JOIN scolarite_inscription si on sip.idInscription = si.id
    AND sip.statut = 1
    JOIN pedagogie_notes pn ON sip.id = pn.idInscription
    JOIN ec ON ec.id = pn.idEc
WHERE pn.idUe = :ueId
GROUP BY pn.idUe;";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':ueId', $ueId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// --- Ajoutez ces fonctions utilitaires issues de votre script de repêchage ---
// --- COPIEZ CE BLOC TOUT EN BAS DE deliberationUeController.php ---

function clamp($x, $a, $b)
{
    return max($a, min($b, $x));
}

function sumCoef($ec)
{
    $s = 0.0;
    foreach ($ec as $e) $s += $e["coef"];
    return $s;
}

function weightedSum($ec)
{
    $sum = 0.0;
    foreach ($ec as $e) $sum += $e["coef"] * $e["note"];
    return $sum;
}

function weightedAverage($ec)
{
    $sc = sumCoef($ec);
    return ($sc > 0) ? (weightedSum($ec) / $sc) : 0.0;
}

function computeWeights($ec, $strategy, $maxNote = 20.0)
{
    $w = [];
    $eps = 1e-6;
    foreach ($ec as $i => $e) {
        $coef = $e["coef"];
        $note = $e["note"];
        if ($strategy === "favor_low") {
            $w[$i] = $coef * max($eps, ($maxNote - $note));
        } elseif ($strategy === "favor_high") {
            $w[$i] = $coef * max($eps, $note);
        } else {
            $w[$i] = $coef;
        }
    }
    return $w;
}

function redistributeContinuous(&$ec, $pointsUE, $strategy, $lockStrict, $maxNote = 20.0)
{
    $eps = 1e-9;
    $P = $pointsUE;
    while ($P > $eps) {
        $activeIdx = [];
        foreach ($ec as $i => $e) {
            $cap = $maxNote - $e["note"];
            if ($cap <= $eps) continue;
            if ($lockStrict && isset($e["note_initial"]) && $e["note_initial"] >= 10.0) continue;
            $activeIdx[] = $i;
        }
        if (count($activeIdx) === 0) break;
        $w = computeWeights($ec, $strategy, $maxNote);
        $W = 0.0;
        foreach ($activeIdx as $i) $W += $w[$i];
        if ($W <= $eps) break;
        $used = 0.0;
        foreach ($activeIdx as $i) {
            $coef = $ec[$i]["coef"];
            $capUE = $coef * ($maxNote - $ec[$i]["note"]);
            $allocUE = $P * ($w[$i] / $W);
            $giveUE = min($allocUE, $capUE);
            $deltaNote = ($coef > 0) ? ($giveUE / $coef) : 0.0;
            $ec[$i]["note"] = min($maxNote, $ec[$i]["note"] + $deltaNote);
            $used += $giveUE;
        }
        if ($used < $eps) break;
        $P -= $used;
    }
    return $P;
}

function forceExactTargetByResidual(&$ec, $targetAvg, $lockStrictPreferred, $maxNote = 20.0)
{
    $eps = 1e-7;
    $sumC = sumCoef($ec);
    $S_target = $targetAvg * $sumC;
    $S = weightedSum($ec);
    $residualUE = $S_target - $S;

    if (abs($residualUE) <= $eps) return ["ok" => true, "used_lock" => $lockStrictPreferred, "reason" => "already_close"];

    $tryPick = function ($lockStrict) use (&$ec, $residualUE, $eps, $maxNote) {
        $candidates = [];
        foreach ($ec as $i => $e) {
            if ($lockStrict && isset($e["note_initial"]) && $e["note_initial"] >= 10.0) continue;
            if ($e["coef"] <= 0) continue;
            $deltaNote = $residualUE / $e["coef"];
            $newNote = $e["note"] + $deltaNote;
            if ($newNote < -$eps || $newNote > $maxNote + $eps) continue;
            $candidates[] = ["i" => $i, "score" => abs($deltaNote) / $e["coef"]];
        }
        if (empty($candidates)) return null;
        usort($candidates, function ($a, $b) {
            return $a["score"] <=> $b["score"];
        });
        return $candidates[0]["i"];
    };

    $idx = $tryPick($lockStrictPreferred);
    if ($idx === null && $lockStrictPreferred) $idx = $tryPick(false);

    if ($idx !== null) {
        $deltaNote = $residualUE / $ec[$idx]["coef"];
        $ec[$idx]["note"] = max(0, min($maxNote, $ec[$idx]["note"] + $deltaNote));
        return ["ok" => true, "used_lock" => false, "reason" => "fixed"];
    }
    return ["ok" => false, "reason" => "impossible"];
}

function displayRound($x, $step)
{
    if ($step <= 0.0) return $x;
    return round(round($x / $step) * $step, 2);
}

// --- La fonction principale de traitement ---
/**
 * Logique de simulation avec prise en compte du verrouillage et de l'arrondi
 */
function appliquerRepechageUE($pdo, $idUE, $minMoyenne, $strategy = 'neutral', $lockGE10 = false, $displayStep = 0.01)
{
    $etudiantsBruts = getEtudiantByUE($pdo, $idUE); // Votre fonction qui regroupe déjà par matricule
    $targetAvg = 10.0;
    $maxNote = 20.0;
    $simulations = [];

    foreach ($etudiantsBruts as $etudiant) {
        $avgBefore = weightedAverage($etudiant['ec']);

        // Vérification de l'éligibilité (Intervalle de repêchage)
        if ($avgBefore >= $minMoyenne && $avgBefore < $targetAvg) {

            $ecModifies = $etudiant['ec'];

            // ÉTAPE 1 : Préparer les données et stocker la note initiale pour le "lock"
            foreach ($ecModifies as &$e) {
                $e["note_initial"] = $e["note"];
            }
            unset($e);

            // ÉTAPE 2 : Calcul des points UE manquants
            $sumC = sumCoef($ecModifies);
            $pointsMissing = ($targetAvg - $avgBefore) * $sumC;

            // ÉTAPE 3 : Redistribution continue avec option LOCK
            // On passe $lockGE10 à la fonction
            redistributeContinuous($ecModifies, $pointsMissing, $strategy, $lockGE10, $maxNote);

            // ÉTAPE 4 : Force le 10.00 exact (le lock est "soft" ici si nécessaire)
            $fix = forceExactTargetByResidual($ecModifies, $targetAvg, $lockGE10, $maxNote);

            // ÉTAPE 5 : Calcul des notes d'affichage (arrondi visuel)
            foreach ($ecModifies as &$e) {
                $e["note_affichage"] = number_format(displayRound($e["note"], $displayStep), 2);
            }
            unset($e);

            $simulations[] = [
                "matricule" => $etudiant['matricule'],
                "nom" => $etudiant['nom'],
                "moyenne_avant" => round($avgBefore, 4),
                "moyenne_apres" => round(weightedAverage($ecModifies), 4),
                "info_fix" => $fix['reason'],
                "details_ec" => $ecModifies
            ];
        }
    }
    return $simulations;
}
// Ajoutez cette fonction AVANT la fonction getStatUE existante

function getStatistiquesCompletes($pdo, $ueId)
{
    $sql = "SELECT 
        MIN(note_finale) as minMoyenne,
        MAX(note_finale) as maxMoyenne,
        AVG(note_finale) as moyenneGenerale,
        COUNT(DISTINCT matricule) as nombreEtudiants,
        COUNT(DISTINCT CASE WHEN note_finale >= 10 THEN matricule END) as nbReussite,
        COUNT(DISTINCT CASE WHEN note_finale < 10 THEN matricule END) as nbEchec
    FROM (
        SELECT 
            sipu.matricule,
            SUM(pn.note * ec.coefficient) / SUM(ec.coefficient) as note_finale
        FROM scolarite_inscription_pedagogique_ue sipu
        JOIN scolarite_inscription_pedagogique sip ON sipu.idInscriptionPedagogique = sip.id
        JOIN pedagogie_notes pn ON sip.id = pn.idInscription
        JOIN ec ON ec.id = pn.idEc
        WHERE pn.idUe = :ueId AND sip.statut = 1
        GROUP BY sipu.matricule
    ) as notes_etudiants";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':ueId', $ueId, PDO::PARAM_INT);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'min' => $result ? (float)$result['minMoyenne'] : 0,
        'max' => $result ? (float)$result['maxMoyenne'] : 0,
        'moyenne' => $result ? (float)$result['moyenneGenerale'] : 0,
        'effectif' => $result ? (int)$result['nombreEtudiants'] : 0,
        'reussite' => $result ? (int)$result['nbReussite'] : 0,
        'echec' => $result ? (int)$result['nbEchec'] : 0
    ];
}
