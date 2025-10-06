<?php
session_start();
require_once '../includes/auth.php'; // Ensures the user is logged in
require_once '../includes/db.php';

// Check if user is a student
if ($_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$assessment_id = $_GET['assessment_id'] ?? null;
if (!$assessment_id) {
    die("Error: No assessment specified.");
}

// Fetch assessment details
// Fetch assessment details
$stmt = $conn->prepare("SELECT strand_id, title, description, duration_minutes FROM assessments WHERE id = ?");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();
$stmt->close();
// Store the strand_id in a variable
$strand_id = $assessment['strand_id'];

// Fetch questions and their options
$stmt = $conn->prepare("SELECT * FROM questions WHERE assessment_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$questions_result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Quiz<?= htmlspecialchars($assessment['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h1><?= htmlspecialchars($assessment['title']) ?></h1>
        <p class="lead"><?= htmlspecialchars($assessment['description']) ?></p>
        <hr>

        <form action="submit_quiz.php" method="POST">
            <input type="hidden" name="assessment_id" value="<?= (int)$assessment_id ?>">
            <input type="hidden" name="strand_id" value="<?= (int)$strand_id ?>">

            <?php $q_num = 1;
            while ($question = $questions_result->fetch_assoc()): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <strong>Question <?= $q_num ?></strong>
                    </div>
                    <div class="card-body">
                        <p class="card-text"><?= htmlspecialchars($question['question_text']) ?></p>

                        <?php if ($question['question_type'] === 'mcq'): ?>
                            <?php
                            $opt_stmt = $conn->prepare("SELECT * FROM question_options WHERE question_id = ?");
                            $opt_stmt->bind_param("i", $question['id']);
                            $opt_stmt->execute();
                            $options = $opt_stmt->get_result();
                            $opt_stmt->close();
                            while ($option = $options->fetch_assoc()):
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="answers[<?= $question['id'] ?>]" id="opt_<?= $option['id'] ?>" value="<?= htmlspecialchars($option['option_key']) ?>" required>
                                    <label class="form-check-label" for="opt_<?= $option['id'] ?>">
                                        <?= htmlspecialchars($option['option_text']) ?>
                                    </label>
                                </div>
                            <?php endwhile; ?>

                        <?php elseif ($question['question_type'] === 'true_false'): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="answers[<?= $question['id'] ?>]" id="q_<?= $question['id'] ?>_true" value="True" required>
                                <label class="form-check-label" for="q_<?= $question['id'] ?>_true">True</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="answers[<?= $question['id'] ?>]" id="q_<?= $question['id'] ?>_false" value="False" required>
                                <label class="form-check-label" for="q_<?= $question['id'] ?>_false">False</label>
                            </div>

                        <?php else: // Short Answer / Essay 
                        ?>
                            <textarea name="answers[<?= $question['id'] ?>]" class="form-control" rows="3" required></textarea>
                        <?php endif; ?>
                    </div>
                </div>
            <?php $q_num++;
            endwhile; ?>

            <button type="submit" class="btn btn-success">Submit Answers</button>
        </form>

    </div>

    <script>
        const duration = <?= (int)$assessment['duration_minutes'] ?> * 60; // Get duration in seconds
        const timerDisplay = document.createElement('div');
        timerDisplay.className = 'alert alert-warning position-fixed top-0 end-0 m-3';
        document.body.appendChild(timerDisplay);

        let timer = duration;
        const quizForm = document.querySelector('form');

        const intervalId = setInterval(() => {
            let minutes = parseInt(timer / 60, 10);
            let seconds = parseInt(timer % 60, 10);

            minutes = minutes < 10 ? "0" + minutes : minutes;
            seconds = seconds < 10 ? "0" + seconds : seconds;

            timerDisplay.textContent = `Time Left: ${minutes}:${seconds}`;

            if (--timer < 0) {
                clearInterval(intervalId);
                alert('Time is up! Your quiz will be submitted automatically.');
                quizForm.submit();
            }
        }, 1000);

        quizForm.addEventListener('submit', () => {
            clearInterval(intervalId); // Stop the timer when the user submits manually
        });
    </script>
</body>

</html>