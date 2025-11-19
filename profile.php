<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = 'info';

$dashboard_link = 'login.php';
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'student') {
        $dashboard_link = 'student/student.php';
    } else if ($_SESSION['role'] === 'teacher') {
        $dashboard_link = 'teacher/teacher.php';
    }
}

// --- Part 1: Handle Form Submission (POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Fetch CURRENT data to check for changes and existing LRN
    $stmt_check = $conn->prepare("SELECT fname, lname, address, phone, grade_level, lrn FROM users WHERE id = ?");
    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $current_data = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    // 2. Get Form Data
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $gradeLevel = isset($_POST['gradeLevel']) ? trim($_POST['gradeLevel']) : null;
    $lrn = isset($_POST['lrn']) ? trim($_POST['lrn']) : null;

    $current_grade = $current_data['grade_level'] ?? '';
    $current_lrn = $current_data['lrn'] ?? '';

    // 3. Check if a file was uploaded
    $has_file_upload = (isset($_FILES["avatar_image"]) && $_FILES["avatar_image"]["error"] == 0);

    // 4. Compare Data
    $has_changes = (
        $fname !== $current_data['fname'] ||
        $lname !== $current_data['lname'] ||
        $address !== $current_data['address'] ||
        $phone !== $current_data['phone'] ||
        $gradeLevel !== $current_grade ||
        $lrn !== $current_lrn ||
        $has_file_upload
    );

    if (!$has_changes) {
        $message = "No changes were made to your profile.";
        $message_type = "warning";
    } else {

        $sql = "UPDATE users SET fname = ?, lname = ?, address = ?, phone = ?, grade_level = ?";
        $types = "sssss";
        $params = [$fname, $lname, $address, $phone, $gradeLevel];

        // --- SMART LRN LOGIC ---
        $requires_reverification = false;

        if (!empty($lrn) && $lrn !== $current_lrn) {
            // Scenario A: LRN is being changed from something to something else
            if (!empty($current_lrn)) {
                // They had an LRN, but changed it. SUSPICIOUS -> Set to Pending (0)
                $sql .= ", lrn = ?, is_admin_verified = 0";
                $requires_reverification = true;
            } else {
                // Scenario B: Old account adding LRN for the first time. TRUST -> Set to Verified (1)
                $sql .= ", lrn = ?, is_admin_verified = 1";
            }
            $types .= "s";
            $params[] = $lrn;
        } elseif (!empty($lrn)) {
            // LRN exists but wasn't changed
            $sql .= ", lrn = ?";
            $types .= "s";
            $params[] = $lrn;
        }

        $sql .= " WHERE id = ?";
        $types .= "i";
        $params[] = $user_id;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $message = "Information updated successfully!";

            // Only show the warning if we actually triggered re-verification
            if ($requires_reverification) {
                $message .= " (Note: LRN changed. Admin re-verification required.)";
            }

            $message_type = 'success';
        } else {
            $message = "Error updating information.";
            $message_type = 'danger';
        }

        // Handle avatar upload (Unchanged)
        if ($has_file_upload) {
            $uploadDir = __DIR__ . "/uploads/avatars/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $file_extension = pathinfo($_FILES["avatar_image"]["name"], PATHINFO_EXTENSION);
            $file_name = "user_" . $user_id . "_" . time() . "." . $file_extension;
            $target_file_system = $uploadDir . $file_name;
            $target_file_db = "uploads/avatars/" . $file_name;

            if (move_uploaded_file($_FILES["avatar_image"]["tmp_name"], $target_file_system)) {
                $stmt = $conn->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
                $stmt->bind_param("si", $target_file_db, $user_id);
                $stmt->execute();
                if ($message_type !== 'danger') {
                    $message = "Profile updated successfully!";
                    if ($requires_reverification) {
                        $message .= " (Note: LRN changed. Admin re-verification required.)";
                    }
                }
            }
        }
    }
}

// --- Part 2: Fetch Current User Data (For Display) ---
$stmt = $conn->prepare("SELECT fname, lname, email, address, phone, avatar_url, grade_level, role, lrn FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$avatar_path = !empty($user['avatar_url']) ? htmlspecialchars($user['avatar_url']) : '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | ALS LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="css/profile.css" />

    <style>
        /* Floating Modern Alert CSS */
        .floating-alert {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            min-width: 350px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            border-radius: 50px;
            border: none;
            text-align: center;
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideDown {
            from {
                top: -100px;
                opacity: 0;
            }

            to {
                top: 20px;
                opacity: 1;
            }
        }
    </style>
</head>

<body class="bg-light">

    <?php if ($message): ?>
        <div id="autoDismissAlert" class="alert alert-<?= $message_type ?> floating-alert d-flex align-items-center justify-content-center gap-2" role="alert">
            <?php if ($message_type == 'success'): ?>
                <i class="bi bi-check-circle-fill fs-5"></i>
            <?php elseif ($message_type == 'warning'): ?>
                <i class="bi bi-exclamation-circle-fill fs-5"></i>
            <?php else: ?>
                <i class="bi bi-x-circle-fill fs-5"></i>
            <?php endif; ?>
            <span class="fw-medium"><?= $message ?></span>
        </div>
    <?php endif; ?>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm profile-card">
                    <div class="card-header">
                        <h4 class="mb-0">My Profile</h4>
                    </div>
                    <div class="card-body">

                        <form action="profile.php" method="post" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <?php if (!empty($avatar_path)): ?>
                                        <img src="<?= $avatar_path ?>" class="img-thumbnail rounded-circle mb-3" alt="User Avatar" style="width: 150px; height: 150px; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="bi bi-person-circle mb-3" style="font-size: 150px; color: #6c757d;"></i>
                                    <?php endif; ?>
                                    <div class="mb-3">
                                        <label for="avatar_image" class="form-label small">Change Picture</label>
                                        <input class="form-control form-control-sm" type="file" name="avatar_image" id="avatar_image">
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="fname" class="form-label small">First Name</label>
                                            <input type="text" class="form-control" name="fname" id="fname" value="<?= htmlspecialchars($user['fname']) ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="lname" class="form-label small">Last Name</label>
                                            <input type="text" class="form-control" name="lname" id="lname" value="<?= htmlspecialchars($user['lname']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label small">Email Address</label>
                                        <input type="email" class="form-control" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone" class="form-label small">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" id="phone" value="<?= htmlspecialchars($user['phone']) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="address" class="form-label small">Address</label>
                                        <textarea class="form-control" name="address" id="address" rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                    </div>

                                    <?php if ($user['role'] === 'student'): ?>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="gradeLevel" class="form-label small">Grade Level</label>
                                                <select class="form-select" name="gradeLevel" id="gradeLevel">
                                                    <option value="" <?= empty($user['grade_level']) ? 'selected' : '' ?>>Select your grade level</option>
                                                    <option value="grade_11" <?= ($user['grade_level'] === 'grade_11') ? 'selected' : '' ?>>Grade 11</option>
                                                    <option value="grade_12" <?= ($user['grade_level'] === 'grade_12') ? 'selected' : '' ?>>Grade 12</option>
                                                </select>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label for="lrn" class="form-label small">LRN (12 Digits)</label>
                                                <input type="text" class="form-control" name="lrn" id="lrn"
                                                    value="<?= htmlspecialchars($user['lrn'] ?? '') ?>"
                                                    minlength="12" maxlength="12" pattern="[0-9]{12}"
                                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                                <div class="form-text text-muted" style="font-size: 0.75rem;">
                                                    Changing this will require Admin verification.
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <a href="<?= $dashboard_link ?>" class="btn btn-secondary rounded-pill px-3 me-2">Back</a>
                                <button type="submit" class="btn btn-primary rounded-pill px-3"><i class="bi bi-save me-2"></i>Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alertElement = document.getElementById('autoDismissAlert');
            if (alertElement) {
                setTimeout(function() {
                    alertElement.style.transition = "opacity 0.5s ease";
                    alertElement.style.opacity = "0";
                    setTimeout(function() {
                        alertElement.remove();
                    }, 500);
                }, 5000); // 5 seconds
            }
        });
    </script>
</body>

</html>