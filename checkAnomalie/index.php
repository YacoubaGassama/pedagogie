<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

// Fonctions de vérification (à inclure ou définir ici)
// Note: Assurez-vous que ces fonctions sont disponibles (incluses depuis un fichier externe ou définies ci-dessous)

/**
 * Récupère les notes des étudiants pour une UE donnée
 */
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
                pn.justifier,
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
            if ($ecData['examen_non_compose']) {
                $ecData["note_finale_ec"] = null;
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

/**
 * Calcule les statistiques complètes pour une UE
 */
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

/**
 * Vérifie la complétude des évaluations pour une UE
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
            if (!empty($ecData['examen_non_compose'])) {
                $anomalies[] = [
                    'ec_id'      => $ecId,
                    'ec_nom'     => $ecUE['nom'],
                    'type'       => 'examen_non_compose',
                    'raison'     => 'non_compose',
                    'message'    => 'Étudiant non composé à l\'examen — note = 0',
                    'bloquant'   => false  // ← non bloquant
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

// ============================================
// EXÉCUTION PRINCIPALE
// ============================================

try {
    // Récupérer les paramètres de la requête (optionnel)
    $format = isset($_GET['format']) ? $_GET['format'] : 'json';
    $export = isset($_GET['export']) ? $_GET['export'] : false;
    $idUESpecifique = isset($_GET['id_ue']) ? intval($_GET['id_ue']) : null;
    
    // Requête pour récupérer les UEs
    $sqlUE = "SELECT u.id, u.code, u.nom, u.nombre_credit
              FROM ue u
              JOIN maquette_ue mu ON mu.id_ue = u.id
              JOIN maquette m ON m.id = mu.id_maquette
              WHERE m.idEtat = 3 AND m.idOption IN (
                  SELECT o.id FROM options o 
                  JOIN filieres fil ON fil.id = o.idFilieres
                  WHERE fil.idDepartements IN (SELECT id FROM departements)
              )";
    
    // Si un ID spécifique est demandé
    if ($idUESpecifique) {
        $sqlUE .= " AND u.id = :id_ue";
    }
    
    $sqlUE .= " ORDER BY u.code";
    
    $stmtUE = $pdo->prepare($sqlUE);
    
    if ($idUESpecifique) {
        $stmtUE->bindParam(':id_ue', $idUESpecifique, PDO::PARAM_INT);
    }
    
    $stmtUE->execute();
    $ues = $stmtUE->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($ues)) {
        echo json_encode([
            'success' => false,
            'message' => 'Aucune UE trouvée',
            'data' => []
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Tableau pour stocker les résultats
    $resultats = [
        'success' => true,
        'date_verification' => date('Y-m-d H:i:s'),
        'total_ues' => count($ues),
        'ues' => []
    ];
    
    // Statistiques globales
    $statsGlobales = [
        'total_inscrits' => 0,
        'total_etudiants_avec_notes' => 0,
        'total_reussite' => 0,
        'total_complets' => 0,
        'total_incomplets' => 0
    ];
    
    // Parcourir chaque UE
    foreach ($ues as $ue) {
        $idUE = $ue['id'];
        $codeUE = $ue['code'];
        
        // Obtenir les données
        $completude = verifierCompletudeEvaluationsUE($pdo, $idUE);
        $statistiques = getStatistiquesCompletes($pdo, $idUE);
        
        // Ajouter aux résultats
        $resultats['ues'][] = [
            'id' => $idUE,
            'code' => $codeUE,
            'nom' => $ue['nom'],
            'credit' => $ue['nombre_credit'],
            'statistiques' => $statistiques,
            'completude' => [
                'total_etudiants' => $completude['total_etudiants'],
                'etudiants_complets' => $completude['etudiants_complets'],
                'etudiants_incomplets' => $completude['etudiants_incomplets'],
                'pourcentage_complets' => $completude['pourcentage_complets'],
                'pourcentage_incomplets' => $completude['pourcentage_incomplets'],
                'raisons_incompletude' => $completude['raisons_incompletude'],
                'liste_etudiants_incomplets' => $completude['liste_etudiants_incomplets'],
                'statistiques_supplementaires' => $completude['statistiques_supplementaires']
            ]
        ];
        
        // Mettre à jour les stats globales
        $statsGlobales['total_inscrits'] += $statistiques['total_inscrits'];
        $statsGlobales['total_etudiants_avec_notes'] += $statistiques['effectif'];
        $statsGlobales['total_reussite'] += $statistiques['reussite'];
        $statsGlobales['total_complets'] += $completude['etudiants_complets'];
        $statsGlobales['total_incomplets'] += $completude['etudiants_incomplets'];
    }
    
    // Calculer les pourcentages globaux
    $statsGlobales['taux_reussite_global'] = $statsGlobales['total_etudiants_avec_notes'] > 0 
        ? round(($statsGlobales['total_reussite'] / $statsGlobales['total_etudiants_avec_notes']) * 100, 2)
        : 0;
    
    $statsGlobales['taux_completude_global'] = $statsGlobales['total_inscrits'] > 0
        ? round(($statsGlobales['total_complets'] / $statsGlobales['total_inscrits']) * 100, 2)
        : 0;
    
    $resultats['statistiques_globales'] = $statsGlobales;
    
    // Gestion de l'export CSV si demandé
    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="verification_ues_' . date('Ymd_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // En-têtes CSV
        fputcsv($output, [
            'Code UE',
            'Nom UE',
            'Crédits',
            'Total inscrits',
            'Ayant composé',
            'Réussite',
            'Taux réussite (%)',
            'Moyenne',
            'Min',
            'Max',
            'Étudiants complets',
            'Étudiants incomplets',
            'Taux complétude (%)'
        ]);
        
        // Données
        foreach ($resultats['ues'] as $ue) {
            fputcsv($output, [
                $ue['code'],
                $ue['nom'],
                $ue['nombre_credit'],
                $ue['statistiques']['total_inscrits'],
                $ue['statistiques']['effectif'],
                $ue['statistiques']['reussite'],
                $ue['statistiques']['tauxReussite'],
                $ue['statistiques']['moyenne'],
                $ue['statistiques']['min'],
                $ue['statistiques']['max'],
                $ue['completude']['etudiants_complets'],
                $ue['completude']['etudiants_incomplets'],
                $ue['completude']['pourcentage_complets']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    // Retourner en JSON par défaut
    echo json_encode($resultats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la vérification: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}