<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../login.php");
    exit;
}
$teacher_id = $_SESSION['user_id'];
$strand_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$strand_id) {
    die("Error: No learning strand ID provided.");
}

// --- 1. FETCH ASSESSMENTS (COLUMNS) ---
// Uses logic: Counts question points for Quiz/Exam, uses manual points for others.
$stmt_cols = $conn->prepare(
    "SELECT 
        a.id, 
        a.title, 
        a.type, 
        CASE 
            WHEN a.type IN ('quiz', 'exam') THEN 
                (SELECT COALESCE(SUM(qb.max_points), 0) 
                 FROM question_bank qb 
                 JOIN assessment_questions aq ON qb.id = aq.question_id 
                 WHERE aq.assessment_id = a.id)
            ELSE 
                a.total_points 
        END as total_points
     FROM assessments a 
     WHERE a.strand_id = ? 
     ORDER BY a.created_at ASC"
);
$stmt_cols->bind_param("i", $strand_id);
$stmt_cols->execute();
$assessments = $stmt_cols->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_cols->close();

// --- 2. FETCH STUDENTS (ROWS) ---
// FIX: Changed 'u.img' to 'u.avatar_url' based on your database structure
$stmt_rows = $conn->prepare(
    "SELECT u.id, u.fname, u.lname, u.avatar_url 
     FROM users u
     JOIN strand_participants sp ON u.id = sp.student_id
     WHERE sp.strand_id = ? AND sp.role = 'student'
     ORDER BY u.lname, u.fname"
);
$stmt_rows->bind_param("i", $strand_id);
$stmt_rows->execute();
$students = $stmt_rows->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_rows->close();

// --- 3. FETCH SCORES (CELLS) ---
// Combines Quiz Attempts and Activity Submissions
$sql_scores = "
    SELECT student_id, assessment_id, score, status
    FROM quiz_attempts 
    WHERE assessment_id IN (SELECT id FROM assessments WHERE strand_id = ?)
    
    UNION ALL
    
    SELECT student_id, assessment_id, score, status
    FROM activity_submissions 
    WHERE assessment_id IN (SELECT id FROM assessments WHERE strand_id = ?)
";

$stmt_data = $conn->prepare($sql_scores);
$stmt_data->bind_param("ii", $strand_id, $strand_id);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

// Build Matrix
$score_matrix = [];
while ($row = $result_data->fetch_assoc()) {
    $s_id = $row['student_id'];
    $a_id = $row['assessment_id'];

    // Keep highest score logic
    if (!isset($score_matrix[$s_id][$a_id]) || $row['score'] > $score_matrix[$s_id][$a_id]['score']) {
        $score_matrix[$s_id][$a_id] = $row;
    }
}
$stmt_data->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Gradebook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .gradebook-card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            overflow: hidden;
        }

        /* Table Container for Scroll */
        .table-responsive {
            position: relative;
            max-height: 80vh;
            overflow: auto;
        }

        table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }

        th,
        td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #eaecf0;
            border-right: 1px solid #f0f2f5;
            min-width: 140px;
            text-align: center;
        }

        /* --- Sticky Columns & Headers --- */
        thead th {
            position: sticky;
            top: 0;
            background-color: #fff;
            z-index: 10;
            border-bottom: 2px solid #eaecf0;
            height: 110px;
        }

        /* FIXED: Centered Sticky Column */
        tbody td:first-child,
        thead th:first-child {
            position: sticky;
            left: 0;
            background-color: #fff;
            z-index: 11;
            text-align: center;
            /* Changed from left to center */
            border-right: 2px solid #eaecf0;
            min-width: 240px;
            /* Made slightly wider for better centered look */
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.02);
        }

        thead th:first-child {
            z-index: 12;
        }

        /* --- Styling Elements --- */
        .student-avatar-placeholder {
            width: 35px;
            height: 35px;
            background-color: #e9ecef;
            color: #495057;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            margin-right: 10px;
        }

        .student-avatar-img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            border: 1px solid #dee2e6;
        }

        .assessment-type-badge {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding: 4px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            margin-bottom: 6px;
            display: inline-block;
        }

        .badge-quiz {
            background-color: #e0f2fe;
            color: #0369a1;
        }

        .badge-exam {
            background-color: #f0f9ff;
            color: #0c4a6e;
            border: 1px solid #0ea5e9;
        }

        .badge-activity {
            background-color: #dcfce7;
            color: #15803d;
        }

        .badge-assignment {
            background-color: #f3e8ff;
            color: #7e22ce;
        }

        .badge-project {
            background-color: #ffedd5;
            color: #c2410c;
        }

        .score-val {
            font-weight: 700;
            font-size: 1.1rem;
            color: #333;
        }

        .total-pts {
            font-size: 0.8rem;
            color: #999;
            font-weight: 400;
        }

        /* Status Indicators */
        .status-btn {
            font-size: 0.8rem;
            padding: 4px 12px;
            border-radius: 20px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }

        .status-needs-grading {
            background-color: #fff7ed;
            color: #c2410c;
            border: 1px solid #fdba74;
        }

        .status-needs-grading:hover {
            background-color: #ffedd5;
            color: #9a3412;
        }

        .status-missing {
            color: #adb5bd;
            font-size: 1.2rem;
        }

        .back-link {
            color: #6c757d;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease-in-out;
            margin-right: 2rem;
        }

        .back-link:hover {
            color: blue;
        }
    </style>
</head>

<body>

    <div class="container-fluid px-4 py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Gradebook</h2>
                <p class="text-muted mb-0">View and manage student performance.</p>
            </div>
            <a href="strand.php?id=<?= $strand_id ?>" class="back-link <?= $back_link_class ?>"> <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>

        <div class="gradebook-card bg-white">
            <?php if (empty($students)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-people display-4 mb-3 d-block"></i>
                    No students enrolled in this strand yet.
                </div>
            <?php elseif (empty($assessments)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-journal-plus display-4 mb-3 d-block"></i>
                    No assessments created yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>
                                    <div class="py-2 text-secondary text-uppercase small fw-bold">Student Name</div>
                                </th>
                                <?php foreach ($assessments as $a): ?>
                                    <?php
                                    // Determine Badge Color
                                    $type = strtolower($a['type']);
                                    $badgeClass = 'badge-activity'; // default
                                    if ($type == 'quiz') $badgeClass = 'badge-quiz';
                                    elseif ($type == 'exam') $badgeClass = 'badge-exam';
                                    elseif ($type == 'assignment') $badgeClass = 'badge-assignment';
                                    elseif ($type == 'project') $badgeClass = 'badge-project';
                                    ?>
                                    <th>
                                        <span class="assessment-type-badge <?= $badgeClass ?>"><?= htmlspecialchars($a['type']) ?></span>
                                        <div class="text-truncate fw-bold" style="max-width: 150px; margin: 0 auto;" title="<?= htmlspecialchars($a['title']) ?>">
                                            <?= htmlspecialchars($a['title']) ?>
                                        </div>
                                        <div class="total-pts">
                                            <?= ($a['total_points'] > 0) ? '/ ' . $a['total_points'] : 'No Points Set' ?>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($student['avatar_url'])): ?>
                                                <img src="../<?= htmlspecialchars($student['avatar_url']) ?>" class="student-avatar-img" alt="User">
                                            <?php else: ?>
                                                <div class="student-avatar-placeholder">
                                                    <?= strtoupper(substr($student['fname'], 0, 1) . substr($student['lname'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>

                                            <div>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($student['lname']) ?>,</div>
                                                <div class="text-muted small"><?= htmlspecialchars($student['fname']) ?></div>
                                            </div>
                                        </div>
                                    </td>

                                    <?php foreach ($assessments as $a): ?>
                                        <td>
                                            <?php
                                            $data = $score_matrix[$student['id']][$a['id']] ?? null;
                                            $type = strtolower($a['type']);

                                            if (!$data) {
                                                echo '<span class="status-missing">&mdash;</span>';
                                            } else {
                                                $score = $data['score'];
                                                $status = $data['status'];
                                                $isAutoGraded = in_array($type, ['quiz', 'exam']);

                                                if ($isAutoGraded) {
                                                    // QUIZ & EXAM: Show Number if finished/graded
                                                    if ($status == 'finished' || $status == 'submitted' || $status == 'graded') {
                                                        echo '<span class="score-val">' . (float)$score . '</span>';
                                                    } else {
                                                        echo '<span class="badge bg-light text-secondary fw-normal">In Progress</span>';
                                                    }
                                                } else {
                                                    // ACTIVITY/ASSIGNMENT: 
                                                    if ($status == 'graded') {
                                                        echo '<span class="score-val">' . (float)$score . '</span>';
                                                    } elseif ($status == 'submitted' || $status == 'pending_grading') {
                                                        // Link to grading page
                                                        echo '<a href="view_submissions.php?assessment_id=' . $a['id'] . '" class="status-btn status-needs-grading">';
                                                        echo '<i class="bi bi-pencil-square me-1"></i>Grade';
                                                        echo '</a>';
                                                    } else {
                                                        echo '<span class="badge bg-light text-secondary fw-normal">Draft</span>';
                                                    }
                                                }
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'))
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    </script>
</body>

</html>