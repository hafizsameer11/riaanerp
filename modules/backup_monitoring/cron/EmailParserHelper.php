<?php
/**
 * Email Parser Helper
 * Provides utilities for email parsing with better error handling for missing IMAP
 */

class EmailParserHelper {
    
    /**
     * Check if IMAP extension is available
     * @return bool True if IMAP is available, false otherwise
     */
    public static function isImapAvailable() {
        return function_exists('imap_open');
    }
    
    /**
     * Get IMAP availability status with detailed info
     * @return array Status information
     */
    public static function getImapStatus() {
        $available = self::isImapAvailable();
        
        return [
            'available' => $available,
            'enabled_extensions' => get_loaded_extensions(),
            'php_version' => phpversion(),
            'message' => $available 
                ? 'IMAP extension is available'
                : 'IMAP extension is not available on this server'
        ];
    }
    
    /**
     * Get email configuration from database
     * PHP 7.4 CLI Compatible - Uses bind_result() instead of get_result()
     * @param string $mailType The type of email (CLIENT, VEEAM, VEEAMCLOUD, NAS)
     * @param object $conn Database connection
     * @return array|null Email configuration or null if not found
     */
    public static function getEmailConfig($mailType, $conn) {
        $stmt = $conn->prepare("SELECT id, email_address, app_password, imap_host FROM backup_mails WHERE mail_type = ? AND is_active = 1 LIMIT 1");
        if (!$stmt) {
            error_log("EmailParserHelper: Failed to prepare query: " . $conn->error);
            return null;
        }
        
        $stmt->bind_param("s", $mailType);
        $stmt->execute();
        
        // PHP 7.4 CLI Compatible: Use bind_result() instead of get_result()
        $config = null;
        
        // Check if mysqlnd is available (get_result() works)
        if (extension_loaded('mysqlnd')) {
            $result = $stmt->get_result();
            if ($result) {
                $config = $result->fetch_assoc();
            }
        } else {
            // Fallback: Use bind_result() for PHP 7.4 CLI compatibility
            $stmt->store_result();
            $meta = $stmt->result_metadata();
            
            if ($meta) {
                $fields = [];
                $params = [];
                
                while ($field = $meta->fetch_field()) {
                    $params[] = &$fields[$field->name];
                }
                
                call_user_func_array([$stmt, 'bind_result'], $params);
                
                if ($stmt->fetch()) {
                    $config = [];
                    foreach ($fields as $key => $value) {
                        $config[$key] = $value;
                    }
                }
                
                $meta->free();
            }
        }
        
        $stmt->close();
        
        if (!$config) {
            return null;
        }
        
        // Decrypt password if needed
        $config['app_password'] = self::decryptPassword($config['app_password']);
        
        return $config;
    }
    
    /**
     * Decrypt password from database
     * @param string $encrypted Encrypted password
     * @return string Decrypted password
     */
    public static function decryptPassword($encrypted) {
        if (empty($encrypted)) {
            return '';
        }
        
        try {
            define('ENC_KEY', 'G7fP9xL2tQ8wR4kB1mZ6uH3cV0aN5sD');
            
            // Decode from base64
            $data = base64_decode($encrypted, true);
            if ($data === false) {
                return $encrypted; // Return as-is if not encrypted
            }
            
            // Extract IV and cipher
            $iv = substr($data, 0, 16);
            $cipher = substr($data, 16);
            
            // Decrypt using AES-256-CBC
            $decrypted = openssl_decrypt($cipher, 'AES-256-CBC', ENC_KEY, 0, $iv);
            
            if ($decrypted === false) {
                return $encrypted; // Return original if decryption fails
            }
            
            return $decrypted;
        } catch (Exception $e) {
            error_log('Password decryption error: ' . $e->getMessage());
            return $encrypted;
        }
    }
    
    /**
     * Get IMAP host from configuration
     * @param array $mailConfig Configuration array
     * @param string $defaultHost Default IMAP host if not specified
     * @return string IMAP host string
     */
    public static function getImapHost($mailConfig, $defaultHost = '{imap.gmail.com:993/imap/ssl}INBOX') {
        if (empty($mailConfig['imap_host'])) {
            return $defaultHost;
        }
        
        return $mailConfig['imap_host'];
    }
    
    /**
     * Test IMAP connection
     * @param string $imapHost IMAP host
     * @param string $email Email address
     * @param string $password Email password
     * @return array Result with success status and message
     */
    public static function testConnection($imapHost, $email, $password) {
        if (!self::isImapAvailable()) {
            return [
                'success' => false,
                'message' => 'IMAP extension not available on server',
                'error_type' => 'IMAP_NOT_AVAILABLE'
            ];
        }
        
        try {
            $connection = @imap_open($imapHost, $email, $password);
            
            if (!$connection) {
                $error = imap_last_error();
                return [
                    'success' => false,
                    'message' => 'Failed to connect to email: ' . $error,
                    'error_type' => 'CONNECTION_FAILED',
                    'imap_error' => $error
                ];
            }
            
            @imap_close($connection);
            
            return [
                'success' => true,
                'message' => 'Connection successful',
                'error_type' => null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception testing connection: ' . $e->getMessage(),
                'error_type' => 'EXCEPTION',
                'exception' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle IMAP not available gracefully
     * @param string $mailType The backup source type
     * @param object $conn Database connection
     * @return array Response array for frontend
     */
    public static function handleImapNotAvailable($mailType, $conn) {
        $config = self::getEmailConfig($mailType, $conn);
        
        if (!$config) {
            return [
                'success' => false,
                'message' => "No email configuration found for $mailType backups",
                'imap_available' => false,
                'action_required' => 'CONFIGURE_EMAIL'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'IMAP extension not available on server. Please contact system administrator.',
            'imap_available' => false,
            'email_configured' => $config['email_address'],
            'imap_host' => $config['imap_host'] ?? 'Not set',
            'action_required' => 'ENABLE_IMAP',
            'instructions' => [
                'The server needs IMAP PHP extension enabled',
                'Contact hosting provider to enable php-imap extension',
                'Once enabled, email parsing will work automatically'
            ]
        ];
    }
}
