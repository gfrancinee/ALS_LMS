<?php
require_once '../../includes/auth.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>View Roles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/view-roles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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
            <h2 class="mb-0">View Roles</h2>
            <div class="mb-3">
                <a href="../admin.php" class="back-link">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <main class="d-flex justify-content-center align-items-start py-4">
            <div class="accordion-container" style="width: 100%; max-width: 800px;">
                <div class="accordion" id="userAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="studentHeading">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#student" aria-expanded="true" aria-controls="student">
                                Student
                            </button>
                        </h2>
                        <div id="student" class="accordion-collapse collapse show" aria-labelledby="studentHeading" data-bs-parent="#userAccordion">
                            <div class="accordion-body">
                                <p>View materials, courses, or simulate student experience for Student.</p>
                                <a href="view-as-student.php?student" class="btn btn-outline-primary">View as Student</a>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="teacherHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#teacherCollapse" aria-expanded="false" aria-controls="teacherCollapse">
                                Teacher
                            </button>
                        </h2>
                        <div id="teacherCollapse" class="accordion-collapse collapse" aria-labelledby="teacherHeading" data-bs-parent="#userAccordion">
                            <div class="accordion-body">
                                <p>View materials, courses, or simulate teacher experience for Teacher.</p>
                                <a href="view-as-teacher.php?teacher" class="btn btn-outline-success">View as Teacher</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>