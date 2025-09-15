<?php
$message = "";
$target_dir = "uploads/";
$comments_file = $target_dir . "comments.json";


$files_info = [];
if (file_exists($comments_file)) {
    $files_info = json_decode(file_get_contents($comments_file), true);
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["fileToUpload"])) {
    $originalName = basename($_FILES["fileToUpload"]["name"]);
    $fileType = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $comment = htmlspecialchars($_POST['fileComment'] ?? '');
    $password = $_POST['filePassword'] ?? '';
    $target_file = $target_dir . $originalName;


    $blockedTypes = ["php", "phtml", "php3", "php4", "php5", "phar"];
    if (in_array($fileType, $blockedTypes)) {
        $message .= "❌ Sorry, PHP files are not allowed.<br>";
    } else {
 
        if ($_FILES["fileToUpload"]["size"] > 104857600) {
            $message .= "❌ Sorry, your file is too large (max 100MB).<br>";
        } else {

            if (file_exists($target_file)) {
                unlink($target_file);
            }

            if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
                $message .= "✅ The file <b>$originalName</b> has been uploaded successfully.<br>";


                $files_info[$originalName] = [
                    "comment" => $comment,
                    "password" => $password ? password_hash($password, PASSWORD_DEFAULT) : "",
                    "upload_time" => time()
                ];
                file_put_contents($comments_file, json_encode($files_info, JSON_PRETTY_PRINT));
            } else {
                $message .= "❌ Sorry, there was an error uploading your file.<br>";
            }
        }
    }
}


if (isset($_GET["download"])) {
    $fileToDownload = basename($_GET["download"]);
    if (isset($files_info[$fileToDownload])) {
        $filePasswordHash = $files_info[$fileToDownload]['password'] ?? '';
        $filePath = $target_dir . $fileToDownload;

        if (!file_exists($filePath)) {
            $message .= "❌ File not found.<br>";
        } elseif ($filePasswordHash) {

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accessPassword'])) {
                if (password_verify($_POST['accessPassword'], $filePasswordHash)) {
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="'.basename($filePath).'"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($filePath));
                    readfile($filePath);
                    exit;
                } else {
                    $message .= "❌ Incorrect password for download.<br>";
                }
            } else {
                echo '<form method="POST">
                        <label>Enter password to download <b>'.$fileToDownload.'</b>:</label>
                        <input type="password" name="accessPassword" required>
                        <input type="submit" value="Download">
                      </form>';
                exit;
            }
        } else {

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($filePath).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
    } else {
        $message .= "❌ File info not found.<br>";
    }
}


if (isset($_GET["delete"])) {
    $fileToDelete = basename($_GET["delete"]);
    if (isset($files_info[$fileToDelete])) {
        $filePasswordHash = $files_info[$fileToDelete]['password'] ?? '';
        $filePath = $target_dir . $fileToDelete;

        if (!file_exists($filePath)) {
            $message .= "❌ File not found.<br>";
        } elseif ($filePasswordHash) {

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accessPassword'])) {
                if (password_verify($_POST['accessPassword'], $filePasswordHash)) {
                    unlink($filePath);
                    unset($files_info[$fileToDelete]);
                    file_put_contents($comments_file, json_encode($files_info, JSON_PRETTY_PRINT));
                    $message .= "✅ File <b>$fileToDelete</b> deleted successfully.<br>";
                } else {
                    $message .= "❌ Incorrect password for deletion.<br>";
                }
            } else {
                echo '<form method="POST">
                        <label>Enter password to delete <b>'.$fileToDelete.'</b>:</label>
                        <input type="password" name="accessPassword" required>
                        <input type="submit" value="Delete">
                      </form>';
                exit;
            }
        } else {
            unlink($filePath);
            unset($files_info[$fileToDelete]);
            file_put_contents($comments_file, json_encode($files_info, JSON_PRETTY_PRINT));
            $message .= "✅ File <b>$fileToDelete</b> deleted successfully.<br>";
        }
    } else {
        $message .= "❌ File info not found.<br>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>File Share</title>
    <link rel="icon" type="image/x-icon" href="favicon.png">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h2>📁 Share your file from anywhere</h2>

   <form action="" method="POST" enctype="multipart/form-data">
    <label for="fileUpload" class="custom-file-upload">
        <span id="fileLabel"><strong>Click to browse</strong></span>
        <input id="fileUpload" type="file" name="fileToUpload" onchange="showFileName()">
        <p> Max : 100 MB</p>
    </label>

        <label>Comment (optional):</label>
        <textarea name="fileComment" rows="3" placeholder="Add a comment about this file"></textarea>

        <label>Password (optional, required to download/delete):</label>
        <input type="password" name="filePassword" placeholder="Set a password">

        <input type="submit" value="Upload File" name="submit">
    </form>

    <p><?php echo $message; ?></p>

    <div class="file-list">
        <h2>Uploaded Files</h2>
        <table>
            <tr>
                <th>File Name</th>
                <th>Size (KB)</th>
                <th>Comment</th>
                <th>Password</th>
                <th>Upload Time</th>
                <th>Download</th>
                <th>Delete</th>
            </tr>
            <?php

            $files_array = [];
            foreach ($files_info as $file => $info) {
                $info['name'] = $file;
                $files_array[] = $info;
            }


            usort($files_array, function($a, $b) {
                return $b['upload_time'] <=> $a['upload_time'];
            });

            foreach ($files_array as $info) {
                $file = $info['name'];
                $filePath = $target_dir . $file;
                if (file_exists($filePath)) {
                    $fileSize = round(filesize($filePath) / 1024, 2);
                    $passwordText = $info['password'] ? 'Yes' : 'No';
                    $uploadTime = date("d M Y, H:i:s", $info['upload_time']);
                    echo "<tr>
                            <td>$file</td>
                            <td>{$fileSize} KB</td>
                            <td>" . htmlspecialchars($info['comment']) . "</td>
                            <td>$passwordText</td>
                            <td>$uploadTime</td>
                            <td><a class='download-btn' href='?download=$file'>Download</a></td>
                            <td><a class='delete-btn' href='?delete=$file'>Delete</a></td>
                          </tr>";
                }
            }
            ?>
        </table>
    </div>
    <div class="footer">
        <br><br>
        <p>Developed by <a href="https://swarupkst.com">Swarup Biswas</a></p>
    </div>
    <script>
function showFileName() {
    const input = document.getElementById('fileUpload');
    const label = document.getElementById('fileLabel');
    if(input.files.length > 0){
        label.innerHTML = `<strong>${input.files[0].name}</strong>`;
    } else {
        label.innerHTML = "<strong>Click to browse</strong>";
    }
}
</script>
</body>
</html>
