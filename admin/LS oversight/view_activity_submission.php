<?php
session_start();
include '../../includes/db.php'; // Path from admin/LS-oversight/

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

// 1. Get and Validate Submission ID
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    echo "Invalid submission ID.";
    exit;
}
$submission_id = $_GET['id'];

// --- HANDLE GRADING FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
    $score = floatval($_POST['score']);
    $feedback = trim($_POST['feedback']);
    $status = 'graded';

    $update_sql = "UPDATE activity_submissions SET score = ?, feedback = ?, status = ? WHERE id = ?";
    $stmt_update = $conn->prepare($update_sql);
    $stmt_update->bind_param("dssi", $score, $feedback, $status, $submission_id);

    if ($stmt_update->execute()) {
        // Redirect to same page to refresh the view to "Summary" mode
        header("Location: view_activity_submission.php?id=" . $submission_id . "&success=1");
        exit;
    } else {
        $error_msg = "Error updating grade.";
    }
}

// Check for success message in URL
if (isset($_GET['success'])) {
    $success_msg = "Graded successfully.";
}

// 2. Fetch Submission Details
$sql_submission = "SELECT 
                    s.*, 
                    u.fname, u.lname, 
                    a.title AS assessment_title, 
                    a.description AS assessment_desc,
                    a.total_points,
                    a.type AS assessment_type,
                    a.strand_id
                   FROM activity_submissions s
                   JOIN users u ON s.student_id = u.id
                   JOIN assessments a ON s.assessment_id = a.id
                   WHERE s.id = ?";

$stmt = $conn->prepare($sql_submission);
$stmt->bind_param("i", $submission_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Submission not found.";
    exit;
}
$submission = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Submission - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/view_single_attempt.css">
</head>

<body class="bg-light">

    <header class="topbar sticky-top d-flex justify-content-between align-items-center px-4 py-3">
        <div class="d-flex align-items-left">
            <h1 class="title m-0">
                <div id="font">
                    <span>A</span><span>L</span><span>S</span> Learning Management System
                </div>
            </h1>
        </div>
        <div class="top-icons d-flex align-items-center gap-3">
            <img src="../../img/ALS.png" class="top-logo" alt="ALS Logo" />
            <img src="../../img/BNHS.jpg" class="top-logo" alt="BNHS Logo" />
        </div>
    </header>

    <main class="content">
        <div class="container py-4">

            <?php if (isset($success_msg)): ?>
                <div id="autoDismissAlert" class="alert alert-success floating-alert d-flex align-items-center justify-content-center gap-2" role="alert">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                    <span class="fw-medium"><?= $success_msg ?></span>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0">Submission Details</h2>
                    <span class="badge bg-secondary"><?= ucfirst($submission['assessment_type']) ?></span>
                </div>
                <div class="mb-3">
                    <a href="learning_strand_attempts.php?id=<?= $submission['strand_id'] ?>" class="back-link">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <h4 class="mb-3 text-center"><?= htmlspecialchars($submission['assessment_title']) ?></h4>
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <h6 class="text-muted">Student</h6>
                            <p class="fs-5"><?= htmlspecialchars($submission['fname'] . ' ' . $submission['lname']) ?></p>
                        </div>
                        <div class="col-md-4 text-center">
                            <h6 class="text-muted">Score</h6>
                            <p class="fs-5 fw-bold text-primary">
                                <?= $submission['score'] ?> / <?= $submission['total_points'] ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-center">
                            <h6 class="text-muted">Date Submitted</h6>
                            <p class="fs-5"><?= date_format(date_create($submission['submitted_at']), 'M d, Y, g:i A') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8">

                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white fw-bold">Instructions</div>
                        <div class="card-body">
                            <?= !empty($submission['assessment_desc']) ? $submission['assessment_desc'] : '<em class="text-muted">No instructions provided.</em>' ?>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white fw-bold">Student Submission</div>
                        <div class="card-body">

                            <?php if (!empty($submission['submission_text'])): ?>
                                <label class="form-label text-muted small text-uppercase fw-bold">Text Response:</label>
                                <div class="p-3 bg-light rounded mb-3 border">
                                    <?= nl2br(htmlspecialchars_decode($submission['submission_text'])) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($submission['submission_file'])): ?>
                                <label class="form-label text-muted small text-uppercase fw-bold">Attached File:</label>
                                <div class="d-flex align-items-center p-3 border rounded">
                                    <i class="bi bi-file-earmark-text fs-3 text-primary me-3"></i>
                                    <div>
                                        <h6 class="mb-0">
                                            <?= !empty($submission['original_filename']) ? htmlspecialchars($submission['original_filename']) : basename($submission['submission_file']) ?>
                                        </h6>
                                        <a href="../../<?= htmlspecialchars($submission['submission_file']) ?>" target="_blank" class="text-decoration-none small">
                                            View / Download File
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (empty($submission['submission_text']) && empty($submission['submission_file'])): ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> No content submitted.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">

                    <?php
                    // Determine if it is already graded
                    $is_graded = ($submission['status'] === 'graded');
                    ?>

                    <?php if ($is_graded): ?>
                        <div class="card shadow-sm border-0 sticky-top" style="top: 100px; z-index: 1;">
                            <div class="card-header bg-white">
                                Summary
                            </div>
                            <div class="card-body text-center">
                                <h5 class="card-title">Score</h5>
                                <p class="display-4 fw-light text-dark" id="current-score-display">
                                    <?= $submission['score'] ?>
                                </p>
                                <p class="fs-5 text-muted">out of <?= $submission['total_points'] ?> total points</p>

                                <hr>

                                <?php if (!empty($submission['feedback'])): ?>
                                    <div class="text-start mb-3">
                                        <label class="form-label text-muted small text-uppercase fw-bold">Teacher Feedback</label>
                                        <div class="p-3 bg-light rounded border border-light">
                                            <?= nl2br(htmlspecialchars($submission['feedback'])) ?>
                                        </div>
                                    </div>
                                    <hr>
                                <?php endif; ?>

                                <p class="small text-muted mb-0">Manually entered points will update the total score upon saving.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!$is_graded): ?>
                        <div class="card shadow-sm border-0 sticky-top" style="top: 100px; z-index: 1;">
                            <div class="card-body p-4">

                                <div class="d-flex align-items-center mb-4">
                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="bi bi-pencil-fill"></i>
                                    </div>
                                    <h5 class="fw-bold m-0">Grading</h5>
                                </div>

                                <form method="POST">
                                    <div class="mb-4">
                                        <label for="score" class="form-label text-uppercase text-muted small fw-bold">Score</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control form-control-lg border-end-0" id="score" name="score"
                                                value="<?= $submission['score'] ?>" max="<?= $submission['total_points'] ?>" min="0" placeholder="0" required>
                                            <span class="input-group-text bg-white border-start-0 text-muted">
                                                / <?= $submission['total_points'] ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label for="feedback" class="form-label text-uppercase text-muted small fw-bold">Feedback (Optional)</label>
                                        <textarea class="form-control" id="feedback" name="feedback" rows="6"
                                            placeholder="Write feedback for the student..." style="resize: none;"><?= htmlspecialchars($submission['feedback']) ?></textarea>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" name="grade_submission" class="btn btn-success rounded-pill py-2 fw-bold">
                                            Save Grade
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alertElement = document.getElementById('autoDismissAlert');

            if (alertElement) {
                // Disappear after 5 seconds (5000 ms)
                setTimeout(function() {
                    // Add a fade-out effect using CSS transition
                    alertElement.style.transition = "opacity 0.5s ease";
                    alertElement.style.opacity = "0";

                    // Remove from DOM after fade out completes
                    setTimeout(function() {
                        alertElement.remove();
                    }, 500);
                }, 5000);
            }
        });

        // (Keep your existing functions here like enableEditing/cancelEditing)
    </script>
</body>

</html>