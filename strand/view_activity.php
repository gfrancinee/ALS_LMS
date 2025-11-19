<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// 1. Check if user is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

// 2. Get Assessment ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Error: Assessment ID is required.');
}
$assessment_id = (int)$_GET['id'];
$student_id = (int)$_SESSION['user_id'];

// 3. Fetch Assessment Details
$stmt = $conn->prepare(
    "SELECT a.*, ls.strand_title 
     FROM assessments a
     JOIN learning_strands ls ON a.strand_id = ls.id
     WHERE a.id = ? AND (a.type = 'activity' OR a.type = 'assignment' OR a.type = 'project')"
);
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die('Error: Activity not found.');
}
$assessment = $result->fetch_assoc();
$stmt->close();

// 4. Check Enrollment
if (!isStudentEnrolled($conn, $student_id, $assessment['strand_id'])) {
    die('Error: You are not enrolled in this learning strand.');
}

// 5. Check Submission
$submission = null;
$stmt_sub = $conn->prepare(
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
    <script src="https://cdn.tiny.cloud/1/9936xft73irpm4pgttgwipsbj5lt3506l1lcwr7g518gp4h1/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>

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

        /* Floating Modern Alert CSS */
        .floating-alert {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            min-width: 350px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            border-radius: 50px;
            border: none;
            text-align: center;
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideDown {
            from {
                top: -100px;
                opacity: 0;
            }

            to {
                top: 20px;
                opacity: 1;
            }
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
            transition: all 0.2s ease-in-out;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .btn-icon-edit:hover {
            background-color: #198754;
            color: #ffffff;
        }

        .btn-icon-delete:hover {
            background-color: #dc3545;
            color: #ffffff;
        }
    </style>
</head>

<body class="bg-light">

    <?php if (isset($_GET['submitted'])): ?>
        <div id="autoDismissAlert" class="alert alert-success floating-alert d-flex align-items-center justify-content-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <span class="fw-medium">Submitted successfully!</span>
        </div>
    <?php endif; ?>

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

                            <div class="card card-body border-light-subtle shadow-sm mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Your Submission</h5>

                                    <?php if ($submission['status'] === 'graded'): ?>
                                        <span class="badge text-primary p-2">
                                            Score: <?= htmlspecialchars($submission['score']) ?> / <?= htmlspecialchars($submission['total_points']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge text-success p-2">
                                            <i class="bi bi-check2-circle me-1"></i>Submitted
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($submission['status'] === 'graded' && !empty($submission['feedback'])): ?>
                                    <div class="alert alert-light border-0 mb-3">
                                        <strong><i class="bi bi-chat-quote-fill me-2 text-muted"></i>Teacher's Feedback:</strong>
                                        <div class="mt-1 ms-4">
                                            <?= nl2br(htmlspecialchars($submission['feedback'])) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

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

                    <?php else: ?>
                        <div id="readyToSubmitContainer">
                            <button type="button" class="btn btn-primary rounded-pill px-3" id="showSubmissionFormBtn">
                                <i class="bi bi-plus-circle me-1"></i> Add Submission
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <?php $can_show_form = (!$has_submitted && $is_open) || ($has_submitted && $submission['status'] !== 'graded' && $is_open); ?>
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
                                <?php endif; ?>
                                <input class="form-control" type="file" id="submissionFile" name="submission_file">
                            </div>

                            <div class="mb-3">
                                <label for="submissionText" class="form-label">Or Write Your Submission</label>
                                <textarea class="form-control" id="submissionText" name="submission_text" rows="8"><?= $has_submitted ? htmlspecialchars($submission['submission_text']) : '' ?></textarea>
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
        tinymce.init({
            selector: '#submissionText',
            plugins: 'lists link image media table code help wordcount',
            toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright | bullist numlist outdent indent | link image media | code help',
            menubar: false,
            height: 300,
            setup: function(editor) {
                editor.on('init', function() {
                    const initialContent = document.getElementById('submissionText').value;
                    editor.setContent(initialContent);
                });
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            // --- Auto Dismiss Alert Logic ---
            const alertElement = document.getElementById('autoDismissAlert');
            if (alertElement) {
                setTimeout(function() {
                    alertElement.style.transition = "opacity 0.5s ease";
                    alertElement.style.opacity = "0";
                    setTimeout(function() {
                        alertElement.remove();
                    }, 500);
                }, 5000);
            }

            const submissionFormContainer = document.getElementById('submissionFormContainer');
            const submissionForm = document.getElementById('submissionForm');
            const submitBtn = document.getElementById('submitBtn');
            const submissionError = document.getElementById('submissionError');
            const formAction = document.getElementById('formAction');
            const submissionFormTitle = document.getElementById('submissionFormTitle');
            const submissionDisplayContainer = document.getElementById('submissionDisplayContainer');
            const readyToSubmitContainer = document.getElementById('readyToSubmitContainer');
            const showSubmissionFormBtn = document.getElementById('showSubmissionFormBtn');
            const cancelSubmitBtn = document.getElementById('cancelSubmitBtn');
            const editSubmissionBtn = document.getElementById('editSubmissionBtn');
            const deleteSubmissionBtn = document.getElementById('deleteSubmissionBtn');

            if (showSubmissionFormBtn) {
                showSubmissionFormBtn.addEventListener('click', () => {
                    formAction.value = 'add';
                    submitBtn.textContent = 'Submit';
                    submissionFormTitle.textContent = 'Add Submission';
                    tinymce.get('submissionText').setContent('');
                    if (readyToSubmitContainer) readyToSubmitContainer.style.display = 'none';
                    if (submissionFormContainer) submissionFormContainer.style.display = 'block';
                });
            }

            if (editSubmissionBtn) {
                editSubmissionBtn.addEventListener('click', () => {
                    formAction.value = 'edit';
                    submitBtn.textContent = 'Update Submission';
                    submissionFormTitle.textContent = 'Edit Your Submission';
                    if (submissionDisplayContainer) submissionDisplayContainer.style.display = 'none';
                    if (submissionFormContainer) submissionFormContainer.style.display = 'block';
                });
            }

            if (cancelSubmitBtn) {
                cancelSubmitBtn.addEventListener('click', () => {
                    if (submissionFormContainer) submissionFormContainer.style.display = 'none';
                    if (submissionError) submissionError.style.display = 'none';
                    if (formAction.value === 'edit') {
                        if (submissionDisplayContainer) submissionDisplayContainer.style.display = 'block';
                    } else {
                        if (readyToSubmitContainer) readyToSubmitContainer.style.display = 'block';
                    }
                });
            }

            if (submissionForm) {
                submissionForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    tinymce.triggerSave();
                    const fileInput = document.getElementById('submissionFile');
                    const textInput = document.getElementById('submissionText');
                    const removeFileCheckbox = document.getElementById('removeFile');
                    const hasExistingFile = document.querySelector('a[href*="../uploads/submissions/"]');
                    const isRemovingFile = removeFileCheckbox ? removeFileCheckbox.checked : false;

                    if (fileInput.files.length === 0 && textInput.value.trim() === '' && (!hasExistingFile || isRemovingFile)) {
                        submissionError.textContent = 'Please upload a file or write a submission.';
                        submissionError.style.display = 'block';
                        return;
                    }

                    submitBtn.disabled = true;
                    submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ${formAction.value === 'edit' ? 'Updating...' : 'Submitting...'}`;
                    submissionError.style.display = 'none';

                    const formData = new FormData(submissionForm);

                    try {
                        const response = await fetch('../ajax/submit_activity.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success) {
                            // *** UPDATED RELOAD LOGIC ***
                            // Reloads page with ?submitted=1 to trigger the floating alert
                            const url = new URL(window.location);
                            url.searchParams.set('submitted', '1');
                            window.location = url;
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

            if (deleteSubmissionBtn) {
                deleteSubmissionBtn.addEventListener('click', async () => {
                    if (!confirm('Are you sure you want to delete this submission?')) return;
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