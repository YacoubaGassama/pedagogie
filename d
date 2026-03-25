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

            if (!$data || !isset($data['idUE']) || !isset($data['simulations']) || !isset($data['seuil'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Données invalides']);
                break;
            }

            $idUE        = $data['idUE'];
            $simulations = $data['simulations'];
            $seuil       = $data['seuil'];
            $barre       = floatval($seuil);
            $campagne    = date('Y') . '-S' . (date('n') <= 6 ? '1' : '2');
            $idUser      = $_SESSION['idUser'] ?? 0;
            $idSem       = $data['idSemestre'] ?? null;

            try {
                $pdo->beginTransaction();

                // Désactiver les repêchages précédents
                $stmtUpdate = $pdo->prepare("UPDATE repechage SET statut = 0 WHERE idUe = :idUE AND statut = 1");
                $stmtUpdate->execute([':idUE' => $idUE]);

                // Annuler les historiques liés aux repêchages désactivés
                $stmtAnnuler = $pdo->prepare("
    UPDATE repechage_historique rh
    JOIN repechage rep ON rep.idRepechage = rh.idRepechage
    SET rh.statut = 'annule'
    WHERE rep.idUe     = :idUE
      AND rep.statut   = 0
      AND rh.statut    = 'applique'
");
                $stmtAnnuler->execute([':idUE' => $idUE]);
                // Récupérer l'année universitaire active
                $stmtAnnee = $pdo->prepare("SELECT MAX(id) AS idAnnee FROM scolarite_anneeuniversitaire");
                $stmtAnnee->execute();
                $idAnnee = $stmtAnnee->fetchColumn();

                if (!$idAnnee) {
                    throw new Exception("Aucune année universitaire active trouvée");
                }
                // Enregistrer le nouveau repêchage
                $sqlRep = "INSERT INTO repechage (idUe, idSem, idAnnee, barre, strategeDeCalcul, pasArrondi, lockIfNoteSup10, campagne, idUser, dateCreation, statut) 
                   VALUES (:idUe, :idSem, :idAnnee, :barre, :strategy, :rounding_step, :lock_ge10, :campagne, :idUser, NOW(), 1)";
                $stmtRep = $pdo->prepare($sqlRep);
                $stmtRep->execute([
                    ':idUe'          => $idUE,
                    ':idSem'         => $idSem,
                    ':idAnnee'       => $idAnnee,
                    ':barre'         => $barre,
                    ':strategy'      => $data['strategy']      ?? 'neutral',
                    ':rounding_step' => floatval($data['rounding_step'] ?? 0.01),
                    ':lock_ge10'     => $data['lock_ge10']     ?? false,
                    ':campagne'      => $campagne,
                    ':idUser'        => $idUser
                ]);
                $idRepechage = $pdo->lastInsertId();

                // Enregistrer chaque simulation
                $notesModifiees   = 0;
                $resultats        = [];
                $etudiantsTraites = 0;

                foreach ($simulations as $simulation) {
                    try {
                        $dataRepêchage                  = préparerDonnéesRepêchage($pdo, $simulation, $idUE, 1, $seuil);
                        $dataRepêchage['idUtilisateur'] = $_SESSION['idUser'] ?? null;
                        $dataRepêchage['idRepechage']            = $idRepechage;
                        $resultat                       = enregistrerRepêchage($pdo, $dataRepêchage);
                        $resultats[]                    = $resultat;

                        if (isset($resultat['nb_ec_repêchés'])) {
                            $notesModifiees += $resultat['nb_ec_repêchés'];
                        }
                        $etudiantsTraites++;
                    } catch (Exception $e) {
                        throw new Exception("Erreur pour l'étudiant {$simulation['matricule']}: " . $e->getMessage());
                    }
                }

                // Sync vue dans la même transaction
                $syncResult = syncVueEtudiantsParUE($pdo, $idUE, true, false);

                // Si la sync échoue on annule tout
                if (!$syncResult['success']) {
                    throw new Exception("Erreur lors de la synchronisation de la vue : " . $syncResult['message']);
                }

                $pdo->commit();

                echo json_encode([
                    'success'      => true,
                    'message'      => "Repêchage applique avec succès. $notesModifiees note(s) modifiée(s) pour $etudiantsTraites étudiant(s).",
                    'idRepechage'  => $idRepechage,
                    'details'      => $resultats,
                    'sync'         => [
                        'success'      => $syncResult['success'],
                        'rows'         => $syncResult['rows']         ?? 0,
                        'sync_version' => $syncResult['sync_version'] ?? null,
                        'message'      => $syncResult['message']      ?? null
                    ]
                ]);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
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

        case 'lancerDeliberationUE':
            $idUE = $input['idUE'] ?? null;

            if (!$idUE) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'idUE manquant']);
                break;
            }

            $idAnnee  = $pdo->query("SELECT MAX(id) FROM scolarite_anneeuniversitaire")->fetchColumn();
            $idSession = 1;
            $campagne = date('Y') . '-S' . (date('n') <= 6 ? '1' : '2');
            $idUser   = $_SESSION['idUser'] ?? 0;

            // Récupérer le semestre de l'UE
            $stmtSem = $pdo->prepare("SELECT id_semestre FROM ue WHERE id = :idUE");
            $stmtSem->execute([':idUE' => $idUE]);
            $idSem = $stmtSem->fetchColumn() ?: null;

            $erreurs  = [];
            $resultats = [];

            try {
                $pdo->beginTransaction();

                // 1. Vérifier s'il existe un repêchage actif pour cette UE, cette année
                $stmtCheck = $pdo->prepare("
            SELECT idRepechage FROM repechage 
            WHERE idUe    = :idUE 
              AND statut  = 1 
              AND idAnnee = :idAnnee
            LIMIT 1
        ");
                $stmtCheck->execute([':idUE' => $idUE, ':idAnnee' => $idAnnee]);
                $repechageActif = $stmtCheck->fetchColumn();

                if ($repechageActif) {
                    // 2. Annuler les historiques liés
                    $stmtAnnulerReph = $pdo->prepare("
                UPDATE repechage_historique rh
                JOIN repechage rep ON rep.idRepechage = rh.idRepechage
                SET rh.statut = 'annule'
                WHERE rep.idUe   = :idUE
                  AND rep.statut = 1
                  AND rh.statut  = 'applique'
            ");
                    $stmtAnnulerReph->execute([':idUE' => $idUE]);

                    // 3. Désactiver le repêchage
                    $stmtDesact = $pdo->prepare("
                UPDATE repechage 
                SET statut = 0 
                WHERE idUe = :idUE AND statut = 1
            ");
                    $stmtDesact->execute([':idUE' => $idUE]);
                }

                // 4. Insérer la nouvelle délibération sans repêchage
                $stmtRep = $pdo->prepare("
            INSERT INTO repechage (idUe, idSem, idSess, idAnnee, barre, strategeDeCalcul, pasArrondi, lockIfNoteSup10, campagne, idUser, dateCreation, statut)
            VALUES (:idUe, :idSem, :idSess, :idAnnee, NULL, 'delibere_sans_repechage', NULL, 0, :campagne, :idUser, NOW(), 1)
        ");
                $stmtRep->execute([
                    ':idUe'     => $idUE,
                    ':idSem'    => $idSem,
                    ':idSess'    => $idSession,
                    ':idAnnee'  => $idAnnee,
                    ':campagne' => $campagne,
                    ':idUser'   => $idUser
                ]);

                // 5. Sync depuis pedagogie_notes uniquement
                $syncResult = syncVueEtudiantsParUE($pdo, $idUE, false, false);

                if (!$syncResult['success']) {
                    throw new Exception($syncResult['message']);
                }

                $pdo->commit();

                echo json_encode([
                    'success'          => true,
                    'message'          => "Délibération lancée avec succès pour l'UE #$idUE.",
                    'repechageAnnule'  => (bool)$repechageActif,
                    'rows'             => $syncResult['rows'],
                    'sync_version'     => $syncResult['sync_version'] ?? null
                ]);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur : ' . $e->getMessage()
                ]);
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
            $ues = getMaquetteUEs($pdoM, $idcycle, $idNiveauFormation, $idOption, $idSemestre);
            header('Content-Type: application/json');
            echo json_encode($ues);

            break;
        case 'getEtudiantByUE':
            $idUE = $_GET['idUE'] ?? null;
            $session_id = $_GET['session_id'] ?? 1;
            if ($idUE) {
                $ecs = getEtudiantByUE($pdoM, $idUE, $session_id);
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
                $stats = getStatUE($pdoM, $ueId);
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
                    $data = appliquerRepechageUE($pdoM, $idUE, $minMoy, $strategy, $lockGE10, $displayStep);
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
                $stats = verifierCompletudeEvaluationsUE($pdoM, $idUE, $session_id);
                echo json_encode(['success' => true, 'stats' => $stats]);
            } else {
                echo json_encode(['success' => false, 'message' => 'ID UE manquant']);
            }
            break;
        case 'getStatistiquesUE':
            $idUE = $_GET['idUE'] ?? null;
            if ($idUE) {
                $stats = getStatistiquesCompletes($pdoM, $idUE);
                echo json_encode(['success' => true, 'stats' => $stats]);
            } else {
                echo json_encode(['success' => false, 'message' => 'ID UE manquant']);
            }
            break;
        case 'verifierRepêchage':
            $idUE = $_GET['idUE'] ?? null;
            if ($idUE) {
                $sql = "SELECT * FROM repechage 
                WHERE idUe = :idUe AND statut = 1
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
                echo json_encode(['success' => false, 'message' => 'ID UE manquant']);
                break;
            }

            try {

                /* =====================================================
            1. UE + dernier repêchage
        ===================================================== */
                $sqlUE = "
            SELECT 
                ue.id, ue.code, ue.nom AS nomUE, ue.nombre_credit,
                sem.numero AS semestre,
                r.idRepechage       AS idRepechage,
                r.barre,
                r.campagne,
                r.dateCreation,
                r.strategeDeCalcul,
                r.pasArrondi,
                r.lockIfNoteSup10
            FROM ue
            JOIN semestre sem ON ue.id_semestre = sem.id
            LEFT JOIN repechage r 
                ON r.idUe = ue.id
                AND r.dateCreation = (
                    SELECT MAX(r2.dateCreation) 
                    FROM repechage r2 
                    WHERE r2.idUe = ue.id and r2.statut = 1
                )
                AND r.statut = 1
            WHERE ue.id = :idUE
        ";
                $stmt = $pdo->prepare($sqlUE);
                $stmt->execute([':idUE' => $idUE]);
                $ue = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$ue) {
                    echo json_encode(['success' => false, 'message' => 'UE introuvable']);
                    break;
                }

                /* =====================================================
            2. TOUT DEPUIS vue_etudiants_complete
        ===================================================== */
                $sqlVue = "
            SELECT 
        vec.matricule,
        vec.prenom,
        vec.nom,
        vec.filiere,
        vec.niveau,
        vec.option_etudiant,
        vec.idInscription,
        vec.idEC,
        vec.code_ec,
        vec.nom_ec,
        vec.coefficient_ec,
        vec.note_initial,
        vec.note_final,
        vec.point_jury,
        vec.source_note,
        vec.idRepechage,
        vec.sync_version,
        r.barre AS barre_repechage,
        vec.sync_at
            FROM vue_etudiants_complete vec
            LEFT JOIN repechage r ON r.idRepechage = vec.idRepechage AND r.statut = 1
            WHERE vec.idUE = :idUE AND vec.sync_version = (
                SELECT MAX(sync_version) 
                FROM vue_etudiants_complete 
                WHERE idUE = :idUE2
            )
            ORDER BY vec.matricule, vec.idEC
        ";
                $stmt = $pdo->prepare($sqlVue);
                $stmt->execute([':idUE' => $idUE, ':idUE2' => $idUE]);
                $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                /* =====================================================
            3. REGROUPEMENT PAR ÉTUDIANT
        ===================================================== */
                $etudiantsMap   = [];
                $totalPointsJury = 0;
                $barreRepêchage = $ue['barre'] ?? null;
                foreach ($lignes as $ligne) {
                    $mat = $ligne['matricule'];

                    if (!isset($etudiantsMap[$mat])) {
                        $etudiantsMap[$mat] = [
                            'matricule'       => $ligne['matricule'],
                            'nom'             => trim(($ligne['prenom'] ?? '') . ' ' . ($ligne['nom'] ?? '')),
                            'filiere'         => $ligne['filiere'],
                            'niveau'          => $ligne['niveau'],
                            'option_etudiant' => $ligne['option_etudiant'],
                            'idInscription'   => $ligne['idInscription'],
                            'est_repeche'     => false,
                            'point_jury'      => 0,
                            'totalPoints'     => 0,
                            'totalCoefs'      => 0,
                            'ec'              => []
                        ];
                    }

                    $noteFinale = (float)($ligne['note_final']     ?? 0);
                    $coef       = (float)($ligne['coefficient_ec'] ?? 1);

                    $etudiantsMap[$mat]['totalPoints'] += $noteFinale * $coef;
                    $etudiantsMap[$mat]['totalCoefs']  += $coef;

                    if ($ligne['source_note'] === 'repechage') {
                        $etudiantsMap[$mat]['est_repeche'] = true;
                    }

                    $pointJury = (float)($ligne['point_jury'] ?? 0);
                    if ($pointJury > 0) {
                        $etudiantsMap[$mat]['point_jury'] += $pointJury;
                        $totalPointsJury                  += $pointJury;
                    }

                    $etudiantsMap[$mat]['ec'][] = [
                        'idEC'           => $ligne['idEC'],
                        'code_ec'        => $ligne['code_ec'],
                        'nom_ec'         => $ligne['nom_ec'],
                        'coefficient_ec' => $coef,
                        'note_initial'   => (float)($ligne['note_initial'] ?? 0),
                        'note_final'     => $noteFinale,
                        'point_jury'     => $pointJury,
                        'source_note'    => $ligne['source_note'],
                    ];
                }

                /* =====================================================
            4. CALCUL MOYENNE UE + STATUT FINAL
        ===================================================== */
                $tousEtudiants = [];

                foreach ($etudiantsMap as $etudiant) {
                    $moyenneUE = $etudiant['totalCoefs'] > 0
                        ? round($etudiant['totalPoints'] / $etudiant['totalCoefs'], 2)
                        : 0;

                    $tousEtudiants[] = [
                        'matricule'          => $etudiant['matricule'],
                        'nom'                => $etudiant['nom'],
                        'filiere'            => $etudiant['filiere'],
                        'niveau'             => $etudiant['niveau'],
                        'option_etudiant'    => $etudiant['option_etudiant'],
                        'moyenne_ue'         => $moyenneUE,
                        'est_repeche'        => $etudiant['est_repeche'],
                        'point_jury'         => round($etudiant['point_jury'], 2),
                        'ec'                 => $etudiant['ec']
                    ];
                }

                // Trier par nom
                usort($tousEtudiants, fn($a, $b) => strcmp($a['nom'], $b['nom']));

                /* =====================================================
            5. RETURN
        ===================================================== */
                $ue['dateDernierRepêchage'] = $ue['dateCreation']
                    ? date('d/m/Y H:i', strtotime($ue['dateCreation']))
                    : null;
                $ue['totalPointsJury'] = round($totalPointsJury, 2);

                echo json_encode([
                    'success'          => true,
                    'ue'               => $ue,
                    'total_etudiants'  => count($tousEtudiants),
                    'etudiants'        => $tousEtudiants
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }

            break;
        case 'test':
            echo json_encode(getLastVersionOfNote($pdo, $_GET['idUE'], 1));
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
    rep.idUe as repechage,
    vec.id as estDelibiere
FROM maquette_ue mue
JOIN ue ON mue.id_ue = ue.id
JOIN semestre sem ON ue.id_semestre = sem.id
JOIN maquette m ON mue.id_maquette = m.id
JOIN options o ON m.idOption = o.id
JOIN niveauformation niv on o.idNiveauFormation = niv.id
JOIN cycleformation cyc ON cyc.id = niv.idCycleFormation
LEFT JOIN repechage rep on rep.idUe = ue.id AND rep.statut = 1
LEFT JOIN vue_etudiants_complete vec on vec.idUE = ue.id

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
            if ($ec["examen_non_compose_flag"] ?? false) {
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
                // && $etudiant["moyenne_ue"] >= 7
                && !$aExamenNonCompose  // ← nouvelle condition
            ),
            "non_repechable_raison" => $raisonNonRepechable
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
        if (empty($data['idInscription'])) {
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
            ':idUE'          => $data['idUE']
        ]);

        if ($stmtCheckInscription->fetchColumn() == 0) {
            throw new Exception("L'étudiant n'est pas inscrit à cette UE");
        }

        // 4. Enregistrer la moyenne UE
        $sqlMoyenne = "INSERT INTO moyenne_ue 
                      (idInscription, idUE, idSession, moyenne, moyenne_initial, methode_calcul, nb_ec, date_calcul)
                      VALUES (:idInscription, :idUE, :idSession, :moyenne, :moyenne_initial, :methode, :nb_ec, NOW())
                      ON DUPLICATE KEY UPDATE
                      moyenne          = VALUES(moyenne),
                      moyenne_initial  = VALUES(moyenne_initial),
                      methode_calcul   = VALUES(methode_calcul),
                      nb_ec            = VALUES(nb_ec),
                      date_calcul      = NOW()";

        $stmtMoyenne = $pdo->prepare($sqlMoyenne);
        $stmtMoyenne->execute([
            ':idInscription'  => $data['idInscription'],
            ':idUE'           => $data['idUE'],
            ':idSession'      => $data['idSession'],
            ':moyenne'        => $data['moyenne_final'],
            ':moyenne_initial' => $data['moyenne_initial'],
            ':methode'        => $data['methode_calcul'] ?? 'note_directe',
            ':nb_ec'          => $data['nb_ec'] ?? count($data['ec_repêchés'])
        ]);

        $idMoyenneUE = $pdo->lastInsertId();

        if (!$idMoyenneUE || $idMoyenneUE == 0) {
            $sqlGetId = "SELECT id FROM moyenne_ue 
                        WHERE idInscription = :idInscription 
                        AND idUE            = :idUE 
                        AND idSession       = :idSession";
            $stmtGetId = $pdo->prepare($sqlGetId);
            $stmtGetId->execute([
                ':idInscription' => $data['idInscription'],
                ':idUE'          => $data['idUE'],
                ':idSession'     => $data['idSession']
            ]);
            $result      = $stmtGetId->fetch(PDO::FETCH_ASSOC);
            $idMoyenneUE = $result['id'] ?? null;

            if (!$idMoyenneUE) {
                throw new Exception("Impossible de récupérer l'ID de la moyenne UE");
            }
        }

        // 5. Historisation + insertion pour chaque EC
        $sqlAnnuler = "UPDATE repechage_historique 
                       SET statut = 'annule'
                       WHERE idInscription = :idInscription 
                       AND idUE            = :idUE 
                       AND idEC            = :idEC 
                       AND idSession       = :idSession
                       AND statut          = 'applique'";
        $stmtAnnuler = $pdo->prepare($sqlAnnuler);

        $sqlInsert = "INSERT INTO repechage_historique
             (idInscription, idUE, idEC, idSession, idMoyenneUE, idRepechage,
              note_initial, note_final, point_jury, coef, credit,
              idUtilisateur, statut, commentaire, date_repechage)
             VALUES 
             (:idInscription, :idUE, :idEC, :idSession, :idMoyenneUE, :idRepechage,
              :note_initial, :note_final, :point_jury, :coef, :credit,
              :idUtilisateur, 'applique', :commentaire, NOW())";
        $stmtInsert = $pdo->prepare($sqlInsert);

        $nbEcRepêchés = 0;
        $ecsTraites   = [];

        foreach ($data['ec_repêchés'] as $ec) {
            if (!isset($ec['idEC'], $ec['note_initial'], $ec['note_final'], $ec['point_jury'], $ec['coef'])) {
                error_log("EC repêché invalide : " . json_encode($ec));
                continue;
            }

            $key = $ec['idEC'] . '_' . $data['idSession'];
            if (in_array($key, $ecsTraites)) continue;
            $ecsTraites[] = $key;

            // // Annuler l'enregistrement applique précédent s'il existe
            // $stmtAnnuler->execute([
            //     ':idInscription' => $data['idInscription'],
            //     ':idUE'          => $data['idUE'],
            //     ':idEC'          => $ec['idEC'],
            //     ':idSession'     => $data['idSession']
            // ]);

            // Toujours insérer un nouvel enregistrement 'applique'
            $stmtInsert->execute([
                ':idInscription' => $data['idInscription'],
                ':idUE'          => $data['idUE'],
                ':idEC'          => $ec['idEC'],
                ':idSession'     => $data['idSession'],
                ':idMoyenneUE'   => $idMoyenneUE,
                ':idRepechage'   => $data['idRepechage'] ?? null,
                ':note_initial'  => $ec['note_initial'],
                ':note_final'    => $ec['note_final'],
                ':point_jury'    => $ec['point_jury'],
                ':coef'          => $ec['coef'],
                ':credit'        => $ec['credit'] ?? ($ec['note_final'] >= 10 ? floor($ec['coef'] * 2) : 0),
                ':commentaire'   => $data['commentaire'] ?? 'Repêchage automatique',
                ':idUtilisateur' => $data['idUtilisateur'] ?? ($_SESSION['idUser'] ?? null)
            ]);

            $nbEcRepêchés++;
        }

        // 6. Mettre à jour la moyenne dans moyenne_ue
        if ($nbEcRepêchés > 0) {
            $sqlUpdateMoyenne = "UPDATE moyenne_ue 
                                 SET moyenne     = 10.00,
                                     date_calcul = NOW()
                                 WHERE id = :idMoyenneUE";
            $stmtUpdateMoyenne = $pdo->prepare($sqlUpdateMoyenne);
            $stmtUpdateMoyenne->execute([':idMoyenneUE' => $idMoyenneUE]);
        }

        return [
            'success'        => true,
            'idMoyenneUE'    => $idMoyenneUE,
            'nb_ec_repêchés' => $nbEcRepêchés,
            'message'        => "Repêchage enregistré avec succès. $nbEcRepêchés EC modifiés."
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
    $sql = "SELECT * FROM vue_etudiants_complete vec";
    $sql .= "WHERE vec.idUE = :idUE AND vec.sync_version = (SELECT MAX(sync_version) FROM vue_etudiants_complete WHERE idUE = :idUE2)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':idUE' => $idUE, ':idUE2' => $idUE]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                sip.id          AS idInscription
            FROM scolarite_inscription_pedagogique_ue sipu
            JOIN scolarite_inscription_pedagogique sip ON sipu.idInscriptionPedagogique = sip.id
            JOIN scolarite_etudiants se ON sip.matricule = se.matricule
            JOIN scolarite_inscription si ON sip.idInscription = si.id
            
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
            SELECT ec.id, ec.code, ec.nombre_credit, niv.niveau, fil.filiere, opt.option
            FROM ec
            JOIN ue ON ue.id = ec.id_ue
            JOIN maquette_ue mu ON mu.id_ue = ue.id
            JOIN maquette m ON m.id = mu.id_maquette
            JOIN options opt ON opt.id = m.idOption
            JOIN niveauformation niv ON niv.id = opt.idNiveauFormation
            JOIN filieres fil on fil.id = opt.idFilieres
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
                    ':filiere'         => $ecInfo['filiere'],
                    ':niveau'          => $ecInfo['niveau'],
                    ':option_etudiant' => $ecInfo['option'],
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

    // 0c. Récupérer les étudiants repêchés (ceux qui ont un enregistrement 'applique')
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
              AND reph.statut   = 'applique'
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
            se.nom              AS nomEtudiant, 
            ec.id               AS ec_id,
            sip.id              AS idInscription,
            ec.nom              AS nomEc,
            ec.coefficient      AS coef_ec,
            pn.note, 
            pn.nature,
            pn.non_compose,
            pn.justifier,
            CASE 
                WHEN bn.idNature = 2 THEN 'examen'
                ELSE 'devoir'
            END AS type_evaluation
        FROM scolarite_inscription_pedagogique_ue sipu
        JOIN scolarite_inscription_pedagogique sip ON sipu.idInscriptionPedagogique = sip.id
        JOIN scolarite_etudiants se ON sipu.matricule = se.matricule
        JOIN scolarite_inscription si ON sip.idInscription = si.id
        JOIN pedagogie_notes pn ON sip.id = pn.idInscription
        JOIN ec ON ec.id = pn.idEc
		JOIN bordereau_note bn ON pn.idDevoir = bn.idDevoir
        WHERE pn.idUe       = :idUE 
          AND sip.statut    = 1 
          AND bn.session_id = :session_id
          AND pn.idAnnee    = :idAnnee
        GROUP BY se.matricule, ec.id, pn.idDevoir
        ORDER BY se.matricule, ec.id, bn.idNature
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
                $moyenneExamen         = array_sum($ecData["examens"]);
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

            if ($ecData["examen_non_compose"]) {
                $ecData["note_finale_ec"] = 0;
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
            if ($ecData['examen_non_compose']) {
                $noteFinale               = 0;
            } else {
                $noteFinale               = ($moyenneDevoir * 0.4) + ($moyenneExamen * 0.6);
            }
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
            if ($ec["examen_non_compose_flag"] ?? false) {
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
            se.nom              AS nomEtudiant, 
            ec.id               AS ec_id,
            sip.id              AS idInscription,
            ec.nom              AS nomEc,
            ec.coefficient      AS coef_ec,
            pn.note, 
            pn.nature,
            pn.non_compose,
            pn.justifier,
            CASE 
                WHEN bn.idNature = 2 THEN 'examen'
                ELSE 'devoir'
            END AS type_evaluation
        FROM scolarite_inscription_pedagogique_ue sipu
        JOIN scolarite_inscription_pedagogique sip ON sipu.idInscriptionPedagogique = sip.id
        JOIN scolarite_etudiants se ON sipu.matricule = se.matricule
        JOIN scolarite_inscription si ON sip.idInscription = si.id
        JOIN pedagogie_notes pn ON sip.id = pn.idInscription
        JOIN ec ON ec.id = pn.idEc
		JOIN bordereau_note bn ON pn.idDevoir = bn.idDevoir
        WHERE pn.idUe       = :idUE 
          AND sip.statut    = 1 
          AND bn.session_id = :session_id
          AND pn.idAnnee    = :idAnnee
        GROUP BY se.matricule, ec.id, pn.idDevoir
        ORDER BY se.matricule, ec.id, bn.idNature
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

            if (!$ecData["a_examen"]) {
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
            if ($ecData['examen_non_compose']) {
                $ecData['note_finale_ec'] = 0;
            } else {
                $ecData["note_finale_ec"] = round(($moyenneDevoir * 0.4) + ($moyenneExamen * 0.6), 2);
            }
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
            if ($ec["examen_non_compose_flag"] ?? false) {
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
