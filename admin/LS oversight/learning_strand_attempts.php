<?php
session_start();
include '../../includes/db.php'; // Updated path

// Security Check: Ensure user is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login.php'); // Updated path
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


// --- Fetch all quiz attempts FOR THIS STRAND ---
$sql = "SELECT
            a.id AS attempt_id, a.score, a.total_items, a.created_at,
            u.fname, u.lname,
            q.title AS quiz_title
        FROM quiz_attempts a
        JOIN users u ON a.user_id = u.id
        JOIN quizzes q ON a.quiz_id = q.id
        WHERE u.role = 'student' AND q.strand_id = ?
        ORDER BY a.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $strand_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Attempts - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin.css">
</head>

<body>

    <header class="topbar sticky-top d-flex justify-content-between align-items-center px-4 py-3">
        <div class="d-flex align-items-left">
            <h1 class="title m-0">
                <div id="font">
                    <span>A</span><span>L</span><span>S</span> Learning Management System
                </div>
            </h1>
        </div>
        <div class="top-icons d-flex align-items-center gap-3">
            <img src="../../img/ALS.png" class="top-logo" alt="ALS Logo" /> <img src="../../img/BNHS.jpg" class="top-logo" alt="BNHS Logo" />
        </div>
    </header>

    <main class="content">
        <div class="container py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0"><?= htmlspecialchars($strand['strand_title']) ?></h2>
                <div class="mb-3">
                    <a href="quiz_performance.php" class="back-link">
                        <i class="bi bi-arrow-left me-1"></i>Back to All Strands
                    </a>
                </div>
            </div>

            <p class="text-muted" style="margin-top: -1.5rem;">Quiz attempts for <?= htmlspecialchars($strand['strand_code']) ?></p>


            <div class="card shadow-sm border-0 mt-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Student Name</th>
                                    <th scope="col">Quiz Title</th>
                                    <th scope="col">Score</th>
                                    <th scope="col">Date Taken</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($attempt = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($attempt['fname'] . ' ' . $attempt['lname']) ?></td>
                                            <td><?= htmlspecialchars($attempt['quiz_title']) ?></td>
                                            <td>
                                                <span class="fw-bold fs-5"><?= htmlspecialchars($attempt['score']) ?></span> / <?= htmlspecialchars($attempt['total_items']) ?>
                                            </td>
                                            <td>
                                                <?= date_format(date_create($attempt['created_at']), 'M d, Y, g:i A') ?>
                                            </td>
                                            <td>
                                                <a href="../view_single_attempt.php?id=<?= $attempt['attempt_id'] ?>" class="btn btn-primary btn-sm">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted p-4">No quiz attempts found for this strand.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>