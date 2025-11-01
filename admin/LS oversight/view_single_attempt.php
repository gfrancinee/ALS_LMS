<?php
session_start();
include '../../includes/db.php'; // Path from admin/LS-oversight/

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

// 1. Get and Validate Attempt ID
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    echo "Invalid attempt ID.";
    exit;
}
$attempt_id = $_GET['id'];

// 2. Fetch Main Attempt Details
$sql_attempt = "SELECT
                    a.score, a.total_items, a.submitted_at,
                    u.fname, u.lname,
                    q.title AS quiz_title,
                    q.strand_id
                FROM quiz_attempts a
                JOIN users u ON a.student_id = u.id
                JOIN assessments q ON a.assessment_id = q.id
                WHERE a.id = ?";

$stmt_attempt = $conn->prepare($sql_attempt);
$stmt_attempt->bind_param("i", $attempt_id);
$stmt_attempt->execute();
$attempt_result = $stmt_attempt->get_result();

if ($attempt_result->num_rows === 0) {
    echo "Attempt not found.";
    exit;
}
$attempt = $attempt_result->fetch_assoc();


// 3. Fetch All Answers for this Attempt (THIS QUERY IS NOW FIXED)
$sql_answers = "SELECT
                    qb.question_text, 
                    sa.answer_text, 
                    sa.is_correct,  
                    (SELECT GROUP_CONCAT(opt.option_text SEPARATOR ', ') 
                     FROM question_options opt 
                     WHERE opt.question_id = qb.id AND opt.is_correct = 1) AS correct_answer
                FROM student_answers sa
                JOIN question_bank qb ON sa.question_id = qb.id
                WHERE sa.quiz_attempt_id = ? -- <-- THIS WAS THE FIX
                ORDER BY sa.id";

$stmt_answers = $conn->prepare($sql_answers);
$stmt_answers->bind_param("i", $attempt_id);
$stmt_answers->execute();
$answers = $stmt_answers->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Attempt Details</title>
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
        <div class="top-icons d-flex align-itemsC-center gap-3">
            <img src="../../img/ALS.png" class="top-logo" alt="ALS Logo" />
            <img src="../../img/BNHS.jpg" class="top-logo" alt="BNHS Logo" />
        </div>
    </header>

    <main class="content">
        <div class="container py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Attempt Details</h2>
                <div class="mb-3">
                    <a href="learning_strand_attempts.php?id=<?= $attempt['strand_id'] ?>" class="back-link">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <h4 class="mb-3 text-center"><?= htmlspecialchars($attempt['quiz_title']) ?></h4>
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <h6 class="text-muted">Student</h6>
                            <p class="fs-5"><?= htmlspecialchars($attempt['fname'] . ' ' . $attempt['lname']) ?></p>
                        </div>
                        <div class="col-md-4 text-center">
                            <h6 class="text-muted">Final Score</h6>
                            <p class="fs-5 fw-bold text-primary"><?= $attempt['score'] ?> / <?= $attempt['total_items'] ?></p>
                        </div>
                        <div class="col-md-4 text-center">
                            <h6 class="text-muted">Date Taken</h6>
                            <p class="fs-5"><?= date_format(date_create($attempt['submitted_at']), 'M d, Y, g:i A') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <h5 class="mb-3">Answer Sheet</h5>
            <?php $q_num = 1; ?>
            <?php while ($answer = $answers->fetch_assoc()): ?>
                <?php $is_correct = $answer['is_correct'] == 1; ?>

                <div class="card answer-card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Question <?= $q_num++ ?></strong>
                        <?php if ($is_correct): ?>
                            <span class="badge bg-success-subtle text-success-emphasis rounded-pill">Correct</span>
                        <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger-emphasis rounded-pill">Incorrect</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <p class="question-text"><?= htmlspecialchars($answer['question_text']) ?></p>

                        <hr>

                        <div class="answer-row <?= $is_correct ? 'text-success' : 'text-danger' ?>">
                            <i class="bi <?= $is_correct ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> me-2"></i>
                            <strong>Your Answer:</strong>
                            <span class="answer-text"><?= htmlspecialchars($answer['answer_text'] ?? 'No answer') ?></span>
                        </div>

                        <?php if (!$is_correct): ?>
                            <div class="answer-row text-success mt-2">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <strong>Correct Answer:</strong>
                                <span class="answer-text"><?= htmlspecialchars($answer['correct_answer'] ?? 'N/A') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>

        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>