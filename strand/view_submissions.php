<?php
session_start();
require_once '../includes/db.php'; // Adjust path if needed
require_once '../includes/functions.php'; // Adjust path if needed

// --- Security Check: Ensure user is a logged-in teacher ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit;
}
$teacher_id = $_SESSION['user_id'];

// --- Get Assessment ID from URL ---
$assessment_id = filter_input(INPUT_GET, 'assessment_id', FILTER_VALIDATE_INT);
if (!$assessment_id) {
    // Redirect or show error if assessment ID is missing or invalid
    header("Location: index.php"); // Redirect to teacher dashboard or strand list
    exit;
}

// --- Fetch Assessment Details & Verify Ownership ---
// *** MODIFICATION: Added 'a.type' to the SELECT statement ***
$stmt_assessment = $conn->prepare(
    "SELECT a.title, a.strand_id, a.type
     FROM assessments a
     WHERE a.id = ? AND a.teacher_id = ?"
);
if ($stmt_assessment === false) {
    die("Prepare failed (assessment): " . $conn->error);
}
$stmt_assessment->bind_param("ii", $assessment_id, $teacher_id);
$stmt_assessment->execute();
$result_assessment = $stmt_assessment->get_result();
$assessment = $result_assessment->fetch_assoc();
$stmt_assessment->close();

if (!$assessment) {
    // Assessment not found or teacher doesn't own it
    echo "Assessment not found or you do not have permission to view it.";
    // Optionally include footer and exit nicely
    exit;
}

// *** NEW: Check Assessment Type ***
$is_quiz_or_exam = ($assessment['type'] === 'quiz' || $assessment['type'] === 'exam');

// --- Fetch Student Submissions (Conditional) ---
if ($is_quiz_or_exam) {
    // --- THIS IS YOUR EXISTING WORKING CODE FOR QUIZZES ---
    $stmt_submissions = $conn->prepare(
        "SELECT qa.id as attempt_id, qa.student_id, qa.score, qa.total_items, qa.submitted_at,
                u.fname, u.lname, 'graded' as status 
         FROM quiz_attempts qa
         JOIN users u ON qa.student_id = u.id
         WHERE qa.assessment_id = ?
         ORDER BY u.lname, u.fname, qa.submitted_at DESC"
    );
    if ($stmt_submissions === false) {
        die("Prepare failed (submissions): " . $conn->error);
    }
} else {
    // --- THIS IS THE NEW CODE FOR ACTIVITIES/ASSIGNMENTS ---
    $stmt_submissions = $conn->prepare(
        "SELECT ac.id as attempt_id, ac.student_id, ac.score, ac.total_points as total_items, ac.submitted_at,
                u.fname, u.lname, ac.status
         FROM activity_submissions ac
         JOIN users u ON ac.student_id = u.id
         WHERE ac.assessment_id = ?
         ORDER BY u.lname, u.fname, ac.submitted_at DESC"
    );
    if ($stmt_submissions === false) {
        die("Prepare failed (submissions): " . $conn->error);
    }
}
// --- End of new logic ---

$stmt_submissions->bind_param("i", $assessment_id);
$stmt_submissions->execute();
$result_submissions = $stmt_submissions->get_result();
$submissions = $result_submissions->fetch_all(MYSQLI_ASSOC);
$stmt_submissions->close();

// --- Include Header ---
$page_title = "Submissions for " . htmlspecialchars($assessment['title']);
require_once '../includes/header.php'; // Adjust path if needed
$back_link = '/ALS_LMS/strand/strand.php?id=' . ($assessment['strand_id'] ?? 0) . '#assessments';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Submissions: <?= htmlspecialchars($assessment['title']) ?></h2>
        <a href="<?= htmlspecialchars($back_link) ?>" class="back-link <?= $back_link_class ?>"> <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
    <hr>

    <?php if (empty($submissions)): ?>
        <div class="alert alert-info" role="alert">
            No students have submitted this assessment yet.
        </div>
    <?php else: ?>
        <div class="table-responsive border-none">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-white">
                    <tr>
                        <th>Student Name</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Submitted At</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $submission): ?>
                        <tr>
                            <td><?= htmlspecialchars($submission['lname'] . ', ' . $submission['fname']) ?></td>

                            <td>
                                <?php if ($submission['status'] === 'graded'): ?>
                                    <span class="badge text-primary">Graded</span>
                                <?php else: ?>
                                    <span class="badge text-warning">Submitted</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($submission['status'] === 'graded'): ?>
                                    <strong><?= (int)$submission['score'] ?> / <?= (int)$submission['total_items'] ?></strong>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date("M j, Y, g:i A", strtotime($submission['submitted_at'])) ?></td>
                            <td>
                                <?php if ($is_quiz_or_exam): ?>
                                    <a href="grade_submission.php?attempt_id=<?= $submission['attempt_id'] ?>" class="btn text-primary btn-sm me-3 btn-pill-hover">
                                        <i class="bi bi-eye-fill me-1"></i> View
                                    </a>
                                <?php else: ?>
                                    <a href="grade_activity.php?submission_id=<?= $submission['attempt_id'] ?>" class="btn text-primary btn-sm me-3 <?= $submission['status'] === 'graded' ?: 'btn-pill-hover' ?>">
                                        <i class="bi <?= $submission['status'] === 'graded' ? 'bi bi-eye-fill me-1' : 'bi bi-eye-fill me-1' ?> me-1"></i>
                                        <?= $submission['status'] === 'graded' ? 'View' : 'View' ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<?php
$conn->close();
require_once '../includes/footer.php'; // Adjust path if needed
?>