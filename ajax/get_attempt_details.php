<?php
session_start();
require_once '../includes/db.php';

// Security: Only teachers can access this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo "Unauthorized.";
    exit;
}

$attempt_id = $_GET['attempt_id'] ?? null;
if (!$attempt_id) {
    http_response_code(400);
    echo "Attempt ID not provided.";
    exit;
}

// Fetch the attempt details along with student and assessment info
$stmt = $conn->prepare("
    SELECT qa.score, qa.total_items, qa.submitted_at, qa.assessment_id, u.fname, u.lname, a.title 
    FROM quiz_attempts qa
    JOIN users u ON qa.student_id = u.id
    JOIN assessments a ON qa.assessment_id = a.id
    WHERE qa.id = ?
");
$stmt->bind_param("i", $attempt_id);
$stmt->execute();
$attempt_details = $stmt->get_result()->fetch_assoc();

if (!$attempt_details) {
    http_response_code(404);
    echo "Attempt not found.";
    exit;
}

// Fetch the student's saved answers for this attempt
$stmt = $conn->prepare("SELECT question_id, student_answer FROM student_answers WHERE attempt_id = ?");
$stmt->bind_param("i", $attempt_id);
$stmt->execute();
$result = $stmt->get_result();
$student_answers = [];
while ($row = $result->fetch_assoc()) {
    $student_answers[$row['question_id']] = $row['student_answer'];
}

// Fetch all the original questions and their correct answers from the assessment
$stmt = $conn->prepare("SELECT id, question_text, correct_answer FROM questions WHERE assessment_id = ? ORDER BY id");
$stmt->bind_param("i", $attempt_details['assessment_id']);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Fix the "1970" date issue for incomplete attempts
$submitted_date = $attempt_details['submitted_at']
    ? date("F j, Y, g:i a", strtotime($attempt_details['submitted_at']))
    : '<i>Attempt in progress or not submitted</i>';
?>

<div class="mb-4">
    <h4><?= htmlspecialchars($attempt_details['title']) ?></h4>
    <p class="lead">
        Student: <strong><?= htmlspecialchars($attempt_details['fname'] . ' ' . $attempt_details['lname']) ?></strong>
    </p>
    <p>
        Final Score: <span class="badge bg-primary fs-6"><?= $attempt_details['score'] ?> / <?= $attempt_details['total_items'] ?></span>
        <br>
        <small class="text-muted">Submitted on: <?= $submitted_date ?></small>
    </p>
</div>
<hr>
<h5>Question Breakdown:</h5>

<?php if (empty($questions)): ?>
    <div class="alert alert-warning">No questions were found for this assessment.</div>
<?php else: ?>
    <?php foreach ($questions as $index => $question): ?>
        <?php
        $q_id = $question['id'];
        $student_answer = $student_answers[$q_id] ?? '<i>No answer provided</i>';
        $correct_answer = $question['correct_answer'];
        $is_correct = (isset($student_answers[$q_id]) && strtolower(trim($student_answer)) === strtolower(trim($correct_answer)));
        ?>
        <div class="mb-3 p-3 rounded <?= $is_correct ? 'border border-success' : 'border border-danger' ?>" style="background-color: #f8f9fa;">
            <p class="mb-2"><strong>Question <?= $index + 1 ?>:</strong> <?= htmlspecialchars($question['question_text']) ?></p>
            <p class="mb-1">
                Student's Answer:
                <span class="fw-bold <?= $is_correct ? 'text-success' : 'text-danger' ?>">
                    <?= htmlspecialchars($student_answer) ?>
                    <?php if (isset($student_answers[$q_id])): ?>
                        <?= $is_correct ? '<i class="bi bi-check-circle-fill ms-1"></i>' : '<i class="bi bi-x-circle-fill ms-1"></i>' ?>
                    <?php endif; ?>
                </span>
            </p>
            <?php if (!$is_correct && isset($student_answers[$q_id])): ?>
                <p class="mb-1" style="font-size: 0.9em;">
                    Correct Answer:
                    <span class="fw-bold text-success"><?= htmlspecialchars($correct_answer) ?></span>
                </p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>