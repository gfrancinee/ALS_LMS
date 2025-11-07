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
<!-- *** START: HTML Header added *** -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Title will use the assessment's real title -->
    <title>View Activity: <?= htmlspecialchars($assessment['title']) ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

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
    </style>
</head>

<body class="bg-light">
    <!-- *** END: HTML Header added *** -->


    <!-- Main Content (This is the original PHP content) -->
    <div class="container my-4">
        <div class="d-flex justify-content-end mb-3">
            <a href="<?= htmlspecialchars($back_link) ?>" class="back-link <?= $back_link_class ?>">
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
                <div class="p-3 mb-4 bg-light rounded border">
                    <?= !empty($assessment['description']) ? $assessment['description'] : '<p class="text-muted">No description provided.</p>' ?>
                </div>

                <hr class="my-4">

                <!-- Submission Status Area -->
                <div class="submission-status-area">
                    <?php if ($has_submitted): ?>
                        <?php if ($submission['status'] === 'graded'): ?>
                            <!-- STATE 1: GRADED -->
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
                            <!-- STATE 2: SUBMITTED, NOT GRADED -->
                            <div class="alert alert-warning" role="alert">
                                <h4 class="alert-heading">Submitted</h4>
                                <p>You have submitted this activity on <?= date('F j, Y, g:i a', strtotime($submission['submitted_at'])) ?>. It is now waiting for your teacher to grade it.</p>
                            </div>
                        <?php endif; ?>

                    <?php elseif (!$is_open): ?>
                        <!-- STATE 3: CLOSED and NOT SUBMITTED -->
                        <div class="alert alert-danger" role="alert">
                            <h4 class="alert-heading">Closed</h4>
                            <p>This activity is closed and is no longer accepting submissions.</p>
                        </div>

                    <?php else: // STATE 4: READY TO SUBMIT 
                    ?>
                        <!-- This div contains just the button, which will be hidden by JavaScript when clicked -->
                        <div id="readyToSubmitContainer">
                            <button type="button" class="btn btn-primary rounded-pill px-3" id="showSubmissionFormBtn">
                                <i class="bi bi-plus-circle me-1"></i> Add Submission
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Submission Form (Hidden by default, shown on button click) -->
                <!-- This block is only shown if the user is in "STATE 4" -->
                <?php if (!$has_submitted && $is_open): ?>
                    <div id="submissionFormContainer" class="card card-body border-light-subtle shadow-sm mb-4" style="display: none;">
                        <h4 class="mb-3">Add Submission for <?= htmlspecialchars($assessment['title']) ?></h4>
                        <form id="submissionForm" action="../ajax/submit_activity.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="assessment_id" value="<?= $assessment_id ?>">
                            <input type="hidden" name="student_id" value="<?= $student_id ?>">

                            <div class="mb-3">
                                <label for="submissionFile" class="form-label">Upload a File (Optional)</label>
                                <input class="form-control" type="file" id="submissionFile" name="submission_file">
                                <div class="form-text">You can upload a PDF, Word document, image, etc.</div>
                            </div>

                            <div class="mb-3">
                                <label for="submissionText" class="form-label">Or Write Your Submission (Optional)</label>
                                <textarea class="form-control" id="submissionText" name="submission_text" rows="8" placeholder="You can type your answer or add a link here..."></textarea>
                            </div>

                            <div id="submissionError" class="alert alert-danger mt-3" style="display: none;"></div>

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <button type="button" id="cancelSubmitBtn" class="btn btn-secondary rounded-pill px-3">Cancel</button>
                                <button type="submit" id="submitBtn" class="btn btn-primary rounded-pill px-3">Submit</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
                <!-- END: Submission Form -->

            </div>
        </div>
    </div>

    <!-- *** START: HTML Footer added *** -->
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript for handling submission AND form visibility -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const submissionForm = document.getElementById('submissionForm');
            const submitBtn = document.getElementById('submitBtn');
            const submissionError = document.getElementById('submissionError');

            // Get buttons for toggling form visibility
            const showSubmissionFormBtn = document.getElementById('showSubmissionFormBtn');
            const cancelSubmitBtn = document.getElementById('cancelSubmitBtn');
            const readyToSubmitContainer = document.getElementById('readyToSubmitContainer'); // The button's container
            const submissionFormContainer = document.getElementById('submissionFormContainer');

            // Show Form Logic
            if (showSubmissionFormBtn) {
                showSubmissionFormBtn.addEventListener('click', () => {
                    if (readyToSubmitContainer) readyToSubmitContainer.style.display = 'none'; // Hide button
                    if (submissionFormContainer) submissionFormContainer.style.display = 'block'; // Show form
                });
            }

            // Hide Form Logic
            if (cancelSubmitBtn) {
                cancelSubmitBtn.addEventListener('click', () => {
                    if (readyToSubmitContainer) readyToSubmitContainer.style.display = 'block'; // Show button
                    if (submissionFormContainer) submissionFormContainer.style.display = 'none'; // Hide form
                    if (submissionError) submissionError.style.display = 'none';
                });
            }

            // Form submission logic
            if (submissionForm) {
                submissionForm.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    const fileInput = document.getElementById('submissionFile');
                    const textInput = document.getElementById('submissionText');

                    if (fileInput.files.length === 0 && textInput.value.trim() === '') {
                        submissionError.textContent = 'Please upload a file or write a submission.';
                        submissionError.style.display = 'block';
                        return;
                    }

                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
                    submissionError.style.display = 'none';

                    const formData = new FormData(submissionForm);

                    try {
                        // This is the REAL submit action
                        const response = await fetch('../ajax/submit_activity.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json();

                        if (result.success) {
                            // Success! Reload the page to show the "Submitted" status
                            window.location.reload();
                        } else {
                            // Show error from server
                            submissionError.textContent = result.error || 'An unknown error occurred.';
                            submissionError.style.display = 'block';
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = 'Submit';
                        }
                    } catch (error) {
                        console.error('Submission error:', error);
                        submissionError.textContent = 'A network error occurred. Please try again.';
                        submissionError.style.display = 'block';
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Submit';
                    }
                });
            }
        });
    </script>
</body>

</html>
<!-- *** END: HTML Footer added *** -->