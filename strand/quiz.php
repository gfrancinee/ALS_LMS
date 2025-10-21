<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php'; // Make sure functions.php is included if needed

// --- SECURITY CHECK & DATA FETCHING ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}
$assessment_id = $_GET['id'] ?? 0;
$student_id = $_SESSION['user_id'];
if (!$assessment_id) {
    header("Location: ../student/student.php?error=notfound"); // Or a more appropriate student dashboard
    exit;
}

$sql = "
    SELECT a.id, a.title, a.duration_minutes, a.max_attempts, a.is_open, a.strand_id
         , (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.assessment_id = a.id AND qa.student_id = ?) as attempts_taken
    FROM assessments a WHERE a.id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) die("Prepare failed (assessment): " . $conn->error);
$stmt->bind_param("ii", $student_id, $assessment_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();
$stmt->close();

$attempts_left = $assessment['max_attempts'] - $assessment['attempts_taken'];
if (!$assessment || !$assessment['is_open'] || $attempts_left <= 0) {
    $_SESSION['error_message'] = !$assessment['is_open'] ? "This assessment is closed." : "You have no attempts remaining.";
    header("Location: take_assessment.php?id=$assessment_id");
    exit;
}

// --- CREATE QUIZ ATTEMPT RECORD ---
$start_time = date("Y-m-d H:i:s");
$status = 'in_progress';
$attempt_stmt = $conn->prepare("INSERT INTO quiz_attempts (assessment_id, student_id, started_at, status) VALUES (?, ?, ?, ?)");
if ($attempt_stmt === false) die("Prepare failed (create attempt): " . $conn->error);
$attempt_stmt->bind_param("iiss", $assessment_id, $student_id, $start_time, $status);
$attempt_stmt->execute();
$quiz_attempt_id = $attempt_stmt->insert_id;
$attempt_stmt->close();

if (empty($quiz_attempt_id)) {
    die("Failed to create quiz attempt record. Please try again.");
}

// --- FETCH QUESTIONS & OPTIONS (EFFICIENTLY) ---
$questions = [];
$q_sql = "SELECT qb.id, qb.question_text, qb.question_type 
          FROM question_bank qb
          JOIN assessment_questions aq ON qb.id = aq.question_id
          WHERE aq.assessment_id = ? 
          ORDER BY aq.id ASC"; // Order questions by add-order
$q_stmt = $conn->prepare($q_sql);
if ($q_stmt === false) die("Prepare failed (questions): " . $conn->error);
$q_stmt->bind_param("i", $assessment_id);
$q_stmt->execute();
$questions = $q_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$q_stmt->close();

// Fetch Options for all fetched Questions in one go
$question_ids = array_column($questions, 'id');
$options_by_question = [];
if (!empty($question_ids)) {
    $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
    $types = str_repeat('i', count($question_ids));

    // Alias 'id' to 'option_id' to prevent confusion
    $sql_options = "SELECT id as option_id, question_id, option_text 
                FROM question_options 
                WHERE question_id IN ({$placeholders})
                ORDER BY id ASC"; // Order options by their creation order
    $stmt_options = $conn->prepare($sql_options);
    if ($stmt_options === false) die("Prepare failed (options): " . $conn->error);

    $stmt_options->bind_param($types, ...$question_ids);
    $stmt_options->execute();
    $result_options = $stmt_options->get_result();
    while ($option = $result_options->fetch_assoc()) {
        // Group options by their question_id
        $options_by_question[$option['question_id']][] = $option;
    }
    $stmt_options->close();
}

// --- Include Header (with no navigation to prevent cheating) ---
$page_title = $assessment['title'];
$hide_navbar = true; // Flag for header.php to hide navigation
require_once '../includes/header.php';
?>

<style>
    /* Prevent user from selecting text, which can be distracting or aid cheating */
    body {
        user-select: none;
        -moz-user-select: none;
        -webkit-user-select: none;
        -ms-user-select: none;
    }

    .quiz-form-container .form-control:focus,
    .quiz-form-container .form-check-input:focus {
        border-color: #0d6efd !important;
        box-shadow: none !important;
    }

    /* Allow text selection in answer fields */
    .quiz-form-container input[type="text"],
    .quiz-form-container textarea {
        user-select: auto;
        -moz-user-select: auto;
        -webkit-user-select: auto;
        -ms-user-select: auto;
    }
</style>

<div class="timer-bar bg-primary text-white p-2" id="timer-bar" style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1031;">
    <div class="container d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?= htmlspecialchars($assessment['title']) ?></h5>
        <h5 class="mb-0">Time Left: <span id="time-left"><?= $assessment['duration_minutes'] ?>:00</span></h5>
    </div>
</div>

<div class="container mt-5 pt-5 mb-5">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4 p-md-5 quiz-form-container">

            <form id="quiz-form" action="submit_quiz.php" method="POST">
                <input type="hidden" name="assessment_id" value="<?= $assessment_id ?>">
                <input type="hidden" name="quiz_attempt_id" value="<?= $quiz_attempt_id ?>">

                <?php foreach ($questions as $index => $q): ?>
                    <div class="mb-4 question-block">
                        <p class="fw-bold fs-5">Question <?= $index + 1 ?>:</p>
                        <div class="ps-2 question-text mb-3"><?= nl2br(htmlspecialchars($q['question_text'])) ?></div>

                        <div class="ms-3">
                            <?php
                            $question_id = $q['id'];
                            // Get the shuffled options for this question
                            $options = $options_by_question[$question_id] ?? [];

                            switch ($q['question_type']):
                                case 'multiple_choice':
                                case 'true_false': // Both use radio buttons
                                    foreach ($options as $opt): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio"
                                                name="answers[<?= $question_id ?>]"
                                                value="<?= $opt['option_id'] ?>"
                                                id="opt_<?= $opt['option_id'] ?>" required>
                                            <label class="form-check-label" for="opt_<?= $opt['option_id'] ?>">
                                                <?= htmlspecialchars($opt['option_text']) ?>
                                            </label>
                                        </div>
                                    <?php endforeach;
                                    break;

                                case 'identification':
                                case 'short_answer':
                                    ?>
                                    <div class_mb-3>
                                        <label for="answer_<?= $question_id ?>" class="form-label small visually-hidden">Your Answer:</label>
                                        <input type="text" class="form-control"
                                            id="answer_<?= $question_id ?>"
                                            name="answers[<?= $question_id ?>]"
                                            placeholder="Your answer here..." required>
                                    </div>
                                <?php
                                    break;

                                case 'essay':
                                ?>
                                    <div class="mb-3">
                                        <label for="answer_<?= $question_id ?>" class="form-label small visually-hidden">Your Answer:</label>
                                        <textarea class="form-control"
                                            id="answer_<?= $question_id ?>"
                                            name="answers[<?= $question_id ?>]"
                                            rows="5"
                                            placeholder="Type your essay here..." required></textarea>
                                    </div>
                            <?php
                                    break;

                            endswitch;
                            ?>
                        </div>
                    </div>
                    <?php if ($index < count($questions) - 1): ?>
                        <hr class="my-4">
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="text-center mt-5">
                    <button type="submit" class="btn btn-success btn-lg">Submit Assessment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const timeLeftDisplay = document.getElementById('time-left');
        const quizForm = document.getElementById('quiz-form');
        let timeInSeconds = <?= $assessment['duration_minutes'] * 60 ?>;

        // Flag to prevent double submit
        let isSubmitting = false;

        // --- Timer Logic ---
        function updateTimerDisplay() {
            let minutes = Math.floor(timeInSeconds / 60);
            let seconds = timeInSeconds % 60;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            timeLeftDisplay.textContent = `${minutes}:${seconds}`;
        }

        const timerInterval = setInterval(() => {
            timeInSeconds--;
            updateTimerDisplay();

            if (timeInSeconds <= 0) {
                clearInterval(timerInterval);
                if (!isSubmitting) {
                    isSubmitting = true;
                    alert("Time's up! Your assessment will be submitted automatically.");
                    quizForm.submit();
                }
            }
        }, 1000);

        // --- Navigation Warning ---
        const confirmLeave = (event) => {
            if (!isSubmitting) {
                // Standard browser prompt
                event.preventDefault();
                event.returnValue = ''; // Required for most browsers
            }
        };
        window.addEventListener('beforeunload', confirmLeave);

        // --- Form Submission ---
        quizForm.addEventListener('submit', () => {
            isSubmitting = true;
            // Remove the navigation warning *before* submitting
            window.removeEventListener('beforeunload', confirmLeave);
        });

        // Initial display update
        updateTimerDisplay();
    });
</script>

<?php
// Use a minimal footer, also hiding navigation
require_once '../includes/footer.php';
$conn->close();
?>