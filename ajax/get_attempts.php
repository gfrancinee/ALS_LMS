<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    die("Unauthorized");
}

$assessment_id = $_GET['assessment_id'] ?? null;
if (!$assessment_id) {
    http_response_code(400);
    die("Assessment ID is required.");
}

$stmt = $conn->prepare("
    SELECT 
        u.fname, 
        u.lname, 
        qa.score, 
        qa.total_items, 
        qa.submitted_at
    FROM quiz_attempts qa
    JOIN users u ON qa.student_id = u.id
    WHERE qa.assessment_id = ?
    ORDER BY qa.submitted_at DESC
");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-info">No students have taken this quiz yet.</div>';
    exit;
}

echo '<ul class="list-group">';
while ($row = $result->fetch_assoc()) {
    $name = htmlspecialchars($row['fname'] . ' ' . $row['lname']);
    $score = (int)$row['score'];
    $total = (int)$row['total_items'];
    $date = date("F j, Y, g:i a", strtotime($row['submitted_at']));

    echo "
        <li class='list-group-item d-flex justify-content-between align-items-center'>
            <div>
                <strong>$name</strong>
                <small class='d-block text-muted'>Taken on: $date</small>
            </div>
            <span class='badge bg-primary rounded-pill fs-6'>$score / $total</span>
        </li>
    ";
}
echo '</ul>';

$stmt->close();
$conn->close();
