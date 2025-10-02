<?php
require_once "../includes/db.php";
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // FIX 1: Changed variable names to match your form ('materialLabel', 'materialType')
    $strandId = $_POST['strand_id'] ?? null;
    $label    = $_POST['materialLabel'] ?? null;
    $type     = $_POST['materialType'] ?? null;

    // NOTE: Your form doesn't send a 'teacher_id', so I have removed it for now to prevent errors.

    if ($strandId && $label && $type) {
        $filePath = null;
        $linkUrl  = null;

        if ($type === "link") {
            // This part for links seems okay
            $linkUrl = $_POST['materialLink'] ?? null;
            if (!$linkUrl) {
                echo json_encode(["status" => "error", "message" => "Link URL is required."]);
                exit;
            }
            $stmt = $conn->prepare("INSERT INTO learning_materials (strand_id, label, type, link_url) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $strandId, $label, $type, $linkUrl);
        } else {
            // FIX 2: Changed $_FILES['file'] to $_FILES['materialFile'] to match your form
            if (!empty($_FILES['materialFile']['name'])) {
                // Check for upload errors
                if ($_FILES['materialFile']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(["status" => "error", "message" => "File upload error code: " . $_FILES['materialFile']['error']]);
                    exit;
                }

                $uploadDir = "../uploads/";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileName   = uniqid() . "_" . basename($_FILES['materialFile']['name']);
                $serverPath = $uploadDir . $fileName;
                $publicPath = "uploads/" . $fileName;

                if (move_uploaded_file($_FILES['materialFile']['tmp_name'], $serverPath)) {
                    $filePath = $publicPath;
                } else {
                    echo json_encode(["status" => "error", "message" => "Failed to move uploaded file."]);
                    exit;
                }

                $stmt = $conn->prepare("INSERT INTO learning_materials (strand_id, label, type, file_path) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $strandId, $label, $type, $filePath);
            } else {
                echo json_encode(["status" => "error", "message" => "No file was uploaded."]);
                exit;
            }
        }

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Material uploaded successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        // This is the error you were getting
        echo json_encode(["status" => "error", "message" => "Invalid input data. Required fields are missing."]);
    }

    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method not allowed"]);
