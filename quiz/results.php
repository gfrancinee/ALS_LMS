<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$attempt_id = $_GET['attempt_id'] ?? null;
$student_id = $_SESSION['user_id'];

if (!$attempt_id) {
    die("Error: No attempt specified.");
}

// UPDATE: Fetch score, total_items, AND the strand_id
$stmt = $conn->prepare("
    SELECT qa.score, qa.total_items, a.strand_id 
    FROM quiz_attempts qa
    JOIN assessments a ON qa.assessment_id = a.id
    WHERE qa.id = ? AND qa.student_id = ?
");
$stmt->bind_param("ii", $attempt_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Error: Attempt not found or you do not have permission to view it.");
}

$attempt = $result->fetch_assoc();
$score = $attempt['score'];
$total_items = $attempt['total_items'];
$strand_id = $attempt['strand_id']; // <-- The missing ID

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        /* Your existing CSS styling */
        body {
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .result-container {
            max-width: 550px;
            width: 100%;
            padding: 50px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .score {
            font-size: 3.5rem;
            font-weight: 700;
            color: #0056b3;
        }

        .total-score {
            font-size: 1.8rem;
            color: #6c757d;
            margin-left: 10px;
        }
    </style>
</head>

<body>
    <div class="result-container">
        <h1><i class="bi bi-check-circle-fill text-success me-2"></i> Quiz Completed</h1>
        <div class="score-display my-4">
            <span class="score"><?= htmlspecialchars($score) ?></span>
            <span class="total-score">/ <?= htmlspecialchars($total_items) ?></span>
        </div>
        <p class="lead">Your results have been recorded.</p>

        <a href="../strand/strand.php?id=<?= htmlspecialchars($strand_id) ?>" class="btn btn-primary mt-3">
            <i class="bi bi-arrow-left-circle me-2"></i>Back to Strand
        </a>
    </div>
</body>

</html>