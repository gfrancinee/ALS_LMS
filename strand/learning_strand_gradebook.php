<?php
session_start();
// --- FIX: Updated path to go up two levels ---
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Security Check: Ensure user is a logged-in teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../login.php"); // --- FIX: Updated path ---
    exit;
}
$teacher_id = $_SESSION['user_id'];
$strand_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$strand_id) {
    die("Error: No learning strand ID provided.");
}

// --- 1. FETCH ALL ASSESSMENTS (THE COLUMNS) ---
$stmt_cols = $conn->prepare(
    "SELECT id, title, (SELECT SUM(max_points) FROM question_bank qb JOIN assessment_questions aq ON qb.id = aq.question_id WHERE aq.assessment_id = a.id) as total_points
     FROM assessments a
     WHERE a.strand_id = ? ORDER BY a.created_at"
);
$stmt_cols->bind_param("i", $strand_id);
$stmt_cols->execute();
$assessments_result = $stmt_cols->get_result();
$assessments = [];
while ($row = $assessments_result->fetch_assoc()) {
    $assessments[] = $row;
}
$stmt_cols->close();


// --- 2. FETCH ALL STUDENTS (THE ROWS) ---
$stmt_rows = $conn->prepare(
    "SELECT u.id, u.fname, u.lname FROM users u
     JOIN strand_participants sp ON u.id = sp.student_id
     WHERE sp.strand_id = ? AND sp.role = 'student'
     ORDER BY u.lname, u.fname"
);
$stmt_rows->bind_param("i", $strand_id);
$stmt_rows->execute();
$students_result = $stmt_rows->get_result();
$students = $students_result->fetch_all(MYSQLI_ASSOC);
$stmt_rows->close();

// --- 3. FETCH ALL SCORE DATA (THE CELLS) ---
$stmt_data = $conn->prepare(
    "SELECT student_id, assessment_id, score, status 
     FROM quiz_attempts 
     WHERE assessment_id IN (
         SELECT id FROM assessments WHERE strand_id = ?
     )"
);
$stmt_data->bind_param("i", $strand_id);
$stmt_data->execute();
$data_result = $stmt_data->get_result();

// --- 4. BUILD THE SCORE MATRIX (2D ARRAY) ---
$score_matrix = [];
while ($row = $data_result->fetch_assoc()) {
    if (!isset($score_matrix[$row['student_id']][$row['assessment_id']])) {
        $score_matrix[$row['student_id']][$row['assessment_id']] = $row;
    } else {
        if ($row['score'] > $score_matrix[$row['student_id']][$row['assessment_id']]['score']) {
            $score_matrix[$row['student_id']][$row['assessment_id']] = $row;
        }
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
    <title>Summary of Scores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Basic styling for the gradebook table */
        .table-container {
            width: 100%;
            overflow-x: auto;
            /* Allows horizontal scrolling on small screens */
            padding: 1rem;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            min-width: 800px;
            /* Force minimum width */
        }

        th,
        td {
            border: 1px solid #dee2e6;
            padding: 0.75rem;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #f8f9fa;
        }

        /* Sticky header for the student names column */
        th:first-child,
        td:first-child {
            position: sticky;
            left: 0;
            background-color: #fdfdfd;
            z-index: 10;
            min-width: 180px;
        }

        th:first-child {
            z-index: 20;
        }

        .score-cell {
            text-align: center;
            min-width: 120px;
        }

        .status-pending {
            font-style: italic;
            color: #6c757d;
        }

        .status-na {
            color: #adb5bd;
        }
    </style>
</head>

<body class="bg-light">

    <!-- Include your Teacher Header/Navbar here -->
    <!-- e.g., <?php // include '../../includes/teacher_header.php'; 
                ?> -->

    <div class="container-fluid">
        <div class="table-container my-4 bg-white rounded shadow-sm">
            <h1 class="mb-3">Summary of Scores</h1>

            <?php if (empty($students) || empty($assessments)): ?>
                <div class="alert alert-info">
                    There are either no students in this strand or no assessments created yet.
                </div>
            <?php else: ?>
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <!-- 1. The first header is fixed -->
                            <th scope="col">Student Name</th>

                            <!-- 2. Loop through assessments to create dynamic headers -->
                            <?php foreach ($assessments as $assessment): ?>
                                <th scope="col" class="text-center">
                                    <?php echo htmlspecialchars($assessment['title']); ?>
                                    <br>
                                    <small class="fw-normal text-muted">(Total: <?php echo (int)$assessment['total_points']; ?> pts)</small>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- 3. Loop through students to create rows -->
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <!-- The first cell is the student's name -->
                                <td><?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?></td>

                                <!-- 4. Loop through assessments again for each student -->
                                <?php foreach ($assessments as $assessment): ?>
                                    <td class="score-cell">
                                        <?php
                                        // 5. Use the matrix to find the correct score
                                        $cell_data = $score_matrix[$student['id']][$assessment['id']] ?? null;

                                        if ($cell_data) {
                                            if ($cell_data['status'] == 'submitted' || $cell_data['status'] == 'graded') {
                                                echo '<strong>' . htmlspecialchars($cell_data['score']) . '</strong> / ' . (int)$assessment['total_points'];
                                            } elseif ($cell_data['status'] == 'pending_grading') {
                                                echo '<span class="status-pending">Pending</span>';
                                            } else {
                                                echo '<span class="status-pending">In Progress</span>';
                                            }
                                        } else {
                                            echo '<span class="status-na">N/A</span>';
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Include your Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>