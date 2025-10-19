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
 * @param mysqli $conn The database connection.
 * @param int $question_id The ID of the question (from question_bank) the student got wrong.
 * @param int $strand_id The ID of the strand to limit the search.
 * @return array|null The best matching material, or null if no good match is found.
 */
function recommendMaterialForQuestion($conn, $question_id, $strand_id)
{
    // 1. Get the text of the question.
    // *** FIX: Fetches from 'question_bank', not 'assessment_questions' ***
    $stmt_q = $conn->prepare("SELECT question_text FROM question_bank WHERE id = ?");
    if (!$stmt_q) {
        error_log("Prepare failed (recommend/q): " . $conn->error);
        return null;
    }
    $stmt_q->bind_param("i", $question_id);
    $stmt_q->execute();
    $result_q = $stmt_q->get_result();
    $question_row = $result_q->fetch_assoc();
    $stmt_q->close();

    if (!$question_row) {
        return null; // Question not found
    }

    // 2. Extract important keywords from the question text.
    $question_text = strtolower($question_row['question_text']);
    $words = preg_split('/[\s,.;:!?]+/', $question_text);
    $stopwords = ['a', 'an', 'and', 'the', 'is', 'in', 'on', 'at', 'which', 'what', 'who', 'how', 'when', 'where', 'to', 'for', 'of', 'it', 'from', 'was', 'by', 'with', 'are', 'or'];
    $keywords = [];

    foreach ($words as $word) {
        $word = preg_replace("/[^a-z0-9]/", "", $word);
        if (strlen($word) > 3 && !in_array($word, $stopwords)) {
            $keywords[] = $word;
        }
    }

    if (empty($keywords)) {
        return null; // No useful keywords
    }

    // 3. Search materials in the same strand for these keywords.
    $sql_search = "SELECT id, label, type, description 
                   FROM learning_materials 
                   WHERE strand_id = ? AND (";

    $search_terms = [];
    $bind_types = 'i';
    $bind_params = [$strand_id];

    foreach ($keywords as $keyword) {
        $search_terms[] = "LOWER(label) LIKE ?";
        $search_terms[] = "LOWER(description) LIKE ?";

        $bind_types .= 'ss';
        $bind_params[] = '%' . $keyword . '%';
        $bind_params[] = '%' . $keyword . '%';
    }

    if (empty($search_terms)) {
        return null; // Should not happen if keywords were found, but good to check.
    }

    $sql_search .= implode(' OR ', $search_terms) . ") LIMIT 1";

    $stmt_m = $conn->prepare($sql_search);
    if (!$stmt_m) {
        error_log("Prepare failed (recommend/m): " . $conn->error);
        return null;
    }

    $stmt_m->bind_param($bind_types, ...$bind_params);

    $stmt_m->execute();
    $result_m = $stmt_m->get_result();
    $material = $result_m->fetch_assoc();
    $stmt_m->close();

    // 4. Return the found material or null
    return $material;
}
