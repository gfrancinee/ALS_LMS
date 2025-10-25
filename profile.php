<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// --- UPDATED: Use a generic variable name ---
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = 'info';

// --- NEW: Determine the correct dashboard link based on the user's role ---
$dashboard_link = 'login.php'; // A safe fallback link
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'student') {
        $dashboard_link = 'student/student.php';
    } else if ($_SESSION['role'] === 'teacher') {
        $dashboard_link = 'teacher/teacher.php';
    }
    // You can add more roles here later, like 'admin'
}


// --- Part 1: Handle Form Submission (POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Update text information
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $gradeLevel = isset($_POST['gradeLevel']) ? $_POST['gradeLevel'] : null; // Added: Get grade level

    // Added: Update query to include grade_level
    $stmt = $conn->prepare("UPDATE users SET fname = ?, lname = ?, address = ?, phone = ?, grade_level = ? WHERE id = ?");
    // --- UPDATED: Use the generic $user_id variable ---
    $stmt->bind_param("sssssi", $fname, $lname, $address, $phone, $gradeLevel, $user_id);

    if ($stmt->execute()) {
        $message = "Information updated successfully!";
        $message_type = 'success';
    } else {
        $message = "Error updating information.";
        $message_type = 'danger';
    }

    // Handle optional avatar upload
    // Handle optional avatar upload
    if (isset($_FILES["avatar_image"]) && $_FILES["avatar_image"]["error"] == 0) {
        // --- THIS IS THE FINAL CORRECT PATH ---
        // Always store path relative to project root (als_lms)
        $uploadDir = __DIR__ . "/uploads/avatars/";  // filesystem path
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true); // make sure folder exists
        }

        $file_extension = pathinfo($_FILES["avatar_image"]["name"], PATHINFO_EXTENSION);
        $file_name = "user_" . $user_id . "_" . time() . "." . $file_extension;

        $target_file_system = $uploadDir . $file_name;      // full server path
        $target_file_db     = "uploads/avatars/" . $file_name; // clean DB path

        if (move_uploaded_file($_FILES["avatar_image"]["tmp_name"], $target_file_system)) {
            // Save the relative path into DB (never absolute!)
            $stmt = $conn->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
            $stmt->bind_param("si", $target_file_db, $user_id);
            $stmt->execute();
            $message .= " Profile picture updated!";
            $message_type = 'success';
        } else {
            $message .= " Error uploading profile picture. Please check folder permissions.";
            $message_type = 'danger';
        }
    }
}

// --- Part 2: Fetch Current User Data (GET Request) ---
// Added: Include grade_level and role in SELECT query
$stmt = $conn->prepare("SELECT fname, lname, email, address, phone, avatar_url, grade_level, role FROM users WHERE id = ?");
// --- UPDATED: Use the generic $user_id variable ---
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Set a default avatar if none is found
// This variable will ONLY be set if a custom avatar exists
$avatar_path = '';
if (!empty($user['avatar_url'])) {
    $avatar_path = htmlspecialchars($user['avatar_url']);
}

// Added: Function to get readable grade level text
function getGradeLevelText($gradeLevel)
{
    switch ($gradeLevel) {
        case 'elementary':
            return 'Elementary (Grades 1-6)';
        case 'junior_high':
            return 'Junior High School (Grades 7-10)';
        case 'senior_high':
            return 'Senior High School (Grades 11-12)';
        default:
            return 'Not set';
    }
}
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

<body>
    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm profile-card">
                    <div class="card-header">
                        <h4 class="mb-0">My Profile</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
                        <?php endif; ?>

                        <form action="profile.php" method="post" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <?php if (!empty($avatar_path)): ?>
                                        <img src="<?= $avatar_path ?>" class="img-thumbnail rounded-circle mb-3" alt="User Avatar" style="width: 150px; height: 150px; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="bi bi-person-circle mb-3" style="font-size: 150px; color: #6c757d;"></i>
                                    <?php endif; ?>
                                    <div class="mb-3">
                                        <label for="avatar_image" class="form-label">Change Picture</label>
                                        <input class="form-control form-control-sm" type="file" name="avatar_image" id="avatar_image">
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="fname" class="form-label">First Name</label>
                                            <input type="text" class="form-control" name="fname" id="fname" value="<?= htmlspecialchars($user['fname']) ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="lname" class="form-label">Last Name</label>
                                            <input type="text" class="form-control" name="lname" id="lname" value="<?= htmlspecialchars($user['lname']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                        <small class="form-text text-muted">Email address cannot be changed.</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" id="phone" value="<?= htmlspecialchars($user['phone']) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" name="address" id="address" rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                    </div>

                                    <!-- Added: Grade Level (only shown for students) -->
                                    <?php if ($user['role'] === 'student'): ?>
                                        <div class="mb-3">
                                            <label for="gradeLevel" class="form-label">Grade Level</label>
                                            <select class="form-select" name="gradeLevel" id="gradeLevel">
                                                <option value="" <?= empty($user['grade_level']) ? 'selected' : '' ?>>Select your grade level</option>
                                                <option value="grade_11" <?= ($user['grade_level'] === 'grade_11') ? 'selected' : '' ?>>Grade 11</option>
                                                <option value="grade_12" <?= ($user['grade_level'] === 'grade_12') ? 'selected' : '' ?>>Grade 12</option>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <!-- --- UPDATED: Use the dynamic dashboard link --- -->
                                <a href="<?= $dashboard_link ?>" class="btn btn-secondary me-2">Back</a>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>