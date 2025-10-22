<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAnyRole(['admin','staff']);

$user = Auth::getCurrentUser();
$id = $_GET['id'] ?? '';
$is_edit = !empty($id);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $visibility = sanitizeInput($_POST['visibility'] ?? 'public');
    $status = sanitizeInput($_POST['status'] ?? 'draft');
    $requires_approval = isset($_POST['requires_approval']) ? 1 : 0;
    $questions_json = $_POST['questions_json'] ?? '[]';

    if ($title === '') {
        $error = 'Title is required';
    } else {
        try {
            if ($is_edit) {
                db()->beginTransaction();
                db()->execute("UPDATE surveys SET title=?, description=?, visibility=?, status=?, requires_approval=?, updated_at=NOW() WHERE id=?", [$title,$description,$visibility,$status,$requires_approval,$id]);
                $questions = json_decode($questions_json, true);
                if (is_array($questions)) {
                    // Replace questions only if client sent valid questions JSON
                    db()->execute("DELETE FROM survey_questions WHERE survey_id = ?", [$id]);
                    $sort = 0;
                    foreach ($questions as $q) {
                        $qid = generateUUID();
                        db()->execute("INSERT INTO survey_questions (id, survey_id, question_text, question_type, options_json, is_required, sort_order) VALUES (?,?,?,?,?,?,?)",
                            [$qid, $id, $q['text'] ?? '', $q['type'] ?? 'single_choice', json_encode($q['options'] ?? []), !empty($q['required'])?1:0, $sort++]
                        );
                    }
                }
                db()->commit();
                // Optional: notify owner when status changes to published
                $success = 'Survey updated';
            } else {
                $newId = generateUUID();
                db()->beginTransaction();
                db()->execute("INSERT INTO surveys (id, title, description, visibility, status, requires_approval, owner_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())",
                    [$newId, $title, $description, $visibility, $status, $requires_approval, $user['id']]
                );
                $questions = json_decode($questions_json, true);
                if (is_array($questions)) {
                    $sort = 0;
                    foreach ($questions as $q) {
                        $qid = generateUUID();
                        db()->execute("INSERT INTO survey_questions (id, survey_id, question_text, question_type, options_json, is_required, sort_order) VALUES (?,?,?,?,?,?,?)",
                            [$qid, $newId, $q['text'] ?? '', $q['type'] ?? 'single_choice', json_encode($q['options'] ?? []), !empty($q['required'])?1:0, $sort++]
                        );
                    }
                }
                db()->commit();
                // Create notifications for all users about new survey
                require_once __DIR__ . '/../../includes/notifications.php';
                Notifications::notifyAllActiveUsersExcept($user['id'], 'New Survey', $title, 'info', 'survey', $newId);
                header('Location: surveys.php?msg=created');
                exit;
            }
        } catch (Exception $e) {
            db()->rollback();
            $error = 'Failed to save survey';
        }
    }
}

$survey = null;
$questions = [];
if ($is_edit) {
    $survey = db()->fetchOne("SELECT * FROM surveys WHERE id = ?", [$id]);
    $questions = db()->fetchAll("SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY sort_order ASC", [$id]);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit? 'Edit' : 'Create'; ?> Survey</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1><i class="fas fa-poll"></i> <?php echo $is_edit? 'Edit' : 'Create'; ?> Survey</h1>
            <a href="surveys.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <form method="POST" id="surveyForm">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($survey['title'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($survey['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Visibility</label>
                            <select class="form-select" name="visibility">
                                <?php foreach (['public','batch','private'] as $v): ?>
                                <option value="<?php echo $v; ?>" <?php echo (($survey['visibility'] ?? 'public')===$v?'selected':''); ?>><?php echo ucfirst($v); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <?php foreach (['draft','pending','approved','published','closed'] as $st): ?>
                                <option value="<?php echo $st; ?>" <?php echo (($survey['status'] ?? 'draft')===$st?'selected':''); ?>><?php echo ucfirst($st); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3 d-flex align-items-center">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="requires_approval" id="requires_approval" <?php echo !empty($survey['requires_approval'])?'checked':''; ?>>
                                <label class="form-check-label" for="requires_approval">Requires Approval (for student submissions)</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Questions</strong>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addQuestion()"><i class="fas fa-plus"></i> Add Question</button>
                </div>
                <div class="card-body" id="questionsContainer"></div>
                <input type="hidden" name="questions_json" id="questions_json">
            </div>

            <div class="text-end">
                <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>

    <script>
        const existing = <?php echo json_encode(array_map(function($q){return [
            'text'=>$q['question_text'], 'type'=>$q['question_type'], 'options'=>json_decode($q['options_json'] ?: '[]', true), 'required'=>!!$q['is_required']
        ];}, $questions)); ?>;
        const container = document.getElementById('questionsContainer');

        function escapeHtml(s){ return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
        function updateHidden(){ document.getElementById('questions_json').value = JSON.stringify(window.questions||[]); }

        function render() {
            container.innerHTML = '';
            (window.questions||[]).forEach(function(q, idx){
                const el = document.createElement('div');
                el.className = 'border p-3 mb-2 rounded';
                el.dataset.qi = String(idx);
                const isChoice = (q.type==='single_choice' || q.type==='multiple_choice');
                el.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Question ${idx+1}</strong>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-danger q-remove" data-qi="${idx}"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="mb-2">
                        <input type="text" class="form-control q-text" data-qi="${idx}" placeholder="Question text" value="${escapeHtml(q.text||'')}">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <select class="form-select q-type" data-qi="${idx}">
                                ${['single_choice','multiple_choice','text','rating'].map(t=>`<option value="${t}" ${q.type===t?'selected':''}>${t.replace('_',' ')}</option>`).join('')}
                            </select>
                        </div>
                        <div class="col-md-4 form-check d-flex align-items-center">
                            <input class="form-check-input me-2 q-req" data-qi="${idx}" type="checkbox" ${q.required?'checked':''}> Required
                        </div>
                    </div>
                    ${isChoice ? `
                    <div class="mb-2">
                        <label class="form-label">Options</label>
                        <div class="q-options" data-qi="${idx}">
                            ${(q.options||[]).map((opt,i)=>`
                                <div class=\"input-group mb-1\" data-oi=\"${i}\">
                                    <input type=\"text\" class=\"form-control q-opt\" data-qi=\"${idx}\" data-oi=\"${i}\" value=\"${escapeHtml(opt)}\">
                                    <button class=\"btn btn-outline-danger q-opt-remove\" type=\"button\" data-qi=\"${idx}\" data-oi=\"${i}\"><i class=\"fas fa-times\"></i></button>
                                </div>
                            `).join('')}
                            <button type="button" class="btn btn-sm btn-outline-primary q-opt-add" data-qi="${idx}"><i class="fas fa-plus"></i> Add Option</button>
                        </div>
                    </div>`: ''}
                `;
                container.appendChild(el);
            });
            updateHidden();
        }
        function addQuestion(){ (window.questions||(window.questions=[])).push({text:'',type:'single_choice',options:[''],required:false}); updateHidden(); render(); }

        container.addEventListener('input', function(e){
            const t = e.target;
            const qi = t.getAttribute('data-qi');
            if (qi === null) return;
            if (t.classList.contains('q-text')) { window.questions[qi].text = t.value; updateHidden(); }
            if (t.classList.contains('q-opt')) {
                const oi = t.getAttribute('data-oi');
                if (oi !== null) { window.questions[qi].options[oi] = t.value; updateHidden(); }
            }
        });
        container.addEventListener('change', function(e){
            const t = e.target; const qi = t.getAttribute('data-qi'); if (qi === null) return;
            if (t.classList.contains('q-type')) { window.questions[qi].type = t.value; if (t.value==='text' || t.value==='rating') { window.questions[qi].options = []; } render(); }
            if (t.classList.contains('q-req')) { window.questions[qi].required = t.checked; updateHidden(); }
        });
        container.addEventListener('click', function(e){
            const t = e.target.closest('button'); if (!t) return;
            if (t.classList.contains('q-remove')) { const qi = +t.getAttribute('data-qi'); window.questions.splice(qi,1); render(); }
            if (t.classList.contains('q-opt-add')) { const qi = +t.getAttribute('data-qi'); (window.questions[qi].options||(window.questions[qi].options=[])).push(''); render(); }
            if (t.classList.contains('q-opt-remove')) { const qi = +t.getAttribute('data-qi'); const oi = +t.getAttribute('data-oi'); window.questions[qi].options.splice(oi,1); render(); }
        });

        window.questions = existing && existing.length ? existing : [];
        render();
        document.getElementById('surveyForm').addEventListener('submit', function(){ updateHidden(); });
    </script>
</body>
</html>


