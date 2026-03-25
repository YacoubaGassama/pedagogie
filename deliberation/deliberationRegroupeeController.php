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
            if (!$input || !isset($input['idUE']) || !isset($input['simulations']) || !isset($input['intervalle'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Données invalides']);
                break;
            }

            $idUE        = $input['idUE'];
            $simulations = $input['simulations'];
            $barre       = floatval($input['intervalle']['min']);
            $campagne    = date('Y') . '-S' . (date('n') <= 6 ? '1' : '2');
            $idUser      = $_SESSION['idUser'] ?? 0;
            $idSession   = 1;

            try {
                $pdo->beginTransaction();

                // Récupérer idAnnee et idSem
                $idAnnee = $pdo->query("SELECT MAX(id) FROM scolarite_anneeuniversitaire")->fetchColumn();
                if (!$idAnnee) throw new Exception("Aucune année universitaire active");

                $stmtSem = $pdo->prepare("SELECT id_semestre FROM ue WHERE id = :idUE");
                $stmtSem->execute([':idUE' => $idUE]);
                $idSem = $stmtSem->fetchColumn() ?: null;

                // 1. Annuler les historiques liés aux repêchages actifs
                $pdo->prepare("
                    UPDATE repechage_historique rh
                    JOIN repechage rep ON rep.idRepechage = rh.idRepechage
                    SET rh.statut = 'annule'
                    WHERE rep.idUe = :idUE AND rep.statut = 1 AND rh.statut = 'applique'
                ")->execute([':idUE' => $idUE]);

                // 2. Désactiver les repêchages précédents
                $pdo->prepare("UPDATE repechage SET statut = 0 WHERE idUe = :idUE AND statut = 1")
                    ->execute([':idUE' => $idUE]);

                // 3. Insérer le nouveau repêchage
                $stmtRep = $pdo->prepare("
                    INSERT INTO repechage (idUe, idSem, idAnnee, barre, strategeDeCalcul, pasArrondi, lockIfNoteSup10, campagne, idUser, dateCreation, statut)
                    VALUES (:idUe, :idSem, :idAnnee, :barre, :strategy, :rounding_step, :lock_ge10, :campagne, :idUser, NOW(), 1)
                ");
                $stmtRep->execute([
                    ':idUe'          => $idUE,
                    ':idSem'         => $idSem,
                    ':idAnnee'       => $idAnnee,
                    ':barre'         => $barre,
                    ':strategy'      => $input['strategy']      ?? 'neutral',
                    ':rounding_step' => floatval($input['rounding_step'] ?? 0.01),
                    ':lock_ge10'     => $input['lock_ge10']      ?? false,
                    ':campagne'      => $campagne,
                    ':idUser'        => $idUser
                ]);
                $idRepechage = $pdo->lastInsertId();

                // 4. Enregistrer chaque simulation
                $notesModifiees   = 0;
                $etudiantsTraites = 0;

                foreach ($simulations as $simulation) {
                    // Toute exception remonte au try/catch global → rollback automatique
                    $dataRepechage                  = préparerDonnéesRepêchage($pdo, $simulation, $idUE, $idSession, $barre);
                    $dataRepechage['idUtilisateur'] = $idUser;
                    $dataRepechage['idRepechage']   = $idRepechage;
                    $dataRepechage['commentaire']   = sprintf("Repêchage à partir de %s/20", $barre);

                    $resultat = enregistrerRepêchage($pdo, $dataRepechage);

                    if (isset($resultat['nb_ec_repeches'])) {
                        $notesModifiees += $resultat['nb_ec_repeches'];
                    }
                    $etudiantsTraites++;
                }

                // 5. Sync vue
                $syncResult = syncVueEtudiantsParUE($pdo, $idUE, true, false);
                if (!$syncResult['success']) {
                    throw new Exception("Erreur sync UE $idUE : " . $syncResult['message']);
                }

                $pdo->commit();

                echo json_encode([
                    'success'     => true,
                    'message'     => "Repêchage appliqué. $notesModifiees note(s) modifiée(s) pour $etudiantsTraites étudiant(s).",
                    'idRepechage' => $idRepechage,
                    'sync'        => [
                        'rows'         => $syncResult['rows']         ?? 0,
                        'sync_version' => $syncResult['sync_version'] ?? null
                    ]
                ]);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
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
        case 'appliquerRepechageGlobal':
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input || !isset($input['applications'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Données invalides']);
                break;
            }

            $applications = $input['applications'];
            $campagne     = date('Y') . '-S' . (date('n') <= 6 ? '1' : '2');
            $idUser       = $_SESSION['idUser'] ?? 0;
            $idSession    = 1;

            // Récupérer l'année une seule fois
            $idAnnee = $pdo->query("SELECT MAX(id) FROM scolarite_anneeuniversitaire")->fetchColumn();
            if (!$idAnnee) {
                echo json_encode(['success' => false, 'message' => 'Aucune année universitaire active']);
                break;
            }

            $ueTraitees             = 0;
            $totalNotesModifiees    = 0;
            $totalEtudiantsRepeches = 0;
            $resultats              = [];
            $erreurs                = [];

            foreach ($applications as $application) {
                $idUE        = $application['idUE'];
                $simulations = $application['simulations'];
                $barre       = floatval($application['intervalle']['min'] ?? $application['seuil'] ?? 0);
// ── Aucun étudiant éligible → insérer repechage vide sans sync ────
if (empty($simulations)) {
    try {
        $stmtSem = $pdo->prepare("SELECT id_semestre FROM ue WHERE id = :idUe");
        $stmtSem->execute([':idUe' => $idUE]);
        $idSem = $stmtSem->fetchColumn() ?: null;

        $stmtDesact = $pdo->prepare("UPDATE repechage SET statut = 0 WHERE idUe = :idUE AND statut = 1");
        $stmtDesact->execute([':idUE' => $idUE]);

        $stmtRep = $pdo->prepare("
            INSERT INTO repechage (idUe, idSem, idAnnee, barre, strategeDeCalcul, pasArrondi, lockIfNoteSup10, campagne, idUser, dateCreation, statut)
            VALUES (:idUe, :idSem, :idAnnee, :barre, :strategy, :rounding_step, :lock_ge10, :campagne, :idUser, NOW(), 1)
        ");
        $stmtRep->execute([
            ':idUe'          => $idUE,
            ':idSem'         => $idSem,
            ':idAnnee'       => $idAnnee,
            ':barre'         => $barre,
            ':strategy'      => $application['strategy']      ?? 'neutral',
            ':rounding_step' => floatval($application['rounding'] ?? 0.01),
            ':lock_ge10'     => $application['lockGe10']      ?? false,
            ':campagne'      => $campagne,
            ':idUser'        => $idUser
        ]);
        $idRepechage = $pdo->lastInsertId();

        // Sync quand même pour mettre à jour la vue
        $syncResult = syncVueEtudiantsParUE($pdo, $idUE, false, true);

        $ueTraitees++;
        $resultats[] = [
            'idUE'              => $idUE,
            'idRepechage'       => $idRepechage,
            'notesModifiees'    => 0,
            'etudiantsRepeches' => 0,
            'seuil'             => $barre,
            'sync_version'      => $syncResult['sync_version'] ?? null,
            'message'           => 'Aucun étudiant éligible — délibération enregistrée sans repêchage'
        ];
    } catch (Exception $e) {
        error_log("Erreur UE $idUE (vide) : " . $e->getMessage());
        $erreurs[] = "UE $idUE : " . $e->getMessage();
    }
    continue;
}
                try {
                    $pdo->beginTransaction();

                    // Semestre de l'UE
                    $stmtSem = $pdo->prepare("SELECT id_semestre FROM ue WHERE id = :idUe");
                    $stmtSem->execute([':idUe' => $idUE]);
                    $idSem = $stmtSem->fetchColumn() ?: null;

                    // 1. Annuler les historiques liés aux repêchages actifs
                    $stmtAnnulerReph = $pdo->prepare("
                UPDATE repechage_historique rh
                JOIN repechage rep ON rep.idRepechage = rh.idRepechage
                SET rh.statut = 'annule'
                WHERE rep.idUe   = :idUE
                  AND rep.statut = 1
                  AND rh.statut  = 'applique'
            ");
                    $stmtAnnulerReph->execute([':idUE' => $idUE]);

                    // 2. Désactiver les repêchages précédents
                    $stmtDesact = $pdo->prepare("UPDATE repechage SET statut = 0 WHERE idUe = :idUE AND statut = 1");
                    $stmtDesact->execute([':idUE' => $idUE]);

                    // 3. Insérer le nouveau repêchage
                    $stmtRep = $pdo->prepare("
                INSERT INTO repechage (idUe, idSem, idAnnee, barre, strategeDeCalcul, pasArrondi, lockIfNoteSup10, campagne, idUser, dateCreation, statut)
                VALUES (:idUe, :idSem, :idAnnee, :barre, :strategy, :rounding_step, :lock_ge10, :campagne, :idUser, NOW(), 1)
            ");
                    $stmtRep->execute([
                        ':idUe'          => $idUE,
                        ':idSem'         => $idSem,
                        ':idAnnee'       => $idAnnee,
                        ':barre'         => $barre,
                        ':strategy'      => $application['strategy']      ?? 'neutral',
                        ':rounding_step' => floatval($application['rounding'] ?? 0.01),
                        ':lock_ge10'     => $application['lockGe10']      ?? false,
                        ':campagne'      => $campagne,
                        ':idUser'        => $idUser
                    ]);
                    $idRepechage = $pdo->lastInsertId();

                    // 4. Enregistrer chaque simulation
                    $notesModifieesUE    = 0;
                    $etudiantsRepechesUE = 0;

                    foreach ($simulations as $simulation) {
                        // Toute exception remonte au try/catch de l'UE → rollback de cette UE
                        $dataRepechage                  = préparerDonnéesRepêchage($pdo, $simulation, $idUE, $idSession, $barre);
                        $dataRepechage['idUtilisateur'] = $idUser;
                        $dataRepechage['idRepechage']   = $idRepechage;
                        $dataRepechage['commentaire']   = sprintf("Repêchage global à partir de %s/20", $barre);

                        $resultat = enregistrerRepêchage($pdo, $dataRepechage);

                        if (isset($resultat['nb_ec_repeches']) && $resultat['nb_ec_repeches'] > 0) {
                            $notesModifieesUE    += $resultat['nb_ec_repeches'];
                            $etudiantsRepechesUE++;
                        }
                    }

                    // 5. Sync vue — existRepechage = true car on vient de répêcher
                    $syncResult = syncVueEtudiantsParUE($pdo, $idUE, true, false);

                    if (!$syncResult['success']) {
                        throw new Exception("Erreur sync UE $idUE : " . $syncResult['message']);
                    }

                    $pdo->commit();

                    $ueTraitees++;
                    $totalNotesModifiees    += $notesModifieesUE;
                    $totalEtudiantsRepeches += $etudiantsRepechesUE;

                    $resultats[] = [
                        'idUE'              => $idUE,
                        'idRepechage'       => $idRepechage,
                        'notesModifiees'    => $notesModifieesUE,
                        'etudiantsRepeches' => $etudiantsRepechesUE,
                        'seuil'             => $barre,
                        'sync_version'      => $syncResult['sync_version'] ?? null
                    ];
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log("Erreur UE $idUE : " . $e->getMessage());
                    $erreurs[] = "UE $idUE : " . $e->getMessage();
                }
            }

            $tempsExecution = round(microtime(true) - ($_SERVER["REQUEST_TIME_FLOAT"] ?? microtime(true)), 2);

            echo json_encode([
                'success' => count($erreurs) === 0 || $ueTraitees > 0,
                'message' => sprintf(
                    "%d UE(s) traitées, %d étudiant(s) repêchés, %d note(s) modifiées.",
                    $ueTraitees,
                    $totalEtudiantsRepeches,
                    $totalNotesModifiees
                ),
                'stats' => [
                    'ueTraitees'        => $ueTraitees,
                    'etudiantsRepeches' => $totalEtudiantsRepeches,
                    'notesModifiees'    => $totalNotesModifiees,
                    'campagne'          => $campagne,
                    'tempsExecution'    => $tempsExecution . 's'
                ],
                'details' => $resultats,
                'erreurs' => $erreurs
            ]);
            break;
        case 'delibererSansRepechage':
            $data = json_decode(file_get_contents('php://input'), true);

            if (
                !$data ||
                !isset($data['idUEs']) ||
                !is_array($data['idUEs']) ||
                count($data['idUEs']) === 0
            ) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => "Liste d'UEs manquante ou invalide"
                ]);
                break;
            }

            $idUEs     = array_map('intval', $data['idUEs']);
            $idUser    = $_SESSION['idUser'] ?? 0;
            $campagne  = date('Y') . '-S' . (date('n') <= 6 ? '1' : '2');
            $resultats = [];
            $erreurs   = [];

            $idAnnee = $pdo->query("SELECT MAX(id) FROM scolarite_anneeuniversitaire")->fetchColumn();

            if (!$idAnnee) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Aucune année universitaire active'
                ]);
                break;
            }

            foreach ($idUEs as $idUE) {
                $maxRetries = 3;
                $attempt = 0;
                $ok = false;

                while ($attempt < $maxRetries && !$ok) {
                    try {
                        $attempt++;

                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        $pdo->beginTransaction();

                        // 0. Récupérer le semestre de l’UE
                        $stmtSem = $pdo->prepare("
                    SELECT id_semestre
                    FROM ue
                    WHERE id = :idUE
                ");
                        $stmtSem->execute([':idUE' => $idUE]);
                        $idSem = $stmtSem->fetchColumn() ?: null;

                        // 1. Annuler les historiques liés aux repêchages actifs
                        $stmtAnnulerReph = $pdo->prepare("
                    UPDATE repechage_historique rh
                    JOIN repechage rep ON rep.idRepechage = rh.idRepechage
                    SET rh.statut = 'annule'
                    WHERE rep.idUe   = :idUE
                      AND rep.statut = 1
                      AND rh.statut  = 'applique'
                ");
                        $stmtAnnulerReph->execute([':idUE' => $idUE]);

                        // 2. Désactiver les délibérations/repêchages précédents
                        $stmtDesact = $pdo->prepare("
                    UPDATE repechage
                    SET statut = 0
                    WHERE idUe = :idUE
                      AND statut = 1
                ");
                        $stmtDesact->execute([':idUE' => $idUE]);

                        // 3. Insérer la nouvelle délibération sans repêchage
                        $stmtRep = $pdo->prepare("
                    INSERT INTO repechage (
                        idUe, idSem, idAnnee, barre, strategeDeCalcul,
                        pasArrondi, lockIfNoteSup10, campagne, idUser,
                        dateCreation, statut
                    )
                    VALUES (
                        :idUe, :idSem, :idAnnee, NULL, 'sans_repechage',
                        NULL, 0, :campagne, :idUser, NOW(), 1
                    )
                ");
                        $stmtRep->execute([
                            ':idUe'     => $idUE,
                            ':idSem'    => $idSem,
                            ':idAnnee'  => $idAnnee,
                            ':campagne' => $campagne,
                            ':idUser'   => $idUser
                        ]);

                        // Commit AVANT la sync pour éviter les deadlocks
                        $pdo->commit();

                        // 4. Sync hors transaction
                        $syncResult = syncVueEtudiantsParUE($pdo, (int)$idUE, false, true);

                        if (!$syncResult['success']) {
                            throw new Exception($syncResult['message']);
                        }

                        $resultats[] = [
                            'idUE'         => $idUE,
                            'success'      => true,
                            'rows'         => $syncResult['rows'] ?? 0,
                            'sync_version' => $syncResult['sync_version'] ?? null,
                            'message'      => $syncResult['message'] ?? 'Synchronisation effectuée'
                        ];

                        $ok = true;
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        $message = $e->getMessage();
                        $isDeadlock =
                            strpos($message, '1213') !== false ||
                            strpos($message, '40001') !== false ||
                            stripos($message, 'Deadlock found') !== false ||
                            stripos($message, 'Serialization failure') !== false;

                        if ($isDeadlock && $attempt < $maxRetries) {
                            usleep(200000 * $attempt);
                            continue;
                        }

                        $erreurs[] = [
                            'idUE' => $idUE,
                            'success' => false,
                            'message' => $message
                        ];

                        break;
                    }
                }
            }

            echo json_encode([
                'success'   => count($erreurs) === 0,
                'message'   => count($erreurs) === 0
                    ? count($idUEs) . " UE(s) délibérée(s) avec succès."
                    : count($erreurs) . " erreur(s) sur " . count($idUEs) . " UE(s).",
                'resultats' => $resultats,
                'erreurs'   => $erreurs
            ]);
            break;
        case 'simulerRepechage':
            if (!$input) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Corps JSON requis']);
                break;
            }

            if ($input) {
        $idUE = isset($input['idUE']) ? (int)$input['idUE'] : null;
        $minMoy = isset($input['minMoyenne']) ? (float)$input['minMoyenne'] : 8.0;
        $strategy = $input['strategy'] ?? 'neutral';
        $lockGE10 = filter_var($input['lock_ge10'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $displayStep = isset($input['rounding_step']) ? (float)$input['rounding_step'] : 0.01;
        $etudiantsEligibles = $input['etudiantsEligibles'] ?? null;
    } else {
        $idUE = isset($_GET['idUE']) ? (int)$_GET['idUE'] : null;
        $minMoy = isset($_GET['minMoyenne']) ? (float)$_GET['minMoyenne'] : 8.0;
        $strategy = $_GET['strategy'] ?? 'neutral';
        $lockGE10 = filter_var($_GET['lock_ge10'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $displayStep = isset($_GET['rounding_step']) ? (float)$_GET['rounding_step'] : 0.01;
        $etudiantsEligibles = null;
    }
            if (!$idUE) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID UE manquant']);
                break;
            }

            try {
        if ($etudiantsEligibles && is_array($etudiantsEligibles)) {
            $data = appliquerRepechageSurEtudiants(
                $etudiantsEligibles,
                $minMoy,
                $strategy,
                $lockGE10,
                $displayStep
            );
        } else {
            $data = appliquerRepechageUE(
                $pdo,
                $idUE,
                $minMoy,
                $strategy,
                $lockGE10,
                $displayStep
            );
        }

        header('Content-Type: application/json');
        echo json_encode([
            "success" => true,
            "simulations" => $data,
            "params" => [
                "idUE" => $idUE,
                "minMoyenne" => $minMoy,
                "strategy" => $strategy,
                "lock_ge10" => $lockGE10,
                "rounding_step" => $displayStep
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => $e->getMessage()
        ]);
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

        case 'getStatistiquesUE':
            $idUE = $_GET['idUE'] ?? null;

            if (!$idUE) {
                echo json_encode(['success' => false, 'message' => 'ID UE manquant']);
                break;
            }

            $verification = verifierCompletudeEvaluationsUE($pdo, $idUE);
            if ($verification['total_etudiants'] == 0) {
                echo json_encode(['success' => false, 'message' => 'Veuillez saisir les notes !', 'verification' => $verification]);
                break;
            }
            if ($verification && $verification['etudiants_incomplets'] > 0) {
                echo json_encode(['success' => false, 'message' => $verification['raisons_incompletude'], 'verification' => $verification]);
                break;
            }

            $stats = getStatistiquesCompletes($pdo, $idUE);
            echo json_encode(['success' => true, 'stats' => $stats, 'verification' => $verification]);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Action not found GET']);
            break;
    }
}

// Fonctions de base
function préparerDonnéesRepêchage($pdo, $simulation, $idUE, $idSession, $seuil)
{
    $stmt = $pdo->prepare("SELECT id FROM scolarite_inscription_pedagogique WHERE matricule = :matricule ORDER BY dateEnregistrement DESC LIMIT 1");
    $stmt->execute([':matricule' => $simulation['matricule']]);
    $inscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inscription) {
        throw new Exception("Inscription non trouvée pour le matricule: " . $simulation['matricule']);
    }

    $idInscription = $inscription['id'];
    $ecRepêchés    = [];

    foreach ($simulation['details_ec'] as $ec) {
        $pointJury = floatval($ec['note_affichage']) - floatval($ec['note_initial']);
        
            $ecRepeches[] = [
                'idEC'         => $ec['id'],
                'note_initial' => floatval($ec['note_initial']),
                'note_final'   => floatval($ec['note_affichage']),
                'point_jury'   => $pointJury,
                'coef'         => floatval($ec['coef'] ?? 1),
                'credit'       => floatval($ec['note_affichage']) >= 10 ? (floatval($ec['coef'] ?? 1) * 2) : 0
            ];
    }

    return [
        'idInscription'  => $idInscription,
        'idUE'           => $idUE,
        'idSession'      => $idSession,
        'moyenne_initial' => floatval($simulation['moyenne_avant'] ?? 0),
        'moyenne_final'  => 10.00,
        'nb_ec'          => count($simulation['details_ec']),
        'seuil_repêchage' => $seuil,
        'ec_repeches' => $ecRepeches,
        'methode_calcul' => 'note_directe',
        'commentaire'    => sprintf("Repêchage à partir de %s/20. %d EC modifiés.", $seuil, count($ecRepêchés))
    ];
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
        $aExamenNonCompose = false;
        $moyenneCalculable = true;

        foreach ($etudiantData['ec'] as $ec) {
            $note = (float)($ec['note'] ?? $ec['note_finale_ec'] ?? 0);
            $coef = (float)($ec['coef'] ?? $ec['coef_ec'] ?? 1);

            $totalPoints += ($note * $coef);
            $totalCoefs += $coef;

           if (($ec['calcul_mode'] ?? '') === 'examen_non_compose') {
                $aExamenNonCompose = true;
                break;
            }
            if (
                array_key_exists('note_finale_ec', $ec)
                && $ec['note_finale_ec'] === null
                && ($ec['calcul_mode'] ?? '') !== 'examen_non_compose'
            ) {
                $moyenneCalculable = false;
            }
        }

        $avgBefore = $totalCoefs > 0 ? ($totalPoints / $totalCoefs) : 0;

        $estRepechable = (
            $moyenneCalculable
            && $avgBefore >= $minMoyenne
            && $avgBefore < $targetAvg
            && !$aExamenNonCompose
        );

        if (!$estRepechable) {
            continue;
        }

        $ecModifies = [];

        foreach ($etudiantData['ec'] as $ec) {
            $noteCourante = (float)($ec['note'] ?? $ec['note_finale_ec'] ?? 0);
            $coefCourant = (float)($ec['coef'] ?? $ec['coef_ec'] ?? 1);

            $ecModifies[] = [
                "id" => $ec["id"],
                "name" => $ec["name"] ?? '',
                "coef" => $coefCourant,
                "note" => $noteCourante,
                "note_initial" => $noteCourante,
                "note_devoir" => $ec["note_devoir"] ?? null,
                "note_examen" => $ec["note_examen"] ?? null,
                "devoirs" => $ec["devoirs"] ?? [],
                "examens" => $ec["examens"] ?? []
            ];
        }

        $sumC = sumCoef($ecModifies);
        $pointsMissing = ($targetAvg - $avgBefore) * $sumC;

        redistributeContinuous($ecModifies, $pointsMissing, $strategy, $lockGE10, $maxNote);
        $fix = forceExactTargetByResidual($ecModifies, $targetAvg, $lockGE10, $maxNote);

        foreach ($ecModifies as &$e) {
            $e["note_affichage"] = number_format(displayRound($e["note"], $displayStep), 2);
            $e["point_jury"] = round($e["note"] - $e["note_initial"], 2);
            $e["nouvelle_note_finale"] = round($e["note"], 2);
        }
        unset($e);

        $simulations[] = [
            "matricule" => $etudiantData['matricule'],
            "nom" => trim(($etudiantData['prenom'] ?? '') . ' ' . ($etudiantData['nom'] ?? '')),
            "moyenne_avant" => round($avgBefore, 4),
            "moyenne_apres" => round(weightedAverage($ecModifies), 4),
            "info_fix" => $fix['reason'] ?? null,
            "details_ec" => $ecModifies,
            "aExamenNonCompose" => $aExamenNonCompose
        ];
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
        $avgBefore = (float)($etudiant['moyenne_ue'] ?? 0);
        $moyenneCalculable = (bool)($etudiant['moyenne_calculable'] ?? false);

        $aExamenNonCompose = false;
        foreach (($etudiant['ec'] ?? []) as $ec) {
            if ($ec['examen_non_compose']) {
                $aExamenNonCompose = true;
                break;
            }
        }

        $estRepechable = (
            $moyenneCalculable
            && $avgBefore >= $minMoyenne
            && $avgBefore < $targetAvg
            && !$aExamenNonCompose
        );

        if (!$estRepechable) {
            continue;
        }

        $ecModifies = [];

        foreach ($etudiant['ec'] as $ec) {
            $noteFinaleEc = (float)($ec["note_finale_ec"] ?? 0);

            $ecModifies[] = [
                "id" => $ec["id"],
                "name" => $ec["name"],
                "coef" => (float)$ec["coef_ec"],
                "note" => $noteFinaleEc,
                "note_initial" => $noteFinaleEc,
                "note_devoir" => $ec["note_devoir"] ?? null,
                "note_examen" => $ec["note_examen"] ?? null,
                "devoirs" => $ec["devoirs"] ?? [],
                "examens" => $ec["examens"] ?? []
            ];
        }

        $sumC = sumCoef($ecModifies);
        $pointsMissing = ($targetAvg - $avgBefore) * $sumC;

        redistributeContinuous($ecModifies, $pointsMissing, $strategy, $lockGE10, $maxNote);
        $fix = forceExactTargetByResidual($ecModifies, $targetAvg, $lockGE10, $maxNote);

        foreach ($ecModifies as &$e) {
            $e["note_affichage"] = number_format(displayRound($e["note"], $displayStep), 2);
            $e["nouvelle_note_finale"] = round($e["note"], 2);
            $e["point_jury"] = round($e["note"] - $e["note_initial"], 2);

            if ($e["note_devoir"] !== null && $e["note_examen"] !== null) {
                $augmentation = $e["note"] - $e["note_initial"];
                $augmentationDevoir = $augmentation * 0.4;
                $augmentationExamen = $augmentation * 0.6;

                $e["nouvelle_note_devoir"] = round($e["note_devoir"] + $augmentationDevoir, 2);
                $e["nouvelle_note_examen"] = round($e["note_examen"] + $augmentationExamen, 2);
            }
        }
        unset($e);

        $simulations[] = [
            "matricule" => $etudiant['matricule'],
            "nom" => trim(($etudiant['prenom'] ?? '') . ' ' . ($etudiant['nom'] ?? '')),
            "moyenne_avant" => round($avgBefore, 4),
            "moyenne_apres" => round(weightedAverage($ecModifies), 4),
            "info_fix" => $fix['reason'] ?? null,
            "details_ec" => $ecModifies,
            "aExamenNonCompose" => $aExamenNonCompose
        ];
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
                bn.nature,
                pn.non_compose,
                pn.justifier,
                pn.idAnnee,
                CASE 
                    WHEN bn.idNature = 2 THEN 'examen'
                    ELSE 'devoir'
                END as type_evaluation
            FROM scolarite_inscription_pedagogique_ue sipu
            JOIN scolarite_inscription_pedagogique sip ON sipu.idInscriptionPedagogique = sip.id
            JOIN scolarite_etudiants se ON sipu.matricule = se.matricule
            JOIN scolarite_inscription si on sip.idInscription = si.id
            JOIN pedagogie_notes pn ON sip.id = pn.idInscription
            JOIN ec ON ec.id = pn.idEc
            JOIN bordereau_note bn ON pn.idDevoir = bn.idDevoir
            WHERE pn.idUe = :idUE 
              AND sip.statut = 1 
              AND bn.session_id = :session_id
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
        $ecId      = $ligne['ec_id'];
        $type      = $ligne['type_evaluation'];
        $nonCompose = (int)$ligne['non_compose'];
        $justifier  = (int)$ligne['justifier'];

        if (!isset($etudiants[$matricule])) {
            $etudiants[$matricule] = [
                "matricule" => $matricule,
                "prenom"    => $ligne['prenom'],
                "nom"       => $ligne['nomEtudiant'],
                "ec"        => []
            ];
        }

        if (!isset($etudiants[$matricule]["ec"][$ecId])) {
            $etudiants[$matricule]["ec"][$ecId] = [
                "id"             => $ecId,
                "name"           => $ligne['nomEc'],
                "coef_ec"        => max(1, (float)$ligne['coef_ec']),
                "devoirs"        => [],  // devoirs composés (non_compose = 0)
                "devoirs_nc_justifies" => [], // non_compose = 1 ET justifier = 1
                "devoirs_nc_non_justifies" => 0, // compteur non_compose = 1 ET justifier = 0
                "examens"        => [],  // examen composé
                "examen_non_compose" => null, // non_compose = 1 (justifier ou non)
                "note_devoir"    => null,
                "note_examen"    => null,
                "note_finale_ec" => null,
                "a_examen"       => false
            ];
        }

        $noteValue = max(0, min(20, (float)$ligne['note']));

        if ($type === 'devoir') {
            if ($nonCompose === 0) {
                // Devoir composé normalement
                $etudiants[$matricule]["ec"][$ecId]["devoirs"][] = $noteValue;
            } elseif ($nonCompose === 1 && $justifier === 1) {
                // Absence justifiée — on garde la note pour le calcul ajusté
                $etudiants[$matricule]["ec"][$ecId]["devoirs_nc_justifies"][] = $noteValue;
            } else {
                // Absence non justifiée — compte comme 0
                $etudiants[$matricule]["ec"][$ecId]["devoirs_nc_non_justifies"]++;
            }
        } else {
            // Examen
            if ($nonCompose === 0) {
                $etudiants[$matricule]["ec"][$ecId]["examens"][]  = $noteValue;
                $etudiants[$matricule]["ec"][$ecId]["a_examen"]   = true;
            } else {
                // Non composé à l'examen → note = 0, mais l'examen est quand même "présent"
                $etudiants[$matricule]["ec"][$ecId]["examen_non_compose"] = true;
                $etudiants[$matricule]["ec"][$ecId]["a_examen"]  = true;
            }
        }
    }

    // 3. Calculer les moyennes pour chaque EC
    foreach ($etudiants as $matricule => &$etudiant) {
        $aTousExamens = true;

        foreach ($etudiant["ec"] as $ecId => &$ecData) {
            $nbDevoirsPrevus      = $nbDevoirsParEc[$ecId] ?? 0;
            $nbComposes           = count($ecData["devoirs"]);
            $nbJustifies          = count($ecData["devoirs_nc_justifies"]);
            $nbNonJustifies       = $ecData["devoirs_nc_non_justifies"];
            $totalNonCompose      = $nbJustifies + $nbNonJustifies;
            $tousNonCompose       = ($nbComposes === 0 && $totalNonCompose === $nbDevoirsPrevus);
            $tousJustifies        = ($tousNonCompose && $nbNonJustifies === 0);

            // --- Calcul note devoir ---
            $moyenneDevoir = null;

            if ($nbDevoirsPrevus === 0) {
                // Pas de devoirs prévus pour cet EC
                $moyenneDevoir = null;
                $ecData["calcul_devoir"] = "aucun_devoir_prevu";
            } elseif ($tousJustifies) {
                // Tous les devoirs sont non composés ET justifiés
                // → on utilise la note d'examen comme note de devoir (traité après)
                $ecData["calcul_devoir"] = "tous_nc_justifies_utiliser_examen";
            } elseif ($nbNonJustifies > 0 && $nbComposes === 0 && $nbJustifies === 0) {
                // Tous non composés et non justifiés → note devoir = 0
                $moyenneDevoir = 0;
                $ecData["calcul_devoir"] = "tous_nc_non_justifies";
            } else {
                // Cas mixte — on calcule le diviseur
                // Diviseur = nb prévus - nb justifiés (les justifiés ne comptent pas)
                $diviseur = $nbDevoirsPrevus - $nbJustifies;

                if ($diviseur <= 0) {
                    // Tous justifiés
                    $ecData["calcul_devoir"] = "tous_nc_justifies_utiliser_examen";
                } else {
                    // Somme = devoirs composés + 0 pour chaque non justifié
                    $somme = array_sum($ecData["devoirs"]); // les non justifiés comptent 0
                    $moyenneDevoir = $somme / $diviseur;
                    $ecData["note_devoir"]   = round($moyenneDevoir, 2);
                    $ecData["calcul_devoir"] = sprintf(
                        "%.2f / %d (dont %d absent(s) non justifié(s) = 0)",
                        $somme,
                        $diviseur,
                        $nbNonJustifies
                    );
                }
            }

            $ecData["nb_devoirs"] = $nbDevoirsPrevus;

            // --- Calcul note examen ---
            $moyenneExamen = null;

            if (!empty($ecData["examens"])) {
                $moyenneExamen = array_sum($ecData["examens"]);
                $ecData["note_examen"] = $moyenneExamen;
            } elseif ($ecData["examen_non_compose"]) {
                // Non composé à l'examen → note examen = 0
                $moyenneExamen = 0;
                $ecData["note_examen"] = 0;
                $ecData["calcul_mode"]    = "examen_manquant";
                $ecData["calcul_detail"]  = "Examen manquant - EC non noté";
                $ecData['note_finale_ec'] = 0;
                continue;
            }

            // --- Cas "tous devoirs NC justifiés" → note devoir = note examen ---
            if (($ecData["calcul_devoir"] ?? '') === "tous_nc_justifies_utiliser_examen" || ($ecData["calcul_devoir"] ?? '') === "aucun_devoir_prevu") {
                if ($moyenneExamen !== null) {
                    $moyenneDevoir = $moyenneExamen;
                    $ecData["note_devoir"] = $moyenneDevoir;
                    if (($ecData["calcul_devoir"] ?? '') === "tous_nc_justifies_utiliser_examen") {
                        $ecData["calcul_devoir"] = sprintf(
                            "Tous devoirs NC justifiés → note devoir = note examen (%.2f)",
                            $moyenneExamen
                        );
                    } else {
                        $ecData["calcul_devoir"] = sprintf(
                            "Aucun devoir prévu → note devoir = note examen (%.2f)",
                            $moyenneExamen
                        );
                    }
                } else {
                    // Pas d'examen non plus
                    $moyenneDevoir = null;
                }
            }

            // --- RÈGLE 1 : Pas d'examen ---
            if (!$ecData["a_examen"]) {
                $ecData["note_finale_ec"] = 0;
                $ecData["calcul_mode"]    = "examen_manquant";
                $ecData["calcul_detail"]  = "Examen manquant - EC non noté";
                $aTousExamens = false;
                continue;
            }

            // --- RÈGLE 2 : Pas de devoir ---
            if ($moyenneDevoir === null && $nbDevoirsPrevus > 0) {
                $ecData["note_finale_ec"] = null;
                $ecData["calcul_mode"]    = "devoir_manquant";
                $ecData["calcul_detail"]  = "Devoir manquant - EC non noté";
                $aTousExamens = false;
                continue;
            }

            // --- RÈGLE 3 : Calcul normal 40/60 ---
            $noteFinale = ($moyenneDevoir * 0.4) + ($moyenneExamen * 0.6);
            $ecData["note_finale_ec"] = round($noteFinale, 2);
            $ecData["calcul_mode"]    = "40_60";
            $ecData["calcul_detail"]  = sprintf(
                "%.2f × 0.4 + %.2f × 0.6 | %s",
                $moyenneDevoir,
                $moyenneExamen,
                $ecData["calcul_devoir"]
            );

            // Marquer si l'examen est non composé
            $ecData["examen_non_compose_flag"] = $ecData["examen_non_compose"] ?? false;

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
        // Vérifier si au moins un EC a un examen non composé
        $aExamenNonCompose = false;
foreach ($etudiant["ec"] as $ec) {
    if (!empty($ec["examen_non_compose"])) {
        $aExamenNonCompose = true;
        break;
    }
}
        $raisonNonRepechable = null;

        if (!$etudiant["moyenne_calculable"]) {
            $raisonNonRepechable = "moyenne_non_calculable";
        } elseif ($aExamenNonCompose) {
            $raisonNonRepechable = "non_compose_examen";
        }
        //  elseif ($etudiant["moyenne_ue"] < 7) {
        //     $raisonNonRepechable = "moyenne_inf_7";
        // }
        $etudiant["stats"] = [
            "nb_ec" => count($etudiant["ec"]),
            "nb_ec_avec_examen" => count(array_filter($etudiant["ec"], function ($ec) {
                return $ec["a_examen"];
            })),
            "nb_ec_sans_examen" => count(array_filter($etudiant["ec"], function ($ec) {
                return !$ec["a_examen"];
            })),
            "moyenne_ue_formatee" => $etudiant["moyenne_calculable"] ? number_format($etudiant["moyenne_ue"], 2) : "N/A",
            "total_coef_ue" => array_sum(array_column($etudiant["ec"], "coef_ec")),
            "calcul_detail" => implode(" + ", $calculDetailUE),
            "moyenne_calculable" => $etudiant["moyenne_calculable"],
            "est_repechable" => (
            $etudiant["moyenne_calculable"]
            && $etudiant["moyenne_ue"] < 10
            && !$aExamenNonCompose
        ),
            "non_repechable_raison" => $raisonNonRepechable
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
            // Après la vérification de note_examen (étape 2), ajouter :

            // 2bis. Vérifier qu'il n'y a pas plusieurs notes d'examen
            $nbExamens = count($ecData['examens'] ?? []);
            if ($nbExamens > 1) {
                $anomalies[] = [
                    'ec_id'    => $ecId,
                    'ec_nom'   => $ecUE['nom'],
                    'type'     => 'plusieurs_examens',
                    'raison'   => 'plusieurs_examens',
                    'message'  => $nbExamens . ' notes d\'examen trouvées — une seule attendue',
                    'notes'    => $ecData['examens'],
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
function enregistrerRepêchage($pdo, $data)
{
    $transactionOuverteIci = false;

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $transactionOuverteIci = true;
        }
        // 1. Validation des données requises
        $requiredFields = ['idInscription', 'idUE', 'idSession', 'moyenne_initial', 'moyenne_final', 'ec_repeches'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Champ requis manquant : $field");
            }
        }

        if (!is_array($data['ec_repeches']) || count($data['ec_repeches']) === 0) {
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
            ':nb_ec' => $data['nb_ec'] ?? count($data['ec_repeches'])
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
        $sqlCheck = "SELECT id FROM `repechage_historique`
                    WHERE idInscription = :idInscription
                    AND idUE = :idUE
                    AND idEC = :idEC
                    AND idSession = :idSession";
        $stmtCheck = $pdo->prepare($sqlCheck);

        // UPDATE — uniquement les champs modifiables (pas idRepechage, note_initial, coef, idUtilisateur)
        $sqlUpdate = "UPDATE `repechage_historique` SET
                     idMoyenneUE  = :idMoyenneUE,
                     note_final   = :note_final,
                     point_jury   = :point_jury,
                     credit       = :credit,
                     statut       = :statut,
                     commentaire  = :commentaire
                     WHERE idInscription = :idInscription
                     AND idUE = :idUE
                     AND idEC = :idEC
                     AND idSession = :idSession";
        $stmtUpdate = $pdo->prepare($sqlUpdate);

        // INSERT — tous les champs
        $sqlInsert = "INSERT INTO `repechage_historique`
                     (idInscription, idUE, idEC, idSession, idRepechage, idMoyenneUE,
                      note_initial, note_final, point_jury, coef, credit,
                      idUtilisateur, statut, commentaire)
                     VALUES
                     (:idInscription, :idUE, :idEC, :idSession, :idRepechage, :idMoyenneUE,
                      :note_initial, :note_final, :point_jury, :coef, :credit,
                      :idUtilisateur, :statut, :commentaire)";
        $stmtInsert = $pdo->prepare($sqlInsert);

        $nbEcRepeches = 0;

        foreach ($data['ec_repeches'] as $ec) {
            if (!isset($ec['idEC'], $ec['note_initial'], $ec['note_final'], $ec['point_jury'], $ec['coef'])) {
                error_log("EC repêché invalide : " . json_encode($ec));
                continue;
            }

            $stmtCheck->execute([
                ':idInscription' => $data['idInscription'],
                ':idUE'          => $data['idUE'],
                ':idEC'          => $ec['idEC'],
                ':idSession'     => $data['idSession']
            ]);
            $exists = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            // Paramètres communs UPDATE et INSERT
            $paramsCommun = [
                ':idInscription' => $data['idInscription'],
                ':idUE'          => $data['idUE'],
                ':idEC'          => $ec['idEC'],
                ':idSession'     => $data['idSession'],
                ':idMoyenneUE'   => $idMoyenneUE,
                ':note_final'    => $ec['note_final'],
                ':point_jury'    => $ec['point_jury'],
                ':credit'        => $ec['credit'] ?? ($ec['note_final'] >= 10 ? ($ec['coef'] * 2) : 0),
                ':statut'        => 'applique',
                ':commentaire'   => $data['commentaire'] ?? 'Repechage automatique',
            ];

            if ($exists) {
                $stmtUpdate->execute($paramsCommun);
            } else {
                $stmtInsert->execute(array_merge($paramsCommun, [
                    ':idRepechage'   => $data['idRepechage']   ?? null,
                    ':note_initial'  => $ec['note_initial'],
                    ':coef'          => $ec['coef'],
                    ':idUtilisateur' => $data['idUtilisateur'] ?? ($_SESSION['idUser'] ?? null),
                ]));
            }
            $nbEcRepeches++;
        }

        if ($transactionOuverteIci) {
            $pdo->commit();
        }
        return [
            'success' => true,
            'idMoyenneUE' => $idMoyenneUE,
            'nb_ec_repeches' => $nbEcRepeches,
            'message' => "Repêchage enregistré avec succès. $nbEcRepeches EC modifiés."
        ];
    } catch (Exception $e) {
        if ($transactionOuverteIci && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur dans enregistrerRepêchage: " . $e->getMessage());
        throw $e;
    }
}
function syncVueEtudiantsParUE($pdo, $idUE, $existRepechage = false, $gererTransaction = true)
{
    $transactionOuverteIci = false;

    try {
        $pdo->exec("SET time_zone = '+00:00'");

        // Récupérer la prochaine version
        $stmtVersion = $pdo->prepare("
            SELECT COALESCE(MAX(sync_version), 0) + 1 AS next_version 
            FROM vue_etudiants_complete 
            WHERE idUE = :idUE
        ");
        $stmtVersion->execute([':idUE' => $idUE]);
        $nextVersion = (int)($stmtVersion->fetchColumn() ?: 1);

        // Récupérer les données UE
        $stmtUE = $pdo->prepare("SELECT code, nom FROM ue WHERE id = :idUE");
        $stmtUE->execute([':idUE' => $idUE]);
        $ue = $stmtUE->fetch(PDO::FETCH_ASSOC);

        if (!$ue) {
            throw new \Exception("UE #$idUE introuvable");
        }

        // Récupérer le repêchage actif
        $idAnnee = $pdo->query("SELECT MAX(id) FROM scolarite_anneeuniversitaire")->fetchColumn();
        $stmtRep = $pdo->prepare("
            SELECT idRepechage FROM repechage 
            WHERE idUe = :idUE AND statut = 1 AND idAnnee = :idAnnee 
            LIMIT 1
        ");
        $stmtRep->execute([':idUE' => $idUE, ':idAnnee' => $idAnnee]);
        $idRepechageActif = $stmtRep->fetchColumn() ?: null;

        // ← Choix de la source selon $existRepechage
        if ($existRepechage) {
            // Données mixtes : repêchés depuis repechage_historique, autres depuis pedagogie_notes
            $etudiants = getLastVersionOfNote($pdo, $idUE, 1);
        } else {
            // Uniquement depuis pedagogie_notes
            $etudiants = getNotesFromPedagogie($pdo, $idUE, 1);
        }

        if ($gererTransaction && !$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $transactionOuverteIci = true;
        }

        // // Supprimer les anciennes données
        // $stmtDelete = $pdo->prepare("DELETE FROM vue_etudiants_complete WHERE idUE = :idUE");
        // $stmtDelete->execute([':idUE' => $idUE]);

        // Préparer l'INSERT
        $sqlInsert = "
            INSERT INTO vue_etudiants_complete (
                matricule, prenom, nom, filiere, niveau, option_etudiant,
                idInscription, idUE, code_ue, nom_ue,
                idEC, code_ec, nom_ec, coefficient_ec, credit_ec,
                note_initial, idSession, idRepechage,
                note_final, point_jury, source_note,
                date_evaluation, `date_repechage`, date_maj, sync_version, sync_at,
                idUser_note, `idUser_repêchage`
            ) VALUES (
                :matricule, :prenom, :nom, :filiere, :niveau, :option_etudiant,
                :idInscription, :idUE, :code_ue, :nom_ue,
                :idEC, :code_ec, :nom_ec, :coefficient_ec, :credit_ec,
                :note_initial, :idSession, :idRepechage,
                :note_final, :point_jury, :source_note,
                :date_evaluation, :date_repechage, NOW(), :sync_version, NOW(),
                :idUser_note, :idUser_repechage
            )
        ";
        $stmtInsert = $pdo->prepare($sqlInsert);

        // Récupérer les infos complémentaires par étudiant (filiere, niveau, option)
        // en une seule requête pour éviter N requêtes dans la boucle
        $sqlInfos = "
            SELECT 
                se.matricule,
                se.prenom,
                se.nom,
                sip.id          AS idInscription,
                fil.filiere,
                niv.niveau,
                opt.option      AS option_etudiant
            FROM scolarite_inscription_pedagogique_ue sipu
            JOIN scolarite_inscription_pedagogique sip ON sipu.idInscriptionPedagogique = sip.id
            JOIN scolarite_etudiants se ON sip.matricule = se.matricule
            JOIN scolarite_inscription si ON sip.idInscription = si.id
            JOIN options opt ON opt.id = si.idOption
            JOIN filieres fil ON fil.id = opt.idFilieres
            JOIN niveauformation niv ON niv.id = opt.idNiveauFormation
            WHERE sipu.idUE = :idUE AND sip.statut = 1
        ";
        $stmtInfos = $pdo->prepare($sqlInfos);
        $stmtInfos->execute([':idUE' => $idUE]);
        $infosMap = [];
        foreach ($stmtInfos->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $infosMap[$row['matricule']] = $row;
        }

        // Récupérer les infos EC (credit) en une seule requête
        $sqlEC = "
            SELECT ec.id, ec.code, ec.nombre_credit
            FROM ec
            JOIN ue ON ue.id = ec.id_ue
            WHERE ue.id = :idUE
        ";
        $stmtEC = $pdo->prepare($sqlEC);
        $stmtEC->execute([':idUE' => $idUE]);
        $ecMap = [];
        foreach ($stmtEC->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ecMap[$row['id']] = $row;
        }

        // Récupérer les dates et users depuis repechage_historique pour les repêchés
        $rephDateMap = [];
        if ($idRepechageActif) {
            $stmtDates = $pdo->prepare("
                SELECT idInscription, idEC, date_repechage, idUtilisateur
                FROM repechage_historique
                WHERE idUE = :idUE AND idSession = 1 AND statut = 'applique'
            ");
            $stmtDates->execute([':idUE' => $idUE]);
            foreach ($stmtDates->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rephDateMap[$row['idInscription']][$row['idEC']] = $row;
            }
        }

        $nbRows = 0;

        foreach ($etudiants as $etudiant) {
            $matricule = $etudiant['matricule'];
            $infos     = $infosMap[$matricule] ?? null;

            if (!$infos) {
                error_log("Infos manquantes pour matricule $matricule — UE $idUE");
                continue;
            }

            foreach ($etudiant['ec'] as $ec) {
                $idEC        = $ec['id'];
                $ecInfo      = $ecMap[$idEC] ?? null;
                $estRepeche  = ($ec['source_note'] ?? 'pedagogie') === 'repechage';
                $rephInfo    = $rephDateMap[$infos['idInscription']][$idEC] ?? null;

                $stmtInsert->execute([
                    ':matricule'       => $matricule,
                    ':prenom'          => $etudiant['prenom'],
                    ':nom'             => $etudiant['nom'],
                    ':filiere'         => $infos['filiere'],
                    ':niveau'          => $infos['niveau'],
                    ':option_etudiant' => $infos['option_etudiant'],
                    ':idInscription'   => $infos['idInscription'],
                    ':idUE'            => $idUE,
                    ':code_ue'         => $ue['code'],
                    ':nom_ue'          => $ue['nom'],
                    ':idEC'            => $idEC,
                    ':code_ec'         => $ecInfo['code']          ?? $ec['name'],
                    ':nom_ec'          => $ec['name'],
                    ':coefficient_ec'  => $ec['coef_ec'],
                    ':credit_ec'       => $ecInfo['nombre_credit'] ?? 0,
                    ':note_initial'    => $ec['note_initial']      ?? $ec['note_finale_ec'],
                    ':idSession'       => 1,
                    ':idRepechage'     => $estRepeche ? $idRepechageActif : null,
                    ':note_final'      => $ec['note_finale_ec'],
                    ':point_jury'      => $ec['point_jury']        ?? 0,
                    ':source_note'     => $ec['source_note']       ?? 'pedagogie',
                    ':date_evaluation' => null,
                    ':date_repechage'  => $estRepeche ? ($rephInfo['date_repechage'] ?? null) : null,
                    ':sync_version'    => $nextVersion,
                    ':idUser_note'     => null,
                    ':idUser_repechage' => $estRepeche ? ($rephInfo['idUtilisateur'] ?? null) : null,
                ]);

                $nbRows++;
            }
        }

        if ($transactionOuverteIci) {
            $pdo->commit();
        }

        return [
            'success'      => true,
            'message'      => "Vue synchronisée pour l'UE #$idUE. Version $nextVersion.",
            'rows'         => $nbRows,
            'sync_version' => $nextVersion
        ];
    } catch (\Exception $e) {
        if ($transactionOuverteIci && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'success' => false,
            'message' => "Erreur sync UE #$idUE : " . $e->getMessage()
        ];
    }
}

function getLastVersionOfNote($pdo, $idUE, $session_id = 1)
{
    // 0. Récupérer l'année active
    $idAnnee = $pdo->query("SELECT MAX(id) FROM scolarite_anneeuniversitaire")->fetchColumn();

    // 0b. Récupérer le repêchage actif pour cette UE (statut=1, année active)
    $stmtRep = $pdo->prepare("
        SELECT idRepechage FROM repechage 
        WHERE idUe    = :idUE 
          AND statut  = 1 
          AND idAnnee = :idAnnee
        LIMIT 1
    ");
    $stmtRep->execute([':idUE' => $idUE, ':idAnnee' => $idAnnee]);
    $idRepechageActif = $stmtRep->fetchColumn() ?: null;

    // 0c. Récupérer les étudiants repêchés (ceux qui ont un enregistrement 'appliqué')
    $etudiantsRepeches = []; // [idInscription => [idEC => donnees_reph]]

    if ($idRepechageActif) {
        $stmtReph = $pdo->prepare("
            SELECT 
                reph.idInscription,
                reph.idEC,
                reph.note_initial,
                reph.note_final,
                reph.point_jury,
                reph.coef,
                reph.date_repechage,
                reph.idUtilisateur,
                ec.nom          AS nomEc,
                ec.coefficient  AS coef_ec,
                ec.id           AS ec_id
            FROM repechage_historique reph
            JOIN ec ON ec.id = reph.idEC
            WHERE reph.idUE     = :idUE
              AND reph.idSession = :session_id
              AND reph.statut   = 'appliqué'
        ");
        $stmtReph->execute([':idUE' => $idUE, ':session_id' => $session_id]);

        foreach ($stmtReph->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $etudiantsRepeches[$row['idInscription']][$row['idEC']] = $row;
        }
    }

    // 1. Récupérer le nombre de devoirs prévus par EC
    $sqlDevoirs = "
        SELECT bn.idEc, COUNT(bn.idDevoir) AS nbDevoirs 
        FROM bordereau_note bn 
        WHERE bn.idNature = 1 
          AND bn.idEc IN (
              SELECT DISTINCT pn.idEc 
              FROM pedagogie_notes pn 
              WHERE pn.idAnnee    = :idAnnee
                AND pn.session_id = :session_id 
                AND pn.idUe       = :idUE
          )
        GROUP BY bn.idEc
    ";
    $stmtDevoirs = $pdo->prepare($sqlDevoirs);
    $stmtDevoirs->execute([':idAnnee' => $idAnnee, ':session_id' => $session_id, ':idUE' => $idUE]);
    $nbDevoirsParEc = [];
    foreach ($stmtDevoirs->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $nbDevoirsParEc[$row['idEc']] = (int)$row['nbDevoirs'];
    }

    // 2. Récupérer toutes les notes depuis pedagogie_notes
    $sql = "
        SELECT 
                se.matricule, 
                se.prenom, 
                se.nom as nomEtudiant, 
                ec.id as ec_id,
                pn.idInscription,
                ec.nom as nomEc,
                ec.coefficient as coef_ec,
                pn.note, 
                bn.nature,
                pn.non_compose,
                pn.justifier,
                pn.idAnnee,
                CASE 
                    WHEN bn.idNature = 2 THEN 'examen'
                    ELSE 'devoir'
                END as type_evaluation
            FROM scolarite_inscription_pedagogique_ue sipu
            JOIN scolarite_inscription_pedagogique sip ON sipu.idInscriptionPedagogique = sip.id
            JOIN scolarite_etudiants se ON sipu.matricule = se.matricule
            JOIN scolarite_inscription si on sip.idInscription = si.id
            JOIN pedagogie_notes pn ON sip.id = pn.idInscription
            JOIN ec ON ec.id = pn.idEc
            JOIN bordereau_note bn ON pn.idDevoir = bn.idDevoir
        WHERE pn.idUe       = :idUE 
          AND sip.statut    = 1 
          AND pn.session_id = :session_id
          AND pn.idAnnee    = :idAnnee
        GROUP BY se.matricule, ec.id, pn.idDevoir
        ORDER BY se.matricule, ec.id, pn.nature
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':idUE' => $idUE, ':session_id' => $session_id, ':idAnnee' => $idAnnee]);
    $resultatsBruts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $etudiants = [];

    // 3. Organiser par étudiant et EC
    foreach ($resultatsBruts as $ligne) {
        $matricule    = $ligne['matricule'];
        $ecId         = $ligne['ec_id'];
        $type         = $ligne['type_evaluation'];
        $nonCompose   = (int)$ligne['non_compose'];
        $justifier    = (int)$ligne['justifier'];
        $idInscription = $ligne['idInscription'];

        if (!isset($etudiants[$matricule])) {
            $etudiants[$matricule] = [
                "matricule"     => $matricule,
                "prenom"        => $ligne['prenom'],
                "nom"           => $ligne['nomEtudiant'],
                "idInscription" => $idInscription,
                "est_repeche"   => isset($etudiantsRepeches[$idInscription]),
                "ec"            => []
            ];
        }

        // Si étudiant repêché → on ne construit pas les EC depuis pedagogie_notes
        // On les construira depuis repechage_historique après
        if ($etudiants[$matricule]["est_repeche"]) continue;

        if (!isset($etudiants[$matricule]["ec"][$ecId])) {
            $etudiants[$matricule]["ec"][$ecId] = [
                "id"                        => $ecId,
                "name"                      => $ligne['nomEc'],
                "coef_ec"                   => max(1, (float)$ligne['coef_ec']),
                "devoirs"                   => [],
                "devoirs_nc_justifies"      => [],
                "devoirs_nc_non_justifies"  => 0,
                "examens"                   => [],
                "examen_non_compose"        => null,
                "note_devoir"               => null,
                "note_examen"               => null,
                "note_finale_ec"            => null,
                "a_examen"                  => false,
                "source_note"               => "pedagogie"
            ];
        }

        $noteValue = max(0, min(20, (float)$ligne['note']));

        if ($type === 'devoir') {
            if ($nonCompose === 0) {
                $etudiants[$matricule]["ec"][$ecId]["devoirs"][] = $noteValue;
            } elseif ($nonCompose === 1 && $justifier === 1) {
                $etudiants[$matricule]["ec"][$ecId]["devoirs_nc_justifies"][] = $noteValue;
            } else {
                $etudiants[$matricule]["ec"][$ecId]["devoirs_nc_non_justifies"]++;
            }
        } else {
            if ($nonCompose === 0) {
                $etudiants[$matricule]["ec"][$ecId]["examens"][] = $noteValue;
                $etudiants[$matricule]["ec"][$ecId]["a_examen"]  = true;
            } else {
                $etudiants[$matricule]["ec"][$ecId]["examen_non_compose"] = true;
                $etudiants[$matricule]["ec"][$ecId]["a_examen"]           = true;
            }
        }
    }

    // 4. Injecter les données repêchage pour les étudiants repêchés
    foreach ($etudiants as $matricule => &$etudiant) {
        if (!$etudiant["est_repeche"]) continue;

        $idInscription = $etudiant["idInscription"];
        $ecsReph       = $etudiantsRepeches[$idInscription] ?? [];

        foreach ($ecsReph as $idEC => $reph) {
            $etudiant["ec"][$idEC] = [
                "id"                        => $idEC,
                "name"                      => $reph['nomEc'],
                "coef_ec"                   => max(1, (float)$reph['coef_ec']),
                "note_finale_ec"            => (float)$reph['note_final'],
                "note_initial"              => (float)$reph['note_initial'],
                "note"                      => (float)$reph['note_final'],
                "coef"                      => max(1, (float)$reph['coef_ec']),
                "point_jury"                => (float)$reph['point_jury'],
                "source_note"               => "repechage",
                "a_examen"                  => true,
                "examen_non_compose_flag"   => false,
                "calcul_mode"               => "repechage",
                "calcul_detail"             => sprintf(
                    "Repêché : %.2f → %.2f (+%.2f pts jury)",
                    $reph['note_initial'],
                    $reph['note_final'],
                    $reph['point_jury']
                )
            ];
        }
    }

    // 5. Calcul des moyennes (identique à avant, mais skip les repêchés déjà calculés)
    foreach ($etudiants as $matricule => &$etudiant) {

        if ($etudiant["est_repeche"]) {
            // Moyenne directement depuis les notes repêchées
            $totalPoints = 0;
            $totalCoefs  = 0;
            foreach ($etudiant["ec"] as $ec) {
                $totalPoints += $ec["note_finale_ec"] * $ec["coef_ec"];
                $totalCoefs  += $ec["coef_ec"];
            }
            $etudiant["ec"]             = array_values($etudiant["ec"]);
            $etudiant["moyenne_ue"]     = $totalCoefs > 0 ? round($totalPoints / $totalCoefs, 2) : 0;
            $etudiant["moyenne_calculable"] = true;
            $etudiant["stats"] = [
                "nb_ec"                 => count($etudiant["ec"]),
                "moyenne_ue_formatee"   => number_format($etudiant["moyenne_ue"], 2),
                "total_coef_ue"         => $totalCoefs,
                "calcul_detail"         => "Données issues du repêchage",
                "moyenne_calculable"    => true,
                "est_repechable"        => false, // déjà repêché
                "non_repechable_raison" => "deja_repeche"
            ];
            continue;
        }

        // --- Calcul normal pour les non repêchés (code existant inchangé) ---
        $aTousExamens = true;

        foreach ($etudiant["ec"] as $ecId => &$ecData) {
            $nbDevoirsPrevus    = $nbDevoirsParEc[$ecId] ?? 0;
            $nbComposes         = count($ecData["devoirs"]);
            $nbJustifies        = count($ecData["devoirs_nc_justifies"]);
            $nbNonJustifies     = $ecData["devoirs_nc_non_justifies"];
            $totalNonCompose    = $nbJustifies + $nbNonJustifies;
            $tousNonCompose     = ($nbComposes === 0 && $totalNonCompose === $nbDevoirsPrevus);
            $tousJustifies      = ($tousNonCompose && $nbNonJustifies === 0);

            $moyenneDevoir = null;

            if ($nbDevoirsPrevus === 0) {
                $moyenneDevoir           = null;
                $ecData["calcul_devoir"] = "aucun_devoir_prevu";
            } elseif ($tousJustifies) {
                $ecData["calcul_devoir"] = "tous_nc_justifies_utiliser_examen";
            } elseif ($nbNonJustifies > 0 && $nbComposes === 0 && $nbJustifies === 0) {
                $moyenneDevoir           = 0;
                $ecData["calcul_devoir"] = "tous_nc_non_justifies";
            } else {
                $diviseur = $nbDevoirsPrevus - $nbJustifies;
                if ($diviseur <= 0) {
                    $ecData["calcul_devoir"] = "tous_nc_justifies_utiliser_examen";
                } else {
                    $somme                   = array_sum($ecData["devoirs"]);
                    $moyenneDevoir           = $somme / $diviseur;
                    $ecData["note_devoir"]   = round($moyenneDevoir, 2);
                    $ecData["calcul_devoir"] = sprintf("%.2f / %d (dont %d absent(s) non justifié(s) = 0)", $somme, $diviseur, $nbNonJustifies);
                }
            }

            $ecData["nb_devoirs"] = $nbDevoirsPrevus;

            $moyenneExamen = null;
            if (!empty($ecData["examens"])) {
                if (count($ecData["examens"]) > 1) {
                    // Plusieurs notes d'examen = erreur de saisie
                    $ecData["note_examen"]        = null;
                    $ecData["a_examen"]           = true;
                    $ecData["plusieurs_examens"]  = true;
                    $ecData["examens_en_erreur"]  = $ecData["examens"];
                    $ecData["note_finale_ec"]     = null;
                    $ecData["calcul_mode"]        = "erreur_plusieurs_examens";
                    $ecData["calcul_detail"]      = count($ecData["examens"]) . " notes d'examen trouvees — une seule attendue";
                    $aTousExamens = false;
                    continue;
                }
                $moyenneExamen         = $ecData["examens"][0];
                $ecData["note_examen"] = $moyenneExamen;
            } elseif ($ecData["examen_non_compose"]) {
                $moyenneExamen         = 0;
                $ecData["note_examen"] = 0;
            }

            if (in_array($ecData["calcul_devoir"] ?? '', ["tous_nc_justifies_utiliser_examen", "aucun_devoir_prevu"])) {
                if ($moyenneExamen !== null) {
                    $moyenneDevoir         = $moyenneExamen;
                    $ecData["note_devoir"] = $moyenneDevoir;
                    $ecData["calcul_devoir"] = sprintf(
                        in_array($ecData["calcul_devoir"], ["aucun_devoir_prevu"])
                            ? "Aucun devoir prévu → note devoir = note examen (%.2f)"
                            : "Tous devoirs NC justifiés → note devoir = note examen (%.2f)",
                        $moyenneExamen
                    );
                } else {
                    $moyenneDevoir = null;
                }
            }

            if ($ecData['examen_non_compose']) {
                $ecData["note_finale_ec"] = null;
                $ecData["calcul_mode"]    = "examen_manquant";
                $ecData["calcul_detail"]  = "Examen manquant - EC non noté";
                $aTousExamens = false;
                continue;
            }

            if ($moyenneDevoir === null && $nbDevoirsPrevus > 0) {
                $ecData["note_finale_ec"] = null;
                $ecData["calcul_mode"]    = "devoir_manquant";
                $ecData["calcul_detail"]  = "Devoir manquant - EC non noté";
                $aTousExamens = false;
                continue;
            }

            $noteFinale               = ($moyenneDevoir * 0.4) + ($moyenneExamen * 0.6);
            $ecData["note_finale_ec"] = round($noteFinale, 2);
            $ecData["note"]           = $ecData["note_finale_ec"];
            $ecData["coef"]           = $ecData["coef_ec"];
            $ecData["calcul_mode"]    = "40_60";
            $ecData["calcul_detail"]  = sprintf("%.2f × 0.4 + %.2f × 0.6 | %s", $moyenneDevoir, $moyenneExamen, $ecData["calcul_devoir"]);
            $ecData["examen_non_compose_flag"] = $ecData["examen_non_compose"] ?? false;
            $ecData["source_note"]    = "pedagogie";
        }

        $etudiant["ec"] = array_values($etudiant["ec"]);

        if ($aTousExamens) {
            $totalPointsUE  = 0;
            $totalCoefUE    = 0;
            $calculDetailUE = [];
            foreach ($etudiant["ec"] as $ec) {
                if ($ec["note_finale_ec"] !== null) {
                    $totalPointsUE  += $ec["note_finale_ec"] * $ec["coef_ec"];
                    $totalCoefUE    += $ec["coef_ec"];
                    $calculDetailUE[] = sprintf("%.2f × %.1f", $ec["note_finale_ec"], $ec["coef_ec"]);
                }
            }
            $etudiant["moyenne_ue"]         = $totalCoefUE > 0 ? round($totalPointsUE / $totalCoefUE, 2) : 0;
            $etudiant["moyenne_calculable"] = true;
        } else {
            $etudiant["moyenne_ue"]         = 0;
            $etudiant["moyenne_calculable"] = false;
            $calculDetailUE                 = ["Moyenne non calculable - Examen(s) manquant(s)"];
        }

        $etudiant["nbDevoirsParEc"] = $nbDevoirsParEc;

        $aExamenNonCompose = false;
        foreach ($etudiant["ec"] as $ec) {
            if ($ec["examen_non_compose"] ?? false) {
                $aExamenNonCompose = true;
                break;
            }
        }

        $raisonNonRepechable = null;
        if (!$etudiant["moyenne_calculable"]) {
            $raisonNonRepechable = "moyenne_non_calculable";
        } elseif ($aExamenNonCompose) {
            $raisonNonRepechable = "non_compose_examen";
        }

        $etudiant["stats"] = [
            "nb_ec"                 => count($etudiant["ec"]),
            "nb_ec_avec_examen"     => count(array_filter($etudiant["ec"], fn($ec) => $ec["a_examen"])),
            "nb_ec_sans_examen"     => count(array_filter($etudiant["ec"], fn($ec) => !$ec["a_examen"])),
            "moyenne_ue_formatee"   => $etudiant["moyenne_calculable"] ? number_format($etudiant["moyenne_ue"], 2) : "N/A",
            "total_coef_ue"         => array_sum(array_column($etudiant["ec"], "coef_ec")),
            "calcul_detail"         => implode(" + ", $calculDetailUE),
            "moyenne_calculable"    => $etudiant["moyenne_calculable"],
            "est_repechable"        => ($etudiant["moyenne_calculable"] && $etudiant["moyenne_ue"] < 10 && !$aExamenNonCompose),
            "non_repechable_raison" => $raisonNonRepechable
        ];
    }

    return array_values($etudiants);
}
function getNotesFromPedagogie($pdo, $idUE, $session_id = 1)
{
    $idAnnee = $pdo->query("SELECT MAX(id) FROM scolarite_anneeuniversitaire")->fetchColumn();

    // Nombre de devoirs prévus par EC
    $stmtDevoirs = $pdo->prepare("
        SELECT bn.idEc, COUNT(bn.idDevoir) AS nbDevoirs 
        FROM bordereau_note bn 
        WHERE bn.idNature = 1 
          AND bn.idEc IN (
              SELECT DISTINCT pn.idEc 
              FROM pedagogie_notes pn 
              WHERE pn.idAnnee    = :idAnnee
                AND pn.session_id = :session_id 
                AND pn.idUe       = :idUE
          )
        GROUP BY bn.idEc
    ");
    $stmtDevoirs->execute([':idAnnee' => $idAnnee, ':session_id' => $session_id, ':idUE' => $idUE]);
    $nbDevoirsParEc = [];
    foreach ($stmtDevoirs->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $nbDevoirsParEc[$row['idEc']] = (int)$row['nbDevoirs'];
    }

    // Notes depuis pedagogie_notes uniquement
    $sql = "
        SELECT 
                se.matricule, 
                se.prenom, 
                se.nom as nomEtudiant, 
                ec.id as ec_id,
                pn.idInscription,
                ec.nom as nomEc,
                ec.coefficient as coef_ec,
                pn.note, 
                bn.nature,
                pn.non_compose,
                pn.justifier,
                pn.idAnnee,
                CASE 
                    WHEN bn.idNature = 2 THEN 'examen'
                    ELSE 'devoir'
                END as type_evaluation
            FROM scolarite_inscription_pedagogique_ue sipu
            JOIN scolarite_inscription_pedagogique sip ON sipu.idInscriptionPedagogique = sip.id
            JOIN scolarite_etudiants se ON sipu.matricule = se.matricule
            JOIN scolarite_inscription si on sip.idInscription = si.id
            JOIN pedagogie_notes pn ON sip.id = pn.idInscription
            JOIN ec ON ec.id = pn.idEc
            JOIN bordereau_note bn ON pn.idDevoir = bn.idDevoir
        WHERE pn.idUe       = :idUE 
          AND sip.statut    = 1 
          AND bn.session_id = :session_id
          AND pn.idAnnee    = :idAnnee
        GROUP BY se.matricule, ec.id, pn.idDevoir
        ORDER BY se.matricule, ec.id, pn.nature
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':idUE' => $idUE, ':session_id' => $session_id, ':idAnnee' => $idAnnee]);
    $resultatsBruts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $etudiants = [];

    foreach ($resultatsBruts as $ligne) {
        $matricule     = $ligne['matricule'];
        $ecId          = $ligne['ec_id'];
        $type          = $ligne['type_evaluation'];
        $nonCompose    = (int)$ligne['non_compose'];
        $justifier     = (int)$ligne['justifier'];
        $idInscription = $ligne['idInscription'];

        if (!isset($etudiants[$matricule])) {
            $etudiants[$matricule] = [
                "matricule"     => $matricule,
                "prenom"        => $ligne['prenom'],
                "nom"           => $ligne['nomEtudiant'],
                "idInscription" => $idInscription,
                "est_repeche"   => false, // forcément false ici
                "ec"            => []
            ];
        }

        if (!isset($etudiants[$matricule]["ec"][$ecId])) {
            $etudiants[$matricule]["ec"][$ecId] = [
                "id"                       => $ecId,
                "name"                     => $ligne['nomEc'],
                "coef_ec"                  => max(1, (float)$ligne['coef_ec']),
                "devoirs"                  => [],
                "devoirs_nc_justifies"     => [],
                "devoirs_nc_non_justifies" => 0,
                "examens"                  => [],
                "examen_non_compose"       => null,
                "note_devoir"              => null,
                "note_examen"              => null,
                "note_finale_ec"           => null,
                "a_examen"                 => false,
                "source_note"              => "pedagogie"
            ];
        }

        $noteValue = max(0, min(20, (float)$ligne['note']));

        if ($type === 'devoir') {
            if ($nonCompose === 0) {
                $etudiants[$matricule]["ec"][$ecId]["devoirs"][] = $noteValue;
            } elseif ($nonCompose === 1 && $justifier === 1) {
                $etudiants[$matricule]["ec"][$ecId]["devoirs_nc_justifies"][] = $noteValue;
            } else {
                $etudiants[$matricule]["ec"][$ecId]["devoirs_nc_non_justifies"]++;
            }
        } else {
            if ($nonCompose === 0) {
                $etudiants[$matricule]["ec"][$ecId]["examens"][] = $noteValue;
                $etudiants[$matricule]["ec"][$ecId]["a_examen"]  = true;
            } else {
                $etudiants[$matricule]["ec"][$ecId]["examen_non_compose"] = true;
                $etudiants[$matricule]["ec"][$ecId]["a_examen"]           = true;
            }
        }
    }

    // Calcul des moyennes — même logique que getLastVersionOfNote
    foreach ($etudiants as $matricule => &$etudiant) {
        $aTousExamens = true;

        foreach ($etudiant["ec"] as $ecId => &$ecData) {
            $nbDevoirsPrevus = $nbDevoirsParEc[$ecId] ?? 0;
            $nbComposes      = count($ecData["devoirs"]);
            $nbJustifies     = count($ecData["devoirs_nc_justifies"]);
            $nbNonJustifies  = $ecData["devoirs_nc_non_justifies"];
            $tousNonCompose  = ($nbComposes === 0 && ($nbJustifies + $nbNonJustifies) === $nbDevoirsPrevus);
            $tousJustifies   = ($tousNonCompose && $nbNonJustifies === 0);

            $moyenneDevoir = null;

            if ($nbDevoirsPrevus === 0) {
                $ecData["calcul_devoir"] = "aucun_devoir_prevu";
            } elseif ($tousJustifies) {
                $ecData["calcul_devoir"] = "tous_nc_justifies_utiliser_examen";
            } elseif ($nbNonJustifies > 0 && $nbComposes === 0 && $nbJustifies === 0) {
                $moyenneDevoir           = 0;
                $ecData["calcul_devoir"] = "tous_nc_non_justifies";
            } else {
                $diviseur = $nbDevoirsPrevus - $nbJustifies;
                if ($diviseur <= 0) {
                    $ecData["calcul_devoir"] = "tous_nc_justifies_utiliser_examen";
                } else {
                    $somme                   = array_sum($ecData["devoirs"]);
                    $moyenneDevoir           = $somme / $diviseur;
                    $ecData["note_devoir"]   = round($moyenneDevoir, 2);
                    $ecData["calcul_devoir"] = sprintf("%.2f / %d (dont %d absent(s) = 0)", $somme, $diviseur, $nbNonJustifies);
                }
            }

            $ecData["nb_devoirs"] = $nbDevoirsPrevus;

            $moyenneExamen = null;
            if (!empty($ecData["examens"])) {
                $moyenneExamen         = array_sum($ecData["examens"]);
                $ecData["note_examen"] = $moyenneExamen;
            } elseif ($ecData["examen_non_compose"]) {
                $moyenneExamen         = 0;
                $ecData["note_examen"] = 0;
            }

            if (in_array($ecData["calcul_devoir"] ?? '', ["tous_nc_justifies_utiliser_examen", "aucun_devoir_prevu"])) {
                if ($moyenneExamen !== null) {
                    $moyenneDevoir           = $moyenneExamen;
                    $ecData["note_devoir"]   = $moyenneDevoir;
                    $ecData["calcul_devoir"] = sprintf(
                        ($ecData["calcul_devoir"] === "aucun_devoir_prevu")
                            ? "Aucun devoir prévu → note examen (%.2f)"
                            : "Tous NC justifiés → note examen (%.2f)",
                        $moyenneExamen
                    );
                } else {
                    $moyenneDevoir = null;
                }
            }

            if ($ecData['examen_non_compose']) {
                $ecData["note_finale_ec"] = null;
                $ecData["calcul_mode"]    = "examen_manquant";
                $ecData["calcul_detail"]  = "Examen manquant";
                $aTousExamens = false;
                continue;
            }

            if ($moyenneDevoir === null && $nbDevoirsPrevus > 0) {
                $ecData["note_finale_ec"] = null;
                $ecData["calcul_mode"]    = "devoir_manquant";
                $ecData["calcul_detail"]  = "Devoir manquant";
                $aTousExamens = false;
                continue;
            }

            $ecData["note_finale_ec"] = round(($moyenneDevoir * 0.4) + ($moyenneExamen * 0.6), 2);
            $ecData["note"]           = $ecData["note_finale_ec"];
            $ecData["coef"]           = $ecData["coef_ec"];
            $ecData["calcul_mode"]    = "40_60";
            $ecData["calcul_detail"]  = sprintf("%.2f × 0.4 + %.2f × 0.6", $moyenneDevoir, $moyenneExamen);
            $ecData["examen_non_compose_flag"] = $ecData["examen_non_compose"] ?? false;
        }

        $etudiant["ec"] = array_values($etudiant["ec"]);

        if ($aTousExamens) {
            $totalPoints = 0;
            $totalCoefs  = 0;
            foreach ($etudiant["ec"] as $ec) {
                if ($ec["note_finale_ec"] !== null) {
                    $totalPoints += $ec["note_finale_ec"] * $ec["coef_ec"];
                    $totalCoefs  += $ec["coef_ec"];
                }
            }
            $etudiant["moyenne_ue"]         = $totalCoefs > 0 ? round($totalPoints / $totalCoefs, 2) : 0;
            $etudiant["moyenne_calculable"] = true;
        } else {
            $etudiant["moyenne_ue"]         = 0;
            $etudiant["moyenne_calculable"] = false;
        }

        $aExamenNonCompose = false;
        foreach ($etudiant["ec"] as $ec) {
            if ($ec["examen_non_compose"] ?? false) {
                $aExamenNonCompose = true;
                break;
            }
        }

        $etudiant["stats"] = [
            "nb_ec"                 => count($etudiant["ec"]),
            "moyenne_ue_formatee"   => $etudiant["moyenne_calculable"] ? number_format($etudiant["moyenne_ue"], 2) : "N/A",
            "total_coef_ue"         => array_sum(array_column($etudiant["ec"], "coef_ec")),
            "moyenne_calculable"    => $etudiant["moyenne_calculable"],
            "est_repechable"        => ($etudiant["moyenne_calculable"] && $etudiant["moyenne_ue"] < 10 && !$aExamenNonCompose),
            "non_repechable_raison" => !$etudiant["moyenne_calculable"] ? "moyenne_non_calculable"
                : ($aExamenNonCompose ? "non_compose_examen" : null)
        ];
    }

    return array_values($etudiants);
}
