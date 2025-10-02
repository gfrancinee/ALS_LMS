<?php
session_start();
require_once '../../includes/db.php';

// It's a good practice to check if the student is logged in.
// Example: if (!isset($_SESSION['student_id'])) { header('Location: /login.php'); exit(); }

$strand_id = $_GET['id'] ?? null;

if (!$strand_id) {
    die("Strand not found.");
}

// Fetch strand details
$stmt = $conn->prepare("SELECT * FROM learning_strands WHERE id = ?");
$stmt->bind_param("i", $strand_id);
$stmt->execute();
$result = $stmt->get_result();
$strand = $result->fetch_assoc();

if (!$strand) {
    die("Strand not found.");
}

// Fetch learning materials for this strand
$materials_stmt = $conn->prepare("
    SELECT id, label, type, file_path, link_url, uploaded_at
    FROM learning_materials
    WHERE strand_id = ?
    ORDER BY uploaded_at DESC
");
$materials_stmt->bind_param("i", $strand_id);
$materials_stmt->execute();
$materials = $materials_stmt->get_result();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($strand['strand_title']) ?> Strand | ALS LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/student_strand.css">
    <script>
        // Pass strand ID to JavaScript for fetching assessments and participants
        window.strandId = <?= json_encode($strand_id) ?>;
    </script>
    <script src="js/student_strand.js" defer></script>
</head>

<body>
    <div class="back-container">
        <a href="/ALS_LMS/student/student.php" class="back-link">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
    <div class="container mt-4">
        <h2><?= htmlspecialchars($strand['strand_title']) ?> <small class="text-muted">(<?= htmlspecialchars($strand['strand_code']) ?>)</small></h2>
        <p><?= htmlspecialchars($strand['description']) ?></p>
        <span class="badge bg-secondary"><?= htmlspecialchars($strand['grade_level']) ?></span>

        <ul class="nav nav-tabs mt-4" id="strandTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#modules">Modules</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#assessments">Assessments</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#participants">Participants</a>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade show active" id="modules" role="tabpanel" aria-labelledby="modules-tab">
                <div class="mt-3">
                    <?php if ($materials->num_rows > 0): ?>
                        <?php
                        $base_path = '/ALS_LMS/';
                        ?>
                        <?php while ($mat = $materials->fetch_assoc()): ?>
                            <?php
                            $full_url = '';
                            if (!empty($mat['file_path'])) {
                                $full_url = $base_path . htmlspecialchars($mat['file_path']);
                            }
                            ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="card-title mb-1"><?= htmlspecialchars($mat['label']) ?></h5>
                                            <span class="badge bg-info text-dark me-2"><?= htmlspecialchars($mat['type']) ?></span>
                                            <small class="text-muted">Uploaded: <?= date("F j, Y", strtotime($mat['uploaded_at'])) ?></small>
                                        </div>
                                    </div>

                                    <div class="material-preview mt-3 text-center">
                                        <?php
                                        $icon_class = 'bi-file-earmark-arrow-down'; // Default icon
                                        $modal_title = htmlspecialchars($mat['label']);

                                        if ($mat['type'] === 'image') $icon_class = 'bi-card-image';
                                        if ($mat['type'] === 'video') $icon_class = 'bi-play-btn';
                                        if ($mat['type'] === 'audio') $icon_class = 'bi-music-note-beamed';
                                        if ($mat['type'] === 'file')  $icon_class = 'bi-file-earmark-text';
                                        if ($mat['type'] === 'link')  $icon_class = 'bi-link-45deg';

                                        $media_url = ($mat['type'] === 'link') ? htmlspecialchars($mat['link_url'] ?? '') : $full_url;
                                        ?>

                                        <?php if ($mat['type'] === 'link'): ?>
                                            <a href="<?= $media_url ?>" target="_blank" class="d-block">
                                                <i class="bi <?= $icon_class ?>" style="font-size: 4rem;"></i>
                                                <p class="mt-2">Open Link</p>
                                            </a>
                                        <?php else: ?>
                                            <a href="#" class="d-block"
                                                data-bs-toggle="modal"
                                                data-bs-target="#mediaModal"
                                                data-type="<?= htmlspecialchars($mat['type']) ?>"
                                                data-url="<?= $media_url ?>"
                                                data-label="<?= $modal_title ?>">
                                                <i class="bi <?= $icon_class ?>" style="font-size: 4rem;"></i>
                                                <p class="mt-2">View <?= htmlspecialchars($mat['type']) ?></p>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="alert alert-info">No materials have been uploaded for this strand yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tab-pane fade" id="assessments" role="tabpanel" aria-labelledby="assessments-tab">
                <div class="mt-3">
                    <div id="assessmentList" class="mt-2">
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="participants" role="tabpanel" aria-labelledby="participants-tab">
                <div class="mt-3">
                    <div id="participantList" class="mt-2">
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

        <div class="modal fade" id="mediaModal" tabindex="-1" aria-labelledby="mediaModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="mediaModalLabel">Loading...</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="mediaModalBody">
                    </div>
                    <div class="modal-footer">
                        <a href="#" id="mediaDownloadLink" class="btn btn-primary" target="_blank" download>Download</a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>