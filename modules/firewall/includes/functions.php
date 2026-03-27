<?php
require_once __DIR__ . '/db.php';

// Load phpseclib if available
// Try to load via Composer autoloader first (most common)
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}
// Fallback: Try root-level vendor directory
elseif (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
}

// ------------------
// ENCRYPTION
// ------------------
function enc($plain) {
    if (!$plain) return null;
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', ENC_KEY, 0, $iv);
    return base64_encode($iv . $cipher);
}
function dec($enc) {
    if (!$enc) return null;
    $enc = base64_decode($enc);
    $iv = substr($enc, 0, 16);
    $cipher = substr($enc, 16);
    return openssl_decrypt($cipher, 'AES-256-CBC', ENC_KEY, 0, $iv);
}

// ------------------
// PING FUNCTION
// ------------------
// Pings are done in a row (no delay between individual pings)
// The interval parameter is now used as time between batches (handled in monitor.php)
function pingHost($ip, $count) {
    // Ping count times in a row without delay between pings
    // Use -i 0.2 for minimal delay (allows rapid pings) or remove -i for default
    $cmd = sprintf(
        'ping -c %d -i 0.2 %s 2>&1',
        $count,
        escapeshellarg($ip)
    );
    $output = [];
    exec($cmd, $output);
    $text = implode("\n", $output);

    preg_match_all('/time=/', $text, $m);
    $success = count($m[0]);
    $fail = $count - $success;

    return ['successes' => $success, 'failures' => $fail, 'raw' => $text];
}

// ------------------
// SSH FUNCTION (Using phpseclib v3)
// ------------------
function runSshCommand($ip, $user, $pass, $command) {

    // Detect phpseclib class
    $sshClass = null;

    if (class_exists('phpseclib3\Net\SSH2')) {
        $sshClass = 'phpseclib3\Net\SSH2';
    } elseif (class_exists('phpseclib\Net\SSH2')) {
        $sshClass = 'phpseclib\Net\SSH2';
    } elseif (class_exists('Net_SSH2')) {
        $sshClass = 'Net_SSH2';
    }

    if (!$sshClass) {
        return ['ok' => false, 'output' => 'phpseclib not found. Install via Composer.'];
    }

    try {

        // Create SSH instance
        $ssh = new $sshClass($ip, SSH_PORT);

        // 🔥 AUTO-ACCEPT SSH HOST KEY (equivalent to StrictHostKeyChecking=no)
        if (method_exists($ssh, 'setVerifyHost')) {
            $ssh->setVerifyHost(false);
        }

        // Compatibility: force allowed hostkey algorithms
        if (method_exists($ssh, 'setPreferredAlgorithms')) {
            $ssh->setPreferredAlgorithms([
                'hostkey' => ['ssh-rsa', 'ssh-ed25519', 'rsa-sha2-256', 'rsa-sha2-512']
            ]);
        }

        // Timeout
        if (method_exists($ssh, 'setTimeout')) {
            $ssh->setTimeout(30);
        }

        // Attempt login
        if (!$ssh->login($user, $pass)) {
        return ['ok' => false, 'output' => "SSH authentication failed for user '$user' on $ip"];
    }

        // Clean command - improved sanitization
        // Remove null bytes and dangerous control characters
        $command = str_replace(["\0", "\x00"], '', $command);
        
        // Normalize line endings (Windows to Unix)
        $command = str_replace(["\r\n", "\r"], "\n", $command);
        
        // Trim whitespace from start and end only (preserve internal structure)
        $command = trim($command);
        
        // If command is empty, return error early
        if (empty($command)) {
            return ['ok' => false, 'output' => 'Command is empty'];
        }
        
        // For single-line commands, clean more aggressively
        $isMultiLine = (substr_count($command, "\n") > 0);
        
        if ($isMultiLine) {
            // Multi-line: Split into lines and clean each line
            $lines = explode("\n", $command);
            $cleanedLines = [];
            foreach ($lines as $line) {
                // Remove control characters except newline and tab (for scripts)
                $line = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $line);
                // Remove carriage returns
                $line = str_replace("\r", '', $line);
                // Trim trailing whitespace only (preserve leading spaces for indentation)
                $line = rtrim($line);
                // Keep the line (even if empty, as it might be intentional)
                $cleanedLines[] = $line;
            }
            // Rejoin with newlines (preserve empty lines)
            $command = implode("\n", $cleanedLines);
        } else {
            // Single-line: Just remove control characters
            $command = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $command);
            $command = trim($command);
        }
        
        // Final validation
        if (empty($command)) {
            return ['ok' => false, 'output' => 'Command is empty after sanitization'];
        }
        
        // Ensure command doesn't start with problematic characters
        if (preg_match('/^[\s;|&<>]/', $command)) {
            return ['ok' => false, 'output' => 'Command starts with invalid character'];
        }

        // Execute command
        // Ensure command is a proper string (not an array or object)
        if (!is_string($command)) {
            return ['ok' => false, 'output' => 'Command must be a string'];
        }
        
        // Ensure command is not empty
        if (strlen($command) === 0) {
            return ['ok' => false, 'output' => 'Command is empty'];
        }
        
        // Log the command for debugging (first 200 chars)
        error_log("SSH Command to execute on $ip as user $user: " . substr($command, 0, 200) . " (length: " . strlen($command) . ")");
        
        // IMPORTANT: phpseclib's exec() executes commands directly on the remote server
        // It does NOT use the format "ssh user@ip command" - it handles SSH internally
        // 
        // How it works:
        // 1. Connection: new SSH2($ip, 22) - connects to IP:PORT
        // 2. Login: $ssh->login($user, $pass) - authenticates with username/password
        // 3. Execute: $ssh->exec($command) - executes command directly on remote server
        //
        // So if you enter "ls" in the custom command field, it executes "ls" on the remote server
        // NOT "ssh user@ip ls" - the SSH connection is already established
        
        // For multi-line commands, phpseclib can handle them directly
        // But we need to ensure they're properly formatted
        $isMultiLine = (substr_count($command, "\n") > 0);
        
        if ($isMultiLine) {
            // Multi-line commands: Execute via bash with heredoc for proper handling
            // This ensures each line is executed in sequence
            $wrappedCommand = "bash << 'EOF'\n" . $command . "\nEOF";
            error_log("Wrapping multi-line command for bash execution");
            $output = $ssh->exec($wrappedCommand);
        } else {
            // Single-line command - execute directly
            // phpseclib's exec() sends the command to the remote shell directly
            $output = $ssh->exec($command);
        }
        
        // For phpseclib v3, we might need to read output differently
        // Check if output is false (which indicates failure)
        if ($output === false) {
            // Try to get error information
            $errorInfo = '';
            if (method_exists($ssh, 'getErrors')) {
                $errors = $ssh->getErrors();
                if (!empty($errors)) {
                    $errorInfo = implode("\n", $errors);
                }
            }
            return ['ok' => false, 'output' => 'SSH exec() returned false. ' . ($errorInfo ?: 'No error details available.')];
        }

        // Collect stderr if available
        $stderr = '';
        if (method_exists($ssh, 'getStdError')) {
            $stderr = $ssh->getStdError();
        }
        
        // For phpseclib v3, stderr might be accessed differently
        if (empty($stderr) && method_exists($ssh, 'getStdErr')) {
            $stderr = $ssh->getStdErr();
        }

        // Exit status
        $exitStatus = null;
        if (method_exists($ssh, 'getExitStatus')) {
            $exitStatus = $ssh->getExitStatus();
        }
        
        // If exit status is 255, try to get more detailed error information
        if ($exitStatus == 255) {
            // Try multiple methods to get error information
            if (empty($stderr)) {
                if (method_exists($ssh, 'getErrors')) {
                    $errors = $ssh->getErrors();
                    if (!empty($errors)) {
                        $stderr = implode("\n", $errors);
                    }
                }
            }
            
            // If we still don't have error info, check if output contains error
            if (empty($stderr) && !empty($output)) {
                // Sometimes errors are in stdout
                if (stripos($output, 'error') !== false || stripos($output, 'bad') !== false) {
                    $stderr = $output;
                    $output = '';
                }
            }
        }

        // Disconnect cleanly
        if (method_exists($ssh, 'disconnect')) {
            $ssh->disconnect();
        }

        // Merge outputs
        $fullOutput = trim($output);
        if ($stderr) {
            $fullOutput .= "\n" . trim($stderr);
        }

        // Determine success
        if ($exitStatus === null || $exitStatus === false || $exitStatus === 0) {
            return [
                'ok' => true,
                'output' => ($fullOutput ?: 'Command executed (no output)')
            ];
        }

        // Otherwise command failed
        $errorMsg = "Command failed with exit status $exitStatus";
        if ($exitStatus == 127) $errorMsg .= " (command not found)";
        if ($exitStatus == 126) $errorMsg .= " (cannot execute)";
        if ($exitStatus == 1)   $errorMsg .= " (general error)";
        if ($exitStatus == 255) {
            $errorMsg .= " (SSH connection or shell error)";
            // For exit 255, provide more helpful error message
            if (empty($fullOutput)) {
                $errorMsg .= " - This usually indicates an SSH connection problem or command execution failure.";
            }
        }

        if ($fullOutput) {
            // Truncate very long output to prevent UI issues, but keep important error info
            $outputPreview = strlen($fullOutput) > 500 ? substr($fullOutput, 0, 500) . "... (truncated)" : $fullOutput;
            $errorMsg .= " | Output: " . $outputPreview;
        } else {
            $errorMsg .= " | No output received";
        }
        
        // Log the full error for debugging
        error_log("SSH Command failed - Exit: $exitStatus, Command: " . substr($command, 0, 100) . ", Output: " . substr($fullOutput, 0, 500));

        return ['ok' => false, 'output' => $errorMsg];

    } catch (\Exception $e) {
        return ['ok' => false, 'output' => "SSH Error: " . $e->getMessage()];
    } catch (\Error $e) {
        return ['ok' => false, 'output' => "SSH Error: " . $e->getMessage()];
    }
}


// ------------------
// EMAIL SENDER
// ------------------

/**
 * Clean error message - remove binary data, duplicate messages, and verbose debug output
 */
function cleanErrorMessage($error) {
    if (empty($error)) {
        return "Unknown error";
    }
    
    // Remove binary/garbled characters (non-printable except newlines and tabs)
    $error = preg_replace('/[^\x20-\x7E\n\t]/', '', $error);
    
    // Remove duplicate error messages
    $lines = explode("\n", $error);
    $seen = [];
    $cleaned = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Skip lines that are just binary data or garbled text
        if (preg_match('/^[^\x20-\x7E]+$/', $line)) continue;
        
        // Extract key error phrases
        $key = '';
        if (preg_match('/(SMTP Error|Connection failed|SSL operation failed|certificate verify failed|Could not connect|Authentication failed|QUIT command failed)/i', $line, $matches)) {
            $key = $matches[1];
        }
        
        // Only add if we haven't seen this key error before
        if (empty($key) || !isset($seen[$key])) {
            $cleaned[] = $line;
            if (!empty($key)) {
                $seen[$key] = true;
            }
        }
    }
    
    $result = implode("\n", $cleaned);
    
    // Extract the main error message (first meaningful line)
    $mainError = '';
    foreach ($cleaned as $line) {
        if (preg_match('/(SMTP Error|Connection failed|SSL|certificate|Could not connect|Authentication failed)/i', $line)) {
            $mainError = $line;
            break;
        }
    }
    
    // If we found a main error, return it with context (but not all the verbose output)
    if (!empty($mainError)) {
        // Get OpenSSL error if present
        if (preg_match('/OpenSSL Error[^:]*:\s*(.+?)(?:\n|$)/i', $error, $matches)) {
            $mainError .= "\n" . trim($matches[1]);
        }
        return $mainError;
    }
    
    // Otherwise return cleaned version (first few lines)
    return implode("\n", array_slice($cleaned, 0, 3));
}

function sendNotificationEmail($emails, $subject, $body, &$errorDetails = null) {
    if (empty($emails)) {
        $errorMsg = "No email addresses configured for notification";
        error_log("Firewall Monitor: " . $errorMsg);
        if ($errorDetails !== null) {
            $errorDetails = $errorMsg;
        }
        return false;
    }
    
    // Try to load PHPMailer
    $phpmailerLoaded = false;
    if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
        require_once __DIR__ . '/../../vendor/autoload.php';
        $phpmailerLoaded = class_exists('PHPMailer\PHPMailer\PHPMailer');
    } elseif (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
        require_once __DIR__ . '/../../../vendor/autoload.php';
        $phpmailerLoaded = class_exists('PHPMailer\PHPMailer\PHPMailer');
    }
    
    $sent = 0;
    $failed = 0;
    $errors = [];
    $lastError = '';
    
    foreach ($emails as $email) {
        $email = trim($email);
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("Firewall Monitor: Invalid email address: " . $email);
            $failed++;
            $errors[] = "Invalid email: $email";
            continue;
        }
        
        $result = false;
        $errorMsg = '';
        
        // Use PHPMailer if available
        if ($phpmailerLoaded) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                
                // SMTP Configuration
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->Port = SMTP_PORT;
                
                // Check if SMTP authentication is required
                $smtpAuth = defined('SMTP_AUTH') ? constant('SMTP_AUTH') : false;
                $mail->SMTPAuth = $smtpAuth;
                
                // If authentication is enabled
                if ($smtpAuth && defined('SMTP_USERNAME') && defined('SMTP_PASSWORD')) {
                    $mail->Username = constant('SMTP_USERNAME');
                    $mail->Password = constant('SMTP_PASSWORD');
                }
                
                // Security settings - for custom SMTP server (156.38.197.98:587)
                // Port 587 typically uses TLS, but custom servers may not require it
                if (defined('SMTP_SECURE')) {
                    $smtpSecure = constant('SMTP_SECURE');
                    if ($smtpSecure !== false && $smtpSecure !== null && $smtpSecure !== '') {
                        $mail->SMTPSecure = $smtpSecure;
                        if ($smtpSecure === false) {
                            $mail->SMTPAutoTLS = false;
                        }
                    } else {
                        // Default: no encryption for custom SMTP servers unless specified
                        $mail->SMTPSecure = false;
                        $mail->SMTPAutoTLS = false;
                    }
                } else {
                    // Auto-detect based on port
                    // For custom SMTP servers (not Gmail), default to no encryption
                    if (SMTP_PORT == 465) {
                        $mail->SMTPSecure = 'ssl';
                    } elseif (SMTP_PORT == 587) {
                        // For port 587, try TLS first (common for Gmail)
                        // But for custom servers, you may need to set SMTP_SECURE = false
                        $mail->SMTPSecure = 'tls';
                    } elseif (SMTP_PORT == 25) {
                        // Port 25 typically doesn't use encryption
                        $mail->SMTPSecure = false;
                        $mail->SMTPAutoTLS = false;
                    } else {
                        // Default: no encryption for custom servers
                        $mail->SMTPSecure = false;
                        $mail->SMTPAutoTLS = false;
                    }
                }
                
                // For custom SMTP servers that don't support TLS on port 587,
                // you can add this in config.php: define('SMTP_SECURE', false);
                
                // Enable verbose debug output for troubleshooting
                // Temporarily enable debug mode to see connection details
                $mail->SMTPDebug = 2; // Enable debug output
                $debugOutput = [];
                $mail->Debugoutput = function($str, $level) use (&$debugOutput) { 
                    $debugOutput[] = $str;
                    error_log("Firewall Monitor SMTP Debug: $str"); 
                };
                
                // Set timeout for SMTP connection
                $mail->Timeout = 30;
                
                // Sender
                $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
                $mail->addReplyTo(SMTP_FROM, SMTP_FROM_NAME);
                
                // Recipient
                $mail->addAddress($email);
                
                // Content
                $mail->isHTML(false); // Plain text
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->CharSet = 'UTF-8';
                
                // Send
                $result = $mail->send();
                
                if ($result) {
                    $sent++;
                    error_log("Firewall Monitor: Email sent successfully via SMTP to: " . $email);
                } else {
                    // Get actual error from PHPMailer and clean it up
                    $errorMsg = $mail->ErrorInfo ?: "Unknown error - send() returned false";
                    $errorMsg = cleanErrorMessage($errorMsg);
                    $lastError = $errorMsg;
                }
                
            } catch (\Exception $e) {
                // Get actual exception message and clean it up
                $errorMsg = $e->getMessage();
                $fullError = cleanErrorMessage($errorMsg);
                
                // Add ErrorInfo if available (cleaned)
                if (isset($mail) && property_exists($mail, 'ErrorInfo') && !empty($mail->ErrorInfo)) {
                    $phpmailerError = cleanErrorMessage($mail->ErrorInfo);
                    if ($phpmailerError !== $fullError) {
                        $fullError .= "\n" . $phpmailerError;
                    }
                }
                
                error_log("Firewall Monitor: Email sending failed for $email - " . $fullError);
                $errorMsg = $fullError;
                $lastError = $fullError;
            }
        } else {
            // Fallback to PHP mail() function if PHPMailer is not available
            $headers = [
                "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">",
                "Reply-To: " . SMTP_FROM,
                "X-Mailer: PHP/" . phpversion(),
                "MIME-Version: 1.0",
                "Content-Type: text/plain; charset=UTF-8"
            ];
            $headersString = implode("\r\n", $headers);
            
            $result = @mail($email, $subject, $body, $headersString);
            
            if ($result) {
                $sent++;
                error_log("Firewall Monitor: Email sent successfully via mail() to: " . $email);
            } else {
                $errorMsg = "mail() function failed";
                error_log("Firewall Monitor: mail() function failed for: " . $email);
            }
        }
        
        if (!$result) {
            $failed++;
            $errorText = $errorMsg ?: "Unknown error";
            $errors[] = "$email: " . $errorText;
            error_log("Firewall Monitor: Failed to send email to: $email - " . $errorText);
            if (empty($lastError)) {
                $lastError = $errorText;
            }
        }
    }
    
    // Log summary
    error_log("Firewall Monitor: Email notification summary - Sent: $sent, Failed: $failed, Total: " . count($emails));
    if (!empty($errors)) {
        error_log("Firewall Monitor: Email errors: " . implode("; ", $errors));
    }
    
    // Store last error in errorDetails if provided
    if ($errorDetails !== null && !empty($lastError)) {
        $errorDetails = $lastError;
    } elseif ($errorDetails !== null && $failed > 0) {
        $errorDetails = implode("; ", $errors);
    }
    
    return $sent > 0;
}

function getEmails() {
    try {
        $emails = db()->query("SELECT email FROM notification_emails WHERE active=1")->fetchAll(PDO::FETCH_COLUMN);
        
        // Log for debugging
        if (empty($emails)) {
            error_log("Firewall Monitor: No active email addresses found in database");
        } else {
            error_log("Firewall Monitor: Found " . count($emails) . " active email address(es)");
        }
        
        return $emails;
    } catch (Exception $e) {
        error_log("Firewall Monitor: Error retrieving emails: " . $e->getMessage());
        return [];
    }
}

// ------------------
// SITE HELPERS
// ------------------
function getSites($filter, $search) {
    $sql = "SELECT * FROM sites WHERE 1=1";
    $params = [];

    if ($filter) {
        $sql .= " AND status = :status";
        $params['status'] = $filter;
    }

    if ($search) {
        $sql .= " AND name LIKE :search";
        $params['search'] = "%" . $search . "%";
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function logAction($siteId, $type, $by, $before, $after, $output, $sshSuccess = null, $command = null) {
    $stmt = db()->prepare("
        INSERT INTO site_actions (site_id, action_type, initiated_by, status_before, status_after, ssh_output, ssh_success, command_executed)
        VALUES (:id,:type,:by,:before,:after,:out,:success,:cmd)
    ");
    $stmt->execute([
        'id' => $siteId,
        'type' => $type,
        'by' => $by,
        'before' => $before,
        'after' => $after,
        'out' => $output,
        'success' => $sshSuccess,
        'cmd' => $command
    ]);
}

function logPingCheck($siteId, $pingCount, $batchInterval, $successes, $failures, $status, $statusChanged = false, $previousStatus = null) {
    $stmt = db()->prepare("
        INSERT INTO ping_logs (site_id, ping_count, ping_interval_sec, successes, failures, status, status_changed, previous_status)
        VALUES (:id,:count,:interval,:successes,:failures,:status,:changed,:prev)
    ");
    $stmt->execute([
        'id' => $siteId,
        'count' => $pingCount,
        'interval' => $batchInterval, // Now represents time between batches
        'successes' => $successes,
        'failures' => $failures,
        'status' => $status,
        'changed' => $statusChanged ? 1 : 0,
        'prev' => $previousStatus
    ]);
}

