<?php
require_once '../../config.php';

$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

$getAction  = $_GET['action']   ?? null;
$input      = json_decode(file_get_contents('php://input'), true);
$postAction = $input['action']  ?? null;
$action     = $getAction ?? $postAction;

if (!$action) {
    die("Aucune action spécifiée.");
}

switch ($action) {

    case 'getFilieres':
        echo json_encode(getFilere($pdo));
        break;

    case 'getOptionsByFiliere':
        $idFiliere         = $_GET['idFiliere']         ?? 0;
        $idNiveauFormation = $_GET['idNiveauFormation'] ?? null;
        echo json_encode(getOptionByFiliere($pdo, $idFiliere, $idNiveauFormation));
        break;
        
    case 'getSemestresByNiveau':
        $idNiveauFormation = isset($_GET['idNiveauFormation']) ? (int)$_GET['idNiveauFormation'] : null;
        echo json_encode(getSemestresByNiveau($pdo, $idNiveauFormation));
        break;
    case 'getNiveauFormationByCycle':
        $idCycleFormation = $_GET['idCycleFormation'] ?? 0;
        echo json_encode(getNiveauFormationByCycle($pdo, $idCycleFormation));
        break;

    case 'getMaquetteUEs':
        $idcycle           = $_GET['idcycle']           ?? null;
        $idNiveauFormation = $_GET['idNiveauFormation'] ?? null;
        $idOption          = $_GET['idOption']          ?? null;
        $idSemestre        = $_GET['idSemestre']        ?? null;
        echo json_encode(getMaquetteUEs($pdo, $idcycle, $idNiveauFormation, $idOption, $idSemestre));
        break;

    case 'getStatsSemestre':
        $idSemestre        = $_GET['idSemestre']        ?? null;
        $idOption          = $_GET['idOption']          ?? null;
        $idNiveauFormation = $_GET['idNiveauFormation'] ?? null;
        $idCycle           = $_GET['idCycle']           ?? null;
        echo json_encode(getStatsSemestre($pdo, $idSemestre, $idOption, $idNiveauFormation, $idCycle));
        break;

    default:
        die("Action inconnue.");
}

// ─────────────────────────────────────────────────────────────────────────────

function getFilere($pdo)
{
    $stmt = $pdo->prepare("SELECT * FROM filieres");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getOptionByFiliere($pdo, $idFiliere, $idNiveauFormation = null)
{
    if ($idFiliere == 0) {
        $stmt = $pdo->prepare("SELECT * FROM options WHERE code_option != 'TC' GROUP BY code_option");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $stmt = $pdo->prepare("
        SELECT * FROM options
        WHERE idFilieres = :idFiliere
          AND idNiveauFormation = :idNiveauFormation
          AND code_option != 'TC'
        GROUP BY code_option, idNiveauFormation
    ");
    $stmt->bindParam(':idFiliere',         $idFiliere,         PDO::PARAM_INT);
    $stmt->bindParam(':idNiveauFormation', $idNiveauFormation, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getSemestresByNiveau($pdo, $idNiveauFormation = null)
{
    $sql = "
        SELECT DISTINCT
            s.id,
            s.numInYear,
            s.numero,
            CONCAT('Semestre ', s.numInYear) AS nom_semestre
        FROM semestre s
    ";
 
    $params = [];
 
    if ($idNiveauFormation) {
        $sql .= "
        WHERE s.idNiveauFormation = :idNiveauFormation
        ";
        $params[':idNiveauFormation'] = $idNiveauFormation;
    }
 
    $sql .= " ORDER BY s.numInYear";
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
 
function getNiveauFormationByCycle($pdo, $idCycleFormation)
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT niv.*
        FROM niveauformation niv
        JOIN options o ON niv.id = o.idNiveauFormation
        WHERE niv.idCycleFormation = :idCycleFormation
    ");
    $stmt->bindParam(':idCycleFormation', $idCycleFormation, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── getMaquetteUEs ─────────────────────────────────────────────────────────────
// Les absents sont calculés séparément en PHP via _calcAbsentsParUE()
// car ils nécessitent pedagogie_notes, inaccessible dans un CTE sur vec seul.
function getMaquetteUEs($pdo, $idcycle, $idNiveauFormation, $idOption, $idSemestre)
{
    $params = [];

    $sql = "
        WITH notes_ec AS (
            SELECT vec.matricule, vec.idUE, vec.idEC, vec.coefficient_ec, vec.note_final
            FROM vue_etudiants_complete vec
            WHERE vec.note_final IS NOT NULL
        ),
        moyennes_etudiants AS (
            SELECT matricule, idUE,
                ROUND(SUM(note_final * coefficient_ec) / NULLIF(SUM(coefficient_ec), 0), 2) AS moyenne_ue
            FROM notes_ec
            GROUP BY matricule, idUE
        ),
        stats_ue AS (
            SELECT
                idUE,
                COUNT(DISTINCT matricule)                                                       AS effectif,
                ROUND(AVG(moyenne_ue), 2)                                                      AS moyenne,
                MIN(moyenne_ue)                                                                 AS note_min,
                MAX(moyenne_ue)                                                                 AS note_max,
                COUNT(DISTINCT CASE WHEN moyenne_ue >= 10                THEN matricule END)   AS reussite,
                COUNT(DISTINCT CASE WHEN moyenne_ue <  10                THEN matricule END)   AS echec,
                COUNT(DISTINCT CASE WHEN moyenne_ue BETWEEN 0   AND 6.99 THEN matricule END)  AS intervalle_0_7,
                COUNT(DISTINCT CASE WHEN moyenne_ue BETWEEN 7   AND 7.99 THEN matricule END)  AS intervalle_7_8,
                COUNT(DISTINCT CASE WHEN moyenne_ue BETWEEN 8   AND 8.99 THEN matricule END)  AS intervalle_8_9,
                COUNT(DISTINCT CASE WHEN moyenne_ue BETWEEN 9   AND 9.99 THEN matricule END)  AS intervalle_9_10,
                COUNT(DISTINCT CASE WHEN moyenne_ue BETWEEN 10  AND 20   THEN matricule END)  AS intervalle_10_20
            FROM moyennes_etudiants
            GROUP BY idUE
        )
        SELECT
            u.id          AS idUE,
            u.code        AS codeUE,
            u.nom         AS nomUE,
            s.numInYear   AS numSemestre,
            s.id          AS idSemestre,
            o.id          AS idOption,
            o.option      AS nomOption,
            nf.id         AS idNiveauFormation,
            nf.niveau     AS niveauFormation,
            m.id          AS idMaquette,
            m.nom         AS nomMaquette,
            cy.id         AS idCycle,
            cy.cycle      AS nomCycle,
            COALESCE(su.effectif,         0) AS effectif,
            COALESCE(su.moyenne,          0) AS moyenne,
            COALESCE(su.note_min,         0) AS note_min,
            COALESCE(su.note_max,         0) AS note_max,
            COALESCE(su.reussite,         0) AS reussite,
            COALESCE(su.echec,            0) AS echec,
            COALESCE(su.intervalle_0_7,   0) AS intervalle_0_7,
            COALESCE(su.intervalle_7_8,   0) AS intervalle_7_8,
            COALESCE(su.intervalle_8_9,   0) AS intervalle_8_9,
            COALESCE(su.intervalle_9_10,  0) AS intervalle_9_10,
            COALESCE(su.intervalle_10_20, 0) AS intervalle_10_20
        FROM ue u
        JOIN maquette_ue mu     ON mu.id_ue  = u.id
        JOIN maquette m         ON m.id      = mu.id_maquette
        JOIN options o          ON o.id      = m.idOption
        JOIN niveauformation nf ON nf.id     = o.idNiveauFormation
        JOIN cycleformation cy  ON cy.id     = nf.idCycleFormation
        JOIN semestre s         ON s.id      = u.id_semestre
        LEFT JOIN stats_ue su   ON su.idUE   = u.id
    ";

    $conditions = [];
    if ($idcycle) {
        $conditions[] = "cy.id = :idCycle";
        $params[':idCycle']           = $idcycle;
    }
    if ($idNiveauFormation) {
        $conditions[] = "nf.id = :idNiveauFormation";
        $params[':idNiveauFormation'] = $idNiveauFormation;
    }
    if ($idOption) {
        $conditions[] = "o.id  = :idOption";
        $params[':idOption']          = $idOption;
    }
    if ($idSemestre) {
        $conditions[] = "s.id  = :idSemestre";
        $params[':idSemestre']        = $idSemestre;
    }

    $sql .= !empty($conditions)
        ? " WHERE m.idEtat = 3 AND " . implode(" AND ", $conditions)
        : " WHERE m.idEtat = 3";

    $sql .= " GROUP BY
                u.id, u.code, u.nom, s.numInYear, s.id, o.id, o.option,
                nf.id, nf.niveau, m.id, m.nom, cy.id, cy.cycle,
                su.effectif, su.moyenne, su.note_min, su.note_max,
                su.reussite, su.echec,
                su.intervalle_0_7, su.intervalle_7_8, su.intervalle_8_9,
                su.intervalle_9_10, su.intervalle_10_20
              ORDER BY s.numInYear, u.code";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($ues)) return $ues;

    // Calcul absents en dehors du SQL principal
    $idUEs          = array_column($ues, 'idUE');
    $absentsResult2 = _calcAbsentsParUE($pdo, $idUEs);
    $absentsParUE2  = $absentsResult2['counts'];

    // 2. getMaquetteUEs — exclure absents des stats
    foreach ($ues as &$ue) {
        $eff = intval($ue['effectif']);
        $abs = $absentsParUE2[$ue['idUE']] ?? 0;
        $presents = max(0, $eff - $abs); // base de calcul = présents

        $ue['absents']      = $abs;
        $ue['presents']     = $presents;
        $ue['tauxReussite'] = $presents > 0 ? round($ue['reussite'] / $presents * 100, 1) : 0;
        $ue['tauxEchec']    = $presents > 0 ? round($ue['echec']    / $presents * 100, 1) : 0;
        $ue['tauxAbsence']  = $eff > 0      ? round($abs            / $eff * 100, 1) : 0;
    }
    unset($ue);

    return $ues;
}

// ── getStatsSemestre ──────────────────────────────────────────────────────────
// Méthode de calcul IDENTIQUE au PV par semestre :
//   - Moyenne UE     = SUM(note_final * coef) / SUM(coef)
//   - Moyenne sem.   = SUM(moy_UE * credits_UE) / SUM(credits_UE) (pondérée)
//   - UE validée     = moy_UE >= 10
//   - Réussite       = poids_valides >= totalPoids (somme des poids des UEs)
//   - note_max/min   = sur les moyennes SEMESTRIELLES individuelles
//   - Filtre         = sync_version = MAX(sync_version) comme le PV
function getStatsSemestre($pdo, $idSemestre, $idOption, $idNiveauFormation, $idCycle) {

    if (!$idSemestre) return ['success' => false, 'message' => 'idSemestre requis'];

    // ── Contexte ──────────────────────────────────────────────────────────────
    $stmtCtx = $pdo->prepare("
        SELECT DISTINCT f.filiere, nf.niveau, o.option AS specialite,
            CONCAT('Semestre ', s.numero) AS nom_semestre, s.numInYear,
            vec.idSession, cy.cycle, fac.faculte, dep.departement
        FROM semestre s
        JOIN ue u                        ON u.id_semestre = s.id
        JOIN vue_etudiants_complete vec  ON vec.idUE = u.id
        JOIN maquette_ue mu              ON mu.id_ue = u.id
        JOIN maquette m                  ON m.id = mu.id_maquette
        JOIN options o                   ON o.id = m.idOption
        JOIN niveauformation nf          ON nf.id = o.idNiveauFormation
        JOIN cycleformation cy           ON cy.id = nf.idCycleFormation
        JOIN filieres f                  ON f.id = o.idFilieres
        JOIN departements dep            ON dep.id = f.idDepartements
        JOIN facultes fac                ON fac.id = dep.idFacultes
        WHERE s.id = :idSemestre LIMIT 1
    ");
    $stmtCtx->execute([':idSemestre' => $idSemestre]);
    $ctx = $stmtCtx->fetch(PDO::FETCH_ASSOC);

    $annee = $pdo->query("
        SELECT annee_academique FROM scolarite_anneeuniversitaire
        WHERE id = (SELECT MAX(id) FROM scolarite_anneeuniversitaire)
    ")->fetchColumn();

    // ── UEs (identique pvSemestre) ─────────────────────────────────────────
    $filtreExists      = '';
$paramsFiltreUEs   = [':idSemestre' => $idSemestre];

if ($idOption) {
    $filtreExists = "
        AND EXISTS (
            SELECT 1
            FROM scolarite_inscription_pedagogique_ue sipu
            JOIN scolarite_inscription_pedagogique    sip ON sip.id = sipu.idInscriptionPedagogique
            JOIN scolarite_inscription                si  ON si.id  = sip.idInscription
            WHERE sipu.idUE      = u.id
              AND si.idOption    = :idOption
              AND sip.statut     = 1
        )";
    $paramsFiltreUEs[':idOption'] = $idOption;

} elseif ($idNiveauFormation) {
    $filtreExists = "
        AND EXISTS (
            SELECT 1
            FROM scolarite_inscription_pedagogique_ue sipu
            JOIN scolarite_inscription_pedagogique    sip ON sip.id  = sipu.idInscriptionPedagogique
            JOIN scolarite_inscription                si  ON si.id   = sip.idInscription
            JOIN options                              o   ON o.id    = si.idOption
            WHERE sipu.idUE             = u.id
              AND o.idNiveauFormation   = :idNiveauFormation
              AND sip.statut            = 1
        )";
    $paramsFiltreUEs[':idNiveauFormation'] = $idNiveauFormation;
}

$stmtUEs = $pdo->prepare("
    SELECT
        u.id            AS idUE,
        u.id_nature,
        u.poids,
        u.nombre_credit AS total_credits,
        vec.code_ue     AS codeUE,
        vec.nom_ue      AS nomUE
    FROM ue u
    -- Une seule ligne par UE : récupérer code_ue/nom_ue depuis la dernière version sync
    JOIN (
        SELECT DISTINCT idUE, code_ue, nom_ue, sync_version
        FROM vue_etudiants_complete v1
        WHERE sync_version = (
            SELECT MAX(sync_version)
            FROM vue_etudiants_complete v2
            WHERE v2.idUE = v1.idUE
        )
    ) vec ON vec.idUE = u.id
    WHERE u.id_semestre = :idSemestre
      $filtreExists
    ORDER BY vec.code_ue
");

$stmtUEs->execute($paramsFiltreUEs);
$ues = $stmtUEs->fetchAll(PDO::FETCH_ASSOC);
    if (empty($ues)) return ['success' => false, 'message' => 'Aucune UE trouvée'];

    $idUEs        = array_column($ues, 'idUE');
    $totalCredits = array_sum(array_column($ues, 'total_credits'));
    $totalPoids   = array_sum(array_column($ues, 'poids'));

    // ── Lignes EC (identique pvSemestre) ──────────────────────────────────────
    $placeholders = implode(',', array_fill(0, count($idUEs), '?'));
    $stmtRows = $pdo->prepare("
        SELECT vec.matricule, vec.prenom, vec.nom,
               vec.idUE, vec.idEC, vec.coefficient_ec, vec.credit_ec,
               vec.note_final, vec.source_note, vec.vpc_enjambiste,
               CASE WHEN EXISTS (
                   SELECT 1 FROM pedagogie_notes pn
                   WHERE pn.idEc = vec.idEC AND pn.nature = 2
                     AND pn.idInscription = vec.idInscription AND pn.non_compose = 1
               ) THEN 1 ELSE 0 END AS non_compose
        FROM vue_etudiants_complete vec
        WHERE vec.idUE IN ($placeholders)
          AND vec.sync_version = (SELECT MAX(sync_version) FROM vue_etudiants_complete WHERE idUE = vec.idUE)
        ORDER BY vec.nom, vec.prenom, vec.idUE, vec.idEC
    ");
    $stmtRows->execute($idUEs);
    $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);

    // ── Regrouper (identique pvSemestre) ──────────────────────────────────────
    $etudiantsMap = [];
    foreach ($rows as $row) {
        $mat  = $row['matricule'];
        $idUE = $row['idUE'];

        if (!isset($etudiantsMap[$mat])) {
            $etudiantsMap[$mat] = [
                'matricule'      => $mat,
                'ues'            => [],
                'vpc_enjambiste' => false,
                'ues_invalides'  => [],
            ];
        }

        if ($row['vpc_enjambiste'] == 1) $etudiantsMap[$mat]['vpc_enjambiste'] = true;

        if (!isset($etudiantsMap[$mat]['ues'][$idUE])) {
            $etudiantsMap[$mat]['ues'][$idUE] = ['points' => 0, 'coefs' => 0, 'non_compose' => 0, 'total_ec' => 0, 'nc_ec' => 0];
        }

        $coef = floatval($row['coefficient_ec']) ?: 1;
        $etudiantsMap[$mat]['ues'][$idUE]['points'] += floatval($row['note_final']) * $coef;
        $etudiantsMap[$mat]['ues'][$idUE]['coefs']  += $coef;
        $etudiantsMap[$mat]['ues'][$idUE]['total_ec']++;
        if ($row['non_compose'] == 1) $etudiantsMap[$mat]['ues'][$idUE]['nc_ec']++;
    }

    // UE invalide = tous ses ECs non composés
    foreach ($etudiantsMap as &$e) {
        $e['ues_invalides'] = [];
        foreach ($e['ues'] as $idUE => &$ue) {
            if ($ue['total_ec'] > 0 && $ue['nc_ec'] === $ue['total_ec']) {
                $ue['non_compose'] = 1;
                $e['ues_invalides'][] = $idUE;
            }
        }
        unset($ue);
    }
    unset($e);

    // Précalculs
    $poidsUEMap   = array_column($ues, 'poids',         'idUE');
    $creditsUEMap = array_column($ues, 'total_credits',  'idUE');
    $natureUEMap  = array_column($ues, 'id_nature',      'idUE');
    $uesParNature = [];
    foreach ($ues as $ue) $uesParNature[$ue['id_nature']][] = $ue['idUE'];

    // ── Calcul par étudiant (identique pvSemestre) ────────────────────────────
    $etudiantsSem = []; // pour les stats par UE
    foreach ($etudiantsMap as $mat => &$etudiant) {
        $totalPoints = $totalCoefs = $creditsValides = 0;
        $moyennesUE  = [];
        $etudiant['enjambisteCredit'] = 0;

        foreach ($ues as $ue) {
            $idUE      = $ue['idUE'];
            $creditsUE = floatval($creditsUEMap[$idUE]);
            $poidsUE   = floatval($poidsUEMap[$idUE]);

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

        // Absent = aucune UE composée (identique pvSemestre)
        $nbUEsComposees  = count(array_filter($etudiant['ues'], fn($ue) => ($ue['non_compose'] ?? 0) == 0));
        $nbUEsNonCompose = count(array_filter($etudiant['ues'], fn($ue) => ($ue['non_compose'] ?? 0) == 1));
        $etudiant['est_absent'] = !empty($etudiant['ues']) && $nbUEsNonCompose > 0 && $nbUEsComposees === 0;

        $moySem = $totalCoefs > 0 ? round($totalPoints / $totalCoefs, 2) : 0;

        // VPC
        $uesCompensees = []; $creditsVPC = $creditsValides;
        if ($creditsValides < $totalCredits) {
            $moyParNature = [];
            foreach ($uesParNature as $nature => $idUEsNature) {
                $somme = $count = 0;
                foreach ($idUEsNature as $idUENature) {
                    $m = $moyennesUE[$idUENature] ?? null;
                    if ($m !== null) { $somme += $m; $count++; }
                }
                $moyParNature[$nature] = $count > 0 ? round($somme / $count, 2) : null;
            }
            foreach ($ues as $ue) {
                $idUE = $ue['idUE']; $moyUE = $moyennesUE[$idUE] ?? null;
                $estInvalideUE = in_array($idUE, $etudiant['ues_invalides']);
                if ($moyUE !== null && $moyUE < 10 && !$estInvalideUE) {
                    $moyNature = $moyParNature[$natureUEMap[$idUE]] ?? null;
                    if (($moyNature !== null && $moyNature >= 10) || !empty($etudiant['vpc_enjambiste'])) {
                        $uesCompensees[$idUE] = true;
                        $creditsVPC += floatval($creditsUEMap[$idUE]);
                    }
                }
            }
        }

        // Statut
        $etudiant['statut'] = match(true) {
            !empty($etudiant['est_absent'])      => 'Absent',
            !empty($etudiant['vpc_enjambiste'])  => ($etudiant['enjambisteCredit'] + $creditsVPC >= $totalCredits ? 'Semestre validé' : 'Semestre non validé'),
            default                              => ($creditsVPC >= $totalCredits ? 'Semestre validé' : 'Semestre non validé'),
        };

        $etudiant['moyennes_ue'] = $moyennesUE;
        $etudiant['moy_sem']     = $moySem;
        $etudiant['poids_valides'] = array_sum(array_map(
            fn($idUE) => ($moyennesUE[$idUE] ?? 0) >= 10 ? floatval($poidsUEMap[$idUE]) : 0,
            $idUEs
        ));

        $etudiantsSem[$mat] = $etudiant;
    }
    unset($etudiant);

    // ── Stats par UE ──────────────────────────────────────────────────────────
    $statsParUE = [];
    foreach ($ues as $ue) {
        $idUE   = $ue['idUE'];
        $moyens = [];
        $nbAbsentsUE = 0;

        foreach ($etudiantsSem as $mat => $es) {
            $moy = $es['moyennes_ue'][$idUE] ?? null;
            if ($moy === null) continue;
            // Absent sur cette UE = UE non composée
            if (($es['ues'][$idUE]['non_compose'] ?? 0) == 1) { $nbAbsentsUE++; continue; }
            $moyens[] = $moy;
        }

        $eff = count($moyens);
        $statsParUE[$idUE] = [
            'idUE'             => $idUE,
            'codeUE'           => $ue['codeUE'],
            'nomUE'            => $ue['nomUE'],
            'effectif'         => $eff,
            'absents'          => $nbAbsentsUE,
            'note_min'         => $eff > 0 ? min($moyens) : 0,
            'note_max'         => $eff > 0 ? max($moyens) : 0,
            'reussite'         => $eff > 0 ? count(array_filter($moyens, fn($m) => $m >= 10)) : 0,
            'echec'            => $eff > 0 ? count(array_filter($moyens, fn($m) => $m < 10))  : 0,
            'tauxReussite'     => 0,
            'tauxEchec'        => 0,
            'tauxAbsence'      => 0,
            'intervalle_0_7'   => count(array_filter($moyens, fn($m) => $m >= 0  && $m < 7)),
            'intervalle_7_8'   => count(array_filter($moyens, fn($m) => $m >= 7  && $m < 8)),
            'intervalle_8_9'   => count(array_filter($moyens, fn($m) => $m >= 8  && $m < 9)),
            'intervalle_9_10'  => count(array_filter($moyens, fn($m) => $m >= 9  && $m < 10)),
            'intervalle_10_20' => count(array_filter($moyens, fn($m) => $m >= 10 && $m <= 20)),
        ];

        $s = &$statsParUE[$idUE];
        $s['tauxReussite'] = $eff > 0 ? round($s['reussite'] / $eff * 100, 1) : 0;
        $s['tauxEchec']    = $eff > 0 ? round($s['echec']    / $eff * 100, 1) : 0;
        $s['tauxAbsence']  = ($eff + $nbAbsentsUE) > 0 ? round($nbAbsentsUE / ($eff + $nbAbsentsUE) * 100, 1) : 0;
        unset($s);
    }

    // ── Stats globales ────────────────────────────────────────────────────────
    $nbTotal     = count($etudiantsSem);
    $nbAbsents   = count(array_filter($etudiantsSem, fn($e) => !empty($e['est_absent'])));
    $nbInvalides = count(array_filter($etudiantsSem, fn($e) => $e['statut'] === 'Invalide'));

    $deliberes   = array_filter($etudiantsSem, fn($e) => empty($e['est_absent']) && $e['statut'] !== 'Invalide');
    $nbDeliberes = count($deliberes);
    $nbValides   = count(array_filter($deliberes, fn($e) => $e['statut'] === 'Semestre validé'));
    $echecGlob   = count(array_filter($deliberes, fn($e) => $e['statut'] === 'Semestre non validé'));

    $moyennesSem = array_column(array_values($deliberes), 'moy_sem');

    return [
        'success'       => true,
        'ctx'           => $ctx,
        'annee'         => $annee,
        'statsParUE'    => array_values($statsParUE),
        'statsGlobales' => [
            'effectif'     => $nbTotal,
            'presents'     => $nbDeliberes,
            'absents'      => $nbAbsents,
            'reussite'     => $nbValides,
            'echec'        => $echecGlob,
            'tauxReussite' => $nbDeliberes > 0 ? round($nbValides  / $nbDeliberes * 100, 1) : 0,
            'tauxEchec'    => $nbDeliberes > 0 ? round($echecGlob  / $nbDeliberes * 100, 1) : 0,
            'moy_sem'      => count($moyennesSem) > 0 ? round(array_sum($moyennesSem) / count($moyennesSem), 2) : 0,
            'max_sem'      => count($moyennesSem) > 0 ? max($moyennesSem) : 0,
            'min_sem'      => count($moyennesSem) > 0 ? min($moyennesSem) : 0,
            'totalPoids'   => $totalPoids,
        ],
    ];
}

// ── Fonction partagée : absents via pedagogie_notes ───────────────────────────
// Absent d'une UE = non_compose = 1 sur TOUS ses ECs de cette UE.
// Dès qu'il a composé sur au moins 1 EC → il est considéré présent.
// Retourne : ['counts' => [idUE => nb], 'matricules' => [idUE => [mat => true]]]
function _calcAbsentsParUE($pdo, array $idUEs)
{
    if (empty($idUEs)) return ['counts' => [], 'matricules' => []];

    $ph = implode(',', array_fill(0, count($idUEs), '?'));

    // 1. Corriger _calcAbsentsParUE — supprimer la sous-requête idNote
    $stmt = $pdo->prepare("
    SELECT
        vec.idUE,
        vec.matricule,
        COUNT(DISTINCT vec.idEC) AS nb_ec_ue,
        COUNT(DISTINCT CASE WHEN pn.non_compose = 1 AND pn.nature = 2 THEN vec.idEC END) AS nb_ec_non_compose
    FROM vue_etudiants_complete vec
    LEFT JOIN pedagogie_notes pn
        ON  pn.idEc         = vec.idEC
        AND pn.idInscription = vec.idInscription
    WHERE vec.idUE IN ($ph)
      AND vec.sync_version = (SELECT MAX(sync_version) FROM vue_etudiants_complete WHERE idUE = vec.idUE)
    GROUP BY vec.idUE, vec.matricule
");
    $stmt->execute($idUEs);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $counts     = [];
    $matricules = []; // [idUE][matricule] = true
    foreach ($rows as $row) {
        if (
            intval($row['nb_ec_ue']) > 0 &&
            intval($row['nb_ec_non_compose']) === intval($row['nb_ec_ue'])
        ) {
            $counts[$row['idUE']]                    = ($counts[$row['idUE']] ?? 0) + 1;
            $matricules[$row['idUE']][$row['matricule']] = true;
        }
    }

    return ['counts' => $counts, 'matricules' => $matricules];
}
