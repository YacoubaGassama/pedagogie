<?php
require_once '../../../config.php';

$input      = json_decode(file_get_contents('php://input'), true);
$getAction  = $_GET['action'] ?? null;
$postAction = $input['action'] ?? null;
$action     = $getAction ?? $postAction;

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

        // ── Niveaux ───────────────────────────────────────────────────────────
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

        // ── Options ───────────────────────────────────────────────────────────
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

        // ── Semestres disponibles ─────────────────────────────────────────────
        case 'getSemestres':
            $idOption = $_GET['idOption'] ?? null;
            $idNiveau = $_GET['idNiveau'] ?? null;

            $sql = "
                SELECT DISTINCT s.id, CONCAT('Semestre ',s.numInYear) AS nom, s.numInYear
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

        // ── PV Semestre ───────────────────────────────────────────────────────
        case 'getPV':
            $idSemestre = $_GET['idSemestre'] ?? null;
            $idOption   = $_GET['idOption']   ?? null;
            $idNiveau   = $_GET['idNiveau']   ?? null;

            if (!$idSemestre) {
                echo json_encode(['success' => false, 'message' => 'idSemestre requis']);
                break;
            }

            // ── Vérification : UEs maquette vs UEs délibérées ─────────────────
            // Toutes les UEs du semestre dans la maquette
            $sqlMaquette = "
                SELECT u.id AS idUE, u.code as code_ue, u.nom as nom_ue
                FROM ue u
                WHERE u.id_semestre = :idSemestre
            ";
            $paramsMaq = [':idSemestre' => $idSemestre];
            if ($idOption) {
                $sqlMaquette .= "
                    AND EXISTS (
                        SELECT 1 FROM scolarite_inscription_pedagogique_ue sipu
                        JOIN scolarite_inscription_pedagogique sip ON sip.id = sipu.idInscriptionPedagogique
                        JOIN maquette_ue mu ON mu.id_ue = sipu.idUE
                        JOIN maquette m ON m.id = mu.id_maquette AND m.idEtat = 3
                        JOIN options o ON o.id = m.idOption
                        WHERE sipu.idUE = u.id AND o.id = :idOption
                    )
                ";
                $paramsMaq[':idOption'] = $idOption;
            } elseif ($idNiveau) {
                $sqlMaquette .= "
                    AND EXISTS (
                        SELECT 1 FROM scolarite_inscription_pedagogique_ue sipu
                        JOIN scolarite_inscription_pedagogique sip ON sip.id = sipu.idInscriptionPedagogique
                        JOIN maquette_ue mu ON mu.id_ue = sipu.idUE
                        JOIN maquette m ON m.id = mu.id_maquette AND m.idEtat = 3
                        JOIN options o ON o.id = m.idOption
                        WHERE sipu.idUE = u.id AND o.idNiveauFormation = :idNiveau
                    )
                ";
                $paramsMaq[':idNiveau'] = $idNiveau;
            }
            $sqlMaquette .= " ORDER BY u.code";
            $stmtMaq = $pdo->prepare($sqlMaquette);
            $stmtMaq->execute($paramsMaq);
            $uesMaquette = $stmtMaq->fetchAll(PDO::FETCH_ASSOC);
            $idUEsMaquette = array_column($uesMaquette, 'idUE');

            // UEs effectivement délibérées (présentes dans vue_etudiants_complete)
            $uesDeliberees = [];
            if (!empty($idUEsMaquette)) {
                $ph = implode(',', array_fill(0, count($idUEsMaquette), '?'));
                $stmtDelib = $pdo->prepare("
                    SELECT DISTINCT idUE
                    FROM vue_etudiants_complete
                    WHERE idUE IN ($ph)
                ");
                $stmtDelib->execute($idUEsMaquette);
                $uesDeliberees = array_column($stmtDelib->fetchAll(PDO::FETCH_ASSOC), 'idUE');
            }

            $idUEsNonDeliberees = array_diff($idUEsMaquette, $uesDeliberees);

            // Si des UEs ne sont pas délibérées, on bloque et on retourne le détail
            if (!empty($idUEsNonDeliberees)) {
                $uesManquantes = array_values(array_filter($uesMaquette, function ($ue) use ($idUEsNonDeliberees) {
                    return in_array($ue['idUE'], $idUEsNonDeliberees);
                }));
                echo json_encode([
                    'success'          => false,
                    'non_deliberees'   => true,
                    'nb_maquette'      => count($idUEsMaquette),
                    'nb_deliberees'    => count($uesDeliberees),
                    'nb_manquantes'    => count($idUEsNonDeliberees),
                    'ues_manquantes'   => $uesManquantes,
                    'message'          => count($idUEsNonDeliberees) . ' UE(s) du semestre n\'ont pas encore été délibérées.',
                ]);
                break;
            }

            // ── Infos semestre + contexte ─────────────────────────────────────
            $stmtSem = $pdo->prepare("
                SELECT DISTINCT
                    s.id AS idSemestre,
                    CONCAT('Semestre ',s.numInYear) AS nom_semestre,
                    s.numInYear,
                    s.numero,
                    vec.filiere,
                    vec.niveau,
                    vec.option_etudiant,
                    vec.idSession,
                    nf.niveau AS niveau_nom,
                     fac.faculte, dep.departement
                FROM semestre s
                JOIN ue u ON u.id_semestre = s.id
                JOIN vue_etudiants_complete vec ON vec.idUE = u.id
                JOIN maquette_ue mu ON mu.id_ue = u.id
                JOIN maquette m ON m.id = mu.id_maquette
                JOIN options o ON o.id = m.idOption
                JOIN filieres fil ON fil.id = o.idFilieres
                JOIN departements dep ON dep.id = fil.idDepartements
                JOIN facultes fac ON fac.id = dep.idFacultes
                LEFT JOIN niveauformation nf ON nf.id = o.idNiveauFormation
                WHERE s.id = :idSemestre
                AND vec.sync_version = (
                    SELECT MAX(sync_version) FROM vue_etudiants_complete WHERE idUE = vec.idUE
                )
                LIMIT 1
            ");
            $stmtSem->execute([':idSemestre' => $idSemestre]);
            $semInfo = $stmtSem->fetch(PDO::FETCH_ASSOC);

            if (!$semInfo) {
                echo json_encode(['success' => false, 'message' => 'Aucune donnée disponible pour ce semestre']);
                break;
            }

            // ── Année universitaire ───────────────────────────────────────────
            $annee = $pdo->query("
                SELECT annee_academique FROM scolarite_anneeuniversitaire
                WHERE id = (SELECT MAX(id) FROM scolarite_anneeuniversitaire)
            ")->fetchColumn();

            // ── Toutes les UEs du semestre (avec leurs crédits) ───────────────
            $sqlUEs = "
                SELECT DISTINCT
                    u.id AS idUE,
                    u.id_nature,
                    vec.code_ue,
                    vec.nom_ue,
                    SUM(DISTINCT u.nombre_credit ) AS total_credits,
                    u.poids
                FROM ue u
                JOIN vue_etudiants_complete vec ON vec.idUE = u.id
                WHERE u.id_semestre = :idSemestre
                AND vec.sync_version = (
                    SELECT MAX(sync_version) FROM vue_etudiants_complete WHERE idUE = u.id
                )
            ";
            $paramsUEs = [':idSemestre' => $idSemestre];

            if ($idOption) {
                $sqlUEs .= "
                    AND EXISTS (
                        SELECT 1 FROM scolarite_inscription_pedagogique_ue sipu
                        JOIN scolarite_inscription_pedagogique sip ON sip.id = sipu.idInscriptionPedagogique
                        JOIN scolarite_inscription si ON si.id = sip.idInscription
                        WHERE sipu.idUE = u.id AND si.idOption = :idOption  and sip.statut = 1
                    )
                ";
                $paramsUEs[':idOption'] = $idOption;
            } elseif ($idNiveau) {
                $sqlUEs .= "
                    AND EXISTS (
                        SELECT 1 FROM scolarite_inscription_pedagogique_ue sipu
                        JOIN scolarite_inscription_pedagogique sip ON sip.id = sipu.idInscriptionPedagogique
                        JOIN scolarite_inscription si ON si.id = sip.idInscription
                        JOIN options o ON o.id = si.idOption
                        WHERE sipu.idUE = u.id AND o.idNiveauFormation = :idNiveau  and sip.statut = 1
                    )
                ";
                $paramsUEs[':idNiveau'] = $idNiveau;
            }
            $sqlUEs .= " GROUP BY u.id, u.id_nature, vec.code_ue, vec.nom_ue ORDER BY vec.code_ue";

            $stmtUEs = $pdo->prepare($sqlUEs);
            $stmtUEs->execute($paramsUEs);
            $ues = $stmtUEs->fetchAll(PDO::FETCH_ASSOC);

            if (empty($ues)) {
                echo json_encode(['success' => false, 'message' => 'Aucune UE trouvée pour ce semestre']);
                break;
            }

            $idUEs = array_column($ues, 'idUE');
            $totalCredits = array_sum(array_column($ues, 'total_credits'));

            // ── Toutes les lignes EC pour toutes les UEs ──────────────────────
            $placeholders = implode(',', array_fill(0, count($idUEs), '?'));
            $stmtRows = $pdo->prepare("
                SELECT
                    vec.matricule, vec.prenom, vec.nom,
                    vec.idUE, vec.code_ue,
                    vec.idEC, vec.coefficient_ec, vec.credit_ec,
                    vec.note_final, vec.source_note,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM pedagogie_notes pn
                        WHERE pn.idEc = vec.idEC
                        AND pn.nature = 2
                        AND pn.idInscription = vec.idInscription
                        AND pn.non_compose = 1
                    ) THEN 1 ELSE 0 END AS non_compose,
                vec.vpc_enjambiste AS vpc_enjambiste
                FROM vue_etudiants_complete vec
                WHERE vec.idUE IN ($placeholders)
                AND vec.sync_version = (
                    SELECT MAX(sync_version) FROM vue_etudiants_complete WHERE idUE = vec.idUE
                )
                ORDER BY vec.nom, vec.prenom, vec.idUE, vec.idEC
            ");
            $stmtRows->execute($idUEs);
            $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);

            // ── Regrouper par étudiant ────────────────────────────────────────
            $etudiantsMap = [];
            foreach ($rows as $row) {
                $mat  = $row['matricule'];
                $idUE = $row['idUE'];

                if (!isset($etudiantsMap[$mat])) {
                    $etudiantsMap[$mat] = [
                        'matricule'      => $mat,
                        'prenom'         => $row['prenom'],
                        'nom'            => $row['nom'],
                        'ues'            => [],
                        'est_repeche'    => false,
                        'vpc_enjambiste' => false,
                        'ues_invalides'  => [],
                    ];
                }

                if ($row['vpc_enjambiste'] == 1) {
                    $etudiantsMap[$mat]['vpc_enjambiste'] = true;
                }

                if (!isset($etudiantsMap[$mat]['ues'][$idUE])) {
                    $etudiantsMap[$mat]['ues'][$idUE] = [
                        'points'       => 0,
                        'coefs'        => 0,
                        'credits'      => 0,
                        'non_compose'  => 0,
                        'total_ec'     => 0,
                        'nc_ec'        => 0,
                    ];
                }

                $coef = floatval($row['coefficient_ec']) ?: 1;
                $etudiantsMap[$mat]['ues'][$idUE]['points']  += floatval($row['note_final']) * $coef;
                $etudiantsMap[$mat]['ues'][$idUE]['coefs']   += $coef;
                $etudiantsMap[$mat]['ues'][$idUE]['credits']  += intval($row['credit_ec']);
                $etudiantsMap[$mat]['ues'][$idUE]['total_ec']++;
                if ($row['non_compose'] == 1) {
                    $etudiantsMap[$mat]['ues'][$idUE]['nc_ec']++;
                }

                if ($row['source_note'] === 'repechage') {
                    $etudiantsMap[$mat]['est_repeche'] = true;
                }
            }

            // Convertir ues_invalides en liste d'idUE
            foreach ($etudiantsMap as &$e) {
                $e['ues_invalides'] = [];
                foreach ($e['ues'] as $idUE => &$ue) {
                    // UE invalide = TOUS ses ECs sont non composés
                    if ($ue['total_ec'] > 0 && $ue['nc_ec'] === $ue['total_ec']) {
                        $ue['non_compose'] = 1;
                        $e['ues_invalides'][] = $idUE;
                    } else {
                        $ue['non_compose'] = 0;
                    }
                }
                unset($ue);
            }
            unset($e);
            // Convertir ues_invalides en tableau d'idUE
            foreach ($etudiantsMap as &$e) {
                $e['ues_invalides'] = array_keys($e['ues_invalides']);
            }
            unset($e);

            $nbInvalide = 0;

            // ── Calculer moyennes UE + semestre + crédits validés ─────────────
            foreach ($etudiantsMap as $mat => &$etudiant) {
                $totalPoints    = 0;
                $totalCoefs     = 0;
                $creditsValides = 0;
                $moyennesUE     = [];
                $etudiant['enjambisteCredit'] = 0;

                foreach ($ues as $ue) {
                    $idUE      = $ue['idUE'];
                    $creditsUE = floatval($ue['total_credits']);
                    $poidsUE   = floatval($ue['poids']);

                    if (isset($etudiant['ues'][$idUE]) && $etudiant['ues'][$idUE]['coefs'] > 0) {
                        $moy = round($etudiant['ues'][$idUE]['points'] / $etudiant['ues'][$idUE]['coefs'], 2);
                    } else {
                        $moy = null;
                        $etudiant['enjambisteCredit'] += $creditsUE;
                        $etudiant['est_enjambiste']    = true;
                    }

                    $moyennesUE[$idUE] = $moy;

                    if ($moy !== null) {
                        $totalPoints += $moy * $poidsUE;
                        $totalCoefs  += $poidsUE;
                        if ($moy >= 10) $creditsValides += $creditsUE;
                    }
                }

                // Absent = a au moins une UE ET aucune UE avec non_compose = 0
                $nbUEsPresentes  = count($etudiant['ues']); // UEs où il a des notes
                $nbUEsNonCompose = count(array_filter($etudiant['ues'], fn($ue) => ($ue['non_compose'] ?? 0) == 1));
                $nbUEsComposees  = count(array_filter($etudiant['ues'], fn($ue) => ($ue['non_compose'] ?? 0) == 0));

                // Absent = inscrit à au moins une UE ET n'a composé sur aucune d'elles
                $etudiant['est_absent'] = $nbUEsPresentes > 0
                    && $nbUEsNonCompose > 0
                    && $nbUEsComposees === 0;

                // Nettoyer les helpers devenus inutiles
                unset($etudiant['_total_ecs'], $etudiant['_nc_ecs']);

                $moySem = $totalCoefs > 0 ? round($totalPoints / $totalCoefs, 2) : 0;

                // ── VPC ───────────────────────────────────────────────────────
                $uesCompensees = [];
                $creditsVPC    = $creditsValides;

                if ($creditsValides < $totalCredits) {
                    $uesParNature = [];
                    foreach ($ues as $ue) {
                        $nature = $ue['id_nature'];
                        if (!isset($uesParNature[$nature])) $uesParNature[$nature] = [];
                        $uesParNature[$nature][] = $ue['idUE'];
                    }

                    $moyParNature = [];
                    foreach ($uesParNature as $nature => $idUEsNature) {
                        $somme = 0;
                        $count = 0;
                        foreach ($idUEsNature as $idUENature) {
                            $moyUE = $moyennesUE[$idUENature] ?? null;
                            if ($moyUE !== null) {
                                $somme += $moyUE;
                                $count++;
                            }
                        }
                        $moyParNature[$nature] = $count > 0 ? round($somme / $count, 2) : null;
                    }

                    foreach ($ues as $ue) {
                        $idUE          = $ue['idUE'];
                        $nature        = $ue['id_nature'];
                        $moyUE         = $moyennesUE[$idUE] ?? null;
                        $creditsUE     = floatval($ue['total_credits']);
                        $estInvalideUE = in_array($idUE, $etudiant['ues_invalides']);

                        if ($moyUE !== null && $moyUE < 10 && !$estInvalideUE) {
                            $moyNature = $moyParNature[$nature] ?? null;
                            if ($moyNature !== null && $moyNature >= 10 && $etudiant['ues'][$idUE]['nc_ec'] == 0) {
                                $uesCompensees[$idUE] = true;
                                $creditsVPC += $creditsUE;
                            } elseif (!empty($etudiant['vpc_enjambiste'])) {
                                $uesCompensees[$idUE] = true;
                                $creditsVPC += $creditsUE;
                            }
                        }
                    }
                }

                // invalide = non_compose sur au moins une UE ET toutes ces UEs sont quand même validées
                $etudiant['invalide'] = false;
                if (!empty($etudiant['ues_invalides'])) {
                    $toutesValidees = true;
                    foreach ($etudiant['ues_invalides'] as $idUEInvalide) {
                        $moyUE = $moyennesUE[$idUEInvalide] ?? null;
                        if ($moyUE === null || $moyUE < 10) {
                            $toutesValidees = false;
                            break;
                        }
                    }
                    if ($toutesValidees) $etudiant['invalide'] = true;
                }

                $etudiant['moyennes_ue']     = $moyennesUE;
                $etudiant['moyenne_sem']     = $moySem;
                $etudiant['credits_valides'] = $creditsValides;
                $etudiant['creditsVPC']      = $creditsVPC;
                $etudiant['ues_compensees']  = array_keys($uesCompensees);

                // ── Statut ────────────────────────────────────────────────────
                if (!empty($etudiant['est_absent'])) {
                    $etudiant['statut'] = 'Absent';
                } elseif (!empty($etudiant['vpc_enjambiste'])) {
                    $credits = $etudiant['enjambisteCredit'] + $creditsValides + $creditsVPC;
                    $etudiant['statut'] = $credits >= $totalCredits
                        ? (!empty($etudiant['invalide']) ? 'Invalide' : 'Semestre validé')
                        : 'Semestre non validé';
                } else {
                    $etudiant['statut'] = $etudiant['enjambisteCredit'] + $creditsVPC  >= $totalCredits
                        ? (!empty($etudiant['invalide']) ? 'Invalide' : 'Semestre validé')
                        : 'Semestre non validé';
                }

                if (!empty($etudiant['invalide'])) $nbInvalide++;
            }
            unset($etudiant);

            // Trier par nom
            usort($etudiantsMap, fn($a, $b) => strcmp($a['nom'] . ' ' . $a['prenom'], $b['nom'] . ' ' . $b['prenom']));
            $etudiants = array_values($etudiantsMap);

            // // ── Statistiques ──────────────────────────────────────────────────
            // $nbTotal    = count($etudiants);
            // $nbEtudiantValide = count($etudiants) - $nbInvalide;
            // $nbValides  = count(array_filter($etudiants, fn($e) => $e['statut'] === 'Semestre validé'));
            // $nbVPC      = count(array_filter($etudiants, fn($e) => $e['statut'] === 'Semestre validé par compensation'));
            // $nbNonValid = $nbTotal - $nbValides - $nbVPC;
            // $nbRepeches = count(array_filter($etudiants, fn($e) => $e['est_repeche']));
            // $tauxReuss  = $nbTotal > 0 ? round(($nbValides + $nbVPC) / $nbTotal * 100, 1) : 0;
            // ── Statistiques ──────────────────────────────────────────────────
            $nbTotal     = count($etudiants);
            $nbAbsents   = count(array_filter($etudiants, fn($e) => !empty($e['est_absent'])));
            $nbInvalides = count(array_filter($etudiants, fn($e) => $e['statut'] === 'Invalide'));
            $lesInvalide = array_filter($etudiants, fn($e) => $e['statut'] === 'Invalide');
            // Base de calcul = ni absents ni invalides
            $etudiantsDeliberes = array_filter(
                $etudiants,
                fn($e) =>
                empty($e['est_absent']) && $e['statut'] !== 'Invalide'
            );
            $nbDeliberes = count($etudiantsDeliberes);

            $nbValides  = count(array_filter($etudiantsDeliberes, fn($e) => $e['statut'] === 'Semestre validé'));
            $nbNonValid = count(array_filter($etudiantsDeliberes, fn($e) => $e['statut'] === 'Semestre non validé'));
            $nbRepeches = count(array_filter($etudiantsDeliberes, fn($e) => !empty($e['est_repeche'])));
            $tauxReuss  = $nbDeliberes > 0 ? round($nbValides / $nbDeliberes * 100, 1) : 0;
            echo json_encode([
                'success'      => true,
                'semInfo'      => $semInfo,
                'annee'        => $annee,
                'ues'          => $ues,
                'totalCredits' => $totalCredits,
                'etudiants'    => $etudiants,
                'stats' => [
                    'nbTotal'     => $nbTotal,
                    'nbAbsents'   => $nbAbsents,
                    'nbInvalides' => $nbInvalides,
                    'nbDeliberes' => $nbDeliberes,
                    'nbValides'   => $nbValides,
                    'nbNonValid'  => $nbNonValid,
                    'nbRepeches'  => $nbRepeches,
                    'tauxReuss'   => $tauxReuss,
                    'nbInvalide'  => $nbInvalides,
                ],
            ]);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Action non trouvée']);
            break;
    }
} else if ($postAction) {
    switch ($action) {
        case 'toggleVPCEnjambisteBulk':
            $data  = json_decode(file_get_contents('php://input'), true);
            $items = $data['items'] ?? [];

            if (empty($items)) {
                echo json_encode(['success' => false, 'message' => 'Aucun item reçu']);
                break;
            }

            $updated = 0;
            $stmt = $pdo->prepare("
        UPDATE vue_etudiants_complete
        SET vpc_enjambiste = :actif
        WHERE matricule   = :matricule
          AND idUE        = :idUE
          AND sync_version = (
              SELECT MAX(sync_version) FROM vue_etudiants_complete
              WHERE idUE = :idUE2
          )
    ");

            foreach ($items as $item) {
                $stmt->execute([
                    ':actif'      => (int)($item['actif'] ?? 1),
                    ':matricule'  => $item['matricule'],
                    ':idUE'       => $item['idUE'],
                    ':idUE2'      => $item['idUE'],
                ]);
                $updated += $stmt->rowCount();
            }

            echo json_encode([
                'success' => true,
                'message' => "$updated ligne(s) mise(s) à jour.",
                'updated' => $updated,
            ]);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Action non trouvée']);
            break;
    }
}
