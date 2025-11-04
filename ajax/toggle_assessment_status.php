<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php'; // Required for notifications
header('Content-Type: application/json');

// Security Check: Only for logged-in teachers
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$teacher_id = $_SESSION['user_id'];

// 1. READ THE JSON PAYLOAD FROM JAVASCRIPT
$data = json_decode(file_get_contents('php://input'), true);
$assessment_id = $data['assessment_id'] ?? 0;
// Convert the (true/false) from JavaScript to a (1/0) for the database
$new_is_open = $data['is_open'] ? 1 : 0;

if (empty($assessment_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Assessment ID is required.']);
    exit;
}

$conn->begin_transaction();
try {
    // --- 2. GET THE CURRENT (OLD) STATE BEFORE UPDATING ---
    $old_is_open = 0;
    $strand_id = null;
    $assessment_title = '';

    // This query assumes your 'assessments' table has 'is_open', 'strand_id', and 'title'
    $stmt_check = $conn->prepare(
        "SELECT is_open, strand_id, title FROM assessments WHERE id = ? AND teacher_id = ?"
    );
    $stmt_check->bind_param("ii", $assessment_id, $teacher_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $current_assessment = $result_check->fetch_assoc();
        $old_is_open = $current_assessment['is_open'];
        $strand_id = $current_assessment['strand_id'];
        $assessment_title = $current_assessment['title'];
    } else {
        throw new Exception("Assessment not found or permission denied.");
    }
    $stmt_check->close();

    // --- 3. UPDATE THE ASSESSMENT TO THE NEW STATE ---
    $stmt_update = $conn->prepare(
        "UPDATE assessments SET is_open = ? WHERE id = ? AND teacher_id = ?"
    );
    $stmt_update->bind_param("iii", $new_is_open, $assessment_id, $teacher_id);
    if (!$stmt_update->execute()) {
        throw new Exception('Database update error: ' . $stmt_update->error);
    }
    $stmt_update->close();

    // --- 4. SEND NOTIFICATIONS (ONLY if it changed from 0 to 1) ---
    if ($new_is_open == 1 && $old_is_open == 0 && $strand_id) {

        // --- THIS IS THE FIX ---
        // Find all students in this strand using the correct 'student_id' column
        $stmt_students = $conn->prepare("SELECT student_id FROM strand_participants WHERE strand_id = ?");
        // --- END OF FIX ---

        $stmt_students->bind_param("i", $strand_id);
        $stmt_students->execute();
        $result_students = $stmt_students->get_result();

        // Set up notification details
        $notification_type = 'open_assessment';
        $notification_link = "strand/take_assessment.php?id=" . $assessment_id;
        $notification_message = "A new assessment is now open: '" . htmlspecialchars($assessment_title) . "'.";

        // Loop and notify each student
        while ($row = $result_students->fetch_assoc()) {

            // --- THIS IS THE FIX ---
            // Use the correct 'student_id' column from the row
            $student_id = $row['student_id'];
            // --- END OF FIX ---

            // Don't notify the teacher who just clicked the button
            if ($student_id != $teacher_id) {
                create_notification($conn, $student_id, $notification_type, $assessment_id, $notification_message, $notification_link);
            }
        }
        $stmt_students->close();
    }

    // --- 5. Commit and send success ---
    $conn->commit();
    // Send back the new label so the JavaScript can be 100% sure
    echo json_encode(['success' => true, 'new_status_label' => $new_is_open ? 'Open' : 'Closed']);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
