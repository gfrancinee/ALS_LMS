<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php'; // For recommendMaterialForQuestion()
require_once '../includes/header.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

// --- GET DATA ---
$attempt_id = $_GET['attempt_id'] ?? 0;
$student_id = $_SESSION['user_id'];

if (!$attempt_id) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Assessment attempt not found.</div></div>";
    require_once '../includes/footer.php';
    exit;
}

// --- FETCH QUIZ ATTEMPT DETAILS ---
$sql = "
    SELECT 
        qa.score, qa.total_items, qa.submitted_at, 
        a.title as assessment_title, a.strand_id, qa.assessment_id
    FROM quiz_attempts qa
    JOIN assessments a ON qa.assessment_id = a.id
    WHERE qa.id = ? AND qa.student_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $attempt_id, $student_id);
$stmt->execute();
$attempt_details = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$attempt_details) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Could not load your results.</div></div>";
    require_once '../includes/footer.php';
    exit;
}

$score = $attempt_details['score'];
$total_items = $attempt_details['total_items'];
$percentage = ($total_items > 0) ? round(($score / $total_items) * 100) : 0;
$back_link = 'strand.php?id=' . $attempt_details['strand_id'] . '#assessments';


// --- *** NEW: CHECK FOR MANUAL GRADED QUESTIONS *** ---
$has_manual_questions = false;
$stmt_check_manual = $conn->prepare(
    "SELECT 1 
     FROM question_bank qb
     JOIN assessment_questions aq ON qb.id = aq.question_id
     WHERE aq.assessment_id = ? AND qb.grading_type = 'manual'
     LIMIT 1"
);
if ($stmt_check_manual === false) {
    error_log("Prepare failed (check manual): " . $conn->error);
} else {
    $stmt_check_manual->bind_param("i", $attempt_details['assessment_id']);
    $stmt_check_manual->execute();
    $has_manual_questions = $stmt_check_manual->get_result()->num_rows > 0;
    $stmt_check_manual->close();
}
// --- *** END NEW CHECK *** ---

// --- CHECK IF MANUAL GRADING IS PENDING ---
$grading_pending = false;
if ($has_manual_questions) {
    $stmt_check_pending = $conn->prepare(
        "SELECT 1 
         FROM student_answers sa
         JOIN question_bank qb ON sa.question_id = qb.id
         WHERE sa.quiz_attempt_id = ? 
           AND qb.grading_type = 'manual' 
           AND sa.points_awarded IS NULL 
         LIMIT 1"
    );
    if ($stmt_check_pending === false) {
        error_log("Prepare failed (check pending): " . $conn->error);
        // Decide how to handle this error - maybe assume pending?
        $grading_pending = true;
    } else {
        $stmt_check_pending->bind_param("i", $attempt_id);
        $stmt_check_pending->execute();
        $grading_pending = $stmt_check_pending->get_result()->num_rows > 0;
        $stmt_check_pending->close();
    }
}
// --- END MANUAL GRADING CHECK ---


// --- AUTOMATIC RECOMMENDATION ENGINE LOGIC ---
$recommendations = [];
$wrong_question_ids = [];

// Find all questions the student got wrong (auto-graded only)
$wrong_q_stmt = $conn->prepare(
    "SELECT sa.question_id 
     FROM student_answers sa
     JOIN question_bank qb ON sa.question_id = qb.id
     WHERE sa.quiz_attempt_id = ? 
       AND sa.is_correct = 0 
       AND qb.grading_type = 'automatic'" // Only recommend for auto-graded wrongs
);
$wrong_q_stmt->bind_param("i", $attempt_id);
$wrong_q_stmt->execute();
$wrong_q_result = $wrong_q_stmt->get_result();
while ($row = $wrong_q_result->fetch_assoc()) {
    $wrong_question_ids[] = $row['question_id'];
}
$wrong_q_stmt->close();

// For each wrong question, get a material recommendation.
if (!empty($wrong_question_ids)) {
    $recommended_ids = []; // To prevent duplicate recommendations
    foreach ($wrong_question_ids as $q_id) {
        $rec = recommendMaterialForQuestion($conn, $q_id, $attempt_details['strand_id']);
        if ($rec !== null && !in_array($rec['id'], $recommended_ids)) {
            $recommendations[] = $rec;
            $recommended_ids[] = $rec['id'];
        }
    }
}

// --- FETCH FULL REVIEW DATA (Questions, Answers, Correct Answers) ---
$sql_review = "
    SELECT
        qb.id as question_id,
        qb.question_text,
        qb.question_type,
        qb.grading_type,
        sa.answer_text as student_answer_text,
        sa.is_correct as student_is_correct,
        sa.points_awarded
    FROM question_bank qb
    JOIN student_answers sa ON qb.id = sa.question_id
    WHERE sa.quiz_attempt_id = ?
    ORDER BY sa.id
";
$stmt_review = $conn->prepare($sql_review);
$stmt_review->bind_param("i", $attempt_id);
$stmt_review->execute();
$result_review = $stmt_review->get_result();
$review_items = [];
// Re-check wrong questions based on fetched review data for consistency
$wrong_question_ids_from_review = [];
while ($row = $result_review->fetch_assoc()) {
    $review_items[$row['question_id']] = $row;
    // If this was an auto-graded, incorrect answer, add it for recommendation
    if ($row['grading_type'] == 'automatic' && $row['student_is_correct'] == 0) {
        $wrong_question_ids_from_review[] = $row['question_id'];
    }
    // NEW: Also add manually-graded questions that scored 0
    elseif ($row['grading_type'] == 'manual' && $row['points_awarded'] !== null && $row['points_awarded'] == 0) {
        $wrong_question_ids_from_review[] = $row['question_id'];
    }
}
$stmt_review->close();

// Fetch correct options separately for all questions
$question_ids = array_keys($review_items);
$correct_options = [];
$all_options = []; // To display all choices for MC/TF

if (!empty($question_ids)) {
    $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
    $types = str_repeat('i', count($question_ids));

    // Get ALL options for these questions
    $sql_all_opts = "SELECT id, question_id, option_text, is_correct 
                     FROM question_options
                     WHERE question_id IN ({$placeholders})";
    $stmt_all_opts = $conn->prepare($sql_all_opts);
    $stmt_all_opts->bind_param($types, ...$question_ids);
    $stmt_all_opts->execute();
    $result_all_opts = $stmt_all_opts->get_result();
    while ($row = $result_all_opts->fetch_assoc()) {
        $all_options[$row['question_id']][] = $row;
        if ($row['is_correct'] == 1) {
            $correct_options[$row['question_id']][] = $row['option_text'];
        }
    }
    $stmt_all_opts->close();
}

// --- AUTOMATIC RECOMMENDATION ENGINE LOGIC ---
$recommendations = [];
if (!empty($wrong_question_ids_from_review)) {
    $recommended_ids = []; // To prevent duplicate recommendations
    $material_wrong_count = []; // Track how many wrong questions per material

    foreach ($wrong_question_ids_from_review as $q_id) {
        $rec = recommendMaterialForQuestion($conn, $q_id, $attempt_details['strand_id']);
        if ($rec !== null) {
            $material_id = $rec['id'];

            // If this material hasn't been added yet, add it
            if (!in_array($material_id, $recommended_ids)) {
                $recommendations[] = $rec;
                $recommended_ids[] = $material_id;
                $material_wrong_count[$material_id] = 1; // First wrong question for this material
            } else {
                // Material already exists, increment the count
                $material_wrong_count[$material_id]++;
            }
        }
    }

    // Sort recommendations by wrong question count (highest to lowest)
    usort($recommendations, function ($a, $b) use ($material_wrong_count) {
        $count_a = $material_wrong_count[$a['id']] ?? 0;
        $count_b = $material_wrong_count[$b['id']] ?? 0;
        return $count_b - $count_a; // Descending order (most wrong questions first)
    });
} else {
    $recommendations = []; // Ensure recommendations are empty if no wrong answers
}
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0 text-center bg-white mt-0">
                <div class="card-body p-5">
                    <h1 class="card-title">Completed!</h1>
                    <p class="lead">You have completed the <strong><?= htmlspecialchars($attempt_details['assessment_title']) ?></strong></p>

                    <div class="my-4">

                        <?php if ($has_manual_questions): ?>

                            <?php if ($grading_pending): // Manual questions exist AND grading is NOT complete 
                            ?>
                                <h5 class="text-info">Score (Auto-Graded Portion):</h5>
                                <h2 class="display-4 fw-bold text-primary"><?= $score ?> / <?= $total_items ?></h2>
                                <p class="h4">(<?= $percentage ?>%)</p>
                                <p class="lead mt-3 text-muted">
                                    Your final score is <strong>pending</strong>.
                                </p>

                            <?php else: // Manual questions exist BUT grading IS complete 
                            ?>
                                <h5 class="text-success">Your Final Score:</h5>
                                <h2 class="display-4 fw-bold text-primary"><?= $score ?> / <?= $total_items ?></h2>
                                <p class="h4">(<?= $percentage ?>%)</p>

                            <?php endif; // End check for grading_pending 
                            ?>

                        <?php else: // NO manual questions in this assessment 
                        ?>

                            <h5 class="text-success">Your Final Score:</h5>
                            <h2 class="display-4 fw-bold text-primary"><?= $score ?> / <?= $total_items ?></h2>
                            <p class="h4">(<?= $percentage ?>%)</p>

                        <?php endif;
                        ?>
                    </div>

                    <p class="text-muted">Submitted on: <?= date("F j, Y, g:i a", strtotime($attempt_details['submitted_at'])) ?></p>

                    <a href="<?= htmlspecialchars($back_link) ?>" class="btn text-primary mt-4 shadow-none">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <?php
            if (!empty($recommendations) && isset($percentage) && $percentage < 74 && isset($grading_pending) && !$grading_pending):
            ?>
                <div class="card shadow-sm border-0 mb-4 mt-4 bg-white">
                    <div class="card-body">

                        <p class="text-center mb-3 text-info small">
                            <i class="bi bi-info-circle me-2"></i>Looks like you got a low score in this assessment. Here are some materials you might want to review:
                        </p>

                        <div class="list-group list-group-flush rounded-3">
                            <?php foreach ($recommendations as $rec): ?>
                                <a href="/ALS_LMS/strand/view_material.php?id=<?= $rec['id'] ?>" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center">
                                    <?php
                                    // Determine icon based on type (example, adjust as needed)
                                    $rec_icon = 'bi-file-earmark-pdf-fill text-danger'; // Default
                                    if ($rec['type'] === 'video') $rec_icon = 'bi-play-circle-fill text-info';
                                    else if ($rec['type'] === 'link') $rec_icon = 'bi-link-45deg text-primary';
                                    else if ($rec['type'] === 'file') {
                                        // You might check extension here for PDF, PPT etc. if needed
                                    }
                                    ?>
                                    <i class="bi <?= $rec_icon ?> fs-4 me-3"></i>
                                    <div>
                                        <strong class="d-block"><?= htmlspecialchars($rec['label']) ?></strong>
                                        <small class="text-muted">Type: <?= ucfirst(htmlspecialchars($rec['type'])) ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif;
            ?>

            <?php if (!empty($review_items)): ?>
                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Question Review</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php
                        $q_num = 1; // Start question numbering
                        foreach ($review_items as $item):
                        ?>
                            <div class="mb-4 pb-3 <?php if ($q_num < count($review_items)) echo 'border-bottom'; ?>">
                                <p class="fw-bold fs-5">Question <?= $q_num++ ?>:</p>
                                <p class="question-text mb-3 p-3 bg-light rounded"><?= nl2br(htmlspecialchars($item['question_text'])) ?></p>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Your Answer:</label>
                                    <?php
                                    $student_answer = $item['student_answer_text'];
                                    $is_correct = $item['student_is_correct'];
                                    $answer_class = $is_correct ? 'border-success bg-success-light' : 'border-danger bg-danger-light';
                                    if ($item['grading_type'] == 'manual') {
                                        $answer_class = 'border-info bg-info-light'; // Pending review
                                    }

                                    if (in_array($item['question_type'], ['multiple_choice', 'true_false'])) {
                                        $student_answer_text = "<em>No answer provided</em>"; // Default if not found
                                        if (isset($all_options[$item['question_id']])) {
                                            foreach ($all_options[$item['question_id']] as $opt) {
                                                // Ensure we compare the stored answer (option ID) correctly
                                                if (isset($opt['id']) && $opt['id'] == $student_answer) {
                                                    $student_answer_text = htmlspecialchars($opt['option_text']);
                                                    break;
                                                }
                                            }
                                        }
                                    } else {
                                        $student_answer_text = !empty($student_answer) ? nl2br(htmlspecialchars($student_answer)) : "<em>No answer provided</em>";
                                    }
                                    ?>
                                    <div class="p-3 border <?= $answer_class ?> rounded student-answer">
                                        <?= $student_answer_text ?>
                                    </div>
                                </div>

                                <?php if ($item['grading_type'] == 'automatic' && !$is_correct): ?>
                                    <div class="mb-2">
                                        <label class="form-label fw-bold">Correct Answer:</label>
                                        <div class="p-3 border border-success bg-success-light rounded correct-answer">
                                            <?php
                                            $correct_texts = $correct_options[$item['question_id']] ?? [];
                                            echo !empty($correct_texts) ? nl2br(htmlspecialchars(implode('; ', $correct_texts))) : "<em>N/A</em>";
                                            ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($item['grading_type'] == 'manual'): ?>
                                    <div class="mb-2">
                                        <label class="form-label fw-bold">Teacher Feedback:</label>
                                        <div class="p-3 border border-info bg-info-light rounded">
                                            <?php if ($item['points_awarded'] !== null): ?>
                                                Point/s: <?= $item['points_awarded'] ?>
                                            <?php else: ?>
                                                <em>Pending teacher review.</em>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>


                                <div class="text-end">
                                    <?php if ($item['grading_type'] == 'manual'): ?>
                                        <span class="badge text-info">
                                            <?= ($item['points_awarded'] !== null) ? 'Graded' : 'Pending Review' ?>
                                        </span>
                                    <?php elseif ($is_correct): ?>
                                        <span class="badge text-success">Correct</span>
                                    <?php else: ?>
                                        <span class="badge text-danger">Incorrect</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div> <?php endif; ?>

        </div>
    </div>
</div>

<style>
    /* Custom CSS to forcefully remove focus outline from buttons/links */
    .btn:focus,
    .btn:focus-visible,
    a.btn:focus,
    /* Target links styled as buttons specifically */
    a.btn:focus-visible {
        outline: none !important;
        box-shadow: none !important;
        border-color: transparent !important;
        /* Added to remove potential border color change */
    }

    .bg-success-light {
        background-color: #e6f7ec;
    }

    .border-success {
        border-color: #b7e1cd !important;
    }

    .bg-danger-light {
        background-color: #fdecea;
    }

    .border-danger {
        border-color: #f5c6cb !important;
    }

    .bg-info-light {
        background-color: #e7f6f8;
    }

    .border-info {
        border-color: #bde6ee !important;
    }
</style>

<?php
require_once '../includes/footer.php';
$conn->close();
?>