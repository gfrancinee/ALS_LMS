<?php
$allowed_roles = ['student'];
require_once '../includes/auth.php';
require_once '../includes/db.php';

$current_tab = 'courses';
$student_id = $_SESSION['user_id'];

// Fetch strands the student is ENROLLED IN
$stmt = $conn->prepare("
    SELECT ls.id, ls.strand_title, ls.description, ls.grade_level, ls.strand_code
    FROM learning_strands ls
    JOIN strand_participants sp ON ls.id = sp.strand_id
    WHERE sp.student_id = ?
    ORDER BY ls.strand_title ASC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$strands = $stmt->get_result();

// Fetch student details for the profile menu
$stmt_user = $conn->prepare("SELECT fname, lname, avatar_url FROM users WHERE id = ?");
$stmt_user->bind_param("i", $student_id);
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
    $userName = 'Student';
    // Make sure this variable exists even in the fallback case
    $avatar_path = '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Student Homepage | ALS LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="css/student.css" />
    <script src="js/student.js" defer></script>
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
            <img src="../img/ALS.png" class="top-logo" alt="ALS Logo" />
            <img src="../img/BNHS.jpg" class="top-logo" alt="BNHS Logo" />
        </div>
    </header>

    <aside class="sidebar position-fixed top-0 start-0 d-flex flex-column align-items-center pt-5 gap-2 shadow" style="width: 65px; height: 100vh;">
        <a href="#" class="sidebar-link d-flex justify-content-center align-items-center <?php echo ($current_tab === 'courses') ? 'active-tab' : ''; ?>" data-tab="courses"><i class="bi bi-book-fill fs-4" aria-label="Courses"></i></a>
        <a href="#" class="sidebar-link d-flex justify-content-center align-items-center <?php echo ($current_tab === 'messages') ? 'active-tab' : ''; ?>" data-tab="messages"><i class="bi bi-envelope fs-4" aria-label="Messages"></i></a>
        <a href="#" class="sidebar-link d-flex justify-content-center align-items-center <?php echo ($current_tab === 'notifications') ? 'active-tab' : ''; ?>" data-tab="notifications"><i class="bi bi-bell fs-4" aria-label="Notifications"></i></a>
        <div class="dropdown dropend">
            <a href="#" class="sidebar-link d-flex justify-content-center align-items-center" data-bs-toggle="dropdown" aria-expanded="false">
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
        <div class="section-title h4 fw-semibold ms-3 mb-4">My Learning Strands</div>

        <!-- Strand Cards -->
        <div class="row mt-4">
            <?php if ($strands && $strands->num_rows > 0): ?>
                <?php while ($strand = $strands->fetch_assoc()): ?>
                    <div class="col-md-4 mb-3">
                        <a href="../strand/strand.php?id=<?= $strand['id'] ?>" class="text-decoration-none">
                            <div class="card h-100 strand-card">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title mb-2"><?= htmlspecialchars($strand['strand_title']) ?></h5>
                                    <p class="card-text text-muted flex-grow-1"><?= htmlspecialchars($strand['description']) ?></p>
                                    <div>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($strand['grade_level']) ?></span>
                                        <span class="badge bg-primary"><?= htmlspecialchars($strand['strand_code']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info mx-3">You are not yet enrolled in any learning strands.</div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>