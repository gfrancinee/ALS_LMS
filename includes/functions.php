<?php
require_once __DIR__ . '/db.php';

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
    // --- 1. Get the full question text ---
    $stmt = $conn->prepare("SELECT question_text FROM question_bank WHERE id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return null;
    }

    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }

    $question = $result->fetch_assoc();
    $question_text = strtolower($question['question_text']);
    $stmt->close();

    // --- 2. Get all materials for this strand with their full content ---
    $stmt = $conn->prepare("
        SELECT id, label, type, file_path, link_url, content_text 
        FROM learning_materials 
        WHERE strand_id = ?
    ");

    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return null;
    }

    $stmt->bind_param("i", $strand_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $materials = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($materials)) {
        return null;
    }

    // --- 3. Calculate semantic similarity for each material ---
    $best_match = null;
    $highest_score = 0;

    foreach ($materials as $material) {
        $material_content = strtolower($material['content_text'] ?? '');
        $material_label = strtolower($material['label'] ?? '');

        if (empty($material_content)) {
            continue;
        }

        // Calculate similarity score using semantic analysis
        $score = calculateSemanticSimilarity($question_text, $material_content, $material_label);

        if ($score > $highest_score) {
            $highest_score = $score;
            $best_match = $material;
        }
    }

    // Only return a match if the score is above threshold (20%)
    if ($highest_score > 0.20) {
        return $best_match;
    }

    return null;
}

/**
 * Calculate semantic similarity between question and material using multiple methods
 *
 * @param string $question The full question text (lowercase)
 * @param string $material The full material content text (lowercase)
 * @param string $material_label The material title/label (lowercase)
 * @return float Similarity score between 0 and 1
 */
function calculateSemanticSimilarity($question, $material, $material_label)
{
    // Method 1: N-gram phrase matching (checks for consecutive word sequences)
    $phrase_score = calculatePhraseMatching($question, $material);

    // Method 2: Sentence-level matching
    $sentence_score = calculateSentenceMatching($question, $material);

    // Method 3: Distinctive keyword matching (proper nouns, technical terms)
    $keyword_score = calculateKeywordMatching($question, $material);

    // Method 4: Title relevance boost (if question mentions words from the title)
    $title_score = calculateTitleRelevance($question, $material_label);

    // Combine scores with weights
    // Phrase matching is most important, followed by sentences, then keywords and title
    $final_score = ($phrase_score * 0.40) + ($sentence_score * 0.30) + ($keyword_score * 0.20) + ($title_score * 0.10);

    return $final_score;
}

/**
 * Calculate phrase matching score using n-grams (sequences of consecutive words)
 */
function calculatePhraseMatching($question, $material)
{
    // Remove common stop words
    $stopwords = ['this', 'that', 'these', 'those', 'what', 'which', 'who', 'when', 'where', 'why', 'how', 'does', 'about', 'with', 'from', 'have', 'been', 'their', 'there', 'would', 'could', 'should', 'what\'s', 'how\'s'];

    $question_words = preg_split('/\s+/', $question);
    $question_words = array_filter($question_words, function ($word) use ($stopwords) {
        return strlen($word) > 2 && !in_array($word, $stopwords);
    });
    $question_words = array_values($question_words);

    if (empty($question_words)) {
        return 0;
    }

    $phrase_matches = 0;
    $total_weight = 0;

    // Try matching 5-word, 4-word, 3-word, and 2-word phrases
    for ($n = 5; $n >= 2; $n--) {
        if (count($question_words) < $n) continue;

        for ($i = 0; $i <= count($question_words) - $n; $i++) {
            $phrase = implode(' ', array_slice($question_words, $i, $n));
            $weight = $n; // Longer phrases get more weight
            $total_weight += $weight;

            if (strpos($material, $phrase) !== false) {
                $phrase_matches += $weight;
            }
        }
    }

    return $total_weight > 0 ? ($phrase_matches / $total_weight) : 0;
}

/**
 * Calculate sentence-level matching
 */
function calculateSentenceMatching($question, $material)
{
    // Split question into sentences
    $sentences = preg_split('/[.!?]+/', $question, -1, PREG_SPLIT_NO_EMPTY);
    $sentences = array_map('trim', $sentences);

    if (empty($sentences)) {
        return 0;
    }

    $sentence_matches = 0;

    foreach ($sentences as $sentence) {
        if (strlen($sentence) < 15) continue; // Skip very short fragments

        // Split sentence into words
        $words = preg_split('/\s+/', $sentence);
        $words = array_filter($words, function ($w) {
            return strlen($w) > 2;
        });

        if (empty($words)) continue;

        // Check if at least 60% of the sentence words appear in material
        $word_matches = 0;
        foreach ($words as $word) {
            if (strpos($material, $word) !== false) {
                $word_matches++;
            }
        }

        $sentence_match_rate = $word_matches / count($words);
        if ($sentence_match_rate >= 0.6) {
            $sentence_matches++;
        }
    }

    return count($sentences) > 0 ? ($sentence_matches / count($sentences)) : 0;
}

/**
 * Calculate keyword matching for distinctive words
 */
function calculateKeywordMatching($question, $material)
{
    // Extract distinctive keywords:
    // 1. Words that are capitalized (proper nouns)
    // 2. Long words (6+ characters)
    // 3. Technical terms

    preg_match_all('/\b[A-Z][a-z]+\b/', $question, $proper_nouns);
    preg_match_all('/\b\w{6,}\b/', $question, $long_words);

    $distinctive_words = array_merge($proper_nouns[0], $long_words[0]);
    $distinctive_words = array_unique(array_map('strtolower', $distinctive_words));

    if (empty($distinctive_words)) {
        return 0;
    }

    $keyword_matches = 0;
    foreach ($distinctive_words as $keyword) {
        if (strpos($material, $keyword) !== false) {
            $keyword_matches++;
        }
    }

    return $keyword_matches / count($distinctive_words);
}

/**
 * Calculate title relevance (boost if question mentions words from material title)
 */
function calculateTitleRelevance($question, $material_label)
{
    if (empty($material_label)) {
        return 0;
    }

    // Extract significant words from title (ignore "Module X:" pattern and common words)
    $title_words = preg_replace('/module\s+\d+:\s*/i', '', $material_label);
    $title_words = preg_split('/\s+/', $title_words);
    $title_words = array_filter($title_words, function ($word) {
        return strlen($word) > 3;
    });

    if (empty($title_words)) {
        return 0;
    }

    $title_matches = 0;
    foreach ($title_words as $word) {
        if (strpos($question, $word) !== false) {
            $title_matches++;
        }
    }

    return count($title_words) > 0 ? ($title_matches / count($title_words)) : 0;
}

/**
 * Creates or updates a notification in the database.
 * This function automatically handles aggregation based on your table structure.
 *
 * @param mysqli $conn The database connection.
 * @param int $user_id The ID of the user to notify (recipient).
 * @param string $type A short code for the action (e.g., 'submit_assessment').
 * @param int $related_id The ID of the item (quiz, module, strand).
 * @param string $message The text to display (e.g., "New submissions for...").
 * @param string $link The URL the user should go to when clicking.
 */
function create_notification($conn, $user_id, $type, $related_id, $message, $link)
{

    // First, check if an unread notification of this exact type already exists
    $stmt_check = $conn->prepare(
        "SELECT id FROM notifications 
         WHERE user_id = ? AND type = ? AND related_id = ? AND is_read = 0"
    );

    if (!$stmt_check) {
        error_log("Notification check prepare failed: " . $conn->error);
        return;
    }

    $stmt_check->bind_param("isi", $user_id, $type, $related_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows > 0) {
        // --- IT EXISTS! UPDATE IT ---
        // We found an existing unread notification. Increment the count.
        // The 'updated_at' column will automatically update and bump it to the top.
        $existing_row = $result->fetch_assoc();
        $existing_id = $existing_row['id'];

        $stmt_update = $conn->prepare(
            "UPDATE notifications SET count = count + 1, message = ? WHERE id = ?"
        );

        if (!$stmt_update) {
            error_log("Notification update prepare failed: " . $conn->error);
        } else {
            // We update the message in case it needs to be plural
            // (e.g., "1 submission" vs "2 submissions")
            $stmt_update->bind_param("si", $message, $existing_id);
            $stmt_update->execute();
            $stmt_update->close();
        }
    } else {
        // --- IT'S NEW! INSERT IT ---
        // No existing notification found. Create a new one with count = 1.
        $stmt_insert = $conn->prepare(
            "INSERT INTO notifications (user_id, type, related_id, count, message, link, is_read) 
             VALUES (?, ?, ?, 1, ?, ?, 0)"
        );

        if (!$stmt_insert) {
            error_log("Notification insert prepare failed: " . $conn->error);
        } else {
            $stmt_insert->bind_param("isiss", $user_id, $type, $related_id, $message, $link);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
    }

    $stmt_check->close();
}

// You may have other functions here. Add this new function at the end of the file.

/**
 * Checks if a student is enrolled in a specific learning strand.
 *
 * @param mysqli $conn The database connection.
 * @param int $student_id The ID of the student.
 * @param int $strand_id The ID of the learning strand.
 * @return bool True if enrolled, false otherwise.
 */
function isStudentEnrolled($conn, $student_id, $strand_id)
{
    // Check in strand_participants table
    $stmt = $conn->prepare(
        "SELECT 1 FROM strand_participants WHERE student_id = ? AND strand_id = ? LIMIT 1"
    );
    if (!$stmt) {
        // Handle query preparation error
        error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        return false;
    }

    $stmt->bind_param("ii", $student_id, $strand_id);

    if (!$stmt->execute()) {
        // Handle query execution error
        error_log("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }

    $stmt->store_result();
    $is_enrolled = $stmt->num_rows > 0;

    $stmt->close();

    return $is_enrolled;
}

// If your file has a closing PHP tag, make sure this function is above it.
