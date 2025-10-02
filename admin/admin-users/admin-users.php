<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>User Management | ALS LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/admin-users.css">
</head>

<body>

    <header class="topbar sticky-top d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
        <div class="d-flex align-items-left">
            <h1 class="title m-0">
                <div id="font">
                    <span>A</span><span>L</span><span>S</span> Learning Management System
                </div>
            </h1>
        </div>
        <div class="top-icons d-flex align-items-center gap-3">
            <img src="img/ALS.png" class="top-logo" alt="ALS Logo" />
            <img src="img/BNHS.jpg" class="top-logo" alt="BNHS Logo" />
    </header>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">User Management</h2>
            <div class="mb-3">
                <a href="../admin.php" class="back-link">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" id="userTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">All</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="teacher-tab" data-bs-toggle="tab" data-bs-target="#teacher" type="button" role="tab">Teacher</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="student-tab" data-bs-toggle="tab" data-bs-target="#student" type="button" role="tab">Student</button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="userTabsContent">
            <!-- All Users -->
            <div class="tab-pane fade show active" id="all" role="tabpanel">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th> </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $allQuery = "SELECT * FROM users ORDER BY role, lname";
                        $allResult = $conn->query($allQuery);

                        if ($allResult->num_rows > 0) {
                            while ($row = $allResult->fetch_assoc()) {
                                echo '<tr>
            <td>' . htmlspecialchars($row['fname'] . ' ' . $row['lname']) . '</td>
            <td>' . htmlspecialchars($row['email']) . '</td>
            <td>' . htmlspecialchars($row['role']) . '</td>
            <td class="text-center">
  <div class="d-flex justify-content-center gap-2">
    <button class="btn btn-sm btn-primary edit-btn" data-id="' . $row['id'] . '">Edit</button>
    <a href="delete-user.php?id=' . $row['id'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to delete this user?\')">Delete</a>
  </div>
</td>
        </tr>';
                            }
                        } else {
                            echo "<tr><td colspan='4' class='text-center'>No users found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Teachers -->
            <div class="tab-pane fade" id="teacher" role="tabpanel">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th> </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $teacherQuery = "SELECT * FROM users WHERE role = 'teacher' ORDER BY lname";
                        $teacherResult = $conn->query($teacherQuery);

                        if ($teacherResult->num_rows > 0) {
                            while ($row = $teacherResult->fetch_assoc()) {
                                echo "<tr>
  <td>{$row['fname']} {$row['lname']}</td>
  <td>{$row['email']}</td>
  <td>{$row['role']}</td>
  <td class='text-center'>
    <div class='d-flex justify-content-center gap-2'>
      <button class='btn btn-sm btn-primary edit-btn' data-id='{$row['id']}'>Edit</button>
      <a href='delete-user.php?id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Delete this user?\")'>Delete</a>
    </div>
  </td>
</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' class='text-center'>No teachers found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Students -->
            <div class="tab-pane fade" id="student" role="tabpanel">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th> </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $studentQuery = "SELECT * FROM users WHERE role = 'student' ORDER BY lname";
                        $studentResult = $conn->query($studentQuery);

                        if ($studentResult->num_rows > 0) {
                            while ($row = $studentResult->fetch_assoc()) {
                                echo "<tr>
                       <td>{$row['fname']} {$row['lname']}</td>
                       <td>{$row['email']}</td>
                       <td>{$row['role']}</td>
                       <td class='text-center'>
                    <div class='d-flex justify-content-center gap-2'>
                    <button class='btn btn-sm btn-primary edit-btn' data-id='{$row['id']}'>Edit</button>
                    <a href='delete-user.php?id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Delete this user?\")'>Delete</a>
                </div>
               </td>
               </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' class='text-center'>No students found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="../../ajax/update-user.php" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <!-- Hidden field for user ID -->
                    <input type="hidden" name="id" id="editUserId">

                    <!-- First Name -->
                    <div class="mb-3">
                        <label for="editFname" class="form-label">First Name</label>
                        <input type="text" class="form-control" name="fname" id="editFname" required>
                    </div>

                    <!-- Last Name -->
                    <div class="mb-3">
                        <label for="editLname" class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="lname" id="editLname" required>
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <label for="editEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="editEmail" required>
                    </div>

                    <!-- Role -->
                    <div class="mb-3">
                        <label for="editRole" class="form-label">Role</label>
                        <select class="form-select" name="role" id="editRole" required>
                            <option value="admin">Admin</option>
                            <option value="teacher">Teacher</option>
                            <option value="student">Student</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Save Changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/admin-user.js" defer></script>

</body>

</html>