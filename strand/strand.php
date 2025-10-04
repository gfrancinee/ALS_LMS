<?php
session_start();
require_once '../includes/db.php';

$strand_id = $_GET['id'] ?? null;

if (!$strand_id) {
    die("Strand not found.");
}

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
    ORDER BY uploaded_at ASC
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
    <link rel="stylesheet" href="css/strand.css">
    <script>
        window.strandId = <?= json_encode($strand_id) ?>;
        window.userRole = '<?= htmlspecialchars($_SESSION['role'] ?? 'guest') ?>'; // ADD THIS LINE
    </script>
    <script src="js/strnd.js" defer></script>

</head>

<body>
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
        } elseif ($_SESSION['role'] === 'admin') {
            $back_link = '/ALS_LMS/admin/dashboard.php';
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
        <p><?= htmlspecialchars($strand['description']) ?></p>
        <span class="badge bg-secondary"><?= htmlspecialchars($strand['grade_level']) ?></span>

        <!-- Tabs -->
        <ul class="nav nav-tabs mt-4 <?= $tab_class ?>" id="strandTabs" role="tablist">
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
            <!-- Modules Tab -->
            <div class="tab-pane fade show active" id="modules" role="tabpanel" aria-labelledby="modules-tab">

                <?php // This block checks if the user is a teacher 
                ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'): ?>
                    <div class="d-flex justify-content-end mb-3">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                            <i class="bi bi-plus-circle me-1"></i>Upload Material
                        </button>
                    </div>
                <?php endif; ?>

                <div class="mt-3">
                    <?php if ($materials->num_rows > 0): ?>
                        <?php
                        // Define the base path for your project ONCE before the loop
                        $base_path = '/ALS_LMS/';
                        ?>
                        <?php while ($mat = $materials->fetch_assoc()): ?>
                            <?php
                            // Construct the full, correct URL for the file
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

                                        <?php // The Edit/Delete menu is hidden from students 
                                        ?>
                                        <?php if ($_SESSION['role'] === 'teacher'): ?>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <?php
                                                        $file_location = '';
                                                        if ($mat['type'] === 'link') {
                                                            $file_location = htmlspecialchars($mat['link_url'] ?? '');
                                                        } else if (!empty($mat['file_path'])) {
                                                            $file_location = $base_path . htmlspecialchars($mat['file_path']);
                                                        }
                                                        ?>
                                                        <button class="dropdown-item edit-material-btn"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editMaterialModal"
                                                            data-id="<?= $mat['id'] ?>"
                                                            data-label="<?= htmlspecialchars($mat['label']) ?>"
                                                            data-type="<?= htmlspecialchars($mat['type']) ?>"
                                                            data-file="<?= $file_location ?>"
                                                            data-link="<?= htmlspecialchars($mat['link_url'] ?? '') ?>">
                                                            <i class="bi bi-pencil-square me-2 text-success"></i> Edit
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deleteMaterialModal" data-bs-id="<?= $mat['id'] ?>">
                                                            <i class="bi bi-trash3 me-2"></i> Delete
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="material-preview mt-3 text-center">
                                        <?php
                                        $icon_class = 'bi-file-earmark-arrow-down';
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
                        <div class="alert alert-info">No materials have been uploaded yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assessments Tab -->
            <div class="tab-pane fade" id="assessments" role="tabpanel" aria-labelledby="assessments-tab">

                <?php // This block checks if the user is a teacher 
                ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'): ?>
                    <div class="d-flex justify-content-end mb-3">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#assessmentModal">
                            <i class="bi bi-plus-circle me-1"></i>Create Assessment
                        </button>
                    </div>
                <?php endif; ?>

                <div id="assessmentAlert" style="display:none;" class="mt-3"></div>
                <div id="assessmentList" class="mt-2"></div>
            </div>

            <!-- Participants Tab -->
            <div class="tab-pane fade" id="participants" role="tabpanel" aria-labelledby="participants-tab">

                <?php // This block checks if the user is a teacher 
                ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'): ?>
                    <div class="d-flex justify-content-end mb-3">
                        <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#participantModal">
                            <i class="bi bi-person-plus me-1"></i>Add Participant
                        </button>
                    </div>
                <?php endif; ?>

                <div id="participantAlert" style="display:none;" class="mt-3"></div>
                <div id="participantList" class="mt-2"></div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

        <!-- Upload Modal -->
        <div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="materialUploadForm" enctype="multipart/form-data" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="uploadModalLabel">Upload Learning Material</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <!-- Label Textfield -->
                            <div class="mb-3">
                                <label for="materialLabel" class="form-label">Material Label</label>
                                <input type="text" class="form-control" name="materialLabel" id="materialLabel"
                                    placeholder="e.g. Week 1: Introduction to HTML" required>
                            </div>

                            <!-- Material Type Dropdown -->
                            <div class="mb-3">
                                <label for="materialType" class="form-label">Material Type</label>
                                <select class="form-select" name="materialType" id="materialType" required>
                                    <option value="">Select type</option>
                                    <option value="file">File</option>
                                    <option value="video">Video</option>
                                    <option value="image">Image</option>
                                    <option value="audio">Audio</option>
                                    <option value="link">Link</option>
                                </select>
                            </div>

                            <!-- Dynamic Input Container (JS will inject file or link input here) -->
                            <div class="mb-3" id="materialInputContainer"></div>
                            <div id="uploadAlertModal" style="display:none;"></div>
                        </div>

                        <input type="hidden" name="strand_id" value="<?= htmlspecialchars($strand_id) ?>">
                        <input type="hidden" name="teacher_id" value="<?= htmlspecialchars($_SESSION['user_id']) ?>">

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success">Upload</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php
        if (!isset($strand_id) || empty($strand_id)) {
            echo '<div class="alert alert-warning">Missing strand context. Please select a strand first.</div>';
            return;
        }
        ?>

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

        <!-- Edit Material Modal -->
        <div class="modal fade" id="editMaterialModal" tabindex="-1" aria-labelledby="editMaterialModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="ajax/update_material.php" enctype="multipart/form-data">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editMaterialModalLabel">Edit Material</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <!-- Hidden ID -->
                            <input type="hidden" name="id" id="edit-id">

                            <!-- Label -->
                            <div class="mb-3">
                                <label for="edit-label" class="form-label">Material Label</label>
                                <input type="text" class="form-control" id="edit-label" name="label" required>
                            </div>

                            <!-- Type -->
                            <div class="mb-3">
                                <label for="edit-type" class="form-label">Material Type</label>
                                <select class="form-select" id="edit-type" name="type" required>
                                    <option value="">Select type</option>
                                    <option value="file">File</option>
                                    <option value="video">Video</option>
                                    <option value="image">Image</option>
                                    <option value="audio">Audio</option>
                                    <option value="link">Link</option>
                                </select>
                            </div>

                            <div id="currentMaterial" class="mb-2"></div>

                            <!-- Uploaded Material (replace option) -->
                            <div class="mb-3" id="edit-materialInputContainer">
                                <!-- JS will update this dynamically -->
                            </div>

                            <!-- Placeholder for alerts -->
                            <div id="uploadAlertModal" style="display:none;"></div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Material Modal -->
        <div class="modal fade" id="deleteMaterialModal" tabindex="-1" aria-labelledby="deleteMaterialModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteMaterialModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete this learning material?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteMaterialBtn">Delete</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assessment Modal -->
        <div class="modal fade" id="assessmentModal" tabindex="-1" aria-labelledby="assessmentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="createAssessmentForm">
                        <div class="modal-header">
                            <h5 class="modal-title" id="assessmentModalLabel">Create Assessment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="assessmentTitle" class="form-label">Title</label>
                                <input type="text" class="form-control" id="assessmentTitle" required />
                            </div>

                            <div class="mb-3">
                                <label for="assessmentDesc" class="form-label">Description</label>
                                <textarea class="form-control" id="assessmentDesc" rows="3"></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="assessmentDuration" class="form-label">Duration (minutes)</label>
                                    <input type="number" class="form-control" id="assessmentDuration" value="60" min="1" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="assessmentAttempts" class="form-label">Max Attempts</label>
                                    <input type="number" class="form-control" id="assessmentAttempts" value="1" min="1" required>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-success">Create Assessment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Questions Modal ↓ -->
        <div
            class="modal fade"
            id="questionsModal"
            tabindex="-1"
            aria-labelledby="questionsModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form id="questionForm">
                        <input type="hidden" name="strand_id" id="questionStrandId" value="<?= htmlspecialchars($strand_id) ?>">
                        <input type="hidden" name="assessment_id" id="assessmentIdInput" value="">
                        <div class="modal-header">
                            <h5 class="modal-title" id="questionsModalLabel">
                                Manage Questions
                            </h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0">
                            <div id="formBuilder" class="p-3"></div>
                            <div class="text-center my-2">
                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    id="addQuestionBtn"
                                    data-strand-id="<?= htmlspecialchars($strand_id) ?>">
                                    + Add Question
                                </button>


                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">
                                Save Questions
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <template id="questionTemplate">
            <div class="question-block border rounded p-3 mb-3 bg-light">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Question <span class="q-index">1</span></strong>
                    <div>
                        <button type="button" class="btn btn-sm btn-danger remove-question">×</button>
                        <button type="button" class="btn btn-sm btn-secondary move-up">↑</button>
                        <button type="button" class="btn btn-sm btn-secondary move-down">↓</button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Question Text</label>
                    <textarea class="form-control question-text" placeholder="Enter question" required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Question Type</label>
                    <select class="form-select question-type" required>
                        <option value="">Select type</option>
                        <option value="mcq">Multiple Choice</option>
                        <option value="true_false">True/False</option>
                        <option value="short_answer">Short Answer</option>
                        <option value="essay">Essay</option>
                    </select>
                </div>

                <div class="answer-area"></div>
            </div>
        </template>

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

        <!-- Edit assessment modal ↓ -->
        <div class="modal fade" id="editAssessmentModal" tabindex="-1" aria-labelledby="editAssessmentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="editAssessmentForm">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editAssessmentModalLabel">Edit Assessment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="editAssessmentId" name="assessment_id">
                            <div class="mb-3">
                                <label for="editAssessmentTitle" class="form-label">Title</label>
                                <input type="text" class="form-control" id="editAssessmentTitle" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="editAssessmentDesc" class="form-label">Description / Instructions</label>
                                <textarea class="form-control" id="editAssessmentDesc" name="description" rows="3"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="editAssessmentDuration" class="form-label">Duration (minutes)</label>
                                    <input type="number" class="form-control" id="editAssessmentDuration" name="duration" min="1" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="editAssessmentAttempts" class="form-label">Max Attempts</label>
                                    <input type="number" class="form-control" id="editAssessmentAttempts" name="attempts" min="1" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Assessment Confirmation Modal -->
        <div class="modal fade" id="deleteAssessmentModal" tabindex="-1" aria-labelledby="deleteAssessmentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteAssessmentModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to permanently delete this assessment? All associated questions and student attempts will also be lost.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteAssessmentBtn">Delete</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Participants Modal ↓ -->
        <div class="modal fade" id="participantModal" tabindex="-1" aria-labelledby="participantModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="participantModalLabel">Add Participants to Strand</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="text" id="studentSearchInput" class="form-control mb-3" placeholder="Search for students by name...">

                        <div id="availableStudentsList" style="max-height: 400px; overflow-y: auto;">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="addSelectedStudentsBtn">Add Students</button>
                    </div>
                </div>
            </div>
        </div>
</body>

</html>