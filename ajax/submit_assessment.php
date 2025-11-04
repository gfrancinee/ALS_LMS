<?php
// --- In your ajax/submit_assessment.php file ---
// After you have saved the student's answers and know the $assessment_id...

// 1. Get the teacher_id for this assessment
$stmt_teacher = $conn->prepare("SELECT teacher_id FROM assessments WHERE id = ?");
$stmt_teacher->bind_param("i", $assessment_id);
$stmt_teacher->execute();
$teacher_id = $stmt_teacher->get_result()->fetch_assoc()['teacher_id'];
$stmt_teacher->close();

if ($teacher_id) {
    // 2. Define the notification details
    $notification_type = 'assessment_attempt';
    // This link will take the teacher to the grading page
    $notification_link = "grade_assessment.php?id=" . $assessment_id;

    // 3. This one query does all the work:
    // It tries to INSERT a new notification.
    // If a row for this teacher/assessment already exists (ON DUPLICATE KEY),
    // it will UPDATE that row by adding 1 to the 'count', marking it as unread,
    // and updating the timestamp.
    $stmt_notify = $conn->prepare("
        INSERT INTO notifications (user_id, type, related_id, count, link, is_read)
        VALUES (?, ?, ?, 1, ?, 0)
        ON DUPLICATE KEY UPDATE
            count = count + 1,
            is_read = 0,
            updated_at = NOW()
    ");
    $stmt_notify->bind_param("isss", $teacher_id, $notification_type, $assessment_id, $notification_link);
    $stmt_notify->execute();
    $stmt_notify->close();
}
