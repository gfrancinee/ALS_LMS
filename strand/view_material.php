<?php
session_start();
require_once '../includes/db.php';

// --- Security & Data Fetching (No changes here) ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
$material_id = $_GET['id'] ?? null;
if (!$material_id) {
    die("Error: No material specified.");
}
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$stmt = $conn->prepare("SELECT * FROM learning_materials WHERE id = ?");
$stmt->bind_param("i", $material_id);
$stmt->execute();
$result = $stmt->get_result();
$material = $result->fetch_assoc();
$stmt->close();
if (!$material) {
    http_response_code(404);
    die("Material not found.");
}
if ($user_role === 'student') {
    $strand_id = $material['strand_id'];
    $enroll_check = $conn->prepare("SELECT id FROM strand_participants WHERE strand_id = ? AND student_id = ?");
    $enroll_check->bind_param("ii", $strand_id, $student_id);
    $enroll_check->execute();
    if ($enroll_check->get_result()->num_rows === 0) {
        die("Access Denied: You are not enrolled in this course.");
    }
    $enroll_check->close();
}
if ($material['type'] === 'link' && !empty($material['link_url'])) {
    header('Location: ' . $material['link_url']);
    exit;
}
$file_url = '/ALS_LMS/' . htmlspecialchars($material['file_path']);
$file_label = htmlspecialchars($material['label']);
$file_type = $material['type'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $file_label ?> | Material Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/view_material.css">
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container-fluid">
            <div class="back-container">
                <a href="/ALS_LMS/strand/strand.php?id=<?= $material['strand_id'] ?>" class="back-link">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
            <span class="navbar-text mx-auto fw-bold text-truncate" title="<?= $file_label ?>">
                <?= $file_label ?>
            </span>
            <a href="<?= $file_url ?>" class="download" download>
                <i class="bi bi-download me-2"></i>Download
            </a>
        </div>
    </nav>

    <main class="viewer-content text-center">
        <?php
        $ext = $material['file_path'] ? strtolower(pathinfo($material['file_path'], PATHINFO_EXTENSION)) : '';

        if ($file_type === 'image') {
            echo "<img src='{$file_url}' alt='{$file_label}'>";
        } elseif ($file_type === 'video') {
            echo "<video src='{$file_url}' controls autoplay></video>";
        } elseif ($file_type === 'audio') {
            echo "<audio src='{$file_url}' controls autoplay></audio>";
        } elseif ($ext === 'pdf') {
            echo "<iframe src='{$file_url}' class='pdf-viewer'></iframe>";
        } else {
            echo "<div class='unsupported-file'>";
            echo "<i class='bi bi-file-earmark-arrow-down display-1 text-muted'></i>";
            echo "<h3 class='mt-4'>Preview not available</h3>";
            echo "<p class='lead text-muted'>This file is best viewed by downloading it.</p>";
            echo "<a href='{$file_url}' class='btn btn-lg btn-primary mt-3' download>Download File</a>";
            echo "</div>";
        }
        ?>
    </main>

</body>

</html>