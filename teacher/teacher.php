<?php
$allowed_roles = ['teacher'];
require_once '../includes/auth.php';
require_once '../includes/db.php';

$current_tab = 'courses';
$teacher_id = $_SESSION['user_id'];

// Optional: Show success message after strand creation
$success_message = '';
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif;

// Securely fetch strands created by this teacher
$stmt = $conn->prepare("SELECT * FROM learning_strands WHERE creator_id = ? ORDER BY date_created DESC");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$strands = $stmt->get_result();

// Fetch student details for the profile menu
$stmt_user = $conn->prepare("SELECT fname, lname, avatar_url FROM users WHERE id = ?");
$stmt_user->bind_param("i", $teacher_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
$currentUser = $user_result->fetch_assoc();

// Check if a user was found and set variables safely
if ($currentUser) {
    $userName = htmlspecialchars($currentUser['fname'] . ' ' . $currentUser['lname']);

    // This variable will ONLY be set if a custom avatar exists
    $avatar_path = '';
    if (!empty($currentUser['avatar_url'])) {
        $avatar_path = '../' . htmlspecialchars($currentUser['avatar_url']);
    }
} else {
    $userName = 'Teacher';
    // Make sure this variable exists even in the fallback case
    $avatar_path = '';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Teacher homepage | ALS LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="css/teacher.css" />
    <script src="js/teacher.js" defer></script>
</head>

<body>
    <script>
        const currentUserId = <?= $_SESSION['user_id'] ?? 'null'; ?>;
    </script>

    <header class="topbar sticky-top d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
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

    <aside class="sidebar position-fixed top-0 start-0 d-flex flex-column align-items-center pt-5 gap-2 shadow" style="width: 65px; height: 100vh;">

        <a href="#" class="sidebar-link d-flex justify-content-center align-items-center active-tab" data-tab="courses"><i class="bi bi-book-fill fs-4" aria-label="Courses"></i></a>

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
                    </div>
                </li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="../profile.php"><i class="bi bi-person-circle me-2"></i>Profile</a></li>
                <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Log Out</a></li>
            </ul>
        </div>
    </aside>

    <main class="content ms-5 pt-4 px-4">
        <div class="section-title h4 fw-semibold ms-3 mb-4">My Learning Strand</div>

        <?php
        // ADD THIS BLOCK to display the success message
        if (isset($_SESSION['success_message'])) {
            // This is a custom style to match your screenshot's green alert box
            echo '<div class="alert" style="background-color: #d4edda; color: #155724; border-color: #c3e6cb; padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.25rem;">';
            echo htmlspecialchars($_SESSION['success_message']);
            echo '</div>';
            // IMPORTANT: Clear the message so it doesn't reappear on refresh
            unset($_SESSION['success_message']);
        }
        ?>

        <!-- Create Button -->
        <button class="strand-button btn d-inline-flex align-items-center gap-2 px-3 py-2 ms-3" data-bs-toggle="modal" data-bs-target="#createStrandModal">
            <i class="bi bi-plus-circle"></i>
            <span>Create Learning Strand</span>
        </button>

        <!-- Strand Cards -->
        <div class="row mt-4">
            <?php while ($strand = $strands->fetch_assoc()): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 strand-card">
                        <!-- Three-dots dropdown menu -->
                        <div class="dropdown card-options">
                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <button type="button" class="dropdown-item edit-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editStrandModal"
                                        data-strand-id="<?= htmlspecialchars($strand['id']) ?>"
                                        data-title="<?= htmlspecialchars($strand['strand_title']) ?>"
                                        data-code="<?= htmlspecialchars($strand['strand_code']) ?>"
                                        data-grade="<?= htmlspecialchars($strand['grade_level']) ?>"
                                        data-desc="<?= htmlspecialchars($strand['description']) ?>">
                                        <i class="bi bi-pencil-square me-2 text-success"></i>Edit
                                    </button>
                                </li>
                                <li>
                                    <button type="button" class="dropdown-item text-danger delete-btn" data-bs-toggle="modal" data-bs-target="#deleteStrandModal" data-bs-id="<?= $strand['id'] ?>">
                                        <i class="bi bi-trash3 me-2"></i>Delete
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- Card Content -->
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title mb-2">
                                <!-- The stretched-link makes the whole card clickable -->
                                <a href="../strand/strand.php?id=<?= $strand['id'] ?>" class="text-decoration-none text-dark stretched-link">
                                    <?= htmlspecialchars($strand['strand_title']) ?>
                                </a>
                            </h5>
                            <p class="card-text text-muted flex-grow-1"><?= htmlspecialchars($strand['description']) ?></p>
                            <div>
                                <span class="badge bg-secondary"><?= htmlspecialchars($strand['grade_level']) ?></span>
                                <span class="badge bg-primary"><?= htmlspecialchars($strand['strand_code']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </main>

    <!-- Modal -->
    <div class="modal fade" id="createStrandModal" tabindex="-1" aria-labelledby="createStrandLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form action="../ajax/create-strand.php" method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createStrandLabel">Create Learning Strand</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createStrandForm" method="POST" action="../ajax/create-strand.php">
                    <div class="modal-body">
                        <!-- Removed hidden creator_id field -->

                        <div class="mb-3">
                            <label for="strandTitle" class="form-label">Strand Title</label>
                            <input type="text" class="form-control" name="strand_title" required>
                        </div>

                        <div class="mb-3">
                            <label for="strandCode" class="form-label">Strand Code</label>
                            <input type="text" class="form-control" name="strand_code" required>
                        </div>

                        <div class="mb-3">
                            <label for="gradeLevel" class="form-label">Grade Level</label>
                            <select class="form-select" name="grade_level" required>
                                <option value="">Select Grade</option>
                                <option value="Grade 11">Grade 11</option>
                                <option value="Grade 12">Grade 12</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Create Strand</button>
                    </div>
                </form>
        </div>
    </div>

    <div class="modal fade" id="editStrandModal" tabindex="-1" aria-labelledby="editStrandLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form id="editStrandForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editStrandLabel">Edit Learning Strand</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit-strand-id">

                    <div class="mb-3">
                        <label for="edit-strand-title" class="form-label">Strand Title</label>
                        <input type="text" class="form-control" id="edit-strand-title" name="strand_title" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-strand-code" class="form-label">Strand Code</label>
                        <input type="text" class="form-control" id="edit-strand-code" name="strand_code" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-grade-level" class="form-label">Grade Level</label>
                        <select class="form-select" id="edit-grade-level" name="grade_level" required>
                            <option value="Grade 11">Grade 11</option>
                            <option value="Grade 12">Grade 12</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit-description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit-description" name="description" rows="3" required></textarea>
                    </div>
                    <input type="hidden" name="strand_id" id="editStrandIdInput" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="deleteStrandModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this learning strand?
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="strand_id" id="deleteStrandId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteStrandBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Chat Modal -->
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
                        <input type="text" id="chat-message-input" name="message_text" class="form-control" placeholder="Type a message..." autocomplete="off" required>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const currentUserId = <?php echo $_SESSION['user_id']; ?>;
    </script>
</body>

</html>