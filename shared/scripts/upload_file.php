<?php
/**
 * File Upload Handler
 * Manages file uploads with validation and directory organization
 */

//include('config.php');

/**
 * Generates a unique UUID for file naming
 * @return string
 */
function generate_uuid() {
    return str_replace('-', '', uniqid('', true));
}

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if file and request_type are provided
    if (isset($_FILES['file']) && isset($_POST['request_type'])) {
        $file = $_FILES['file'];
        $request_type = $_POST['request_type'];

        // Determine upload directory based on request type
        switch ($request_type) {
            case 'documents':
                $target_dir = "/var/www/html/framework/portal/data/framework/docs/images/";
                $url_base = "/framework/portal/data/framework/docs/images/";
                break;
            default:
                $target_dir = "/var/www/html/framework/portal/uploads/others/";
                $url_base = "/framework/portal/uploads/others/";
                break;
        }

        // Create upload directory if it doesn't exist
        if (!is_dir($target_dir)) {
            if (!mkdir($target_dir, 0777, true)) {
                echo json_encode(['error' => 'Failed to create upload directory.']);
                exit;
            }
        }

        // Process file name and extension
        $file_name = basename($file['name']);
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_file_name = generate_uuid() . '.' . $file_extension;
        $target_file = $target_dir . $new_file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Validate file type
        $valid_extensions = array("jpg", "jpeg", "png", "gif");
        
        if (in_array($imageFileType, $valid_extensions)) {
            // Attempt to move uploaded file
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                // Return success response with file URL
                $image_url = $url_base . $new_file_name;
                echo json_encode([
                    'success' => true,
                    'url' => $image_url
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to move uploaded file.',
                    'details' => error_get_last()
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid file type. Allowed types: ' . implode(', ', $valid_extensions)
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No file uploaded or request type missing.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method. Use POST.'
    ]);
}
?>