<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php'; // We need this for isStudentEnrolled

// 1. Check if user is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

// 2. Get Assessment ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Error: Assessment ID is required.');
}
$assessment_id = (int)$_GET['id'];
$student_id = (int)$_SESSION['user_id'];

// 3. Fetch Assessment Details (Activity/Assignment)
$stmt = $conn->prepare(
    "SELECT a.*, ls.strand_title 
     FROM assessments a
     JOIN learning_strands ls ON a.strand_id = ls.id
     WHERE a.id = ? AND (a.type = 'activity' OR a.type = 'assignment')"
);
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die('Error: Activity not found or it is not an activity/assignment.');
}
$assessment = $result->fetch_assoc();
$stmt->close();

// 4. Check if student is enrolled in this strand
if (!isStudentEnrolled($conn, $student_id, $assessment['strand_id'])) {
    die('Error: You are not enrolled in this learning strand.');
}

// 5. Check for Existing Submission
$submission = null;
$stmt_sub = $conn->prepare(
    // Note: SELECT * will automatically pick up 'original_filename' if you ran the SQL command
    "SELECT * FROM activity_submissions 
     WHERE assessment_id = ? AND student_id = ?"
);
$stmt_sub->bind_param("ii", $assessment_id, $student_id);
$stmt_sub->execute();
$result_sub = $stmt_sub->get_result();
if ($result_sub->num_rows > 0) {
    $submission = $result_sub->fetch_assoc();
}
$stmt_sub->close();

$is_open = !empty($assessment['is_open']);
$has_submitted = ($submission !== null);

$back_link = '/ALS_LMS/strand/strand.php?id=' . ($assessment['strand_id'] ?? 0) . '#assessments';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Activity: <?= htmlspecialchars($assessment['title']) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.tiny.cloud/1/7xskvh2bu8gio6eivhdb9jhxvgebwjuh180l3ct3sqza4vh5/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>

    <style>
        .back-link {
            color: #6c757d;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease-in-out;
        }

        .back-link:hover {
            color: blue;
        }

        .submission-file-link {
            display: inline-block;
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

        .btn-icon {
            background-color: transparent;
            border: none;
            color: #6c757d;
            /* Light icon color */
            transition: all 0.2s ease-in-out;

            /* Make it a circle */
            border-radius: 50%;
            width: 40px;
            /* Set fixed width */
            height: 40px;
            /* Set fixed height */

            /* Center the icon inside */
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .btn-icon-edit:hover {
            background-color: #198754;
            /* Bootstrap Green */
            color: #ffffff;
            /* White icon on hover */
        }

        .btn-icon-delete:hover {
            background-color: #dc3545;
            /* Bootstrap Red */
            color: #ffffff;
            /* White icon on hover */
        }
    </style>
</head>

<body class="bg-light">
    <div class="container my-4">
        <div class="d-flex justify-content-end mb-3">
            <a href="<?= htmlspecialchars($back_link) ?>" class="back-link">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h3 class="mb-0"><?= htmlspecialchars($assessment['title']) ?></h3>
                <span class="badge bg-light text-dark fw-normal ms-2"><?= ucfirst($assessment['type']) ?></span>
            </div>
            <div class="card-body p-4">
                <h5 class="card-title">Instructions/Description</h5>
                <div class="p-3 mb-4 bg-light rounded border border-0">
                    <?= !empty($assessment['description']) ? $assessment['description'] : '<p class="text-muted">No description provided.</p>' ?>
                </div>

                <hr class="my-4">

                <div class="submission-status-area">
                    <?php if ($has_submitted): ?>

                        <div id="submissionDisplayContainer">
                            <?php if ($submission['status'] === 'graded'): ?>
                                <div class="alert alert-primary" role="alert">
                                    <h4 class="alert-heading">Graded</h4>
                                    <p>Your submission has been graded by your teacher.</p>
                                    <hr>
                                    <p class="mb-0"><strong>Score:</strong> <?= htmlspecialchars($submission['score']) ?> / <?= htmlspecialchars($submission['total_points']) ?></p>
                                    <?php if (!empty($submission['feedback'])): ?>
                                        <p class="mt-2 mb-0"><strong>Feedback:</strong> <?= nl2br(htmlspecialchars($submission['feedback'])) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning border-0" role="alert">
                                    <h4 class="alert-heading">Submitted</h4>
                                    <p>You submitted this on <?= date('F j, Y, g:i a', strtotime($submission['submitted_at'])) ?>. It's awaiting grading.</p>
                                </div>
                            <?php endif; ?>

                            <div class="card card-body border-light-subtle shadow-sm mb-4">
                                <h5 class="mb-3">Your Submission</h5>
                                <?php if (!empty($submission['submission_text'])): ?>
                                    <label class="form-label fw-bold">Submitted Text:</label>
                                    <div class="p-3 mb-3 bg-white rounded border">
                                        <?= nl2br(htmlspecialchars($submission['submission_text'])) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($submission['submission_file'])): ?>
                                    <label class="form-label fw-bold">Submitted File:</label>
                                    <div>
                                        <?php
                                        // LOGIC UPDATE 1: Check for original filename
                                        $display_filename = !empty($submission['original_filename'])
                                            ? $submission['original_filename']
                                            : basename($submission['submission_file']);
                                        ?>
                                        <a href="../<?= htmlspecialchars($submission['submission_file']) ?>" target="_blank" class="submission-file-link">
                                            <i class="bi bi-paperclip me-2"></i><?= htmlspecialchars($display_filename) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <?php if (empty($submission['submission_text']) && empty($submission['submission_file'])): ?>
                                    <p class="text-muted">No details found for this submission.</p>
                                <?php endif; ?>

                                <?php if ($submission['status'] !== 'graded' && $is_open): ?>
                                    <hr class="my-3">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" id="editSubmissionBtn" class="btn btn-icon btn-icon-edit rounded-pill px-3" title="Edit Submission">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" id="deleteSubmissionBtn" class="btn btn-icon btn-icon-delete rounded-pill px-3" title="Delete Submission" data-submission-id="<?= $submission['id'] ?>">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif (!$is_open): ?>
                        <div class="alert alert-danger" role="alert">
                            <h4 class="alert-heading">Closed</h4>
                            <p>This activity is closed and is no longer accepting submissions.</p>
                        </div>

                    <?php else: // STATE 4: READY TO SUBMIT 
                    ?>
                        <div id="readyToSubmitContainer">
                            <button type="button" class="btn btn-primary rounded-pill px-3" id="showSubmissionFormBtn">
                                <i class="bi bi-plus-circle me-1"></i> Add Submission
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <?php
                // Show form if:
                // 1. We are in "Ready to Submit" state (State 4)
                // 2. We have submitted, it's not graded, and it's open (State 2)
                $can_show_form = (!$has_submitted && $is_open) || ($has_submitted && $submission['status'] !== 'graded' && $is_open);
                ?>
                <?php if ($can_show_form): ?>
                    <div id="submissionFormContainer" class="card card-body border-light-subtle shadow-sm mb-4" style="display: none;">
                        <h4 class="mb-3" id="submissionFormTitle">Add Submission</h4>
                        <form id="submissionForm" action="../ajax/submit_activity.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="assessment_id" value="<?= $assessment_id ?>">
                            <input type="hidden" name="student_id" value="<?= $student_id ?>">

                            <input type="hidden" name="action" id="formAction" value="add">
                            <input type="hidden" name="submission_id" id="submissionId" value="<?= $has_submitted ? $submission['id'] : '' ?>">

                            <div class="mb-3">
                                <label for="submissionFile" class="form-label">Upload a File</label>
                                <?php if ($has_submitted && !empty($submission['submission_file'])): ?>
                                    <div class="mb-2">
                                        Current file:
                                        <?php
                                        // LOGIC UPDATE 2: Check for original filename in the edit form too
                                        $edit_display_filename = !empty($submission['original_filename'])
                                            ? $submission['original_filename']
                                            : basename($submission['submission_file']);
                                        ?>
                                        <a href="../<?= htmlspecialchars($submission['submission_file']) ?>" target="_blank"><?= htmlspecialchars($edit_display_filename) ?></a>
                                        <div class="form-check form-check-inline ms-3">
                                            <input class="form-check-input" type="checkbox" id="removeFile" name="remove_file" value="1">
                                            <label class="form-check-label" for="removeFile">Remove file</label>
                                        </div>
                                    </div>
                                    <div class="form-text">Uploading a new file will replace the current one.</div>
                                <?php endif; ?>
                                <input class="form-control" type="file" id="submissionFile" name="submission_file">
                            </div>

                            <div class="mb-3">
                                <label for="submissionText" class="form-label">Or Write Your Submission</label>
                                <textarea class="form-control" id="submissionText" name="submission_text" rows="8" placeholder="You can type your answer or add a link here..."><?= $has_submitted ? htmlspecialchars($submission['submission_text']) : '' ?></textarea>
                            </div>

                            <div id="submissionError" class="alert alert-danger mt-3" style="display: none;"></div>

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <button type="button" id="cancelSubmitBtn" class="btn btn-secondary rounded-pill px-3">Cancel</button>
                                <button type="submit" id="submitBtn" class="btn btn-primary rounded-pill px-3">Submit</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Initialize TinyMCE
        tinymce.init({
            selector: '#submissionText', // Targets your textarea
            plugins: 'lists link image media table code help wordcount',
            toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright | bullist numlist outdent indent | link image media | code help',
            menubar: false,
            height: 300,
            setup: function(editor) {
                // This pre-fills the editor with existing content if the form is for editing
                // It runs when the editor is created (even if hidden)
                editor.on('init', function() {
                    const initialContent = document.getElementById('submissionText').value;
                    editor.setContent(initialContent);
                });
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Form elements
            const submissionFormContainer = document.getElementById('submissionFormContainer');
            const submissionForm = document.getElementById('submissionForm');
            const submitBtn = document.getElementById('submitBtn');
            const submissionError = document.getElementById('submissionError');
            const formAction = document.getElementById('formAction');
            const submissionFormTitle = document.getElementById('submissionFormTitle');

            // Display elements
            const submissionDisplayContainer = document.getElementById('submissionDisplayContainer');
            const readyToSubmitContainer = document.getElementById('readyToSubmitContainer');

            // Buttons
            const showSubmissionFormBtn = document.getElementById('showSubmissionFormBtn');
            const cancelSubmitBtn = document.getElementById('cancelSubmitBtn');
            const editSubmissionBtn = document.getElementById('editSubmissionBtn');
            const deleteSubmissionBtn = document.getElementById('deleteSubmissionBtn');

            // --- Show "Add" Form Logic ---
            if (showSubmissionFormBtn) {
                showSubmissionFormBtn.addEventListener('click', () => {
                    formAction.value = 'add';
                    submitBtn.textContent = 'Submit';
                    submissionFormTitle.textContent = 'Add Submission';

                    // Clear the TinyMCE editor
                    tinymce.get('submissionText').setContent('');

                    if (readyToSubmitContainer) readyToSubmitContainer.style.display = 'none';
                    if (submissionFormContainer) submissionFormContainer.style.display = 'block';
                });
            }

            // --- Show "Edit" Form Logic ---
            if (editSubmissionBtn) {
                editSubmissionBtn.addEventListener('click', () => {
                    formAction.value = 'edit';
                    submitBtn.textContent = 'Update Submission';
                    submissionFormTitle.textContent = 'Edit Your Submission';

                    // The 'setup' function already pre-filled the editor
                    // We just need to show it.

                    if (submissionDisplayContainer) submissionDisplayContainer.style.display = 'none';
                    if (submissionFormContainer) submissionFormContainer.style.display = 'block';
                });
            }

            // --- Hide Form / Cancel Logic ---
            if (cancelSubmitBtn) {
                cancelSubmitBtn.addEventListener('click', () => {
                    if (submissionFormContainer) submissionFormContainer.style.display = 'none';
                    if (submissionError) submissionError.style.display = 'none';

                    // Show the correct container again
                    if (formAction.value === 'edit') {
                        if (submissionDisplayContainer) submissionDisplayContainer.style.display = 'block';
                    } else {
                        if (readyToSubmitContainer) readyToSubmitContainer.style.display = 'block';
                    }
                });
            }

            // --- Form Submission (Handles BOTH Add and Edit) ---
            if (submissionForm) {
                submissionForm.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    // *** Call triggerSave() to update the underlying textarea ***
                    tinymce.triggerSave();

                    const fileInput = document.getElementById('submissionFile');
                    // Get the textarea, which now has the TinyMCE content
                    const textInput = document.getElementById('submissionText');
                    const removeFileCheckbox = document.getElementById('removeFile');
                    const hasExistingFile = document.querySelector('a[href*="../uploads/submissions/"]');
                    const isRemovingFile = removeFileCheckbox ? removeFileCheckbox.checked : false;

                    // *** Use textInput.value (which was updated by triggerSave) ***
                    if (fileInput.files.length === 0 && textInput.value.trim() === '' && (!hasExistingFile || isRemovingFile)) {
                        submissionError.textContent = 'Please upload a file or write a submission.';
                        submissionError.style.display = 'block';
                        return;
                    }

                    submitBtn.disabled = true;
                    submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ${formAction.value === 'edit' ? 'Updating...' : 'Submitting...'}`;
                    submissionError.style.display = 'none';

                    // This FormData will now correctly include the TinyMCE content
                    const formData = new FormData(submissionForm);

                    try {
                        const response = await fetch('../ajax/submit_activity.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success) {
                            window.location.reload();
                        } else {
                            submissionError.textContent = result.error || 'An unknown error occurred.';
                            submissionError.style.display = 'block';
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = formAction.value === 'edit' ? 'Update Submission' : 'Submit';
                        }
                    } catch (error) {
                        console.error('Submission error:', error);
                        submissionError.textContent = 'A network error occurred. Please try again.';
                        submissionError.style.display = 'block';
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = formAction.value === 'edit' ? 'Update Submission' : 'Submit';
                    }
                });
            }

            // --- Delete Submission Logic ---
            if (deleteSubmissionBtn) {
                deleteSubmissionBtn.addEventListener('click', async () => {
                    if (!confirm('Are you sure you want to delete this submission? This action cannot be undone.')) {
                        return;
                    }
                    const submissionId = deleteSubmissionBtn.dataset.submissionId;
                    try {
                        const formData = new FormData();
                        formData.append('submission_id', submissionId);
                        const response = await fetch('../ajax/delete_activity_submission.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success) {
                            window.location.reload();
                        } else {
                            alert('Error: ' + (result.error || 'Could not delete submission.'));
                        }
                    } catch (error) {
                        console.error('Delete error:', error);
                        alert('A network error occurred. Please try again.');
                    }
                });
            }
        });
    </script>
</body>

</html>