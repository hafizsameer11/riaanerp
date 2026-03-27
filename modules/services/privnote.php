<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function generateRandomString($length = 12) {
    return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
}

if (isset($_POST['save_note'])) {
    $note = $conn->real_escape_string($_POST['note']);
    $note_id = generateRandomString();

    $stmt = $conn->prepare("INSERT INTO privnotes (note_id, note) VALUES (?, ?)");
    $stmt->bind_param("ss", $note_id, $note);
    $stmt->execute();

    $link = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?note=' . $note_id;
}

$note_text = null;
if (isset($_GET['note'])) {
    $note_id = $conn->real_escape_string($_GET['note']);
    $res = $conn->query("SELECT * FROM privnotes WHERE note_id = '$note_id' LIMIT 1");

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $note_text = $row['note'];
        $conn->query("DELETE FROM privnotes WHERE note_id = '$note_id'");
    } else {
        $note_text = "❌ This note has already been viewed or does not exist.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>PrivNote</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: #f4f7fa;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
    }
    .container {
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 500px;
      text-align: center;
    }
    textarea {
      width: 100%;
      height: 150px;
      padding: 10px;
      border-radius: 8px;
      border: 1px solid #ccc;
      resize: none;
      margin-bottom: 20px;
      font-size: 16px;
    }
    .button {
      background: #007bff;
      color: white;
      padding: 10px 20px;
      font-size: 16px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: 0.3s;
      margin-top: 10px;
    }
    .button:hover {
      background: #0056b3;
    }
    .link-box {
      background: #eef2f7;
      padding: 10px;
      margin-top: 20px;
      border-radius: 8px;
      word-break: break-word;
    }
    .show-note-button {
      background: #28a745;
      color: white;
      padding: 12px 24px;
      font-size: 18px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: 0.3s;
      margin-top: 20px;
    }
    .show-note-button:hover {
      background: #218838;
    }
  </style>
</head>
<body>

<div class="container">
    <?php if (isset($link)): ?>
        <h2>✅ Your Private Note Link:</h2>
        <div class="link-box" id="link"><?= htmlspecialchars($link) ?></div>
        <button class="button" onclick="copyLink()">Copy Link</button>
        <script>
        function copyLink() {
            const link = document.getElementById('link').innerText;
            navigator.clipboard.writeText(link).then(() => {
                alert("Link copied to clipboard!");
            });
        }
        </script>

    <?php elseif (isset($_GET['note'])): ?>
        <?php if (strpos($note_text, '❌') !== false): ?>
            <h2><?= htmlspecialchars($note_text) ?></h2>
        <?php else: ?>
            <h2>🔒 This note will be deleted after viewing</h2>
            <button class="show-note-button" onclick="showNote()">Show Note</button>
            <div id="note-content" style="display:none; margin-top:20px;">
                <textarea readonly><?= htmlspecialchars($note_text) ?></textarea>
            </div>
            <script>
            function showNote() {
                document.querySelector('.show-note-button').style.display = 'none';
                document.getElementById('note-content').style.display = 'block';
            }
            </script>
        <?php endif; ?>

    <?php else: ?>
        <h2>Create a Private Note</h2>
        <form method="POST">
            <textarea name="note" placeholder="Type your secret note here..." required></textarea>
            <button class="button" type="submit" name="save_note">Create Note</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>