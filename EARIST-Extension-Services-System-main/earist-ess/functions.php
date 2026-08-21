<?php
/**
 * EARIST Extension Service System (EESS)
 * Common Functions Library
 * 
 * Improved version with security enhancements, bug fixes, and better organization
 */

// Prevent direct access
if (!defined('SYSTEM_INITIALIZED')) {
    die('Direct access not allowed');
}

/**
 * Hash password using PHP's password_hash function with cost option
 */
function hashPassword($password, $options = []) {
    return password_hash($password, PASSWORD_DEFAULT, $options);
}

/**
 * Verify password against hash
 */
function verifyPassword($password, $hash) {
    return password_verify(trim($password), $hash);
}

/**
 * Verify user login credentials with enhanced security
 */
function verifyLogin($email, $password) {
    global $db;
    
    try {
        // Sanitize and validate input
        $email = trim($email);
        if (empty($email) || empty($password)) {
            return false;
        }

        // First try to find active user by email or username
        $user = $db->fetch(
            "SELECT * FROM users WHERE (email = ? OR username = ?) AND status = 'Active' LIMIT 1", 
            [$email, $email]
        );
        
        if (!$user) {
            // Delay to prevent timing attacks
            usleep(rand(200000, 500000));
            return false;
        }
        
        // Clean passwords
        $storedPassword = trim($user['password']);
        $inputPassword = trim($password);
        
        // Debug logging if enabled
        if (defined('DEBUG') && DEBUG) {
            error_log("Login attempt for email: $email");
        }
        
        // Check password
        if (strpos($storedPassword, '$2y$') === 0 || strpos($storedPassword, '$2a$') === 0) {
            // Modern bcrypt hash
            $verified = password_verify($inputPassword, $storedPassword);
            
            // Check if rehash needed (for when PHP updates default algorithm)
            if ($verified && password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
                $newHash = hashPassword($inputPassword);
                $db->query(
                    "UPDATE users SET password = ? WHERE user_id = ?",
                    [$newHash, $user['user_id']]
                );
            }
        } else {
            // Legacy plain text comparison
            $verified = hash_equals($storedPassword, $inputPassword);
            
            // If verified, upgrade to hashed password
            if ($verified) {
                $hashedPassword = hashPassword($inputPassword);
                $db->query(
                    "UPDATE users SET password = ? WHERE user_id = ?",
                    [$hashedPassword, $user['user_id']]
                );
            }
        }
        
        return $verified ? $user : false;
    } catch (Exception $e) {
        error_log("Login verification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Sanitize input data with improved XSS protection
 */
function sanitizeInput($data, $stripTags = true) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    
    if ($stripTags) {
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    return $data;
}

/**
 * Validate email format with DNS check
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && 
           checkdnsrr(explode('@', $email)[1] ?? '', 'MX');
}

/**
 * Check if user is logged in with session validation
 */
function isLoggedIn() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    
    // Additional session validation
    if (!isset($_SESSION['user_agent']) || 
        $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        return false;
    }
    
    if (!isset($_SESSION['ip_address']) || 
        $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        return false;
    }
    
    return true;
}

/**
 * Check if user has required role with hierarchy support
 */
function hasRole($required_roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Make sure $required_roles is an array
    if (!is_array($required_roles)) {
        $required_roles = [$required_roles];
    }
    
    // Get user role with fallback
    $user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
    
    // Define role hierarchy
    $hierarchy = [
        'Admin' => 100,
        'Manager' => 80,
        'Editor' => 60,
        'User' => 40,
        'Guest' => 20
    ];
    
    // Admin has full access
    if ($user_role === 'Admin') {
        return true;
    }
    
    // Check if user has sufficient privileges
    $user_level = $hierarchy[$user_role] ?? 0;
    foreach ($required_roles as $role) {
        $required_level = $hierarchy[$role] ?? 0;
        if ($user_level >= $required_level) {
            return true;
        }
    }
    
    return false;
}

/**
 * Redirect to login page if not authenticated with return URL
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['return_url'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . BASE_URL . 'authentication/login.php');
        exit();
    }
}

/**
 * Require specific role or redirect with message
 */
function requireRole($required_role) {
    requireLogin();
    
    if (!hasRole($required_role)) {
        setFlashMessage('error', 'You do not have permission to access this page');
        header('Location: ' . BASE_URL . 'access_denied.php');
        exit();
    }
}

/**
 * Get current user information with caching
 */
function getCurrentUser() {
    global $db;
    
    static $currentUser = null;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    if ($currentUser === null) {
        $currentUser = $db->fetch(
            "SELECT * FROM users WHERE user_id = ? LIMIT 1",
            [$_SESSION['user_id']]
        );
    }
    
    return $currentUser;
}

/**
 * Flash message system with multiple messages per type
 */
function setFlashMessage($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    
    if (!isset($_SESSION['flash_messages'][$type])) {
        $_SESSION['flash_messages'][$type] = [];
    }
    
    $_SESSION['flash_messages'][$type][] = $message;
}

/**
 * Get and clear flash messages of a type
 */
function getFlashMessage($type) {
    if (isset($_SESSION['flash_messages'], $_SESSION['flash_messages'][$type])) {
        $messages = $_SESSION['flash_messages'][$type];
        unset($_SESSION['flash_messages'][$type]);
        return $messages;
    }
    return [];
}

/**
 * Check if flash messages exist for a type
 */
function hasFlashMessage($type) {
    return !empty($_SESSION['flash_messages'][$type]);
}

/**
 * Clear all flash messages
 */
function clearFlashMessages() {
    unset($_SESSION['flash_messages']);
}

/**
 * Set success message
 */
function setSuccessMessage($message) {
    setFlashMessage('success', $message);
}

/**
 * Set error message
 */
function setErrorMessage($message) {
    setFlashMessage('error', $message);
}

/**
 * Format date with timezone support
 */
function formatDate($date, $format = 'F j, Y', $timezone = null) {
    if (empty($date) || in_array($date, ['0000-00-00', '0000-00-00 00:00:00'])) {
        return 'Not set';
    }
    
    try {
        $dt = new DateTime($date);
        if ($timezone) {
            $dt->setTimezone(new DateTimeZone($timezone));
        }
        return $dt->format($format);
    } catch (Exception $e) {
        error_log("Date formatting error: " . $e->getMessage());
        return 'Invalid date';
    }
}

/**
 * Format date with time
 */
function formatDateTime($datetime, $format = 'F j, Y g:i A') {
    return formatDate($datetime, $format);
}

/**
 * Generate cryptographically secure random string
 */
function generateRandomString($length = 32) {
    try {
        return bin2hex(random_bytes($length / 2));
    } catch (Exception $e) {
        error_log("Random string generation failed: " . $e->getMessage());
        // Fallback (less secure)
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}

/**
 * Secure file upload with improved validation
 */
function uploadFile($file, $upload_dir = 'uploads/', $allowed_types = []) {
    // Basic validation
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file parameters'];
    }
    
    // Check for upload errors
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'message' => 'No file selected'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'message' => 'File size exceeds limit'];
        default:
            return ['success' => false, 'message' => 'File upload error'];
    }
    
    // Check file size
    if ($file['size'] > (defined('MAX_FILE_SIZE') ? MAX_FILE_SIZE : 2097152)) { // Default 2MB
        return ['success' => false, 'message' => 'File size exceeds limit'];
    }
    
    // Default allowed types
    if (empty($allowed_types)) {
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
    }
    
    // Get file info
    $file_name = basename($file['name']);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Validate extension
    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    // Create secure filename
    $new_filename = sprintf(
        '%s_%s.%s',
        bin2hex(random_bytes(8)),
        time(),
        $file_ext
    );
    
    // Ensure upload directory exists - FIXED: Added missing closing parenthesis
    $upload_dir = rtrim($upload_dir, '/') . '/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create upload directory'];
        }
    }
    
    // Verify file is actually an uploaded file
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }
    
    // Move file with error handling
    $upload_path = $upload_dir . $new_filename;
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return [
            'success' => true,
            'filename' => $new_filename,
            'path' => $upload_path,
            'original_name' => $file_name,
            'extension' => $file_ext,
            'size' => $file['size']
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}

/**
 * Delete file safely with validation
 */
function deleteFile($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }
    
    // Prevent directory traversal
    $real_path = realpath($file_path);
    if ($real_path === false || strpos($real_path, realpath(BASE_PATH)) !== 0) {
        return false;
    }
    
    return @unlink($real_path);
}

/**
 * Log activity with IP and user agent
 */
function logActivity($user_id, $action, $description = '', $target_id = null) {
    global $db;
    
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $db->query(
            "INSERT INTO audit_logs 
            (user_id, action, table_affected, record_id, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$user_id, $action, 'general', $target_id, $ip_address, $user_agent]
        );
        return true;
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Add notification with validation
 */
function addNotification($user_id, $title, $message, $type = 'Info', $link_url = null) {
    global $db;
    
    if (!in_array($type, ['Info', 'Success', 'Warning', 'Error'])) {
        $type = 'Info';
    }
    
    try {
        $db->query(
            "INSERT INTO notifications 
            (user_id, title, message, type, link_url, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())",
            [$user_id, $title, $message, $type, $link_url]
        );
        return true;
    } catch (Exception $e) {
        error_log("Failed to add notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notifications with limit
 */
function getUnreadNotifications($user_id, $limit = 10) {
    global $db;
    
    try {
        return $db->fetchAll(
            "SELECT * FROM notifications 
            WHERE user_id = ? AND is_read = 0 
            ORDER BY created_at DESC LIMIT ?",
            [$user_id, $limit]
        );
    } catch (Exception $e) {
        error_log("Failed to get notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Generate CSRF token with per-request tokens
 */
function generateCSRFToken($form_name = 'default') {
    if (!isset($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }
    
    if (!isset($_SESSION['csrf_tokens'][$form_name])) {
        $_SESSION['csrf_tokens'][$form_name] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_tokens'][$form_name];
}

/**
 * Verify CSRF token with timing-safe comparison
 */
function verifyCSRFToken($token, $form_name = 'default') {
    if (!isset($_SESSION['csrf_tokens'][$form_name])) {
        return false;
    }
    
    $valid = hash_equals($_SESSION['csrf_tokens'][$form_name], $token);
    unset($_SESSION['csrf_tokens'][$form_name]);
    return $valid;
}

/**
 * Get pagination data with validation
 */
function getPagination($total_records, $records_per_page = 10, $current_page = 1) {
    $total_records = max(0, (int)$total_records);
    $records_per_page = max(1, (int)$records_per_page);
    $current_page = max(1, (int)$current_page);
    
    $total_pages = max(1, ceil($total_records / $records_per_page));
    $current_page = min($current_page, $total_pages);
    $offset = ($current_page - 1) * $records_per_page;
    
    return [
        'total_records' => $total_records,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'records_per_page' => $records_per_page,
        'offset' => $offset,
        'has_previous' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}

/**
 * Format currency with localization support
 */
function formatCurrency($amount, $currency = 'PHP', $locale = 'en_PH') {
    $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
    return $formatter->formatCurrency($amount, $currency);
}

/**
 * Truncate text with UTF-8 support
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length, 'UTF-8') . $suffix;
}

/**
 * Debug function with JSON support
 */
function debug($data, $label = '', $return = false) {
    if (!defined('DEBUG') || !DEBUG) {
        return '';
    }
    
    $output = '<pre style="background: #f4f4f4; padding: 10px; margin: 10px; border: 1px solid #ddd;">';
    if ($label) {
        $output .= '<strong>' . htmlspecialchars($label) . ':</strong><br>';
    }
    
    if (is_string($data)) {
        $output .= htmlspecialchars($data);
    } elseif (is_bool($data)) {
        $output .= $data ? 'true' : 'false';
    } else {
        $output .= htmlspecialchars(print_r($data, true));
    }
    
    $output .= '</pre>';
    
    if ($return) {
        return $output;
    }
    
    echo $output;
}

/**
 * Secure redirect with header injection protection
 */
function safeRedirect($url, $statusCode = 302) {
    // Remove newlines to prevent header injection
    $url = str_replace(["\n", "\r"], '', $url);
    
    // Validate URL
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        // Handle relative URLs
        if (strpos($url, '/') === 0) {
            $url = BASE_URL . ltrim($url, '/');
        } else {
            $url = BASE_URL;
        }
    }
    
    header("Location: $url", true, $statusCode);
    exit();
}

/**
 * Validate and sanitize URL
 */
function sanitizeUrl($url) {
    $url = trim($url);
    $url = filter_var($url, FILTER_SANITIZE_URL);
    
    if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
        $url = 'http://' . $url;
    }
    
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : false;
}

/**
 * Get client IP address with proxy support
 */
function getClientIp() {
    $ip_keys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($ip_keys as $key) {
        if (isset($_SERVER[$key])) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Generate a v4 UUID
 */
function generateUUID() {
    try {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    } catch (Exception $e) {
        error_log("UUID generation failed: " . $e->getMessage());
        return uniqid('', true);
    }
}

/**
 * Encrypt data with OpenSSL
 */
function encryptData($data, $key) {
    $iv_length = openssl_cipher_iv_length('AES-256-CBC');
    $iv = openssl_random_pseudo_bytes($iv_length);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt data with OpenSSL
 */
function decryptData($data, $key) {
    $data = base64_decode($data);
    $iv_length = openssl_cipher_iv_length('AES-256-CBC');
    $iv = substr($data, 0, $iv_length);
    $data = substr($data, $iv_length);
    return openssl_decrypt($data, 'AES-256-CBC', $key, 0, $iv);
}