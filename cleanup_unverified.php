<?php
// FILE: cleanup_unverified.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db.php';

// --- This new version is smarter and safer ---

// Step 1: Delete all unverified STUDENTS older than 24 hours. This is always safe.
$sql_students = "DELETE FROM users WHERE is_verified = 0 AND role = 'student' AND create_at < NOW() - INTERVAL 24 HOUR";
$conn->query($sql_students);
$deleted_students = $conn->affected_rows;

// Step 2: Delete unverified TEACHERS older than 24 hours ONLY IF they have no assessments.
// We use a subquery to find teachers whose ID does not appear in the assessments table.
$sql_teachers = "DELETE FROM users 
                 WHERE is_verified = 0 
                 AND role = 'teacher' 
                 AND create_at < NOW() - INTERVAL 24 HOUR
                 AND id NOT IN (SELECT DISTINCT teacher_id FROM assessments)";
$conn->query($sql_teachers);
$deleted_teachers = $conn->affected_rows;

echo "Cleanup complete.<br>";
echo "Deleted " . $deleted_students . " unverified student(s).<br>";
echo "Deleted " . $deleted_teachers . " unverified teacher(s) without any assessments.";

$conn->close();
