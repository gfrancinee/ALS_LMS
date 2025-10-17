<?php
// includes/functions.php

// Function to create a notification
function create_notification($conn, $user_id, $message, $link)
{
    // We use a prepared statement to prevent SQL injection
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");

    // Check if the statement was prepared successfully
    if ($stmt === false) {
        // Handle error, e.g., log it or show a generic message
        // For debugging, you can use: error_log("Prepare failed: " . $conn->error);
        return false;
    }

    // Bind the parameters
    $stmt->bind_param("iss", $user_id, $message, $link);

    // Execute the statement and return true on success, false on failure
    $success = $stmt->execute();

    $stmt->close();

    return $success;
}


/**
 * Recommends a learning material for a given question based on keyword matching.
 *
 * @param mysqli $conn The database connection.
 * @param int $question_id The ID of the question the student got wrong.
 * @param int $strand_id The ID of the course/strand.
 * @return array|null The best matching material, or null if no good match is found.
 */
function recommendMaterialForQuestion($conn, $question_id, $strand_id)
{
    // 1. Get the text of the question the student got wrong.
    $stmt_q = $conn->prepare("SELECT question_text FROM assessment_questions WHERE id = ?");
    $stmt_q->bind_param("i", $question_id);
    $stmt_q->execute();
    $result_q = $stmt_q->get_result();
    $question_row = $result_q->fetch_assoc();
    $stmt_q->close();

    if (!$question_row) {
        return null; // Question not found
    }
    $question_text = $question_row['question_text'];

    // 2. Extract important keywords from the question text.
    // This removes common words and symbols to find the core concepts.
    $common_words = ['a', 'an', 'the', 'is', 'was', 'in', 'on', 'at', 'which', 'what', 'who', 'how', 'through', 'was', 'the', 'of', 'for', 'by', 'with'];
    $question_keywords = array_diff(
        str_word_count(strtolower(preg_replace('/[^a-z0-9\s]/i', '', $question_text)), 1),
        $common_words
    );

    if (empty($question_keywords)) {
        return null; // No useful keywords in the question
    }

    // 3. Get all materials uploaded by the teacher for this strand.
    $stmt_m = $conn->prepare("SELECT id, label, type, file_path, link_url FROM learning_materials WHERE strand_id = ?");
    $stmt_m->bind_param("i", $strand_id);
    $stmt_m->execute();
    $all_materials = $stmt_m->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_m->close();

    // 4. Find the best matching material.
    $best_match = null;
    $highest_score = 0;

    foreach ($all_materials as $material) {
        $score = 0;
        $material_label_lower = strtolower($material['label']);
        foreach ($question_keywords as $keyword) {
            // If a keyword from the question is found in the material's title, increase the score.
            // We check for length > 2 to avoid matching small, common words like 'it' or 'do'.
            if (strlen($keyword) > 2 && strpos($material_label_lower, $keyword) !== false) {
                $score++;
            }
        }

        // If this material has more matching keywords, it becomes the new best match.
        if ($score > $highest_score) {
            $highest_score = $score;
            $best_match = $material;
        }
    }

    // Only return a recommendation if we found at least one matching keyword.
    return ($highest_score > 0) ? $best_match : null;
}
