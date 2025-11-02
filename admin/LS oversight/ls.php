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
    $recently_added_course = 'CONNECTION FAILED'; // <-- Use your original variable
    $recently_added_course_id = 0;
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

        // --- FIX #1: Select both 'id' and 'strand_title' ---
        $result_latest = $conn->query("SELECT id, strand_title FROM learning_strands ORDER BY id DESC LIMIT 1");
        $latest_row = $result_latest ? $result_latest->fetch_assoc() : null;

        $recently_added_course = $latest_row ? $latest_row['strand_title'] : 'None Added';
        // --- FIX #2: Add this line to store the ID ---
        $recently_added_course_id = $latest_row ? $latest_row['id'] : 0;
    } catch (Exception $e) {
        // Handle database query exceptions
        $total_courses = 'Query Error';
        $active_courses = 'Query Error';
        $courses_needing_review = 'Query Error';
        $recently_added_course = 'Query Error';
        // --- FIX #3: Add this line to prevent errors on failure ---
        $recently_added_course_id = 0;
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
    <header class="topbar sticky-top d-flex justify-content-between align-items-center px-4 py-3">
        <div class="d-flex align-items-left">
            <h1 class="title m-0">
                <div id="font">
                    <span>A</span><span>L</span><span>S</span> Learning Management System
                </div>
            </h1>
        </div>
        <div class="top-icons d-flex align-items-center gap-3">
            <img src="../img/ALS.png" class="top-logo" alt="ALS Logo" />
            <img src="../img/BNHS.jpg" class="top-logo" alt="BNHS Logo" />
        </div>
    </header>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Learning Strand Oversight</h2>
            <div class="mb-3">
                <a href="../admin.php" class="back-link">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-value text-primary"><?php echo $total_courses; ?></div>
                    <div class="stat-label text-primary">
                        <i class="bi bi-book me-2"></i>
                        Total Learning Strands
                    </div>
                    <p class="stat-sublabel">Total learning strands in the system.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-value text-success"><?php echo $active_courses; ?></div>
                    <div class="stat-label text-success">
                        <i class="bi bi-check-circle me-2"></i>
                        Active Learning Strands
                    </div>
                    <p class="stat-sublabel">Learning Strands currently available to learners.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-value text-danger"><?php echo $courses_needing_review; ?></div>
                    <div class="stat-label text-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Materials Needing Review
                    </div>
                    <p class="stat-sublabel">Materials flagged for content or quality check.</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12 mx-auto">
                <div class="card shadow-lg" style="border: none; border-radius: 0.75rem;">
                    <div class="card-header bg-light" style="border-radius: 0.75rem 0.75rem 0 0; border: none; padding: 1.25rem 1.5rem;">
                        <h4 class="mb-0"><i class="bi bi-list-columns-reverse me-2"></i> Learning Strand Management </h4>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle management-table">
                            <tbody>
                                <tr>
                                    <td>Manage Learning Strands & Materials</td>
                                    <td class="text-center">
                                        <a href="strand_materials_editor.php" class="btn btn-outline-primary rounded-pill px-3">Go to Editor</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td>View Quiz Performance Data</td>
                                    <td class="text-center">
                                        <a href="quiz_performance.php" class="btn btn-outline-info rounded-pill px-3">View Attempts</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Review Student Answers</td>
                                    <td class="text-center">
                                        <a href="quiz_performance.php" class="btn btn-outline-secondary rounded-pill px-3">Start Review</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Latest Added: <span class="fw-bold text-success ms-2"><?php echo htmlspecialchars($recently_added_course); ?></span></td>
                                    <td class="text-center">
                                        <a href="strand_details.php?id=<?php echo $recently_added_course_id; ?>" class="btn btn-outline-success rounded-pill px-3">Details</a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>