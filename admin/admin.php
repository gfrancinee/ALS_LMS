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
    <style>
        #messages-icon-float {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1050;
            /* Ensure it's above other content */
            background-color: var(--bs-primary);
            /* Use theme primary color, or choose another */
            color: white;
            border-radius: 50%;
            width: 55px;
            /* Adjust size as needed */
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            text-decoration: none;
            /* Remove underline from link */
        }

        #messages-icon-float:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }

        #messages-icon-float i {
            font-size: 1.75rem;
            /* Adjust icon size */
        }

        /* Style for logout button to be fixed bottom-left */
        #logoutBtn {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 1050;
        }

        /* Adjust main content padding if needed to avoid overlap */
        body {
            padding-bottom: 80px;
            /* Add padding to prevent content hiding behind fixed buttons */
        }
    </style>
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
            <img src="../img/ALS.png" class="top-logo" alt="ALS Logo" /> <img src="../img/BNHS.jpg" class="top-logo" alt="BNHS Logo" />
        </div>
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

    <a id="logout-icon-float" href="../logout.php" class="btn btn-sm btn-danger ms-3">
        <i class="bi bi-box-arrow-left"></i>
    </a>
    <a id="messages-icon-float" href="#" title="Messages">
        <i class="bi bi-chat-dots-fill"></i> </a>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>