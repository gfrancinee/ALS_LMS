<?php
session_start();
require_once '../includes/db.php';

$strand_id  = $_GET['strand_id']  ?? null;
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? null;

if (!$strand_id || !$user_id) {
  echo '<div class="alert alert-warning">Missing required data.</div>';
  exit;
}

// The SQL now gets all the new assessment details AND counts previous attempts
$stmt = $conn->prepare("
    SELECT 
        a.id, a.title, a.description, a.duration_minutes, a.max_attempts, a.status,
        (SELECT COUNT(*) FROM quiz_attempts WHERE assessment_id = a.id AND student_id = ?) as attempt_count
    FROM assessments a
    WHERE a.strand_id = ?
    ORDER BY a.created_at ASC
");
$stmt->bind_param("ii", $user_id, $strand_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo '<div class="alert alert-info">No assessments have been created for this strand yet.</div>';
  exit;
}

while ($row = $result->fetch_assoc()) {
  $id = $row['id'];
  $title = htmlspecialchars($row['title']);
  $desc = htmlspecialchars($row['description']);
  $duration = (int)$row['duration_minutes'];

  echo "
    <div class='card mb-2'>
        <div class='card-body'>
            <div class='d-flex justify-content-between align-items-start'>
                <div>
                    <h5 class='card-title'>$title</h5>
                    <p class='card-text small'>$desc</p>
                </div>
                <div class='text-end ms-3' style='min-width: 120px;'>
                    <span class='badge bg-light text-dark'>Time: $duration mins</span>
                </div>
            </div>
            <hr>
            <div class='d-flex justify-content-between align-items-center mt-2'>";

  // --- BUTTON LOGIC ---
  if ($user_role === 'teacher') {
    $status_color = $row['status'] === 'open' ? 'success' : 'secondary';
    $toggle_action = $row['status'] === 'open' ? 'closed' : 'open';
    $toggle_text = $row['status'] === 'open' ? 'Close' : 'Open';
    $status_text = ucfirst($row['status']);

    echo "
            <div>
                <button class='btn btn-sm btn-outline-primary manage-questions' data-assessment-id='$id' data-bs-toggle='modal' data-bs-target='#questionsModal'>Manage Questions</button>
                <button class='btn btn-sm btn-outline-$status_color toggle-status-btn' data-id='$id' data-status='$toggle_action'>$toggle_text</button>
                <button class='btn btn-sm btn-outline-danger delete-assessment' data-id='$id'>Delete</button>
            </div>
            <span class='badge bg-$status_color'>$status_text</span>
        ";
  } else { // Student View
    $attempt_count = (int)$row['attempt_count'];
    $max_attempts = (int)$row['max_attempts'];
    $attempts_left = $max_attempts - $attempt_count;

    // Fetch the student's highest score if they've taken it
    $score_stmt = $conn->prepare("SELECT MAX(score) as max_score, total_items FROM quiz_attempts WHERE assessment_id = ? AND student_id = ?");
    $score_stmt->bind_param("ii", $id, $user_id);
    $score_stmt->execute();
    $score_result = $score_stmt->get_result()->fetch_assoc();
    $score_stmt->close();

    if ($score_result && $score_result['max_score'] !== null) {
      echo "<div><span class='badge bg-success'>Highest Score: {$score_result['max_score']} / {$score_result['total_items']}</span></div>";
    }

    if ($row['status'] === 'closed') {
      echo "<button class='btn btn-sm btn-secondary disabled'>Quiz is Closed</button>";
    } else if ($attempts_left <= 0) {
      echo "<button class='btn btn-sm btn-warning disabled'>No Attempts Left</button>";
    } else {
      echo "<a href='../quiz/take_quiz.php?assessment_id=$id' class='btn btn-sm btn-primary'>Take Quiz</a>";
    }

    echo "<span class='badge bg-info'>Attempts Left: $attempts_left</span>";
  }

  echo "
            </div>
        </div>
    </div>";
}
