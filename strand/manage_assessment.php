<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/header.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

// --- GET ASSESSMENT ID ---
$assessment_id = $_GET['id'] ?? $_GET['assessment_id'] ?? 0;
if (empty($assessment_id)) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Error: Assessment ID not provided.</div></div>";
    // Corrected footer path
    require_once '../includes/footer.php';
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

$back_link = '/ALS_LMS/strand/strand.php?id=' . ($assessment['strand_id'] ?? 0) . '#assessments';

// Determine back link class based on role (assuming session role is set)
$back_link_class = '';
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'teacher') {
        $back_link_class = 'teacher-back-link';
    } elseif ($_SESSION['role'] === 'student') {
        $back_link_class = 'student-back-link';
    }
}


if (!$assessment) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Assessment not found or you do not have permission.</div></div>";
    // Corrected footer path
    require_once '../includes/footer.php';
    exit;
}

// --- FETCH QUESTIONS DATA WITH ERROR CHECKING ---
$questions_result = null; // Initialize variable
$sql = "SELECT qb.id, qb.question_text, qb.question_type, qb.grading_type, qb.max_points
        FROM question_bank AS qb
        JOIN assessment_questions AS aq ON qb.id = aq.question_id
        WHERE aq.assessment_id = ?
        ORDER BY aq.id ASC"; // Order by the link table's ID to preserve insertion order

$questions_stmt = $conn->prepare($sql);
if ($questions_stmt === false) {
    die("Error preparing the question query. SQL Error: " . htmlspecialchars($conn->error));
}
$questions_stmt->bind_param("i", $assessment_id);
$questions_stmt->execute();
$questions_result = $questions_stmt->get_result();
// Fetch all questions into an array for easier looping later
$questions_array = $questions_result->fetch_all(MYSQLI_ASSOC);
$questions_stmt->close();

?>

<div class="container mt-4 mb-5">
    <div class="back-container mb-3">
        <a href="<?= htmlspecialchars($back_link) ?>" class="back-link <?= $back_link_class ?>">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light py-3 border-bottom d-flex justify-content-between align-items-center">
            <h3 class="mb-0">Manage: <?= htmlspecialchars($assessment['title']) ?></h3>
        </div>
        <div class="card-body p-4">
            <h5 class="card-title">Instructions/Description</h5>
            <div class="p-3 mb-4 bg-light">
                <?= !empty($assessment['description']) ? html_entity_decode($assessment['description']) : '<i class="text-muted">No description provided.</i>' ?>
            </div>

            <hr class="my-4">

            <div class="mb-4 d-flex justify-content-start align-items-center border-bottom pb-4">
                <h5 class="me-3 mb-0">Add Questions:</h5>
                <button type="button" class="btn text-dark" data-bs-toggle="collapse" href="#addNewQuestionForm" role="button" aria-expanded="false" aria-controls="addNewQuestionForm">
                    <i class="bi bi-plus-circle me-1"></i> Create New Question
                </button>
                <button type="button" class="btn text-dark" data-bs-toggle="modal" data-bs-target="#questionBankModal">
                    <i class="bi bi-journal-plus me-1"></i> Add from Question Bank
                </button>
            </div>
            <div class="collapse mt-4" id="addNewQuestionForm">
                <div class="card card-body border-light-subtle shadow-sm mb-4">
                    <h4 class="mb-3">Add New Question</h4>
                    <form id="add-question-form" action="../ajax/add_question.php" method="POST">
                        <input type="hidden" name="assessment_id" value="<?= $assessment['id'] ?>">

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="question_text" class="form-label fw-bold">Question:</label>
                                <textarea class="form-control" id="question_text" name="question_text" rows="3" required></textarea>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="question_type" class="form-label fw-bold">Question Type:</label>
                                <select class="form-select" id="question_type" name="question_type" required>
                                    <option value="multiple_choice" selected>Multiple Choice</option>
                                    <option value="true_false">True/False</option>
                                    <option value="identification">Identification</option>
                                    <option value="short_answer">Short Answer/Enumeration</option>
                                    <option value="essay">Essay</option>
                                </select>
                            </div>
                        </div>

                        <div id="answer-fields-container" class="mt-3">
                            <div id="multiple-choice-fields">
                                <label class="form-label fw-bold">Options (Select the correct answer):</label>
                                <?php for ($i = 0; $i < 4; $i++): ?>
                                    <div class="input-group mb-2">
                                        <div class="input-group-text">
                                            <input class="form-check-input mt-0 form-check-input-success" type="radio" name="correct_option" value="<?= $i ?>" <?= $i == 0 ? 'required' : '' ?>>
                                        </div>
                                        <input type="text" class="form-control" name="options[]" placeholder="Option <?= $i + 1 ?>" <?= $i < 2 ? 'required' : '' ?>>
                                    </div>
                                <?php endfor; ?>
                                <div class="form-text">At least two options are required.</div>
                            </div>
                            <div id="true-false-fields" style="display: none;">
                                <label class="form-label fw-bold">Options (Select the correct answer):</label>
                                <div class="input-group mb-2">
                                    <div class="input-group-text"><input class="form-check-input mt-0 form-check-input-success" type="radio" name="tf_correct_option" value="0" required></div>
                                    <input type="text" class="form-control" name="tf_options[]" value="True" readonly>
                                </div>
                                <div class="input-group">
                                    <div class="input-group-text"><input class="form-check-input mt-0 form-check-input-success" type="radio" name="tf_correct_option" value="1"></div>
                                    <input type="text" class="form-control" name="tf_options[]" value="False" readonly>
                                </div>
                            </div>
                            <div id="single-answer-fields" style="display: none;">
                                <label for="single_answer_text" class="form-label fw-bold">Correct Answer:</label>
                                <input type="text" class="form-control" id="single_answer_text" name="single_answer_text">
                                <div class="form-text">For Identification/Short Answer, this is used for automatic grading (case-insensitive). Leave blank or set grading to manual if needed.</div>
                            </div>
                            <div id="essay-fields" style="display: none;">
                                <p class="text-muted fst-italic">Essay questions require manual grading.</p>
                            </div>
                        </div>

                        <div id="gradingArea" class="mt-3">
                            <label class="form-label fw-bold">Grading:</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="grading_type" id="gradingAuto" value="automatic" checked>
                                <label class="form-check-label" for="gradingAuto">Automatic</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="grading_type" id="gradingManual" value="manual">
                                <label class="form-check-label" for="gradingManual">Manual</label>
                            </div>
                            <div id="pointsGroup" class="mt-2" style="display: none;">
                                <label for="maxPoints" class="form-label">Max Points:</label>
                                <input type="number" class="form-control w-25" id="maxPoints" name="max_points" value="1" min="1">
                            </div>
                            <div class="form-text" id="gradingHelpText">Automatic grading is based on the options/answer provided above.</div>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button type="button" class="btn btn-secondary me-2" data-bs-toggle="collapse" href="#addNewQuestionForm">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Question</button>
                        </div>
                    </form>
                </div>
            </div>
            <h4 class="mt-4 mb-3">Existing Questions</h4>
            <div id="question-list" class="mb-4 mt-3">
                <?php if (!empty($questions_array)): ?>
                    <?php foreach ($questions_array as $index => $question): ?>
                        <div class="bg-light rounded p-3 mb-2 question-card" data-question-id="<?= $question['id'] ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="fw-bold mb-1">
                                        Question <?= $index + 1 ?>:
                                        <span class="badge text-secondary fw-normal ms-2">
                                            <?= str_replace('_', ' ', ucfirst($question['question_type'])) ?>
                                        </span>
                                        <span class="badge text-success fw-normal ms-1">
                                            <?= ucfirst($question['grading_type']) ?> Grading (<?= $question['max_points'] ?>pt<?= $question['max_points'] > 1 ? 's' : '' ?>)
                                        </span>
                                    </p>
                                    <p class="mb-0 question-text-display"><?= nl2br(htmlspecialchars($question['question_text'])) ?></p>
                                </div>
                                <div class="actions-container flex-shrink-0 mt-2 d-flex">
                                    <button class="btn btn-action-icon edit edit-question-btn me-1" title="Edit Question" data-bs-toggle="modal" data-bs-target="#editQuestionModal" data-question-id="<?= $question['id'] ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button class="btn btn-action-icon delete delete-question-btn" title="Remove Question" data-bs-toggle="modal" data-bs-target="#deleteQuestionModal" data-question-id="<?= $question['id'] ?>" data-assessment-id="<?= $assessment_id ?>">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p id="no-questions-message" class="text-muted">No questions have been added to this assessment yet.</p>
    <?php endif; ?>
        </div>

    </div>
</div>
</div>
<div class="modal fade" id="questionBankModal" tabindex="-1" aria-labelledby="questionBankModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="questionBankModalLabel">Add Questions from Bank</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-8">
                        <input type="text" id="questionBankSearch" class="form-control" placeholder="Search questions by text...">
                    </div>
                    <div class="col-md-4">
                        <select id="questionBankTypeFilter" class="form-select">
                            <option value="">All Types</option>
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="true_false">True/False</option>
                            <option value="identification">Identification</option>
                            <option value="short_answer">Short Answer / Enumeration</option>
                            <option value="essay">Essay</option>
                        </select>
                    </div>
                </div>
                <hr>
                <form id="questionBankForm">
                    <input type="hidden" name="assessment_id" value="<?= $assessment_id ?>">
                    <div id="questionBankListContainer" style="max-height: 60vh; overflow-y: auto;">
                        <p class="text-center text-muted">Loading questions...</p>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="addSelectedQuestionsBtn">Add Selected Questions</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="editQuestionModal" tabindex="-1" aria-labelledby="editQuestionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editQuestionModalLabel">Edit Question</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="edit-question-loader" class="text-center">
                    <div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
                </div>
                <form id="edit-question-form" style="display:none;" action="../ajax/update_question.php" method="POST">
                    <input type="hidden" name="question_id" id="edit_question_id">
                    <input type="hidden" name="assessment_id" value="<?= $assessment_id ?>">

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="edit_question_text" class="form-label fw-bold">Question:</label>
                            <textarea class="form-control" id="edit_question_text" name="question_text" rows="3" required></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="edit_question_type" class="form-label fw-bold">Question Type:</label>
                            <select class="form-select" id="edit_question_type" name="question_type" disabled>
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="true_false">True/False</option>
                                <option value="identification">Identification</option>
                                <option value="short_answer">Short Answer / Enumeration</option>
                                <option value="essay">Essay</option>
                            </select>
                            <div class="form-text">Type cannot be changed.</div>
                        </div>
                    </div>

                    <div id="edit-answer-fields-container" class="mt-3">
                    </div>

                    <div id="editGradingArea" class="mt-3">
                        <label class="form-label fw-bold">Grading:</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="grading_type" id="editGradingAuto" value="automatic">
                            <label class="form-check-label" for="editGradingAuto">Automatic</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="grading_type" id="editGradingManual" value="manual">
                            <label class="form-check-label" for="editGradingManual">Manual</label>
                        </div>
                        <div id="editPointsGroup" class="mt-2" style="display: none;">
                            <label for="editMaxPoints" class="form-label">Max Points:</label>
                            <input type="number" class="form-control w-25" id="editMaxPoints" name="max_points" value="1" min="1">
                        </div>
                        <div class="form-text" id="editGradingHelpText">Adjust grading type and points.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="edit-question-form" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteQuestionModal" tabindex="-1" aria-labelledby="deleteQuestionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteQuestionModalLabel">Confirm Removal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to remove this question from this assessment?
                <p class="small text-muted mt-2">The question will remain in the Question Bank.</p>
            </div>
            <div class="modal-footer">
                <form id="remove-question-form" action="../ajax/delete_question.php" method="POST"> <input type="hidden" name="question_id" id="remove_question_id">
                    <input type="hidden" name="assessment_id" value="<?= $assessment_id ?>">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Remove</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Corrected footer path
require_once '../includes/footer.php';
$conn->close();
?>