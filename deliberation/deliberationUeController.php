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
            $campagne = date('Y') . '-S' . (date('n') <= 6 ? '1' : '2'); // Correction : ajout des parenthèses
            $idUser = $_SESSION['idUser'] ?? 0;
            $idSem = $data['idSemestre'] ?? null;

            try {
                $pdo->beginTransaction();

                // 1. Enregistrer le repêchage dans la table repêchage
                $sqlRep = "INSERT INTO `repechage`(`idUe`, `idSem`, `barre`, `strategeDeCalcul`, `pasArrondi`, `lockIfNoteSup10`, `campagne`, `idUser`, `dateCreation`) 
               VALUES (:idUe, :idSem, :barre, :strategy, :rounding_step, :lock_ge10, :campagne, :idUser, NOW())";

                $stmtRep = $pdo->prepare($sqlRep);
                $stmtRep->execute([
                    ':idUe' => $idUE,
                    ':idSem' => $idSem,
                    ':barre' => $barre,
                    ':strategy' => $data['strategy'] ?? 'neutral',
                    ':rounding_step' => floatval($data['rounding_step'] ?? 0.01),
                    ':lock_ge10' => $data['lock_ge10'] ?? false,
                    ':campagne' => $campagne,
                    ':idUser' => $idUser
                ]);

                $idRepechage = $pdo->lastInsertId();

                // 2. Mettre à jour les notes dans pedagogie_notes via la fonction enregistrerRepêchage
                $notesModifiees = 0;
                $resultats = [];
                $etudiantsTraites = 0;

                foreach ($simulations as $simulation) { // Correction : utiliser $simulations au lieu de $input['simulations']
                    try {
                        // Préparer les données
                        $dataRepêchage = préparerDonnéesRepêchage($pdo, $simulation, $idUE, 1, $intervalle['min']);

                        // Ajouter l'utilisateur
                        $dataRepêchage['idUtilisateur'] = $_SESSION['idUser'] ?? null;

                        // Enregistrer
                        $resultat = enregistrerRepêchage($pdo, $dataRepêchage);
                        $resultats[] = $resultat;

                        // Compter les notes modifiées
                        if (isset($resultat['nb_ec_repêchés'])) {
                            $notesModifiees += $resultat['nb_ec_repêchés'];
                        }

                        $etudiantsTraites++;
                    } catch (Exception $e) {
                        // Si un étudiant échoue, on rollback tout
                        throw new Exception("Erreur pour l'étudiant {$simulation['matricule']}: " . $e->getMessage());
                    }
                }

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => "Repêchage appliqué avec succès. $notesModifiees note(s) modifiée(s) pour $etudiantsTraites étudiant(s).",
                    'idRepechage' => $idRepechage,
                    'details' => $resultats
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            break;
        case 'enregistrerRepêchage':
            $input = json_decode(file_get_contents('php://input'), true);

            try {
                $pdo->beginTransaction();
                $resultats = [];

                foreach ($input['simulations'] as $simulation) {
                    // Préparer les données
                    $dataRepêchage = préparerDonnéesRepêchage($pdo, $simulation, $input['idUE'], $input['idSession'], $input['seuil']);

                    // Ajouter l'utilisateur
                    $dataRepêchage['idUtilisateur'] = $_SESSION['idUser'] ?? null;

                    // Enregistrer
                    $resultat = enregistrerRepêchage($pdo, $dataRepêchage);
                    $resultats[] = $resultat;
                }

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => sprintf('%d repêchage(s) enregistré(s)', count($resultats)),
                    'details' => $resultats
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur: ' . $e->getMessage()
                ]);
            }
            break;
        case 'appliquerRepêchageGlobal':
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input || !isset($input['applications']) || !isset($input['seuil'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Données invalides']);
                break;
            }

            $applications = $input['applications'];
            $seuil = floatval($input['seuil']);
            $campagne = date('Y') . '-S' . (date('n') <= 6 ? '1' : '2');
            session_start();
            $idUser = $_SESSION['idUser'] ?? 0;

            try {
                $pdo->beginTransaction();

                $ueTraitees = 0;
                $totalNotesModifiees = 0;
                $resultats = [];

                foreach ($applications as $application) {
                    $idUE = $application['idUE'];
                    $simulations = $application['simulations'];
                    $intervalle = $application['intervalle'];
                    $barre = floatval($intervalle['min']);

                    // Récupérer le semestre de l'UE
                    $sqlSemestre = "SELECT id_semestre FROM ue WHERE id = :idUe";
                    $stmtSemestre = $pdo->prepare($sqlSemestre);
                    $stmtSemestre->execute([':idUe' => $idUE]);
                    $ueData = $stmtSemestre->fetch(PDO::FETCH_ASSOC);
                    $idSem = $ueData['id_semestre'] ?? null;

                    // 1. Enregistrer le repêchage
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

                    // 2. Mettre à jour les notes
                    $notesModifieesUE = 0;
                    foreach ($simulations as $simulation) {
                        foreach ($simulation['details_ec'] as $ec) {
                            if (isset($ec['note_affichage']) && $ec['note_affichage'] != $ec['note_initial']) {


                                if ($stmtUpdate->rowCount() > 0) {
                                    $notesModifieesUE++;
                                    $totalNotesModifiees++;

                                    // Enregistrer le détail
                                    try {
                                        $sqlDetail = "INSERT INTO repêchage_details 
                                            (idRepechage, matricule, idEc, note_avant, note_apres) 
                                            VALUES (:idRep, :matricule, 
                                                    (SELECT id FROM ec WHERE nom = :nomEc LIMIT 1),
                                                    :avant, :apres)";

                                        $stmtDetail = $pdo->prepare($sqlDetail);
                                        $stmtDetail->execute([
                                            ':idRep' => $idRepechage,
                                            ':matricule' => $simulation['matricule'],
                                            ':nomEc' => $ec['name'],
                                            ':avant' => $ec['note_initial'],
                                            ':apres' => $ec['note_affichage']
                                        ]);
                                    } catch (Exception $e) {
                                        // Ignorer si la table n'existe pas
                                    }
                                }
                            }
                        }
                    }

                    $ueTraitees++;
                    $resultats[] = [
                        'idUE' => $idUE,
                        'idRepechage' => $idRepechage,
                        'notesModifiees' => $notesModifieesUE,
                        'etudiants' => count($simulations)
                    ];
                }

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => "Repêchage global appliqué avec succès. $ueTraitees UE(s) traitées, $totalNotesModifiees note(s) modifiées.",
                    'ueTraitees' => $ueTraitees,
                    'notesModifiees' => $totalNotesModifiees,
                    'resultats' => $resultats
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur globale: ' . $e->getMessage()]);
            }
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Action not found']);
            break;
    }
} elseif ($getAction) {
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
            $idNiveauFormation = $_GET['idNiveauFormation'] ?? null;

            $options = getOptionByFiliere($pdo, $idFiliere, $idNiveauFormation);
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
        case 'verifierEvaluationsUE':
            $idUE = $_GET['idUE'] ?? null;
            $session_id = $_GET['session_id'] ?? 1;

            if ($idUE) {
                $stats = verifierCompletudeEvaluationsUE($pdo, $idUE, $session_id);
                echo json_encode(['success' => true, 'stats' => $stats]);
            } else {
                echo json_encode(['success' => false, 'message' => 'ID UE manquant']);
            }
            break;
        case 'getStatistiquesUE':
            $idUE = $_GET['idUE'] ?? null;
            if ($idUE) {
                $stats = getStatistiquesCompletes($pdo, $idUE);
                echo json_encode(['success' => true, 'stats' => $stats]);
            } else {
                echo json_encode(['success' => false, 'message' => 'ID UE manquant']);
            }
            break;
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
        case 'getDeliberationDeUE':

$idUE = $_GET['idUE'] ?? null;

if (!$idUE) {
    echo json_encode(['success'=>false,'message'=>'ID UE manquant']);
    break;
}

try {

    /* =====================================================
        1. UE
    ===================================================== */

    $sqlUE = "
        SELECT ue.id, ue.code, ue.nom AS nomUE, ue.nombre_credit,
               sem.numero as semestre, r.idRepechage , r.barre, r.campagne, r.dateCreation
        FROM ue
        JOIN semestre sem ON ue.id_semestre = sem.id
        JOIN repechage r ON r.idUe = ue.id and dateCreation = (
            SELECT MAX(dateCreation) 
            FROM repechage 
            WHERE idUe = ue.id
        )
        WHERE ue.id = :idUE
    ";

    $stmt = $pdo->prepare($sqlUE);
    $stmt->execute([':idUE'=>$idUE]);
    $ue = $stmt->fetch(PDO::FETCH_ASSOC);


    /* =====================================================
        2. ETUDIANTS
    ===================================================== */

    $sqlEtudiants = "
        SELECT DISTINCT 
            se.matricule,
            se.nom,
            se.prenom,
            sip.id AS idInscription
        FROM scolarite_inscription_pedagogique_ue sipu
        JOIN scolarite_inscription_pedagogique sip 
            ON sipu.idInscriptionPedagogique = sip.id
        JOIN scolarite_etudiants se 
            ON se.matricule = sip.matricule
        WHERE sipu.idUE = :idUE
        AND sip.statut = 1
        ORDER BY se.nom, se.prenom
    ";

    $stmt = $pdo->prepare($sqlEtudiants);
    $stmt->execute([':idUE'=>$idUE]);
    $inscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalEtudiants = count($inscriptions);


    /* =====================================================
        3. EC
    ===================================================== */

    $sqlEC = "SELECT id, nom, coefficient FROM ec WHERE id_ue = :idUE";
    $stmt = $pdo->prepare($sqlEC);
    $stmt->execute([':idUE'=>$idUE]);
    $ecsUE = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $ecsUEIndexed = [];
    foreach($ecsUE as $ec){
        $ecsUEIndexed[$ec['id']] = $ec;
    }


    /* =====================================================
        4. NOTES (UNE SEULE REQUETE)
    ===================================================== */

    $sqlNotes = "
        SELECT 
            pn.idInscription,
            pn.idEc,
            pn.note,
            pn.nature,
            pn.non_compose,
            ec.nom AS nomEC,
            ec.coefficient
        FROM pedagogie_notes pn
        JOIN ec ON ec.id = pn.idEc
        WHERE pn.idUE = :idUE
        AND pn.session_id = 1
    ";

    $stmt = $pdo->prepare($sqlNotes);
    $stmt->execute([':idUE'=>$idUE]);

    $notesParInscription = [];

    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $n){
        $notesParInscription[$n['idInscription']][] = $n;
    }


    /* =====================================================
        5. REPECHAGES (UNE SEULE REQUETE)
    ===================================================== */

    $sqlRepechage = "
        SELECT *
        FROM repêchage_historique
        WHERE idUE = :idUE
    ";

    $stmt = $pdo->prepare($sqlRepechage);
    $stmt->execute([':idUE'=>$idUE]);

    $repechageMap = [];
    $repechageDetails = [];
    $totalPointsJury = 0;
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $rep){
        $repechageMap[$rep['idInscription']] = true;
        $repechageDetails[$rep['idInscription']][] = $rep;
        if(isset($rep['point_jury']) && is_numeric($rep['point_jury'])){
            $totalPointsJury += $rep['point_jury'];
        }
    }


    /* =====================================================
        6. CALCUL
    ===================================================== */

    $tousEtudiants = [];

    foreach($inscriptions as $inscription){

        $idInscription = $inscription['idInscription'];
        $notes = $notesParInscription[$idInscription] ?? [];

        $ecsEtudiant = [];

        foreach($notes as $note){

            $ecId = $note['idEc'];

            if(!isset($ecsEtudiant[$ecId])){
                $ecsEtudiant[$ecId]=[
                    'coef'=>$note['coefficient'],
                    'devoirs'=>[],
                    'examens'=>[]
                ];
            }

            if($note['non_compose']==0){
                if($note['nature']==1)
                    $ecsEtudiant[$ecId]['devoirs'][]=$note['note'];
                elseif($note['nature']==2)
                    $ecsEtudiant[$ecId]['examens'][]=$note['note'];
            }
        }

        $totalPointsUE=0;
        $totalCoefsUE=0;
        $aTousExamens=true;
        $pointJuryEtudiant = 0;
        foreach($ecsUEIndexed as $ecId=>$ec){

            $data=$ecsEtudiant[$ecId]??null;

            if(!$data || empty($data['examens'])){
                $aTousExamens=false;
                $noteEC=0;
            }else{

                $moyExam=array_sum($data['examens'])/count($data['examens']);

                if(!empty($data['devoirs'])){
                    $moyDev=array_sum($data['devoirs'])/count($data['devoirs']);
                    $noteEC=($moyDev*0.4)+($moyExam*0.6);
                }else{
                    $noteEC=$moyExam;
                }
            }

            $totalPointsUE += $noteEC * $ec['coefficient'];
            $totalCoefsUE += $ec['coefficient'];
        }

        $moyenneUE = ($aTousExamens && $totalCoefsUE>0)
            ? $totalPointsUE/$totalCoefsUE
            : 0;

        $estRepeche = isset($repechageMap[$idInscription]);

        $pointJuryTotal = 0;
        if ($estRepeche && isset($repechageDetails[$idInscription])) {
            foreach ($repechageDetails[$idInscription] as $rep) {
                if (isset($rep['point_jury']) && is_numeric($rep['point_jury'])) {
                    $pointJuryTotal += $rep['point_jury'];
                }
            }
        }

        $tousEtudiants[]=[
            'matricule'=>$inscription['matricule'],
            'nom'=>$inscription['prenom'].' '.$inscription['nom'],
            'moyenne_ue'=>round($moyenneUE,2),
            'est_repeche'=>$estRepeche,
            'point_jury'=>round($pointJuryTotal, 2)
        ];
    }


    /* =====================================================
        7. RETURN
    ===================================================== */
    $ue['dateDernierRepêchage'] = $ue['dateCreation'] ? date('d/m/Y H:i', strtotime($ue['dateCreation'])) : null;
    $ue['totalPointsJury'] = round($totalPointsJury, 2);
    echo json_encode([
        'success'=>true,
        'ue'=>$ue,
        'total_etudiants'=>$totalEtudiants,
        'etudiants'=>$tousEtudiants
    ]);

}catch(Exception $e){

    echo json_encode([
        'success'=>false,
        'message'=>$e->getMessage()
    ]);

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
function getOptionByFiliere($pdo, $idFiliere, $idNiveauFormation = null)
{
    if ($idFiliere == 0) {
        $sql = "SELECT * FROM options where code_option != 'TC' GROUP BY code_option";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $sql = "SELECT * FROM options WHERE idFilieres = :idFiliere AND idNiveauFormation = :idNiveauFormation AND code_option != 'TC' GROUP BY code_option, idNiveauFormation";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':idFiliere', $idFiliere, PDO::PARAM_INT);

    $stmt->bindParam(':idNiveauFormation', $idNiveauFormation, PDO::PARAM_INT);
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
    // Recuperer le nombre de devoirs pour chaque EC de l'UE
    $sqlDevoirs = "SELECT bn.idEc, COUNT(bn.idDevoir) as nbDevoirs, bn.nature FROM bordereau_note bn 
   WHERE  bn.idNature = 1 
   AND bn.idEc IN (SELECT DISTINCT pn.idEc FROM pedagogie_notes pn WHERE pn.idAnnee = 
                 (SELECT MAX(id) FROM scolarite_anneeuniversitaire)
                  AND pn.session_id = 1 AND pn.idUe = :idUE)
    GROUP BY bn.idEc";
    $stmtDevoirs = $pdo->prepare($sqlDevoirs);
    $stmtDevoirs->bindParam(':idUE', $idUE, PDO::PARAM_INT);
    $stmtDevoirs->execute();
    $nbDevoirsParEc = [];
    foreach ($stmtDevoirs->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $nbDevoirsParEc[$row['idEc']] = (int)$row['nbDevoirs'];
    }

    // 1. Récupérer les étudiants et leurs notes
    $sql = "SELECT 
                se.matricule, 
                se.prenom, 
                se.nom as nomEtudiant, 
                ec.id as ec_id,
                pn.idInscription,
                ec.nom as nomEc,
                ec.coefficient as coef_ec,
                pn.note, 
                pn.nature,
                pn.non_compose,
                pn.idAnnee,
                CASE 
                    WHEN pn.nature = 2 THEN 'examen'
                    ELSE 'devoir'
                END as type_evaluation
            FROM scolarite_inscription_pedagogique_ue sipu
            JOIN scolarite_inscription_pedagogique sip ON sipu.idInscriptionPedagogique = sip.id
            JOIN scolarite_etudiants se ON sipu.matricule = se.matricule
            JOIN scolarite_inscription si on sip.idInscription = si.id
            JOIN pedagogie_notes pn ON sip.id = pn.idInscription
            JOIN ec ON ec.id = pn.idEc
            WHERE pn.idUe = :idUE 
              AND sip.statut = 1 
              AND session_id = :session_id
              AND pn.non_compose = 0
              AND pn.idAnnee = (SELECT MAX(id) FROM scolarite_anneeuniversitaire)
            GROUP BY se.matricule, ec.id, pn.idDevoir
            ORDER BY se.matricule, ec.id, pn.nature";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':idUE', $idUE, PDO::PARAM_INT);
    $stmt->bindParam(':session_id', $session_id, PDO::PARAM_INT);
    $stmt->execute();

    $resultatsBruts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $etudiants = [];

    // 2. Organiser les données par étudiant et par EC
    foreach ($resultatsBruts as $ligne) {
        $matricule = $ligne['matricule'];
        $ecId = $ligne['ec_id'];
        $type = $ligne['type_evaluation'];

        if (!isset($etudiants[$matricule])) {
            $etudiants[$matricule] = [
                "matricule" => $matricule,
                "prenom"    => $ligne['prenom'],
                "nom"       => $ligne['nomEtudiant'],
                "ec"        => []
            ];
        }

        // Initialiser l'EC s'il n'existe pas
        if (!isset($etudiants[$matricule]["ec"][$ecId])) {
            $etudiants[$matricule]["ec"][$ecId] = [
                "id" => $ecId,
                "name" => $ligne['nomEc'],
                "coef_ec" => max(1, (float)$ligne['coef_ec']),
                "devoirs" => [],
                "examens" => [],
                "note_devoir" => null,
                "note_examen" => null,
                "note_finale_ec" => null,
                "a_examen" => false
            ];
        }

        // Ajouter la note
        $noteValue = max(0, min(20, (float)$ligne['note']));

        if ($type === 'devoir') {
            $etudiants[$matricule]["ec"][$ecId]["devoirs"][] = $noteValue;
        } else {
            $etudiants[$matricule]["ec"][$ecId]["examens"][] = $noteValue;
            $etudiants[$matricule]["ec"][$ecId]["a_examen"] = true;
        }
    }

    // 3. Calculer les moyennes pour chaque EC
    foreach ($etudiants as $matricule => &$etudiant) {
        $aTousExamens = true;
        
        foreach ($etudiant["ec"] as $ecId => &$ecData) {
            // Moyenne des devoirs
            $moyenneDevoir = null;

            if (!empty($ecData["devoirs"])) {
                $nbDevoirs = $nbDevoirsParEc[$ecId];
                $moyenneDevoir = array_sum($ecData["devoirs"]) / $nbDevoirs;
                $ecData["note_devoir"] = $moyenneDevoir;
            }
            if (empty($ecData["devoirs"])) {
                $ecData["note_finale_ec"] = null;
                $ecData["calcul_mode"] = "devoir_manquant";
                $ecData["calcul_detail"] = "Devoir manquant - EC non noté";
                $aTousExamens = false; // ou un flag $aTousDevoirs dédié
                continue;
            }
            $ecData["nb_devoirs"] = $nbDevoirsParEc[$ecId];
            // Moyenne des examens
            $moyenneExamen = null;
            if (!empty($ecData["examens"])) {
                $moyenneExamen = array_sum($ecData["examens"]);
                $ecData["note_examen"] = $moyenneExamen;
            }

            // RÈGLE 1: S'il n'y a PAS d'examen, l'EC n'est pas noté
            if (!$ecData["a_examen"]) {
                $ecData["note_finale_ec"] = null;
                $ecData["calcul_mode"] = "examen_manquant";
                $ecData["calcul_detail"] = "Examen manquant - EC non noté";
                $aTousExamens = false;
                continue;
            }

            // RÈGLE 2: Calcul de la note EC (40% devoir + 60% examen)
            if ($moyenneDevoir !== null && $moyenneExamen !== null) {
                // Cas normal: devoirs ET examen
                $noteFinale = ($moyenneDevoir * 0.4) + ($moyenneExamen * 0.6);
                $ecData["note_finale_ec"] = round($noteFinale, 2);
                $ecData["calcul_mode"] = "40_60";
                $ecData["calcul_detail"] = sprintf("%.2f × 0.4 + %.2f × 0.6", $moyenneDevoir, $moyenneExamen);
            } else {
                // Ce cas ne devrait pas arriver car on a vérifié a_examen = true
                $ecData["note_finale_ec"] = null;
                $ecData["calcul_mode"] = "erreur_calcul";
                $ecData["calcul_detail"] = "Erreur de calcul";
                $aTousExamens = false;
            }

            // Pour compatibilité
            $ecData["note"] = $ecData["note_finale_ec"];
            $ecData["coef"] = $ecData["coef_ec"];
        }

        // Convertir en liste
        $etudiant["ec"] = array_values($etudiant["ec"]);
        
        // RÈGLE 4: Calcul de la moyenne UE
        if ($aTousExamens) {
            // Tous les EC ont un examen → calcul normal
            $totalPointsUE = 0;
            $totalCoefUE = 0;
            $calculDetailUE = [];

            foreach ($etudiant["ec"] as $ec) {
                if ($ec["note_finale_ec"] !== null) {
                    $contribution = $ec["note_finale_ec"] * $ec["coef_ec"];
                    $totalPointsUE += $contribution;
                    $totalCoefUE += $ec["coef_ec"];
                    $calculDetailUE[] = sprintf("%.2f × %.1f", $ec["note_finale_ec"], $ec["coef_ec"]);
                }
            }

            $etudiant["moyenne_ue"] = $totalCoefUE > 0 ? round($totalPointsUE / $totalCoefUE, 2) : 0;
            $etudiant["moyenne_calculable"] = true;
        } else {
            // RÈGLE 5: Si au moins un EC sans examen → moyenne UE = 0 (non calculable)
            $etudiant["moyenne_ue"] = 0;
            $etudiant["moyenne_calculable"] = false;
            $calculDetailUE = ["Moyenne non calculable - Examen(s) manquant(s)"];
        }
        $etudiant["nbDevoirsParEc"] = $nbDevoirsParEc; // Ajouter le nombre de devoirs par EC pour les statistiques
        // Statistiques
        $etudiant["stats"] = [
            "nb_ec" => count($etudiant["ec"]),
            "nb_ec_avec_examen" => count(array_filter($etudiant["ec"], function($ec) {
                return $ec["a_examen"];
            })),
            "nb_ec_sans_examen" => count(array_filter($etudiant["ec"], function($ec) {
                return !$ec["a_examen"];
            })),
            "moyenne_ue_formatee" => $etudiant["moyenne_calculable"] ? number_format($etudiant["moyenne_ue"], 2) : "N/A",
            "total_coef_ue" => array_sum(array_column($etudiant["ec"], "coef_ec")),
            "calcul_detail" => implode(" + ", $calculDetailUE),
            "moyenne_calculable" => $etudiant["moyenne_calculable"],
            "est_repechable" => ($etudiant["moyenne_calculable"] && $etudiant["moyenne_ue"] < 10 && $etudiant["moyenne_ue"] >= 7),
        ];
    }

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
    $etudiantsBruts = getEtudiantByUE($pdo, $idUE);
    $targetAvg = 10.0;
    $maxNote = 20.0;
    $simulations = [];

    foreach ($etudiantsBruts as $etudiant) {
        // Utiliser la moyenne UE calculée (40% devoir + 60% examen)
        $avgBefore = $etudiant['moyenne_ue'];

        // Vérification de l'éligibilité
        if ($avgBefore >= $minMoyenne && $avgBefore < $targetAvg) {
            $ecModifies = [];

            // Préparer les EC pour le traitement
            foreach ($etudiant['ec'] as $ec) {
                // Pour le repêchage, on travaille sur la note finale de l'EC
                $ecModifies[] = [
                    "id" => $ec["id"],
                    "name" => $ec["name"],
                    "coef" => $ec["coef_ec"],
                    "note" => $ec["note_finale_ec"],
                    "note_initial" => $ec["note_finale_ec"], // Pour référence
                    "note_devoir" => $ec["note_devoir"],
                    "note_examen" => $ec["note_examen"],
                    "devoirs" => $ec["devoirs"], // Pour affichage détaillé
                    "examens" => $ec["examens"]  // Pour affichage détaillé
                ];
            }

            // Appliquer le repêchage
            $sumC = sumCoef($ecModifies);
            $pointsMissing = ($targetAvg - $avgBefore) * $sumC;

            // Redistribution
            redistributeContinuous($ecModifies, $pointsMissing, $strategy, $lockGE10, $maxNote);
            $fix = forceExactTargetByResidual($ecModifies, $targetAvg, $lockGE10, $maxNote);

            // Calculer les notes d'affichage
            foreach ($ecModifies as &$e) {
                $e["note_affichage"] = number_format(displayRound($e["note"], $displayStep), 2);

                // Calculer la nouvelle note finale EC
                $e["nouvelle_note_finale"] = $e["note"];

                // Pour l'affichage, on peut montrer la répartition
                if ($e["note_devoir"] !== null && $e["note_examen"] !== null) {
                    // On répartit l'augmentation proportionnellement
                    $augmentation = $e["note"] - $e["note_initial"];
                    $augmentationDevoir = $augmentation * 0.4;
                    $augmentationExamen = $augmentation * 0.6;

                    $e["nouvelle_note_devoir"] = $e["note_devoir"] + $augmentationDevoir;
                    $e["nouvelle_note_examen"] = $e["note_examen"] + $augmentationExamen;
                }
            }
            unset($e);

            $simulations[] = [
                "matricule" => $etudiant['matricule'],
                "nom" => $etudiant['prenom'] . ' ' . $etudiant['nom'],
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
    // 1. Statistiques de base
    $sqlBase = "SELECT 
        COUNT(DISTINCT sipu.matricule) as effectif,
        COUNT(DISTINCT CASE WHEN pn.note >= 10 THEN sipu.matricule END) as reussite,
        COUNT(DISTINCT CASE WHEN pn.note < 10 THEN sipu.matricule END) as echec,
        COUNT(DISTINCT CASE WHEN pn.non_compose = 0 THEN sipu.matricule END) as presents,
        COUNT(DISTINCT CASE WHEN pn.non_compose = 1 THEN sipu.matricule END) as absents,
        MIN(pn.note) as min_note,
        MAX(pn.note) as max_note,
        AVG(pn.note) as moyenne
    FROM scolarite_inscription_pedagogique_ue sipu
    JOIN scolarite_inscription_pedagogique sip ON sipu.idInscriptionPedagogique = sip.id
    JOIN pedagogie_notes pn ON sip.id = pn.idInscription
    WHERE pn.idUe = :ueId AND sip.statut = 1";

    $stmtBase = $pdo->prepare($sqlBase);
    $stmtBase->execute([':ueId' => $ueId]);
    $baseStats = $stmtBase->fetch(PDO::FETCH_ASSOC);

    // 2. Intervalles de notes
    $sqlIntervals = "SELECT 
        COUNT(DISTINCT CASE WHEN note_finale >= 0 AND note_finale < 7 THEN matricule END) as intervalle_0_7,
        COUNT(DISTINCT CASE WHEN note_finale >= 7 AND note_finale < 8 THEN matricule END) as intervalle_7_8,
        COUNT(DISTINCT CASE WHEN note_finale >= 8 AND note_finale < 9 THEN matricule END) as intervalle_8_9,
        COUNT(DISTINCT CASE WHEN note_finale >= 9 AND note_finale < 10 THEN matricule END) as intervalle_9_10,
        COUNT(DISTINCT CASE WHEN note_finale >= 10 THEN matricule END) as intervalle_10_20
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

    $stmtIntervals = $pdo->prepare($sqlIntervals);
    $stmtIntervals->execute([':ueId' => $ueId]);
    $intervalStats = $stmtIntervals->fetch(PDO::FETCH_ASSOC);

    // 3. Calcul des pourcentages
    $effectif = intval($baseStats['effectif'] ?? 0);
    $reussite = intval($baseStats['reussite'] ?? 0);
    $echec = intval($baseStats['echec'] ?? 0);

    $tauxReussite = $effectif > 0 ? ($reussite / $effectif) * 100 : 0;
    $tauxEchec = $effectif > 0 ? ($echec / $effectif) * 100 : 0;

    // Combiner toutes les statistiques
    return array_merge($baseStats, $intervalStats, [
        'tauxReussite' => round($tauxReussite, 2),
        'tauxEchec' => round($tauxEchec, 2),
        'moyenne' => round(floatval($baseStats['moyenne'] ?? 0), 2),
        'min' => round(floatval($baseStats['min_note'] ?? 0), 2),
        'max' => round(floatval($baseStats['max_note'] ?? 0), 2)
    ]);
}
function getNbECForUE($idUE)
{
    $sql = 'SELECT COUNT(ec.id) FROM ue 
JOIN ec ON ec.id_ue = ue.id
WHERE ue.id = :idUE';
}
function verifierCompletudeEvaluationsUE($pdo, $idUE, $session_id = 1)
{
    $etudiants = getEtudiantByUE($pdo, $idUE, $session_id);

    $stats = [
        'total_etudiants' => count($etudiants),
        'etudiants_complets' => 0,
        'etudiants_incomplets' => 0,
        'raisons_incompletude' => [],
        'liste_etudiants_incomplets' => [],
        'details_completude' => []
    ];

    // Récupérer la liste des EC de l'UE
    $sqlEC = "SELECT DISTINCT ec.id, ec.nom, ec.coefficient 
              FROM ue
              JOIN ec ON ec.id_ue = ue.id
              WHERE ue.id = :idUE";
    $stmtEC = $pdo->prepare($sqlEC);
    $stmtEC->execute([':idUE' => $idUE]);
    $ecsUE = $stmtEC->fetchAll(PDO::FETCH_ASSOC);
    $ecsUEIndexed = [];
    foreach ($ecsUE as $ec) {
        $ecsUEIndexed[$ec['id']] = $ec;
    }
    $nbECTotal = count($ecsUE);

    foreach ($etudiants as $etudiant) {
        $anomalies = [];
        $ecsEtudiantIndexed = [];

        // Indexer les EC de l'étudiant
        foreach ($etudiant['ec'] as $ec) {
            $ecsEtudiantIndexed[$ec['id']] = $ec;
        }
        $nbECEtudiant = count($etudiant['ec']);

        // 1. Vérifier les EC manquants
        foreach ($ecsUEIndexed as $ecId => $ecUE) {
            if (!isset($ecsEtudiantIndexed[$ecId])) {
                $anomalies[] = [
                    'ec_id' => $ecId,
                    'ec_nom' => $ecUE['nom'],
                    'type' => 'ec_manquant',
                    'raison' => 'aucune_note',
                    'message' => 'Aucune note pour cet EC',
                    'bloquant' => true
                ];
                continue;
            }

            $ecData = $ecsEtudiantIndexed[$ecId];

            // 2. Vérifier la présence d'une note d'examen
            $aNoteExamen = isset($ecData['note_examen']) && $ecData['note_examen'] !== null;

            if (!$aNoteExamen) {
                $anomalies[] = [
                    'ec_id' => $ecId,
                    'ec_nom' => $ecUE['nom'],
                    'type' => 'examen_manquant',
                    'raison' => 'pas_examen',
                    'message' => 'Pas de note d\'examen',
                    'note_devoir' => $ecData['note_devoir'] ?? null,
                    'note_examen' => null,
                    'bloquant' => true
                ];
                continue;
            }

            // 3. Vérifier que la note finale est calculable
            if (!isset($ecData['note_finale_ec']) || $ecData['note_finale_ec'] === null) {
                $anomalies[] = [
                    'ec_id' => $ecId,
                    'ec_nom' => $ecUE['nom'],
                    'type' => 'note_non_calculable',
                    'raison' => 'note_non_calculable',
                    'message' => 'Note finale non calculable',
                    'note_devoir' => $ecData['note_devoir'] ?? null,
                    'note_examen' => $ecData['note_examen'] ?? null,
                    'bloquant' => true
                ];
                continue;
            }
            // EC valide : a une note d'examen et une note finale calculée
        }
        // Compter les EC valides (avec examen)
        $nbECValides = 0;
        foreach ($etudiant['ec'] as $ec) {
            if (isset($ec['note_examen']) && $ec['note_examen'] !== null) {
                $nbECValides++;
            }
        }

        // 4. Vérifier la moyenne UE
        $moyenneUE = $etudiant['moyenne_ue'] ?? 0;
        $moyenneCalculable = ($nbECValides === $nbECTotal);

        if (!$moyenneCalculable) {
            $anomalies[] = [
                'ec_id' => null,
                'ec_nom' => null,
                'type' => 'moyenne_non_calculable',
                'raison' => 'moyenne_non_calculable',
                'message' => 'Moyenne UE non calculable - EC manquants',
                'ec_manquants' => $nbECTotal - $nbECValides,
                'bloquant' => true
            ];
        }

        // Filtrer uniquement les anomalies bloquantes
        $anomaliesBloquantes = array_filter($anomalies, function ($anomalie) {
            return $anomalie['bloquant'] === true;
        });

        // Déterminer le statut de complétude
        $estComplet = empty($anomaliesBloquantes);

        if ($estComplet) {
            $stats['etudiants_complets']++;
            $statut = 'complet';
        } else {
            $stats['etudiants_incomplets']++;
            $statut = 'incomplet';

            // Ajouter à la liste des étudiants incomplets
            $stats['liste_etudiants_incomplets'][] = [
                'matricule' => $etudiant['matricule'],
                'nom' => trim(($etudiant['prenom'] ?? '') . ' ' . ($etudiant['nom'] ?? '')),
                'moyenne_ue' => $moyenneUE,
                'ec_valides' => $nbECValides,
                'ec_manquants' => $nbECTotal - $nbECValides,
                'ec_attendus' => $nbECTotal,
                'anomalies' => $anomaliesBloquantes
            ];

            // Compter les raisons d'incomplétude
            foreach ($anomaliesBloquantes as $anomalie) {
                $raison = $anomalie['raison'];
                if (!isset($stats['raisons_incompletude'][$raison])) {
                    $stats['raisons_incompletude'][$raison] = 0;
                }
                $stats['raisons_incompletude'][$raison]++;
            }
        }

        // Stocker les détails complets pour cet étudiant
        $stats['details_completude'][] = [
            'matricule' => $etudiant['matricule'],
            'nom' => trim(($etudiant['prenom'] ?? '') . ' ' . ($etudiant['nom'] ?? '')),
            'statut' => $statut,
            'moyenne_ue' => $moyenneUE,
            'moyenne_calculable' => $moyenneCalculable,
            'ec_presents' => $nbECEtudiant,
            'ec_valides' => $nbECValides,
            'ec_attendus' => $nbECTotal,
            'anomalies' => $anomalies,
            'anomalies_bloquantes' => $anomaliesBloquantes
        ];
    }

    // Calculer les pourcentages
    $total = $stats['total_etudiants'];
    $stats['pourcentage_complets'] = $total > 0
        ? round(($stats['etudiants_complets'] / $total) * 100, 2)
        : 0;
    $stats['pourcentage_incomplets'] = $total > 0
        ? round(($stats['etudiants_incomplets'] / $total) * 100, 2)
        : 0;

    // Calculer la moyenne générale des UE
    $sommeMoyennes = 0;
    $nbMoyennesCalculees = 0;
    foreach ($stats['details_completude'] as $detail) {
        if ($detail['moyenne_calculable'] && $detail['moyenne_ue'] > 0) {
            $sommeMoyennes += $detail['moyenne_ue'];
            $nbMoyennesCalculees++;
        }
    }

    $stats['statistiques_supplementaires'] = [
        'nb_ec_total' => $nbECTotal,
        'ecs_liste' => array_values($ecsUEIndexed),
        'moyenne_generale' => $nbMoyennesCalculees > 0
            ? round($sommeMoyennes / $nbMoyennesCalculees, 2)
            : 0,
        'nb_etudiants_moyenne_calculee' => $nbMoyennesCalculees,
        'nb_etudiants_sans_moyenne' => $total - $nbMoyennesCalculees,
        'taux_calculabilite' => $total > 0
            ? round(($nbMoyennesCalculees / $total) * 100, 2)
            : 0
    ];
    $stats['noteEtudiantsParEC'] = $etudiants; // Ajouter les notes des étudiants par EC pour analyse détaillée
    return $stats;
}
/**
 * Enregistre un repêchage dans la base de données
 * Note: La gestion des transactions (beginTransaction/commit) doit être gérée par le code appelant
 */
function enregistrerRepêchage($pdo, $data)
{
    try {
        // 1. Validation des données requises
        $requiredFields = ['idInscription', 'idUE', 'idSession', 'moyenne_initial', 'moyenne_final', 'ec_repêchés'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Champ requis manquant : $field");
            }
        }

        if (!is_array($data['ec_repêchés']) || count($data['ec_repêchés']) === 0) {
            throw new Exception("Aucun EC repêché à enregistrer");
        }

        // 2. Récupérer l'inscription pédagogique si non fournie
        if (!isset($data['idInscription']) || empty($data['idInscription'])) {
            $sqlGetInscription = "SELECT id FROM scolarite_inscription_pedagogique 
                                  WHERE matricule = :matricule 
                                  ORDER BY dateEnregistrement DESC LIMIT 1";
            $stmtGetInscription = $pdo->prepare($sqlGetInscription);
            $stmtGetInscription->execute([':matricule' => $data['matricule'] ?? '']);
            $inscription = $stmtGetInscription->fetch(PDO::FETCH_ASSOC);
            
            if (!$inscription) {
                throw new Exception("Inscription non trouvée pour le matricule: " . ($data['matricule'] ?? 'inconnu'));
            }
            $data['idInscription'] = $inscription['id'];
        }

        // 3. Vérifier que l'étudiant est bien inscrit à cette UE
        $sqlCheckInscription = "SELECT COUNT(*) FROM scolarite_inscription_pedagogique_ue 
                                WHERE idInscriptionPedagogique = :idInscription AND idUE = :idUE";
        $stmtCheckInscription = $pdo->prepare($sqlCheckInscription);
        $stmtCheckInscription->execute([
            ':idInscription' => $data['idInscription'],
            ':idUE' => $data['idUE']
        ]);
        
        if ($stmtCheckInscription->fetchColumn() == 0) {
            throw new Exception("L'étudiant n'est pas inscrit à cette UE");
        }

        // 4. Enregistrer la moyenne UE
        $sqlMoyenne = "INSERT INTO moyenne_ue 
                      (idInscription, idUE, idSession, moyenne, moyenne_initial, methode_calcul, nb_ec, date_calcul)
                      VALUES (:idInscription, :idUE, :idSession, :moyenne, :moyenne_initial, :methode, :nb_ec, NOW())
                      ON DUPLICATE KEY UPDATE
                      moyenne = VALUES(moyenne),
                      moyenne_initial = VALUES(moyenne_initial),
                      methode_calcul = VALUES(methode_calcul),
                      nb_ec = VALUES(nb_ec),
                      date_calcul = NOW()";

        $stmtMoyenne = $pdo->prepare($sqlMoyenne);
        $stmtMoyenne->execute([
            ':idInscription' => $data['idInscription'],
            ':idUE' => $data['idUE'],
            ':idSession' => $data['idSession'],
            ':moyenne' => $data['moyenne_final'],
            ':moyenne_initial' => $data['moyenne_initial'],
            ':methode' => $data['methode_calcul'] ?? '40%_devoir_60%_examen',
            ':nb_ec' => $data['nb_ec'] ?? count($data['ec_repêchés'])
        ]);

        // Récupérer l'ID de la moyenne UE
        $idMoyenneUE = $pdo->lastInsertId();
        
        if (!$idMoyenneUE || $idMoyenneUE == 0) {
            // Si c'était un UPDATE, récupérer l'ID existant
            $sqlGetId = "SELECT id FROM moyenne_ue 
                        WHERE idInscription = :idInscription 
                        AND idUE = :idUE 
                        AND idSession = :idSession";
            $stmtGetId = $pdo->prepare($sqlGetId);
            $stmtGetId->execute([
                ':idInscription' => $data['idInscription'],
                ':idUE' => $data['idUE'],
                ':idSession' => $data['idSession']
            ]);
            $result = $stmtGetId->fetch(PDO::FETCH_ASSOC);
            $idMoyenneUE = $result['id'] ?? null;
            
            if (!$idMoyenneUE) {
                throw new Exception("Impossible de récupérer l'ID de la moyenne UE");
            }
        }

        // 5. Insérer ou mettre à jour chaque EC repêché
        $sqlCheck = "SELECT id FROM repêchage_historique 
                    WHERE idInscription = :idInscription 
                    AND idUE = :idUE 
                    AND idEC = :idEC 
                    AND idSession = :idSession";
        $stmtCheck = $pdo->prepare($sqlCheck);

        $sqlUpdate = "UPDATE repêchage_historique SET
                     idMoyenneUE = :idMoyenneUE,
                     note_final = :note_final,
                     point_jury = :point_jury,
                     credit = :credit,
                     statut = :statut,
                     commentaire = :commentaire,
                     date_repêchage = NOW()
                     WHERE idInscription = :idInscription 
                     AND idUE = :idUE 
                     AND idEC = :idEC 
                     AND idSession = :idSession";
        $stmtUpdate = $pdo->prepare($sqlUpdate);

        $sqlInsert = "INSERT INTO repêchage_historique
                     (idInscription, idUE, idEC, idSession, idMoyenneUE,
                      note_initial, note_final, point_jury, coef, credit,
                      idUtilisateur, statut, commentaire, date_repêchage)
                     VALUES 
                     (:idInscription, :idUE, :idEC, :idSession, :idMoyenneUE,
                      :note_initial, :note_final, :point_jury, :coef, :credit,
                      :idUtilisateur, :statut, :commentaire, NOW())";
        $stmtInsert = $pdo->prepare($sqlInsert);

        $nbEcRepêchés = 0;
        $ecsTraites = [];
        
        foreach ($data['ec_repêchés'] as $ec) {
            // Validation des données EC
            if (!isset($ec['idEC'], $ec['note_initial'], $ec['note_final'], $ec['point_jury'], $ec['coef'])) {
                error_log("EC repêché invalide : " . json_encode($ec));
                continue;
            }

            // Éviter les doublons dans la même requête
            $key = $ec['idEC'] . '_' . $data['idSession'];
            if (in_array($key, $ecsTraites)) {
                continue;
            }
            $ecsTraites[] = $key;

            // Vérifier si l'enregistrement existe déjà
            $stmtCheck->execute([
                ':idInscription' => $data['idInscription'],
                ':idUE' => $data['idUE'],
                ':idEC' => $ec['idEC'],
                ':idSession' => $data['idSession']
            ]);
            
            $exists = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            // Paramètres communs
            $params = [
                ':idInscription' => $data['idInscription'],
                ':idUE' => $data['idUE'],
                ':idEC' => $ec['idEC'],
                ':idSession' => $data['idSession'],
                ':idMoyenneUE' => $idMoyenneUE,
                ':note_initial' => $ec['note_initial'],
                ':note_final' => $ec['note_final'],
                ':point_jury' => $ec['point_jury'],
                ':coef' => $ec['coef'],
                ':credit' => $ec['credit'] ?? ($ec['note_final'] >= 10 ? floor($ec['coef'] * 2) : 0),
                ':statut' => 'appliqué',
                ':commentaire' => $data['commentaire'] ?? 'Repêchage automatique',
                ':idUtilisateur' => $data['idUtilisateur'] ?? ($_SESSION['idUser'] ?? null)
            ];

            if ($exists) {
                // Mise à jour - utiliser exactement les mêmes paramètres que la requête
                $updateParams = [
                    ':idMoyenneUE' => $params[':idMoyenneUE'],
                    ':note_final' => $params[':note_final'],
                    ':point_jury' => $params[':point_jury'],
                    ':credit' => $params[':credit'],
                    ':statut' => $params[':statut'],
                    ':commentaire' => $params[':commentaire'],
                    ':idInscription' => $params[':idInscription'],
                    ':idUE' => $params[':idUE'],
                    ':idEC' => $params[':idEC'],
                    ':idSession' => $params[':idSession']
                ];
                $stmtUpdate->execute($updateParams);
            } else {
                // Insertion - utiliser tous les paramètres
                $stmtInsert->execute($params);
            }
            
            $nbEcRepêchés++;
        }

        // 6. Mettre à jour la moyenne dans la table moyenne_ue
        if ($nbEcRepêchés > 0) {
            $sqlUpdateMoyenne = "UPDATE moyenne_ue 
                                 SET moyenne = 10.00,
                                     date_calcul = NOW()
                                 WHERE id = :idMoyenneUE";
            $stmtUpdateMoyenne = $pdo->prepare($sqlUpdateMoyenne);
            $stmtUpdateMoyenne->execute([':idMoyenneUE' => $idMoyenneUE]);
        }

        return [
            'success' => true,
            'idMoyenneUE' => $idMoyenneUE,
            'nb_ec_repêchés' => $nbEcRepêchés,
            'message' => "Repêchage enregistré avec succès. $nbEcRepêchés EC modifiés."
        ];

    } catch (Exception $e) {
        error_log("Erreur dans enregistrerRepêchage: " . $e->getMessage());
        throw $e;
    }
}
/**
 * Prépare les données pour l'enregistrement du repêchage
 */
function préparerDonnéesRepêchage($pdo, $simulation, $idUE, $idSession, $seuil)
{
    // Récupérer l'inscription à partir du matricule
    $sqlInscription = "SELECT id FROM scolarite_inscription_pedagogique 
                      WHERE matricule = :matricule 
                      ORDER BY dateEnregistrement DESC LIMIT 1";

    $stmt = $pdo->prepare($sqlInscription);
    $stmt->execute([':matricule' => $simulation['matricule']]);
    $inscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inscription) {
        throw new Exception("Inscription non trouvée pour le matricule: " . $simulation['matricule']);
    }

    $idInscription = $inscription['id'];

    // Préparer les données pour chaque EC
    $ecRepêchés = [];

    foreach ($simulation['details_ec'] as $ec) {
        $pointJury = $ec['note_affichage'] - $ec['note_initial'];

        if ($pointJury > 0) { // Seulement les EC qui ont été augmentés
            $ecRepêchés[] = [
                'idEC' => $ec['id'],
                'note_initial' => $ec['note_initial'],
                'note_final' => $ec['note_affichage'],
                'point_jury' => $pointJury,
                'coef' => $ec['coef'],
                'credit' => $ec['note_affichage'] >= 10 ? ($ec['coef'] * 2) : 0 // Exemple: 2 crédits par coef si ≥ 10
            ];
        }
    }

    return [
        'idInscription' => $idInscription,
        'idUE' => $idUE,
        'idSession' => $idSession,
        'moyenne_initial' => $simulation['moyenne_avant'],
        'moyenne_final' => 10.00, // Toujours 10 après repêchage
        'nb_ec' => count($simulation['details_ec']),
        'seuil_repêchage' => $seuil,
        'ec_repêchés' => $ecRepêchés,
        'methode_calcul' => '40%_devoir_60%_examen',
        'commentaire' => sprintf("Repêchage à partir de %s/20. %d EC modifiés.", $seuil, count($ecRepêchés))
    ];
}
function getDeliberationDeUE($pdo, $idUE, $session_id = 1)
{
    $sql = "SELECT rh.*, ec.nom as nomEC, se.nom ,se.prenom, se.matricule FROM repêchage_historique rh
JOIN ec ON ec.id = rh.idEC
JOIN scolarite_inscription_pedagogique sip ON sip.id = rh.idInscription
JOIN scolarite_etudiants se ON se.matricule = sip.matricule
";
    $sql .= "WHERE rh.idUE = :idUE AND rh.idSession = :session_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':idUE' => $idUE, ':session_id' => $session_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// Fonction pour qui retourne pour chaque EC la note devoir, la note d'examen
// un EC peut avoir plusieurs, et certains etudiants peuvent être absents (non composés) lors certains devoirs ou examens
// On cherche le nombre de devoirs fait par EC pour pouvoir calculer la note finale EC en faisant la somme des devoirs sur le nombre de devoirs
// Si le nombre de devoirs est 0, alors la note d'examen sera 100% de la note finale EC
// Si le nombre de devoirs est superieur a 0, mais l'etudiant n'a aucune note de devoir, on ne calculera pas la note finale EC et on considérera que c'est une note non calculable (incomplet)
// Parcontre si l'etudiant a au moins une note de devoir, alors la note d'examen sera 60% et la note de devoir sera 40% de la note finale EC meme si il n'a pas fait tous les devoirs on fait somme qu'il a fait sur le nombre de devoirs pour calculer la note de devoir
// Si l'etudiant n'a pas de note d'examen, alors la note finale EC ne sera pas calculable et on considérera que c'est une note non calculable (incomplet)
// On veut savoir pour chaque étudiant, pour chaque EC de l'UE, le nombre de devoirs, la note de devoir (moyenne des devoirs), la note d'examen, et la note finale EC (calculée selon les règles ci-dessus)
