<?php
session_start();

require_once __DIR__ . '/config.php';

// Check if user is authenticated
function requireAuth() {
    if (empty($_SESSION['firewall_auth'])) {
        // Show PIN screen
        $pin_error = '';
        if (isset($_POST['pin'])) {
            if ($_POST['pin'] === PIN_CODE) {
                $_SESSION['firewall_auth'] = true;
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            } else {
                $pin_error = 'Incorrect PIN';
            }
        }
        
        // Show PIN entry screen
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Enter PIN - Firewall Monitor</title>
            <style>
                body {
                    background: #f4f7fa;
                    color: #fff;
                    font-family: Arial, sans-serif;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    height: 100vh;
                    margin: 0;
                }
                .box {
                    background: #fff;
                    color: #001a66;
                    padding: 30px;
                    border-radius: 10px;
                    width: 320px;
                    text-align: center;
                }
                input {
                    width: 100%;
                    padding: 12px;
                    font-size: 18px;
                    margin: 15px 0;
                    border-radius: 6px;
                    border: 1px solid #ccc;
                    box-sizing: border-box;
                }
                button {
                    width: 100%;
                    padding: 12px;
                    font-size: 17px;
                    background: #001a66;
                    color: #fff;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                }
                .error {
                    color: red;
                    min-height: 22px;
                    margin-bottom: 10px;
                }
                img {
                    max-height: 60px;
                    margin-bottom: 10px;
                }
            </style>
        </head>
        <body>
            <div class="box">
                <img src="logo.jpg" onerror="this.style.display='none'">
                <h2>Enter PIN</h2>
                <div class="error"><?= htmlspecialchars($pin_error) ?></div>
                <form method="post">
                    <input type="password" name="pin" placeholder="PIN" autofocus required>
                    <button type="submit">Unlock</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Logout function
function logout() {
    unset($_SESSION['firewall_auth']);
    // Redirect will be handled by calling script
}

