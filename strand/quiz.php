<?php
session_start();
require_once '../includes/db.php';

// --- SECURITY CHECK 1: Ensure user is a logged-in student ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$assessment_id = $_GET['id'] ?? 0;
$student_id = $_SESSION['user_id'];

if (!$assessment_id) {
    header("Location: ../student/student.php?error=notfound");
    exit;
}

// --- FETCH ASSESSMENT AND CHECK AVAILABILITY ---
$sql = "
    SELECT a.id, a.title, a.duration_minutes, a.max_attempts, a.is_open,
           (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.assessment_id = a.id AND qa.student_id = ?) as attempts_taken
    FROM assessments a
    WHERE a.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $student_id, $assessment_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();
$stmt->close();

// --- SECURITY CHECK 2: Ensure the quiz is open and student has attempts left ---
$attempts_left = $assessment['max_attempts'] - $assessment['attempts_taken'];
if (!$assessment || !$assessment['is_open'] || $attempts_left <= 0) {
    header("Location: take_assessment.php?id=$assessment_id&error=unavailable");
    exit;
}

// --- CREATE THE ATTEMPT RECORD ---
$start_time = date("Y-m-d H:i:s");
$status = 'in_progress';
$attempt_stmt = $conn->prepare("INSERT INTO quiz_attempts (assessment_id, student_id, started_at, status) VALUES (?, ?, ?, ?)");
$attempt_stmt->bind_param("iiss", $assessment_id, $student_id, $start_time, $status);
$attempt_stmt->execute();
$quiz_attempt_id = $attempt_stmt->insert_id;
$attempt_stmt->close();

// --- FETCH QUESTIONS and OPTIONS, RANDOMIZED ---
$questions = [];
$q_sql = "
    SELECT id, question_text, question_type 
    FROM question_bank 
    WHERE id IN (SELECT question_id FROM assessment_questions WHERE assessment_id = ?)
    ORDER BY RAND()
";
$q_stmt = $conn->prepare($q_sql);
$q_stmt->bind_param("i", $assessment_id);
$q_stmt->execute();
$questions_result = $q_stmt->get_result();

while ($question = $questions_result->fetch_assoc()) {
    if ($question['question_type'] === 'multiple_choice' || $question['question_type'] === 'true_false') {

        // THE FIX IS HERE: The table name is now correct.
        $opt_sql = "SELECT id, option_text FROM question_options WHERE question_id = ? ORDER BY RAND()";

        $opt_stmt = $conn->prepare($opt_sql);
        $opt_stmt->bind_param("i", $question['id']);
        $opt_stmt->execute();
        $question['options'] = $opt_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $opt_stmt->close();
    }
    $questions[] = $question;
}
$q_stmt->close();

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><?= htmlspecialchars($assessment['title']) ?></h4>
            <div id="timer" class="fs-4 fw-bold">
                <i class="bi bi-stopwatch"></i>
                <span id="time"><?= $assessment['duration_minutes'] ?>:00</span>
            </div>
        </div>
        <div class="card-body p-4">
            <form id="quizForm" action="submit_quiz.php" method="POST">
                <input type="hidden" name="assessment_id" value="<?= $assessment_id ?>">
                <input type="hidden" name="quiz_attempt_id" value="<?= $quiz_attempt_id ?>">

                <?php foreach ($questions as $index => $q): ?>
                    <div class="mb-4 question-block">
                        <p class="fw-bold">Question <?= $index + 1 ?>:</p>
                        <div class="ps-2 question-text"><?= $q['question_text'] ?></div>

                        <div class="ms-3 mt-3">
                            <?php if ($q['question_type'] === 'multiple_choice' || $q['question_type'] === 'true_false'): ?>
                                <?php foreach ($q['options'] as $opt): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $opt['id'] ?>" id="opt_<?= $opt['id'] ?>">
                                        <label class="form-check-label" for="opt_<?= $opt['id'] ?>"><?= htmlspecialchars($opt['option_text']) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            <?php elseif (in_array($q['question_type'], ['identification', 'short_answer'])): ?>
                                <input type="text" class="form-control" name="answers[<?= $q['id'] ?>]" placeholder="Your answer here...">
                            <?php elseif ($q['question_type'] === 'essay'): ?>
                                <textarea class="form-control" name="answers[<?= $q['id'] ?>]" rows="4" placeholder="Your answer here..."></textarea>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($index < count($questions) - 1): ?>
                        <hr><?php endif; ?>
                <?php endforeach; ?>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-success btn-lg">Submit Quiz</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const timeDisplay = document.getElementById('time');
        const quizForm = document.getElementById('quizForm');

        let durationInMinutes = <?= $assessment['duration_minutes'] ?>;
        let totalSeconds = durationInMinutes * 60;

        const timer = setInterval(function() {
            if (totalSeconds <= 0) {
                clearInterval(timer);
                alert("Time's up! Your quiz will be submitted automatically.");
                quizForm.submit();
                return;
            }

            totalSeconds--;
            let minutes = Math.floor(totalSeconds / 60);
            let seconds = totalSeconds % 60;

            seconds = seconds < 10 ? '0' + seconds : seconds;
            minutes = minutes < 10 ? '0' + minutes : minutes;

            timeDisplay.textContent = `${minutes}:${seconds}`;
        }, 1000);
    });
</script>

<?php
require_once '../includes/footer.php';
$conn->close();
?>