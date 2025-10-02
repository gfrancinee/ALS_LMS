<?php
session_start();
require_once '../includes/db.php';

$strand_id  = $_GET['strand_id']  ?? null;
$teacher_id = $_SESSION['user_id'] ?? null;

if (!$strand_id || !$teacher_id) {
  echo '<div class="alert alert-warning">Missing strand or user context.</div>';
  exit;
}

$stmt = $conn->prepare("
    SELECT id, title, type, description 
      FROM assessments 
     WHERE strand_id = ? 
       AND teacher_id = ?
  ORDER BY created_at ASC
");
$stmt->bind_param("ii", $strand_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo '<div class="alert alert-info">No assessments found for this strand.</div>';
  exit;
}

while ($row = $result->fetch_assoc()) {
  $id    = $row['id'];
  $title = htmlspecialchars($row['title']);
  $type  = ucfirst($row['type']);
  $desc  = htmlspecialchars($row['description']);

  echo "
    <div class='card mb-2'>
      <div class='card-body'>
        <h5 class='card-title'>$title</h5>
        <h6 class='card-subtitle mb-2 text-muted'>$type</h6>
        <p class='card-text'>$desc</p>
        <div class='d-flex gap-2'>
          <!-- Manage Questions: opens #questionsModal -->
          <button
            class='btn btn-sm btn-outline-primary manage-questions'
            data-assessment-id='<?= (int)$id ?>'
            data-strand-id='<?= htmlspecialchars($strand_id) ?>'
            data-bs-toggle='modal'
            data-bs-target='#questionsModal'>
            Manage Questions
          </button>

          <!-- Delete Assessment: tagged so JS can distinguish -->
          <button
            class='btn btn-sm btn-outline-danger delete-assessment'
            data-id='$id'
          >
            Delete
          </button>
        </div>
      </div>
    </div>
    ";
}
