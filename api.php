<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, ");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$servername = "localhost";
$username = "root";
$password = "";
$database = "filemanager";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed"]));
}

$action = $_GET['action'] ?? '';

function deleteFolder($folderPath) {
    if (is_dir($folderPath)) {
        $files = scandir($folderPath);
        foreach ($files as $file) {
            if ($file !== "." && $file !== "..") {
                $filePath = $folderPath . "/" . $file;
                is_dir($filePath) ? deleteFolder($filePath) : unlink($filePath); // Recursively delete
            }
        }
        rmdir($folderPath); // Delete the main folder after its contents are cleared
    }
}

switch ($action) {
    case 'login':
        // Login API
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $username = $_POST['username'];
            $password = $_POST['password'];

            // Check if the user exists
            $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE username = ? AND password = ?");
            $stmt->bind_param("ss", $username, $password);
            $stmt->execute();

            // Bind result variables
            $stmt->bind_result($userId, $name, $hashedPassword);
            $stmt->fetch();

            // Check if the user exists and return the response
            if ($userId) {
                echo json_encode([
                    "status" => "success",
                    "message" => "Login successful",
                    "user_id" => $userId,
                    "name" => $name // Include the user's name in the response
                ]);
            } else {
                echo json_encode([
                    "status" => "error",
                    "message" => "Invalid username or password"
                ]);
            }

            $stmt->close();
        }
        break;



        // case 'upload_folder':
        //     if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id'], $_POST['folder_name'])) {
        //         $userId = $_POST['user_id'];
        //         $folderName = $_POST['folder_name'];
        //         $folderPath = "uploads/$userId/$folderName"; // Define folder path
        
        //         // Step 1: Insert the folder into `folder_uploads` table with path
        //         $stmt = $conn->prepare("INSERT INTO folder_uploads (user_id, folder_name, folder_path) VALUES (?, ?, ?)");
        //         $stmt->bind_param("iss", $userId, $folderName, $folderPath);
        //         if ($stmt->execute()) {
        //             $folderId = $stmt->insert_id;
        
        //             // Step 2: Prepare to store files in the `files_upload` table
        //             $fileStmt = $conn->prepare("INSERT INTO upload_files (folder_id, user_id, file_name, file_path) VALUES (?, ?, ?, ?)");
        //             foreach ($_FILES['files']['name'] as $index => $name) {
        //                 $tempPath = $_FILES['files']['tmp_name'][$index];
        //                 $targetPath = "$folderPath/" . basename($name);
        
        //                 // Create the directory if it doesn't exist
        //                 if (!file_exists(dirname($targetPath))) {
        //                     mkdir(dirname($targetPath), 0777, true);
        //                 }
        
        //                 // Move the file to the target path
        //                 if (move_uploaded_file($tempPath, $targetPath)) {
        //                     $fileStmt->bind_param("iiss", $folderId, $userId, $name, $targetPath);
        //                     $fileStmt->execute();
        //                 }
        //             }
        
        //             echo json_encode([
        //                 "status" => "success",
        //                 "message" => "Folder and files uploaded successfully",
        //                 "folder_id" => $folderId,
        //                 "folder_name" => $folderName,
        //                 "folder_path" => $folderPath
        //             ]);
        //         } else {
        //             echo json_encode(["status" => "error", "message" => "Failed to create folder"]);
        //         }
        //     } else {
        //         echo json_encode(["status" => "error", "message" => "Invalid request"]);
        //     }
        //     break;
        


    case 'get_folders':
        // Fetch folders based on user ID
        if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['user_id'])) {
            $userId = $_GET['user_id'];
            $stmt = $conn->prepare("SELECT id, folder_name, folder_path FROM folder_uploads WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $folders = [];
                while ($row = $result->fetch_assoc()) {
                    $folders[] = $row;
                }
                echo json_encode(["status" => "success", "folders" => $folders]);
            } else {
                echo json_encode(["status" => "error", "message" => "No folders found"]);
            }

            $stmt->close();
        }
        break;



    case 'create_folder':
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['folder_name']) && isset($_POST['user_id'])) {
        $folderName = $_POST['folder_name'];
        $userId = $_POST['user_id'];
        $folderPath = "uploads/$userId/$folderName";

        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0777, true);
            $stmt = $conn->prepare("INSERT INTO folder_uploads (user_id, folder_name, folder_path) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $userId, $folderName, $folderPath);

            if ($stmt->execute()) {
                $folderId = $conn->insert_id;
                echo json_encode([
                    "status" => "success",
                    "folder_id" => $folderId,
                    "folder_name" => $folderName,
                    "folder_path" => $folderPath
                ]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to save folder in the database"]);
            }

            $stmt->close();
        } else {
            echo json_encode(["status" => "error", "message" => "Folder already exists"]);
        }
    }
    break;



        case 'upload_file':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['folder_id']) && isset($_FILES['file']) && isset($_POST['user_id'])) {
                $folderId = $_POST['folder_id'];
                $userId = $_POST['user_id'];
                $file = $_FILES['file'];
                
                // Retrieve folder path
                $stmt = $conn->prepare("SELECT folder_path FROM folder_uploads WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $folderId, $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $folder = $result->fetch_assoc();
                    $folderPath = $folder['folder_path'];
                    
                    $filePath = $folderPath . '/' . basename($file['name']);
                    if (move_uploaded_file($file['tmp_name'], $filePath)) {
                        $stmt = $conn->prepare("INSERT INTO upload_files (folder_id, user_id, file_name, file_path) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("iiss", $folderId, $userId, $file['name'], $filePath);
                        $stmt->execute();
                        
                        echo json_encode(["status" => "success", "message" => "File uploaded successfully"]);
                    } else {
                        echo json_encode(["status" => "error", "message" => "File upload failed"]);
                    }
                } else {
                    echo json_encode(["status" => "error", "message" => "Folder not found"]);
                }
            }
            break;
            

            
            case 'get_files':
                if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['folder_id']) && isset($_GET['user_id'])) {
                    $folderId = $_GET['folder_id'];
                    $userId = $_GET['user_id'];
                    
                    $stmt = $conn->prepare("SELECT id, file_name, file_path FROM upload_files WHERE folder_id = ? AND user_id = ?");
                    $stmt->bind_param("ii", $folderId, $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    $files = [];
                    while ($row = $result->fetch_assoc()) {
                        $files[] = $row;
                    }
                    
                    echo json_encode(["status" => "success", "files" => $files]);
                }
                break;
            

    


    case 'delete_folder':
        // Delete folder API
        if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['folder_id']) && isset($_GET['user_id'])) {
            $folderId = $_GET['folder_id'];
            $userId = $_GET['user_id'];
    
            $stmt = $conn->prepare("SELECT folder_path FROM folder_uploads WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $folderId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
    
            if ($result->num_rows > 0) {
                $folder = $result->fetch_assoc();
                $folderPath = $folder['folder_path'];
    
                if (is_dir($folderPath)) {
                    deleteFolder($folderPath);
                    $stmt = $conn->prepare("DELETE FROM folder_uploads WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("ii", $folderId, $userId);
    
                    if ($stmt->execute()) {
                        // After successful deletion, retrieve the updated folder list
                        $folders = [];
                        $folderQuery = $conn->prepare("SELECT * FROM folder_uploads WHERE user_id = ?");
                        $folderQuery->bind_param("i", $userId);
                        $folderQuery->execute();
                        $foldersResult = $folderQuery->get_result();
    
                        while ($row = $foldersResult->fetch_assoc()) {
                            $folders[] = $row;
                        }
                        echo json_encode(["status" => "success", "message" => "Folder deleted successfully", "folders" => $folders]);
                    } else {
                        echo json_encode(["status" => "error", "message" => "Failed to delete folder record from database"]);
                    }
                } else {
                    echo json_encode(["status" => "error", "message" => "Folder does not exist"]);
                }
            } else {
                echo json_encode(["status" => "error", "message" => "No folder found for the given ID"]);
            }
    
            $stmt->close();
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid request"]);
        }
        break;
    

        case 'delete_file':
            // Delete file API
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['file_id']) && isset($_GET['user_id'])) {
                $fileId = $_GET['file_id'];
                $userId = $_GET['user_id'];
    
                // Fetch the file path from the database
                $stmt = $conn->prepare("SELECT file_path FROM upload_files WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $fileId, $userId);
                $stmt->execute();
                $result = $stmt->get_result();
    
                if ($result->num_rows > 0) {
                    $fileData = $result->fetch_assoc();
                    $filePath = $fileData['file_path'];
    
                    // Delete the file from the server
                    if (file_exists($filePath)) {
                        unlink($filePath); // Delete file from server
                    }
    
                    // Delete the file record from the database
                    $deleteQuery = "DELETE FROM upload_files WHERE id = ? AND user_id = ?";
                    $deleteStmt = $conn->prepare($deleteQuery);
                    $deleteStmt->bind_param("ii", $fileId, $userId);
                    $deleteStmt->execute();
    
                    if ($deleteStmt->affected_rows > 0) {
                        echo json_encode(['status' => 'success', 'message' => 'File deleted successfully']);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'Failed to delete file record from the database']);
                    }
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'File not found']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
            }
            break;


        case 'download_folder':
            // Check if folder_id is set and proceed to download folder as zip
            if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['folder_id']) && isset($_GET['user_id'])) {
                $folderId = $_GET['folder_id'];
                $userId = $_GET['user_id'];
                
                // Get folder path from database
                $stmt = $conn->prepare("SELECT folder_path, folder_name FROM folder_uploads WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $folderId, $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $folder = $result->fetch_assoc();
                    $folderPath = $folder['folder_path'];
                    $folderName = $folder['folder_name'];
                    
                    // Define the zip file path
                    $zipFile = $folderName . ".zip";
                    $zipPath = sys_get_temp_dir() . "/" . $zipFile;
                    
                    // Create zip archive
                    $zip = new ZipArchive();
                    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folderPath), RecursiveIteratorIterator::LEAVES_ONLY);
                        
                        foreach ($files as $file) {
                            if (!$file->isDir()) {
                                $filePath = $file->getRealPath();
                                $relativePath = substr($filePath, strlen($folderPath) + 1);
                                $zip->addFile($filePath, $relativePath);
                            }
                        }
                        $zip->close();
                        
                        // Set headers for download
                        header('Content-Type: application/zip');
                        header('Content-Disposition: attachment; filename="' . $zipFile . '"');
                        header('Content-Length: ' . filesize($zipPath));
                        readfile($zipPath);
        
                        // Delete temporary zip file
                        unlink($zipPath);
                    } else {
                        echo json_encode(["status" => "error", "message" => "Failed to create zip file"]);
                    }
                } else {
                    echo json_encode(["status" => "error", "message" => "No folder found for the given ID"]);
                }
        
                $stmt->close();
            } else {
                echo json_encode(["status" => "error", "message" => "Invalid request"]);
            }
            break;
        
//files functionality


case 'get_files_sep':
    // Fetch folders based on user ID
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['user_id'])) {
        $userId = $_GET['user_id'];
        $stmt = $conn->prepare("SELECT id, file_name, file_path FROM upload_file_sep WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $files = [];
            while ($row = $result->fetch_assoc()) {
                $files[] = $row;
            }
            echo json_encode(["status" => "success", "files" => $files]);
        } else {
            echo json_encode(["status" => "error", "message" => "No folders found"]);
        }

        $stmt->close();
    }
    break;

    case 'upload_file_sep':
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file']) && isset($_POST['user_id'])) {
            $fileName = $_FILES['file']['name'];
            $fileTmpName = $_FILES['file']['tmp_name'];
            $userId = $_POST['user_id'];
            $filePath = "uploads/$userId/$fileName";

            if (move_uploaded_file($fileTmpName, $filePath)) {
                // Save file path in the database
                $stmt = $conn->prepare("INSERT INTO upload_file_sep (user_id, file_name, file_path) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $userId, $fileName, $filePath);
                
                if ($stmt->execute()) {
                    $fileId = $conn->insert_id;
                    echo json_encode(["status" => "success", "message" => "File uploaded successfully", "file_path" => $filePath,"fileId" => $fileId]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Failed to save file path in the database"]);
                }
                
                $stmt->close();
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to upload file"]);
            }
        }
        break;
        

case 'delete_file_sep':
    // Delete folder API
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['file_id']) && isset($_GET['user_id'])) {
        $fileId = $_GET['file_id'];
        $userId = $_GET['user_id'];

        // Fetch the file path from the database
        $stmt = $conn->prepare("SELECT file_path FROM upload_file_sep WHERE file_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $fileId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $fileData = $result->fetch_assoc();
            $filePath = $fileData['file_path'];
            // Delete the file from the server
            if (file_exists($filePath)) {
                unlink($filePath); // Delete file from server
            }

            // Delete the file record from the database
            $deleteQuery = "DELETE FROM upload_file_sep WHERE file_id = ? AND user_id = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->bind_param("ii", $fileId, $userId);
            $deleteStmt->execute();

            if ($deleteStmt->affected_rows > 0) {
                echo json_encode(['status' => 'success', 'message' => 'File deleted successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to delete file record from the database']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'File not found']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    }
    break;







    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
}

$conn->close();

?>