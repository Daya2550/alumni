<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$id = $_GET['id'] ?? '';
$survey = db()->fetchOne("SELECT * FROM surveys WHERE id = ?", [$id]);
if (!$survey || !in_array($survey['status'], ['approved','published'])) {
    header('HTTP/1.1 404 Not Found'); echo 'Survey not available'; exit;
}
$questions = db()->fetchAll("SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY sort_order ASC", [$id]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($survey['title']); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="container py-4">
        <div class="mb-3">
            <h1 class="mb-1"><?php echo htmlspecialchars($survey['title']); ?></h1>
            <?php if (!empty($survey['description'])): ?><p class="text-muted"><?php echo nl2br(htmlspecialchars($survey['description'])); ?></p><?php endif; ?>
        </div>

        <form id="takeForm" method="POST" action="api/survey_submit.php">
            <input type="hidden" name="survey_id" value="<?php echo htmlspecialchars($survey['id']); ?>">
            <input type="hidden" name="redirect" value="1">
            <?php foreach ($questions as $q): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <strong class="d-block mb-2"><?php echo htmlspecialchars($q['question_text']); ?><?php if ($q['is_required']): ?><span class="text-danger">*</span><?php endif; ?></strong>
                        <?php if ($q['question_type'] === 'text'): ?>
                            <textarea class="form-control" name="q_<?php echo $q['id']; ?>" rows="3" <?php echo $q['is_required']?'required':''; ?>></textarea>
                        <?php elseif ($q['question_type'] === 'rating'): ?>
                            <select class="form-select" name="q_<?php echo $q['id']; ?>" <?php echo $q['is_required']?'required':''; ?>>
                                <?php for($i=1;$i<=5;$i++): ?><option value="<?php echo $i; ?>"><?php echo $i; ?></option><?php endfor; ?>
                            </select>
                        <?php else: $opts = json_decode($q['options_json'] ?: '[]', true) ?: []; ?>
                            <?php if ($q['question_type'] === 'single_choice'): ?>
                                <?php foreach ($opts as $i=>$opt): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q_<?php echo $q['id']; ?>" value="<?php echo htmlspecialchars($opt); ?>" id="q_<?php echo $q['id'].'_'.$i; ?>" <?php echo $q['is_required']?'required':''; ?>>
                                        <label class="form-check-label" for="q_<?php echo $q['id'].'_'.$i; ?>"><?php echo htmlspecialchars($opt); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($opts as $i=>$opt): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="q_<?php echo $q['id']; ?>[]" value="<?php echo htmlspecialchars($opt); ?>" id="q_<?php echo $q['id'].'_'.$i; ?>">
                                        <label class="form-check-label" for="q_<?php echo $q['id'].'_'.$i; ?>"><?php echo htmlspecialchars($opt); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="text-end">
                <button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane"></i> Submit</button>
            </div>
        </form>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


