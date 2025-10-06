<?php
// ls.php (Course Oversight Dashboard)
$allowed_roles = ['admin'];
// Corrected path to go up two levels
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// Check if the included file successfully created the MySQLi connection object
if (!isset($conn) || $conn->connect_error) {
    // If connection failed
    $total_courses = 'DB Error';
    $active_courses = 'DB Error';
    $courses_needing_review = 'DB Error';
    $recently_added_course = 'CONNECTION FAILED';
} else {
    // Connection is good, proceed with MySQLi queries
    try {

        $result_total = $conn->query("SELECT COUNT(*) AS count FROM learning_strands");
        $total_courses = $result_total ? $result_total->fetch_assoc()['count'] : '0';

        // 2. Active Courses (Set equal to Total courses for simple status tracking)
        $active_courses = $total_courses;

        // 3. Materials Needing Review (Counting total learning materials as a metric)
        $result_materials = $conn->query("SELECT COUNT(*) AS count FROM learning_materials");
        $courses_needing_review = $result_materials ? $result_materials->fetch_assoc()['count'] : '0';

        // 4. LATEST COURSE FIX: Changed column name from 'strand_name' to 'strand_title'
        $result_latest = $conn->query("SELECT strand_title FROM learning_strands ORDER BY id DESC LIMIT 1");
        $latest_row = $result_latest ? $result_latest->fetch_assoc() : null;
        $recently_added_course = $latest_row ? $latest_row['strand_title'] : 'None Added';
    } catch (Exception $e) {
        // Handle database query exceptions
        $total_courses = 'Query Error';
        $active_courses = 'Query Error';
        $courses_needing_review = 'Query Error';
        $recently_added_course = 'Query Error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Course Oversight | ALS LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/ls.css">
</head>

<body>
    <header class="topbar sticky-top d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
        <div class="d-flex align-items-left">
            <h1 class="title m-0">
                <div id="font">
                    <span>A</span><span>L</span><span>S</span> Learning Management System
                </div>
            </h1>
        </div>
        <div class="top-icons d-flex align-items-center gap-3">
            <img src="img/ALS.png" class="top-logo" alt="ALS Logo" />
            <img src="img/BNHS.jpg" class="top-logo" alt="BNHS Logo" />
    </header>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">User Management</h2>
            <div class="mb-3">
                <a href="../admin.php" class="back-link">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <div class="container-fluid px-4 pb-5">
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="card shadow-sm h-100 border-primary border-3">
                        <div class="card-body p-3">
                            <h5 class="card-title text-primary"><i class="bi bi-book me-2"></i> Total Learning Strands</h5>
                            <p class="card-text display-5 fw-bold"><?php echo $total_courses; ?></p>
                            <p class="text-muted mb-0">Total learning strands in the system.</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm h-100 border-success border-3">
                        <div class="card-body p-3">
                            <h5 class="card-title text-success"><i class="bi bi-check-circle me-2"></i> Active Learning Strands</h5>
                            <p class="card-text display-5 fw-bold"><?php echo $active_courses; ?></p>
                            <p class="text-muted mb-0">Courses currently available to learners.</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm h-100 border-warning border-3">
                        <div class="card-body p-3">
                            <h5 class="card-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i> Materials Needing Review</h5>
                            <p class="card-text display-5 fw-bold"><?php echo $courses_needing_review; ?></p>
                            <p class="text-muted mb-0">Materials flagged for content or quality check.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card shadow-lg">
                        <div class="card-header bg-light">
                            <h4 class="mb-0"><i class="bi bi-list-columns-reverse me-2"></i> Course Management </h4>
                        </div>
                        <ul class="list-group course-actions-list">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Manage Strands & Materials
                                <a href="strand_materials_editor.php" class="btn btn-primary btn-sm">Go to Editor</a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                View Quiz Performance Data
                                <a href="quiz_performance.php" class="btn btn-info btn-sm">View Attempts</a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Review Student Answers
                                <a href="student_answers_review.php" class="btn btn-secondary btn-sm">Start Review</a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center bg-light">
                                Latest Added Strand: <span class="fw-bold text-success"><?php echo $recently_added_course; ?></span>
                                <a href="strand_details.php?name=<?php echo urlencode($recently_added_course); ?>" class="btn btn-outline-success btn-sm">Details</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>