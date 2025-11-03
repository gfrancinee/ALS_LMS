<?php
session_start();
require_once '../../includes/db.php'; // Path from admin/LS-oversight/

// --- Security Check: Ensure user is an admin ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

// --- 1. Get Strand ID from URL ---
$strand_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (empty($strand_id)) {
    die("Error: No strand ID provided.");
}

// --- 2. Fetch Strand Details by ID ---
$stmt_strand = $conn->prepare("SELECT * FROM learning_strands WHERE id = ?");
$stmt_strand->bind_param("i", $strand_id);
$stmt_strand->execute();
$strand_result = $stmt_strand->get_result();

if ($strand_result->num_rows === 0) {
    die("Error: Strand not found.");
}
$strand = $strand_result->fetch_assoc();
$stmt_strand->close();

// --- 3. Fetch Enrolled Students ---
// --- FIX: Added 'u.avatar_url' to the SELECT statement ---
$sql_students = "SELECT u.fname, u.lname, u.avatar_url 
                 FROM users u
                 JOIN strand_participants sp ON u.id = sp.student_id
                 WHERE sp.strand_id = ? AND u.role = 'student'
                 ORDER BY u.lname, u.fname";
$stmt_students = $conn->prepare($sql_students);
$stmt_students->bind_param("i", $strand_id);
$stmt_students->execute();
$students_result = $stmt_students->get_result();

// --- 4. Fetch Learning Materials (This query is already correct) ---
$sql_materials = "SELECT label, type FROM learning_materials WHERE strand_id = ? ORDER BY label";
$stmt_materials = $conn->prepare($sql_materials);
$stmt_materials->bind_param("i", $strand_id);
$stmt_materials->execute();
$materials_result = $stmt_materials->get_result();

// --- 5. Fetch Assessments (Quizzes) (This query is already correct) ---
$sql_assessments = "SELECT title FROM assessments WHERE strand_id = ? ORDER BY title";
$stmt_assessments = $conn->prepare($sql_assessments);
$stmt_assessments->bind_param("i", $strand_id);
$stmt_assessments->execute();
$assessments_result = $stmt_assessments->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Strand Details: <?= htmlspecialchars($strand['strand_title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/learning_strand_details.css">
</head>

<body class="bg-light">

    <header class="topbar sticky-top d-flex justify-content-between align-items-center px-4 py-3">
        <div class="d-flex align-items-left">
            <h1 class="title m-0">
                <div id="font">
                    <span>A</span><span>L</span><span>S</span> Learning Management System
                </div>
            </h1>
        </div>
        <div class="top-icons d-flex align-items-center gap-3">
            <img src="../../img/ALS.png" class="top-logo" alt="ALS Logo" />
            <img src="../../img/BNHS.jpg" class="top-logo" alt="BNHS Logo" />
        </div>
    </header>

    <main class="content">
        <div class="container py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Details for "<?= htmlspecialchars($strand['strand_title']) ?>"</h2>
                <div class="mb-3">
                    <a href="ls.php" class="back-link">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-value"><?= htmlspecialchars($strand['strand_code']) ?></div>
                        <div class="stat-label text-primary"><i class="bi bi-hash me-1"></i>Strand Code</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-value"><?= htmlspecialchars($strand['grade_level']) ?></div>
                        <div class="stat-label text-success"><i class="bi bi-mortarboard-fill me-2"></i>Grade Level</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-value"><?= $students_result->num_rows ?></div>
                        <div class="stat-label text-info"><i class="bi bi-people-fill me-2"></i>Enrolled Students</div>
                    </div>
                </div>
            </div>

            <div class="row g-4">

                <div class="col-lg-8">

                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i> Learning Materials</h5>
                        </div>
                        <div class="card-body">
                            <table class="table">
                                <tbody>
                                    <?php if ($materials_result->num_rows > 0): ?>
                                        <?php while ($material = $materials_result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    $icon = 'bi-file-earmark-text';
                                                    if (strtolower($material['type']) == 'video') $icon = 'bi-youtube text-danger';
                                                    if (strtolower($material['type']) == 'link') $icon = 'bi-link-45deg text-info';
                                                    if (strtolower($material['type']) == 'pdf') $icon = 'bi-file-earmark-pdf text-danger';
                                                    ?>
                                                    <i class="bi <?= $icon ?> me-3 fs-5"></i>
                                                    <?= htmlspecialchars($material['label']) ?>
                                                </td>
                                                <td class="text-end text-muted"><?= htmlspecialchars($material['type']) ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td class="text-muted text-center p-3 shadow-sm">
                                                <i class="bi bi-file-earmark-text me-1"></i>
                                                No materials have been added to this strand.
                                            </td>
                                        <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="mb-0"><i class="bi bi-clipboard-check me-2 text-success"></i> Assessments</h5>
                        </div>
                        <div class="card-body">
                            <table class="table">
                                <tbody>
                                    <?php if ($assessments_result->num_rows > 0): ?>
                                        <?php while ($assessment = $assessments_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><i class="bi bi-clipboard-check me-3 fs-5"></i> <?= htmlspecialchars($assessment['title']) ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td class="text-muted text-center p-3 shadow-sm">
                                                <i class="bi bi-clipboard-check me-1"></i>
                                                No assessments have been created for this strand.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 sticky-top" style="top: 100px;">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="mb-0"><i class="bi bi-people-fill me-2 text-info"></i> Enrolled Students</h5>
                        </div>
                        <div class="card-body" style="max-height: 60vh; overflow-y: auto;">
                            <table class="table">
                                <tbody>
                                    <?php if ($students_result->num_rows > 0): ?>
                                        <?php while ($student = $students_result->fetch_assoc()): ?>
                                            <tr>
                                                <td class="shadow-sm">
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($student['avatar_url'])): ?>
                                                            <img src="../../<?= htmlspecialchars($student['avatar_url']) ?>"
                                                                alt="Avatar"
                                                                class="rounded-circle me-3"
                                                                style="width: 32px; height: 32px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <i class="bi bi-person-circle me-3 text-muted" style="font-size: 32px;"></i>
                                                        <?php endif; ?>
                                                        <span><?= htmlspecialchars($student['fname'] . ' ' . $student['lname']) ?></span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td class="text-muted text-center p-3 shadow-sm">No students are enrolled.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>