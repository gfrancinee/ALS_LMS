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
$stmt = $conn->prepare("SELECT title, description, strand_id FROM assessments WHERE id = ?");
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

// --- Fetch All Questions and Their Options ---
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

$stmt_options = $conn->prepare("SELECT option_text FROM question_options WHERE question_id = ?");
foreach ($questions as $key => $question) {
    $stmt_options->bind_param("i", $question['id']);
    $stmt_options->execute();
    $options_result = $stmt_options->get_result();
    $options = $options_result->fetch_all(MYSQLI_ASSOC);
    $questions[$key]['options'] = $options;
}
$stmt_options->close();

// The back link needs to go up one level to find strand.php
$back_link = '../strand.php?id=' . $assessment['strand_id'] . '#assessments';
?>

<div class="container my-4">
    <div class="mb-3">
        <a href="<?= htmlspecialchars($back_link) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Strand
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h3 class="mb-0">Preview: <?= htmlspecialchars($assessment['title']) ?></h3>
        </div>
        <div class="card-body p-4">
            <h5 class="card-title">Instructions</h5>
            <div class="p-3 mb-4 bg-light border rounded">
                <?= $assessment['description'] ?>
            </div>
            <hr class="mb-4">

            <?php if (empty($questions)): ?>
                <p class="text-muted text-center p-4">No questions have been added to this assessment yet.</p>
            <?php else: ?>
                <form>
                    <?php foreach ($questions as $index => $q): ?>
                        <div class="mb-4">
                            <p class="fw-bold">Question <?= $index + 1 ?>:</p>
                            <div class="ps-2"><?= nl2br(htmlspecialchars($q['question_text'])) ?></div>
                            <div class="ms-3 mt-3">
                                <?php if ($q['question_type'] === 'multiple_choice' || $q['question_type'] === 'true_false'): ?>
                                    <?php foreach ($q['options'] as $opt): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="q_<?= $q['id'] ?>" disabled>
                                            <label class="form-check-label"><?= htmlspecialchars($opt['option_text']) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php elseif (in_array($q['question_type'], ['identification', 'short_answer'])): ?>
                                    <input type="text" class="form-control" placeholder="Your answer here..." readonly>
                                <?php elseif ($q['question_type'] === 'essay'): ?>
                                    <textarea class="form-control" rows="4" placeholder="Your answer here..." readonly></textarea>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($index < count($questions) - 1): ?>
                            <hr><?php endif; ?>
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