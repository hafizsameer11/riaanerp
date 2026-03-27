<?php
// Ensure no output before headers
ob_start();

require_once __DIR__ . '/../includes/auth.php';
requireAuth();
require_once __DIR__ . '/../includes/functions.php';

// Start session for error messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id = $_POST['id'] ?? null;
$errors = [];

try {
    $fields = [
        'name','location','primary_ip','secondary_ip','ssh_username',
        'description','login_url','login_username',
        'ping_count','ping_interval_sec','failover_command',
        'failback_command','reboot_command',
        'auto_failback_enabled','auto_failback_success_pings'
    ];

    $data = [];
    foreach ($fields as $f) { 
        $data[$f] = isset($_POST[$f]) ? trim($_POST[$f]) : null; 
    }

    $monitoring = isset($_POST['monitoring_enabled']) ? 1 : 0;
    $autoFailback = isset($_POST['auto_failback_enabled']) ? 1 : 0;

    $sshPass = isset($_POST['ssh_password']) ? trim($_POST['ssh_password']) : null;
    $loginPass = isset($_POST['login_password']) ? trim($_POST['login_password']) : null;

    // Validation
    if (empty($data['name'])) {
        $errors[] = "Site name is required.";
    }

    if (empty($data['primary_ip'])) {
        $errors[] = "Primary IP address is required.";
    } elseif (!filter_var($data['primary_ip'], FILTER_VALIDATE_IP) && !preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $data['primary_ip'])) {
        $errors[] = "Primary IP address or hostname is invalid.";
    }

    if (empty($data['secondary_ip'])) {
        $errors[] = "Secondary IP address is required.";
    } elseif (!filter_var($data['secondary_ip'], FILTER_VALIDATE_IP) && !preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $data['secondary_ip'])) {
        $errors[] = "Secondary IP address or hostname is invalid.";
    }

    if (empty($data['ssh_username'])) {
        $errors[] = "SSH username is required.";
    }

    // For new sites, SSH password is required
    if (!$id && empty($sshPass)) {
        $errors[] = "SSH password is required for new sites.";
    }

    // Validate numeric fields
    if (!empty($data['ping_count']) && (!is_numeric($data['ping_count']) || $data['ping_count'] < 1 || $data['ping_count'] > 100)) {
        $errors[] = "Ping count must be a number between 1 and 100.";
    }

    if (!empty($data['ping_interval_sec']) && (!is_numeric($data['ping_interval_sec']) || $data['ping_interval_sec'] < 1 || $data['ping_interval_sec'] > 3600)) {
        $errors[] = "Ping interval must be a number between 1 and 3600 seconds.";
    }

    if (!empty($data['auto_failback_success_pings']) && (!is_numeric($data['auto_failback_success_pings']) || $data['auto_failback_success_pings'] < 1 || $data['auto_failback_success_pings'] > 1000)) {
        $errors[] = "Auto-failback success pings must be a number between 1 and 1000.";
    }

    // If there are validation errors, redirect back with errors
    if (!empty($errors)) {
        $_SESSION['error'] = implode(' ', $errors);
        ob_end_clean();
        if ($id) {
            header("Location: ../pages/site_form.php?id=" . urlencode($id));
        } else {
            header("Location: ../pages/site_form.php");
        }
        exit;
    }

    // Database operations
    if ($id) {
        // Update existing site
        try {
            $stmt = db()->prepare("SELECT * FROM sites WHERE id=?");
            $stmt->execute([$id]);
            $old = $stmt->fetch();

            if (!$old) {
                throw new Exception("Site not found. It may have been deleted.");
            }

            $sql = "UPDATE sites SET ";
            $params = [];

            foreach ($fields as $f) {
                $sql .= "$f=:$f, ";
                $params[$f] = $data[$f];
            }

            if ($sshPass) {
                try {
                    $encryptedSshPass = enc($sshPass);
                    if ($encryptedSshPass === false) {
                        throw new Exception("Failed to encrypt SSH password.");
                    }
                    $sql .= "ssh_password_enc=:sshpass, ";
                    $params['sshpass'] = $encryptedSshPass;
                } catch (Exception $e) {
                    throw new Exception("Error encrypting SSH password: " . $e->getMessage());
                }
            }
            
            if ($loginPass) {
                try {
                    $encryptedLoginPass = enc($loginPass);
                    if ($encryptedLoginPass === false) {
                        throw new Exception("Failed to encrypt login password.");
                    }
                    $sql .= "login_password_enc=:loginpass, ";
                    $params['loginpass'] = $encryptedLoginPass;
                } catch (Exception $e) {
                    throw new Exception("Error encrypting login password: " . $e->getMessage());
                }
            }

            $sql .= "monitoring_enabled=:m, auto_failback_enabled=:afb WHERE id=:id";
            $params['m'] = $monitoring;
            $params['afb'] = $autoFailback;
            $params['id'] = $id;

            $stmt = db()->prepare($sql);
            if (!$stmt) {
                $errorInfo = db()->errorInfo();
                throw new Exception("Failed to prepare update statement: " . ($errorInfo[2] ?? 'Unknown error'));
            }
            
            $result = $stmt->execute($params);
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception("Failed to update site: " . ($errorInfo[2] ?? 'Unknown error'));
            }

            $_SESSION['success'] = "Site updated successfully.";
            
            // Clear any output buffer and redirect immediately after successful update
            ob_end_clean();
            header("Location: ../pages/index.php");
            exit;

        } catch (PDOException $e) {
            error_log("Database error in save_site.php (UPDATE): " . $e->getMessage());
            $_SESSION['error'] = "Database error occurred while updating the site. Please try again.";
            ob_end_clean();
            header("Location: ../pages/site_form.php?id=" . urlencode($id));
            exit;
        } catch (Exception $e) {
            error_log("Error in save_site.php (UPDATE): " . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
            ob_end_clean();
            header("Location: ../pages/site_form.php?id=" . urlencode($id));
            exit;
        }

    } else {
        // Insert new site
        try {
            if (empty($sshPass)) {
                throw new Exception("SSH password is required for new sites.");
            }

            // Encrypt passwords
            try {
                $encryptedSshPass = enc($sshPass);
                if ($encryptedSshPass === false) {
                    throw new Exception("Failed to encrypt SSH password.");
                }
            } catch (Exception $e) {
                throw new Exception("Error encrypting SSH password: " . $e->getMessage());
            }

            $encryptedLoginPass = null;
            if ($loginPass) {
                try {
                    $encryptedLoginPass = enc($loginPass);
                    if ($encryptedLoginPass === false) {
                        throw new Exception("Failed to encrypt login password.");
                    }
                } catch (Exception $e) {
                    throw new Exception("Error encrypting login password: " . $e->getMessage());
                }
            }

            $stmt = db()->prepare("
                INSERT INTO sites
                (name,location,primary_ip,secondary_ip,ssh_username,ssh_password_enc,
                description,login_url,login_username,login_password_enc,
                ping_count,ping_interval_sec,monitoring_enabled,
                failover_command,failback_command,reboot_command,
                auto_failback_enabled,auto_failback_success_pings)
                VALUES
                (:name,:location,:primary_ip,:secondary_ip,:ssh_user,:ssh_pass,
                :descr,:login_url,:login_user,:login_pass,
                :pingc,:pingi,:mon,
                :fo,:fb,:rb,
                :afb,:afbp)
            ");

            if (!$stmt) {
                throw new Exception("Failed to prepare insert statement.");
            }

            $result = $stmt->execute([
                'name'=>$data['name'],
                'location'=>$data['location'],
                'primary_ip'=>$data['primary_ip'],
                'secondary_ip'=>$data['secondary_ip'],
                'ssh_user'=>$data['ssh_username'],
                'ssh_pass'=>$encryptedSshPass,
                'descr'=>$data['description'],
                'login_url'=>$data['login_url'],
                'login_user'=>$data['login_username'],
                'login_pass'=>$encryptedLoginPass,
                'pingc'=>$data['ping_count'] ?: null,
                'pingi'=>$data['ping_interval_sec'] ?: null,
                'mon'=>$monitoring,
                'fo'=>$data['failover_command'],
                'fb'=>$data['failback_command'],
                'rb'=>$data['reboot_command'],
                'afb'=>$autoFailback,
                'afbp'=>$data['auto_failback_success_pings'] ?: null
            ]);

            if (!$result) {
                throw new Exception("Failed to create site. Please try again.");
            }

            $_SESSION['success'] = "Site created successfully.";
            
            // Clear any output buffer and redirect immediately after successful insert
            ob_end_clean();
            header("Location: ../pages/index.php");
            exit;

        } catch (PDOException $e) {
            error_log("Database error in save_site.php (INSERT): " . $e->getMessage());
            
            // Check for specific database errors
            if ($e->getCode() == 23000) {
                $_SESSION['error'] = "A site with this name or IP address already exists.";
            } else {
                $_SESSION['error'] = "Database error occurred while creating the site. Please try again.";
            }
            
            ob_end_clean();
            header("Location: ../pages/site_form.php");
            exit;
        } catch (Exception $e) {
            error_log("Error in save_site.php (INSERT): " . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
            ob_end_clean();
            header("Location: ../pages/site_form.php");
            exit;
        }
    }

    // This should never be reached, but kept as fallback
    // Success - redirect to dashboard (for update case)
    if (!isset($_SESSION['success'])) {
        $_SESSION['success'] = "Operation completed successfully.";
    }
    ob_end_clean();
    header("Location: ../pages/index.php");
    exit;

} catch (Exception $e) {
    // Catch any unexpected errors
    error_log("Unexpected error in save_site.php: " . $e->getMessage());
    $_SESSION['error'] = "An unexpected error occurred. Please try again.";
    
    ob_end_clean();
    if ($id) {
        header("Location: ../pages/site_form.php?id=" . urlencode($id));
    } else {
        header("Location: ../pages/site_form.php");
    }
    exit;
}

