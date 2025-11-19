<?php
session_start();
require_once '../includes/db.php'; // Adjust path if your admin folder is deeper

// Security Check: Only Admin can see this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Verify Students</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body class="bg-light">

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Pending Student Verifications</h2>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>LRN</th>
                                <th>Grade Level</th>
                                <th>Email Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Fetch students who are registered (Student role) but NOT verified by admin yet
                            $sql = "SELECT * FROM users WHERE role = 'student' AND is_admin_verified = 0 ORDER BY create_at DESC";
                            $result = $conn->query($sql);

                            if ($result->num_rows > 0):
                                while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($row['fname'] . ' ' . $row['lname']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($row['email']) ?></div>
                                        </td>
                                        <td><span class="badge bg-info text-dark fs-6"><?= htmlspecialchars($row['lrn']) ?></span></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars(str_replace('_', ' ', $row['grade_level'])) ?></span></td>
                                        <td>
                                            <?php if ($row['is_verified'] == 1): ?>
                                                <span class="text-success"><i class="bi bi-check-circle-fill"></i> Verified</span>
                                            <?php else: ?>
                                                <span class="text-warning"><i class="bi bi-exclamation-circle-fill"></i> Unverified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button onclick="verifyStudent(<?= $row['id'] ?>)" class="btn btn-success btn-sm">
                                                <i class="bi bi-check-lg"></i> Approve LRN
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No pending verifications.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function verifyStudent(userId) {
            if (confirm("Are you sure you want to verify this student's LRN? They will be able to join classes after this.")) {

                const formData = new FormData();
                formData.append('user_id', userId);

                // Make sure you created this file in your 'ajax/' folder previously
                fetch('../ajax/admin_verify_student.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert("Student verified successfully!");
                            location.reload();
                        } else {
                            alert("Error verifying student: " + (data.message || "Unknown error"));
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert("Network error occurred.");
                    });
            }
        }
    </script>
</body>

</html>