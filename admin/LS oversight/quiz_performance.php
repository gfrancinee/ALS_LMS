<?php
session_start();
include '../../includes/db.php';

// Security Check: Ensure user is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

// Fetch all learning strands
$sql = "SELECT id, strand_title, strand_code, grade_level 
        FROM learning_strands 
        ORDER BY grade_level, strand_title";
$result = $conn->query($sql);
$strands = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Performance - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/quiz_performance.css">
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
            <img src="../../img/ALS.png" class="top-logo" alt="ALS Logo" />
            <img src="../../img/BNHS.jpg" class="top-logo" alt="BNHS Logo" />
        </div>
    </header>

    <main class="content">
        <div class="container py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Quiz Performance Data</h2>
                <div class="mb-3">
                    <a href="ls.php" class="back-link"> <i class="bi bi-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>

            <ul class="nav nav-tabs" id="gradeTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all-pane" type="button" role="tab">All</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="g11-tab" data-bs-toggle="tab" data-bs-target="#g11-pane" type="button" role="tab">Grade 11</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="g12-tab" data-bs-toggle="tab" data-bs-target="#g12-pane" type="button" role="tab">Grade 12</button>
                </li>
            </ul>

            <div class="tab-content" id="gradeTabsContent">

                <div class="tab-pane fade show active" id="all-pane" role="tabpanel">
                    <table class="table align-middle">
                        <tbody>
                            <?php foreach ($strands as $strand): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold"><i class="bi bi-book-half me-3 text-success mx-3"></i><?= htmlspecialchars($strand['strand_title']) ?></span>
                                            <span class="text-muted small ps-5">
                                                <?= htmlspecialchars($strand['strand_code']) ?> | <?= htmlspecialchars($strand['grade_level']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <a href="learning_strand_attempts.php?id=<?= $strand['id'] ?>" class="btn text-primary btn-sm me-3 btn-pill-hover">
                                            View Attempts
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="g11-pane" role="tabpanel">
                    <table class="table mt-4">
                        <tbody>
                            <?php
                            $g11_count = 0;
                            foreach ($strands as $strand):
                                if ($strand['grade_level'] == 'Grade 11'):
                                    $g11_count++;
                            ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-bold"><i class="bi bi-book-half me-3 text-success mx-3"></i><?= htmlspecialchars($strand['strand_title']) ?></span>
                                                <span class="text-muted small ps-5">
                                                    <?= htmlspecialchars($strand['strand_code']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <a href="learning_strand_attempts.php?id=<?= $strand['id'] ?>" class="btn text-primary btn-sm me-3 btn-pill-hover">
                                                View Attempts
                                            </a>
                                        </td>
                                    </tr>
                            <?php
                                endif;
                            endforeach;
                            if ($g11_count == 0) echo "<tr><td colspan='2' class='text-center text-muted p-4'>No Grade 11 strands found.</td></tr>";
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="g12-pane" role="tabpanel">
                    <table class="table mt-4">
                        <tbody>
                            <?php
                            $g12_count = 0;
                            foreach ($strands as $strand):
                                if ($strand['grade_level'] == 'Grade 12'):
                                    $g12_count++;
                            ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-bold"><i class="bi bi-book-half me-3 text-success mx-3"></i><?= htmlspecialchars($strand['strand_title']) ?></span>
                                                <span class="text-muted small ps-5">
                                                    <?= htmlspecialchars($strand['strand_code']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <a href="learning_strand_attempts.php?id=<?= $strand['id'] ?>" class="btn text-primary btn-sm me-3 btn-pill-hover">
                                                View Attempts
                                            </a>
                                        </td>
                                    </tr>
                            <?php
                                endif;
                            endforeach;
                            if ($g12_count == 0) echo "<tr><td colspan='2' class='text-center text-muted p-4'>No Grade 12 strands found.</td></tr>";
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>