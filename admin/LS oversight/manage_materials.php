<?php
$allowed_roles = ['admin'];
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// 1. Get and validate the Strand ID from the URL
if (!isset($_GET['strand_id']) || !is_numeric($_GET['strand_id'])) {
    die("Invalid strand ID.");
}
$strand_id = intval($_GET['strand_id']);

// 2. Fetch the Strand's details (to show its name)
$strand_stmt = $conn->prepare("SELECT strand_title FROM learning_strands WHERE id = ?");
$strand_stmt->bind_param("i", $strand_id);
$strand_stmt->execute();
$strand_result = $strand_stmt->get_result();

if ($strand_result->num_rows === 0) {
    die("Strand not found.");
}
$strand = $strand_result->fetch_assoc();
$strand_title = htmlspecialchars($strand['strand_title']);
$strand_stmt->close();

// 3. Fetch all materials for this strand
// --- UPDATED THIS LINE ---
$materials_result = $conn->prepare("SELECT * FROM learning_materials WHERE strand_id = ? ORDER BY label ASC"); // Was 'title'
$materials_result->bind_param("i", $strand_id);
$materials_result->execute();
$materials = $materials_result->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Materials: <?php echo $strand_title; ?> | ALS LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/manage-materials.css">
</head>

<body class="bg-light">
    <header class="topbar sticky-top d-flex justify-content-between align-items-center px-4 py-3">
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

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>
                <h2 class="mb-0">Manage Materials</h2>
                <p class="text-muted">For Learning Strand: <strong><?php echo $strand_title; ?></strong></p>
            </div>

            <div>
                <a href="strand_materials_editor.php" class="back-link me-3">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>

        </div>

        <table class="table align-middle">
            <thead class="table-light">
                <tr>
                    <th>Material Title</th>
                    <th>Type</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($materials && $materials->num_rows > 0) {
                    while ($row = $materials->fetch_assoc()) {

                        $material_title = htmlspecialchars($row['label']);
                        $material_type = htmlspecialchars(ucfirst($row['type']));

                        // --- START: ADDED ICON LOGIC ---
                        $icon = 'bi-file-earmark-text'; // Default icon

                        if ($row['type'] === 'file') {
                            $ext = strtolower(pathinfo($row['file_path'], PATHINFO_EXTENSION));
                            if ($ext === 'pdf') $icon = 'bi-file-earmark-pdf-fill text-danger';
                            elseif (in_array($ext, ['ppt', 'pptx'])) $icon = 'bi-file-earmark-slides-fill text-warning';
                        } elseif ($row['type'] === 'link') {
                            $icon = 'bi-link-45deg text-primary';
                        } elseif ($row['type'] === 'image') {
                            $icon = 'bi-card-image text-success';
                        } elseif ($row['type'] === 'video') {
                            $icon = 'bi-play-circle-fill text-info';
                        } elseif ($row['type'] === 'audio') {
                            $icon = 'bi-volume-up-fill text-purple';
                        }
                        // --- END: ADDED ICON LOGIC ---

                        echo "
                        <tr>
                            <td><i class=\"bi {$icon} me-3\"></i>{$material_title}</td>
                            <td>{$material_type}</td>
                           <td class=\"text-end\">
                                <div class=\"d-flex justify-content-end gap-2\">
                                    <button class=\"btn btn-sm btn-primary rounded-pill px-3 edit-material-btn\" data-id=\"{$row['id']}\" title=\"Edit Material\">
                                        <i class=\"bi bi-pencil-square\"></i>
                                    </button>
                                    <button class=\"btn btn-sm btn-danger rounded-pill px-3 delete-material-btn\" data-id=\"{$row['id']}\" title=\"Delete Material\">
                                        <i class=\"bi bi-trash3\"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        ";
                    }
                } else {
                    echo "<tr><td colspan='3' class='text-center'>No materials found for this strand.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

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

    <div class="modal fade" id="deleteMaterialModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Delete Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to permanently delete this material?</p>
                    <p><strong>Title:</strong> <span id="deleteMaterialLabel" class="text-muted"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger rounded-pill px-3" id="confirmDeleteMaterialBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/manage-materials.js"></script>
</body>

</html>