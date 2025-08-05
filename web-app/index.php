<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$mysqli = new mysqli("localhost", "ha_user", "securepass", "ha_app");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Allowed file extensions for upload
$allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'doc', 'docx'];
$maxFileSize = 10 * 1024 * 1024; // 10MB
function isNodeActive() {
    $vip = '192.168.100.200';
    $ifconfig = shell_exec("ip addr");
    return strpos($ifconfig, $vip) !== false;
}

// Handle add random user
if (isset($_POST['add_user'])) {
    $randomUser = "user_" . rand(1000, 9999);
    $stmt = $mysqli->prepare("INSERT INTO users (username) VALUES (?)");
    $stmt->bind_param("s", $randomUser);
    $stmt->execute();
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle file upload
if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] == 0) {
    if(!isNodeActive()){
        throw new Exception('Cannot upload to standby node.');
    }
    $uploadDir = __DIR__ . "/uploads/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = basename($_FILES['upload_file']['name']);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $filesize = $_FILES['upload_file']['size'];

    // Check extension and size
    if (!in_array($ext, $allowedExts)) {
        die("Error: File type not allowed.");
    }
    if ($filesize > $maxFileSize) {
        die("Error: File size exceeds 10MB.");
    }

    // To avoid overwriting, prepend a timestamp
    $newFilename = time() . "_" . $filename;
    $targetFile = $uploadDir . $newFilename;

    if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $targetFile)) {
        $stmt = $mysqli->prepare("INSERT INTO files (filename, filesize) VALUES (?, ?)");
        $stmt->bind_param("si", $newFilename, $filesize);
        $stmt->execute();
        $stmt->close();
    } else {
        die("Error uploading the file.");
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle file delete
if (isset($_GET['delete_file'])) {
    if(!isNodeActive()){
        throw new Exception('Cannot delete file on standby node.');
    }
    $fileId = intval($_GET['delete_file']);
    $stmt = $mysqli->prepare("SELECT filename FROM files WHERE id = ?");
    $stmt->bind_param("i", $fileId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $filepath = __DIR__ . "/uploads/" . $row['filename'];
        if (file_exists($filepath)) unlink($filepath);
        $stmtDel = $mysqli->prepare("DELETE FROM files WHERE id = ?");
        $stmtDel->bind_param("i", $fileId);
        $stmtDel->execute();
        $stmtDel->close();
    }
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle file rename
if (isset($_POST['rename_file'], $_POST['file_id'], $_POST['new_name'])) {
    if(!isNodeActive()){
        throw new Exception('Cannot rename file on standby node.');
    }
    $fileId = intval($_POST['file_id']);
    $newNameRaw = $_POST['new_name'];
    $newName = basename($newNameRaw);
    $ext = strtolower(pathinfo($newName, PATHINFO_EXTENSION));
    // Validate extension for rename too
    if (!in_array($ext, $allowedExts)) {
        die("Error: File type not allowed.");
    }

    $stmt = $mysqli->prepare("SELECT filename FROM files WHERE id = ?");
    $stmt->bind_param("i", $fileId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $oldPath = __DIR__ . "/uploads/" . $row['filename'];
        $newPath = __DIR__ . "/uploads/" . $newName;
        if (file_exists($oldPath)) {
            if (!file_exists($newPath)) {
                rename($oldPath, $newPath);
                $stmtUp = $mysqli->prepare("UPDATE files SET filename = ? WHERE id = ?");
                $stmtUp->bind_param("si", $newName, $fileId);
                $stmtUp->execute();
                $stmtUp->close();
            } else {
                die("Error: A file with the new name already exists.");
            }
        }
    }
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch users
$users = $mysqli->query("SELECT * FROM users ORDER BY created_at DESC");

// Fetch files
$files = $mysqli->query("SELECT * FROM files ORDER BY uploaded_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>HA App - Users and Files</title>
<style>
    /* Reset */
    * {
        box-sizing: border-box;
    }
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #1e1e2f;
        color: #ddd;
        margin: 0; padding: 0;
        min-height: 100vh;
    }
    h1, h2 {
        text-align: center;
        margin-top: 20px;
        color: #ff6f61;
        // text-shadow: 0 0 2px #ff6f61;
    }
    h1 {
        font-weight:normal;
    }
    h1 > strong{
        color:white;
    }
    .container {
        max-width: 960px;
        margin: 20px auto 60px;
        background: #2e2e3e;
        padding: 30px;
        border-radius: 12px;
        // box-shadow: 0 0 15px rgba(255, 111, 97, 0.6);
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
        background: #222233;
        border-radius: 8px;
        overflow: hidden;
    }
    th, td {
        padding: 12px 15px;
        border-bottom: 1px solid #444466;
        text-align: left;
    }
    th {
        background: #ff6f61;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 0.1em;
    }
    tr:hover {
        background: #3a3a56;
    }
    button, input[type="submit"] {
        background: #ff6f61;
        border: none;
        color: white;
        padding: 10px 18px;
        font-size: 14px;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    button:hover, input[type="submit"]:hover {
        background: #e55b4f;
    }
    input[type="file"], input[type="text"] {
        padding: 8px;
        border-radius: 5px;
        border: none;
        width: 100%;
        box-shadow: inset 0 0 5px #444466;
        background: #1e1e2f;
        color: #ddd;
        margin-bottom: 10px;
    }
    form.inline {
        display: inline-block;
        margin: 0 5px;
    }
    a {
        color: #ff6f61;
        text-decoration: none;
        font-weight: bold;
    }
    a:hover {
        text-decoration: underline;
    }
    .status {
        text-align: center;
        font-size: 14px;
        margin-bottom: 25px;
        color: #ff9a90;
        font-weight: bold;
    }
    @media (max-width: 600px) {
        .container {
            padding: 15px;
        }
        table, thead, tbody, th, td, tr {
            display: block;
        }
        tr {
            margin-bottom: 15px;
        }
        th {
            background: none;
            color: #ff6f61;
            padding-left: 15px;
            text-align: left;
            border-bottom: none;
        }
        td {
            padding-left: 15px;
            border: none;
            position: relative;
            padding-bottom: 20px;
            text-align: left;
        }
        td::before {
            content: attr(data-label);
            position: absolute;
            left: 15px;
            top: 0;
            font-weight: bold;
            color: #ff6f61;
        }
        form.inline {
            display: block;
            margin: 10px 0;
        }
        input[type="text"], input[type="file"] {
            width: 100%;
        }
    }
</style>
</head>
<body>
<div class="container">

<h1>The <strong><?= htmlspecialchars(gethostname()); ?></strong> is <?= isNodeActive()?'active':'standby' ?> now</h1>
<h2>Users</h2>
<form method="POST" style="text-align:center;margin-bottom:5px;">
    <button type="submit" name="add_user">Add Random User</button>
</form>
<table>
<thead><tr><th>ID</th><th>Username</th><th>Created At</th></tr></thead>
<tbody>
<?php while ($user = $users->fetch_assoc()) { ?>
<tr>
    <td data-label="ID"><?= htmlspecialchars($user['id']) ?></td>
    <td data-label="Username"><?= htmlspecialchars($user['username']) ?></td>
    <td data-label="Created At"><?= htmlspecialchars($user['created_at']) ?></td>
</tr>
<?php } ?>
</tbody>
</table>

<h2>Upload File</h2>
<?php if(isNodeActive()):?>
<form method="POST" enctype="multipart/form-data" style="max-width:400px;margin:auto;">
    <input type="file" name="upload_file" required>
    <button type="submit">Upload</button>
</form>
<?php else: ?>
<p style="color:red;text-align:center;">Uploads are disabled on standby node.</p>
<?php endif; ?>

<h2>Files</h2>
<table>
<thead>
<tr><th>ID</th><th>Name</th><th>Size (bytes)</th><th>Uploaded At</th><th>Actions</th></tr>
</thead>
<tbody>
<?php while ($file = $files->fetch_assoc()) { ?>
<tr>
    <td data-label="ID"><?= htmlspecialchars($file['id']) ?></td>
    <td data-label="Name"><a href="/uploads/<?= htmlspecialchars($file['filename']) ?>" target="_blank"><?= htmlspecialchars($file['filename']) ?></a></td>
    <td data-label="Size"><?= htmlspecialchars($file['filesize']) ?></td>
    <td data-label="Uploaded At"><?= htmlspecialchars($file['uploaded_at']) ?></td>
    <td data-label="Actions">
        <form class="inline" method="POST" style="display:inline;">
            <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
            <input type="text" name="new_name" placeholder="New name" required>
            <button type="submit" name="rename_file">Rename</button>
        </form>
        <a href="?delete_file=<?= $file['id'] ?>" onclick="return confirm('Are you sure you want to delete this file?')">Delete</a>
    </td>
</tr>
<?php } ?>
</tbody>
</table>

</div>
</body>
</html>