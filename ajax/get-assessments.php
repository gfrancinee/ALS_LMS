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

// --- START: Replace your existing while loop with this code ---
while ($row = $result->fetch_assoc()) {
  $id = $row['id'];
  $title = htmlspecialchars($row['title']);
  $desc = htmlspecialchars($row['description']);
  $duration = (int)$row['duration_minutes'];
  $max_attempts = (int)$row['max_attempts'];

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

  if ($user_role === 'teacher') {
    $status_color = $row['status'] === 'open' ? 'success' : 'secondary';
    $toggle_action = $row['status'] === 'open' ? 'closed' : 'open';
    $toggle_text = $row['status'] === 'open' ? 'Close' : 'Open';
    $status_text = ucfirst($row['status']);

    // --- THIS TEACHER VIEW IS NOW CORRECTED ---
    echo "
            <div class='d-flex gap-2 align-items-center'>
                
                <a href='/ALS_LMS/strand/manage_assessment.php?id=$id' class='btn btn-sm btn-outline-primary'>Manage Questions</a>

                <button class='btn btn-sm btn-outline-info view-attempts-btn' data-bs-toggle='modal' data-bs-target='#viewAttemptsModal' data-id='$id'>View Attempts</button>
                <button class='btn btn-sm btn-outline-$status_color toggle-status-btn' data-id='$id' data-status='$toggle_action'>$toggle_text</button>
                
                <div class='dropdown'>
                    <button class='btn btn-sm btn-light' type='button' data-bs-toggle='dropdown' aria-expanded='false'>
                        <i class='bi bi-three-dots-vertical'></i>
                    </button>
                    <ul class='dropdown-menu dropdown-menu-end'>
                        <li>
                            <a class='dropdown-item' href='/ALS_LMS/strand/preview_assessment.php?id=$id'>
                                <i class='bi bi-eye me-2'></i> Preview
                            </a>
                        </li>
                        <li>
                            <button class='dropdown-item edit-assessment-btn'
                                data-bs-toggle='modal'
                                data-bs-target='#editAssessmentModal'
                                data-id='$id'
                                data-title='$title'
                                data-description='$desc'
                                data-duration='$duration'
                                data-max-attempts='$max_attempts'>
                                <i class='bi bi-pencil-square me-2 text-success'></i> Edit
                            </button>
                        </li>
                        <li>
                            <button type='button' class='dropdown-item text-danger' data-bs-toggle='modal' data-bs-target='#deleteAssessmentModal' data-id='$id'>
                                <i class='bi bi-trash3 me-2'></i> Delete
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
            <span class='badge bg-$status_color'>$status_text</span>
        ";
  } else { // Student View (This part was already good)
    $attempt_count = (int)$row['attempt_count'];
    $attempts_left = $max_attempts - $attempt_count;

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
      echo "<button class='btn btn-sm btn-primary start-quiz-btn' data-assessment-id='$id'>Take Quiz</button>";
    }

    echo "<span class='badge bg-info'>Attempts Left: $attempts_left</span>";
  }

  echo "
            </div>
        </div>
    </div>";
}
// --- END: Replacement block ---
