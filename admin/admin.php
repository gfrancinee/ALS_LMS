<?php
$allowed_roles = ['admin'];
require_once '../includes/auth.php';
require_once '../includes/functions.php';
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

    <div class="container-fluid full-height d-flex align-items-center justify-content-center">
        <div class="row w-100">
            <main class="col-md-8 mx-auto px-4 py-5 text-center">
                <h2 class="mb-5">Admin Controls</h2>
                <div class="d-grid gap-3 col-8 mx-auto">
                    <a href="view-roles/view-roles.php" class="btn solid-btn btn-lg">View Roles</a>
                    <a href="admin-users/admin-users.php" class="btn solid-btn btn-lg">User Management</a>
                    <a href="LS oversight/ls.php" class="btn solid-btn btn-lg">LS Oversight</a>
                </div>
            </main>
        </div>
    </div>
    <button id="logoutBtn" class="btn btn-danger mb-2" onclick="window.location.href='../logout.php'">
        <i class="bi bi-box-arrow-left"></i>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>