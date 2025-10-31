<?php
$allowed_roles = ['admin'];
// Path goes up two levels to the root, then into 'includes'
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// Fetch all learning strands from the database
$strands_result = $conn->query("SELECT * FROM learning_strands ORDER BY strand_title ASC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Strand & Materials Editor | ALS LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/strand-editor.css">
</head>

<body>
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
            <h2 class="mb-0">Strand & Materials Editor</h2>
            <div>
                <a href="ls.php" class="back-link me-3">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <table class="table align-middle">
            <thead class="table-white">
                <tr>
                    <th>Learning Strand Title</th>
                    <th class="text-center"></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($strands_result && $strands_result->num_rows > 0) {
                    while ($row = $strands_result->fetch_assoc()) {
                        $strand_title = htmlspecialchars($row['strand_title']);

                        // --- ADDED: Get and format new data ---
                        $strand_code = htmlspecialchars($row['strand_code']);

                        // Formats 'grade_11' into 'Grade 11', shows 'N/A' if empty
                        $grade_level_display = (!empty($row['grade_level']))
                            ? htmlspecialchars(ucfirst(str_replace('_', ' ', $row['grade_level'])))
                            : 'N/A';
                        // --- END ADDED ---

                        echo "
                        <tr>
                            <td>
                                <div class=\"d-flex flex-column\">
                                    <span class=\"fw-bold\"><i class=\"bi bi-book-half me-3 text-success\"></i>{$strand_title}</span>
                                    
                                    <span class=\"text-muted small ps-5\">
                                        {$strand_code} | {$grade_level_display}
                                    </span>
                                </div>
                            </td>
                            <td class=\"text-end\">
                                <div class=\"d-flex justify-content-end gap-2\">
                                    <button class=\"btn btn-sm btn-info manage-materials-btn\" data-id=\"{$row['id']}\" title=\"Manage Materials\">
                                        <i class=\"bi bi-folder-fill\"></i>
                                    </button>
                                    <button class=\"btn btn-sm btn-primary edit-strand-btn\" data-id=\"{$row['id']}\" title=\"Edit Learning Strand\">
                                        <i class=\"bi bi-pencil-square\"></i>
                                    </button>
                                    <button class=\"btn btn-sm btn-danger delete-strand-btn\" data-id=\"{$row['id']}\" title=\"Delete Learning Strand\">
                                        <i class=\"bi bi-trash3\"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        ";
                    }
                } else {
                    echo "<tr><td colspan='2' class='text-center'>No learning strands found. Click 'Add New Strand' to begin.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="editStrandModal" tabindex="-1" aria-labelledby="editStrandModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form id="editStrandForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editStrandModalLabel">Edit Learning Strand</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="editStrandId">

                    <div class="mb-3">
                        <label for="editStrandTitle" class="form-label">Strand Title</label>
                        <input type="text" class="form-control" name="strand_title" id="editStrandTitle" required>
                    </div>

                    <div class="mb-3">
                        <label for="editStrandCode" class="form-label">Strand Code</label>
                        <input type="text" class="form-control" name="strand_code" id="editStrandCode" required>
                    </div>

                    <div class="mb-3">
                        <label for="editGradeLevel" class="form-label">Grade Level</label>
                        <select class="form-select" name="grade_level" id="editGradeLevel">
                            <option value="">N/A</option>

                            <option value="Grade 11">Grade 11</option>
                            <option value="Grade 12">Grade 12</option>
                        </select>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="saveStrandChangesBtn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="deleteStrandModal" tabindex="-1" aria-labelledby="deleteStrandModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteStrandModalLabel">Delete Learning Strand</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to permanently delete this learning strand?</p>
                    <p><strong>Title:</strong> <span id="deleteStrandTitle" class="text-muted"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteStrandBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/strand-editor.js"></script>
</body>

</html>