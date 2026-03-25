<?php
session_start();
header('Content-Type: application/json');

require_once '../../config.php'; // adapter selon ton arborescence

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── GET ───────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {

        // ── Recherche étudiant par matricule ──────────────────────────────────
        case 'getEtudiant':
            $matricule = trim($_GET['matricule'] ?? '');
            if (!$matricule) {
                echo json_encode(['success' => false, 'message' => 'Matricule requis']);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT DISTINCT
                    vec.matricule,
                    vec.prenom,
                    vec.nom,
                    vec.filiere,
                    vec.niveau,
                    vec.option_etudiant,
                    sem.numInYear   AS numSemestre,
                    CONCAT('Semestre ',sem.numero)         AS nom_semestre,
                    vec.idSession
                FROM vue_etudiants_complete vec 
                JOIN ue u ON u.id = vec.idUE
                JOIN semestre sem ON sem.id = u.id_semestre
                WHERE vec.matricule = :mat
                  AND vec.sync_version = (
                      SELECT MAX(v2.sync_version)
                      FROM vue_etudiants_complete v2
                      WHERE v2.idUE = vec.idUE
                  )
                LIMIT 1
            ");
            $stmt->execute([':mat' => $matricule]);
            $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$etudiant) {
                echo json_encode(['success' => false, 'message' => 'Aucun étudiant trouvé pour ce matricule.']);
                break;
            }

            echo json_encode(['success' => true, 'etudiant' => $etudiant]);
            break;

        // ── Fiche complète : UEs + ECs + moyennes + statuts ───────────────────
        case 'getFiche':
            $matricule = trim($_GET['matricule'] ?? '');
            if (!$matricule) {
                echo json_encode(['success' => false, 'message' => 'Matricule requis']);
                break;
            }
 
            // Toutes les lignes EC de l'étudiant (dernière sync_version par UE)
            // total_credits_ue en sous-requête — MariaDB ne supporte pas SUM(DISTINCT) OVER()
            $stmtRows = $pdo->prepare("
                SELECT
                    vec.idUE,
                    vec.code_ue,
                    vec.nom_ue,
                    vec.idEC,
                    vec.code_ec,
                    vec.nom_ec,
                    vec.coefficient_ec,
                    vec.credit_ec,
                    vec.note_initial,
                    vec.note_final,
                    vec.point_jury,
                    vec.source_note,
                    u.id_semestre,
                    sem.numero   AS num_semestre,
                    CONCAT('Semestre :',sem.numero)         AS nom_semestre,
                    u.poids         AS poids_ue
                    ,pn.non_compose
                FROM vue_etudiants_complete vec
                JOIN ue u ON u.id = vec.idUE
                JOIN semestre sem ON sem.id = u.id_semestre
                JOIN pedagogie_notes pn ON pn.idEc = vec.idEC AND pn.idInscription = vec.idInscription

                WHERE vec.matricule = :mat
                  AND vec.sync_version = (
                      SELECT MAX(v2.sync_version)
                      FROM vue_etudiants_complete v2
                      WHERE v2.idUE = vec.idUE
                  )
                GROUP BY vec.idEC
                ORDER BY sem.numInYear, vec.code_ue, vec.code_ec
            ");
            $stmtRows->execute([':mat' => $matricule]);
            $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);
 
            if (empty($rows)) {
                echo json_encode(['success' => false, 'message' => 'Aucune note synchronisée pour cet étudiant.']);
                break;
            }
 
            // ── Regrouper par semestre → UE → EC ─────────────────────────────
            $semestres = [];
 
            foreach ($rows as $row) {
                $numSem = $row['num_semestre'];
                $idUE   = $row['idUE'];
                $idEC   = $row['idEC'];
 
                if (!isset($semestres[$numSem])) {
                    $semestres[$numSem] = [
                        'numSemestre' => $numSem,
                        'nomSemestre' => $row['nom_semestre'],
                        'ues'         => [],
                    ];
                }
 
                if (!isset($semestres[$numSem]['ues'][$idUE])) {
                    $semestres[$numSem]['ues'][$idUE] = [
                        'idUE'         => $idUE,
                        'code_ue'      => $row['code_ue'],
                        'nom_ue'       => $row['nom_ue'],
                        'poids_ue'     => floatval($row['poids_ue']),
                        'total_credits'=> 0, // calculé via SUM(credit_ec) des ECs
                        'ecs'          => [],
                        // calculés après
                        'moyenne_ue'   => null,
                        'credits_valides' => 0,
                        'est_repeche'  => false,
                        'isValid' => $row['non_compose'] == 0
                    ];
                }
 
                $semestres[$numSem]['ues'][$idUE]['ecs'][] = [
                    'idEC'          => $idEC,
                    'code_ec'       => $row['code_ec'],
                    'nom_ec'        => $row['nom_ec'],
                    'coefficient'   => floatval($row['coefficient_ec']),
                    'credit'        => floatval($row['credit_ec']),
                    'note_initial'  => floatval($row['note_initial']),
                    'note_final'    => floatval($row['note_final']),
                    'point_jury'    => floatval($row['point_jury']),
                    'source_note'   => $row['source_note'],
                ];
                // Accumuler les crédits de l'UE (DISTINCT par idEC)
                if (!isset($semestres[$numSem]['ues'][$idUE]['_ec_credits'][$idEC])) {
                    $semestres[$numSem]['ues'][$idUE]['_ec_credits'][$idEC] = floatval($row['credit_ec']);
                    $semestres[$numSem]['ues'][$idUE]['total_credits'] += floatval($row['credit_ec']);
                }
 
                if ($row['source_note'] === 'repechage') {
                    $semestres[$numSem]['ues'][$idUE]['est_repeche'] = true;
                }
            }
 
            // ── Calculer moyenne UE + crédits validés ─────────────────────────
            foreach ($semestres as $numSem => &$semestre) {
                $totalPointsSem = 0;
                $totalPoidsValidesSem = 0;
                $totalPoidsSem = 0;
                $creditsValidesSem = 0;
                $totalCreditsSem  = 0; // fixe : 30 crédits par semestre

                foreach ($semestre['ues'] as $idUE => &$ue) {
                    $totalPoints = 0;
                    $totalCoefs  = 0;
 
                    foreach ($ue['ecs'] as $ec) {
                        $coef = $ec['coefficient'] ?: 1;
                        $totalPoints += $ec['note_final'] * $coef;
                        $totalCoefs  += $coef;
                    }
 
                    $moy = $totalCoefs > 0 ? round($totalPoints / $totalCoefs, 2) : 0;
                    $ue['moyenne_ue'] = $moy;
 
                    if ($moy >= 10) {
                        $ue['credits_valides'] = $ue['total_credits'];
                        $creditsValidesSem    += $ue['total_credits'];
                        $totalPoidsValidesSem += $ue['poids_ue'];
                    }
                    $totalCreditsSem += $ue['total_credits'];
                    $totalPointsSem += $moy * $ue['poids_ue'];
                    $totalPoidsSem  += $ue['poids_ue'];
                    // totalCreditsSem est fixe (30), ne pas l'accumuler ici
                }
                unset($ue);
 
                $moySem = $totalPoidsSem > 0 ? round($totalPointsSem / $totalPoidsSem, 2) : 0;
 
                // ── VPC par nature ────────────────────────────────────────────
                $statutVPC     = false;
                $uesCompensees = [];
                $creditsVPC    = $creditsValidesSem;
 
                if ($creditsValidesSem < $totalCreditsSem) {
                    // Récupérer id_nature de chaque UE du semestre
                    $idUEsSem = array_keys($semestre['ues']);
                    $ph = implode(',', array_fill(0, count($idUEsSem), '?'));
                    $stmtNature = $pdo->prepare("SELECT id, id_nature FROM ue WHERE id IN ($ph)");
                    $stmtNature->execute($idUEsSem);
                    $naturesMap = [];
                    foreach ($stmtNature->fetchAll(PDO::FETCH_ASSOC) as $r) {
                        $naturesMap[$r['id']] = $r['id_nature'];
                    }
 
                    // Grouper par nature
                    $uesParNature = [];
                    foreach ($semestre['ues'] as $idUE => $ue) {
                        $nature = $naturesMap[$idUE] ?? null;
                        if ($nature) $uesParNature[$nature][] = $idUE;
                    }
 
                    // Moyenne par nature
                    foreach ($uesParNature as $nature => $idUEsNature) {
                        $somme = 0; $cnt = 0;
                        foreach ($idUEsNature as $idUE) {
                            $moy = $semestre['ues'][$idUE]['moyenne_ue'] ?? null;
                            if ($moy !== null) { $somme += $moy; $cnt++; }
                        }
                        $moyNature = $cnt > 0 ? round($somme / $cnt, 2) : null;
 
                        if ($moyNature !== null && $moyNature >= 10) {
                            foreach ($idUEsNature as $idUE) {
                                $moy = $semestre['ues'][$idUE]['moyenne_ue'] ?? null;
                                if ($moy !== null && $moy < 10 && $semestre['ues'][$idUE]['isValid']) {
                                    $uesCompensees[] = $idUE;
                                    $creditsVPC += $semestre['ues'][$idUE]['total_credits'];
                                }
                            }
                        }
                    }
 
                    if ($creditsVPC >= $totalCreditsSem) $statutVPC = true;
                }
 
                $statut = $creditsValidesSem >= $totalCreditsSem
                    ? 'Semestre validé'
                    : ($statutVPC ? 'Semestre validé par compensation' : 'Semestre non validé');
 
                $semestre['moyenne_sem']      = $moySem;
                $semestre['credits_valides']  =  $creditsVPC ?? $creditsValidesSem ;
                $semestre['total_credits']    = $totalCreditsSem;
                $semestre['statut']           = $statut;
                $semestre['ues_compensees']   = $uesCompensees;
                // Nettoyer le helper _ec_credits avant sérialisation
                foreach ($semestre['ues'] as &$ueClean) {
                    unset($ueClean['_ec_credits']);
                }
                unset($ueClean);
                $semestre['ues'] = array_values($semestre['ues']);
            }
            unset($semestre);
 
            // Trier semestres par numéro
            usort($semestres, fn($a, $b) => $a['numSemestre'] - $b['numSemestre']);
 
            echo json_encode([
                'success'   => true,
                'semestres' => array_values($semestres),
            ]);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Action non trouvée']);
    }
}
