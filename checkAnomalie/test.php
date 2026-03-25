<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? 'getAnomalies';

switch ($action) {

    case 'getFiltres':
        try {
            // Récupérer tous les filtres
            $filieres = getFilieres($pdo);
            $niveaux = getNiveaux($pdo);
            $options = getOptions($pdo);
            $semestres = getSemestres($pdo);

            echo json_encode([
                'success' => true,
                'filieres' => $filieres,
                'niveaux' => $niveaux,
                'options' => $options,
                'semestres' => $semestres
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        break;
    case 'getOptionsByFiltres':
        try {
            $idFiliere  = $_GET['idFiliere']         ?? null;
            $idNiveau   = $_GET['idNiveauFormation'] ?? null;

            $options = getOptions($pdo, $idFiliere ?: null, $idNiveau ?: null);

            echo json_encode([
                'success' => true,
                'options' => $options
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
    case 'verifierEvaluationsUE':
        $idUE = $_GET['idUE'] ?? null;
        $session_id = $_GET['session_id'] ?? 1;

        if ($idUE) {
            try {
                $stats = verifierCompletudeEvaluationsUE($pdo, $idUE, $session_id);
                $statistiques = getStatistiquesCompletes($pdo, $idUE);

                echo json_encode([
                    'success' => true,
                    'idUE' => (int)$idUE,
                    'stats' => $stats,
                    'statistiques' => $statistiques
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } catch (Throwable $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'ID UE manquant'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        break;

    case 'verifierEvaluationsToutesUE':
    try {
        $idOption   = isset($_GET['idOption']) && $_GET['idOption'] !== '' ? (int)$_GET['idOption'] : null;
        $idNiveau   = isset($_GET['idNiveau']) && $_GET['idNiveau'] !== '' ? (int)$_GET['idNiveau'] : null;
        $idFiliere  = isset($_GET['idFiliere']) && $_GET['idFiliere'] !== '' ? (int)$_GET['idFiliere'] : null;
        $idSemestre = isset($_GET['idSemestre']) && $_GET['idSemestre'] !== '' ? (int)$_GET['idSemestre'] : null;
        $session_id = isset($_GET['session_id']) && $_GET['session_id'] !== '' ? (int)$_GET['session_id'] : 1;

        /**
         * Classes actives
         */
        $sqlClasses = "
            SELECT
                o.id AS idOption,
                o.option AS nom_option,
                o.code_option,
                nf.id AS idNiveau,
                nf.niveau,
                f.id AS idFiliere,
                f.filiere
            FROM options o
            JOIN niveauformation nf ON nf.id = o.idNiveauFormation
            JOIN filieres f ON f.id = o.idFilieres
            WHERE o.etat = 0
        ";

        $paramsClasses = [];

        if (!empty($idOption)) {
            $sqlClasses .= " AND o.id = :idOption";
            $paramsClasses[':idOption'] = $idOption;
        } else {
            if (!empty($idNiveau)) {
                $sqlClasses .= " AND nf.id = :idNiveau";
                $paramsClasses[':idNiveau'] = $idNiveau;
            }

            if (!empty($idFiliere)) {
                $sqlClasses .= " AND f.id = :idFiliere";
                $paramsClasses[':idFiliere'] = $idFiliere;
            }
        }

        $sqlClasses .= " ORDER BY f.filiere, nf.niveau, o.option";

        $stmtClasses = $pdo->prepare($sqlClasses);
        $stmtClasses->execute($paramsClasses);
        $classes = $stmtClasses->fetchAll(PDO::FETCH_ASSOC);

        if (empty($classes)) {
            echo json_encode([
                'success' => true,
                'filtres' => [
                    'idOption' => $idOption,
                    'idNiveau' => $idNiveau,
                    'idFiliere' => $idFiliere,
                    'idSemestre' => $idSemestre
                ],
                'resume' => [
                    'nb_classes' => 0,
                    'nb_saines' => 0,
                    'nb_avec_anomalie' => 0,
                    'nb_critiques' => 0,
                    'nb_avertissements' => 0
                ],
                'rapport' => []
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        /**
         * UEs par option
         */
        $sqlUEs = "
            SELECT
                u.id AS idUE,
                u.code AS code_ue,
                u.nom AS nom_ue,
                s.numInYear AS num_semestre
            FROM ue u
            LEFT JOIN semestre s ON s.id = u.id_semestre
            JOIN maquette_ue mu ON mu.id_ue = u.id
            JOIN maquette m ON m.id = mu.id_maquette AND m.idEtat = 3
            WHERE m.idOption = :idOption
        ";

        if (!empty($idSemestre)) {
            $sqlUEs .= " AND u.id_semestre = :idSemestre";
        }

        $sqlUEs .= " ORDER BY s.numInYear, u.code";
        $stmtUEs = $pdo->prepare($sqlUEs);

        /**
         * Nombre d'inscrits de la classe
         */
        $stmtInscrits = $pdo->prepare("
            SELECT COUNT(DISTINCT sip.matricule) AS nb_inscrits
            FROM scolarite_inscription_pedagogique sip
            WHERE sip.idOption = :idOption
              AND sip.statut = 1
        ");

        $libellesRaisons = [
            'aucune_note'            => 'Aucune note pour un EC',
            'pas_examen'             => "Note d'examen manquante",
            'note_non_calculable'    => 'Note finale non calculable',
            'moyenne_non_calculable' => 'Moyenne UE non calculable',
            'non_compose'            => "Etudiant non compose a l'examen",
            'devoir_manquant'        => 'Devoir manquant',
            'devoir_incomplet'       => 'Devoir incomplet',
            'plusieurs_examens'      => "Plusieurs notes d'examen saisies pour un meme EC",
            'examen_non_compose'     => "Non compose a l'examen — note = 0",
            'double_examen'          => "Plusieurs notes d'examen détectées pour le même EC"
        ];

        $raisonsAvertissement = ['non_compose', 'devoir_manquant', 'devoir_incomplet'];

        $rapport = [];

        foreach ($classes as $classe) {
            $idOpt = (int)$classe['idOption'];
            $anomaliesClasse = [];

            $stmtInscrits->execute([':idOption' => $idOpt]);
            $nbInscrits = (int)$stmtInscrits->fetchColumn();

            $paramsUE = [':idOption' => $idOpt];
            if (!empty($idSemestre)) {
                $paramsUE[':idSemestre'] = $idSemestre;
            }

            $stmtUEs->execute($paramsUE);
            $ues = $stmtUEs->fetchAll(PDO::FETCH_ASSOC);

            if ($nbInscrits === 0 && empty($ues)) {
                $anomaliesClasse[] = [
                    'idUE' => null,
                    'code_ue' => null,
                    'nom_ue' => null,
                    'semestre' => null,
                    'nb_etudiants' => 0,
                    'nb_complets' => 0,
                    'nb_incomplets' => 0,
                    'taux_completude' => 0,
                    'details_completude' => [],
                    'statistiques_supplementaires' => [],
                    'statistiques_ue' => [],
                    'anomalies' => [[
                        'type' => 'aucun_inscrit',
                        'libelle' => 'Aucun etudiant inscrit',
                        'gravite' => 'critique',
                        'detail' => null,
                        'etudiants' => []
                    ]]
                ];
            } else {
                if ($nbInscrits === 0) {
                    $anomaliesClasse[] = [
                        'idUE' => null,
                        'code_ue' => null,
                        'nom_ue' => null,
                        'semestre' => null,
                        'nb_etudiants' => 0,
                        'nb_complets' => 0,
                        'nb_incomplets' => 0,
                        'taux_completude' => 0,
                        'details_completude' => [],
                        'statistiques_supplementaires' => [],
                        'statistiques_ue' => [],
                        'anomalies' => [[
                            'type' => 'aucun_inscrit',
                            'libelle' => 'Aucun etudiant inscrit',
                            'gravite' => 'critique',
                            'detail' => null,
                            'etudiants' => []
                        ]]
                    ];
                }

                if (empty($ues)) {
                    $anomaliesClasse[] = [
                        'idUE' => null,
                        'code_ue' => null,
                        'nom_ue' => null,
                        'semestre' => null,
                        'nb_etudiants' => 0,
                        'nb_complets' => 0,
                        'nb_incomplets' => 0,
                        'taux_completude' => 0,
                        'details_completude' => [],
                        'statistiques_supplementaires' => [],
                        'statistiques_ue' => [],
                        'anomalies' => [[
                            'type' => 'aucune_ue',
                            'libelle' => 'Aucune UE trouvée',
                            'gravite' => 'critique',
                            'detail' => null,
                            'etudiants' => []
                        ]]
                    ];
                }

                foreach ($ues as $ue) {
                    $idUE = (int)$ue['idUE'];
                    $anomaliesUE = [];

                    $verif = verifierUE($pdo, $idUE, $session_id);

                    if (($verif['total_etudiants'] ?? 0) === 0) {
                        $anomaliesUE[] = [
                            'type' => 'aucune_note',
                            'libelle' => 'Aucune note saisie',
                            'gravite' => 'critique',
                            'detail' => null,
                            'etudiants' => []
                        ];
                    }

                    if (!empty($verif['raisons_incompletude'])) {
                        foreach ($verif['raisons_incompletude'] as $raison => $nb) {
                            $etudiantsConcernes = array_values(array_filter(
                                $verif['liste_etudiants_incomplets'],
                                function ($etu) use ($raison) {
                                    foreach (($etu['anomalies'] ?? []) as $a) {
                                        if (($a['raison'] ?? null) === $raison) {
                                            return true;
                                        }
                                    }
                                    return false;
                                }
                            ));

                            $anomaliesUE[] = [
                                'type'      => $raison,
                                'libelle'   => $libellesRaisons[$raison] ?? $raison,
                                'gravite'   => in_array($raison, $raisonsAvertissement, true) ? 'avertissement' : 'critique',
                                'detail'    => $nb . ' etudiant(s) concerne(s)',
                                'etudiants' => $etudiantsConcernes
                            ];
                        }
                    }

                    foreach (($verif['details_completude'] ?? []) as $detailEtu) {
                        foreach (($detailEtu['anomalies'] ?? []) as $anomalie) {
                            if (($anomalie['bloquant'] ?? true) === false) {
                                $anomaliesUE[] = [
                                    'type' => $anomalie['type'] ?? 'anomalie',
                                    'libelle' => $anomalie['libelle'] ?? $anomalie['message'] ?? ($anomalie['type'] ?? 'Anomalie'),
                                    'gravite' => 'avertissement',
                                    'detail' => $anomalie['detail'] ?? null,
                                    'etudiants' => [[
                                        'matricule' => $detailEtu['matricule'] ?? null,
                                        'nom' => $detailEtu['nom'] ?? null,
                                        'moyenne_ue' => $detailEtu['moyenne_ue'] ?? 0,
                                        'ec_valides' => $detailEtu['ec_valides'] ?? 0,
                                        'ec_attendus' => $detailEtu['ec_attendus'] ?? 0,
                                        'anomalies' => [$anomalie]
                                    ]]
                                ];
                            }
                        }
                    }

                    if (!empty($anomaliesUE)) {
                        $anomaliesClasse[] = [
                            'idUE' => $idUE,
                            'code_ue' => $ue['code_ue'],
                            'nom_ue' => $ue['nom_ue'],
                            'semestre' => 'S' . ($ue['num_semestre'] ?? ''),
                            'nb_etudiants' => $verif['total_etudiants'] ?? 0,
                            'nb_complets' => $verif['etudiants_complets'] ?? 0,
                            'nb_incomplets' => $verif['etudiants_incomplets'] ?? 0,
                            'taux_completude' => $verif['pourcentage_complets'] ?? 0,
                            'details_completude' => $verif['details_completude'] ?? [],
                            'statistiques_supplementaires' => $verif['statistiques_supplementaires'] ?? [],
                            'statistiques_ue' => $verif['statistiques_ue'] ?? [],
                            'anomalies' => $anomaliesUE
                        ];
                    }
                }
            }

            $nbCritiques = 0;
            $nbAvertissements = 0;

            foreach ($anomaliesClasse as $a) {
                foreach (($a['anomalies'] ?? []) as $an) {
                    if (($an['gravite'] ?? null) === 'critique') {
                        $nbCritiques++;
                    }
                    if (($an['gravite'] ?? null) === 'avertissement') {
                        $nbAvertissements++;
                    }
                }
            }

            $rapport[] = [
                'idOption' => $idOpt,
                'filiere' => $classe['filiere'],
                'niveau' => $classe['niveau'],
                'option' => $classe['nom_option'],
                'code' => $classe['code_option'],
                'nb_inscrits' => $nbInscrits,
                'nb_ues_maquette' => count($ues),
                'nb_ues_deliberees' => 0,
                'nb_critiques' => $nbCritiques,
                'nb_avertissements' => $nbAvertissements,
                'saine' => empty($anomaliesClasse),
                'anomalies' => $anomaliesClasse
            ];
        }

        $nbClassesSaines = count(array_filter($rapport, function ($r) {
            return !empty($r['saine']);
        }));

        $nbTotalCritiques = array_sum(array_column($rapport, 'nb_critiques'));
        $nbTotalAvert = array_sum(array_column($rapport, 'nb_avertissements'));

        echo json_encode([
            'success' => true,
            'filtres' => [
                'idOption' => $idOption,
                'idNiveau' => $idNiveau,
                'idFiliere' => $idFiliere,
                'idSemestre' => $idSemestre
            ],
            'resume' => [
                'nb_classes' => count($rapport),
                'nb_saines' => $nbClassesSaines,
                'nb_avec_anomalie' => count($rapport) - $nbClassesSaines,
                'nb_critiques' => $nbTotalCritiques,
                'nb_avertissements' => $nbTotalAvert
            ],
            'rapport' => $rapport
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    break;

    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Action non trouvee'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;
}

function getLibellesRaisons()
{
    return [
        'aucune_evaluation'      => "Aucune évaluation (ni devoir ni examen) n'a été saisie pour cet étudiant.",
        'aucune_note'            => "Aucune note enregistrée pour cet EC.",
        'pas_examen'             => "La note d'examen est absente pour cet EC.",
        'double_examen'          => "Plusieurs notes d'examen ont été détectées pour cet EC (doublon).",
        'note_non_calculable'    => "La note finale de cet EC ne peut pas être calculée.",
        'moyenne_non_calculable' => "La moyenne de l'UE ne peut pas être calculée (EC incomplets ou invalides).",
        'non_compose'            => "L'étudiant est non composé à l'examen.",
        'devoir_manquant'        => "Les notes de devoir sont manquantes.",
        'devoir_incomplet'       => "Les notes de devoir sont incomplètes."
    ];
}

/**
 * Filtres optionnels
 */
$idOption   = $_GET['idOption']   ?? null;
$idNiveau   = $_GET['idNiveau']   ?? null;
$idFiliere  = $_GET['idFiliere']  ?? null;
$idSemestre = $_GET['idSemestre'] ?? null;

function getFilieres($pdo)
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT f.id, f.filiere
        FROM filieres f
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNiveaux($pdo)
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT nf.id, nf.niveau
        FROM niveauformation nf
        ORDER BY nf.niveau
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getOptions($pdo, $idFiliere = null, $idNiveau = null)
{
    $sql = "
        SELECT
            o.id,
            o.option,
            o.code_option,
            o.idFilieres,
            o.idNiveauFormation
        FROM options o
    ";

    $conditions = [];
    $params = [];

    if ($idFiliere !== null) {
        $conditions[] = "o.idFilieres = :idFiliere";
        $params[':idFiliere'] = $idFiliere;
    }

    if ($idNiveau !== null) {
        $conditions[] = "o.idNiveauFormation = :idNiveau";
        $params[':idNiveau'] = $idNiveau;
    }

    if (!empty($conditions)) {
        $sql .= " WHERE o.etat = 0 AND " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY o.option";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSemestres($pdo)
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            s.id,
            s.numInYear,
            CONCAT('Semestre ', s.numero) AS semestre
        FROM semestre s
        ORDER BY s.numInYear
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function getAllFiltresData($pdo)
{
    return [
        'filieres' => getFilieres($pdo),
        'niveaux' => getNiveaux($pdo),
        'options' => getOptions($pdo),
        'semestres' => getSemestres($pdo)
    ];
}

/**
 * Session de calcul par défaut
 */
$session_id = 1;

/**
 * Statistiques académiques complètes de l'UE
 */
function getStatistiquesCompletes($pdo, $ueId)
{
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

    $effectif = count($moyennesEtudiants);
    $reussite = 0;
    $echec = 0;
    $totalMoyenne = 0;
    $minMoyenne = $effectif > 0 ? 20 : 0;
    $maxMoyenne = 0;

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

        if ($moyenne < $minMoyenne) $minMoyenne = $moyenne;
        if ($moyenne > $maxMoyenne) $maxMoyenne = $moyenne;

        if ($moyenne >= 10) {
            $reussite++;
        } else {
            $echec++;
        }

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

    $tauxReussite = $effectif > 0 ? ($reussite / $effectif) * 100 : 0;
    $tauxEchec = $effectif > 0 ? ($echec / $effectif) * 100 : 0;
    $moyenneGenerale = $effectif > 0 ? $totalMoyenne / $effectif : 0;

    $sqlTotalInscrits = "SELECT COUNT(DISTINCT sipu.matricule) as total_inscrits
                        FROM scolarite_inscription_pedagogique_ue sipu
                        JOIN scolarite_inscription_pedagogique sip ON sipu.idInscriptionPedagogique = sip.id
                        WHERE sipu.idUE = :ueId AND sip.statut = 1";

    $stmtTotal = $pdo->prepare($sqlTotalInscrits);
    $stmtTotal->execute([':ueId' => $ueId]);
    $totalData = $stmtTotal->fetch(PDO::FETCH_ASSOC);
    $totalInscrits = intval($totalData['total_inscrits'] ?? 0);

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
        'non_composes' => $totalInscrits - $effectif
    ], $intervalles);
}

/**
 * Vérification de complétude pédagogique de l'UE
 * Nécessite getEtudiantByUE($pdo, $idUE, $session_id)
 */
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

    $sqlEC = "
        SELECT ec.id, ec.nom, COALESCE(ec.coefficient, 1) AS coefficient
        FROM ec
        WHERE ec.id_ue = :idUE
        ORDER BY ec.nom
    ";
    $stmtEC = $pdo->prepare($sqlEC);
    $stmtEC->execute([':idUE' => $idUE]);
    $ecsUE = $stmtEC->fetchAll(PDO::FETCH_ASSOC);

    $ecsUEIndexed = [];
    foreach ($ecsUE as $ec) {
        $ecsUEIndexed[$ec['id']] = $ec;
    }
    $nbECTotal = count($ecsUEIndexed);

    foreach ($etudiants as $etudiant) {
        $anomalies = [];
        $ecsEtudiantIndexed = [];

        foreach (($etudiant['ec'] ?? []) as $ec) {
            $ecsEtudiantIndexed[$ec['id']] = $ec;
        }

        foreach ($ecsUEIndexed as $ecId => $ecUE) {
            $ecData = $ecsEtudiantIndexed[$ecId] ?? null;

            if ($ecData === null || empty($ecData['a_ligne_evaluation'])) {
                $anomalies[] = [
                    'ec_id' => $ecId,
                    'ec_nom' => $ecUE['nom'],
                    'type' => 'aucune_evaluation',
                    'raison' => 'aucune_evaluation',
                    'message' => "Aucune ligne d'evaluation enregistree dans pedagogie_notes",
                    'bloquant' => true
                ];
                continue;
            }

            if (empty($ecData['a_examen'])) {
                $anomalies[] = [
                    'ec_id' => $ecId,
                    'ec_nom' => $ecUE['nom'],
                    'type' => 'examen_manquant',
                    'raison' => 'pas_examen',
                    'message' => "Aucune ligne d'examen pour cet EC",
                    'note_devoir' => $ecData['note_devoir'] ?? null,
                    'note_examen' => null,
                    'bloquant' => true
                ];
                continue;
            }

            // Plusieurs notes d'examen = erreur bloquante
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
            

            // Non composé à l'examen = note finale 0, avertissement uniquement
            if (!empty($ecData['examen_non_compose']) && ($ecData['calcul_mode'] ?? '') === 'examen_non_compose') {
                $anomalies[] = [
                    'ec_id'   => $ecId,
                    'ec_nom'  => $ecUE['nom'],
                    'type'    => 'examen_non_compose',
                    'raison'  => 'non_compose',
                    'message' => "Non compose a l'examen — note finale = 0",
                    'bloquant'=> false
                ];
                // Ne pas bloquer : note_finale_ec = 0 est valide
                continue;
            }

            // Note finale non calculable (autre raison)
            if (!isset($ecData['note_finale_ec']) || $ecData['note_finale_ec'] === null) {
                $anomalies[] = [
                    'ec_id'       => $ecId,
                    'ec_nom'      => $ecUE['nom'],
                    'type'        => 'note_non_calculable',
                    'raison'      => 'note_non_calculable',
                    'message'     => 'Note finale non calculable — ' . ($ecData['calcul_mode'] ?? 'raison inconnue'),
                    'note_devoir' => $ecData['note_devoir'] ?? null,
                    'note_examen' => $ecData['note_examen'] ?? null,
                    'bloquant'    => true
                ];
            }
        }

        $anomaliesBloquantes = array_values(array_filter(
            $anomalies,
            fn($a) => ($a['bloquant'] ?? false) === true
        ));

        $nbECValides = 0;
        foreach (($etudiant['ec'] ?? []) as $ec) {
            if (
                // EC normal avec note calculée
                (!empty($ec['a_ligne_evaluation']) && isset($ec['note_finale_ec']) && $ec['note_finale_ec'] !== null)
                ||
                // EC non composé : note finale = 0 est valide
                (!empty($ec['examen_non_compose']) && ($ec['calcul_mode'] ?? '') === 'examen_non_compose')
            ) {
                $nbECValides++;
            }
        }

        $moyenneUE = $etudiant['moyenne_ue'] ?? 0;
        $moyenneCalculable = ($nbECValides === $nbECTotal && $nbECTotal > 0);

        if (!$moyenneCalculable) {
            $anomalies[] = [
                'ec_id' => null,
                'ec_nom' => null,
                'type' => 'moyenne_non_calculable',
                'raison' => 'moyenne_non_calculable',
                'message' => 'Moyenne UE non calculable - EC incomplets',
                'ec_manquants' => $nbECTotal - $nbECValides,
                'bloquant' => true
            ];
            $anomaliesBloquantes[] = end($anomalies);
        }

        $estComplet = empty($anomaliesBloquantes);
        $statut = $estComplet ? 'complet' : 'incomplet';

        if ($estComplet) {
            $stats['etudiants_complets']++;
        } else {
            $stats['etudiants_incomplets']++;

            $stats['liste_etudiants_incomplets'][] = [
                'matricule' => $etudiant['matricule'],
                'nom' => trim(($etudiant['prenom'] ?? '') . ' ' . ($etudiant['nom'] ?? '')),
                'moyenne_ue' => $moyenneUE,
                'ec_valides' => $nbECValides,
                'ec_manquants' => $nbECTotal - $nbECValides,
                'ec_attendus' => $nbECTotal,
                'anomalies' => $anomaliesBloquantes
            ];

            foreach ($anomaliesBloquantes as $anomalie) {
                $raison = $anomalie['raison'];
                $stats['raisons_incompletude'][$raison] = ($stats['raisons_incompletude'][$raison] ?? 0) + 1;
            }
        }

        $stats['details_completude'][] = [
            'matricule' => $etudiant['matricule'],
            'nom' => trim(($etudiant['prenom'] ?? '') . ' ' . ($etudiant['nom'] ?? '')),
            'statut' => $statut,
            'moyenne_ue' => $moyenneUE,
            'moyenne_calculable' => $moyenneCalculable,
            'ec_presents' => count(array_filter($etudiant['ec'] ?? [], fn($ec) => !empty($ec['a_ligne_evaluation']))),
            'ec_valides' => $nbECValides,
            'ec_attendus' => $nbECTotal,
            'anomalies' => $anomalies,
            'anomalies_bloquantes' => $anomaliesBloquantes
        ];
    }

    $total = $stats['total_etudiants'];
    $stats['pourcentage_complets'] = $total > 0 ? round(($stats['etudiants_complets'] / $total) * 100, 2) : 0;
    $stats['pourcentage_incomplets'] = $total > 0 ? round(($stats['etudiants_incomplets'] / $total) * 100, 2) : 0;

    $sommeMoyennes = 0;
    $nbMoyennesCalculees = 0;
    foreach ($stats['details_completude'] as $detail) {
        if (!empty($detail['moyenne_calculable']) && $detail['moyenne_ue'] > 0) {
            $sommeMoyennes += $detail['moyenne_ue'];
            $nbMoyennesCalculees++;
        }
    }

    $stats['statistiques_supplementaires'] = [
        'nb_ec_total' => $nbECTotal,
        'ecs_liste' => array_values($ecsUEIndexed),
        'moyenne_generale' => $nbMoyennesCalculees > 0 ? round($sommeMoyennes / $nbMoyennesCalculees, 2) : 0,
        'nb_etudiants_moyenne_calculee' => $nbMoyennesCalculees,
        'nb_etudiants_sans_moyenne' => $total - $nbMoyennesCalculees,
        'taux_calculabilite' => $total > 0 ? round(($nbMoyennesCalculees / $total) * 100, 2) : 0
    ];

    $stats['noteEtudiantsParEC'] = $etudiants;

    return $stats;
}
function getEtudiantByUE($pdo, $idUE, $session_id = 1)
{
    $anneeSql = "(SELECT MAX(id) FROM scolarite_anneeuniversitaire)";

    // 1. EC attendus pour l'UE
    $sqlEC = "
        SELECT 
            ec.id,
            ec.nom,
            COALESCE(ec.coefficient, 1) AS coefficient
        FROM ec
        WHERE ec.id_ue = :idUE
        ORDER BY ec.nom
    ";
    $stmtEC = $pdo->prepare($sqlEC);
    $stmtEC->execute([':idUE' => $idUE]);
    $ecs = $stmtEC->fetchAll(PDO::FETCH_ASSOC);

    $ecsIndexed = [];
    foreach ($ecs as $ec) {
        $ecsIndexed[$ec['id']] = [
            'id' => (int)$ec['id'],
            'name' => $ec['nom'],
            'coef_ec' => max(1, (float)$ec['coefficient'])
        ];
    }

    // 2. Nombre de devoirs prévus par EC
    $sqlDevoirs = "
        SELECT 
            bn.idEc, 
            COUNT(DISTINCT bn.idDevoir) AS nbDevoirs
        FROM bordereau_note bn
        WHERE bn.idNature = 1
          AND bn.idEc IN (
              SELECT ec.id
              FROM ec
              WHERE ec.id_ue = :idUE
          )
        GROUP BY bn.idEc
    ";
    $stmtDevoirs = $pdo->prepare($sqlDevoirs);
    $stmtDevoirs->execute([':idUE' => $idUE]);

    $nbDevoirsParEc = [];
    foreach ($stmtDevoirs->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $nbDevoirsParEc[$row['idEc']] = (int)$row['nbDevoirs'];
    }

    // 3. Étudiants inscrits pédagogiquement à l'UE
    $sqlEtudiants = "
        SELECT
            sip.id AS idInscription,
            --sip.idInscription,
            sipu.matricule,
            se.prenom,
            se.nom AS nomEtudiant
        FROM scolarite_inscription_pedagogique_ue sipu
        JOIN scolarite_inscription_pedagogique sip 
            ON sipu.idInscriptionPedagogique = sip.id
        JOIN scolarite_etudiants se 
            ON sipu.matricule = se.matricule
        WHERE sipu.idUE = :idUE
          AND sip.statut = 1
        ORDER BY sipu.matricule
    ";
    $stmtEtudiants = $pdo->prepare($sqlEtudiants);
    $stmtEtudiants->execute([
        ':idUE' => $idUE
    ]);
    $etudiantsRows = $stmtEtudiants->fetchAll(PDO::FETCH_ASSOC);

    $etudiants = [];
    $inscriptionsMap = [];

    foreach ($etudiantsRows as $row) {
        $matricule = $row['matricule'];
        $inscriptionsMap[$row['idInscription']] = $matricule;

        $etudiants[$matricule] = [
            'matricule' => $matricule,
            'prenom' => $row['prenom'],
            'nom' => $row['nomEtudiant'],
            'idInscription' => (int)$row['idInscription'],
            'ec' => []
        ];

        // Précharger tous les EC attendus, même sans note
        foreach ($ecsIndexed as $ecId => $ecMeta) {
            $etudiants[$matricule]['ec'][$ecId] = [
                'id' => $ecId,
                'name' => $ecMeta['name'],
                'coef_ec' => $ecMeta['coef_ec'],
                'devoirs' => [],
                'devoirs_nc_justifies' => [],
                'devoirs_nc_non_justifies' => 0,
                'examens' => [],
                'examen_non_compose' => false,
                'note_devoir' => null,
                'note_examen' => null,
                'note_finale_ec' => null,
                'a_examen' => false,
                'a_ligne_evaluation' => false,
                'nb_lignes_evaluation' => 0
            ];
        }
    }

    if (empty($etudiants)) {
        return [];
    }

    // 4. Charger les lignes de notes une seule fois
    $sqlNotes = "
        SELECT
            sip.id as idInscription,
            pn.idEc AS ec_id,
            pn.idNote,
            pn.idDevoir,
            pn.note,
            bn.idNature as nature,
            pn.non_compose,
            pn.justifier,
            ec.nom AS nomEc,
            COALESCE(ec.coefficient, 1) AS coef_ec
        FROM scolarite_inscription_pedagogique_ue sipu
        JOIN scolarite_inscription_pedagogique sip 
            ON sipu.idInscriptionPedagogique = sip.id
        JOIN pedagogie_notes pn 
            ON pn.idInscription = sip.id
           AND pn.idUe = sipu.idUE
        JOIN ec 
            ON ec.id = pn.idEc
        JOIN bordereau_note bn ON bn.idDevoir = pn.idDevoir AND bn.idNature = pn.nature

        WHERE sipu.idUE = :idUE
          AND sip.statut = 1
          AND pn.idAnnee = $anneeSql
         AND pn.session_id = :session_id
        ORDER BY sip.id, pn.idEc, pn.nature, pn.idDevoir, pn.idNote
    ";
    $stmtNotes = $pdo->prepare($sqlNotes);
    $stmtNotes->execute([
        ':idUE' => $idUE,
        ':session_id' => $session_id
    ]);
    $notesRows = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);

    foreach ($notesRows as $ligne) {
        $idInscription = (int)$ligne['idInscription'];
        if (!isset($inscriptionsMap[$idInscription])) {
            continue;
        }

        $matricule = $inscriptionsMap[$idInscription];
        $ecId = (int)$ligne['ec_id'];

        if (!isset($etudiants[$matricule]['ec'][$ecId])) {
            continue;
        }

        $type = ((int)$ligne['nature'] === 2) ? 'examen' : 'devoir';
        $nonCompose = (int)$ligne['non_compose'];
        $justifier = (int)$ligne['justifier'];
        $noteValue = max(0, min(20, (float)$ligne['note']));

        $etudiants[$matricule]['ec'][$ecId]['a_ligne_evaluation'] = true;
        $etudiants[$matricule]['ec'][$ecId]['nb_lignes_evaluation']++;

        if ($type === 'devoir') {
            if ($nonCompose === 0) {
                $etudiants[$matricule]['ec'][$ecId]['devoirs'][] = $noteValue;
            } elseif ($justifier === 1) {
                $etudiants[$matricule]['ec'][$ecId]['devoirs_nc_justifies'][] = $noteValue;
            } else {
                $etudiants[$matricule]['ec'][$ecId]['devoirs_nc_non_justifies']++;
            }
        } else {
            if ($nonCompose === 0) {
                $etudiants[$matricule]['ec'][$ecId]['examens'][] = $noteValue;
                $etudiants[$matricule]['ec'][$ecId]['a_examen'] = true;
            } else {
                $etudiants[$matricule]['ec'][$ecId]['examen_non_compose'] = true;
                $etudiants[$matricule]['ec'][$ecId]['a_examen'] = true;
            }
        }
    }

    // 5. Calculs par EC et moyenne UE
    foreach ($etudiants as &$etudiant) {
        $aTousExamens = true;
        $totalPointsUE = 0;
        $totalCoefUE = 0;
        $calculDetailUE = [];

        foreach ($etudiant['ec'] as $ecId => &$ecData) {
            $nbDevoirsPrevus = $nbDevoirsParEc[$ecId] ?? 0;
            $nbComposes = count($ecData['devoirs']);
            $nbJustifies = count($ecData['devoirs_nc_justifies']);
            $nbNonJustifies = $ecData['devoirs_nc_non_justifies'];
            $totalNonCompose = $nbJustifies + $nbNonJustifies;
            $tousNonCompose = ($nbDevoirsPrevus > 0 && $nbComposes === 0 && $totalNonCompose === $nbDevoirsPrevus);
            $tousJustifies = ($tousNonCompose && $nbNonJustifies === 0);

            $moyenneDevoir = null;
            $moyenneExamen = null;

            // Note devoir
            if ($nbDevoirsPrevus === 0) {
                $ecData['calcul_devoir'] = 'aucun_devoir_prevu';
            } elseif ($tousJustifies) {
                $ecData['calcul_devoir'] = 'tous_nc_justifies_utiliser_examen';
            } elseif ($nbNonJustifies > 0 && $nbComposes === 0 && $nbJustifies === 0) {
                $moyenneDevoir = 0;
                $ecData['calcul_devoir'] = 'tous_nc_non_justifies';
            } else {
                $diviseur = $nbDevoirsPrevus - $nbJustifies;
                if ($diviseur > 0) {
                    $somme = array_sum($ecData['devoirs']);
                    $moyenneDevoir = $somme / $diviseur;
                    $ecData['note_devoir'] = round($moyenneDevoir, 2);
                    $ecData['calcul_devoir'] = sprintf(
                        "%.2f / %d (dont %d absent(s) non justifié(s) = 0)",
                        $somme,
                        $diviseur,
                        $nbNonJustifies
                    );
                } else {
                    $ecData['calcul_devoir'] = 'tous_nc_justifies_utiliser_examen';
                }
            }

            $ecData['nb_devoirs'] = $nbDevoirsPrevus;

            // Note examen
// ── VÉRIFICATION PRIORITAIRE : pas de ligne du tout ──────────────────────────
if (!$ecData['a_ligne_evaluation']) {
    $ecData['calcul_mode']    = 'aucune_evaluation';
    $ecData['calcul_detail']  = 'Aucune ligne pedagogie_notes pour cet EC';
    $ecData['note_finale_ec'] = null;
    $aTousExamens = false;
    continue;
}

// Note examen
if (!empty($ecData["examens"]) && $ecData["examen_non_compose"]) {
    // Conflit : note saisie ET non_compose → erreur bloquante
    $ecData["note_examen"]       = null;
    $ecData["a_examen"]          = true;
    $ecData["plusieurs_examens"] = true;
    $ecData["examens_en_erreur"] = $ecData["examens"];
    $ecData["note_finale_ec"]    = null;
    $ecData["calcul_mode"]       = "erreur_plusieurs_examens";
    $ecData["calcul_detail"]     = "Conflit : note d'examen saisie ET non_compose = 1";
    $aTousExamens = false;
    continue;
} elseif (!empty($ecData["examens"])) {
    if (count($ecData["examens"]) > 1) {
        $ecData["note_examen"]       = null;
        $ecData["a_examen"]          = true;
        $ecData["plusieurs_examens"] = true;
        $ecData["examens_en_erreur"] = $ecData["examens"];
        $ecData["note_finale_ec"]    = null;
        $ecData["calcul_mode"]       = "erreur_plusieurs_examens";
        $ecData["calcul_detail"]     = count($ecData["examens"]) . " notes d'examen trouvees — une seule attendue";
        $aTousExamens = false;
        continue;
    }
    $moyenneExamen         = $ecData["examens"][0];
    $ecData["note_examen"] = $moyenneExamen;
} elseif ($ecData["examen_non_compose"]) {
    // Non composé → note finale = 0, pas de blocage
    $ecData["note_finale_ec"] = 0;
    $ecData["note"]           = 0;
    $ecData["note_examen"]    = 0;
    $ecData["coef"]           = $ecData["coef_ec"];
    $ecData["calcul_mode"]    = "examen_non_compose";
    $ecData["calcul_detail"]  = "Non compose a l'examen — note finale = 0";
    $totalPointsUE += 0;
    $totalCoefUE   += $ecData["coef_ec"];
    continue;
}

// Ajustement si pas de devoir prévu ou tous devoirs justifiés
if (
    ($ecData['calcul_devoir'] ?? '') === 'tous_nc_justifies_utiliser_examen' ||
    ($ecData['calcul_devoir'] ?? '') === 'aucun_devoir_prevu'
) {
    if ($moyenneExamen !== null) {
        $moyenneDevoir         = $moyenneExamen;
        $ecData['note_devoir'] = $moyenneDevoir;
    }
}

// ── Pas de note examen (mais a_ligne_evaluation = true → devoirs seulement) ──
if ($moyenneExamen === null) {
    $ecData['calcul_mode']    = 'examen_manquant';
    $ecData['calcul_detail']  = "Aucune ligne d'examen pour cet EC";
    $ecData['note_finale_ec'] = null;
    $aTousExamens = false;
    continue;
}

if ($moyenneDevoir === null && $nbDevoirsPrevus > 0) {
    $ecData['calcul_mode']    = 'devoir_manquant';
    $ecData['calcul_detail']  = 'Devoir(s) prévu(s) mais non calculable(s)';
    $ecData['note_finale_ec'] = null;
    continue;
}

            $noteFinale = ($moyenneDevoir * 0.4) + ($moyenneExamen * 0.6);
            $ecData['note_finale_ec'] = round($noteFinale, 2);
            $ecData['calcul_mode'] = '40_60';
            $ecData['calcul_detail'] = sprintf(
                "%.2f × 0.4 + %.2f × 0.6",
                $moyenneDevoir,
                $moyenneExamen
            );

            $ecData['note'] = $ecData['note_finale_ec'];
            $ecData['coef'] = $ecData['coef_ec'];

            $totalPointsUE += $ecData['note_finale_ec'] * $ecData['coef_ec'];
            $totalCoefUE += $ecData['coef_ec'];
            $calculDetailUE[] = sprintf("%.2f × %.1f", $ecData['note_finale_ec'], $ecData['coef_ec']);
        }

        $etudiant['ec'] = array_values($etudiant['ec']);

        if ($aTousExamens && $totalCoefUE > 0) {
            $etudiant['moyenne_ue'] = round($totalPointsUE / $totalCoefUE, 2);
            $etudiant['moyenne_calculable'] = true;
        } else {
            $etudiant['moyenne_ue'] = 0;
            $etudiant['moyenne_calculable'] = false;
            $calculDetailUE = ['Moyenne non calculable'];
        }

        $aExamenNonCompose = false;
        foreach ($etudiant['ec'] as $ec) {
            if (!empty($ec['examen_non_compose'])) {
                $aExamenNonCompose = true;
                break;
            }
        }

        $raisonNonRepechable = null;
        if (!$etudiant['moyenne_calculable']) {
            $raisonNonRepechable = 'moyenne_non_calculable';
        } elseif ($aExamenNonCompose) {
            $raisonNonRepechable = 'non_compose_examen';
        }

        $etudiant['stats'] = [
            'nb_ec' => count($etudiant['ec']),
            'nb_ec_avec_examen' => count(array_filter($etudiant['ec'], fn($ec) => $ec['a_examen'])),
            'nb_ec_sans_examen' => count(array_filter($etudiant['ec'], fn($ec) => !$ec['a_examen'])),
            'moyenne_ue_formatee' => $etudiant['moyenne_calculable'] ? number_format($etudiant['moyenne_ue'], 2) : 'N/A',
            'total_coef_ue' => array_sum(array_column($etudiant['ec'], 'coef_ec')),
            'calcul_detail' => implode(' + ', $calculDetailUE),
            'moyenne_calculable' => $etudiant['moyenne_calculable'],
            'est_repechable' => (
                $etudiant['moyenne_calculable']
                && $etudiant['moyenne_ue'] < 10
                && !$aExamenNonCompose
            ),
            'non_repechable_raison' => $raisonNonRepechable
        ];
    }

    return array_values($etudiants);
}
/**
 * Wrapper unifié utilisé par la page anomalies
 */
function verifierUE($pdo, $idUE, $session_id = 1)
{
    $completude = verifierCompletudeEvaluationsUE($pdo, $idUE, $session_id);
    $completude['statistiques_ue'] = getStatistiquesCompletes($pdo, $idUE);
    return $completude;
}

