<?php
/**
 * repechage_ue_exact10.php
 * ------------------------------------------------------------
 * Objectif PRIORITAIRE (règle de base) :
 *   - Si la moyenne UE < 10, calculer exactement les points manquants
 *     P = (10.00 - moyenne)*SommeCoef
 *   - Redistribuer ces points (notes flottantes acceptées) pour obtenir
 *     une moyenne UE finale EXACTEMENT = 10.00 (si atteignable avec plafond 20).
 *
 * Options (SOFT, non contraignantes) :
 *   - strategy: neutral / favor_low / favor_high (influence la redistribution)
 *   - lock_ge10: essayer de ne pas toucher les EC initialement >= 10,
 *                MAIS si ça empêche d'atteindre 10.00, l'option est ignorée.
 *   - rounding_step: uniquement pour AFFICHAGE (arrondi visuel). Les notes
 *                    internes restent flottantes pour garantir 10.00.
 */

$targetAvg = 10.0;
$maxNote   = 20.0;

// -----------------------------
// Utilitaires
// -----------------------------
function clamp($x, $a, $b) { return max($a, min($b, $x)); }

function sumCoef($ec){
  $s = 0.0;
  foreach($ec as $e) $s += $e["coef"];
  return $s;
}

function weightedSum($ec){
  $sum = 0.0;
  foreach($ec as $e) $sum += $e["coef"] * $e["note"];
  return $sum;
}

function weightedAverage($ec){
  $sc = sumCoef($ec);
  return ($sc > 0) ? (weightedSum($ec) / $sc) : 0.0;
}

/**
 * Poids selon stratégie (influence la répartition)
 * neutral:    w_i = coef
 * favor_low:  w_i = coef * (20 - note)
 * favor_high: w_i = coef * note
 */
function computeWeights($ec, $strategy, $maxNote=20.0){
  $w = [];
  $eps = 1e-6;
  foreach($ec as $i => $e){
    $coef = $e["coef"];
    $note = $e["note"];
    if($strategy === "favor_low"){
      $w[$i] = $coef * max($eps, ($maxNote - $note));
    } elseif($strategy === "favor_high"){
      $w[$i] = $coef * max($eps, $note);
    } else {
      $w[$i] = $coef;
    }
  }
  return $w;
}

/**
 * Redistribution continue (water-filling) en "points UE" :
 *   - On veut distribuer P points UE : somme(coef_i * delta_i) = P
 *   - Plafond note <= 20
 *   - lock_ge10 est appliqué si $lockStrict = true (sinon ignoré)
 *
 * Retour: reste non distribué (si plafonds bloquent)
 */
function redistributeContinuous(&$ec, $pointsUE, $strategy, $lockStrict, $maxNote=20.0){
  $eps = 1e-9;
  $P = $pointsUE;

  while($P > $eps){
    $activeIdx = [];
    foreach($ec as $i => $e){
      $cap = $maxNote - $e["note"];
      if($cap <= $eps) continue;
      if($lockStrict && $e["note_initial"] >= 10.0) continue;
      $activeIdx[] = $i;
    }
    if(count($activeIdx) === 0) break;

    $w = computeWeights($ec, $strategy, $maxNote);
    $W = 0.0;
    foreach($activeIdx as $i) $W += $w[$i];
    if($W <= $eps) break;

    $used = 0.0;
    foreach($activeIdx as $i){
      $coef = $ec[$i]["coef"];
      $capNote = $maxNote - $ec[$i]["note"];
      $capUE = $coef * $capNote;

      $allocUE = $P * ($w[$i] / $W);
      $giveUE  = min($allocUE, $capUE);

      $deltaNote = ($coef > 0) ? ($giveUE / $coef) : 0.0;
      $ec[$i]["note"] = min($maxNote, $ec[$i]["note"] + $deltaNote);

      $used += $giveUE;
    }

    if($used < $eps) break;
    $P -= $used;
  }

  return $P;
}

/**
 * Forcer EXACTEMENT la moyenne UE à 10.00 en ajustant un EC "tampon"
 * (notes flottantes acceptées) :
 *   - Calcule residualUE = S_target - S_current
 *   - Trouve un EC qui peut absorber residualUE (sans dépasser 20).
 *   - Si lockStrict empêche, on essaye d'abord EC non verrouillés,
 *     sinon on ignore lock (car option SOFT).
 *
 * Retour:
 *   - ["ok" => bool, "used_lock" => bool, "reason" => string]
 */
function forceExactTargetByResidual(&$ec, $targetAvg, $lockStrictPreferred, $maxNote=20.0){
  $eps = 1e-7;
  $sumCoef = sumCoef($ec);
  $S_target = $targetAvg * $sumCoef;
  $S = weightedSum($ec);
  $residualUE = $S_target - $S;

  // si déjà très proche
  if(abs($residualUE) <= $eps){
    $best = null;
    $bestCoef = -1;
    foreach($ec as $i => $e){
      $capUp = $maxNote - $e["note"];
      $capDown = $e["note"];
      $can = ($residualUE >= 0) ? ($capUp > $eps) : ($capDown > $eps);
      if(!$can) continue;
      if($e["coef"] > $bestCoef){
        $bestCoef = $e["coef"];
        $best = $i;
      }
    }
    if($best !== null){
      $deltaNote = $residualUE / $ec[$best]["coef"];
      $ec[$best]["note"] = clamp($ec[$best]["note"] + $deltaNote, 0.0, $maxNote);
    }
    return ["ok" => true, "used_lock" => $lockStrictPreferred, "reason" => "already_close"];
  }

  $tryPick = function($lockStrict) use (&$ec, $residualUE, $eps, $maxNote){
    $candidates = [];
    foreach($ec as $i => $e){
      if($lockStrict && $e["note_initial"] >= 10.0) continue;

      $coef = $e["coef"];
      if($coef <= 0) continue;

      $deltaNote = $residualUE / $coef;
      $newNote = $e["note"] + $deltaNote;
      if($newNote < -$eps || $newNote > $maxNote + $eps) continue;

      $score = abs($deltaNote) / max(1e-6, $coef);
      $candidates[] = ["i" => $i, "score" => $score];
    }
    if(empty($candidates)) return null;

    usort($candidates, function($a, $b){
      return ($a["score"] <=> $b["score"]);
    });
    return $candidates[0]["i"];
  };

  if($lockStrictPreferred){
    $idx = $tryPick(true);
    if($idx !== null){
      $deltaNote = $residualUE / $ec[$idx]["coef"];
      $ec[$idx]["note"] = clamp($ec[$idx]["note"] + $deltaNote, 0.0, $maxNote);
      return ["ok" => true, "used_lock" => true, "reason" => "fixed_with_lock"];
    }
  }

  $idx = $tryPick(false);
  if($idx !== null){
    $deltaNote = $residualUE / $ec[$idx]["coef"];
    $ec[$idx]["note"] = clamp($ec[$idx]["note"] + $deltaNote, 0.0, $maxNote);
    return ["ok" => true, "used_lock" => false, "reason" => "fixed_without_lock"];
  }

  return ["ok" => false, "used_lock" => false, "reason" => "impossible_due_to_cap20"];
}

// Arrondi d'affichage uniquement
function displayRound($x, $step){
  if($step <= 0.0) return $x;
  return round(round($x / $step) * $step, 2);
}

// -----------------------------
// Lecture formulaire
// -----------------------------
$isPost = ($_SERVER["REQUEST_METHOD"] === "POST");

$coef1 = floatval($_POST["coef1"] ?? 2);
$coef2 = floatval($_POST["coef2"] ?? 1);
$coef3 = floatval($_POST["coef3"] ?? 3);

$note1 = $_POST["note1"] ?? "";
$note2 = $_POST["note2"] ?? "";
$note3 = $_POST["note3"] ?? "";

$strategy = $_POST["strategy"] ?? "neutral";
$lockGE10 = (isset($_POST["lock_ge10"]) && $_POST["lock_ge10"] == "1");

$displayStep = floatval($_POST["rounding_step"] ?? 0.01);
$allowedDisplay = [0.01, 0.25, 0.5, 1.0];
if(!in_array($displayStep, $allowedDisplay, true)) $displayStep = 0.01;

$result = null;
$error = null;

if($isPost){
  if($note1 === "" || $note2 === "" || $note3 === ""){
    $error = "Veuillez saisir les 3 notes.";
  } else {
    $ec = [
      ["name"=>"EC1","coef"=>max(1, $coef1),"note"=>clamp(floatval($note1), 0, 20)],
      ["name"=>"EC2","coef"=>max(1, $coef2),"note"=>clamp(floatval($note2), 0, 20)],
      ["name"=>"EC3","coef"=>max(1, $coef3),"note"=>clamp(floatval($note3), 0, 20)],
    ];
    foreach($ec as &$e) $e["note_initial"] = $e["note"];
    unset($e);

    $avg0 = weightedAverage($ec);
    $sumC = sumCoef($ec);

    if($avg0 >= $targetAvg){
      $result = [
        "status" => "no_add",
        "avg_before" => $avg0,
        "points_missing" => 0.0,
        "avg_after" => $avg0,
        "ec_before" => $ec,
        "ec_after" => $ec,
        "info" => "Moyenne UE >= 10 : aucun point à ajouter."
      ];
    } else {
      // Règle de base : points manquants EXACTS
      $pointsMissing = ($targetAvg - $avg0) * $sumC;
      $pointsMissing = max(0.0, $pointsMissing);

      $before = $ec;

      // 1) Redistribution continue (d'abord avec lock si demandé)
      $rest = redistributeContinuous($ec, $pointsMissing, $strategy, $lockGE10, $maxNote);

      // 2) Forcer exactement 10.00 (lock est SOFT)
      $fix = forceExactTargetByResidual($ec, $targetAvg, $lockGE10, $maxNote);

      $avg1 = weightedAverage($ec);

      $result = [
        "status" => "add",
        "avg_before" => $avg0,
        "points_missing" => $pointsMissing,
        "avg_after" => $avg1,
        "ec_before" => $before,
        "ec_after" => $ec,
        "rest" => $rest,
        "fix" => $fix,
      ];

      if(!$fix["ok"]){
        $result["info"] = "Impossible d'atteindre 10.00 : plafonds à 20 bloquent (même en ignorant les options).";
      } else {
        if($fix["used_lock"]){
          $result["info"] = "10.00 atteint en respectant l'option 'ne pas toucher EC ≥ 10'.";
        } else {
          if($lockGE10){
            $result["info"] = "10.00 atteint, mais l'option 'ne pas toucher EC ≥ 10' a été ignorée car elle empêchait d'atteindre 10.00.";
          } else {
            $result["info"] = "10.00 atteint.";
          }
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Repêchage UE — Exact 10.00</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 24px; }
    .box { border: 1px solid #ddd; padding: 16px; border-radius: 10px; max-width: 900px; }
    .row { display: flex; gap: 12px; flex-wrap: wrap; }
    .field { display: flex; flex-direction: column; margin: 8px 0; min-width: 180px; }
    label { font-weight: 600; margin-bottom: 6px; }
    input, select { padding: 10px; border-radius: 8px; border: 1px solid #ccc; }
    button { padding: 12px 16px; border-radius: 10px; border: 0; cursor: pointer; background:#0d5F33; color:#fff; }
    .err { color: #b91c1c; font-weight: 600; }
    .warn { color: #b45309; font-weight: 600; }
    .ok { color: #0d5F33; font-weight: 700; }
    table { border-collapse: collapse; width: 100%; margin-top: 12px; }
    th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
    th { background: #f7f7f7; }
    .muted { color: #666; }
  </style>
</head>
<body>
<div class="box">
  <h2>Repêchage UE — Redistribution (objectif : 10.00 exact)</h2>
  <p class="muted">
    Règle de base : si moyenne UE &lt; 10, on calcule exactement <b>(10.00 - moyenne) × somme des coefficients</b>,
    puis on redistribue pour obtenir <b>10.00</b> (notes flottantes acceptées). Les options sont appliquées seulement
    si elles n’empêchent pas d’atteindre 10.00.
  </p>

  <?php if($error): ?>
    <p class="err"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <form method="POST">
    <h3>UE (3 EC)</h3>
    <div class="row">
      <div class="field">
        <label>EC1 - Coef</label>
        <select name="coef1">
          <?php foreach([1,2,3,4,5] as $c): ?>
            <option value="<?= $c ?>" <?= ($c == $coef1 ? "selected" : "") ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>EC1 - Note /20</label>
        <input type="number" step="0.01" min="0" max="20" name="note1" value="<?= htmlspecialchars($note1) ?>" />
      </div>

      <div class="field">
        <label>EC2 - Coef</label>
        <select name="coef2">
          <?php foreach([1,2,3,4,5] as $c): ?>
            <option value="<?= $c ?>" <?= ($c == $coef2 ? "selected" : "") ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>EC2 - Note /20</label>
        <input type="number" step="0.01" min="0" max="20" name="note2" value="<?= htmlspecialchars($note2) ?>" />
      </div>

      <div class="field">
        <label>EC3 - Coef</label>
        <select name="coef3">
          <?php foreach([1,2,3,4,5] as $c): ?>
            <option value="<?= $c ?>" <?= ($c == $coef3 ? "selected" : "") ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>EC3 - Note /20</label>
        <input type="number" step="0.01" min="0" max="20" name="note3" value="<?= htmlspecialchars($note3) ?>" />
      </div>
    </div>

    <hr>

    <div class="row">
      <div class="field" style="min-width:280px;">
        <label>Stratégie (option)</label>
        <select name="strategy">
          <option value="neutral" <?= ($strategy==="neutral"?"selected":"") ?>>Neutre (pondérée par coef)</option>
          <option value="favor_low" <?= ($strategy==="favor_low"?"selected":"") ?>>Avantager notes faibles</option>
          <option value="favor_high" <?= ($strategy==="favor_high"?"selected":"") ?>>Avantager notes fortes</option>
        </select>
      </div>

      <div class="field" style="min-width:260px;">
        <label>Affichage (arrondi visuel)</label>
        <select name="rounding_step">
          <option value="0.01" <?= ($displayStep==0.01?"selected":"") ?>>0.01 (pas fin)</option>
          <option value="0.25" <?= ($displayStep==0.25?"selected":"") ?>>0.25</option>
          <option value="0.5" <?= ($displayStep==0.5?"selected":"") ?>>0.5</option>
          <option value="1" <?= ($displayStep==1.0?"selected":"") ?>>1.0</option>
        </select>
      </div>

      <div class="field" style="min-width:300px;">
        <label>&nbsp;</label>
        <label style="font-weight:normal;">
          <input type="checkbox" name="lock_ge10" value="1" <?= ($lockGE10?"checked":"") ?> />
          (Option) Ne pas toucher les EC initialement ≥ 10
        </label>
      </div>
    </div>

    <button type="submit">Calculer</button>
  </form>

  <?php if($result): ?>
    <hr>
    <h3>Résultat</h3>

    <?php if($result["status"] === "no_add"): ?>
      <p class="ok">Moyenne UE avant = <b><?= round($result["avg_before"], 4) ?></b> → aucun point à ajouter.</p>
    <?php else: ?>
      <p>Moyenne UE avant = <b><?= round($result["avg_before"], 6) ?></b></p>
      <p>Points manquants (UE) = <b><?= round($result["points_missing"], 6) ?></b></p>

      <?php if(!empty($result["fix"]) && !$result["fix"]["ok"]): ?>
        <p class="warn"><?= htmlspecialchars($result["info"]) ?></p>
      <?php else: ?>
        <p class="ok"><?= htmlspecialchars($result["info"]) ?></p>
      <?php endif; ?>

      <p>Moyenne UE après = <b><?= round($result["avg_after"], 6) ?></b></p>
    <?php endif; ?>

    <table>
      <thead>
        <tr>
          <th>EC</th>
          <th>Coef</th>
          <th>Note avant</th>
          <th>Note après (interne)</th>
          <th>Note après (affichage)</th>
        </tr>
      </thead>
      <tbody>
        <?php for($i=0; $i<3; $i++): ?>
          <tr>
            <td><?= htmlspecialchars($result["ec_after"][$i]["name"]) ?></td>
            <td><?= htmlspecialchars($result["ec_after"][$i]["coef"]) ?></td>
            <td><?= round($result["ec_before"][$i]["note"], 6) ?></td>
            <td><b><?= round($result["ec_after"][$i]["note"], 6) ?></b></td>
            <td><b><?= number_format(displayRound($result["ec_after"][$i]["note"], $displayStep), 2) ?></b></td>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>

    <p class="muted" style="margin-top:10px;">
      NB : l’arrondi est uniquement visuel. Les notes internes restent flottantes pour garantir 10.00.
    </p>
  <?php endif; ?>
</div>
</body>
</html>
