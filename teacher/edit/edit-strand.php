<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

$teacher_id = $_SESSION['user_id'];
$strand_id = $_GET['id'] ?? null;
$strand = null;

if (!$strand_id) {
    exit('No strand ID provided.');
}

// Fetch the strand to make sure the teacher owns it
$stmt = $conn->prepare("SELECT * FROM learning_strands WHERE id = ? AND creator_id = ?");
$stmt->bind_param("ii", $strand_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $strand = $result->fetch_assoc();
} else {
    exit('You do not have permission to edit this strand or it does not exist.');
}

// Handle form submission for updating
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['strand_title'];
    $code = $_POST['strand_code'];
    $grade = $_POST['grade_level'];
    $desc = $_POST['description'];

    $stmt_update = $conn->prepare("UPDATE learning_strands SET strand_title = ?, strand_code = ?, grade_level = ?, description = ? WHERE id = ?");
    $stmt_update->bind_param("ssssi", $title, $code, $grade, $desc, $strand_id);

    if ($stmt_update->execute()) {
        $_SESSION['success'] = 'Learning strand updated successfully.';
        header("Location: ../teacher.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Learning Strand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>

<body>
    <div class="container mt-5">
        <h2>Edit Learning Strand</h2>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Strand Title</label>
                <input type="text" class="form-control" name="strand_title" value="<?= htmlspecialchars($strand['strand_title']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Strand Code</label>
                <input type="text" class="form-control" name="strand_code" value="<?= htmlspecialchars($strand['strand_code']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Grade Level</label>
                <select class="form-select" name="grade_level" required>
                    <option value="Grade 11" <?= $strand['grade_level'] == 'Grade 11' ? 'selected' : '' ?>>Grade 11</option>
                    <option value="Grade 12" <?= $strand['grade_level'] == 'Grade 12' ? 'selected' : '' ?>>Grade 12</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="3" required><?= htmlspecialchars($strand['description']) ?></textarea>
            </div>
            <a href="../teacher.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</body>

</html>