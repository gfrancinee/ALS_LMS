<?php
// Make sure this is at the top of functions.php if it's not already
// Note: db.php is often included in the file that *calls* this function,
// but including it here makes it self-contained if needed.
// If you get a 'cannot redeclare' error, you can remove the line below.
require_once 'db.php';

/**
 * Main recommendation function.
 * Finds the best material to recommend for a question the student got wrong.
 *
 * @param mysqli $conn The database connection
 * @param int $question_id The ID of the question the student got wrong
 * @param int $strand_id The ID of the current strand
 * @return array|null The recommended material, or null if no good match
 */
function recommendMaterialForQuestion($conn, $question_id, $strand_id)
{

    // --- 1. Get the content of the wrong question ---
    $question_text = '';
    $stmt_q = $conn->prepare("SELECT question_text FROM question_bank WHERE id = ?");
    if ($stmt_q) {
        $stmt_q->bind_param("i", $question_id);
        $stmt_q->execute();
        $res_q = $stmt_q->get_result();
        if ($res_q->num_rows > 0) {
            $question_text = $res_q->fetch_assoc()['question_text'];
        }
        $stmt_q->close();
    }

    if (empty($question_text)) {
        return null; // Can't recommend if we don't have the question text
    }

    // --- 2. Get all materials for this strand ---
    // We fetch all materials to compare them.
    $materials = [];

    // *** FIX: Use 'learning_materials' table AND 'link_url' column ***
    $sql_m = "
        SELECT id, label, type, file_path, link_url, content_text 
        FROM learning_materials 
        WHERE strand_id = ?
    ";
    // *** END FIX ***

    $stmt_m = $conn->prepare($sql_m);
    if ($stmt_m === false) {
        error_log("Prepare failed (get materials): " . $conn->error);
        return null;
    }

    $stmt_m->bind_param("i", $strand_id);
    $stmt_m->execute();
    $res_m = $stmt_m->get_result();

    while ($row = $res_m->fetch_assoc()) {
        $materials[] = $row;
    }
    $stmt_m->close();

    if (empty($materials)) {
        return null; // No materials to recommend
    }

    // --- 3. Calculate similarity score for each material ---
    $best_material = null;
    $highest_score = -1.0; // Start with a score of -1

    // Get the "important" words from the question
    $question_words = cleanAndTokenizeText($question_text);

    foreach ($materials as $material) {
        // Combine all text content for the material
        // We add 'label' (title) because it's very important
        $material_full_text = $material['label'] . ' ' . $material['content_text'];

        // Get "important" words from the material
        $material_words = cleanAndTokenizeText($material_full_text);

        // Calculate how similar this material is to the question
        $score = calculateJaccardSimilarity($question_words, $material_words);

        // --- Debugging (Optional: Remove later) ---
        // error_log("Material '{$material['label']}' Score: $score");
        // ---

        if ($score > $highest_score) {
            $highest_score = $score;
            $best_material = $material;
        }
    }

    // --- 4. Return the best match (if it's good enough) ---
    // We set a threshold: only recommend if the score is > 0.
    // This stops it from recommending a random material if no words match.
    if ($highest_score > 0.0) {
        return $best_material;
    }

    return null; // No good recommendation found
}

/**
 * Helper function to clean text and break it into unique "important" words.
 *
 * @param string $text The text to clean (question or material)
 * @return array A list of unique, important words
 */
function cleanAndTokenizeText($text)
{
    // 1. Common English "stop words" to ignore.
    // *** FIX: Removed course-specific words like 'media', 'literacy', etc. ***
    $stopWords = [
        'a',
        'an',
        'and',
        'are',
        'as',
        'at',
        'be',
        'by',
        'for',
        'from',
        'has',
        'he',
        'in',
        'is',
        'it',
        'its',
        'of',
        'on',
        'that',
        'the',
        'to',
        'was',
        'were',
        'will',
        'with',
        'what',
        'which',
        'who',
        'how',
        'when',
        'why'
    ];
    // *** END FIX ***

    // 1. Convert to lowercase
    $text = strtolower($text);
    // 2. Remove punctuation (keeps letters, numbers, and spaces)
    $text = preg_replace('/[^\w\s]/', '', $text);
    // 3. Break into words
    $words = explode(' ', $text);
    // 4. Remove stop words and empty strings
    $words = array_filter($words, function ($word) use ($stopWords) {
        return !empty($word) && !in_array($word, $stopWords);
    });
    // 5. Return a list of unique words
    return array_unique($words);
}

/**
 * Calculates the Jaccard Similarity (Intersection over Union) between two sets of words.
 * A score of 1.0 means they are identical.
 * A score of 0.0 means they have zero words in common.
 *
 * @param array $set1 Words from the question
 * @param array $set2 Words from the material
 * @return float The similarity score
 */
function calculateJaccardSimilarity(array $set1, array $set2)
{
    if (empty($set1) || empty($set2)) {
        return 0.0;
    }

    // Find words that are in BOTH lists
    $intersection = count(array_intersect($set1, $set2));

    // Find all unique words from BOTH lists combined
    $union = count(array_unique(array_merge($set1, $set2)));

    if ($union == 0) {
        return 0.0;
    }

    // The score is (Number of common words) / (Total number of unique words)
    return $intersection / $union;
}

/**
 * Creates a new notification for a user.
 *
 * @param mysqli $conn The database connection
 * @param int $user_id The ID of the user to notify
 * @param string $message The notification message
 * @param string $link The relative link for the notification
 * @return bool True on success, false on failure
 */
function create_notification($conn, $user_id, $message, $link)
{
    // We set 'is_read' to 0 (unread) by default
    $sql = "INSERT INTO notifications (user_id, message, link, is_read) VALUES (?, ?, ?, 0)";

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        // Log the error for debugging
        error_log("Notification prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("iss", $user_id, $message, $link);

    if ($stmt->execute()) {
        $stmt->close();
        return true; // Success
    } else {
        // Log the error for debugging
        error_log("Notification execute failed: " . $stmt->error);
        $stmt->close();
        return false; // Failure
    }
}
