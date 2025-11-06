<?php
session_start();
// The '../' is important because this file is inside the 'strand' folder
require_once '../includes/db.php';
require_once '../includes/header.php';

// --- Security Check ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); // Path goes up and then to login.php
    exit;
}

// --- Get Assessment ID ---
$assessment_id = $_GET['id'] ?? 0;
if (empty($assessment_id)) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Error: Assessment ID not provided.</div></div>";
    require_once '../includes/footer.php';
    exit;
}

// --- Fetch Assessment Details ---
$stmt = $conn->prepare("SELECT title, description, strand_id, type FROM assessments WHERE id = ?");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$assessment_result = $stmt->get_result();
$assessment = $assessment_result->fetch_assoc();
$stmt->close();

if (!$assessment) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Assessment not found.</div></div>";
    require_once '../includes/footer.php';
    exit;
}

// --- Fetch All Questions ---
$sql_questions = "SELECT qb.id, qb.question_text, qb.question_type 
                  FROM question_bank qb
                  JOIN assessment_questions aq ON qb.id = aq.question_id
                  WHERE aq.assessment_id = ? ORDER BY aq.id ASC";

$stmt_questions = $conn->prepare($sql_questions);
$stmt_questions->bind_param("i", $assessment_id);
$stmt_questions->execute();
$questions_result = $stmt_questions->get_result();
$questions = $questions_result->fetch_all(MYSQLI_ASSOC);
$stmt_questions->close();

// --- Fetch All Options for All Questions in One Go (More Efficient) ---
$question_ids = array_column($questions, 'id');
$options_by_question = [];
if (!empty($question_ids)) {
    $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
    $types = str_repeat('i', count($question_ids));

    // Select the necessary columns: question_id, option_text, and is_correct
    $sql_options = "SELECT question_id, option_text, is_correct 
                    FROM question_options 
                    WHERE question_id IN ({$placeholders})";
    $stmt_options = $conn->prepare($sql_options);
    if ($stmt_options === false) die("Prepare failed (options): " . $conn->error);

    $stmt_options->bind_param($types, ...$question_ids);
    $stmt_options->execute();
    $result_options = $stmt_options->get_result();
    while ($option = $result_options->fetch_assoc()) {
        // Group options by their question_id in an array
        $options_by_question[$option['question_id']][] = $option;
    }
    $stmt_options->close();
}

// The back link needs to go up one level to find strand.php
$back_link = '/ALS_LMS/strand/strand.php?id=' . ($assessment['strand_id'] ?? 0) . '#assessments';
?>

<div class="container my-4">
    <div class="back-container">
        <a href="<?= htmlspecialchars($back_link) ?>" class="back-link <?= $back_link_class ?? '' ?>">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h3 class="mb-0"><?= htmlspecialchars($assessment['title']) ?></h3>
        </div>
        <div class="card-body p-4">
            <h5 class="card-title">Description/Instructions</h5>
            <div class="p-3 mb-4 bg-light border-none rounded">
                <?= $assessment['description'] ?>
            </div>
            <hr class="mb-4">

            <?php if (empty($questions)): ?>
                <!-- --- MODIFICATION: Only show this message for quiz and exam --- -->
                <?php if ($assessment['type'] === 'quiz' || $assessment['type'] === 'exam'): ?>
                    <p class="text-muted text-center p-4">No questions have been added to this assessment yet.</p>
                <?php endif; ?>
                <!-- --- End of Modification --- -->

            <?php else: ?>
                <form>
                    <?php foreach ($questions as $index => $q): ?>
                        <div class="mb-4">
                            <p class="fw-bold">Question <?= $index + 1 ?>:</p>
                            <div class="ps-2"><?= nl2br(htmlspecialchars($q['question_text'])) ?></div>
                            <div class="ms-3 mt-3">
                                <?php
                                // Get the options for this specific question
                                $options = $options_by_question[$q['id']] ?? [];

                                if ($q['question_type'] === 'multiple_choice' || $q['question_type'] === 'true_false'): ?>
                                    <?php foreach ($options as $opt): ?>
                                        <?php
                                        // Check if this option is the correct one
                                        $is_correct = ($opt['is_correct'] == 1);
                                        ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="q_<?= $q['id'] ?>"
                                                <?= $is_correct ? 'checked' : '' // Pre-check the correct answer 
                                                ?> disabled>
                                            <label class="form-check-label <?= $is_correct ? 'text-success fw-bold' : '' // Highlight the correct answer 
                                                                            ?>">
                                                <?= htmlspecialchars($opt['option_text']) ?>

                                            </label>
                                        </div>
                                    <?php endforeach; ?>

                                <?php elseif (in_array($q['question_type'], ['identification', 'short_answer', 'essay'])): ?>
                                    <?php
                                    // Find the correct answer text
                                    $correct_answer = '';
                                    foreach ($options as $opt) {
                                        if ($opt['is_correct'] == 1) {
                                            $correct_answer = $opt['option_text'];
                                            break; // Stop after finding the first correct answer
                                        }
                                    }
                                    ?>
                                    <label class="form-label small text-muted">Correct Answer:</label>
                                    <?php if ($q['question_type'] === 'essay'): ?>
                                        <textarea class="form-control" rows="4" readonly><?= htmlspecialchars($correct_answer) ?></textarea>
                                        <div class="form-text">(Note: Essay answers may vary. This is a sample correct answer, if provided.)</div>
                                    <?php else: ?>
                                        <input type="text" class="form-control text-success fw-bold" value="<?= htmlspecialchars($correct_answer) ?>" readonly>
                                    <?php endif; ?>

                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($index < count($questions) - 1): ?>
                            <hr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
$conn->close();
?>