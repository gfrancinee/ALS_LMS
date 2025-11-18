<?php
session_start();
require_once '../includes/db.php';

$user_role = $_SESSION['role'] ?? 'guest';
$is_teacher = ($user_role === 'teacher');

// Fetch strand and material details
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
// Find and replace the old $materials_stmt block with this

// Fetch material categories for the current strand
$material_categories_stmt = $conn->prepare("SELECT * FROM material_categories WHERE strand_id = ? ORDER BY name ASC");
$material_categories_stmt->bind_param("i", $strand_id);
$material_categories_stmt->execute();
$material_categories_result = $material_categories_stmt->get_result();
$material_categories = [];
while ($category = $material_categories_result->fetch_assoc()) {
    // For each category, fetch its materials
    $materials_stmt = $conn->prepare("SELECT * FROM learning_materials WHERE category_id = ? ORDER BY uploaded_at ASC");
    $materials_stmt->bind_param("i", $category['id']);
    $materials_stmt->execute();
    $materials_result = $materials_stmt->get_result();
    $category['materials'] = $materials_result->fetch_all(MYSQLI_ASSOC);
    $materials_stmt->close();
    $material_categories[] = $category;
}
$material_categories_stmt->close();

// Correct logic for fetching assessments
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$categories = [];

if ($user_role === 'teacher') {
    // Teacher logic (your original code is fine)
    $cat_stmt = $conn->prepare("SELECT * FROM assessment_categories WHERE strand_id = ? AND teacher_id = ? ORDER BY id ASC");
    $cat_stmt->bind_param("ii", $strand_id, $user_id);
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
} elseif ($user_role === 'student') {
    // --- STUDENT LOGIC (WITH THE BUG FIX) ---
    $cat_stmt = $conn->prepare("SELECT * FROM assessment_categories WHERE strand_id = ? ORDER BY id ASC");
    $cat_stmt->bind_param("i", $strand_id);
    $cat_stmt->execute();
    $categories = $cat_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $cat_stmt->close();

    foreach ($categories as &$category) {
        // --- THIS SQL IS UPDATED to get the 'latest_attempt_id' ---
        $assessment_sql = "
            SELECT 
                a.id, a.title, a.type, a.description, a.duration_minutes, a.max_attempts, a.is_open,
                (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.assessment_id = a.id AND qa.student_id = ?) as attempts_taken,
                (SELECT score FROM quiz_attempts qa WHERE qa.assessment_id = a.id AND qa.student_id = ? ORDER BY submitted_at DESC LIMIT 1) as latest_score,
                (SELECT total_items FROM quiz_attempts qa WHERE qa.assessment_id = a.id AND qa.student_id = ? ORDER BY submitted_at DESC LIMIT 1) as total_items,
                
                -- This is the NEW line we need for the link --
                (SELECT id FROM quiz_attempts qa WHERE qa.assessment_id = a.id AND qa.student_id = ? AND qa.status = 'submitted' ORDER BY submitted_at DESC LIMIT 1) as latest_attempt_id

            FROM assessments a
            WHERE a.category_id = ? 
        ";
        $assess_stmt = $conn->prepare($assessment_sql);
        // We added one more '?' so we need one more 'i' and $user_id
        $assess_stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $category['id']);
        $assess_stmt->execute();
        $category['assessments'] = $assess_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $assess_stmt->close();
    }
    unset($category);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($strand['strand_title']) ?> Strand | ALS LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.tiny.cloud/1/7xskvh2bu8gio6eivhdb9jhxvgebwjuh180l3ct3sqza4vh5/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <link rel="stylesheet" href="css/strand.css">
    <script>
        window.strandId = <?= json_encode($strand_id) ?>;
        window.userRole = '<?= htmlspecialchars($_SESSION['role'] ?? 'guest') ?>'; // ADD THIS LINE
    </script>

</head>

<body class="<?= ($_SESSION['role'] === 'student') ? 'student-view-theme' : '' ?>">
    <script>
        // This makes the current strand ID available to all JavaScript files
        window.strandId = <?= json_encode($strand_id); ?>;
    </script>

    <?php
    // Determine the correct "Back" link based on the user's role
    $back_link = '/ALS_LMS/index.php'; // Default link
    $back_link_class = ''; // Default class
    $tab_class = '';

    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'teacher') {
            $back_link = '/ALS_LMS/teacher/teacher.php';
            $back_link_class = 'back-link-teacher';
            $tab_class = 'tabs-teacher';
        } elseif ($_SESSION['role'] === 'student') {
            $back_link = '/ALS_LMS/student/student.php';
            $back_link_class = 'back-link-student';
            $tab_class = 'tabs-student';

            // --- ADD THE RECOMMENDATION CODE HERE ---
            $recommendations = [];
            // This query finds materials viewed by other students active in this strand.
            $rec_sql = "
            SELECT 
                lm.id, lm.label, lm.type, lm.file_path, lm.link_url,
                COUNT(DISTINCT mv.student_id) as similar_views
            FROM material_views mv
            JOIN learning_materials lm ON mv.material_id = lm.id
            WHERE 
                lm.strand_id = ? 
                AND mv.student_id IN (
                    -- Find IDs of other students who have taken quizzes in this strand
                    SELECT DISTINCT qa.student_id 
                    FROM quiz_attempts qa
                    JOIN assessments a ON qa.assessment_id = a.id
                    WHERE a.strand_id = ? AND qa.student_id != ?
                )
                AND lm.id NOT IN (
                    -- Exclude materials the current student has already seen
                    SELECT material_id FROM material_views WHERE student_id = ?
                )
            GROUP BY lm.id
            ORDER BY similar_views DESC
            LIMIT 3
        ";
            $rec_stmt = $conn->prepare($rec_sql);
            // Note: Make sure $strand_id and $user_id are defined before this block
            $rec_stmt->bind_param("iiii", $strand_id, $strand_id, $user_id, $user_id);
            $rec_stmt->execute();
            $recommendations = $rec_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $rec_stmt->close();
            // --- END OF RECOMMENDATION CODE ---

        } elseif ($_SESSION['role'] === 'admin') {
            $back_link = '/ALS_LMS/admin/admin.php';
        }
    }
    ?>
    <div class="back-container">
        <a href="<?= htmlspecialchars($back_link) ?>" class="back-link <?= $back_link_class ?>">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
    <div class="container mt-4">
        <h2><?= htmlspecialchars($strand['strand_title']) ?> <small class="text-muted">(<?= htmlspecialchars($strand['strand_code']) ?>)</small></h2>
        <p><?= ($strand['description']) ?></p>
        <span class="badge rounded-pill bg-light text-dark"><?= htmlspecialchars($strand['grade_level']) ?></span>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-0 mt-4 <?= $tab_class ?>" id="strandTabs" role="tablist">
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

            <!-- Modules Tabs -->
            <div class="tab-pane fade show active" id="modules" role="tabpanel" aria-labelledby="modules-tab">

                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'): ?>
                    <div class="d-flex justify-content-end mb-4">
                        <button type="button" class="btn btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#manageMaterialCategoriesModal">
                            <i class="bi bi-folder-plus me-2"></i>Manage Categories
                        </button>
                    </div>
                <?php endif; ?>

                <div class="accordion assessment-accordion" id="materialsAccordion">

                    <?php if (empty($material_categories)): ?>
                        <div id="no-material-categories-message" class="text-center text-muted p-5">
                            <p><i class="bi bi-file-earmark-text fs-1"></i></p>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'): ?>
                                <h5>No material categories have been created yet.</h5>
                                <p>Click "Manage Categories" to get started.</p>
                            <?php else: ?>
                                <h5>No learning materials are available yet.</h5>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($material_categories as $category): ?>
                        <div class="accordion-item" id="material-category-item-<?= $category['id'] ?>">
                            <h2 class="accordion-header">
                                <div class="d-flex align-items-center w-100">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#material-collapse-cat-<?= $category['id'] ?>">
                                        <i class="bi bi-folder me-2"></i> <?= htmlspecialchars($category['name']) ?>
                                    </button>

                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'): ?>
                                        <div class="dropdown mb-2">
                                            <button class="btn btn-options" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><button class="dropdown-item text-success" type="button" data-bs-toggle="modal" data-bs-target="#materialCategoryActionModal" data-action="edit" data-id="<?= $category['id'] ?>" data-name="<?= htmlspecialchars($category['name']) ?>"><i class="bi bi-pencil-square me-2"></i> Edit</button></li>
                                                <li><button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#materialCategoryActionModal" data-action="delete" data-id="<?= $category['id'] ?>" data-name="<?= htmlspecialchars($category['name']) ?>"><i class="bi bi-trash3 me-2"></i> Delete</button></li>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </h2>
                            <div id="material-collapse-cat-<?= $category['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#materialsAccordion">
                                <div class="accordion-body">
                                    <div id="material-list-container-cat-<?= $category['id'] ?>">
                                        <ul class="list-unstyled mb-0 material-list-group">
                                            <?php if (!empty($category['materials'])): ?>
                                                <?php foreach ($category['materials'] as $mat): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center material-item" id="material-item-<?= $mat['id'] ?>">

                                                        <a href="/ALS_LMS/strand/view_material.php?id=<?= $mat['id'] ?>" target="_blank" class="material-item-link">
                                                            <div class="d-flex align-items-center">
                                                                <?php
                                                                // --- AFTER (All types included) ---
                                                                $icon = 'bi-file-earmark-text'; // Default icon
                                                                if ($mat['type'] === 'file') {
                                                                    $ext = strtolower(pathinfo($mat['file_path'], PATHINFO_EXTENSION));
                                                                    if ($ext === 'pdf') $icon = 'bi-file-earmark-pdf-fill text-danger';
                                                                    elseif (in_array($ext, ['ppt', 'pptx'])) $icon = 'bi-file-earmark-slides-fill text-warning';
                                                                } elseif ($mat['type'] === 'link') {
                                                                    $icon = 'bi-link-45deg text-primary';
                                                                } elseif ($mat['type'] === 'image') {
                                                                    $icon = 'bi-card-image text-success'; // Added for Image
                                                                } elseif ($mat['type'] === 'video') {
                                                                    $icon = 'bi-play-circle-fill text-info'; // Added for Video
                                                                } elseif ($mat['type'] === 'audio') {
                                                                    // --- FIX #2: Combined icon and color class here ---
                                                                    $icon = 'bi-volume-up-fill text-purple';
                                                                }
                                                                ?>
                                                                <i class="bi <?= $icon ?> fs-2 me-3"></i>
                                                                <div>
                                                                    <span class="fw-bold"><?= htmlspecialchars($mat['label']) ?></span>
                                                                    <span class="badge bg-white text-dark fw-normal ms-2"><?= ucfirst($mat['type']) ?></span>
                                                                </div>
                                                            </div>
                                                        </a>

                                                        <?php if ($is_teacher): ?>
                                                            <div class="dropdown">
                                                                <button class="btn btn-options" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                    <i class="bi bi-three-dots-vertical"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li><button class="dropdown-item edit-material-btn text-success" data-bs-toggle="modal" data-bs-target="#editMaterialModal" data-id="<?= $mat['id'] ?>"><i class="bi bi-pencil-square me-2"></i> Edit</button></li>
                                                                    <li><button type="button" class="dropdown-item delete-material-btn text-danger" data-bs-toggle="modal" data-bs-target="#deleteMaterialModal" data-id="<?= $mat['id'] ?>"><i class="bi bi-trash3 me-2"></i> Delete</button></li>
                                                                </ul>
                                                            </div>
                                                        <?php endif; ?>

                                                    </li>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <li class="text-muted fst-italic p-3 no-materials-message">No materials in this category yet.</li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>

                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'): ?>
                                        <hr class="my-3">
                                        <div class="text-center">
                                            <button class="btn btn-link text-success btn-sm me-3 btn-pill-hoverr text-decoration-none upload-material-btn" data-bs-toggle="collapse" data-bs-target="#uploadMaterialContainer" data-category-id="<?= $category['id'] ?>">
                                                <i class="bi-file-earmark-plus-fill"></i> Upload Material
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Upload Material -->
                <div class="collapse" id="uploadMaterialContainer">
                    <div class="card card-body m-5 shadow-sm border-light-subtle">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Upload New Material</h5>
                        </div>

                        <hr class="my-3">

                        <form id="uploadMaterialForm" enctype="multipart/form-data">
                            <input type="hidden" name="category_id" id="uploadMaterialCategoryId">

                            <div class="mb-3">
                                <label class="form-label">Material Type</label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="material_type" id="typeFile" value="file" checked>
                                        <label class="form-check-label" for="typeFile">File</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="material_type" id="typeImage" value="image">
                                        <label class="form-check-label" for="typeImage">Image</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="material_type" id="typeVideo" value="video">
                                        <label class="form-check-label" for="typeVideo">Video</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="material_type" id="typeAudio" value="audio">
                                        <label class="form-check-label" for="typeAudio">Audio</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="material_type" id="typeLink" value="link">
                                        <label class="form-check-label" for="typeLink">Link/URL</label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="materialLabel" class="form-label">Label/Title</label>
                                <input type="text" class="form-control" id="materialLabel" name="label" required>
                            </div>

                            <div class="mb-3">
                                <div id="fileUploadGroup">
                                    <label for="materialFile" class="form-label">Select File</label>
                                    <input class="form-control" type="file" id="materialFile" name="material_file" required>
                                </div>
                                <div id="linkUploadGroup" style="display: none;">
                                    <label for="materialLink" class="form-label">Enter URL</label>
                                    <input type="url" class="form-control" id="materialLink" name="link_url" placeholder="https://example.com">
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary rounded-pill px-3 me-2" data-bs-toggle="collapse" data-bs-target="#uploadMaterialContainer">Cancel</button>
                                <button type="submit" class="btn btn-primary rounded-pill px-3">Upload</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Assessments Tab -->
            <div class="tab-pane fade" id="assessments" role="tabpanel" aria-labelledby="assessments-tab">

                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'): ?>
                    <div class="d-flex justify-content-end mb-4">
                        <a href="learning_strand_gradebook.php?id=<?= htmlspecialchars($strand_id) ?>" class="btn btn-success rounded-pill px-3 me-2">
                            <i class="bi bi-bar-chart-fill me-2"></i>Summary of Scores
                        </a>
                        <button type="button" class="btn btn-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#manageCategoriesModal">
                            <i class="bi bi-folder-plus me-2"></i>Manage Categories
                        </button>
                    </div>
                <?php endif; ?>

                <div class="accordion assessment-accordion" id="assessmentAccordion">

                    <?php if (empty($categories) && empty($uncategorized_assessments)): ?>
                        <div id="no-categories-message" class="text-center text-muted p-5">
                            <p><i class="bi bi-clipboard-check fs-1"></i></p>
                            <h5>No assessments or categories have been created yet.</h5>
                            <p>Click "Manage Categories" to get started.</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($categories as $category): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <div class="d-flex align-items-center w-100">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-cat-<?= $category['id'] ?>">
                                        <i class="bi bi-folder me-2"></i> <?= htmlspecialchars($category['name']) ?>
                                    </button>

                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'): ?>
                                        <div class="dropdown mb-2">
                                            <button class="btn btn-options" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><button class="dropdown-item text-success" type="button" data-bs-toggle="modal" data-bs-target="#categoryActionModal" data-action="edit" data-id="<?= $category['id'] ?>" data-name="<?= htmlspecialchars($category['name']) ?>"><i class="bi bi-pencil-square me-2"></i> Edit</button></li>
                                                <li><button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#categoryActionModal" data-action="delete" data-id="<?= $category['id'] ?>" data-name="<?= htmlspecialchars($category['name']) ?>"><i class="bi bi-trash3 me-2"></i> Delete</button></li>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </h2>
                            <div id="collapse-cat-<?= $category['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#assessmentAccordion">
                                <div class="accordion-body">
                                    <ul class="list-unstyled mb-0">
                                        <?php if (!empty($category['assessments'])): ?>
                                            <?php foreach ($category['assessments'] as $assessment): ?>
                                                <li>
                                                    <?php if ($_SESSION['role'] === 'teacher'): ?>

                                                        <div class="assessment-item">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div class="flex-grow-1">
                                                                    <a href="/ALS_LMS/strand/preview_assessment.php?id=<?= $assessment['id'] ?>" class="assessment-item-link">
                                                                        <div class="d-flex justify-content-between align-items-center">
                                                                            <div>
                                                                                <span class="fw-bold"><?= htmlspecialchars($assessment['title']) ?></span>
                                                                                <span class="badge bg-light text-dark fw-normal ms-2"><?= ucfirst($assessment['type']) ?></span>
                                                                            </div>

                                                                            <!-- --- MODIFICATION 1: Conditionally show duration/attempts --- -->
                                                                            <?php if ($assessment['type'] === 'quiz' || $assessment['type'] === 'exam'): ?>
                                                                                <div class="text-muted small">
                                                                                    <span class="me-3"><i class="bi bi-clock"></i> <?= $assessment['duration_minutes'] ?> mins</span>
                                                                                    <span><i class="bi bi-arrow-repeat"></i> <?= $assessment['max_attempts'] ?> attempt(s)</span>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                            <!-- --- End of Modification 1 --- -->

                                                                        </div>
                                                                    </a>
                                                                    <?php if (!empty(trim(strip_tags($assessment['description'])))): ?>
                                                                        <div class="mt-2">
                                                                            <button class="btn btn-sm py-0 btn-toggle-desc" type="button" data-bs-toggle="collapse" data-bs-target="#desc-<?= $assessment['id'] ?>">
                                                                                Show/Hide Description
                                                                            </button>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="d-flex align-items-center gap-2 ps-3">
                                                                    <div class="form-check form-switch">
                                                                        <input class="form-check-input assessment-status-toggle" type="checkbox" role="switch" data-id="<?= $assessment['id'] ?>" <?= !empty($assessment['is_open']) ? 'checked' : '' ?>>
                                                                        <label class="form-check-label small"><?= !empty($assessment['is_open']) ? 'Open' : 'Closed' ?></label>
                                                                    </div>
                                                                    <div class="dropdown">
                                                                        <button class="btn btn-options" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                                                        <ul class="dropdown-menu dropdown-menu-end">

                                                                            <!-- --- MODIFICATION 2: Conditionally show 'Manage Questions' --- -->
                                                                            <?php if ($assessment['type'] === 'quiz' || $assessment['type'] === 'exam'): ?>
                                                                                <li><a class="dropdown-item" href="/ALS_LMS/strand/manage_assessment.php?id=<?= $assessment['id'] ?>"><i class="bi bi-list-check me-2"></i> Manage Questions</a></li>
                                                                            <?php endif; ?>
                                                                            <!-- --- End of Modification 2 --- -->

                                                                            <li>
                                                                                <a class="dropdown-item" href="/ALS_LMS/strand/view_submissions.php?assessment_id=<?= $assessment['id'] ?>">
                                                                                    <i class="bi bi-person-check-fill me-2"></i> View Submissions
                                                                                </a>
                                                                            </li>
                                                                            <li>
                                                                                <hr class="dropdown-divider">
                                                                            </li>
                                                                            <li><button class="dropdown-item text-success edit-assessment-btn" type="button" data-bs-toggle="modal" data-bs-target="#editAssessmentModal" data-id="<?= $assessment['id'] ?>"><i class="bi bi-pencil-square me-2"></i> Edit</button></li>
                                                                            <li><button class="dropdown-item text-danger delete-assessment-btn" type="button" data-bs-toggle="modal" data-bs-target="#deleteAssessmentModal" data-id="<?= $assessment['id'] ?>" data-title="<?= htmlspecialchars($assessment['title']) ?>"><i class="bi bi-trash3 me-2"></i> Delete</button></li>
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php if (!empty(trim(strip_tags($assessment['description'])))): ?>
                                                                <div class="collapse" id="desc-<?= $assessment['id'] ?>">
                                                                    <div class="small text-muted mt-2 p-3 bg-light rounded">
                                                                        <?= $assessment['description'] ?>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="assessment-item">
                                                            <?php
                                                            $assessment_type = $assessment['type'];
                                                            $is_quiz_or_exam = ($assessment_type === 'quiz' || $assessment_type === 'exam');

                                                            // Default link
                                                            $link_href = '#';
                                                            $link_class = 'disabled';
                                                            $status_badge = '';
                                                            $retake_button_html = '';

                                                            if ($is_quiz_or_exam) {
                                                                // --- LOGIC FOR QUIZ/EXAM ---
                                                                $attempts_left = $assessment['max_attempts'] - $assessment['attempts_taken'];
                                                                $has_completed_attempt = isset($assessment['latest_attempt_id']) && $assessment['latest_attempt_id'] > 0;
                                                                $can_take_quiz = !empty($assessment['is_open']) && $attempts_left > 0;

                                                                if ($has_completed_attempt) {
                                                                    $link_href = 'quiz_results.php?attempt_id=' . $assessment['latest_attempt_id'];
                                                                    $link_class = '';
                                                                    $status_badge = "<span class=\"badge text-primary ms-2\">View Results (Score: {$assessment['latest_score']} / {$assessment['total_items']})</span>";

                                                                    if ($can_take_quiz) {
                                                                        $retake_button_html = "<div class=\"mt-2\"><a href=\"take_assessment.php?id={$assessment['id']}\" class=\"btn btn-sm btn-outline-secondary py-0\">Retake ({$attempts_left} left)</a></div>";
                                                                    }
                                                                } elseif ($can_take_quiz) {
                                                                    $link_href = 'take_assessment.php?id=' . $assessment['id'];
                                                                    $link_class = '';
                                                                    $status_badge = '<span class="badge text-success ms-2">Open</span>';
                                                                } elseif ($attempts_left <= 0 && $assessment['attempts_taken'] > 0) {
                                                                    // *** UPDATED: Only show if they've actually taken it
                                                                    $status_badge = '<span class="badge text-dark ms-2">No Attempts Left</span>';
                                                                } elseif ($attempts_left <= 0 && $assessment['attempts_taken'] == 0) {
                                                                    // *** NEW: Case for 0 max attempts (like our old bug)
                                                                    $status_badge = '<span class="badge text-danger ms-2">Not Available</span>';
                                                                } elseif (empty($assessment['is_open'])) {
                                                                    $status_badge = '<span class="badge text-danger ms-2">Closed</span>';
                                                                }
                                                            } else {
                                                                // --- LOGIC FOR ACTIVITY/ASSIGNMENT ---
                                                                $has_submission = isset($assessment['submission_id']) && $assessment['submission_id'] > 0;
                                                                $can_submit = !empty($assessment['is_open']);

                                                                if ($has_submission) {
                                                                    // Student has submitted, link to their submission details
                                                                    $link_href = 'view_submission.php?submission_id=' . $assessment['submission_id'];
                                                                    $link_class = '';

                                                                    // *** UPDATED: New badge logic ***
                                                                    if ($assessment['submission_status'] === 'graded') {
                                                                        $status_badge = "<span class=\"badge text-primary ms-2\">Graded (Score: {$assessment['submission_score']} / {$assessment['submission_total']})</span>";
                                                                    } else {
                                                                        $status_badge = "<span class=\"badge text-warning ms-2\">Submitted</span>";
                                                                    }
                                                                } elseif ($can_submit) {
                                                                    // Student can view/submit
                                                                    $link_href = 'view_activity.php?id=' . $assessment['id']; // We will create this page
                                                                    $link_class = '';
                                                                    $status_badge = '<span class="badge text-success ms-2">Open</span>';
                                                                } else { // Closed and no submission
                                                                    $status_badge = '<span class="badge text-danger ms-2">Closed</span>';
                                                                }
                                                            }
                                                            ?>

                                                            <a href="<?= $link_href ?>" class="assessment-item-link <?= $link_class ?>">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <span class="fw-bold"><?= htmlspecialchars($assessment['title']) ?></span>
                                                                        <span class="badge bg-light text-dark fw-normal ms-2"><?= ucfirst($assessment['type']) ?></span>
                                                                        <?= $status_badge ?>
                                                                    </div>

                                                                    <!-- Conditionally show duration/attempts for student -->
                                                                    <?php if ($is_quiz_or_exam): ?>
                                                                        <div class="text-muted small">
                                                                            <span class="me-3"><i class="bi bi-clock"></i> <?= $assessment['duration_minutes'] ?> mins</span>
                                                                            <span><i class="bi bi-arrow-repeat"></i> <?= $assessment['max_attempts'] ?> attempt(s)</span>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </a>

                                                            <?= $retake_button_html ?>

                                                            <?php if (!empty(trim(strip_tags($assessment['description'])))): ?>
                                                                <div class="mt-2">
                                                                    <button class="btn btn-sm py-0 btn-toggle-desc" type="button" data-bs-toggle="collapse" data-bs-target="#desc-student-<?= $assessment['id'] ?>">
                                                                        Show/Hide Description
                                                                    </button>
                                                                </div>
                                                                <div class="collapse" id="desc-student-<?= $assessment['id'] ?>">
                                                                    <div class="small text-muted mt-2 p-3 bg-light rounded">
                                                                        <?= $assessment['description'] ?>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>

                                                        </div>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li class="text-muted fst-italic">No assessments in this category yet.</li>
                                        <?php endif; ?>
                                    </ul>

                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'): ?>
                                        <hr class="my-3">
                                        <div class="text-center">
                                            <button class="btn btn-link text-success btn-sm me-3 btn-pill-hoverr text-decoration-none create-assessment-btn" data-bs-toggle="collapse" data-bs-target="#createAssessmentContainer" data-category-id="<?= $category['id'] ?>">
                                                <i class="bi bi-plus-circle"></i> Create Assessment
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div> <?php if (!empty($uncategorized_assessments)): ?>
                    <div class="mt-4">
                        <h5 class="mb-3">Uncategorized</h5>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($uncategorized_assessments as $assessment): ?>
                                <li>
                                    <div class="assessment-item">
                                        <a href="<?= (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher') ? '/ALS_LMS/strand/manage_assessment.php?id=' . $assessment['id'] : 'take_assessment.php?id=' . $assessment['id'] ?>" class="assessment-item-link">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span class="fw-bold"><?= htmlspecialchars($assessment['title']) ?></span>
                                                    <span class="badge bg-light text-dark fw-normal ms-2"><?= ucfirst($assessment['type']) ?></span>
                                                </div>
                                                <div class="text-muted small">
                                                    <span class="me-3"><i class="bi bi-clock"></i> <?= $assessment['duration_minutes'] ?> mins</span>
                                                    <span><i class="bi bi-arrow-repeat"></i> <?= $assessment['max_attempts'] ?> attempt(s)</span>
                                                </div>
                                            </div>
                                        </a>

                                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'): ?>
                                            <div class="dropdown">
                                                <button class="btn btn-options" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-three-dots-vertical"></i></button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><button class="dropdown-item text-success edit-assessment-btn" type="button" data-bs-toggle="modal" data-bs-target="#editAssessmentModal" data-id="<?= $assessment['id'] ?>"><i class="bi bi-pencil-square me-2"></i> Edit </button></li>
                                                    <li><button class="dropdown-item text-danger delete-assessment-btn" type="button" data-bs-toggle="modal" data-bs-target="#deleteAssessmentModal" data-id="<?= $assessment['id'] ?>" data-title="<?= htmlspecialchars($assessment['title']) ?>"><i class="bi bi-trash3 me-2"></i> Delete</button></li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Create assessment -->
                <div class="collapse" id="createAssessmentContainer">
                    <div class="card card-body m-5 shadow-sm border-light-subtle">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Create New Assessment</h5>
                        </div>

                        <hr class="my-3">

                        <form id="createAssessmentForm">
                            <input type="hidden" id="assessmentCategoryId" name="category_id">

                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="assessmentTitle" class="form-label">Title</label>
                                    <input type="text" class="form-control" id="assessmentTitle" name="title" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label d-block">Type</label>
                                    <!-- Updated to flex-wrap for better layout -->
                                    <div class="d-flex flex-wrap pt-2" style="gap: 1rem;">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input assessment-type-option" type="radio" name="type" id="typeQuiz" value="quiz" checked>
                                            <label class="form-check-label" for="typeQuiz">Quiz</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input assessment-type-option" type="radio" name="type" id="typeExam" value="exam">
                                            <label class="form-check-label" for="typeExam">Exam</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input assessment-type-option" type="radio" name="type" id="typeActivity" value="activity">
                                            <label class="form-check-label" for="typeActivity">Activity</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input assessment-type-option" type="radio" name="type" id="typeAssignment" value="assignment">
                                            <label class="form-check-label" for="typeAssignment">Assignment</label>
                                        </div>
                                        <!-- NEW: Added Project -->
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input assessment-type-option" type="radio" name="type" id="typeProject" value="project">
                                            <label class="form-check-label" for="typeProject">Project</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="assessmentDesc" class="form-label">Description/Instructions</label>
                                <!-- This textarea will be targeted by TinyMCE -->
                                <textarea class="form-control" id="assessmentDesc" name="description" rows="3"></textarea>
                            </div>

                            <div class="row">
                                <!-- Quiz/Exam Fields -->
                                <div class="col-md-6 mb-3" id="durationContainer">
                                    <label for="assessmentDuration" class="form-label">Duration (minutes)</label>
                                    <input type="number" class="form-control" id="assessmentDuration" name="duration_minutes" value="60" min="1" required>
                                </div>
                                <div class="col-md-6 mb-3" id="attemptsContainer">
                                    <label for="assessmentAttempts" class="form-label">Max Attempts</label>
                                    <input type="number" class="form-control" id="assessmentAttempts" name="max_attempts" value="1" min="1" required>
                                </div>

                                <!-- NEW: Activity/Assignment/Project Field -->
                                <div class="col-md-6 mb-3" id="totalPointsContainer" style="display: none;">
                                    <label for="assessmentTotalPoints" class="form-label">Total Points</label>
                                    <input type="number" class="form-control" id="assessmentTotalPoints" name="total_points" value="20" min="1" required>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-3">
                                <button type="button" class="btn btn-secondary rounded-pill px-3 me-2" data-bs-toggle="collapse" data-bs-target="#createAssessmentContainer">Cancel</button>
                                <button type="submit" class="btn btn-success rounded-pill px-3">Create Assessment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Participants Tab -->
            <div class="tab-pane fade" id="participants" role="tabpanel" aria-labelledby="participants-tab">

                <?php // This block checks if the user is a teacher 
                ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'): ?>
                    <div class="d-flex justify-content-end mb-3">
                        <button class="btn btn-secondary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#participantModal">
                            <i class="bi bi-person-plus me-1"></i>Add Participant
                        </button>
                    </div>
                <?php endif; ?>

                <div id="participantAlert" style="display:none;" class="mt-3"></div>
                <div id="participantList" class="mt-2"></div>
            </div>
        </div>

        <!-- Manage Material Categories Modal ↓ -->
        <div class="modal fade" id="manageMaterialCategoriesModal" tabindex="-1" aria-labelledby="manageMaterialCategoriesModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="manageMaterialCategoriesModalLabel">Manage Categories</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <h6 class="mb-3">Create New Category</h6>
                        <form id="add-material-category-form" class="mb-4">
                            <div class="input-group">
                                <input type="text" class="form-control" name="name" required>
                                <button type="submit" class="btn btn-success">Add</button>
                            </div>
                        </form>

                        <hr>

                        <h6 class="mb-3">Existing Categories:</h6>
                        <ul id="material-category-list" class="list-group">
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php
        if (!isset($strand_id) || empty($strand_id)) {
            echo '<div class="alert alert-warning">Missing strand context. Please select a strand first.</div>';
            return;
        }
        ?>

        <div class="modal fade" id="editMaterialModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form id="editMaterialForm" class="modal-content" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">Edit Material</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="editMaterialId">

                        <div class="mb-3">
                            <label for="editLabel" class="form-label">Material Title</label>
                            <input type="text" class="form-control" name="label" id="editLabel" required>
                        </div>
                        <div class="mb-3">
                            <label for="editType" class="form-label">Material Type</label>
                            <select class="form-select" name="type" id="editType" required>
                                <option value="file">File</option>
                                <option value="video">Video</option>
                                <option value="audio">Audio</option>
                                <option value="image">Image</option>
                                <option value="link">Link URL</option>
                            </select>
                        </div>

                        <div class="mb-3" id="editFileGroup" style="display: none;">
                            <label for="editFile" class="form-label">Upload New File (Optional)</label>
                            <input type="file" class="form-control" name="file_path" id="editFile">
                            <div class="form-text">Current: <span id="currentFile"></span></div>
                        </div>

                        <div class="mb-3" id="editLinkGroup" style="display: none;">
                            <label for="editLink" class="form-label">Link URL</label>
                            <input type="url" class="form-control" name="link_url" id="editLink" placeholder="https://example.com">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-3" id="saveMaterialChangesBtn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Material Confirmation Modal -->
        <div class="modal fade" id="deleteMaterialModal" tabindex="-1" aria-labelledby="deleteMaterialModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteMaterialModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to permanently delete this material?</p>
                        <p class="text-danger small">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger rounded-pill px-3" id="confirmDeleteMaterialBtn">Delete</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="materialCategoryActionModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="materialCategoryActionModalLabel">Category Action</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="materialCategoryActionForm">
                        <div class="modal-body">
                            <input type="hidden" name="action" id="materialCategoryActionInput">
                            <input type="hidden" name="id" id="materialCategoryIdInput">

                            <div class="mb-3" id="materialCategoryNameGroup">
                                <label for="materialCategoryNameInput" class="form-label">Category Name</label>
                                <input type="text" class="form-control" id="materialCategoryNameInput" name="name" required>
                            </div>

                            <div id="materialCategoryDeleteConfirm" style="display: none;">
                                <p>Are you sure you want to delete "<strong><span id="deleteMaterialCategoryName">this category</span></strong>"?</p>
                                <small class="text-danger">This action cannot be undone.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-3" id="materialCategorySubmitBtn">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Universal Media Modal -->
        <div class="modal fade" id="mediaModal" tabindex="-1" aria-labelledby="mediaModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="mediaModalLabel">Loading...</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="mediaModalBody">
                        <!-- JavaScript will inject content here -->
                    </div>
                    <div class="modal-footer">
                        <a href="#" id="mediaDownloadLink" class="btn btn-primary" target="_blank" download>Download</a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editAssessmentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="editAssessmentForm">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Assessment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="editAssessmentId" name="assessment_id">
                            <div class="mb-3">
                                <label for="editAssessmentTitle" class="form-label">Title</label>
                                <input type="text" class="form-control" id="editAssessmentTitle" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label d-block">Type</label>
                                <div class="d-flex flex-wrap pt-2" style="gap: 1rem;">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input edit-assessment-type-option" type="radio" name="type" id="editTypeQuiz" value="quiz">
                                        <label class="form-check-label" for="editTypeQuiz">Quiz</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input edit-assessment-type-option" type="radio" name="type" id="editTypeExam" value="exam">
                                        <label class="form-check-label" for="editTypeExam">Exam</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input edit-assessment-type-option" type="radio" name="type" id="editTypeActivity" value="activity">
                                        <label class="form-check-label" for="editTypeActivity">Activity</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input edit-assessment-type-option" type="radio" name="type" id="editTypeAssignment" value="assignment">
                                        <label class="form-check-label" for="editTypeAssignment">Assignment</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input edit-assessment-type-option" type="radio" name="type" id="editTypeProject" value="project">
                                        <label class="form-check-label" for="editTypeProject">Project</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="editAssessmentCategory" class="form-label">Category / Folder</label>
                                <select class="form-select" id="editAssessmentCategory" name="category_id">
                                    <option value="">(No Category)</option>
                                    <?php if (!empty($categories)) {
                                        foreach ($categories as $category) {
                                            echo "<option value='{$category['id']}'>" . htmlspecialchars($category['name']) . "</option>";
                                        }
                                    } ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="editAssessmentDesc" class="form-label">Description</label>
                                <textarea class="form-control" id="editAssessmentDesc" name="description" rows="3"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3" id="editDurationContainer">
                                    <label for="editAssessmentDuration" class="form-label">Duration (mins)</label>
                                    <input type="number" class="form-control" id="editAssessmentDuration" name="duration_minutes" min="1" required>
                                </div>
                                <div class="col-md-6 mb-3" id="editAttemptsContainer">
                                    <label for="editAssessmentAttempts" class="form-label">Max Attempts</label>
                                    <input type="number" class="form-control" id="editAssessmentAttempts" name="max_attempts" min="1" required>
                                </div>
                                <div class="col-md-6 mb-3" id="editTotalPointsContainer" style="display: none;">
                                    <label for="editAssessmentTotalPoints" class="form-label">Total Points</label>
                                    <input type="number" class="form-control" id="editAssessmentTotalPoints" name="total_points" value="20" min="1">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-3">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


        <!-- Delete assessment Modal -->
        <div class="modal fade" id="deleteAssessmentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete "<strong><span id="assessmentNameToDelete">this assessment</span></strong>"?</p>
                        <p class="text-danger small">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger rounded-pill px-3" id="confirmDeleteBtn">Delete</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manage Assessment Categories Modal ↓ -->
        <div class="modal fade" id="manageCategoriesModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Manage Categories</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="add-category-form" class="mb-4">
                            <label for="new-category-name" class="form-label">Create New Category</label>
                            <div class="input-group">
                                <input type="text" id="new-category-name" name="category_name" class="form-control" required>
                                <button type="submit" class="btn btn-success">Add</button>
                            </div>
                        </form>

                        <h6>Existing Categories:</h6>
                        <ul id="category-list" class="list-group">
                            <li class="list-group-item text-muted">Loading...</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assessment Category Action Modal ↓ -->
        <div class="modal fade" id="categoryActionModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Assessment Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="assessmentCategoryActionForm">
                        <div class="modal-body">
                            <input type="hidden" id="assessmentCategoryAction" name="action">
                            <input type="hidden" id="assessmentCategoryIdInput" name="category_id">

                            <div id="assessmentCategoryNameGroup" style="display: none;">
                                <label for="assessmentCategoryNameInput" class="form-label">Category Name</label>
                                <input type="text" id="assessmentCategoryNameInput" name="category_name" class="form-control" required>
                            </div>

                            <div id="assessmentCategoryDeleteConfirm" style="display: none;">
                                <p>Are you sure you want to delete "<strong id="deleteAssessmentCategoryName"></strong>"?</p>
                                <p class="text-danger small">This action cannot be undone.</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-3" id="saveAssessmentCategoryBtn">Save Changes</button>
                            <button type="submit" class="btn btn-danger rounded-pill px-3" id="deleteAssessmentCategoryBtn">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Attempts Modal ↓ -->
        <div class="modal fade" id="viewAttemptsModal" tabindex="-1" aria-labelledby="viewAttemptsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewAttemptsModalLabel">Student Attempts</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="attemptsListContainer">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Review Attempts Modal ↓ -->
        <div class="modal fade" id="reviewAttemptModal" tabindex="-1" aria-labelledby="reviewAttemptModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="reviewAttemptModalLabel">Review Student Attempt</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="reviewAttemptBody">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="closeReviewModalBtn">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Participants Modal ↓ -->
        <div class="modal fade" id="participantModal" tabindex="-1" aria-labelledby="participantModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="participantModalLabel">Add Participants to Learning Strand</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="text" id="studentSearchInput" class="form-control mb-3" placeholder="Search for students by name...">

                        <div id="availableStudentsList" style="max-height: 400px; overflow-y: auto;">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary rounded-pill px-3" id="addSelectedStudentsBtn">Add Students</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="js/strnd.js"></script>
</body>

</html>