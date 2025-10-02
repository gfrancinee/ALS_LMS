<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db.php';

// 1) Grab & sanitize inputs
$teacherId   = $_SESSION['user_id']                      ?? null;
$strandId    = $_POST['strand_id']                       ?? null;
$title       = trim(strip_tags($_POST['assessmentTitle'] ?? ''));
$type        = trim(strip_tags($_POST['assessmentType']  ?? ''));
$description = trim(strip_tags($_POST['assessmentDescription'] ?? ''));
$questions   = $_POST['questions']                       ?? [];

// 2) Basic validation
$errors = [];
if (!$teacherId)    $errors[] = 'User not authenticated.';
if (!$strandId)     $errors[] = 'Missing strand ID.';
if (!$title)        $errors[] = 'Assessment title is required.';
if (!$type)         $errors[] = 'Assessment type is required.';

if ($errors) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => implode(' ', $errors)
    ]);
    exit;
}

// 3) Insert assessment
$stmt = $conn->prepare("
    INSERT INTO assessments
      (strand_id, teacher_id, title, type, description)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param(
    'iisss',
    $strandId,
    $teacherId,
    $title,
    $type,
    $description
);

if (! $stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'DB error: ' . $stmt->error
    ]);
    exit;
}

$assessmentId = $stmt->insert_id;

// 4) If questions exist, bulk-save them
if (! empty($questions)) {
    // 4a) Delete any old questions for a clean slate
    $del = $conn->prepare("
        DELETE q, o
          FROM questions q
     LEFT JOIN question_options o ON o.question_id = q.id
         WHERE q.assessment_id = ?
    ");
    $del->bind_param('i', $assessmentId);
    $del->execute();

    // 4b) Prepare insert statements
    $qStmt = $conn->prepare("
        INSERT INTO questions
          (assessment_id, question_type, question_text)
        VALUES (?, ?, ?)
    ");
    $oStmt = $conn->prepare("
        INSERT INTO question_options
          (question_id, `key`, value)
        VALUES (?, ?, ?)
    ");

    // 4c) Loop through each question block
    // --- AFTER (Correct) ---
    foreach ($questions as $question) {
        // Bind the parameters for the current question
        $question_insert_stmt->bind_param(
            "iisss",
            $assessment_id,
            $strand_id,
            $question['text'],
            $question['type'],
            $question['answer']
        );
        // Execute the statement to save THIS question
        $question_insert_stmt->execute();

        // If it's a multiple-choice question, save its options
        if ($question['type'] === 'mcq') {
            // Get the ID of the question we just created
            $new_question_id = $conn->insert_id;

            $option_keys = ['a', 'b', 'c', 'd'];
            foreach ($question['options'] as $index => $option_text) {
                if (isset($option_keys[$index])) {
                    // Bind and execute for each option
                    $option_insert_stmt->bind_param(
                        "iss",
                        $new_question_id,
                        $option_keys[$index],
                        $option_text
                    );
                    $option_insert_stmt->execute();
                }
            }
        }
    }
    // 5) Return success + assessment ID
    echo json_encode([
        'status'        => 'success',
        'message'       => 'Assessment & questions saved.',
        'assessment_id' => $assessmentId
    ]);
}
