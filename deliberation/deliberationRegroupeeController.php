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
            $campagne = date('Y') . '-S' . (date('n') <= 6 ? '1' : '2');
            $idUser = $_SESSION['idUser'] ?? 0;
            $idSem = $data['idSemestre'] ?? null;

            try {
                $pdo->beginTransaction();

                // Enregistrer le repêchage
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
                $notesModifiees = 0;

                // Mettre à jour les notes
                foreach ($simulations as $simulation) {
                    foreach ($simulation['details_ec'] as $ec) {
                        if (isset($ec['note_affichage']) && $ec['note_affichage'] != $ec['note_initial']) {

                            if ($stmtUpdate->rowCount() > 0) {
                                $notesModifiees++;
                            }
                        }
                    }
                }

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
            $idSession = 1; // Session normale par défaut

            try {
                $pdo->beginTransaction();

                $ueTraitees = 0;
                $totalNotesModifiees = 0;
                $totalEtudiantsRepêches = 0;
                $resultats = [];

                foreach ($applications as $application) {
                    $idUE = $application['idUE'];
                    $simulations = $application['simulations'];
                    $intervalle = $application['intervalle'];
                    $barre = floatval($intervalle['min']);

                    // Récupérer l'ID du semestre pour l'UE
                    $sqlSemestre = "SELECT ue.id_semestre FROM ue WHERE ue.id = :idUe";
                    $stmtSemestre = $pdo->prepare($sqlSemestre);
                    $stmtSemestre->execute([':idUe' => $idUE]);
                    $semestreData = $stmtSemestre->fetch(PDO::FETCH_ASSOC);
                    $idSem = $semestreData['id_semestre'] ?? null;

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

                    // Traitement des simulations
                    $notesModifieesUE = 0;
                    $etudiantsRepêchesUE = 0;

                    // Préparer les requêtes pour éviter les doublons
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

                    foreach ($simulations as $simulation) {
                        $matricule = $simulation['matricule'];

                        // Récupérer l'inscription pédagogique
                        $sqlInscription = "SELECT id FROM scolarite_inscription_pedagogique 
                                  WHERE matricule = :matricule 
                                  ORDER BY dateEnregistrement DESC LIMIT 1";
                        $stmtInscription = $pdo->prepare($sqlInscription);
                        $stmtInscription->execute([':matricule' => $matricule]);
                        $inscription = $stmtInscription->fetch(PDO::FETCH_ASSOC);

                        if (!$inscription) {
                            error_log("Inscription non trouvée pour le matricule: " . $matricule);
                            continue;
                        }

                        $idInscription = $inscription['id'];
                        $etudiantNotesModifiees = 0;
                        $ecsRepêchés = [];

                        // D'abord, enregistrer la moyenne UE
                        $totalPoints = 0;
                        $totalCoefs = 0;
                        foreach ($simulation['details_ec'] as $ec) {
                            $note = floatval($ec['note_affichage'] ?? $ec['note_initial'] ?? 0);
                            $coef = floatval($ec['coef'] ?? 1);
                            $totalPoints += $note * $coef;
                            $totalCoefs += $coef;
                        }
                        $moyenneFinale = $totalCoefs > 0 ? $totalPoints / $totalCoefs : 10.00;

                        // Enregistrer la moyenne UE
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
                            ':idInscription' => $idInscription,
                            ':idUE' => $idUE,
                            ':idSession' => $idSession,
                            ':moyenne' => $moyenneFinale,
                            ':moyenne_initial' => $simulation['moyenne_avant'] ?? 0,
                            ':methode' => '40%_devoir_60%_examen',
                            ':nb_ec' => count($simulation['details_ec'])
                        ]);

                        // Récupérer l'ID de la moyenne UE
                        $idMoyenneUE = $pdo->lastInsertId();
                        if (!$idMoyenneUE || $idMoyenneUE == 0) {
                            $sqlGetId = "SELECT id FROM moyenne_ue 
                                WHERE idInscription = :idInscription 
                                AND idUE = :idUE 
                                AND idSession = :idSession";
                            $stmtGetId = $pdo->prepare($sqlGetId);
                            $stmtGetId->execute([
                                ':idInscription' => $idInscription,
                                ':idUE' => $idUE,
                                ':idSession' => $idSession
                            ]);
                            $result = $stmtGetId->fetch(PDO::FETCH_ASSOC);
                            $idMoyenneUE = $result['id'] ?? null;
                        }

                        if (!$idMoyenneUE) {
                            throw new Exception("Impossible de récupérer l'ID de la moyenne UE pour l'étudiant " . $matricule);
                        }

                        // Ensuite, traiter chaque EC
                        foreach ($simulation['details_ec'] as $ec) {
                            // Vérifier si la note doit être modifiée
                            $noteInitiale = floatval($ec['note_initial'] ?? 0);
                            $noteAffichage = floatval($ec['note_affichage'] ?? 0);

                            if ($noteAffichage > $noteInitiale) {
                                // Récupérer l'ID de l'EC
                                $sqlEcId = "SELECT id FROM ec WHERE id = :idEc OR (nom = :nomEc AND id_ue = :idUe) LIMIT 1";
                                $stmtEcId = $pdo->prepare($sqlEcId);
                                $stmtEcId->execute([
                                    ':idEc' => $ec['id'] ?? 0,
                                    ':nomEc' => $ec['name'],
                                    ':idUe' => $idUE
                                ]);
                                $ecData = $stmtEcId->fetch(PDO::FETCH_ASSOC);

                                if ($ecData) {
                                    $idEC = $ecData['id'];

                                    // Vérifier si l'enregistrement existe déjà
                                    $stmtCheck->execute([
                                        ':idInscription' => $idInscription,
                                        ':idUE' => $idUE,
                                        ':idEC' => $idEC,
                                        ':idSession' => $idSession
                                    ]);

                                    $exists = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                                    $params = [
                                        ':idInscription' => $idInscription,
                                        ':idUE' => $idUE,
                                        ':idEC' => $idEC,
                                        ':idSession' => $idSession,
                                        ':idMoyenneUE' => $idMoyenneUE,
                                        ':note_initial' => $noteInitiale,
                                        ':note_final' => $noteAffichage,
                                        ':point_jury' => $noteAffichage - $noteInitiale,
                                        ':coef' => floatval($ec['coef'] ?? 1),
                                        ':credit' => $noteAffichage >= 10 ? floor(floatval($ec['coef'] ?? 1) * 2) : 0,
                                        ':statut' => 'appliqué',
                                        ':commentaire' => sprintf("Repêchage global à partir de %s/20", $barre),
                                        ':idUtilisateur' => $idUser
                                    ];

                                    if ($exists) {
                                        // Mise à jour
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
                                        // Insertion
                                        $stmtInsert->execute($params);
                                    }

                                    $notesModifieesUE++;
                                    $etudiantNotesModifiees++;
                                } else {
                                    error_log("EC non trouvé pour le repêchage: " . ($ec['name'] ?? 'inconnu') . " dans l'UE ID: " . $idUE);
                                }
                            }
                        }

                        if ($etudiantNotesModifiees > 0) {
                            $etudiantsRepêchesUE++;
                        }
                    }

                    $ueTraitees++;
                    $totalNotesModifiees += $notesModifieesUE;
                    $totalEtudiantsRepêches += $etudiantsRepêchesUE;

                    $resultats[] = [
                        'idUE' => $idUE,
                        'idRepechage' => $idRepechage,
                        'notesModifiees' => $notesModifieesUE,
                        'etudiantsRepêches' => $etudiantsRepêchesUE,
                        'seuil' => $barre
                    ];
                }

                $pdo->commit();

                $tempsExecution = microtime(true) - ($_SERVER["REQUEST_TIME_FLOAT"] ?? microtime(true));

                echo json_encode([
                    'success' => true,
                    'message' => sprintf(
                        "Repêchage global appliqué avec succès. %d UE(s) traitées, %d étudiant(s) repêchés, %d note(s) modifiées.",
                        $ueTraitees,
                        $totalEtudiantsRepêches,
                        $totalNotesModifiees
                    ),
                    'stats' => [
                        'ueTraitees' => $ueTraitees,
                        'etudiantsRepêches' => $totalEtudiantsRepêches,
                        'notesModifiees' => $totalNotesModifiees,
                        'seuilGlobal' => $seuil,
                        'campagne' => $campagne,
                        'tempsExecution' => round($tempsExecution, 2) . 's'
                    ],
                    'details' => $resultats
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Erreur repêchage global: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de l\'application du repêchage global: ' . $e->getMessage()
                ]);
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
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Action not found']);
            break;
    }
} elseif ($getAction) {
    switch ($action) {
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
        case 'getEtudiantByUE':
            $idUE = $_GET['idUE'] ?? null;
            $session_id = $_GET['session_id'] ?? 1;
            if ($idUE) {
                // Inclure le fichier deliberationUeController.php ou dupliquer la fonction
                $etudiants = getEtudiantByUE($pdo, $idUE, $session_id);
                header('Content-Type: application/json');
                echo json_encode($etudiants);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'idUE parameter is required']);
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

        case 'simulerRepechage':
            $input = json_decode(file_get_contents('php://input'), true);

            if ($input) {
                $idUE = $input['idUE'] ?? null;
                $minMoy = floatval($input['minMoyenne'] ?? 8.0);
                $strategy = $input['strategy'] ?? 'neutral';
                $lockGE10 = $input['lock_ge10'] ?? true;
                $displayStep = floatval($input['rounding_step'] ?? 0.01);
                $etudiantsEligibles = $input['etudiantsEligibles'] ?? null;
            } else {
                $idUE = $_GET['idUE'] ?? null;
                $minMoy = floatval($_GET['minMoyenne'] ?? 8.0);
                $strategy = $_GET['strategy'] ?? 'neutral';
                $lockGE10 = $_GET['lock_ge10'] ?? false;
                $displayStep = floatval($_GET['rounding_step'] ?? 0.01);
                $etudiantsEligibles = null;
            }

            if ($idUE) {
                if ($etudiantsEligibles) {
                    $data = appliquerRepechageSurEtudiants($etudiantsEligibles, $minMoy, $strategy, $lockGE10, $displayStep);
                } else {
                    $data = appliquerRepechageUE($pdo, $idUE, $minMoy, $strategy, $lockGE10, $displayStep);
                }
                header('Content-Type: application/json');
                echo json_encode(["success" => true, "simulations" => $data]);
            } else {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID UE manquant"]);
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

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Action not found']);
            break;
    }
}

// Fonctions de base
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

// Fonctions utilitaires pour le repêchage
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

function appliquerRepechageSurEtudiants($etudiantsEligibles, $minMoyenne, $strategy = 'neutral', $lockGE10 = false, $displayStep = 0.01)
{
    $targetAvg = 10.0;
    $maxNote = 20.0;
    $simulations = [];

    foreach ($etudiantsEligibles as $etudiantData) {
        if (!isset($etudiantData['ec']) || !is_array($etudiantData['ec']) || count($etudiantData['ec']) === 0) {
            continue;
        }

        $totalPoints = 0;
        $totalCoefs = 0;

        foreach ($etudiantData['ec'] as $ec) {
            $note = floatval($ec['note'] ?? 0);
            $coef = floatval($ec['coef'] ?? 1);
            $totalPoints += ($note * $coef);
            $totalCoefs += $coef;
        }

        $avgBefore = $totalCoefs > 0 ? ($totalPoints / $totalCoefs) : 0;

        if ($avgBefore >= $minMoyenne && $avgBefore < $targetAvg) {
            $ecModifies = $etudiantData['ec'];

            foreach ($ecModifies as &$e) {
                $e["note_initial"] = $e["note"];
            }
            unset($e);

            $sumC = sumCoef($ecModifies);
            $pointsMissing = ($targetAvg - $avgBefore) * $sumC;

            redistributeContinuous($ecModifies, $pointsMissing, $strategy, $lockGE10, $maxNote);
            $fix = forceExactTargetByResidual($ecModifies, $targetAvg, $lockGE10, $maxNote);

            foreach ($ecModifies as &$e) {
                $e["note_affichage"] = number_format(displayRound($e["note"], $displayStep), 2);
            }
            unset($e);

            $simulations[] = [
                "matricule" => $etudiantData['matricule'],
                "nom" => $etudiantData['matricule'],
                "moyenne_avant" => round($avgBefore, 4),
                "moyenne_apres" => round(weightedAverage($ecModifies), 4),
                "info_fix" => $fix['reason'],
                "details_ec" => $ecModifies
            ];
        }
    }
    return $simulations;
}

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
function getStatistiquesCompletes($pdo, $ueId)
{
    // 1. Récupérer les moyennes UE de chaque étudiant
    $sqlMoyennes = "SELECT 
        sipu.matricule,
        SUM(pn.note * ec.coefficient) / SUM(ec.coefficient) as moyenne_ue,
        COUNT(DISTINCT pn.idNote) as nb_ec_composes
    FROM scolarite_inscription_pedagogique_ue sipu
    JOIN scolarite_inscription_pedagogique sip ON sipu.idInscriptionPedagogique = sip.id
    JOIN pedagogie_notes pn ON sip.id = pn.idInscription
    JOIN ec ON ec.id = pn.idEc
    WHERE pn.idUe = :ueId 
      AND sip.statut = 1 
      AND pn.non_compose = 0 
    GROUP BY sipu.matricule
    HAVING nb_ec_composes > 0";

    $stmtMoyennes = $pdo->prepare($sqlMoyennes);
    $stmtMoyennes->execute([':ueId' => $ueId]);
    $moyennesEtudiants = $stmtMoyennes->fetchAll(PDO::FETCH_ASSOC);

    // 2. Calculer les statistiques à partir des moyennes UE
    $effectif = count($moyennesEtudiants);
    $reussite = 0;
    $echec = 0;
    $totalMoyenne = 0;
    $minMoyenne = $effectif > 0 ? 20 : 0;
    $maxMoyenne = 0;

    // Tableaux pour les intervalles
    $intervalles = [
        'intervalle_0_7' => 0,
        'intervalle_7_8' => 0,
        'intervalle_8_9' => 0,
        'intervalle_9_10' => 0,
        'intervalle_10_20' => 0
    ];

    foreach ($moyennesEtudiants as $etudiant) {
        $moyenne = floatval($etudiant['moyenne_ue']);
        $totalMoyenne += $moyenne;

        // Min/Max
        if ($moyenne < $minMoyenne) $minMoyenne = $moyenne;
        if ($moyenne > $maxMoyenne) $maxMoyenne = $moyenne;

        // Réussite/Échec
        if ($moyenne >= 10) {
            $reussite++;
        } else {
            $echec++;
        }

        // Intervalles
        if ($moyenne < 7) {
            $intervalles['intervalle_0_7']++;
        } elseif ($moyenne < 8) {
            $intervalles['intervalle_7_8']++;
        } elseif ($moyenne < 9) {
            $intervalles['intervalle_8_9']++;
        } elseif ($moyenne < 10) {
            $intervalles['intervalle_9_10']++;
        } else {
            $intervalles['intervalle_10_20']++;
        }
    }

    // 3. Calculer les pourcentages et moyennes
    $tauxReussite = $effectif > 0 ? ($reussite / $effectif) * 100 : 0;
    $tauxEchec = $effectif > 0 ? ($echec / $effectif) * 100 : 0;
    $moyenneGenerale = $effectif > 0 ? $totalMoyenne / $effectif : 0;

    // 4. Récupérer le nombre total d'étudiants inscrits (même ceux qui n'ont pas composé)
    $sqlTotalInscrits = "SELECT COUNT(DISTINCT sipu.matricule) as total_inscrits
                        FROM scolarite_inscription_pedagogique_ue sipu
                        JOIN scolarite_inscription_pedagogique sip ON sipu.idInscriptionPedagogique = sip.id
                        WHERE sipu.idUE = :ueId AND sip.statut = 1";

    $stmtTotal = $pdo->prepare($sqlTotalInscrits);
    $stmtTotal->execute([':ueId' => $ueId]);
    $totalData = $stmtTotal->fetch(PDO::FETCH_ASSOC);
    $totalInscrits = intval($totalData['total_inscrits'] ?? 0);

    // 5. Retourner toutes les statistiques
    return array_merge([
        'effectif' => $effectif,
        'total_inscrits' => $totalInscrits,
        'reussite' => $reussite,
        'echec' => $echec,
        'tauxReussite' => round($tauxReussite, 2),
        'tauxEchec' => round($tauxEchec, 2),
        'moyenne' => round($moyenneGenerale, 2),
        'min' => round($minMoyenne, 2),
        'max' => round($maxMoyenne, 2),
        'non_composes' => $totalInscrits - $effectif  // Étudiants qui n'ont pas composé
    ], $intervalles);
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

    // Récupérer la liste des EC de l'UE pour référence
    $sqlEC = "SELECT DISTINCT ec.id, ec.nom 
              FROM ue_ec 
              JOIN ec ON ue_ec.id_ec = ec.id 
              WHERE ue_ec.id_ue = :idUE";
    $stmtEC = $pdo->prepare($sqlEC);
    $stmtEC->execute([':idUE' => $idUE]);
    $ecsUE = $stmtEC->fetchAll(PDO::FETCH_ASSOC);

    foreach ($etudiants as $etudiant) {
        $estComplet = true;
        $missingDetails = [];

        // Vérifier chaque EC pour cet étudiant
        foreach ($ecsUE as $ecUE) {
            $ecId = $ecUE['id'];
            $ecTrouve = false;
            $ecData = null;

            // Chercher l'EC dans les données de l'étudiant
            foreach ($etudiant['ec'] as $ecEtudiant) {
                if ($ecEtudiant['id'] == $ecId) {
                    $ecTrouve = true;
                    $ecData = $ecEtudiant;
                    break;
                }
            }

            if (!$ecTrouve) {
                // L'étudiant n'a aucune note pour cet EC
                $estComplet = false;
                $missingDetails[] = [
                    'ec_id' => $ecId,
                    'ec_name' => $ecUE['nom'],
                    'reason' => 'aucune_note',
                    'details' => 'Aucune note trouvée pour cet EC'
                ];
            } else {
                // Vérifier si l'EC a une note finale calculable
                if ($ecData['note_finale_ec'] === null || $ecData['note_finale_ec'] == 0) {
                    $estComplet = false;
                    $missingDetails[] = [
                        'ec_id' => $ecId,
                        'ec_name' => $ecUE['nom'],
                        'reason' => 'note_non_calculable',
                        'details' => 'Note finale EC non calculable',
                        'note_devoir' => $ecData['note_devoir'],
                        'note_examen' => $ecData['note_examen'],
                        'calcul_mode' => $ecData['calcul_mode'] ?? 'inconnu'
                    ];
                } else {
                    // Vérifier la complétude selon la règle 40%/60%
                    if ($ecData['calcul_mode'] === '100_devoir') {
                        // Seulement devoirs : acceptable mais à noter
                        $missingDetails[] = [
                            'ec_id' => $ecId,
                            'ec_name' => $ecUE['nom'],
                            'reason' => 'pas_examen',
                            'details' => 'Pas de note d\'examen, 100% basé sur devoirs',
                            'note_devoir' => $ecData['note_devoir'],
                            'note_examen' => null,
                            'calcul_mode' => $ecData['calcul_mode']
                        ];
                        // Note: Ce n'est pas une incomplétude bloquante
                    } elseif ($ecData['calcul_mode'] === '100_examen') {
                        // Seulement examen : acceptable mais à noter
                        $missingDetails[] = [
                            'ec_id' => $ecId,
                            'ec_name' => $ecUE['nom'],
                            'reason' => 'pas_devoir',
                            'details' => 'Pas de note de devoir, 100% basé sur examen',
                            'note_devoir' => null,
                            'note_examen' => $ecData['note_examen'],
                            'calcul_mode' => $ecData['calcul_mode']
                        ];
                        // Note: Ce n'est pas une incomplétude bloquante
                    } elseif ($ecData['calcul_mode'] === '40_60') {
                        // Parfait : a les deux types de notes
                        // Pas d'action nécessaire
                    } elseif ($ecData['calcul_mode'] === 'aucune') {
                        $estComplet = false;
                        $missingDetails[] = [
                            'ec_id' => $ecId,
                            'ec_name' => $ecUE['nom'],
                            'reason' => 'aucune_note_ec',
                            'details' => 'Aucune note pour cet EC',
                            'calcul_mode' => $ecData['calcul_mode']
                        ];
                    }
                }
            }
        }

        // Vérifier si l'étudiant a une moyenne UE calculée
        if (!isset($etudiant['moyenne_ue']) || $etudiant['moyenne_ue'] == 0) {
            $estComplet = false;
            $missingDetails[] = [
                'ec_id' => null,
                'ec_name' => 'Moyenne UE',
                'reason' => 'moyenne_non_calculable',
                'details' => 'Moyenne UE non calculable'
            ];
        }

        // Filtrer les détails pour ne garder que les vraies incomplétudes
        $incompletudesBloquantes = array_filter($missingDetails, function ($detail) {
            return in_array($detail['reason'], ['aucune_note', 'note_non_calculable', 'aucune_note_ec', 'moyenne_non_calculable']);
        });

        if (empty($incompletudesBloquantes)) {
            $stats['etudiants_complets']++;
            $completudeStatus = 'complet';
        } else {
            $stats['etudiants_incomplets']++;
            $completudeStatus = 'incomplet';

            // Ajouter aux listes
            $stats['liste_etudiants_incomplets'][] = [
                'matricule' => $etudiant['matricule'],
                'nom' => $etudiant['prenom'] . ' ' . $etudiant['nom'],
                'moyenne_ue' => $etudiant['moyenne_ue'] ?? 0,
                'missing_evaluations' => $incompletudesBloquantes
            ];

            // Compter les raisons
            foreach ($incompletudesBloquantes as $missing) {
                if (!isset($stats['raisons_incompletude'][$missing['reason']])) {
                    $stats['raisons_incompletude'][$missing['reason']] = 0;
                }
                $stats['raisons_incompletude'][$missing['reason']]++;
            }
        }

        // Stocker les détails de complétude pour chaque étudiant
        $stats['details_completude'][] = [
            'matricule' => $etudiant['matricule'],
            'nom' => $etudiant['prenom'] . ' ' . $etudiant['nom'],
            'status' => $completudeStatus,
            'moyenne_ue' => $etudiant['moyenne_ue'] ?? 0,
            'nb_ec' => count($etudiant['ec']),
            'all_details' => $missingDetails,
            'incompletudes_bloquantes' => $incompletudesBloquantes
        ];
    }

    // Calculer les pourcentages
    $stats['pourcentage_complets'] = $stats['total_etudiants'] > 0
        ? round(($stats['etudiants_complets'] / $stats['total_etudiants']) * 100, 2)
        : 0;

    $stats['pourcentage_incomplets'] = $stats['total_etudiants'] > 0
        ? round(($stats['etudiants_incomplets'] / $stats['total_etudiants']) * 100, 2)
        : 0;

    // Ajouter des statistiques supplémentaires
    $stats['statistiques_supplementaires'] = [
        'nb_ec_total' => count($ecsUE),
        'ecs_list' => array_map(function ($ec) {
            return ['id' => $ec['id'], 'nom' => $ec['nom']];
        }, $ecsUE),
        'moyenne_generale' => $stats['total_etudiants'] > 0
            ? array_sum(array_column(array_column($stats['details_completude'], 'moyenne_ue'), 'moyenne_ue')) / $stats['total_etudiants']
            : 0
    ];
    $stats['noteEtudiantsParEC'] = $etudiants; // Ajouter les notes des étudiants par EC pour analyse détaillée
    

    return $stats;
}
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

        // 2. Enregistrer la moyenne UE
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

        // 3. Mettre à jour ou insérer chaque EC repêché
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

        foreach ($data['ec_repêchés'] as $ec) {
            // Validation des données EC
            if (!isset($ec['idEC'], $ec['note_initial'], $ec['note_final'], $ec['point_jury'], $ec['coef'])) {
                error_log("EC repêché invalide : " . json_encode($ec));
                continue;
            }

            // Vérifier si l'enregistrement existe déjà
            $stmtCheck->execute([
                ':idInscription' => $data['idInscription'],
                ':idUE' => $data['idUE'],
                ':idEC' => $ec['idEC'],
                ':idSession' => $data['idSession']
            ]);

            $exists = $stmtCheck->fetch(PDO::FETCH_ASSOC);

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
                ':credit' => $ec['credit'] ?? ($ec['note_final'] >= 10 ? ($ec['coef'] * 2) : 0),
                ':statut' => 'appliqué',
                ':commentaire' => $data['commentaire'] ?? 'Repêchage automatique',
                ':idUtilisateur' => $data['idUtilisateur'] ?? ($_SESSION['idUser'] ?? null)
            ];

            if ($exists) {
                // Mise à jour
                $stmtUpdate->execute($params);
            } else {
                // Insertion
                $stmtInsert->execute($params);
            }

            $nbEcRepêchés++;
        }

        // 4. Mettre à jour les notes dans pedagogie_notes
        $sqlUpdateNote = "UPDATE pedagogie_notes pn
                         JOIN ec ON ec.id = pn.idEc
                         SET pn.note = :note_final
                         WHERE pn.idInscription = :idInscription
                         AND pn.idUe = :idUE
                         AND pn.idEc = :idEC
                         AND pn.session_id = :idSession";
        $stmtUpdateNote = $pdo->prepare($sqlUpdateNote);

        foreach ($data['ec_repêchés'] as $ec) {
            $stmtUpdateNote->execute([
                ':note_final' => $ec['note_final'],
                ':idInscription' => $data['idInscription'],
                ':idUE' => $data['idUE'],
                ':idEC' => $ec['idEC'],
                ':idSession' => $data['idSession']
            ]);
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
