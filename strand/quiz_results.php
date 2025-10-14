<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/header.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

// --- GET DATA ---
$attempt_id = $_GET['attempt_id'] ?? 0;
$student_id = $_SESSION['user_id'];

if (!$attempt_id) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Quiz attempt not found.</div></div>";
    require_once '../includes/footer.php';
    exit;
}

// --- FETCH QUIZ ATTEMPT DETAILS ---
$sql = "
    SELECT 
        qa.score, qa.total_items, qa.submitted_at, 
        a.title as assessment_title, a.strand_id, qa.assessment_id
    FROM quiz_attempts qa
    JOIN assessments a ON qa.assessment_id = a.id
    WHERE qa.id = ? AND qa.student_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $attempt_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();
$attempt_details = $result->fetch_assoc();
$stmt->close();

if (!$attempt_details) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Could not load your quiz results.</div></div>";
    require_once '../includes/footer.php';
    exit;
}

$score = $attempt_details['score'];
$total_items = $attempt_details['total_items'];
$percentage = ($total_items > 0) ? round(($score / $total_items) * 100) : 0;
$back_link = 'strand.php?id=' . $attempt_details['strand_id'] . '#assessments';


// --- RECOMMENDATION ENGINE LOGIC ---
$recommendations = [];
// Only show recommendations if the student passed (e.g., scored 50% or higher)
if ($percentage >= 50) {
    $sql_rec = "
        SELECT 
            lm.id, lm.label, lm.type
        FROM material_views mv
        JOIN learning_materials lm ON mv.material_id = lm.id
        WHERE mv.student_id IN (
            -- Find other students who also passed this quiz
            SELECT student_id 
            FROM quiz_attempts 
            WHERE assessment_id = ? AND (score / total_items) >= 0.5 AND student_id != ?
        )
        AND lm.strand_id = ?
        AND lm.id NOT IN (
             -- Exclude materials the current student has already seen
            SELECT material_id FROM material_views WHERE student_id = ?
        )
        GROUP BY lm.id
        LIMIT 3
    ";
    $stmt_rec = $conn->prepare($sql_rec);
    $stmt_rec->bind_param("iiii", $attempt_details['assessment_id'], $student_id, $attempt_details['strand_id'], $student_id);
    $stmt_rec->execute();
    $recommendations = $stmt_rec->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_rec->close();
}
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0 text-center">
                <div class="card-body p-5">
                    <h1 class="card-title">Quiz Completed!</h1>
                    <p class="lead">You have completed the quiz: <strong><?= htmlspecialchars($attempt_details['assessment_title']) ?></strong></p>

                    <div class="my-4">
                        <h2 class="display-4 fw-bold text-primary"><?= $score ?> / <?= $total_items ?></h2>
                        <p class="h4">(<?= $percentage ?>%)</p>
                    </div>

                    <p class="text-muted">Submitted on: <?= date("F j, Y, g:i a", strtotime($attempt_details['submitted_at'])) ?></p>

                    <a href="<?= htmlspecialchars($back_link) ?>" class="btn btn-outline-secondary mt-4">
                        <i class="bi bi-arrow-left"></i> Back to Strand
                    </a>
                </div>
            </div>

            <?php if (!empty($recommendations)): ?>
                <div class="mt-5">
                    <h3 class="text-center mb-4">Recommended For You</h3>
                    <div class="list-group">
                        <?php foreach ($recommendations as $rec): ?>
                            <a href="/ALS_LMS/view_material.php?id=<?= $rec['id'] ?>" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center">
                                <i class="bi bi-file-earmark-text fs-4 me-3 text-primary"></i>
                                <div>
                                    <strong class="d-block"><?= htmlspecialchars($rec['label']) ?></strong>
                                    <small class="text-muted">Type: <?= ucfirst(str_replace('_', ' ', $rec['type'])) ?></small>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
$conn->close();
?>