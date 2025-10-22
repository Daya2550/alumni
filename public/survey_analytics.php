<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();

$survey_id = $_GET['id'] ?? '';
$survey = db()->fetchOne("SELECT * FROM surveys WHERE id = ?", [$survey_id]);
if (!$survey) { header('HTTP/1.1 404 Not Found'); echo 'Survey not found'; exit; }

$is_admin_or_staff = Auth::hasAnyRole(['admin','staff']);
$is_owner = $survey['owner_id'] === $user['id'];
if (!$is_admin_or_staff && !$is_owner) { header('HTTP/1.1 403 Forbidden'); exit('Access denied'); }

$questions = db()->fetchAll("SELECT id, question_text, question_type, options_json, is_required FROM survey_questions WHERE survey_id = ? ORDER BY sort_order ASC", [$survey_id]);

// Filters
$view = $_GET['view'] ?? 'combined'; // combined | individual
$filter_role = $_GET['role'] ?? '';
$filter_batch = $_GET['batch'] ?? '';
$respondent_id = $_GET['respondent'] ?? '';

// Respondents list (for filters and individual view)
$respondents = db()->fetchAll(
    "SELECT DISTINCT sr.user_id, u.name, u.role, u.batch
     FROM survey_responses sr
     LEFT JOIN users u ON u.id = sr.user_id
     WHERE sr.survey_id = ?
     ORDER BY u.name",
    [$survey_id]
);

// Build response filter where clause
$whereResp = [ 'survey_id = ?' ];
$respParams = [ $survey_id ];
if ($filter_role && in_array($filter_role, ['student','alumni','staff','admin'])) {
    $whereResp[] = 'user_id IN (SELECT id FROM users WHERE role = ?)';
    $respParams[] = $filter_role;
}
if ($filter_batch !== '') {
    $whereResp[] = 'user_id IN (SELECT id FROM users WHERE batch = ?)';
    $respParams[] = $filter_batch;
}
if ($view === 'individual' && $respondent_id !== '') {
    $whereResp[] = 'user_id = ?';
    $respParams[] = $respondent_id;
}
$whereRespSql = implode(' AND ', $whereResp);

$total_responses = db()->fetchOne("SELECT COUNT(*) AS c FROM survey_responses WHERE $whereRespSql", $respParams)['c'] ?? 0;

// Load answers (respecting filters)
$answers = db()->fetchAll(
    "SELECT sa.question_id, sa.answer_text, sa.answer_value, sr.user_id
     FROM survey_answers sa
     JOIN survey_responses sr ON sr.id = sa.response_id
     WHERE sr.$whereRespSql",
    $respParams
);

// Group answers by question
$byQ = [];
foreach ($answers as $a) {
    $qid = $a['question_id'];
    if (!isset($byQ[$qid])) $byQ[$qid] = [];
    $byQ[$qid][] = $a;
}

function calcStats($question, $answers) {
    $type = $question['question_type'];
    $stats = [ 'type' => $type, 'count' => count($answers) ];
    if ($type === 'rating') {
        $sum = 0; $n = 0;
        foreach ($answers as $a) { if ($a['answer_value'] !== null) { $sum += floatval($a['answer_value']); $n++; } }
        $stats['avg'] = $n ? round($sum / $n, 2) : 0;
    } elseif ($type === 'single_choice' || $type === 'multiple_choice') {
        $options = json_decode($question['options_json'] ?: '[]', true) ?: [];
        $tally = array_fill_keys($options, 0);
        foreach ($answers as $a) {
            if ($type === 'multiple_choice') {
                $vals = [$a['answer_text']];
                // multiple stored as separate rows per option in our submit; handle strings
                foreach ($vals as $v) { if ($v !== null && $v !== '') { if (!isset($tally[$v])) $tally[$v] = 0; $tally[$v]++; } }
            } else {
                $v = $a['answer_text']; if ($v !== null && $v !== '') { if (!isset($tally[$v])) $tally[$v] = 0; $tally[$v]++; }
            }
        }
        $stats['tally'] = $tally;
    } else { // text
        $stats['samples'] = array_slice(array_map(function($a){ return $a['answer_text']; }, $answers), 0, 5);
    }
    return $stats;
}

$analytics = [];
foreach ($questions as $q) {
    $analytics[$q['id']] = calcStats($q, $byQ[$q['id']] ?? []);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Survey Analytics - <?php echo htmlspecialchars($survey['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1><i class="fas fa-chart-pie"></i> Analytics: <?php echo htmlspecialchars($survey['title']); ?></h1>
            <a href="<?php echo $is_admin_or_staff ? 'admin/surveys.php' : 'my_surveys.php'; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2" method="GET" id="filtersForm">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($survey_id); ?>">
                    <div class="col-md-3">
                        <label class="form-label">View</label>
                        <select class="form-select" name="view">
                            <option value="combined" <?php echo $view==='combined'?'selected':''; ?>>Combined</option>
                            <option value="individual" <?php echo $view==='individual'?'selected':''; ?>>Individual</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Respondent</label>
                        <select class="form-select" name="respondent" <?php echo $view==='individual'?'':'disabled'; ?>>
                            <option value="">All</option>
                            <?php foreach ($respondents as $r): ?>
                                <option value="<?php echo htmlspecialchars($r['user_id']); ?>" <?php echo ($respondent_id===$r['user_id'])?'selected':''; ?>>
                                    <?php echo htmlspecialchars(($r['name'] ?: $r['user_id']).' • '.($r['role']?:'').($r['batch']?(' • '.$r['batch']):'')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role">
                            <option value="">All</option>
                            <?php foreach (['student','alumni','staff','admin'] as $rr): ?>
                                <option value="<?php echo $rr; ?>" <?php echo $filter_role===$rr?'selected':''; ?>><?php echo ucfirst($rr); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Batch</label>
                        <input class="form-control" name="batch" value="<?php echo htmlspecialchars($filter_batch); ?>" placeholder="e.g., 2020-2024">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter"></i> Apply</button>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search Questions</label>
                        <input type="text" class="form-control" id="searchBox" placeholder="Type to filter by question text">
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <a id="downloadCsv" class="btn btn-outline-success" href="#"><i class="fas fa-file-download"></i> Download CSV</a>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card h-100"><div class="card-body d-flex align-items-center"><i class="fas fa-users fa-2x text-primary me-3"></i><div><div class="text-muted">Total Responses</div><div class="h4 mb-0"><?php echo $total_responses; ?></div></div></div></div>
            </div>
            <div class="col-md-4">
                <div class="card h-100"><div class="card-body d-flex align-items-center"><i class="fas fa-clipboard-list fa-2x text-success me-3"></i><div><div class="text-muted">Questions</div><div class="h4 mb-0"><?php echo count($questions); ?></div></div></div></div>
            </div>
            <div class="col-md-4">
                <div class="card h-100"><div class="card-body d-flex align-items-center"><i class="fas fa-eye fa-2x text-info me-3"></i><div><div class="text-muted">Status</div><div class="h5 mb-0"><?php echo htmlspecialchars($survey['status']); ?></div></div></div></div>
            </div>
        </div>

        <?php foreach ($questions as $q): $stat = $analytics[$q['id']]; ?>
            <div class="card mb-3 qa-card" data-qtxt="<?php echo htmlspecialchars(strtolower($q['question_text'])); ?>">
                <div class="card-header"><strong class="qa-text"><?php echo htmlspecialchars($q['question_text']); ?></strong> <span class="badge bg-light text-dark ms-2"><?php echo htmlspecialchars($q['question_type']); ?></span></div>
                <div class="card-body">
                    <?php if ($stat['type'] === 'rating'): ?>
                        <div>Average rating: <strong><?php echo number_format($stat['avg'] ?? 0, 2); ?></strong> (<?php echo (int)($stat['count'] ?? 0); ?> responses)</div>
                    <?php elseif ($stat['type'] === 'single_choice' || $stat['type'] === 'multiple_choice'): ?>
                        <?php $tally = $stat['tally'] ?? []; $sum = array_sum($tally); if ($sum <= 0) $sum = 1; ?>
                        <div class="table-responsive"><table class="table table-sm">
                            <thead><tr><th>Option</th><th class="text-end">Count</th><th class="text-end">%</th></tr></thead>
                            <tbody>
                                <?php foreach ($tally as $opt=>$cnt): $pct = round(($cnt*100)/$sum); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($opt); ?></td>
                                    <td class="text-end"><?php echo (int)$cnt; ?></td>
                                    <td class="text-end"><?php echo $pct; ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table></div>
                    <?php else: ?>
                        <div class="text-muted">Text responses: <?php echo (int)($stat['count'] ?? 0); ?></div>
                        <?php if (!empty($stat['samples'])): ?>
                            <div class="mt-2">
                                <strong>Sample responses</strong>
                                <ul>
                                    <?php foreach ($stat['samples'] as $s): ?>
                                        <li><?php echo htmlspecialchars(mb_strimwidth((string)$s, 0, 140, '...')); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
<script>
    (function(){
        var s = document.getElementById('searchBox');
        if (s) {
            s.addEventListener('input', function(){
                var q = (s.value||'').trim().toLowerCase();
                document.querySelectorAll('.qa-card').forEach(function(card){
                    var t = card.getAttribute('data-qtxt') || '';
                    card.style.display = (!q || t.indexOf(q) !== -1) ? '' : 'none';
                });
            });
        }

        function toCsvRow(arr){ return arr.map(function(v){
            var s = (v==null? '': String(v));
            if (s.search(/[",\n]/) >= 0) s = '"' + s.replace(/"/g,'""') + '"';
            return s;
        }).join(','); }

        var dl = document.getElementById('downloadCsv');
        if (dl) {
            dl.addEventListener('click', function(ev){
                ev.preventDefault();
                var rows = [];
                rows.push(['Question','Type','Metric','Value']);
                document.querySelectorAll('.qa-card').forEach(function(card){
                    if (card.style.display==='none') return;
                    var qText = card.querySelector('.qa-text').textContent.trim();
                    var type = card.querySelector('.badge').textContent.trim();
                    if (type === 'rating') {
                        var avg = card.querySelector('.card-body').textContent.match(/Average rating:\s*(\d+(?:\.\d+)?)/);
                        var cnt = card.querySelector('.card-body').textContent.match(/\((\d+) responses\)/);
                        rows.push([qText,type,'Average', avg?avg[1]:'0']);
                        rows.push([qText,type,'Responses', cnt?cnt[1]:'0']);
                    } else if (type === 'single_choice' || type === 'multiple_choice') {
                        card.querySelectorAll('tbody tr').forEach(function(tr){
                            var opt = tr.children[0].textContent.trim();
                            var c = tr.children[1].textContent.trim();
                            var p = tr.children[2].textContent.trim();
                            rows.push([qText,type,opt, c + ' (' + p + ')']);
                        });
                    } else {
                        var cntText = card.querySelector('.text-muted').textContent.trim();
                        var m = cntText.match(/Text responses:\s*(\d+)/);
                        rows.push([qText,type,'Responses', m?m[1]:'0']);
                    }
                });
                var csv = rows.map(toCsvRow).join('\n');
                var blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'survey_<?php echo preg_replace('/[^a-z0-9_\-]+/i','_', $survey_id); ?>_analytics.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });
        }
    })();
</script>


