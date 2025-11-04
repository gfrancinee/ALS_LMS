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
    <div class="dropend" id="messages-icon-float-wrapper" style="position: fixed; bottom: 20px; right: 20px; z-index: 1050;">

        <a href="#" class="position-relative" id="messages-icon-float"
            data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
            style="background-color: var(--bs-primary); color: white; border-radius: 50%; width: 55px; height: 55px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15); text-decoration: none;">

            <i class="bi bi-chat-dots-fill" style="font-size: 1.75rem;"></i>

            <span id="message-notification-dot" class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle d-none">
                <span class="visually-hidden">New messages</span>
            </span>
        </a>

        <div class="dropdown-menu shadow" id="messages-dropdown" aria-labelledby="messages-icon-float" style="width: 350px;">
            <div class="px-3 pt-2">
                <h5 class="mb-0">Messages</h5>
                <hr class="my-2">
                <div class="search-wrapper mb-2">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" class="form-control pill-search" placeholder="Search for people...">
                </div>
                <hr class="my-2">
            </div>
            <div class="list-group list-group-flush" id="conversation-list" style="max-height: 400px; overflow-y: auto;">
                <div class="text-center text-muted p-5" id="no-messages-placeholder">
                    No messages yet.
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="chatModal" tabindex="-1" aria-labelledby="chatModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div id="chat-modal-header" class="d-flex align-items-center"></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="chat-modal-body">
                </div>
                <div class="modal-footer">
                    <form id="message-form" class="w-100 d-flex gap-2">
                        <input type="hidden" id="chat-conversation-id" name="conversation_id">

                        <input type="text" id="chat-message-input" name="message_text" class="form-control" placeholder="Type a message..." required autocomplete="off">

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const currentUserId = <?php echo json_encode($_SESSION['user_id']); ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/admin.js"></script>
</body>

</html>