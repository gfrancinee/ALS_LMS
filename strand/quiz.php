<?php
session_start();
require_once '../includes/db.php';

// --- PHP LOGIC (No changes needed here) ---
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
$sql = "
    SELECT a.id, a.title, a.duration_minutes, a.max_attempts, a.is_open,
           (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.assessment_id = a.id AND qa.student_id = ?) as attempts_taken
    FROM assessments a WHERE a.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $student_id, $assessment_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();
$stmt->close();
$attempts_left = $assessment['max_attempts'] - $assessment['attempts_taken'];
if (!$assessment || !$assessment['is_open'] || $attempts_left <= 0) {
    header("Location: take_assessment.php?id=$assessment_id&error=unavailable");
    exit;
}
$start_time = date("Y-m-d H:i:s");
$status = 'in_progress';
$attempt_stmt = $conn->prepare("INSERT INTO quiz_attempts (assessment_id, student_id, started_at, status) VALUES (?, ?, ?, ?)");
$attempt_stmt->bind_param("iiss", $assessment_id, $student_id, $start_time, $status);
$attempt_stmt->execute();
$quiz_attempt_id = $attempt_stmt->insert_id;
$attempt_stmt->close();
$questions = [];
$q_sql = "SELECT id, question_text, question_type FROM question_bank WHERE id IN (SELECT question_id FROM assessment_questions WHERE assessment_id = ?) ORDER BY RAND()";
$q_stmt = $conn->prepare($q_sql);
$q_stmt->bind_param("i", $assessment_id);
$q_stmt->execute();
$questions_result = $q_stmt->get_result();
while ($question = $questions_result->fetch_assoc()) {
    if ($question['question_type'] === 'multiple_choice' || $question['question_type'] === 'true_false') {
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

<style>
    .quiz-form-container .form-control:focus {
        border-color: #0d6efd !important;
        box-shadow: none !important;
    }
</style>

<div class="container my-5">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h4 class="mb-0 text-dark"><?= htmlspecialchars($assessment['title']) ?></h4>
            <div id="timer" class="badge bg-light text-dark fs-5 fw-bold p-2">
                <i class="bi bi-stopwatch me-1"></i>
                <span id="time"><?= $assessment['duration_minutes'] ?>:00</span>
            </div>
        </div>
        <div class="card-body p-4 quiz-form-container">
            <form id="quizForm" action="submit_quiz.php" method="POST">
                <input type="hidden" name="assessment_id" value="<?= $assessment_id ?>">
                <input type="hidden" name="quiz_attempt_id" value="<?= $quiz_attempt_id ?>">

                <?php foreach ($questions as $index => $q): ?>
                    <div class="mb-4 question-block">
                        <p class="fw-bold fs-5">Question <?= $index + 1 ?>:</p>
                        <div class="ps-2 question-text mb-3"><?= $q['question_text'] ?></div>

                        <div class="ms-3">
                            <?php if ($q['question_type'] === 'multiple_choice' || $q['question_type'] === 'true_false'): ?>
                                <?php foreach ($q['options'] as $opt): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $opt['id'] ?>" id="opt_<?= $opt['id'] ?>">
                                        <label class="form-check-label" for="opt_<?= $opt['id'] ?>"><?= htmlspecialchars($opt['option_text']) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <input type="text" class="form-control" name="answers[<?= $q['id'] ?>]" placeholder="Your answer here...">
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($index < count($questions) - 1): ?>
                        <hr class="my-4"><?php endif; ?>
                <?php endforeach; ?>

                <div class="text-center mt-5">
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

        // Function to update the timer display
        function updateTimer() {
            let minutes = Math.floor(totalSeconds / 60);
            let seconds = totalSeconds % 60;

            seconds = seconds < 10 ? '0' + seconds : seconds;
            minutes = minutes < 10 ? '0' + minutes : minutes;

            timeDisplay.textContent = `${minutes}:${seconds}`;
        }

        // Initial display
        updateTimer();

        const timer = setInterval(function() {
            totalSeconds--;
            updateTimer();

            if (totalSeconds < 0) {
                clearInterval(timer);
                alert("Time's up! Your quiz will be submitted automatically.");
                quizForm.submit();
            }
        }, 1000);
    });
</script>

<?php
require_once '../includes/footer.php';
$conn->close();
?>