<?php
require_once "../includes/db.php";
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id    = $_POST['id'] ?? null;
    $label = $_POST['label'] ?? null;
    $type  = $_POST['type'] ?? null;

    if ($id && $label && $type) {
        // 🔹 Fetch existing record first
        $stmt = $conn->prepare("SELECT file_path, link_url FROM learning_materials WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result   = $stmt->get_result();
        $existing = $result->fetch_assoc();
        $stmt->close();

        $filePath = $existing['file_path'];
        $linkUrl  = $existing['link_url'];

        if ($type === "link") {
            // 🔹 Update with new link, clear file_path
            $linkUrl = $_POST['link'] ?? $linkUrl;
            $filePath = null;

            $stmt = $conn->prepare("UPDATE learning_materials 
                                    SET label=?, type=?, link_url=?, file_path=NULL 
                                    WHERE id=?");
            $stmt->bind_param("sssi", $label, $type, $linkUrl, $id);
        } else {
            if (!empty($_FILES['file']['name'])) {
                // 🔹 New file uploaded
                $uploadDir = "../uploads/";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileName   = basename($_FILES['file']['name']);
                $serverPath = $uploadDir . $fileName;
                $publicPath = "uploads/" . $fileName;

                move_uploaded_file($_FILES['file']['tmp_name'], $serverPath);

                $filePath = $publicPath;
                $linkUrl  = null;

                $stmt = $conn->prepare("UPDATE learning_materials 
                                        SET label=?, type=?, file_path=?, link_url=NULL 
                                        WHERE id=?");
                $stmt->bind_param("sssi", $label, $type, $filePath, $id);
            } else {
                // 🔹 No new file → keep old file_path
                $stmt = $conn->prepare("UPDATE learning_materials 
                                        SET label=?, type=?, file_path=?, link_url=? 
                                        WHERE id=?");
                $stmt->bind_param("ssssi", $label, $type, $filePath, $linkUrl, $id);
            }
        }

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Material updated successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid input data."]);
    }

    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method not allowed"]);
