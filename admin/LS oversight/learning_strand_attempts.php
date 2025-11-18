<?php
session_start();
include '../../includes/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

// Get the strand ID from the URL and validate it
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    echo "Invalid request.";
    exit;
}
$strand_id = $_GET['id'];

// --- Fetch Strand Details (for the title) ---
$strand_sql = "SELECT strand_title, strand_code FROM learning_strands WHERE id = ?";
$strand_stmt = $conn->prepare($strand_sql);
$strand_stmt->bind_param("i", $strand_id);
$strand_stmt->execute();
$strand_result = $strand_stmt->get_result();
if ($strand_result->num_rows === 0) {
    echo "Strand not found.";
    exit;
}
$strand = $strand_result->fetch_assoc();


// --- UPDATED SQL QUERY: Combines Quizzes AND Activities/Assignments/Projects ---
$sql = "SELECT * FROM (
            -- Part 1: Quiz & Exam Attempts
            SELECT
                a.id AS attempt_id, 
                a.score, 
                a.total_items, 
                a.submitted_at, 
                u.fname, 
                u.lname,
                assess.title AS quiz_title,
                assess.type AS assessment_type
            FROM quiz_attempts a
            JOIN users u ON a.student_id = u.id 
            JOIN assessments assess ON a.assessment_id = assess.id 
            WHERE u.role = 'student' AND assess.strand_id = ?

            UNION ALL

            -- Part 2: Activity, Assignment, & Project Submissions
            SELECT
                s.id AS attempt_id,
                s.score,
                assess.total_points AS total_items, -- Use total_points for activities
                s.submitted_at,
                u.fname,
                u.lname,
                assess.title AS quiz_title,
                assess.type AS assessment_type
            FROM activity_submissions s
            JOIN users u ON s.student_id = u.id
            JOIN assessments assess ON s.assessment_id = assess.id
            WHERE u.role = 'student' AND assess.strand_id = ?
        ) AS combined_results
        ORDER BY quiz_title ASC, submitted_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("SQL Error: " . $conn->error);
}
// We bind the ID twice because there are two ? placeholders now (one for each query in the UNION)
$stmt->bind_param("ii", $strand_id, $strand_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Attempts - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/learning_strand_attempts.css">
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0"><?= htmlspecialchars($strand['strand_title']) ?></h2>
                <div class="mb-3">
                    <a href="quiz_performance.php" class="back-link"> <i class="bi bi-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>

            <p class="text-muted" style="margin-top: -1.5rem;">Assessment Attempts for <?= htmlspecialchars($strand['strand_code']) ?></p>

            <table class="table mt-4">
                <thead class="table-light">
                    <tr>
                        <th>Student Name</th>
                        <th>Assessment Title</th>
                        <th>Type</th>
                        <th>Score</th>
                        <th>Date Taken</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($attempt = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($attempt['fname'] . ' ' . $attempt['lname']) ?></td>
                                <td><?= htmlspecialchars($attempt['quiz_title']) ?></td>
                                <td>
                                    <span class="badge text-secondary"><?= ucfirst($attempt['assessment_type']) ?></span>
                                </td>
                                <td>
                                    <span class="fw-bold"><?= htmlspecialchars($attempt['score']) ?></span> / <?= htmlspecialchars($attempt['total_items']) ?>
                                </td>
                                <td>
                                    <?= date_format(date_create($attempt['submitted_at']), 'M d, Y, g:i A') ?>
                                </td>
                                <td>
                                    <?php
                                    // Determine link based on type
                                    $link = '#';
                                    if ($attempt['assessment_type'] == 'quiz' || $attempt['assessment_type'] == 'exam') {
                                        $link = "view_single_attempt.php?id=" . $attempt['attempt_id'];
                                    } else {
                                        // Assuming you have a file to view/grade assignments (adjust filename if needed)
                                        $link = "view_activity_submission.php?id=" . $attempt['attempt_id'];
                                    }
                                    ?>
                                    <a href="<?= $link ?>" class="btn text-primary btn-sm me-3 btn-pill-hover">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted p-4">No assessment attempts found for this strand.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>