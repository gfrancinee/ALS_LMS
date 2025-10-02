<?php
session_start();
require_once '../../includes/db.php';

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
    <link rel="stylesheet" href="css/strand.css">
    <script>
        window.strandId = <?= json_encode($strand_id) ?>;
    </script>
    <script src="js/strnd.js" defer></script>

</head>

<body>
    <div class="back-container">
        <a href="/ALS_LMS/teacher/teacher.php" class="back-link">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
    <div class="container mt-4">
        <h2><?= htmlspecialchars($strand['strand_title']) ?> <small class="text-muted">(<?= htmlspecialchars($strand['strand_code']) ?>)</small></h2>
        <p><?= htmlspecialchars($strand['description']) ?></p>
        <span class="badge bg-secondary"><?= htmlspecialchars($strand['grade_level']) ?></span>

        <!-- Tabs -->
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
            <!-- Modules Tab -->
            <div class="tab-pane fade show active" id="modules" role="tabpanel" aria-labelledby="modules-tab">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="bi bi-plus-circle me-1"></i>Upload Material
                </button>

                <!-- Show uploaded materials with Edit/Delete -->
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

                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <?php
                                                    // This is the corrected Edit button logic from our previous conversation
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
                                    </div>

                                    <div class="material-preview mt-3 text-center">
                                        <?php
                                        $icon_class = 'bi-file-earmark-arrow-down'; // Default icon
                                        $modal_title = htmlspecialchars($mat['label']);

                                        // Determine the icon based on the material type
                                        if ($mat['type'] === 'image') $icon_class = 'bi-card-image';
                                        if ($mat['type'] === 'video') $icon_class = 'bi-play-btn';
                                        if ($mat['type'] === 'audio') $icon_class = 'bi-music-note-beamed';
                                        if ($mat['type'] === 'file')  $icon_class = 'bi-file-earmark-text';
                                        if ($mat['type'] === 'link')  $icon_class = 'bi-link-45deg';

                                        // For links, the URL is different
                                        $media_url = ($mat['type'] === 'link') ? htmlspecialchars($mat['link_url'] ?? '') : $full_url;
                                        ?>

                                        <?php if ($mat['type'] === 'link'): ?>
                                            <!-- External Link opens in a new tab -->
                                            <a href="<?= $media_url ?>" target="_blank" class="d-block">
                                                <i class="bi <?= $icon_class ?>" style="font-size: 4rem;"></i>
                                                <p class="mt-2">Open Link</p>
                                            </a>
                                        <?php else: ?>
                                            <!-- All other types open in a modal -->
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
                <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#assessmentModal">
                    <i class="bi bi-plus-circle me-1"></i>Create Assessment
                </button>

                <!-- Feedback + Assessment List -->
                <div id="assessmentAlert" style="display:none;" class="mt-3"></div>
                <div id="assessmentList" class="mt-2"></div>
            </div>

            <!-- Participants Tab -->
            <div class="tab-pane fade" id="participants" role="tabpanel" aria-labelledby="participants-tab">
                <button class="btn btn-secondary mb-3" data-bs-toggle="modal" data-bs-target="#participantModal">
                    <i class="bi bi-person-plus me-1"></i>Add Participant
                </button>

                <!-- Feedback + Participants List -->
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
        <div
            class="modal fade"
            id="assessmentModal"
            tabindex="-1"
            aria-labelledby="assessmentModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form
                        id="assessmentForm"
                        method="post"
                        action="../../ajax/save-assessment.php">
                        <div class="modal-header">
                            <h5 class="modal-title" id="assessmentModalLabel">Create Assessment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="assessmentTitle" class="form-label">Title</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="assessmentTitle"
                                    name="assessmentTitle"
                                    required />
                            </div>

                            <div class="mb-3">
                                <label for="assessmentType" class="form-label">Type</label>
                                <select
                                    class="form-select"
                                    id="assessmentType"
                                    name="assessmentType"
                                    required>
                                    <option value="">Select type</option>
                                    <option value="quiz">Quiz</option>
                                    <option value="exam">Exam</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label
                                    for="assessmentDescription"
                                    class="form-label">Description</label>
                                <textarea
                                    class="form-control"
                                    id="assessmentDescription"
                                    name="assessmentDescription"
                                    rows="3"></textarea>
                            </div>

                            <!-- Hidden strand_id -->
                            <input type="hidden" name="strand_id" id="assessmentStrandId" value="<?= htmlspecialchars($strand_id) ?>">
                        </div>

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success">
                                Save Assessment
                            </button>
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