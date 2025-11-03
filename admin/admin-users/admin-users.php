<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// --- ADDED: DATA FETCHING FOR DASHBOARD ---
$stats = [
    'total_users' => 0,
    'total_students' => 0,
    'total_teachers' => 0,
    'unverified_users' => 0
];
// Count users by role
$result = $conn->query("SELECT COUNT(*) as count, role FROM users GROUP BY role");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['role'] == 'student') {
            $stats['total_students'] = (int)$row['count'];
        } elseif ($row['role'] == 'teacher') {
            $stats['total_teachers'] = (int)$row['count'];
        }
        // This counts all users including admins
        $stats['total_users'] += (int)$row['count'];
    }
    $result->free();
}
// Count unverified users
$unverified_result = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_verified = 0 AND role != 'admin'");
if ($unverified_result) {
    $stats['unverified_users'] = (int)$unverified_result->fetch_assoc()['count'];
    $unverified_result->free();
}
// --- END: DATA FETCHING ---
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
            <h2 class="mb-0">User Management</h2>
            <div class="mb-3">
                <a href="../admin.php" class="back-link">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value text-primary"><?= $stats['total_teachers'] + $stats['total_students'] ?></div>
                    <div class="stat-label text-primary"><i class="bi bi-people-fill me-2"></i>Total Users</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value text-success"><?= $stats['total_students'] ?></div>
                    <div class="stat-label text-success"><i class="bi bi-person-fill me-2"></i>Total Students</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value text-success"><?= $stats['total_teachers'] ?></div>
                    <div class="stat-label text-success"><i class="bi bi-person-fill me-2"></i>Total Teachers</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value text-danger"><?= $stats['unverified_users'] ?></div>
                    <div class="stat-label text-danger"><i class="bi bi-person-exclamation me-2"></i>Unverified Accounts</div>
                </div>
            </div>
        </div>
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
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="unverified-tab" data-bs-toggle="tab" data-bs-target="#unverified" type="button" role="tab">
                    Unverified <span class="badge text-danger ms-0"><?= $stats['unverified_users'] ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="userTabsContent">
            <div class="tab-pane fade show active" id="all" role="tabpanel">
                <table class="table align-middle">
                    <thead class="table-white">
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Grade Level</th>
                            <th>Status</th>
                            <th class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // UPDATED: Query now hides admins and selects new columns
                        $allQuery = "SELECT id, fname, lname, email, role, is_verified, grade_level 
                                      FROM users 
                                      WHERE role != 'admin' 
                                      ORDER BY role, lname";
                        $allResult = $conn->query($allQuery);

                        if ($allResult->num_rows > 0) {
                            while ($row = $allResult->fetch_assoc()) {
                                $grade_level_display = ($row['role'] == 'student' && !empty($row['grade_level'])) ? htmlspecialchars(ucfirst(str_replace('_', ' ', $row['grade_level']))) : 'N/A';
                                echo '<tr>
                                    <td>' . htmlspecialchars($row['fname'] . ' ' . $row['lname']) . '</td>
                                    <td>' . htmlspecialchars($row['email']) . '</td>
                                    <td>' . htmlspecialchars(ucfirst($row['role'])) . '</td>
                                    <td>' . $grade_level_display . '</td>
                                    <td>' . ($row['is_verified'] ? '<span class="badge text-success">Verified</span>' : '<span class="badge text-warning">Unverified</span>') . '</td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            ' . (!$row['is_verified'] ? '<button class="btn btn-sm btn-primary verify-btn" data-id="' . $row['id'] . '" data-email="' . htmlspecialchars($row['email']) . '" title="Verify User"><i class="bi bi-check-circle"></i></button>' : '') . '
                                            <button class="btn btn-sm btn-success rounded-pill px-3 edit-btn" data-id="' . $row['id'] . '" title="Edit User"><i class="bi bi-pencil-square"></i></button>
                                            <button class="btn btn-sm btn-danger rounded-pill px-3 delete-btn" data-id="' . $row['id'] . '" data-email="' . htmlspecialchars($row['email']) . '" title="Delete User"><i class="bi bi-trash3"></i></button>
                                        </div>
                                    </td>
                                </tr>';
                            }
                        } else {
                            echo "<tr><td colspan='6' class='text-center'>No users found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="tab-pane fade" id="teacher" role="tabpanel">
                <table class="table align-middle">
                    <thead class="table-white">
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // UPDATED: Query now selects new columns
                        $teacherQuery = "SELECT id, fname, lname, email, role, is_verified 
                                           FROM users 
                                           WHERE role = 'teacher' 
                                           ORDER BY lname";
                        $teacherResult = $conn->query($teacherQuery);

                        if ($teacherResult->num_rows > 0) {
                            while ($row = $teacherResult->fetch_assoc()) {
                                echo "<tr>
                                    <td>{$row['fname']} {$row['lname']}</td>
                                    <td>{$row['email']}</td>
                                    <td>" . ($row['is_verified'] ? '<span class="badge text-success">Verified</span>' : '<span class="badge text-warning">Unverified</span>') . "</td>
                                    <td class='text-center'>
                                        <div class='d-flex justify-content-center gap-2'>
                                            " . (!$row['is_verified'] ? '<button class="btn btn-sm btn-success verify-btn" data-id="' . $row['id'] . '" data-email="' . htmlspecialchars($row['email']) . '" title="Verify User"><i class="bi bi-check-circle"></i></button>' : '') . "
                                            <button class='btn btn-sm btn-primary rounded-pill px-3 edit-btn' data-id='{$row['id']}' title='Edit User'><i class='bi bi-pencil-square'></i></button>
                                            <a href='delete-user.php?id={$row['id']}' class='btn btn-sm btn-danger rounded-pill px-3' onclick='return confirm(\"Delete this user?\")' title='Delete User'><i class='bi bi-trash3'></i></a>
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

            <div class="tab-pane fade" id="student" role="tabpanel">
                <table class="table align-middle">
                    <thead class="table-white">
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Grade Level</th>
                            <th>Status</th>
                            <th class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // UPDATED: Query now selects new columns
                        $studentQuery = "SELECT id, fname, lname, email, role, is_verified, grade_level 
                                           FROM users 
                                           WHERE role = 'student' 
                                           ORDER BY lname";
                        $studentResult = $conn->query($studentQuery);

                        if ($studentResult->num_rows > 0) {
                            while ($row = $studentResult->fetch_assoc()) {
                                $grade_level_display = ($row['role'] == 'student' && !empty($row['grade_level'])) ? htmlspecialchars(ucfirst(str_replace('_', ' ', $row['grade_level']))) : 'N/A';
                                echo "<tr>
                                    <td>{$row['fname']} {$row['lname']}</td>
                                    <td>{$row['email']}</td>
                                    <td>{$grade_level_display}</td>
                                    <td>" . ($row['is_verified'] ? '<span class="badge text-success">Verified</span>' : '<span class="badge text-warning">Unverified</span>') . "</td>
                                    <td class='text-center'>
                                        <div class='d-flex justify-content-center gap-2'>
                                            " . (!$row['is_verified'] ? '<button class="btn btn-sm btn-success verify-btn" data-id="' . $row['id'] . '" data-email="' . htmlspecialchars($row['email']) . '" title="Verify User"><i class="bi bi-check-circle"></i></button>' : '') . "
                                            <button class='btn btn-sm btn-primary rounded-pill px-3 edit-btn' data-id='{$row['id']}' title='Edit User'><i class='bi bi-pencil-square'></i></button>
                                            <a href='delete-user.php?id={$row['id']}' class='btn btn-sm btn-danger rounded-pill px-3' onclick='return confirm(\"Delete this user?\")' title='Delete User'><i class='bi bi-trash3'></i></a>
                                        </div>
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center'>No students found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="tab-pane fade" id="unverified" role="tabpanel">
                <table class="table align-middle">
                    <thead class="table-white">
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Grade Level</th>
                            <th class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $unverifiedQuery = "SELECT id, fname, lname, email, role, is_verified, grade_level 
                                            FROM users 
                                            WHERE is_verified = 0 AND role != 'admin'
                                            ORDER BY lname";
                        $unverifiedResult = $conn->query($unverifiedQuery);

                        if ($unverifiedResult->num_rows > 0) {
                            while ($row = $unverifiedResult->fetch_assoc()) {
                                $grade_level_display = ($row['role'] == 'student' && !empty($row['grade_level'])) ? htmlspecialchars(ucfirst(str_replace('_', ' ', $row['grade_level']))) : 'N/A';
                                echo "<tr>
                                    <td>{$row['fname']} {$row['lname']}</td>
                                    <td>{$row['email']}</td>
                                    <td>" . htmlspecialchars(ucfirst($row['role'])) . "</td>
                                    <td>{$grade_level_display}</td>
                                    <td class='text-center'>
                                        <div class='d-flex justify-content-center gap-2'>
                                            <button class='btn btn-sm btn-success verify-btn' data-id='{$row['id']}' data-email='{$row['email']}' title='Verify User'><i class='bi bi-check-circle'></i></button>
                                            <button class='btn btn-sm btn-primary rounded-pill px-3 edit-btn' data-id='{$row['id']}' title='Edit User'><i class='bi bi-pencil-square'></i></button>
                                            <a href='delete-user.php?id={$row['id']}' class='btn btn-sm btn-danger rounded-pill px-3' onclick='return confirm(\"Delete this user?\")' title='Delete User'><i class='bi bi-trash3'></i></a>
                                        </div>
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center'>No unverified users found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="../../ajax/update-user.php" id="editUserForm" class="modal-content">
                <div class="modal-header bg-white">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="id" id="editUserId">

                    <div class="mb-3">
                        <label for="editFname" class="form-label">First Name</label>
                        <input type="text" class="form-control" name="fname" id="editFname" required>
                    </div>

                    <div class="mb-3">
                        <label for="editLname" class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="lname" id="editLname" required>
                    </div>

                    <div class="mb-3">
                        <label for="editEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="editEmail" required>
                    </div>

                    <div class="mb-3">
                        <label for="editRole" class="form-label">Role</label>
                        <select class="form-select" name="role" id="editRole" required>
                            <option value="teacher">Teacher</option>
                            <option value="student">Student</option>
                        </select>
                    </div>

                    <div class="mb-3" id="editGradeLevelGroup">
                        <label for="editGradeLevel" class="form-label">Grade Level</label>
                        <select class="form-select" name="grade_level" id="editGradeLevel">
                            <option value="">N/A</option>

                            <option value="grade_11">Grade 11</option>
                            <option value="grade_12">Grade 12</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="editPassword" class="form-label">New Password (Optional)</label>
                        <input type="password" class="form-control" name="password" id="editPassword" placeholder="Leave blank to keep current password">
                        <div class="form-text">Must be at least 6 characters.</div>
                    </div>

                    <div id="editUserError" class="alert alert-danger" style="display: none;"></div>

                </div>

                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button> <button type="submit" class="btn btn-success rounded-pill px-3" id="saveUserChangesBtn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="verifyUserModal" tabindex="-1" aria-labelledby="verifyUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-white">
                    <h5 class="modal-title" id="verifyUserModalLabel">Manually Verify User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to manually verify this user?</p>
                    <p><strong>Email:</strong> <span id="verifyUserEmail" class="text-muted"></span></p>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success rounded-pill px-3" id="confirmVerifyBtn">Verify</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-white">
                    <h5 class="modal-title" id="deleteUserModalLabel">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to permanently delete this user?</p>
                    <p><strong>Email:</strong> <span id="deleteUserEmail" class="text-muted"></span></p>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger rounded-pill px-3" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/admin-user.js" defer></script>

</body>

</html>