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

        // Handle avatar upload
        if ($has_file_upload) {
            // Check File Size (10MB = 10 * 1024 * 1024 bytes)
            if ($_FILES["avatar_image"]["size"] > 10485760) {
                $message = "File is too large. Maximum limit is 10MB.";
                $message_type = 'danger';
            } else {
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

                    // Only append success message if previous updates didn't fail
                    if ($message_type !== 'danger') {
                        $message = "Profile updated successfully!";
                        if ($requires_reverification) {
                            $message .= " (Note: LRN changed. Admin re-verification required.)";
                        }
                    }
                } else {
                    $message = "Error uploading file. Please try again.";
                    $message_type = 'danger';
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
            <div class="col-lg-9">
                <div class="card shadow-sm profile-card">
                    <div class="profile-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0 fw-bold text-dark">My Profile</h4>
                        <span class="badge bg-light text-dark border"><?= ucfirst($user['role']) ?> Account</span>
                    </div>
                    <div class="card-body p-4">

                        <form action="profile.php" method="post" enctype="multipart/form-data">
                            <div class="row g-4">
                                <div class="col-md-4 text-center border-end">
                                    <div class="mb-3 position-relative d-inline-block">
                                        <?php if (!empty($avatar_path)): ?>
                                            <img src="<?= $avatar_path ?>" class="rounded-circle shadow-sm" alt="User Avatar" style="width: 160px; height: 160px; object-fit: cover; border: 4px solid #fff;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 160px; height: 160px;">
                                                <i class="bi bi-person-fill text-secondary" style="font-size: 80px;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mb-3">
                                        <label for="avatar_image" class="btn btn-outline-secondary btn-sm w-100">
                                            <i class="bi bi-camera me-2"></i>Change Photo
                                        </label>
                                        <input class="d-none" type="file" name="avatar_image" id="avatar_image" onchange="document.getElementById('file-name').textContent = this.files[0].name">
                                        <div id="file-name" class="small text-muted mt-2 text-truncate"></div>
                                    </div>

                                    <div class="text-muted small">
                                        Allowed: JPG, PNG. Max 10MB.
                                    </div>
                                </div>

                                <div class="col-md-8 ps-md-4">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="fname" class="form-label small">First Name</label>
                                            <input type="text" class="form-control" name="fname" id="fname" value="<?= htmlspecialchars($user['fname']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="lname" class="form-label small">Last Name</label>
                                            <input type="text" class="form-control" name="lname" id="lname" value="<?= htmlspecialchars($user['lname']) ?>" required>
                                        </div>

                                        <div class="col-md-12">
                                            <label for="email" class="form-label small">Email Address</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope"></i></span>
                                                <input type="email" class="form-control border-start-0 bg-light" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <label for="phone" class="form-label small">Phone Number</label>
                                            <input type="tel" class="form-control" name="phone" id="phone" value="<?= htmlspecialchars($user['phone']) ?>">
                                        </div>

                                        <div class="col-md-12">
                                            <label for="address" class="form-label small">Address</label>
                                            <textarea class="form-control" name="address" id="address" rows="2"><?= htmlspecialchars($user['address']) ?></textarea>
                                        </div>

                                        <?php if ($user['role'] === 'student'): ?>
                                            <div class="col-md-12">
                                                <hr class="my-2">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="gradeLevel" class="form-label small">Grade Level</label>
                                                <select class="form-select" name="gradeLevel" id="gradeLevel">
                                                    <option value="" <?= empty($user['grade_level']) ? 'selected' : '' ?>>Select Grade</option>
                                                    <option value="grade_11" <?= ($user['grade_level'] === 'grade_11') ? 'selected' : '' ?>>Grade 11</option>
                                                    <option value="grade_12" <?= ($user['grade_level'] === 'grade_12') ? 'selected' : '' ?>>Grade 12</option>
                                                </select>
                                            </div>

                                            <div class="col-md-6">
                                                <label for="lrn" class="form-label small">LRN (12 Digits)</label>
                                                <input type="text" class="form-control" name="lrn" id="lrn"
                                                    value="<?= htmlspecialchars($user['lrn'] ?? '') ?>"
                                                    minlength="12" maxlength="12" pattern="[0-9]{12}"
                                                    placeholder="e.g. 109876543210"
                                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                                        <a href="<?= $dashboard_link ?>" class="btn btn-light text-secondary rounded-pill px-4 me-2 fw-medium">Cancel</a>
                                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-medium shadow-sm">
                                            Save Changes
                                        </button>
                                    </div>
                                </div>
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