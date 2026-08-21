<?php
/**
 * EARIST Extension Service System (EESS)
 * Installation Wizard
 * 
 * This script helps with the initial setup of the system
 */

// Check if system is already installed
if (file_exists('config.php') && !isset($_GET['force'])) {
    $config_content = file_get_contents('config.php');
    if (strpos($config_content, 'SYSTEM_INITIALIZED') !== false && !isset($_GET['reinstall'])) {
        header('Location: index.php');
        exit();
    }
}

$step = $_GET['step'] ?? 1;
$errors = [];
$success = [];

// Step processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2: // Database connection test
            $db_host = $_POST['db_host'];
            $db_username = $_POST['db_username'];
            $db_password = $_POST['db_password'];
            $db_name = $_POST['db_name'];
            
            try {
                $pdo = new PDO("mysql:host={$db_host};charset=utf8mb4", $db_username, $db_password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Check if database exists, if not create it
                $stmt = $pdo->query("SHOW DATABASES LIKE '{$db_name}'");
                if ($stmt->rowCount() == 0) {
                    $pdo->exec("CREATE DATABASE `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
                    $success[] = "Database '{$db_name}' created successfully.";
                }
                
                // Store database config in session
                session_start();
                $_SESSION['install_db'] = [
                    'host' => $db_host,
                    'username' => $db_username,
                    'password' => $db_password,
                    'database' => $db_name
                ];
                
                $success[] = "Database connection successful!";
                header('Location: install.php?step=3');
                exit();
                
            } catch (PDOException $e) {
                $errors[] = "Database connection failed: " . $e->getMessage();
            }
            break;
            
        case 3: // Create tables and import data
            session_start();
            if (!isset($_SESSION['install_db'])) {
                header('Location: install.php?step=2');
                exit();
            }
            
            $db_config = $_SESSION['install_db'];
            
            try {
                $pdo = new PDO("mysql:host={$db_config['host']};dbname={$db_config['database']};charset=utf8mb4", 
                              $db_config['username'], $db_config['password']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Read and execute SQL file
                if (file_exists('database_schema.sql')) {
                    $sql = file_get_contents('database_schema.sql');
                    $statements = explode(';', $sql);
                    
                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        if (!empty($statement)) {
                            $pdo->exec($statement);
                        }
                    }
                    
                    $success[] = "Database tables created successfully!";
                    header('Location: install.php?step=4');
                    exit();
                } else {
                    $errors[] = "Database schema file not found!";
                }
                
            } catch (PDOException $e) {
                $errors[] = "Error creating tables: " . $e->getMessage();
            }
            break;
            
        case 4: // Create config file
            session_start();
            if (!isset($_SESSION['install_db'])) {
                header('Location: install.php?step=2');
                exit();
            }
            
            $db_config = $_SESSION['install_db'];
            $system_name = $_POST['system_name'] ?? 'EARIST Extension Service System';
            $system_abbr = $_POST['system_abbr'] ?? 'EESS';
            $institution = $_POST['institution'] ?? 'Eulogio "Amang" Rodriguez Institute of Science and Technology';
            $base_url = $_POST['base_url'] ?? 'http://localhost/earist-ess/';
            
            $config_content = generateConfigFile($db_config, $system_name, $system_abbr, $institution, $base_url);
            
            if (file_put_contents('config.php', $config_content)) {
                // Create upload directories
                $directories = ['uploads', 'uploads/profiles', 'uploads/documents', 'uploads/images', 'backups'];
                foreach ($directories as $dir) {
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                }
                
                // Clear installation session
                unset($_SESSION['install_db']);
                
                $success[] = "Configuration file created successfully!";
                header('Location: install.php?step=5');
                exit();
            } else {
                $errors[] = "Failed to create configuration file. Please check file permissions.";
            }
            break;
    }
}

function generateConfigFile($db_config, $system_name, $system_abbr, $institution, $base_url) {
    return "<?php
/**
 * EARIST Extension Service System (EESS)
 * Database Configuration File
 * Generated by Installation Wizard on " . date('Y-m-d H:i:s') . "
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_HOST', '{$db_config['host']}');
define('DB_USERNAME', '{$db_config['username']}');
define('DB_PASSWORD', '{$db_config['password']}');
define('DB_NAME', '{$db_config['database']}');
define('DB_CHARSET', 'utf8mb4');

// System Configuration
define('SYSTEM_NAME', '{$system_name}');
define('SYSTEM_ABBR', '{$system_abbr}');
define('INSTITUTION_NAME', '{$institution}');
define('BASE_URL', '{$base_url}');
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 10485760); // 10MB in bytes

// Database Connection Class
class Database {
    private \$host = DB_HOST;
    private \$username = DB_USERNAME;
    private \$password = DB_PASSWORD;
    private \$database = DB_NAME;
    private \$charset = DB_CHARSET;
    public \$pdo;
    
    public function __construct() {
        \$this->connect();
    }
    
    private function connect() {
        \$dsn = \"mysql:host={\$this->host};dbname={\$this->database};charset={\$this->charset}\";
        \$options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            \$this->pdo = new PDO(\$dsn, \$this->username, \$this->password, \$options);
        } catch (PDOException \$e) {
            die(\"Database connection failed: \" . \$e->getMessage());
        }
    }
    
    public function query(\$sql, \$params = []) {
        try {
            \$stmt = \$this->pdo->prepare(\$sql);
            \$stmt->execute(\$params);
            return \$stmt;
        } catch (PDOException \$e) {
            error_log(\"Database query error: \" . \$e->getMessage());
            return false;
        }
    }
    
    public function fetch(\$sql, \$params = []) {
        \$stmt = \$this->query(\$sql, \$params);
        return \$stmt ? \$stmt->fetch() : false;
    }
    
    public function fetchAll(\$sql, \$params = []) {
        \$stmt = \$this->query(\$sql, \$params);
        return \$stmt ? \$stmt->fetchAll() : [];
    }
    
    public function lastInsertId() {
        return \$this->pdo->lastInsertId();
    }
}

// Global database instance
\$db = new Database();

// Authentication Functions
function isLoggedIn() {
    return isset(\$_SESSION['user_id']) && !empty(\$_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

function hasRole(\$required_roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    \$user_role = \$_SESSION['user_role'];
    if (is_array(\$required_roles)) {
        return in_array(\$user_role, \$required_roles);
    }
    return \$user_role === \$required_roles;
}

function requireRole(\$required_roles) {
    if (!hasRole(\$required_roles)) {
        header('HTTP/1.1 403 Forbidden');
        die('Access denied. Insufficient permissions.');
    }
}

// Utility Functions
function sanitizeInput(\$input) {
    return htmlspecialchars(strip_tags(trim(\$input)), ENT_QUOTES, 'UTF-8');
}

function formatDate(\$date, \$format = 'M d, Y') {
    return date(\$format, strtotime(\$date));
}

function formatCurrency(\$amount) {
    return '₱' . number_format(\$amount, 2);
}

function generateCSRFToken() {
    if (!isset(\$_SESSION['csrf_token'])) {
        \$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return \$_SESSION['csrf_token'];
}

function validateCSRFToken(\$token) {
    return isset(\$_SESSION['csrf_token']) && hash_equals(\$_SESSION['csrf_token'], \$token);
}

// File Upload Functions
function uploadFile(\$file, \$allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'], \$upload_dir = 'uploads/') {
    if (!isset(\$file['error']) || is_array(\$file['error'])) {
        return ['success' => false, 'message' => 'Invalid file parameters.'];
    }
    
    switch (\$file['error']) {
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
    
    if (\$file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'Exceeded filesize limit.'];
    }
    
    \$finfo = new finfo(FILEINFO_MIME_TYPE);
    \$mime_type = \$finfo->file(\$file['tmp_name']);
    
    \$extension = strtolower(pathinfo(\$file['name'], PATHINFO_EXTENSION));
    if (!in_array(\$extension, \$allowed_types)) {
        return ['success' => false, 'message' => 'File type not allowed.'];
    }
    
    if (!is_dir(\$upload_dir)) {
        mkdir(\$upload_dir, 0755, true);
    }
    
    \$filename = sprintf('%s_%s.%s', uniqid(), date('Y-m-d_H-i-s'), \$extension);
    \$filepath = \$upload_dir . \$filename;
    
    if (!move_uploaded_file(\$file['tmp_name'], \$filepath)) {
        return ['success' => false, 'message' => 'Failed to move uploaded file.'];
    }
    
    return ['success' => true, 'filename' => \$filename, 'filepath' => \$filepath];
}

// Log Functions
function logActivity(\$user_id, \$action, \$table_affected = null, \$record_id = null, \$old_values = null, \$new_values = null) {
    global \$db;
    
    \$sql = \"INSERT INTO audit_logs (user_id, action, table_affected, record_id, old_values, new_values, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)\";
    
    \$params = [
        \$user_id,
        \$action,
        \$table_affected,
        \$record_id,
        \$old_values ? json_encode(\$old_values) : null,
        \$new_values ? json_encode(\$new_values) : null,
        \$_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        \$_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ];
    
    \$db->query(\$sql, \$params);
}

// Notification Functions
function addNotification(\$user_id, \$title, \$message, \$type = 'Info', \$link_url = null) {
    global \$db;
    
    \$sql = \"INSERT INTO notifications (user_id, title, message, type, link_url) VALUES (?, ?, ?, ?, ?)\";
    return \$db->query(\$sql, [\$user_id, \$title, \$message, \$type, \$link_url]);
}

function getUnreadNotifications(\$user_id) {
    global \$db;
    
    \$sql = \"SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC\";
    return \$db->fetchAll(\$sql, [\$user_id]);
}

// Error Handling
function handleError(\$message, \$redirect = false) {
    error_log(\$message);
    if (\$redirect) {
        \$_SESSION['error_message'] = \$message;
        header('Location: ' . \$redirect);
        exit();
    }
    return false;
}

// Success Message
function setSuccessMessage(\$message) {
    \$_SESSION['success_message'] = \$message;
}

function setErrorMessage(\$message) {
    \$_SESSION['error_message'] = \$message;
}

function getFlashMessage(\$type) {
    if (isset(\$_SESSION[\$type . '_message'])) {
        \$message = \$_SESSION[\$type . '_message'];
        unset(\$_SESSION[\$type . '_message']);
        return \$message;
    }
    return null;
}

// Pagination Function
function paginate(\$sql, \$params, \$page = 1, \$per_page = 10) {
    global \$db;
    
    // Get total count
    \$count_sql = \"SELECT COUNT(*) as total FROM (\$sql) as count_table\";
    \$total = \$db->fetch(\$count_sql, \$params)['total'];
    
    // Calculate pagination
    \$total_pages = ceil(\$total / \$per_page);
    \$offset = (\$page - 1) * \$per_page;
    
    // Get paginated results
    \$paginated_sql = \$sql . \" LIMIT \$per_page OFFSET \$offset\";
    \$results = \$db->fetchAll(\$paginated_sql, \$params);
    
    return [
        'data' => \$results,
        'total' => \$total,
        'current_page' => \$page,
        'total_pages' => \$total_pages,
        'per_page' => \$per_page,
        'has_next' => \$page < \$total_pages,
        'has_prev' => \$page > 1
    ];
}

// System Status Check
function checkSystemStatus() {
    global \$db;
    
    try {
        \$db->fetch(\"SELECT 1\");
        return ['status' => 'online', 'database' => 'connected'];
    } catch (Exception \$e) {
        return ['status' => 'error', 'database' => 'disconnected', 'error' => \$e->getMessage()];
    }
}

// Initialize system
if (!defined('SYSTEM_INITIALIZED')) {
    define('SYSTEM_INITIALIZED', true);
    
    // Set timezone
    date_default_timezone_set('Asia/Manila');
    
    // Error reporting (disable in production)
    if (\$_SERVER['SERVER_NAME'] === 'localhost') {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    } else {
        error_reporting(0);
        ini_set('display_errors', 0);
    }
}
?>";
}

function checkSystemRequirements() {
    $requirements = [
        'PHP Version' => [
            'required' => '8.0',
            'current' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '8.0', '>=')
        ],
        'MySQL Extension' => [
            'required' => 'Required',
            'current' => extension_loaded('mysql') || extension_loaded('mysqli') || extension_loaded('pdo_mysql') ? 'Available' : 'Not Available',
            'status' => extension_loaded('mysql') || extension_loaded('mysqli') || extension_loaded('pdo_mysql')
        ],
        'PDO Extension' => [
            'required' => 'Required',
            'current' => extension_loaded('pdo') ? 'Available' : 'Not Available',
            'status' => extension_loaded('pdo')
        ],
        'GD Extension' => [
            'required' => 'Required',
            'current' => extension_loaded('gd') ? 'Available' : 'Not Available',
            'status' => extension_loaded('gd')
        ],
        'JSON Extension' => [
            'required' => 'Required',
            'current' => extension_loaded('json') ? 'Available' : 'Not Available',
            'status' => extension_loaded('json')
        ],
        'File Uploads' => [
            'required' => 'Enabled',
            'current' => ini_get('file_uploads') ? 'Enabled' : 'Disabled',
            'status' => ini_get('file_uploads')
        ],
        'Write Permission (uploads)' => [
            'required' => 'Writable',
            'current' => is_writable('.') ? 'Writable' : 'Not Writable',
            'status' => is_writable('.')
        ]
    ];
    
    return $requirements;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EARIST EESS - Installation Wizard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --earist-blue: #1e40af;
            --earist-green: #059669;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--earist-blue) 0%, var(--earist-green) 100%);
            min-height: 100vh;
            padding: 20px 0;
        }

        .install-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .install-header {
            background: linear-gradient(135deg, var(--earist-blue), var(--earist-green));
            color: white;
            padding: 30px;
            text-align: center;
        }

        .install-logo {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .install-logo i {
            font-size: 25px;
            color: var(--earist-blue);
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 30px 0;
        }

        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: 600;
            position: relative;
        }

        .step.active {
            background: var(--earist-blue);
            color: white;
        }

        .step.completed {
            background: var(--earist-green);
            color: white;
        }

        .step::after {
            content: '';
            position: absolute;
            right: -25px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 2px;
            background: #e2e8f0;
        }

        .step:last-child::after {
            display: none;
        }

        .install-content {
            padding: 40px;
        }

        .requirement-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .requirement-item:last-child {
            border-bottom: none;
        }

        .status-pass {
            color: #059669;
        }

        .status-fail {
            color: #dc2626;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--earist-blue), var(--earist-green));
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(30, 64, 175, 0.3);
            color: white;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <div class="install-logo">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h1>EARIST EESS Installation</h1>
            <p>Extension Service System Setup Wizard</p>
        </div>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step <?= $step >= 1 ? ($step == 1 ? 'active' : 'completed') : '' ?>">1</div>
            <div class="step <?= $step >= 2 ? ($step == 2 ? 'active' : 'completed') : '' ?>">2</div>
            <div class="step <?= $step >= 3 ? ($step == 3 ? 'active' : 'completed') : '' ?>">3</div>
            <div class="step <?= $step >= 4 ? ($step == 4 ? 'active' : 'completed') : '' ?>">4</div>
            <div class="step <?= $step >= 5 ? ($step == 5 ? 'active' : 'completed') : '' ?>">5</div>
        </div>

        <div class="install-content">
            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h6><i class="fas fa-exclamation-triangle"></i> Installation Errors:</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Success Messages -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <h6><i class="fas fa-check-circle"></i> Success:</h6>
                    <ul class="mb-0">
                        <?php foreach ($success as $message): ?>
                            <li><?= htmlspecialchars($message) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Step Content -->
            <?php switch ($step): 
                case 1: // System Requirements ?>
                    <h3><i class="fas fa-server text-primary"></i> System Requirements Check</h3>
                    <p class="text-muted">Please ensure your server meets the following requirements:</p>

                    <?php $requirements = checkSystemRequirements(); ?>
                    <?php $all_passed = true; ?>

                    <?php foreach ($requirements as $name => $req): ?>
                        <div class="requirement-item">
                            <div>
                                <strong><?= $name ?></strong><br>
                                <small class="text-muted">Required: <?= $req['required'] ?></small>
                            </div>
                            <div class="text-end">
                                <span class="<?= $req['status'] ? 'status-pass' : 'status-fail' ?>">
                                    <i class="fas fa-<?= $req['status'] ? 'check-circle' : 'times-circle' ?>"></i>
                                    <?= $req['current'] ?>
                                </span>
                            </div>
                        </div>
                        <?php if (!$req['status']) $all_passed = false; ?>
                    <?php endforeach; ?>

                    <div class="text-center mt-4">
                        <?php if ($all_passed): ?>
                            <a href="install.php?step=2" class="btn btn-primary-custom">
                                <i class="fas fa-arrow-right"></i> Continue to Database Setup
                            </a>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Please fix the failed requirements before continuing.
                            </div>
                            <button onclick="location.reload()" class="btn btn-outline-primary">
                                <i class="fas fa-refresh"></i> Recheck Requirements
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php break;

                case 2: // Database Configuration ?>
                    <h3><i class="fas fa-database text-primary"></i> Database Configuration</h3>
                    <p class="text-muted">Please provide your database connection details:</p>

                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Database Host</label>
                                    <input type="text" class="form-control" name="db_host" value="localhost" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Database Name</label>
                                    <input type="text" class="form-control" name="db_name" value="earist_ess" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" name="db_username" value="root" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="db_password" placeholder="Leave empty for XAMPP default">
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <button type="submit" class="btn btn-primary-custom">
                                <i class="fas fa-database"></i> Test Database Connection
                            </button>
                        </div>
                    </form>
                    <?php break;

                case 3: // Database Installation ?>
                    <h3><i class="fas fa-cogs text-primary"></i> Database Installation</h3>
                    <p class="text-muted">Creating database tables and inserting initial data...</p>

                    <form method="POST">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> What will be installed:</h6>
                            <ul class="mb-0">
                                <li>Database tables for users, programs, feedback, etc.</li>
                                <li>Default admin account and sample data</li>
                                <li>System configuration settings</li>
                                <li>Required indexes and constraints</li>
                            </ul>
                        </div>

                        <div class="text-center">
                            <button type="submit" class="btn btn-primary-custom">
                                <i class="fas fa-play"></i> Install Database
                            </button>
                        </div>
                    </form>
                    <?php break;

                case 4: // System Configuration ?>
                    <h3><i class="fas fa-cog text-primary"></i> System Configuration</h3>
                    <p class="text-muted">Configure your system settings:</p>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">System Name</label>
                            <input type="text" class="form-control" name="system_name" value="EARIST Extension Service System" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">System Abbreviation</label>
                                    <input type="text" class="form-control" name="system_abbr" value="EESS" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Base URL</label>
                                    <input type="url" class="form-control" name="base_url" value="http://<?= $_SERVER['HTTP_HOST'] ?>/earist-ess/" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Institution Name</label>
                            <input type="text" class="form-control" name="institution" value="Eulogio &quot;Amang&quot; Rodriguez Institute of Science and Technology" required>
                        </div>

                        <div class="text-center">
                            <button type="submit" class="btn btn-primary-custom">
                                <i class="fas fa-save"></i> Save Configuration
                            </button>
                        </div>
                    </form>
                    <?php break;

                case 5: // Installation Complete ?>
                    <h3><i class="fas fa-check-circle text-success"></i> Installation Complete!</h3>
                    <p class="text-muted">Your EARIST Extension Service System has been successfully installed.</p>

                    <div class="alert alert-success">
                        <h6><i class="fas fa-key"></i> Default Login Credentials:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Administrator:</strong><br>
                                Username: <code>admin</code><br>
                                Password: <code>admin123</code>
                            </div>
                            <div class="col-md-6">
                                <strong>Authorized User:</strong><br>
                                Username: <code>coe_head</code><br>
                                Password: <code>admin123</code>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <h6><i class="fas fa-shield-alt"></i> Important Security Notes:</h6>
                        <ul class="mb-0">
                            <li>Change all default passwords immediately after login</li>
                            <li>Delete this installation file (install.php) for security</li>
                            <li>Configure proper file permissions on production servers</li>
                            <li>Enable HTTPS in production environments</li>
                        </ul>
                    </div>

                    <div class="text-center">
                        <a href="index.php" class="btn btn-primary-custom me-3">
                            <i class="fas fa-sign-in-alt"></i> Login to System
                        </a>
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                        </a>
                    </div>
                    <?php break;

            endswitch; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>