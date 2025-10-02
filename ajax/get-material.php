<?php
require '../includes/db.php';
header('Content-Type: text/html; charset=UTF-8');

$strand_id = $_GET['strand_id'] ?? null;
if (!$strand_id) {
    echo "<p class='text-danger'>Missing strand ID.</p>";
    exit;
}

$materials = $conn->prepare("SELECT * FROM learning_materials WHERE strand_id = ?");
$materials->bind_param("i", $strand_id);
$materials->execute();
$result = $materials->get_result();
?>

<?php if ($result->num_rows > 0): ?>
    <div class="list-group">
        <?php while ($mat = $result->fetch_assoc()): ?>
            <?php
            // Build correct file/link URL
            $fileUrl = '';
            if ($mat['type'] === 'link') {
                $fileUrl = $mat['link_url'];
            } elseif (!empty($mat['file_path'])) {
                // Adjust base path to your real folder
                $fileUrl = '/ALS_LMS/teacher/strand/' . ltrim($mat['file_path'], '/');
            }
            ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong><?= htmlspecialchars($mat['label']) ?></strong>
                    <span class="badge bg-info text-dark ms-2"><?= htmlspecialchars($mat['type']) ?></span>
                    <?php if ($mat['type'] === 'link'): ?>
                        <a href="<?= htmlspecialchars($mat['link_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary ms-3">Open Link</a>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank" class="btn btn-sm btn-outline-secondary ms-3">Download</a>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-light" type="button" id="dropdownMenuButton<?= $mat['id'] ?>"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?= $mat['id'] ?>">
                        <!-- Edit -->
                        <li>
                            <button class="dropdown-item"
                                data-bs-toggle="modal"
                                data-bs-target="#editMaterialModal"
                                data-id="<?= $mat['id'] ?>"
                                data-label="<?= htmlspecialchars($mat['label']) ?>"
                                data-type="<?= htmlspecialchars($mat['type']) ?>"
                                data-file="<?= htmlspecialchars($fileUrl) ?>">
                                <i class="bi bi-pencil-square me-2 text-success"></i> Edit
                            </button>
                        </li>
                        <!-- Delete -->
                        <li>
                            <button class="dropdown-item text-danger delete-material"
                                data-id="<?= $mat['id'] ?>">
                                <i class="bi bi-trash me-2"></i> Delete
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <p class="text-muted">No materials uploaded yet.</p>
<?php endif; ?>