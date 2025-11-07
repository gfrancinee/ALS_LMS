<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/header.php';

// --- SECURITY CHECK & DATA FETCHING (No changes here) ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}
$assessment_id = $_GET['id'] ?? 0;
$student_id = $_SESSION['user_id'];
if (!$assessment_id) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Assessment not found.</div></div>";
    require_once '../includes/footer.php';
    exit;
}
$sql = "
    SELECT 
        a.id, a.title, a.description, a.duration_minutes, a.max_attempts, a.is_open, a.strand_id,
        (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.assessment_id = a.id AND qa.student_id = ?) as attempts_taken
    FROM assessments a
    WHERE a.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $student_id, $assessment_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$assessment) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Assessment could not be loaded.</div></div>";
    require_once '../includes/footer.php';
    exit;
}
$attempts_left = $assessment['max_attempts'] - $assessment['attempts_taken'];
$is_available = !empty($assessment['is_open']) && $attempts_left > 0;
$back_link = '/ALS_LMS/strand/strand.php?id=' . ($assessment['strand_id'] ?? 0) . '#assessments';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/ALS_LMS/strand/css/manage_assessment.css">
</head>

<style>
    /* This targets the "Back" link and changes its color to blue only on hover */
    .back-link:hover {
        color: blue !important;
        /* A standard Bootstrap blue */
    }
</style>

<body class="bg-light">
    <div class="container my-4">
        <div class="back-container">
            <a href="<?= htmlspecialchars($back_link) ?>" class="back-link <?= $back_link_class ?>">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-0">
                <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded">
                    <h3 class="mb-0"><?= htmlspecialchars($assessment['title']) ?></h3>
                    <?php if (!empty($assessment['is_open'])): ?>
                        <span class="text-success fw-bold fs-6">Open</span>
                    <?php else: ?>
                        <span class="text-danger fw-bold fs-6">Closed</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body p-3">
                <div class="bg-light p-3 mt-0 rounded">
                    <?= $assessment['description'] ?>
                </div>

                <div class="p-3 mt-3">
                    <div class="row mt-2 bg-light p-3 rounded">
                        <div class="col-md-4">
                            <p class="mb-1"><strong class="d-block text-dark">Time Limit:</strong> <?= $assessment['duration_minutes'] ?> minutes</p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1"><strong class="d-block text-dark">Max Attempts:</strong> <?= $assessment['max_attempts'] ?></p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1"><strong class="d-block text-dark">Attempts Left:</strong> <span class="text-dark fw-bold"><?= max(0, $attempts_left) ?></span></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-white text-center p-3 border-0">
                <?php if ($is_available): ?>
                    <a href="quiz.php?id=<?= $assessment['id'] ?>" class="btn btn-primary rounded-pill px-3 btn-lg">
                        <i class="bi bi-play-circle-fill me-2"></i>Start
                    </a>
                    <p class="text-muted small mt-2">The timer will begin as soon as you start the quiz.</p>
                <?php else: ?>
                    <button class="btn btn-secondary btn-lg" disabled>
                        <i class="bi bi-slash-circle-fill me-2"></i>Unavailable
                    </button>
                    <p class="text-danger small mt-2">
                        <?= empty($assessment['is_open']) ? "This assessment is currently closed." : "You have no attempts remaining." ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>