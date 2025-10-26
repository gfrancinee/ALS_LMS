<?php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Only allow admin to access this view
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit;
}

// Fetch strand details
$strand_id = $_GET['id'] ?? 0;
if (!$strand_id) {
    die("Strand not found.");
}

$stmt = $conn->prepare("SELECT * FROM learning_strands WHERE id = ?");
$stmt->bind_param("i", $strand_id);
$stmt->execute();
$strand = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$strand) {
    die("Strand not found.");
}

// Fetch material categories for the current strand
$material_categories_stmt = $conn->prepare("SELECT * FROM material_categories WHERE strand_id = ? ORDER BY name ASC");
$material_categories_stmt->bind_param("i", $strand_id);
$material_categories_stmt->execute();
$material_categories_result = $material_categories_stmt->get_result();
$material_categories = [];
while ($category = $material_categories_result->fetch_assoc()) {
    $materials_stmt = $conn->prepare("SELECT * FROM learning_materials WHERE category_id = ? ORDER BY uploaded_at ASC");
    $materials_stmt->bind_param("i", $category['id']);
    $materials_stmt->execute();
    $materials_result = $materials_stmt->get_result();
    $category['materials'] = $materials_result->fetch_all(MYSQLI_ASSOC);
    $materials_stmt->close();
    $material_categories[] = $category;
}
$material_categories_stmt->close();

// Fetch assessment categories and assessments (read-only)
$cat_stmt = $conn->prepare("SELECT * FROM assessment_categories WHERE strand_id = ? ORDER BY id ASC");
$cat_stmt->bind_param("i", $strand_id);
$cat_stmt->execute();
$categories = $cat_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$cat_stmt->close();

foreach ($categories as &$category) {
    $assess_stmt = $conn->prepare("SELECT * FROM assessments WHERE category_id = ?");
    $assess_stmt->bind_param("i", $category['id']);
    $assess_stmt->execute();
    $category['assessments'] = $assess_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $assess_stmt->close();
}
unset($category);

// Fetch participants for this strand
$participants_stmt = $conn->prepare("
    SELECT u.id, u.fname, u.lname, u.email, u.avatar_url, sp.joined_at, u.grade_level
    FROM strand_participants sp
    JOIN users u ON sp.student_id = u.id
    WHERE sp.strand_id = ? AND u.role = 'student'
    ORDER BY u.lname, u.fname
");
$participants_stmt->bind_param("i", $strand_id);
$participants_stmt->execute();
$participants = $participants_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$participants_stmt->close();

// Get grade level for back link
$grade_param = ($strand['grade_level'] === 'Grade 11') ? 'grade_11' : 'grade_12';

$back_link = '/ALS_LMS/admin/view-roles/view-as-student.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($strand['strand_title']) ?> - Student View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/view-strand.css" />

</head>

<body class="student-view-theme">
    <div class="container mt-4">
        <div class="back-container">
            <a href="<?= htmlspecialchars($back_link) ?>" class="back-link">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>

        <h2><?= htmlspecialchars($strand['strand_title']) ?> <small class="text-muted">(<?= htmlspecialchars($strand['strand_code']) ?>)</small></h2>
        <p><?= $strand['description'] ?></p>
        <span class="badge bg-secondary"><?= htmlspecialchars($strand['grade_level']) ?></span>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-0 mt-4 tabs-student" id="strandTabs" role="tablist">
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

        <div class="tab-content bg-white p-4" id="myTabContent">
            <!-- Modules Tab -->
            <div class="tab-pane fade show active" id="modules" role="tabpanel">
                <div class="accordion" id="materialsAccordion">
                    <?php if (empty($material_categories)): ?>
                        <div class="text-center text-muted p-5">
                            <p><i class="bi bi-journal-x fs-1"></i></p>
                            <h5>No learning materials are available yet.</h5>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($material_categories as $category): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#material-collapse-cat-<?= $category['id'] ?>">
                                    <i class="bi bi-folder me-2"></i> <?= htmlspecialchars($category['name']) ?>
                                </button>
                            </h2>
                            <div id="material-collapse-cat-<?= $category['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#materialsAccordion">
                                <div class="accordion-body">
                                    <ul class="list-unstyled mb-0">
                                        <?php if (!empty($category['materials'])): ?>
                                            <?php foreach ($category['materials'] as $mat): ?>
                                                <li class="list-group-item">
                                                    <a href="/ALS_LMS/strand/view_material.php?id=<?= $mat['id'] ?>" target="_blank" class="material-item-link">
                                                        <div class="d-flex align-items-center">
                                                            <?php
                                                            $icon = 'bi-file-earmark-text';
                                                            if ($mat['type'] === 'file') {
                                                                $ext = strtolower(pathinfo($mat['file_path'], PATHINFO_EXTENSION));
                                                                if ($ext === 'pdf') $icon = 'bi-file-earmark-pdf-fill text-danger';
                                                                elseif (in_array($ext, ['ppt', 'pptx'])) $icon = 'bi-file-earmark-slides-fill text-warning';
                                                            } elseif ($mat['type'] === 'link') {
                                                                $icon = 'bi-link-45deg text-primary';
                                                            } elseif ($mat['type'] === 'image') {
                                                                $icon = 'bi-card-image text-success';
                                                            } elseif ($mat['type'] === 'video') {
                                                                $icon = 'bi-play-circle-fill text-info';
                                                            } elseif ($mat['type'] === 'audio') {
                                                                $icon = 'bi-volume-up-fill text-purple';
                                                            }
                                                            ?>
                                                            <i class="bi <?= $icon ?> fs-2 me-3"></i>
                                                            <div>
                                                                <span class="fw-bold"><?= htmlspecialchars($mat['label']) ?></span>
                                                                <span class="badge bg-light text-dark fw-normal ms-2"><?= ucfirst($mat['type']) ?></span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li class="text-muted fst-italic p-3">No materials in this category yet.</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Assessments Tab -->
            <div class="tab-pane fade" id="assessments" role="tabpanel">
                <div class="accordion" id="assessmentAccordion">
                    <?php if (empty($categories)): ?>
                        <div class="text-center text-muted p-5">
                            <p><i class="bi bi-journal-x fs-1"></i></p>
                            <h5>No assessments are available yet.</h5>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($categories as $category): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-cat-<?= $category['id'] ?>">
                                    <i class="bi bi-folder me-2"></i> <?= htmlspecialchars($category['name']) ?>
                                </button>
                            </h2>
                            <div id="collapse-cat-<?= $category['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#assessmentAccordion">
                                <div class="accordion-body">
                                    <ul class="list-unstyled mb-0">
                                        <?php if (!empty($category['assessments'])): ?>
                                            <?php foreach ($category['assessments'] as $assessment): ?>
                                                <li>
                                                    <div class="assessment-item">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <span class="fw-bold"><?= htmlspecialchars($assessment['title']) ?></span>
                                                                <span class="badge bg-light text-dark fw-normal ms-2"><?= ucfirst($assessment['type']) ?></span>
                                                                <?php if (!empty($assessment['is_open'])): ?>
                                                                    <span class="badge bg-success ms-2">Open</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary ms-2">Closed</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="text-muted small">
                                                                <span class="me-3"><i class="bi bi-clock"></i> <?= $assessment['duration_minutes'] ?> mins</span>
                                                                <span><i class="bi bi-arrow-repeat"></i> <?= $assessment['max_attempts'] ?> attempt(s)</span>
                                                            </div>
                                                        </div>
                                                        <?php if (!empty(trim(strip_tags($assessment['description'])))): ?>
                                                            <div class="mt-2">
                                                                <button class="btn btn-sm py-0 btn-toggle-desc" type="button" data-bs-toggle="collapse" data-bs-target="#desc-<?= $assessment['id'] ?>">
                                                                    Show/Hide Description
                                                                </button>
                                                            </div>
                                                            <div class="collapse" id="desc-<?= $assessment['id'] ?>">
                                                                <div class="small text-muted mt-2 p-3 bg-light rounded">
                                                                    <?= $assessment['description'] ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li class="text-muted fst-italic">No assessments in this category yet.</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Participants Tab -->
            <div class="tab-pane fade" id="participants" role="tabpanel">
                <?php if (empty($participants)): ?>
                    <div class="text-center text-muted p-5">
                        <p><i class="bi bi-people fs-1"></i></p>
                        <h5>No students enrolled yet.</h5>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($participants as $participant): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100">
                                    <div class="card-body d-flex align-items-center">
                                        <div class="me-3">
                                            <?php if (!empty($participant['avatar_url'])): ?>
                                                <img src="/ALS_LMS/<?= htmlspecialchars($participant['avatar_url']) ?>"
                                                    class="rounded-circle"
                                                    style="width: 50px; height: 50px; object-fit: cover;"
                                                    alt="Avatar">
                                            <?php else: ?>
                                                <i class="bi bi-person-circle" style="font-size: 50px; color: #6c757d;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?= htmlspecialchars($participant['fname'] . ' ' . $participant['lname']) ?></h6>
                                            <small class="text-muted d-block"><?= htmlspecialchars($participant['email']) ?></small>
                                            <?php if (!empty($participant['grade_level'])): ?>
                                                <span class="badge bg-info mt-1">
                                                    <?php
                                                    echo ($participant['grade_level'] === 'grade_11') ? 'Grade 11' : 'Grade 12';
                                                    ?>
                                                </span>
                                            <?php endif; ?>
                                            <small class="text-muted d-block mt-1">
                                                <i class="bi bi-calendar3"></i> Joined <?= date('M d, Y', strtotime($participant['joined_at'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3 text-muted">
                        <small><i class="bi bi-info-circle"></i> Total Participants: <?= count($participants) ?></small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>