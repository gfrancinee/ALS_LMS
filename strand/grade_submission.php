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
            u.fname, u.lname,
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

// --- THIS IS THE FULLY CORRECTED SQL QUERY ---
$sql_answers = "
    SELECT
        qb.id as question_id,
        qb.question_text,
        qb.question_type,
        qb.grading_type,
        qb.max_points,
        sa.id as student_answer_id,
        
        -- This CASE statement is now correct
        CASE 
            -- If it's manual, just show the text
            WHEN qb.grading_type = 'manual' THEN sa.answer_text
            
            -- If it's automatic AND identification/short_answer, show the text
            WHEN qb.grading_type = 'automatic' AND qb.question_type IN ('identification', 'short_answer', 'Short Answer/Enumeration') THEN sa.answer_text
            
            -- If it's automatic and anything else (MC, T/F), get the option text
            ELSE opt.option_text
        END as student_answer_display_text,
        
        sa.is_correct as student_is_correct,
        sa.points_awarded
    FROM question_bank qb
    JOIN student_answers sa ON qb.id = sa.question_id

    -- This JOIN is now correct
    LEFT JOIN question_options opt ON 
        (qb.grading_type = 'automatic' AND qb.question_type IN ('multiple_choice', 'true_false'))
        AND opt.id = CAST(sa.answer_text AS UNSIGNED) 
        AND opt.question_id = qb.id

    WHERE sa.quiz_attempt_id = ?
    ORDER BY sa.id
";
$stmt_answers = $conn->prepare($sql_answers);
if ($stmt_answers === false) {
    die("Prepare failed (answers): " . $conn->error);
}
$stmt_answers->bind_param("i", $attempt_id);
$stmt_answers->execute();
$result_answers = $stmt_answers->get_result();
$questions_and_answers = [];
// --- FIX #1 (Numbering Bug): Store results in a simple array
while ($row = $result_answers->fetch_assoc()) {
    $questions_and_answers[] = $row; // This fixes the array index
}
$stmt_answers->close();
// --- END OF SQL FIXES ---


// Fetch correct options separately (This is your original, unchanged code)
$question_ids = array_column($questions_and_answers, 'question_id'); // Get question IDs
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
            <div class="back-container">
                <a href="view_submissions.php?assessment_id=<?= $attempt_details['assessment_id'] ?>" class="back-link">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
            <h2 class="mb-1">Grade Submission</h2>
            <p class="lead mb-0">Assessment: <strong><?= htmlspecialchars($attempt_details['assessment_title']) ?></strong></p>
            <p class="text-muted">Student: <?= htmlspecialchars($attempt_details['fname'] . ' ' . $attempt_details['lname']) ?> | Submitted: <?= date("M j, Y, g:i A", strtotime($attempt_details['submitted_at'])) ?></p>
        </div>

    </div>
    <hr>

    <div class="row">
        <div class="col-md-8">
            <h4 class="mb-3">Answers</h4>
            <form id="grading-form" action="process_grading.php" method="POST">
                <input type="hidden" name="attempt_id" value="<?= $attempt_id ?>">

                <?php $q_num = 1; // FIX #1 (Numbering Bug): Initialize counter to 1 
                ?>
                <?php foreach ($questions_and_answers as $index => $qa): ?>
                    <?php
                    // Your original logic
                    // $q_num = $index + 1; // <-- This was the original numbering bug
                    $is_manual = ($qa['grading_type'] == 'manual' || $qa['question_type'] == 'essay');
                    $points_value = ($qa['points_awarded'] !== null) ? $qa['points_awarded'] : ($qa['student_is_correct'] ? $qa['max_points'] : 0);
                    ?>
                    <div class="card mt-4 border-light shadow-sm question-review-card"
                        data-grading="<?= htmlspecialchars($qa['grading_type']) ?>"
                        data-max-points="<?= htmlspecialchars($qa['max_points']) ?>"
                        data-auto-points="<?= ($qa['grading_type'] == 'automatic' ? ($points_value ?? '0') : '0') ?>">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">

                            <h5 class="mb-0">Question <?= $q_num++ ?></h5>

                            <span class="badge <?= $is_manual ? 'text-warning' : 'text-info' ?>">
                                <?= ucfirst($qa['grading_type']) ?> Grading (<?= $qa['max_points'] ?>pt<?= $qa['max_points'] > 1 ? 's' : '' ?>)
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="card-text fw-bold question-text mb-3"><?= nl2br(htmlspecialchars($qa['question_text'])) ?></p>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Student's Answer:</label>
                                <div class="p-2 border rounded bg-light student-answer">

                                    <?php if ($qa['student_answer_display_text'] === null || $qa['student_answer_display_text'] === ''): ?>
                                        <em class="text-danger">-- No answer provided --</em>
                                    <?php else: ?>
                                        <?= nl2br(htmlspecialchars($qa['student_answer_display_text'])) ?>
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
                        <div class="card-footer d-flex justify-content-end align-items-center bg-white">
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
                                <span class="fw-bold me-2">Point/s:</span>
                                <span class="badge <?= $qa['student_is_correct'] ? 'text-success' : 'text-danger' ?>">
                                    <?= $points_value ?> / <?= $qa['max_points'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-success rounded-pill px-3 btn-md">
                        <i class="bi bi-check-circle-fill me-2"></i>Save Grades
                    </button>
                </div>
            </form>
        </div>

        <div class="col-md-4">
            <div class="card sticky-top mt-5 border-light shadow-sm" style="top: 20px;">
                <div class="card-header bg-white">
                    Summary
                </div>
                <div class="card-body text-center">
                    <h5 class="card-title">Score</h5>
                    <p class="display-4 fw-light" id="current-score-display"><?= $attempt_details['score'] ?></p>
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
        const manualGradeInputs = gradingForm.querySelectorAll('.manual-grade-input'); // Use the specific class for inputs
        const totalPossiblePoints = <?= $attempt_details['total_items'] ?? 0 ?>; // Get total from PHP

        // Function to calculate and update the score preview
        function recalculateScorePreview() {
            let currentTotalScore = 0.0; // Use float for potential 0.5 points
            console.log('--- Recalculating Score ---'); // Keep for debugging

            gradingForm.querySelectorAll('.question-review-card').forEach((block, index) => {

                const gradingType = block.dataset.grading; // 'manual' or 'automatic'
                const maxPoints = parseFloat(block.dataset.maxPoints || '0');
                const autoPoints = parseFloat(block.dataset.autoPoints || '0'); // Get auto points

                console.log(`Question ${index + 1}: Type=${gradingType}, Max=${maxPoints}, AutoPts=${autoPoints}`);


                if (gradingType === 'manual') {
                    const input = block.querySelector('.manual-grade-input');
                    if (input) {
                        let points = parseFloat(input.value);
                        console.log(`  Manual Input Value: "${input.value}", Parsed: ${points}`);

                        if (isNaN(points) || points < 0) {
                            points = 0.0;
                        }
                        const scoreToAdd = Math.min(points, maxPoints);
                        currentTotalScore += scoreToAdd;
                        console.log(`  Manual Score Added: ${scoreToAdd}`);
                    } else {
                        console.log('  Manual input not found!');
                    }
                } else { // Assumed automatic
                    currentTotalScore += autoPoints;
                    console.log(`  Automatic Score Added: ${autoPoints}`);
                }
            });

            console.log('Final Calculated Score:', currentTotalScore); // Debug Final Score

            if (scoreDisplay) {
                const finalScore = Math.min(currentTotalScore, totalPossiblePoints);
                scoreDisplay.textContent = Number.isInteger(finalScore) ? finalScore : finalScore.toFixed(1);
            }
        }

        // Add event listeners to manual input fields
        manualGradeInputs.forEach(input => {
            input.addEventListener('input', recalculateScorePreview);

            input.addEventListener('blur', () => {
                const max = parseFloat(input.getAttribute('max'));
                let value = parseFloat(input.value);

                if (isNaN(value) || value < 0) {
                    input.value = ''; // Or set to '0' if you prefer
                    recalculateScorePreview();
                } else if (value > max) {
                    input.value = max;
                    recalculateScorePreview();
                }
            });
        });

        recalculateScorePreview();
    });
</script>

<style>
    .back-container {
        position: absolute;
        top: 20px;
        right: 30px;
        z-index: 1000;
    }

    /* --- Role-Based Hover Color for Back Link --- */
    .back-link {
        color: #6c757d;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s ease-in-out;
    }

    .back-link-teacher:hover {
        color: green;
    }
</style>

<?php
$conn->close();
require_once '../includes/footer.php'; // Adjust path if needed
?>