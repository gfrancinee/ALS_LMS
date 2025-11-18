<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// 1. Check if user is a TEACHER
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}
$teacher_id = (int)$_SESSION['user_id'];

// 2. Get Submission ID from URL
if (!isset($_GET['submission_id']) || empty($_GET['submission_id'])) {
    die('Error: Submission ID is required.');
}
$submission_id = (int)$_GET['submission_id'];

// 3. Fetch Submission Details (and verify teacher owns it)
// Note: s.* will automatically fetch 'original_filename' if you added the column
$stmt = $conn->prepare(
    "SELECT s.*, u.fname, u.lname, a.title as assessment_title, a.strand_id, a.total_points as assessment_total_points
     FROM activity_submissions s
     JOIN users u ON s.student_id = u.id
     JOIN assessments a ON s.assessment_id = a.id
     WHERE s.id = ? AND a.teacher_id = ?"
);
$stmt->bind_param("ii", $submission_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die('Error: Submission not found or you do not have permission.');
}
$submission = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Set the back link
$back_link = 'view_submissions.php?assessment_id=' . ($submission['assessment_id'] ?? 0);

// --- Include Header ---
$page_title = "Grade Submission";
require_once '../includes/header.php';
?>
<style>
    body {
        background-color: #f8f9fa;
    }

    .submission-file-link {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        background-color: #e9ecef;
        border: none;
        border-radius: 0.25rem;
        text-decoration: none;
        color: #212529;
        font-weight: 500;
    }

    .submission-file-link:hover {
        background-color: #dde2e6;
    }
</style>

<div class="container my-5">
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="back-container">
                <a href="<?= htmlspecialchars($back_link) ?>" class="back-link <?= $back_link_class ?>"> <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
            <div>
                <h2 class="mb-0">Grade Submission</h2>
                <p class="text-muted mb-0">
                    For: <strong><?= htmlspecialchars($submission['assessment_title']) ?></strong>
                </p>
            </div>

        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?= htmlspecialchars($submission['lname'] . ', ' . $submission['fname']) ?></h5>
                <span class="text-muted small">Submitted on: <?= date('F j, Y, g:i a', strtotime($submission['submitted_at'])) ?></span>
            </div>
            <div class="card-body p-4">

                <?php if (!empty($submission['submission_text'])): ?>
                    <h6 class="text-muted">Submitted Text:</h6>
                    <div class="p-3 mb-3 bg-light rounded border" style="max-height: 400px; overflow-y: auto;">
                        <?= html_entity_decode($submission['submission_text']) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($submission['submission_file'])): ?>
                    <h6 class="text-muted">Submitted File:</h6>
                    <?php
                    // UPDATE: Check for original filename
                    $display_filename = !empty($submission['original_filename'])
                        ? $submission['original_filename']
                        : basename($submission['submission_file']);
                    ?>
                    <a href="../<?= htmlspecialchars($submission['submission_file']) ?>" target="_blank" class="submission-file-link">
                        <i class="bi bi-paperclip me-2"></i><?= htmlspecialchars($display_filename) ?>
                    </a>
                <?php endif; ?>

                <hr class="my-4">

                <div id="grade-view" <?= $submission['status'] === 'graded' ? '' : 'style="display: none;"' ?>>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Score</label>
                            <input type="text" class="form-control-plaintext fs-5" value="<?= htmlspecialchars($submission['score']) ?> / <?= (int)$submission['total_points'] ?>" readonly>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label fw-bold">Feedback</label>
                            <div class="p-2 bg-light border rounded border-light mb-3">
                                <?= !empty($submission['feedback']) ? nl2br(htmlspecialchars($submission['feedback'])) : '<em class="text-muted">No feedback provided.</em>' ?>
                            </div>
                            <button type="button" class="btn text-primary btn-sm me-3 btn-pill-hover edit-grade-btn">Edit Grade</button>
                        </div>
                    </div>
                </div>

                <form id="grade-form" action="../ajax/grade_activity.php" method="POST" <?= $submission['status'] === 'graded' ? 'style="display: none;"' : '' ?>>
                    <input type="hidden" name="submission_id" value="<?= $submission['id'] ?>">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="score" class="form-label fw-bold">Score</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="score" name="score" value="<?= htmlspecialchars($submission['score']) ?>" required>
                                <span class="input-group-text">/ <?= (int)$submission['total_points'] ?></span>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <label for="feedback" class="form-label fw-bold">Feedback (Optional)</label>
                            <textarea class="form-control" id="feedback" name="feedback" rows="3" placeholder="Provide feedback to the student..."><?= htmlspecialchars($submission['feedback']) ?></textarea>
                        </div>
                        <div class="col-12 text-end">
                            <?php if ($submission['status'] === 'graded'): ?>
                                <button type="button" class="btn btn-secondary rounded-pill px-3 cancel-edit-btn">Cancel</button>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-success rounded-pill px-3">
                                <i class="bi bi-check-circle me-1"></i> <?= $submission['status'] === 'graded' ? 'Update Grade' : 'Save Grade' ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const gradeForm = document.getElementById('grade-form');
        const gradeView = document.getElementById('grade-view');
        const editGradeBtn = document.querySelector('.edit-grade-btn');
        const cancelEditBtn = document.querySelector('.cancel-edit-btn');

        // Handle "Edit Grade" Button
        if (editGradeBtn) {
            editGradeBtn.addEventListener('click', function() {
                gradeView.style.display = 'none';
                gradeForm.style.display = 'block';
            });
        }

        // Handle "Cancel Edit" Button
        if (cancelEditBtn) {
            cancelEditBtn.addEventListener('click', function() {
                gradeForm.style.display = 'none';
                gradeView.style.display = 'block';
            });
        }

        // Handle Grading Form Submission
        if (gradeForm) {
            gradeForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(gradeForm);
                const submitButton = gradeForm.querySelector('button[type="submit"]');

                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

                try {
                    const response = await fetch(gradeForm.action, {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        window.location.reload(); // Reload to show the updated grade
                    } else {
                        alert('Error: ' + (result.error || 'Could not save grade.'));
                        submitButton.disabled = false;
                        submitButton.innerHTML = '<?= $submission['status'] === 'graded' ? 'Update Grade' : 'Save Grade' ?>';
                    }
                } catch (error) {
                    console.error('Grading error:', error);
                    alert('A network error occurred. Please try again.');
                    submitButton.disabled = false;
                    submitButton.innerHTML = '<?= $submission['status'] === 'graded' ? 'Update Grade' : 'Save Grade' ?>';
                }
            });
        }
    });
</script>

<?php
require_once '../includes/footer.php';
?>