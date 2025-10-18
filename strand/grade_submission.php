<?php
session_start();
require_once '../includes/db.php'; // Adjust path if needed
require_once '../includes/functions.php'; // Adjust path if needed

// --- Security Check: Ensure user is a logged-in teacher ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit;
}
$teacher_id = $_SESSION['user_id'];

// --- Get Attempt ID from URL ---
$attempt_id = filter_input(INPUT_GET, 'attempt_id', FILTER_VALIDATE_INT);
if (!$attempt_id) {
    die("Invalid attempt ID."); // Or redirect
}

// --- Fetch Attempt Details (Student, Assessment, Score) & Verify Teacher owns Assessment ---
$stmt_attempt = $conn->prepare(
    "SELECT qa.id as attempt_id, qa.student_id, qa.assessment_id, qa.score, qa.total_items, qa.submitted_at,
            u.first_name, u.last_name,
            a.title as assessment_title, a.strand_id, a.teacher_id
     FROM quiz_attempts qa
     JOIN users u ON qa.student_id = u.id
     JOIN assessments a ON qa.assessment_id = a.id
     WHERE qa.id = ?"
);
if ($stmt_attempt === false) {
    die("Prepare failed (attempt): " . $conn->error);
}
$stmt_attempt->bind_param("i", $attempt_id);
$stmt_attempt->execute();
$result_attempt = $stmt_attempt->get_result();
$attempt_details = $result_attempt->fetch_assoc();
$stmt_attempt->close();

if (!$attempt_details || $attempt_details['teacher_id'] != $teacher_id) {
    die("Attempt not found or you do not have permission to view it.");
}

// --- Fetch Questions, Student Answers, and Correct Options for this Attempt ---
// We need the question text, type, grading_type, max_points from question_bank
// We need the student's answer_text and is_correct status from student_answers
// We need the correct option_text from question_options where is_correct = 1
$sql_answers = "
    SELECT
        qb.id as question_id,
        qb.question_text,
        qb.question_type,
        qb.grading_type,
        qb.max_points,
        sa.id as student_answer_id,
        sa.answer_text as student_answer_text,
        sa.is_correct as student_is_correct,
        sa.points_awarded -- Store manually awarded points
    FROM question_bank qb
    JOIN student_answers sa ON qb.id = sa.question_id
    WHERE sa.quiz_attempt_id = ?
    ORDER BY sa.id -- Order by the sequence they were answered/stored
";
$stmt_answers = $conn->prepare($sql_answers);
if ($stmt_answers === false) {
    die("Prepare failed (answers): " . $conn->error);
}
$stmt_answers->bind_param("i", $attempt_id);
$stmt_answers->execute();
$result_answers = $stmt_answers->get_result();
$questions_and_answers = [];
while ($row = $result_answers->fetch_assoc()) {
    $questions_and_answers[$row['question_id']] = $row;
}
$stmt_answers->close();

// Fetch correct options separately (can be complex in one query)
$question_ids = array_keys($questions_and_answers);
$correct_options = [];
if (!empty($question_ids)) {
    $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
    $types = str_repeat('i', count($question_ids));
    $sql_correct_opts = "SELECT question_id, GROUP_CONCAT(option_text SEPARATOR '; ') as correct_texts
                         FROM question_options
                         WHERE question_id IN ({$placeholders}) AND is_correct = 1
                         GROUP BY question_id";
    $stmt_correct_opts = $conn->prepare($sql_correct_opts);
    if ($stmt_correct_opts) {
        $stmt_correct_opts->bind_param($types, ...$question_ids);
        $stmt_correct_opts->execute();
        $result_correct_opts = $stmt_correct_opts->get_result();
        while ($row = $result_correct_opts->fetch_assoc()) {
            $correct_options[$row['question_id']] = $row['correct_texts'];
        }
        $stmt_correct_opts->close();
    }
}


// --- Include Header ---
$page_title = "Grade Submission";
require_once '../includes/header.php'; // Adjust path if needed
?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Grade Submission</h2>
            <p class="lead mb-0">Assessment: <strong><?= htmlspecialchars($attempt_details['assessment_title']) ?></strong></p>
            <p class="text-muted">Student: <?= htmlspecialchars($attempt_details['first_name'] . ' ' . $attempt_details['last_name']) ?> | Submitted: <?= date("M j, Y, g:i A", strtotime($attempt_details['submitted_at'])) ?></p>
        </div>
        <a href="view_submissions.php?assessment_id=<?= $attempt_details['assessment_id'] ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Submissions
        </a>
    </div>
    <hr>

    <div class="row">
        <div class="col-md-8">
            <h4 class="mb-3">Answers</h4>
            <form id="grading-form" action="process_grading.php" method="POST">
                <input type="hidden" name="attempt_id" value="<?= $attempt_id ?>">

                <?php foreach ($questions_and_answers as $index => $qa): ?>
                    <?php
                    $q_num = $index + 1; // Simple numbering based on fetch order
                    $is_manual = ($qa['grading_type'] == 'manual' || $qa['question_type'] == 'essay'); // Determine if manual input needed
                    $points_value = ($qa['points_awarded'] !== null) ? $qa['points_awarded'] : ($qa['student_is_correct'] ? $qa['max_points'] : 0); // Pre-fill points
                    ?>
                    <div class="card mb-4 shadow-sm question-review-card">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Question <?= $q_num ?></h5>
                            <span class="badge <?= $is_manual ? 'bg-warning text-dark' : 'bg-info' ?>">
                                <?= ucfirst($qa['grading_type']) ?> Grading (<?= $qa['max_points'] ?>pt<?= $qa['max_points'] > 1 ? 's' : '' ?>)
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="card-text fw-bold question-text mb-3"><?= nl2br(htmlspecialchars($qa['question_text'])) ?></p>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Student's Answer:</label>
                                <div class="p-2 border rounded bg-light student-answer">
                                    <?php if (empty($qa['student_answer_text'])): ?>
                                        <em class="text-danger">-- No answer provided --</em>
                                    <?php else: ?>
                                        <?= nl2br(htmlspecialchars($qa['student_answer_text'])) ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!$is_manual && isset($correct_options[$qa['question_id']])): ?>
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Correct Answer(s):</label>
                                    <div class="p-2 border rounded correct-answer">
                                        <?= nl2br(htmlspecialchars($correct_options[$qa['question_id']])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                        <div class="card-footer d-flex justify-content-end align-items-center">
                            <?php if ($is_manual): ?>
                                <label for="points_<?= $qa['student_answer_id'] ?>" class="form-label me-2 mb-0">Points:</label>
                                <input type="number"
                                    class="form-control manual-grade-input"
                                    style="width: 80px;"
                                    id="points_<?= $qa['student_answer_id'] ?>"
                                    name="points[<?= $qa['student_answer_id'] ?>]"
                                    min="0"
                                    max="<?= $qa['max_points'] ?>"
                                    step="0.5" value="<?= htmlspecialchars($points_value) ?>" required>
                                <span class="ms-2 text-muted">/ <?= $qa['max_points'] ?></span>
                            <?php else: ?>
                                <span class="fw-bold me-2">Points Awarded:</span>
                                <span class="badge <?= $qa['student_is_correct'] ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $points_value ?> / <?= $qa['max_points'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-check-circle-fill me-2"></i> Save Grades & Update Total Score
                    </button>
                </div>
            </form>
        </div>
        <div class="col-md-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header">
                    Summary
                </div>
                <div class="card-body text-center">
                    <h5 class="card-title">Current Score</h5>
                    <p class="display-4 fw-bold" id="current-score-display"><?= $attempt_details['score'] ?></p>
                    <p class="fs-5 text-muted">out of <?= $attempt_details['total_items'] ?> total points</p>
                    <hr>
                    <p class="small text-muted">Manually entered points will update the total score upon saving.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const gradingForm = document.getElementById('grading-form');
        const scoreDisplay = document.getElementById('current-score-display');
        const manualGradeInputs = gradingForm.querySelectorAll('.manual-grade-input');
        const totalPointsPossible = <?= $attempt_details['total_items'] ?? 0 ?>; // Get total from PHP

        // Store initial scores for auto-graded items
        const autoGradedScores = {};
        gradingForm.querySelectorAll('.question-review-card').forEach(card => {
            const pointsAwardedSpan = card.querySelector('.card-footer .badge'); // Badge for auto-graded
            if (pointsAwardedSpan && !card.querySelector('.manual-grade-input')) {
                // Extract points and question ID (assuming student_answer_id links uniquely)
                const studentAnswerIdInput = card.querySelector('input[type="number"]'); // Check if manual input exists
                if (!studentAnswerIdInput) { // Only process if truly auto-graded display
                    const pointsText = pointsAwardedSpan.textContent.trim();
                    const points = parseFloat(pointsText.split('/')[0].trim());
                    // Need a unique identifier if multiple auto questions exist.
                    // For now, let's assume we recalculate all auto based on stored correct status
                }
            }
        });

        function recalculateScorePreview() {
            let currentTotal = 0;
            // Loop through all question cards again for recalculation
            gradingForm.querySelectorAll('.question-review-card').forEach(card => {
                const manualInput = card.querySelector('.manual-grade-input');
                const autoBadge = card.querySelector('.card-footer .badge'); // Auto-graded score badge
                const maxPointsText = card.querySelector('.card-footer .text-muted')?.textContent || card.querySelector('.card-footer span:not(.badge)')?.textContent || '/ 1'; // Find max points text
                const maxPoints = parseFloat(maxPointsText.replace(/[^0-9.]/g, '')) || 1; // Extract max points number
                const isCorrectAuto = autoBadge ? autoBadge.classList.contains('bg-success') : false; // Check if auto was correct

                if (manualInput) {
                    // Manual: Add the current input value (or 0 if invalid)
                    const points = parseFloat(manualInput.value);
                    if (!isNaN(points) && points >= 0) {
                        // Clamp value to max points visually
                        currentTotal += Math.min(points, maxPoints);
                    }
                } else if (autoBadge) {
                    // Automatic: Add max points if correct, 0 otherwise
                    if (isCorrectAuto) {
                        currentTotal += maxPoints;
                    }
                }
            });

            // Update the display
            if (scoreDisplay) {
                // Ensure score doesn't exceed total possible visually
                scoreDisplay.textContent = Math.min(currentTotal, totalPointsPossible);
            }
        }

        // Add event listeners to all manual input fields
        manualGradeInputs.forEach(input => {
            input.addEventListener('input', () => {
                // Optional: Basic validation to prevent negative or > max points input
                const max = parseFloat(input.getAttribute('max'));
                let value = parseFloat(input.value);
                if (isNaN(value) || value < 0) {
                    input.value = 0; // Reset invalid input
                } else if (value > max) {
                    input.value = max; // Cap at max points
                }
                recalculateScorePreview(); // Update score preview on any change
            });
        });

        // Initial calculation on page load
        recalculateScorePreview();
    });
</script>

<?php
$conn->close();
require_once '../includes/footer.php'; // Adjust path if needed
?>