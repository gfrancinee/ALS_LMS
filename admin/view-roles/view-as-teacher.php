<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// Only allow admin to access this page
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit;
}

// Get the selected grade level from URL
$selected_grade = $_GET['grade'] ?? '';

// Validate grade level
if (!in_array($selected_grade, ['grade_11', 'grade_12'])) {
    header("Location: view-roles.php");
    exit;
}

// Map grade_level to database format
$db_grade = ($selected_grade === 'grade_11') ? 'Grade 11' : 'Grade 12';
$display_grade = ($selected_grade === 'grade_11') ? 'Grade 11' : 'Grade 12';

// Fetch all unique learning strands for the selected grade level with correct student count
$stmt = $conn->prepare("
    SELECT ls.id, ls.strand_title, ls.strand_code, ls.grade_level, ls.description, ls.date_created,
           u.fname, u.lname,
           (SELECT COUNT(DISTINCT sp2.student_id) 
            FROM strand_participants sp2 
            JOIN users u2 ON sp2.student_id = u2.id
            WHERE sp2.strand_id = ls.id AND u2.role = 'student') as student_count
    FROM learning_strands ls
    LEFT JOIN users u ON ls.creator_id = u.id
    WHERE ls.grade_level = ?
    ORDER BY ls.date_created DESC
");
$stmt->bind_param("s", $db_grade);
$stmt->execute();
$result = $stmt->get_result();
$strands = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch admin user details for profile display
$admin_id = $_SESSION['user_id'];
$stmt_user = $conn->prepare("SELECT fname, lname, avatar_url FROM users WHERE id = ?");
$stmt_user->bind_param("i", $admin_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
$currentUser = $user_result->fetch_assoc();

if ($currentUser) {
    $userName = htmlspecialchars($currentUser['fname'] . ' ' . $currentUser['lname']);
    $avatar_path = '';
    if (!empty($currentUser['avatar_url'])) {
        $avatar_path = '../../' . htmlspecialchars($currentUser['avatar_url']);
    }
} else {
    $userName = 'Admin';
    $avatar_path = '';
}

$grade_param = ($db_grade === 'Grade 11') ? 'grade_11' : 'grade_12';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>View as Teacher - <?= htmlspecialchars($display_grade) ?> | ALS LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="css/view-as-teacher.css" />
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
            <img src="../../img/ALS.png" class="top-logo" alt="ALS Logo" />
            <img src="../../img/BNHS.jpg" class="top-logo" alt="BNHS Logo" />
        </div>
    </header>

    <aside class="sidebar position-fixed top-0 start-0 d-flex flex-column align-items-center pt-5 gap-2 shadow" style="width: 65px; height: 100vh;">
        <a href="#" class="sidebar-link d-flex justify-content-center align-items-center active-tab">
            <i class="bi bi-book-fill fs-4" aria-label="Courses"></i>
        </a>

        <!-- Messages -->
        <div class="dropdown dropend">
            <a href="#" class="sidebar-link d-flex justify-content-center align-items-center position-relative" id="messages-icon-wrapper" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <i class="bi bi-chat-dots-fill fs-4" aria-label="Messages"></i>
                <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle d-none" id="message-notification-dot"></span>
            </a>
            <div class="dropdown-menu shadow" id="messages-dropdown" aria-labelledby="messages-icon-wrapper">
                <div class="px-3 pt-2" style="width: 350px;">
                    <h5 class="mb-0">Messages</h5>
                    <hr class="my-2">
                    <div class="input-group mb-2">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" placeholder="Search for people...">
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

        <!-- Notifications -->
        <div class="dropdown dropend">
            <a href="#" class="sidebar-link d-flex justify-content-center align-items-center position-relative" id="notifications-icon-wrapper" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <i class="bi bi-bell-fill fs-4" aria-label="Notifications"></i>
                <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle d-none" id="general-notification-dot"></span>
            </a>
            <div class="dropdown-menu shadow" id="notifications-dropdown" aria-labelledby="notifications-icon-wrapper">
                <div class="px-3 pt-2" style="width: 350px;">
                    <h5 class="mb-0">Notifications</h5>
                    <hr class="my-2">
                </div>
                <div class="list-group list-group-flush" id="notification-list" style="max-height: 400px; overflow-y: auto;">
                    <div class="text-center text-muted p-5" id="no-notifications-placeholder">
                        No new notifications.
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile -->
        <div class="dropdown dropend">
            <a href="#" class="sidebar-link d-flex justify-content-center align-items-center" data-bs-toggle="dropdown">
                <i class="bi bi-person-fill fs-4" aria-label="Profile"></i>
            </a>
            <ul class="dropdown-menu">
                <li>
                    <div class="dropdown-header text-center">
                        <?php if (!empty($avatar_path)): ?>
                            <img src="<?= $avatar_path ?>" class="rounded-circle mb-2" alt="User Avatar" width="50" height="50" style="object-fit: cover;">
                        <?php else: ?>
                            <i class="bi bi-person-circle mb-2" style="font-size: 50px; color: #6c757d;"></i>
                        <?php endif; ?>
                        <h6 class="mb-0"><?= $userName ?></h6>
                        <small class="text-muted">Viewing as Teacher</small>
                    </div>
                </li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="../../profile.php"><i class="bi bi-person-circle me-2"></i>Profile</a></li>
                <li><a class="dropdown-item" href="../../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Log Out</a></li>
            </ul>
        </div>

        <a href="view-roles.php" class="sidebar-link d-flex justify-content-center align-items-center" title="Back to View Roles">
            <i class="bi bi-arrow-left fs-4"></i>
        </a>
    </aside>

    <main class="content ms-5 pt-4 px-4">
        <div class="section-title h4 fw-semibold ms-3 mb-4">Learning Strands - <?= htmlspecialchars($display_grade) ?></div>

        <!-- Read-Only Alert -->
        <div class="alert" style="background-color: #fff3cd; color: #856404; border-color: #ffeeba; padding: 1rem; margin-bottom: 1.5rem; margin-left: 1rem; margin-right: 1rem; border-radius: 0.25rem;">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Read-Only Mode:</strong> You are viewing as a teacher for <?= htmlspecialchars($display_grade) ?>. All management features are disabled.
        </div>

        <!-- Create Button (disabled) -->
        <button class="strand-button btn d-inline-flex align-items-center gap-2 px-3 py-2 ms-3" style="opacity: 0.6; cursor: not-allowed;" onclick="alert('This is a read-only view. You cannot create learning strands.')">
            <i class="bi bi-plus-circle"></i>
            <span>Create Learning Strand</span>
        </button>

        <!-- Strand Cards -->
        <div class="row mt-4">
            <?php if (empty($strands)): ?>
                <div class="col-12">
                    <div class="alert alert-info mx-3">
                        <i class="bi bi-info-circle me-2"></i>
                        No learning strands found for <?= htmlspecialchars($display_grade) ?>.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($strands as $strand): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 strand-card position-relative">
                            <!-- Three-dots dropdown menu -->
                            <div class="dropdown card-options position-absolute" style="top: 10px; right: 10px; z-index: 10;">
                                <button class="btn btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="opacity: 0.7; cursor: not-allowed;">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button type="button" class="dropdown-item" onclick="alert('This is a read-only view. You cannot edit learning strands.')" style="cursor: not-allowed;">
                                            <i class="bi bi-pencil-square me-2 text-success"></i>Edit
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item text-danger" onclick="alert('This is a read-only view. You cannot delete learning strands.')" style="cursor: not-allowed;">
                                            <i class="bi bi-trash3 me-2"></i>Delete
                                        </button>
                                    </li>
                                </ul>
                            </div>

                            <a href="view-strand.php?id=<?= $strand['id'] ?>&view=teacher" class="text-decoration-none">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="card-title mb-0 flex-grow-1 text-dark"><?= htmlspecialchars($strand['strand_title']) ?></h5>
                                        <span class="badge bg-primary ms-2 mx-4"><?= htmlspecialchars($strand['strand_code']) ?></span>
                                    </div>

                                    <div class="card-text text-muted flex-grow-1 description-preview">
                                        <?= $strand['description'] ?>
                                    </div>

                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center text-muted small mb-2">
                                            <div>
                                                <i class="bi bi-person-fill me-1"></i>
                                                <?= htmlspecialchars($strand['fname'] . ' ' . $strand['lname']) ?>
                                            </div>
                                            <div>
                                                <i class="bi bi-people-fill me-1"></i>
                                                <?= $strand['student_count'] ?> student<?= $strand['student_count'] != 1 ? 's' : '' ?>
                                            </div>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            Created <?= date('M d, Y', strtotime($strand['date_created'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>