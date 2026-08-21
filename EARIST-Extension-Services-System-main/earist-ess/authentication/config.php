<?php
/**
 * EARIST Extension Service System (EESS)
 * Database Configuration File
 * 
 * Configure your database connection settings here
 * Uses PHPMailer 5.2.28 from local PHPMailer folder
 */

// Include PHPMailer (using local PHPMailer folder)
if (file_exists(__DIR__ . '/PHPMailer/PHPMailerAutoload.php')) {
    require_once __DIR__ . '/PHPMailer/PHPMailerAutoload.php';
} elseif (file_exists(__DIR__ . '/PHPMailer/class.phpmailer.php')) {
    require_once __DIR__ . '/PHPMailer/class.phpmailer.php';
    require_once __DIR__ . '/PHPMailer/class.smtp.php';
} else {
    // Fallback for composer installation
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    }
}

// Note: PHPMailer 5.2.28 doesn't use namespaces, so no use statements needed

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', ''); // Default XAMPP MySQL password is empty
define('DB_NAME', 'earist_ess');
define('DB_CHARSET', 'utf8mb4');

// System Configuration
define('SYSTEM_NAME', 'EARIST Extension Service System');
define('SYSTEM_ABBR', 'ESS');
define('INSTITUTION_NAME', 'Eulogio "Amang" Rodriguez Institute of Science and Technology');
define('BASE_URL', 'http://localhost/earist-ess/');
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 10485760); // 10MB in bytes

// ==============================================
// EMAIL CONFIGURATION (Gmail SMTP)
// Compatible with PHPMailer 5.2.28
// ==============================================

// Gmail SMTP Settings - FIXED: FROM_EMAIL must match SMTP_USERNAME for Gmail
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'authenticpsychopath@gmail.com'); // Your Gmail address
define('SMTP_PASSWORD', 'pzogqszfjcvittvz');              // Your Gmail App Password
define('FROM_EMAIL', 'authenticpsychopath@gmail.com');    // MUST be same as SMTP_USERNAME for Gmail
define('FROM_NAME', 'EARIST Extension Services');         // Your system/organization name

// Additional Email Settings
define('EMAIL_LOGO_URL', BASE_URL . 'images/earist_logo_png.png');
define('EMAIL_SUPPORT_EMAIL', 'support@earist.edu.ph');
define('EMAIL_FOOTER_TEXT', 'EARIST - Eulogio "Amang" Rodriguez Institute of Science and Technology');
define('EMAIL_CHARSET', 'UTF-8');
define('EMAIL_ENCODING', '8bit');
define('EMAIL_DEBUG_MODE', true); // Set to true for debugging - TEMPORARILY ENABLED

// Password Reset Settings
define('PASSWORD_RESET_TOKEN_EXPIRY', '+1 hour'); // Token expiry time
define('RESET_TOKEN_LENGTH', 32); // bytes (will be 64 hex characters)
define('MAX_RESET_ATTEMPTS_PER_HOUR', 3);
define('EMAIL_COOLDOWN_MINUTES', 2); // Minimum time between reset requests

// Email Rate Limiting
define('EMAIL_RATE_LIMIT', 5); // Max emails per hour per user

// Notification Settings
define('NOTIFY_PASSWORD_RESET', true);
define('NOTIFY_LOGIN_ATTEMPTS', true);
define('NOTIFY_ACCOUNT_LOCKED', true);
define('NOTIFY_PROFILE_CHANGES', true);
define('ADMIN_NOTIFICATION_EMAIL', 'admin@earist.edu.ph');

// Database Connection Class
class Database {
    private $host = DB_HOST;
    private $username = DB_USERNAME;
    private $password = DB_PASSWORD;
    private $database = DB_NAME;
    private $charset = DB_CHARSET;
    public $pdo;
    
    public function __construct() {
        $this->connect();
    }
    
    private function connect() {
        $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            return false;
        }
    }
    
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : false;
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
}

// Global database instance
$db = new Database();

// ==============================================
// EMAIL FUNCTIONS
// ==============================================

/**
 * Send email using Gmail SMTP
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param bool $isHTML Whether the email is HTML format
 * @return bool True if sent successfully, false otherwise
 */
function sendEmail($to, $subject, $body, $isHTML = true) {
    // Check if PHPMailer is available
    if (!class_exists('PHPMailer')) {
        error_log("PHPMailer is not available. Please make sure PHPMailer files are in the PHPMailer folder.");
        return false;
    }
    
    $mail = new PHPMailer(true);

    try {
        // Server settings - FIXED for PHPMailer 5.2.28
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = 'tls'; // Use string for PHPMailer 5.2.28
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = EMAIL_CHARSET;
        
        // IMPORTANT: Add SSL options for Gmail SMTP
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Enable debugging if configured
        if (EMAIL_DEBUG_MODE) {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = 'error_log';
        }

        // Recipients - FIXED: Use matching FROM_EMAIL
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(FROM_EMAIL, FROM_NAME);

        // Content
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        // Optional: Add plain text version for HTML emails
        if ($isHTML) {
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
        }

        $mail->send();
        
        // Log successful email
        if (function_exists('logActivity') && isset($_SESSION['user_id'])) {
            logActivity($_SESSION['user_id'], 'Email Sent', 'email_log', null, null, [
                'to' => $to,
                'subject' => $subject
            ]);
        }
        
        return true;
    } catch (phpmailerException $e) {
        error_log("PHPMailer Exception: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("General Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Send password reset email
 * @param string $email User's email address
 * @param string $username User's username
 * @param string $firstName User's first name
 * @param string $lastName User's last name
 * @param string $reset_token Password reset token
 * @return bool True if sent successfully, false otherwise
 */
function sendPasswordResetEmail($email, $username, $firstName, $lastName, $reset_token) {
    $reset_link = BASE_URL . "authentication/reset_password.php?token=" . $reset_token;
    $fullName = trim($firstName . ' ' . $lastName);
    
    $subject = "Password Reset Request - " . SYSTEM_ABBR;
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f4f4f4; 
            }
            .email-container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: white; 
                border-radius: 8px; 
                overflow: hidden;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            .header { 
                background: linear-gradient(135deg, #a7000e 0%, #8c000c 100%); 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 24px; 
                font-weight: 700; 
            }
            .header p { 
                margin: 5px 0 0; 
                opacity: 0.9; 
                font-size: 14px; 
            }
            .content { 
                padding: 30px 20px; 
                background: white; 
            }
            .content h2 { 
                color: #a7000e; 
                margin-bottom: 20px; 
                font-size: 20px; 
            }
            .content p { 
                margin-bottom: 15px; 
                color: #555; 
            }
            .reset-button { 
                display: inline-block; 
                padding: 15px 30px; 
                background: linear-gradient(135deg, #a7000e 0%, #8c000c 100%); 
                color: white !important; 
                text-decoration: none; 
                border-radius: 6px; 
                margin: 20px 0; 
                font-weight: 600;
                text-align: center;
            }
            .reset-button:hover { 
                background: linear-gradient(135deg, #8c000c 0%, #a7000e 100%); 
            }
            .link-section { 
                background: #f8f9fa; 
                padding: 15px; 
                border-radius: 6px; 
                margin: 20px 0; 
                border-left: 4px solid #a7000e; 
            }
            .link-section p { 
                margin: 5px 0; 
                font-size: 14px; 
            }
            .link-section a { 
                color: #a7000e; 
                word-break: break-all; 
            }
            .security-notice { 
                background: #fff3cd; 
                border: 1px solid #ffeaa7; 
                border-radius: 6px; 
                padding: 15px; 
                margin: 20px 0; 
            }
            .security-notice strong { 
                color: #856404; 
            }
            .footer { 
                padding: 20px; 
                text-align: center; 
                font-size: 12px; 
                color: #666; 
                background: #f8f9fa; 
                border-top: 1px solid #e9ecef; 
            }
            .footer p { 
                margin: 5px 0; 
            }
            @media (max-width: 600px) {
                .email-container { 
                    margin: 10px; 
                    border-radius: 0; 
                }
                .header, .content { 
                    padding: 20px 15px; 
                }
                .reset-button { 
                    display: block; 
                    text-align: center; 
                }
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h1>" . SYSTEM_ABBR . "</h1>
                <p>Password Reset Request</p>
            </div>
            <div class='content'>
                <h2>Hello " . htmlspecialchars($fullName ?: $username) . ",</h2>
                <p>We received a request to reset the password for your " . SYSTEM_ABBR . " account associated with this email address.</p>
                <p>To reset your password, click the button below:</p>
                
                <a href='" . $reset_link . "' class='reset-button'>Reset My Password</a>
                
                <div class='link-section'>
                    <p><strong>Button not working?</strong> Copy and paste this link into your browser:</p>
                    <p><a href='" . $reset_link . "'>" . $reset_link . "</a></p>
                </div>
                
                <div class='security-notice'>
                    <p><strong>⚠️ Important Security Information:</strong></p>
                    <p>• This link will expire in <strong>1 hour</strong> for your security</p>
                    <p>• If you didn't request this reset, please ignore this email</p>
                    <p>• Never share this link with anyone</p>
                </div>
                
                <p>If you continue to have problems or didn't request this password reset, please contact our support team immediately.</p>
                
                <p>Best regards,<br>
                The " . SYSTEM_ABBR . " Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " " . EMAIL_FOOTER_TEXT . "</p>
                <p>If you have questions, contact us at " . EMAIL_SUPPORT_EMAIL . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body, true);
}

/**
 * Send notification email
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Notification message
 * @param string $type Notification type (Info, Warning, Success, Error)
 * @return bool True if sent successfully, false otherwise
 */
function sendNotificationEmail($to, $subject, $message, $type = 'Info') {
    $colors = [
        'Info' => '#007bff',
        'Success' => '#28a745',
        'Warning' => '#ffc107',
        'Error' => '#dc3545'
    ];
    
    $color = $colors[$type] ?? $colors['Info'];
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
            .header { background: {$color}; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { padding: 15px; text-align: center; font-size: 12px; color: #666; background: #f8f9fa; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>" . SYSTEM_ABBR . " - {$type} Notification</h2>
            </div>
            <div class='content'>
                {$message}
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " " . EMAIL_FOOTER_TEXT . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($to, $subject, $body, true);
}

/**
 * Check if user can send email (rate limiting)
 * @param int $user_id User ID
 * @return bool True if can send, false if rate limited
 */
function canSendEmail($user_id) {
    global $db;
    
    $time_limit = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $recent_emails = $db->fetch(
        "SELECT COUNT(*) as count FROM audit_logs 
         WHERE user_id = ? AND action = 'Email Sent' AND created_at > ?",
        [$user_id, $time_limit]
    );
    
    return ($recent_emails['count'] ?? 0) < EMAIL_RATE_LIMIT;
}

/**
 * Check password reset cooldown
 * @param string $email User's email
 * @return bool True if can send, false if in cooldown
 */
function canSendPasswordReset($email) {
    global $db;
    
    $cooldown_time = date('Y-m-d H:i:s', strtotime('-' . EMAIL_COOLDOWN_MINUTES . ' minutes'));
    
    // Check if there's a recent reset request
    $recent_reset = $db->fetch(
        "SELECT COUNT(*) as count FROM password_resets pr
         JOIN users u ON pr.user_id = u.user_id
         WHERE u.email = ? AND pr.created_at > ?",
        [$email, $cooldown_time]
    );
    
    return ($recent_reset['count'] ?? 0) == 0;
}

// Authentication Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Verify user login credentials
 * @param string $email User's email
 * @param string $password User's password (plain text)
 * @return array|false User data if login successful, false otherwise
 */
function verifyLogin($email, $password) {
    global $db;
    
    try {
        // Query to get user by email and active status
        $sql = "SELECT user_id, username, email, password, role, first_name, last_name, department, status 
                FROM users 
                WHERE email = ? AND status = 'active'";
        
        $user = $db->fetch($sql, [$email]);
        
        if ($user && password_verify($password, $user['password'])) {
            // Remove sensitive data before returning
            unset($user['password']);
            unset($user['status']);
            return $user;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Login verification error: " . $e->getMessage());
        return false;
    }
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function hasRole($required_roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_role = $_SESSION['user_role'];
    if (is_array($required_roles)) {
        return in_array($user_role, $required_roles);
    }
    return $user_role === $required_roles;
}

function requireRole($required_roles) {
    if (!hasRole($required_roles)) {
        header('HTTP/1.1 403 Forbidden');
        die('Access denied. Insufficient permissions.');
    }
}

// Utility Functions
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Format date with proper null/empty checking
 * @param string|null $date The date to format
 * @param string $format The desired date format
 * @return string Formatted date or default value
 */
function formatDate($date, $format = 'M d, Y') {
    // Check if date is null, empty, or invalid
    if (empty($date) || is_null($date)) {
        return 'N/A';
    }
    
    // Handle common invalid date values
    if ($date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    // Try to convert the date
    $timestamp = strtotime($date);
    
    // Check if strtotime failed
    if ($timestamp === false) {
        return 'Invalid Date';
    }
    
    // Format and return the date
    return date($format, $timestamp);
}

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// File Upload Functions
function uploadFile($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'], $upload_dir = 'uploads/') {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file parameters.'];
    }
    
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'message' => 'No file sent.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'message' => 'Exceeded filesize limit.'];
        default:
            return ['success' => false, 'message' => 'Unknown upload error.'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'Exceeded filesize limit.'];
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_types)) {
        return ['success' => false, 'message' => 'File type not allowed.'];
    }
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $filename = sprintf('%s_%s.%s', uniqid(), date('Y-m-d_H-i-s'), $extension);
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Failed to move uploaded file.'];
    }
    
    return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
}

// Log Functions
function logActivity($user_id, $action, $table_affected = null, $record_id = null, $old_values = null, $new_values = null) {
    global $db;
    
    $sql = "INSERT INTO audit_logs (user_id, action, table_affected, record_id, old_values, new_values, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $params = [
        $user_id,
        $action,
        $table_affected,
        $record_id,
        $old_values ? json_encode($old_values) : null,
        $new_values ? json_encode($new_values) : null,
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ];
    
    $db->query($sql, $params);
}

// Notification Functions
function addNotification($user_id, $title, $message, $type = 'Info', $link_url = null) {
    global $db;
    
    $sql = "INSERT INTO notifications (user_id, title, message, type, link_url) VALUES (?, ?, ?, ?, ?)";
    $result = $db->query($sql, [$user_id, $title, $message, $type, $link_url]);
    
    // Send email notification if enabled
    if ($result && NOTIFY_PASSWORD_RESET && $type === 'Warning') {
        $user = $db->fetch("SELECT email FROM users WHERE user_id = ?", [$user_id]);
        if ($user) {
            sendNotificationEmail($user['email'], $title, $message, $type);
        }
    }
    
    return $result;
}

function getUnreadNotifications($user_id) {
    global $db;
    
    $sql = "SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC";
    return $db->fetchAll($sql, [$user_id]);
}

// Error Handling
function handleError($message, $redirect = false) {
    error_log($message);
    if ($redirect) {
        $_SESSION['error_message'] = $message;
        header('Location: ' . $redirect);
        exit();
    }
    return false;
}

// Success Message
function setSuccessMessage($message) {
    $_SESSION['success_message'] = $message;
}

function setErrorMessage($message) {
    $_SESSION['error_message'] = $message;
}

function getFlashMessage($type) {
    if (isset($_SESSION[$type . '_message'])) {
        $message = $_SESSION[$type . '_message'];
        unset($_SESSION[$type . '_message']);
        return $message;
    }
    return null;
}

// Pagination Function
function paginate($sql, $params, $page = 1, $per_page = 10) {
    global $db;
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM ($sql) as count_table";
    $total = $db->fetch($count_sql, $params)['total'];
    
    // Calculate pagination
    $total_pages = ceil($total / $per_page);
    $offset = ($page - 1) * $per_page;
    
    // Get paginated results
    $paginated_sql = $sql . " LIMIT $per_page OFFSET $offset";
    $results = $db->fetchAll($paginated_sql, $params);
    
    return [
        'data' => $results,
        'total' => $total,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'per_page' => $per_page,
        'has_next' => $page < $total_pages,
        'has_prev' => $page > 1
    ];
}

// Date utility functions
/**
 * Check if a date is valid
 * @param string $date Date to validate
 * @return bool True if valid, false otherwise
 */
function isValidDate($date) {
    if (empty($date) || is_null($date)) {
        return false;
    }
    
    if ($date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return false;
    }
    
    return strtotime($date) !== false;
}

/**
 * Format date with timezone consideration
 * @param string $date Date to format
 * @param string $format Desired format
 * @param string $timezone Timezone to use
 * @return string Formatted date
 */
function formatDateWithTimezone($date, $format = 'M d, Y', $timezone = 'Asia/Manila') {
    if (!isValidDate($date)) {
        return 'N/A';
    }
    
    try {
        $dt = new DateTime($date);
        $dt->setTimezone(new DateTimeZone($timezone));
        return $dt->format($format);
    } catch (Exception $e) {
        error_log("Date formatting error: " . $e->getMessage());
        return 'Invalid Date';
    }
}

/**
 * Get relative time (e.g., "2 hours ago")
 * @param string $date Date to compare
 * @return string Relative time
 */
function getRelativeTime($date) {
    if (!isValidDate($date)) {
        return 'Unknown';
    }
    
    $timestamp = strtotime($date);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return 'Just now';
    } elseif ($difference < 3600) {
        $minutes = floor($difference / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($difference < 2592000) {
        $days = floor($difference / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($date);
    }
}

// System Status Check
function checkSystemStatus() {
    global $db;
    
    try {
        $db->fetch("SELECT 1");
        $status = ['status' => 'online', 'database' => 'connected'];
        
        // Check email functionality
        if (class_exists('PHPMailer')) {
            $status['email'] = 'available';
        } else {
            $status['email'] = 'unavailable - PHPMailer not found';
        }
        
        return $status;
    } catch (Exception $e) {
        return ['status' => 'error', 'database' => 'disconnected', 'error' => $e->getMessage()];
    }
}

// Initialize password_resets table
function initializePasswordResetTable() {
    global $db;
    
    $sql = "CREATE TABLE IF NOT EXISTS password_resets (
        user_id INT PRIMARY KEY,
        token VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )";
    
    $db->query($sql);
}

// Initialize system
if (!defined('SYSTEM_INITIALIZED')) {
    define('SYSTEM_INITIALIZED', true);
    
    // Set timezone
    date_default_timezone_set('Asia/Manila');
    
    // Error reporting (disable in production)
    if ($_SERVER['SERVER_NAME'] === 'localhost') {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    } else {
        error_reporting(0);
        ini_set('display_errors', 0);
    }
    
    // Initialize required database tables
    initializePasswordResetTable();
    
    // Check email configuration on every page load (only in debug mode)
    if (EMAIL_DEBUG_MODE && isset($_GET['check_email'])) {
        echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;'>";
        echo "<strong>Email Configuration Check:</strong><br>";
        echo "PHPMailer Class: " . (class_exists('PHPMailer') ? '✅ Found' : '❌ Not Found') . "<br>";
        echo "SMTP Username: " . SMTP_USERNAME . "<br>";
        echo "FROM Email: " . FROM_EMAIL . "<br>";
        echo "Match Status: " . (FROM_EMAIL === SMTP_USERNAME ? '✅ Match' : '❌ Mismatch') . "<br>";
        echo "</div>";
    }
}
?>