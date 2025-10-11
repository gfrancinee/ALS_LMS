<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/header.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

// --- GET ASSESSMENT ID ---
$assessment_id = $_GET['id'] ?? 0;
if (empty($assessment_id)) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Error: Assessment ID not provided.</div></div>";
    require_once 'includes/footer.php';
    exit;
}

$teacher_id = $_SESSION['user_id'];

// --- FETCH ASSESSMENT DATA ---
$stmt = $conn->prepare("SELECT * FROM assessments WHERE id = ? AND teacher_id = ?");
if ($stmt === false) {
    die("Error preparing assessment query: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("ii", $assessment_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$assessment = $result->fetch_assoc();
$stmt->close();

// Note: I also corrected the path to remove the extra '/strand/' folder
$back_link = '/ALS_LMS/strand/strand.php?id=' . ($assessment['strand_id'] ?? 0) . '#assessments';

if (!$assessment) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Assessment not found or you do not have permission.</div></div>";
    require_once 'includes/footer.php';
    exit;
}

// --- FETCH QUESTIONS DATA WITH ERROR CHECKING ---
$questions_result = null; // Initialize variable
$sql = "SELECT qb.id, qb.question_text, qb.question_type 
        FROM question_bank AS qb 
        JOIN assessment_questions AS aq ON qb.id = aq.question_id 
        WHERE aq.assessment_id = ? 
        ORDER BY aq.question_id ASC";

$questions_stmt = $conn->prepare($sql);
// THIS IS THE NEW ERROR CHECK
if ($questions_stmt === false) {
    die("Error preparing the question query. Please check your table and column names. SQL Error: " . htmlspecialchars($conn->error));
}
$questions_stmt->bind_param("i", $assessment_id);
$questions_stmt->execute();
$questions_result = $questions_stmt->get_result();
$questions_stmt->close();

?>

<div class="container">
    <div class="back-container">
        <a href="<?= htmlspecialchars($back_link) ?>" class="back-link <?= $back_link_class ?>">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-lightwhite py-3">
            <h3>Manage: <?= htmlspecialchars($assessment['title']) ?></h3>
        </div>
        <div class="card-body">
            <h5 class="card-title">Instructions / Description</h5>
            <div class="p-3 mb-4 bg-light border rounded">
                <?= $assessment['description'] ?>
            </div>
            <hr>
            <h4 class="mt-4">Existing Questions</h4>
            <div id="question-list" class="mb-4">
                <?php if ($questions_result && $questions_result->num_rows > 0): ?>
                    <?php $q_num = 1;
                    while ($question = $questions_result->fetch_assoc()): ?>
                        <div class="card mb-2">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="fw-bold mb-1">
                                            Question <?= $q_num++ ?>:
                                            <span class="badge bg-secondary fw-normal ms-2">
                                                <?= str_replace('_', ' ', ucfirst($question['question_type'])) ?>
                                            </span>
                                        </p>
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($question['question_text'])) ?></p>
                                    </div>
                                    <div class="actions-container">
                                        <button class="btn btn-action-icon edit" title="Edit Question">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="btn btn-action-icon delete" title="Delete Question" data-bs-toggle="modal" data-bs-target="#deleteQuestionModal" data-question-id="<?= $question['id'] ?>">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-muted">No questions have been added yet.</p>
                <?php endif; ?>
            </div>

            <hr>

            <h4 class="mt-4">Add New Question</h4>
            <form id="add-question-form">
                <input type="hidden" name="assessment_id" value="<?= $assessment['id'] ?>">

                <div class="row">
                    <div class="col-md-8">
                        <label for="question_text" class="form-label fw-bold">Question:</label>
                        <textarea class="form-control" id="question_text" name="question_text" rows="3" required></textarea>
                    </div>
                    <div class="col-md-4">
                        <label for="question_type" class="form-label fw-bold">Question Type:</label>
                        <select class="form-select" id="question_type" name="question_type">
                            <option value="multiple_choice" selected>Multiple Choice</option>
                            <option value="true_false">True/False</option>
                            <option value="identification">Identification</option>
                            <option value="short_answer">Short Answer / Enumeration</option>
                            <option value="essay">Essay</option>
                        </select>
                    </div>
                </div>

                <div id="answer-fields-container" class="mt-3">

                    <div id="multiple-choice-fields">
                        <label class="form-label fw-bold">Options (Select the correct answer):</label>
                        <div class="input-group mb-2">
                            <div class="input-group-text"><input class="form-check-input mt-0" type="radio" name="correct_option" value="0" required></div><input type="text" class="form-control" name="options[]" required>
                        </div>
                        <div class="input-group mb-2">
                            <div class="input-group-text"><input class="form-check-input mt-0" type="radio" name="correct_option" value="1"></div><input type="text" class="form-control" name="options[]" required>
                        </div>
                        <div class="input-group mb-2">
                            <div class="input-group-text"><input class="form-check-input mt-0" type="radio" name="correct_option" value="2"></div><input type="text" class="form-control" name="options[]">
                        </div>
                        <div class="input-group">
                            <div class="input-group-text"><input class="form-check-input mt-0" type="radio" name="correct_option" value="3"></div><input type="text" class="form-control" name="options[]">
                        </div>
                    </div>

                    <div id="true-false-fields" style="display: none;">
                        <label class="form-label fw-bold">Options (Select the correct answer):</label>
                        <div class="input-group mb-2">
                            <div class="input-group-text"><input class="form-check-input mt-0" type="radio" name="tf_correct_option" value="0" required></div><input type="text" class="form-control" name="tf_options[]" value="True">
                        </div>
                        <div class="input-group">
                            <div class="input-group-text"><input class="form-check-input mt-0" type="radio" name="tf_correct_option" value="1"></div><input type="text" class="form-control" name="tf_options[]" value="False">
                        </div>
                    </div>

                    <div id="single-answer-fields" style="display: none;">
                        <label for="single_answer_text" class="form-label fw-bold">Correct Answer:</label>
                        <input type="text" class="form-control" id="single_answer_text" name="single_answer_text">
                    </div>

                </div>

                <button type="submit" class="btn btn-primary mt-3">Add Question</button>
            </form>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
$conn->close();
?>