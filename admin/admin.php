<?php
$allowed_roles = ['admin'];
require_once '../includes/auth.php';
require_once '../includes/functions.php'; // Make sure this file exists or remove if not needed
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin homepage | ALS LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/admin.css">
    <script src="js/admin.js" defer></script>
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

    <div class="container py-5">
        <div class="row">
            <div class="col-12 text-center mb-5">
                <h2 class="display-5 fw-normal">Admin Controls</h2>
            </div>
        </div>

        <div class="row g-4 justify-content-center">

            <div class="col-md-4">
                <a href="view-roles/view-roles.php" class="action-card">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="action-icon text-primary">
                                <i class="bi bi-person-video3"></i>
                            </div>
                            <h5 class="card-title">View Roles</h5>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="admin-users/admin-users.php" class="action-card">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="action-icon text-success">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <h5 class="card-title">User Management</h5>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="LS oversight/ls.php" class="action-card">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="action-icon text-info">
                                <i class="bi bi-book-half"></i>
                            </div>
                            <h5 class="card-title">LS Oversight</h5>
                        </div>
                    </div>
                </a>
            </div>

        </div>
    </div>

    <a id="logout-icon-float" href="../logout.php" class="btn btn-sm btn-danger ms-3">
        <i class="bi bi-box-arrow-left"></i>
    </a>
    <a id="messages-icon-float" href="#" title="Messages">
        <i class="bi bi-chat-dots-fill"></i> </a>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>