<?php
require_once '../../../config.php';

$input     = json_decode(file_get_contents('php://input'), true);
$getAction = $_GET['action'] ?? null;
$postAction = $input['action'] ?? null;
$action    = $getAction ?? $postAction;

if (!$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Action parameter is required']);
    exit;
}

header('Content-Type: application/json');

if ($getAction) {
    switch ($action) {

        // ── Filières ──────────────────────────────────────────────────────────
        case 'getFilieres':
            $stmt = $pdo->query("
                SELECT DISTINCT f.id, f.filiere AS nom
                FROM filieres f
                JOIN options o ON o.idFilieres = f.id
                JOIN scolarite_inscription si ON si.idOption = o.id
                JOIN scolarite_inscription_pedagogique sip ON sip.idInscription = si.id
                JOIN scolarite_inscription_pedagogique_ue sipu ON sipu.idInscriptionPedagogique = sip.id
                JOIN vue_etudiants_complete vec ON vec.idUE = sipu.idUE
                ORDER BY f.filiere
            ");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ── Niveaux par filière (et cycle si fourni) ──────────────────────────
        case 'getNiveaux':
            $idFiliere = $_GET['idFiliere'] ?? null;
            $idCycle   = $_GET['idCycle']   ?? null;

            if (!$idFiliere) {
                echo json_encode(['success' => false, 'message' => 'idFiliere requis']);
                break;
            }

            $sql = "
                SELECT DISTINCT nf.id, nf.niveau AS nom, nf.idCycleFormation
                FROM niveauformation nf
                JOIN options o ON o.idNiveauFormation = nf.id AND o.idFilieres = :idFiliere
                JOIN scolarite_inscription si ON si.idOption = o.id
                JOIN scolarite_inscription_pedagogique sip ON sip.idInscription = si.id
                JOIN scolarite_inscription_pedagogique_ue sipu ON sipu.idInscriptionPedagogique = sip.id
                JOIN vue_etudiants_complete vec ON vec.idUE = sipu.idUE
            ";
            $params = [':idFiliere' => $idFiliere];
            if ($idCycle) {
                $sql .= " AND nf.idCycleFormation = :idCycle";
                $params[':idCycle'] = $idCycle;
            }
            $sql .= " ORDER BY nf.niveau";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ── Options par filière + niveau ──────────────────────────────────────
        case 'getOptions':
            $idFiliere = $_GET['idFiliere'] ?? null;
            $idNiveau  = $_GET['idNiveau']  ?? null;

            if (!$idFiliere || !$idNiveau) {
                echo json_encode(['success' => false, 'message' => 'idFiliere et idNiveau requis']);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT DISTINCT o.id, o.option AS nom
                FROM options o
                JOIN scolarite_inscription si ON si.idOption = o.id
                JOIN scolarite_inscription_pedagogique sip ON sip.idInscription = si.id
                JOIN scolarite_inscription_pedagogique_ue sipu ON sipu.idInscriptionPedagogique = sip.id
                JOIN vue_etudiants_complete vec ON vec.idUE = sipu.idUE
                WHERE o.idFilieres = :idFiliere AND o.idNiveauFormation = :idNiveau
                ORDER BY o.option
            ");
            $stmt->execute([':idFiliere' => $idFiliere, ':idNiveau' => $idNiveau]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ── Semestres disponibles pour un niveau/option ───────────────────────
        case 'getSemestres':
            $idOption = $_GET['idOption'] ?? null;
            $idNiveau = $_GET['idNiveau'] ?? null;

            $sql = "
                SELECT DISTINCT s.id, CONCAT('Semestre ',s.numInYear) AS nom
                FROM semestre s
                JOIN ue u ON u.id_semestre = s.id
            ";
            $params = [];
            if ($idOption || $idNiveau) {
                $sql .= "
                     JOIN maquette_ue mu ON mu.id_ue = u.id
                    JOIN maquette m ON m.id = mu.id_maquette
                    JOIN options o ON o.id = m.idOption
                    JOIN niveauformation niv ON niv.id = o.idNiveauFormation
                    WHERE o.idNiveauFormation = :idNiveau
                    AND m.idOption = :idOption
                ";
                $params[':idOption'] = $idOption;
                $params[':idNiveau'] = $idNiveau;
            }
            $sql .= " ORDER BY s.numero";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ── UEs disponibles dans vue_etudiants_complete ───────────────────────
        case 'getUEs':
            $idFiliere  = $_GET['idFiliere']  ?? null;
            $idNiveau   = $_GET['idNiveau']   ?? null;
            $idOption   = $_GET['idOption']   ?? null;
            $idSemestre = $_GET['idSemestre'] ?? null;

            $sql = "
        SELECT DISTINCT
            vec.idUE,
            vec.code_ue,
            vec.nom_ue,
            MAX(vec.sync_version) AS sync_version,
            COUNT(DISTINCT vec.matricule) AS nb_etudiants
        FROM vue_etudiants_complete vec
        JOIN ue u ON u.id = vec.idUE
        JOIN scolarite_inscription_pedagogique_ue sipu ON sipu.idUE = vec.idUE
        JOIN scolarite_inscription_pedagogique sip ON sip.id = sipu.idInscriptionPedagogique
        JOIN scolarite_inscription si ON si.id = sip.idInscription
        JOIN options o ON o.id = si.idOption
        WHERE 1=1
    ";
            $params = [];

            if ($idFiliere) {
                $sql .= " AND o.idFilieres = :idFiliere";
                $params[':idFiliere'] = $idFiliere;
            }
            if ($idNiveau) {
                $sql .= " AND o.idNiveauFormation = :idNiveau";
                $params[':idNiveau'] = $idNiveau;
            }
            if ($idOption) {
                $sql .= " AND si.idOption = :idOption";
                $params[':idOption'] = $idOption;
            }
            if ($idSemestre) {
                $sql .= " AND u.id_semestre = :idSemestre";
                $params[':idSemestre'] = $idSemestre;
            }

            $sql .= " GROUP BY vec.idUE, vec.code_ue, vec.nom_ue ORDER BY vec.code_ue";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $ues = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ── Vérification : toutes les UEs du semestre ont-elles délibéré ? ────
            $alerteSemestre = null;

            if ($idSemestre) {
                // UEs de la maquette pour ce semestre/filiere/niveau/option
                $sqlMaquette = "
            SELECT DISTINCT u.id AS idUE, u.code AS code_ue, u.nom AS nom_ue
            FROM ue u
            JOIN semestre sem ON sem.id = u.id_semestre
            JOIN maquette_ue mu ON mu.id_ue = u.id
            JOIN maquette m ON m.id = mu.id_maquette
            JOIN options o ON o.id = m.idOption
            WHERE sem.numInYear = :idSemestre
              AND m.idEtat = 3
        ";
                $paramsMaq = [':idSemestre' => $idSemestre];

                if ($idOption) {
                    $sqlMaquette .= " AND m.idOption = :idOption";
                    $paramsMaq[':idOption'] = $idOption;
                } elseif ($idNiveau) {
                    $sqlMaquette .= " AND o.idNiveauFormation = :idNiveau";
                    $paramsMaq[':idNiveau'] = $idNiveau;
                } elseif ($idFiliere) {
                    $sqlMaquette .= " AND o.idFilieres = :idFiliere";
                    $paramsMaq[':idFiliere'] = $idFiliere;
                }

                $stmtMaq = $pdo->prepare($sqlMaquette);
                $stmtMaq->execute($paramsMaq);
                $uesMaquette = $stmtMaq->fetchAll(PDO::FETCH_ASSOC);

                // IDs délibérés (présents dans vue_etudiants_complete)
                $idUEsDeliberees = array_column($ues, 'idUE');

                // UEs non délibérées
                $uesNonDeliberees = array_filter($uesMaquette, fn($u) => !in_array($u['idUE'], $idUEsDeliberees));
                $uesNonDeliberees = array_values($uesNonDeliberees);

                if (!empty($uesNonDeliberees)) {
                    $alerteSemestre = [
                        'has_alert'         => true,
                        'nb_maquette'       => count($uesMaquette),
                        'nb_deliberees'     => count($idUEsDeliberees),
                        'nb_non_deliberees' => count($uesNonDeliberees),
                        'ues_non_deliberees' => $uesNonDeliberees,
                        'message'           => count($uesNonDeliberees) . ' UE(s) du semestre n\'ont pas encore été délibérées.',
                    ];
                } else {
                    $alerteSemestre = [
                        'has_alert'     => false,
                        'nb_maquette'   => count($uesMaquette),
                        'nb_deliberees' => count($idUEsDeliberees),
                        'message'       => 'Toutes les UEs du semestre ont été délibérées.',
                    ];
                }
            }

            echo json_encode([
                'success'         => true,
                'data'            => $ues,
                'alerte_semestre' => $alerteSemestre,
            ]);
            break;

        // ── PV complet pour une UE ────────────────────────────────────────────
        case 'getPV':
            $idUE = $_GET['idUE'] ?? null;

            if (!$idUE) {
                echo json_encode(['success' => false, 'message' => 'idUE requis']);
                break;
            }

            // Infos UE + année
            $stmtUE = $pdo->prepare("
                SELECT DISTINCT
                    vec.idUE, vec.code_ue, vec.nom_ue,
                    vec.filiere, vec.niveau, vec.option_etudiant,
                    vec.idSession, sem.numInYear as semestre, sem.numero, fac.faculte, dep.departement
                FROM vue_etudiants_complete vec
                JOIN ue on ue.id = vec.idUE
                JOIN semestre sem ON sem.id = ue.id_semestre
                JOIN maquette_ue mu ON mu.id_ue = ue.id
                JOIN maquette m ON m.id = mu.id_maquette
                JOIN options opt ON opt.id = m.idOption
                JOIN filieres fil ON fil.id = opt.idFilieres
                JOIN departements dep ON dep.id = fil.idDepartements
                JOIN facultes fac ON fac.id = dep.idFacultes
                WHERE vec.idUE = :idUE
                AND vec.sync_version = (SELECT MAX(sync_version) FROM vue_etudiants_complete WHERE idUE = :idUE2)
                LIMIT 1
            ");
            $stmtUE->execute([':idUE' => $idUE, ':idUE2' => $idUE]);
            $ueInfo = $stmtUE->fetch(PDO::FETCH_ASSOC);

            if (!$ueInfo) {
                echo json_encode(['success' => false, 'message' => 'Aucune donnée synchronisée pour cette UE']);
                break;
            }

            // Année universitaire
            $annee = $pdo->query("
                SELECT annee_academique FROM scolarite_anneeuniversitaire
                WHERE id = (SELECT MAX(id) FROM scolarite_anneeuniversitaire)
            ")->fetchColumn();

            // Toutes les lignes EC (dernière sync_version)
            $stmtRows = $pdo->prepare("
                SELECT
                    vec.matricule, vec.prenom, vec.nom,
                    vec.idEC, vec.code_ec, vec.nom_ec,
                    vec.coefficient_ec, vec.credit_ec,
                    vec.note_initial, vec.note_final, vec.point_jury,
                    vec.source_note, vec.idRepechage,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM pedagogie_notes pn
                        WHERE pn.idEc = vec.idEC
                        AND pn.nature = 2
                        AND pn.idInscription = vec.idInscription
                        AND pn.non_compose = 1
                    ) THEN 1 ELSE 0 END AS non_compose
                FROM vue_etudiants_complete vec
                WHERE vec.idUE = :idUE
                AND vec.sync_version = (
                    SELECT MAX(sync_version) FROM vue_etudiants_complete WHERE idUE = :idUE2
                )
                ORDER BY vec.nom, vec.prenom, vec.idEC
            ");
            $stmtRows->execute([':idUE' => $idUE, ':idUE2' => $idUE]);
            $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);

            // ── Regrouper par étudiant ────────────────────────────────────────
            $etudiantsMap = [];
            $colonnesEC   = [];
            $ecIds        = [];

            foreach ($rows as $row) {
                $mat  = $row['matricule'];
                $idEC = $row['idEC'];

                if (!isset($etudiantsMap[$mat])) {
                    $etudiantsMap[$mat] = [
                        'matricule'   => $mat,
                        'prenom'      => $row['prenom'],
                        'nom'         => $row['nom'],
                        'ecs'         => [],
                        'est_repeche' => false,
                    ];
                }

                // Dédupliquer par idEC — garder la note repechage si elle existe
                if (!isset($etudiantsMap[$mat]['ecs'][$idEC]) || $row['source_note'] === 'repechage') {
                    $etudiantsMap[$mat]['ecs'][$idEC] = [
                        'idEC'           => $idEC,
                        'code_ec'        => $row['code_ec'],
                        'nom_ec'         => $row['nom_ec'],
                        'coefficient_ec' => floatval($row['coefficient_ec']),
                        'credit_ec'      => floatval($row['credit_ec']),
                        'note_initial'   => floatval($row['note_initial']),
                        'note_final'     => floatval($row['note_final']),
                        'point_jury'     => floatval($row['point_jury']),
                        'source_note'    => $row['source_note'],
                        'non_compose'    => (int)$row['non_compose'],
                    ];
                }

                if ($row['source_note'] === 'repechage') {
                    $etudiantsMap[$mat]['est_repeche'] = true;
                }

                if (!in_array($idEC, $ecIds)) {
                    $ecIds[]      = $idEC;
                    $colonnesEC[] = [
                        'idEC'   => $idEC,
                        'code'   => $row['code_ec'],
                        'nom'    => $row['nom_ec'],
                        'coef'   => floatval($row['coefficient_ec']),
                        'credit' => floatval($row['credit_ec']),
                    ];
                }
            }

            // Convertir ecs de map en liste
            foreach ($etudiantsMap as &$e) {
                $e['ecs'] = array_values($e['ecs']);
            }
            unset($e);

            $effectifAyantPasCompose = 0;

            // ── Calculer moyenne UE, obs, matières à reprendre ────────────────
            foreach ($etudiantsMap as $mat => &$etudiant) {
                $totalPoints = 0;
                $totalCoefs  = 0;
                $ecsEchoue   = [];
                $ecsInvalid  = [];
                $isValid     = true;

                foreach ($etudiant['ecs'] as $ec) {
                    $note = $ec['note_final'];
                    $coef = $ec['coefficient_ec'] ?: 1;
                    $totalPoints += $note * $coef;
                    $totalCoefs  += $coef;
                    if ($note < 10) {
                        $ecsEchoue[] = $ec['nom_ec'];
                    }
                    if ($ec['non_compose'] == 1) {
                        $isValid = false;
                        $ecsInvalid[] = $ec;
                    }
                }

                // Absent = non_compose = 1 sur TOUS les ECs
                $nbECs        = count($etudiant['ecs']);
                $nbNonCompose = count(array_filter($etudiant['ecs'], fn($ec) => $ec['non_compose'] == 1));
                $estAbsent    = $nbECs > 0 && $nbNonCompose === $nbECs;

                $etudiant['est_absent'] = $estAbsent;

                if (!$isValid) {
                    $effectifAyantPasCompose++;
                }

                $moy = $totalCoefs > 0 ? round($totalPoints / $totalCoefs, 2) : 0;
                $etudiant['moyenne_ue'] = $moy;

                if ($estAbsent) {
                    $etudiant['obs']                = 'Absent';
                    $etudiant['matieres_reprendre'] = [];
                } elseif (!$isValid && $moy >= 10) {
                    $etudiant['obs']                = 'Invalide';
                    $etudiant['matieres_reprendre'] = [];
                    $etudiant['matieres_invalide']  = $ecsInvalid;
                } elseif ($moy >= 10) {
                    $etudiant['obs']                = 'Validée';
                    $etudiant['matieres_reprendre'] = [];
                } else {
                    $etudiant['obs']                = 'Non validée';
                    $etudiant['matieres_reprendre'] = $ecsEchoue;
                }
            }
            unset($etudiant);

            // Trier par nom
            usort($etudiantsMap, fn($a, $b) => strcmp($a['nom'] . ' ' . $a['prenom'], $b['nom'] . ' ' . $b['prenom']));
            $etudiants = array_values($etudiantsMap);

            // ── VPC : Validation Par Compensation ─────────────────────────────
            // Récupérer id_nature et id_semestre de l'UE courante
            $stmtNature = $pdo->prepare("SELECT id_nature, id_semestre FROM ue WHERE id = :idUE");
            $stmtNature->execute([':idUE' => $idUE]);
            $ueNature = $stmtNature->fetch(PDO::FETCH_ASSOC);

            $vpcActif    = false;
            $nbVPC       = 0;
            $matriculesVPC = [];

            if ($ueNature && $ueNature['id_nature'] && $ueNature['id_semestre']) {
                $idNature   = $ueNature['id_nature'];
                $idSemestre = $ueNature['id_semestre'];

                // Toutes les UEs du même semestre et de même nature (y compris l'UE courante)
                $stmtUEsMemeNature = $pdo->prepare("
                    SELECT ue.id FROM maquette m
                    JOIN maquette_ue mu ON mu.id_maquette = m.id
                    JOIN ue ON ue.id = mu.id_ue
                    WHERE m.idEtat = 3 AND m.id = (SELECT m.id FROM maquette m
                              JOIN maquette_ue mu ON m.id = mu.id_maquette AND mu.id_ue = :idUE AND mu.statut = 1
                               )
                               AND ue.id_semestre = :idSemestre
                               AND ue.id_nature = :idNature
                ");
                $stmtUEsMemeNature->execute([':idUE' => $idUE, ':idSemestre' => $idSemestre, ':idNature' => $idNature]);
                $uesMemeNature = $stmtUEsMemeNature->fetchAll(PDO::FETCH_COLUMN);

                $nbUEsNature = count($uesMemeNature);

                if ($nbUEsNature > 1) {
                    // Pour chaque UE de la nature, récupérer la moyenne par étudiant
                    // (depuis vue_etudiants_complete, dernière sync_version par UE)
                    $placeholders = implode(',', array_fill(0, $nbUEsNature, '?'));
                    $stmtMoyUEs = $pdo->prepare("
                        SELECT
                            vec.matricule,
                            vec.idUE,
                            SUM(vec.note_final * vec.coefficient_ec) / SUM(vec.coefficient_ec) AS moy_ue
                        FROM vue_etudiants_complete vec
                        WHERE vec.idUE IN ($placeholders)
                          AND vec.sync_version = (
                              SELECT MAX(v2.sync_version)
                              FROM vue_etudiants_complete v2
                              WHERE v2.idUE = vec.idUE
                          )
                        GROUP BY vec.matricule, vec.idUE
                    ");
                    $stmtMoyUEs->execute($uesMemeNature);
                    $rowsMoy = $stmtMoyUEs->fetchAll(PDO::FETCH_ASSOC);

                    // Organiser : moyennesParNature[matricule][idUE] = moy
                    $moyParNature = [];
                    foreach ($rowsMoy as $r) {
                        $moyParNature[$r['matricule']][$r['idUE']] = floatval($r['moy_ue']);
                    }

                    // Pour chaque étudiant non validé dans l'UE courante → tester VPC
                    foreach ($etudiants as &$etudiant) {
                        if ($etudiant['obs'] !== 'Non validée' && $etudiant['obs'] !== 'Invalide') continue;
                        if ($etudiant['obs'] == 'Invalide') continue;
                        if ($etudiant['est_repeche']) continue;
                        $mat = $etudiant['matricule'];
                        $moyEtudiantParUE = $moyParNature[$mat] ?? [];

                        // Vérifier que l'étudiant a une note dans toutes les UEs de la nature
                        if (count($moyEtudiantParUE) < $nbUEsNature) continue;

                        // Moyenne de compensation = moyenne des moyennes de toutes les UEs de la nature
                        $sommeMoy = array_sum($moyEtudiantParUE);
                        $moyComp  = $sommeMoy / $nbUEsNature;
                        $nbNonCompose = count(array_filter($etudiant['ecs'], fn($ec) => $ec['non_compose'] == 1));
                        if($nbNonCompose > 0) continue;
                        if ($moyComp >= 10) {
                            $etudiant['obs']               = 'VPC';
                            $etudiant['matieres_reprendre'] = [];
                            $etudiant['est_vpc']           = true;
                            $etudiant['moy_compensation']  = round($moyComp, 2);
                            $matriculesVPC[]               = $mat;
                            $nbVPC++;
                        }
                    }
                    unset($etudiant);
                    $vpcActif = true;
                }
            }

            // ── Statistiques ──────────────────────────────────────────────────
            $nbTotal              = count($etudiants);
            $nbAbsents           = count(array_filter($etudiants, fn($e) => $e['obs'] === 'Absent'));
            $nbInvalide           = count(array_filter($etudiants, fn($e) => $e['obs'] === 'Invalide'));
            $effectifAyantCompose = $nbTotal - $nbAbsents;

            // Base de calcul = présents (ni absents)
            $etudiantsPresents = array_filter($etudiants, fn($e) => $e['obs'] !== 'Absent');
            $nbPresents        = count($etudiantsPresents);

            $nbValides  = count(array_filter($etudiantsPresents, fn($e) => $e['obs'] === 'Validée'));
            $nbVPCFinal = count(array_filter($etudiantsPresents, fn($e) => $e['obs'] === 'VPC'));
            $nbNonValid = count(array_filter($etudiantsPresents, fn($e) => $e['obs'] === 'Non validée'));
            $nbRepeches = count(array_filter($etudiantsPresents, fn($e) => $e['est_repeche']));
            $tauxReuss  = $nbPresents > 0 ? round(($nbValides + $nbVPCFinal) / $nbPresents * 100, 1) : 0;
            echo json_encode([
                'success'    => true,
                'ueInfo'     => $ueInfo,
                'annee'      => $annee,
                'colonnesEC' => $colonnesEC,
                'etudiants'  => $etudiants,
                'stats' => [
                    'nbTotal'              => $nbTotal,
                    'nbAbsents'            => $nbAbsents,
                    'nbPresents'           => $nbPresents,
                    'nbValides'            => $nbValides,
                    'nbVPC'                => $nbVPCFinal,
                    'nbNonValid'           => $nbNonValid,
                    'nbInvalide'           => $nbInvalide,
                    'nbRepeches'           => $nbRepeches,
                    'tauxReuss'            => $tauxReuss,
                    'effectifAyantCompose' => $effectifAyantCompose,
                ],
            ]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Action non trouvée']);
            break;
    }
}
