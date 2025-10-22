<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$user = Auth::getCurrentUser();

$survey_id = $_POST['survey_id'] ?? '';
$survey = db()->fetchOne("SELECT * FROM surveys WHERE id = ?", [$survey_id]);
if (!$survey || !in_array($survey['status'], ['approved','published'])) { echo json_encode(['success'=>false,'message'=>'Survey not open']); exit; }

try {
    db()->beginTransaction();
    $response_id = generateUUID();
    db()->execute("INSERT INTO survey_responses (id, survey_id, user_id, status, submitted_at) VALUES (?,?,?,?,NOW())",
        [$response_id, $survey_id, $user['id'], 'submitted']
    );
    $questions = db()->fetchAll("SELECT id, question_type FROM survey_questions WHERE survey_id = ?", [$survey_id]);
    $qidToType = [];
    foreach ($questions as $q) { $qidToType[$q['id']] = $q['question_type']; }
    foreach ($_POST as $key=>$val) {
        if (strpos($key, 'q_') === 0) {
            $qid = substr($key, 2);
            if (!isset($qidToType[$qid])) continue;
            $type = $qidToType[$qid];
            if ($type === 'multiple_choice') {
                $values = is_array($val) ? $val : [$val];
                foreach ($values as $v) {
                    db()->execute("INSERT INTO survey_answers (id, response_id, question_id, answer_text) VALUES (?,?,?,?)",
                        [generateUUID(), $response_id, $qid, (string)$v]
                    );
                }
            } elseif ($type === 'rating') {
                db()->execute("INSERT INTO survey_answers (id, response_id, question_id, answer_value) VALUES (?,?,?,?)",
                    [generateUUID(), $response_id, $qid, floatval($val)]
                );
            } else {
                db()->execute("INSERT INTO survey_answers (id, response_id, question_id, answer_text) VALUES (?,?,?,?)",
                    [generateUUID(), $response_id, $qid, is_array($val)? json_encode($val) : (string)$val]
                );
            }
        }
    }
    db()->commit();
    if (!empty($_POST['redirect'])) {
        header('Location: ../surveys.php?submitted=1');
        exit;
    }
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    db()->rollback();
    error_log('survey submit failed: '.$e->getMessage());
    if (!empty($_POST['redirect'])) {
        header('Location: ../surveys.php?error=1');
        exit;
    }
    echo json_encode(['success'=>false,'message'=>'Submit failed']);
}
?>


