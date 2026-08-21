<?php
// Start output buffering at the very beginning
ob_start();

require_once 'config.php';
requireLogin();

// Handle notification actions
if (isset($_POST['action']) && $_POST['action'] === 'notification') {
    $response = ['success' => false];
    
    try {
        $user_id = $_SESSION['user_id'];
        
        switch ($_POST['type']) {
            case 'mark_read':
                $notification_id = $_POST['notification_id'];
                $db->query("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?", 
                          [$notification_id, $user_id]);
                $response = ['success' => true, 'message' => 'Notification marked as read'];
                break;
                
            case 'mark_all_read':
                $db->query("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", [$user_id]);
                $response = ['success' => true, 'message' => 'All notifications marked as read'];
                break;
                
            case 'delete':
                $notification_id = $_POST['notification_id'];
                $db->query("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?", 
                          [$notification_id, $user_id]);
                $response = ['success' => true, 'message' => 'Notification deleted'];
                break;
                
            case 'delete_all_read':
                $db->query("DELETE FROM notifications WHERE user_id = ? AND is_read = 1", [$user_id]);
                $response = ['success' => true, 'message' => 'All read notifications deleted'];
                break;
                
            case 'get_all':
                $filter = $_POST['filter'] ?? 'all';
                $page_num = (int)($_POST['page'] ?? 1);
                $limit = 10;
                $offset = ($page_num - 1) * $limit;
                
                $where_clause = "WHERE user_id = ?";
                $params = [$user_id];
                
                if ($filter === 'unread') {
                    $where_clause .= " AND is_read = 0";
                } elseif ($filter === 'read') {
                    $where_clause .= " AND is_read = 1";
                }
                
                $notifications = $db->fetchAll(
                    "SELECT * FROM notifications $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset", 
                    $params
                );
                
                $total = $db->fetch("SELECT COUNT(*) as count FROM notifications $where_clause", $params)['count'];
                
                $response = [
                    'success' => true, 
                    'notifications' => $notifications,
                    'total' => $total,
                    'page' => $page_num,
                    'hasMore' => ($offset + $limit) < $total
                ];
                break;
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'error' => $e->getMessage()];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    if (isLoggedIn()) {
        logActivity($_SESSION['user_id'], 'User Logout');
    }
    session_destroy();
    header('Location: authentication/login.php');
    exit();
}

// Get current page
$page = $_GET['page'] ?? 'dashboard';
$allowed_pages = ['dashboard', 'programs', 'users', 'reports', 'recommendations', 'profile', 'settings', 'notifications'];

if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}

// Initialize variables
$stats = [];
$recent_programs = [];
$notifications = [];
$dashboard_error = null;

// Get dashboard statistics with improved error handling
if ($page === 'dashboard') {
    try {
        // Check if programs table exists first
        $table_exists = $db->fetch("SHOW TABLES LIKE 'programs'");
        if (!$table_exists) {
            throw new Exception("Programs table does not exist");
        }

        // Get table structure to understand available columns
        $table_columns = $db->fetchAll("DESCRIBE programs");
        $available_columns = array_column($table_columns, 'Field');
        error_log("Available columns in programs table: " . implode(', ', $available_columns));

        // Basic stats with error handling
        $stats['total_programs'] = 0;
        $stats['active_programs'] = 0;
        $stats['completed_programs'] = 0;
        $stats['total_beneficiaries'] = 0;
        $stats['total_budget'] = 0;
        $stats['pending_requests'] = 0;

        try {
            $stats['total_programs'] = $db->fetch("SELECT COUNT(*) as count FROM programs")['count'] ?? 0;
        } catch (Exception $e) {
            error_log("Error getting total programs: " . $e->getMessage());
        }

        try {
            // Try different status values
            $active_statuses = ['Ongoing', 'Active', 'In Progress', 'Started'];
            $active_query = "SELECT COUNT(*) as count FROM programs WHERE status IN ('" . implode("','", $active_statuses) . "')";
            $stats['active_programs'] = $db->fetch($active_query)['count'] ?? 0;
        } catch (Exception $e) {
            error_log("Error getting active programs: " . $e->getMessage());
        }

        try {
            $completed_statuses = ['Completed', 'Finished', 'Done', 'Ended'];
            $completed_query = "SELECT COUNT(*) as count FROM programs WHERE status IN ('" . implode("','", $completed_statuses) . "')";
            $stats['completed_programs'] = $db->fetch($completed_query)['count'] ?? 0;
        } catch (Exception $e) {
            error_log("Error getting completed programs: " . $e->getMessage());
        }

        // Check for participants column variations
        $participant_columns = ['actual_participants', 'participants', 'participant_count', 'total_participants', 'beneficiaries'];
        $participant_column = null;
        foreach ($participant_columns as $col) {
            if (in_array($col, $available_columns)) {
                $participant_column = $col;
                break;
            }
        }

        if ($participant_column) {
            try {
                $stats['total_beneficiaries'] = $db->fetch("SELECT SUM(COALESCE($participant_column, 0)) as total FROM programs WHERE status IN ('Completed', 'Finished', 'Done', 'Ended')")['total'] ?? 0;
            } catch (Exception $e) {
                error_log("Error getting total beneficiaries: " . $e->getMessage());
            }
        }

        // Check for budget column variations
        $budget_columns = ['budget_allocated', 'budget', 'total_budget', 'allocated_budget', 'cost'];
        $budget_column = null;
        foreach ($budget_columns as $col) {
            if (in_array($col, $available_columns)) {
                $budget_column = $col;
                break;
            }
        }

        if ($budget_column) {
            try {
                $stats['total_budget'] = $db->fetch("SELECT SUM(COALESCE($budget_column, 0)) as total FROM programs")['total'] ?? 0;
            } catch (Exception $e) {
                error_log("Error getting total budget: " . $e->getMessage());
            }
        }

        // Check if program_requests table exists
        $requests_table_exists = $db->fetch("SHOW TABLES LIKE 'program_requests'");
        if ($requests_table_exists) {
            try {
                $stats['pending_requests'] = $db->fetch("SELECT COUNT(*) as count FROM program_requests WHERE status = 'Pending'")['count'] ?? 0;
            } catch (Exception $e) {
                error_log("Error getting pending requests: " . $e->getMessage());
            }
        }

        // Get recent programs with flexible column handling
        $name_columns = ['program_name', 'name', 'title', 'program_title'];
        $name_column = 'program_name'; // default
        foreach ($name_columns as $col) {
            if (in_array($col, $available_columns)) {
                $name_column = $col;
                break;
            }
        }

        $location_columns = ['location', 'venue', 'address', 'place', 'site'];
        $location_column = 'location'; // default
        foreach ($location_columns as $col) {
            if (in_array($col, $available_columns)) {
                $location_column = $col;
                break;
            }
        }

        $date_columns = ['start_date', 'date_start', 'program_date', 'start_datetime', 'date'];
        $date_column = 'start_date'; // default
        foreach ($date_columns as $col) {
            if (in_array($col, $available_columns)) {
                $date_column = $col;
                break;
            }
        }

        $id_columns = ['program_id', 'id'];
        $id_column = 'program_id'; // default
        foreach ($id_columns as $col) {
            if (in_array($col, $available_columns)) {
                $id_column = $col;
                break;
            }
        }

        // Build flexible query
        $recent_programs_query = "
            SELECT 
                p.*,
                p.$id_column as program_id,
                p.$name_column as program_name,
                p.$location_column as location,
                p.$date_column as start_date,
                COALESCE(u.first_name, '') as first_name,
                COALESCE(u.last_name, '') as last_name,
                CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as creator_name
            FROM programs p 
            LEFT JOIN users u ON p.created_by = u.user_id 
            ORDER BY " . (in_array('created_at', $available_columns) ? 'p.created_at' : 'p.' . $id_column) . " DESC 
            LIMIT 5
        ";

        error_log("Executing query: " . $recent_programs_query);
        $recent_programs = $db->fetchAll($recent_programs_query);
        error_log("Found " . count($recent_programs) . " recent programs");

        // Validate and clean the program data
        foreach ($recent_programs as &$program) {
            // Ensure required fields exist with fallback values
            if (empty($program['program_name'])) {
                $program['program_name'] = $program['title'] ?? $program['name'] ?? 'Unnamed Program';
            }
            
            if (empty($program['status'])) {
                $program['status'] = 'Planned';
            }
            
            if (empty($program['start_date']) || $program['start_date'] === '0000-00-00') {
                $program['start_date'] = $program['created_at'] ?? date('Y-m-d');
            }
            
            if (empty($program['location'])) {
                $program['location'] = $program['venue'] ?? $program['address'] ?? 'Not specified';
            }
            
            // Clean up the creator name
            if (empty(trim($program['creator_name'] ?? ''))) {
                $program['creator_name'] = 'Unknown';
            } else {
                $program['creator_name'] = trim($program['creator_name']);
            }
        }

        // Get recent notifications with error handling
        try {
            if (function_exists('getUnreadNotifications')) {
                $notifications = getUnreadNotifications($_SESSION['user_id']);
            } else {
                // Fallback if function doesn't exist
                $notif_table_exists = $db->fetch("SHOW TABLES LIKE 'notifications'");
                if ($notif_table_exists) {
                    $notifications = $db->fetchAll(
                        "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5", 
                        [$_SESSION['user_id']]
                    );
                } else {
                    $notifications = [];
                }
            }
        } catch (Exception $e) {
            error_log("Error getting notifications: " . $e->getMessage());
            $notifications = [];
        }

    } catch (Exception $e) {
        error_log("Dashboard error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        // Set default values if there's an error
        $stats = [
            'total_programs' => 0,
            'active_programs' => 0,
            'completed_programs' => 0,
            'total_beneficiaries' => 0,
            'total_budget' => 0,
            'pending_requests' => 0
        ];
        $recent_programs = [];
        $notifications = [];
        $dashboard_error = "Unable to load dashboard data: " . $e->getMessage();
    }
}

// Get user profile
try {
    $user_profile = $db->fetch("SELECT * FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
} catch (Exception $e) {
    error_log("Error getting user profile: " . $e->getMessage());
    $user_profile = null;
}

// Get notification counts for header
try {
    $notif_table_exists = $db->fetch("SHOW TABLES LIKE 'notifications'");
    if ($notif_table_exists) {
        $unread_count = $db->fetch("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", [$_SESSION['user_id']])['count'] ?? 0;
    } else {
        $unread_count = 0;
    }
} catch (Exception $e) {
    error_log("Error getting notification count: " . $e->getMessage());
    $unread_count = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EARIST - <?= ucfirst($page) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --earist-red: #a7000e;
            --earist-gold: #ffd000;
            --earist-dark: #1e293b;
            --sidebar-width: 250px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: white;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        .sidebar-header {
            background: linear-gradient(135deg, var(--earist-red), #8c000c);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .sidebar-header img {
            max-width: 100%;
            height: auto;
        }

        .nav-item {
            padding: 12px 20px;
            color: var(--earist-dark);
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .nav-item:hover, .nav-item.active {
            background: #f1f5f9;
            color: var(--earist-red);
            border-left-color: var(--earist-red);
            text-decoration: none;
        }

        .nav-item i {
            width: 20px;
            margin-right: 10px;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: white;
            padding: 15px 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-radius: 10px;
            position: relative;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
        }

        .header-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--earist-dark);
            margin-bottom: 0;
        }

        .date-time {
            color: #64748b;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }

        /* Enhanced Notification System */
        .notification-container {
            position: relative;
            z-index: 10000;
        }

        .notification-btn {
            background: none !important;
            border: none !important;
            color: #64748b !important;
            padding: 12px !important;
            border-radius: 12px !important;
            transition: all 0.3s ease !important;
            position: relative;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-btn:hover {
            background: #f1f5f9 !important;
            color: var(--earist-red) !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(167, 0, 14, 0.2);
        }

        .notification-btn:focus {
            box-shadow: 0 0 0 3px rgba(167, 0, 14, 0.2) !important;
            outline: none !important;
        }

        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: linear-gradient(135deg, var(--earist-red), #ff4757);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(167, 0, 14, 0.4);
            animation: notificationPulse 2s infinite;
            z-index: 10001;
        }

        @keyframes notificationPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.15); }
            100% { transform: scale(1); }
        }

        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            height: 100%;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            background: var(--earist-red);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--earist-dark);
            margin-bottom: 5px;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-top: 25px;
            padding: 20px;
            overflow: hidden;
        }

        .table-header {
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .table-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--earist-dark);
        }

        /* User Profile */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            background: #f1f5f9;
            border-radius: 25px;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: var(--earist-red);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Status badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-completed, .status-finished, .status-done, .status-ended { 
            background: #d1fae5; color: #059669; 
        }
        .status-ongoing, .status-active, .status-in-progress, .status-started { 
            background: #dbeafe; color: #1d4ed8; 
        }
        .status-planned, .status-pending { 
            background: #fef3c7; color: #d97706; 
        }
        .status-cancelled, .status-canceled { 
            background: #fee2e2; color: #dc2626; 
        }

        /* Error Message */
        .dashboard-error {
            background: linear-gradient(135deg, #fee2e2, #fef2f2);
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
                padding: 15px 20px;
            }

            .header-actions {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="images/logo.png" alt="EARIST Logo" height="60" class="mb-3">
            <h5 class="mb-1">EARIST</h5>
            <small>Extension Services System</small>
        </div>
        <nav class="mt-4">
            <a href="?page=dashboard" class="nav-item <?= $page === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="?page=programs" class="nav-item <?= $page === 'programs' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> Programs
            </a>
            <a href="?page=reports" class="nav-item <?= $page === 'reports' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
            <a href="?page=notifications" class="nav-item <?= $page === 'notifications' ? 'active' : '' ?>">
                <i class="fas fa-bell"></i> Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="badge bg-danger ms-2"><?= $unread_count ?></span>
                <?php endif; ?>
            </a>
            <?php if (hasRole(['Admin'])): ?>
            <a href="?page=users" class="nav-item <?= $page === 'users' ? 'active' : '' ?>">
                <i class="fas fa-users"></i> Users
            </a>
            <a href="?page=settings" class="nav-item <?= $page === 'settings' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i> Settings
            </a>
            <?php endif; ?>
            <?php if (hasRole(['Authorized User'])): ?>
            <a href="?page=recommendations" class="nav-item <?= $page === 'recommendations' ? 'active' : '' ?>">
                <i class="fas fa-lightbulb"></i> Recommendations
            </a>
            <?php endif; ?>
            <div class="mt-4">
                <a href="?page=profile" class="nav-item <?= $page === 'profile' ? 'active' : '' ?>">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="?logout=1" class="nav-item text-danger" onclick="return confirm('Are you sure you want to logout?')">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <button class="btn btn-link d-md-none me-3" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h4 class="header-title"><?= ucfirst(str_replace('_', ' ', $page)) ?></h4>
                    <div class="date-time">
                        <i class="far fa-clock me-1"></i> <?= date('Y-m-d H:i:s') ?> UTC
                    </div>
                </div>
            </div>
            <div class="header-actions">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div>
                        <div class="fw-500"><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') ?></div>
                        <small class="text-muted"><?= htmlspecialchars($_SESSION['user_role'] ?? 'User') ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <?php if ($page === 'dashboard'): ?>
            
            <?php if ($dashboard_error): ?>
                <div class="dashboard-error">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Dashboard Error:</strong> <?= htmlspecialchars($dashboard_error) ?>
                    <br><small>Please check your database connection and table structure.</small>
                </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="stat-value"><?= number_format($stats['total_programs'] ?? 0) ?></div>
                        <div class="stat-label">Total Programs</div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-play"></i>
                        </div>
                        <div class="stat-value"><?= number_format($stats['active_programs'] ?? 0) ?></div>
                        <div class="stat-label">Active Programs</div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="stat-value"><?= number_format($stats['completed_programs'] ?? 0) ?></div>
                        <div class="stat-label">Completed Programs</div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value"><?= number_format($stats['total_beneficiaries'] ?? 0) ?></div>
                        <div class="stat-label">Total Beneficiaries</div>
                    </div>
                </div>
            </div>

            <!-- Recent Programs Table -->
            <div class="table-card">
                <div class="table-header">
                    <h5 class="table-title mb-0">Recent Programs</h5>
                    <?php if (count($recent_programs) > 0): ?>
                        <small class="text-muted">Showing <?= count($recent_programs) ?> most recent programs</small>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Program Name</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>Location</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_programs)): ?>
                                <?php foreach ($recent_programs as $program): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($program['program_name']) ?></strong>
                                            <?php if (!empty($program['description'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars(substr($program['description'], 0, 60)) ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= strtolower(str_replace([' ', '_'], '-', $program['status'])) ?>">
                                                <?= htmlspecialchars($program['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $date = $program['start_date'];
                                            if ($date && $date !== '0000-00-00' && $date !== '0000-00-00 00:00:00') {
                                                echo date('M d, Y', strtotime($date));
                                            } else {
                                                echo '<span class="text-muted">Not set</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?= htmlspecialchars($program['location']) ?></td>
                                        <td>
                                            <?= htmlspecialchars(trim($program['creator_name'])) ?>
                                        </td>
                                        <td>
                                            <a href="?page=programs&view=<?= $program['program_id'] ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block opacity-50"></i>
                                        <h6>No programs found</h6>
                                        <p class="mb-3">No programs have been created yet.</p>
                                        <a href="?page=programs" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Create your first program
                                        </a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (count($recent_programs) >= 5): ?>
                    <div class="text-center pt-3">
                        <a href="?page=programs" class="btn btn-outline-primary">
                            <i class="fas fa-list"></i> View All Programs
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($page === 'notifications'): ?>
            <!-- Notifications page content would go here -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Notifications page content not implemented yet.
            </div>

        <?php else: ?>
            <!-- Content for other pages -->
            <div class="content">
                <?php
                // Include the appropriate page content
                switch ($page) {
                    case 'programs':
                        if (file_exists('pages/programs_admin.php')) {
                            include 'pages/programs_admin.php';
                        } else {
                            echo '<div class="alert alert-warning">Programs page not found. Please check if pages/programs_admin.php exists.</div>';
                        }
                        break;
                    case 'users':
                        if (hasRole(['Admin'])) {
                            if (file_exists('pages/users.php')) {
                                include 'pages/users.php';
                            } else {
                                echo '<div class="alert alert-warning">Users page not found. Please check if pages/users.php exists.</div>';
                            }
                        } else {
                            echo '<div class="alert alert-danger">Access denied. You do not have permission to view this page.</div>';
                        }
                        break;
                    case 'reports':
                        if (file_exists('pages/reports.php')) {
                            include 'pages/reports.php';
                        } else {
                            echo '<div class="alert alert-warning">Reports page not found. Please check if pages/reports.php exists.</div>';
                        }
                        break;
                    case 'recommendations':
                        if (hasRole(['Authorized User'])) {
                            if (file_exists('pages/recommendations.php')) {
                                include 'pages/recommendations.php';
                            } else {
                                echo '<div class="alert alert-warning">Recommendations page not found. Please check if pages/recommendations.php exists.</div>';
                            }
                        } else {
                            echo '<div class="alert alert-danger">Access denied. You do not have permission to view this page.</div>';
                        }
                        break;
                    case 'profile':
                        if (file_exists('pages/profile.php')) {
                            include 'pages/profile.php';
                        } else {
                            echo '<div class="alert alert-warning">Profile page not found. Please check if pages/profile.php exists.</div>';
                        }
                        break;
                    case 'settings':
                        if (hasRole(['Admin'])) {
                            if (file_exists('pages/settings.php')) {
                                include 'pages/settings.php';
                            } else {
                                echo '<div class="alert alert-warning">Settings page not found. Please check if pages/settings.php exists.</div>';
                            }
                        } else {
                            echo '<div class="alert alert-danger">Access denied. You do not have permission to view this page.</div>';
                        }
                        break;
                    default:
                        echo '<div class="alert alert-warning">Page not found.</div>';
                }
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- jQuery (load first) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- Bootstrap Bundle -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Toggle sidebar on mobile
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Update datetime every second
        setInterval(() => {
            const now = new Date();
            document.querySelector('.date-time').innerHTML = 
                `<i class="far fa-clock me-1"></i> ${now.toISOString().slice(0, 19).replace('T', ' ')} UTC`;
        }, 1000);

        // Initialize DataTables only if there are programs
        $(document).ready(function() {
            const table = $('.table-responsive table');
            if (table.find('tbody tr').length > 1 || !table.find('tbody tr td[colspan]').length) {
                table.DataTable({
                    responsive: true,
                    pageLength: 10,
                    language: {
                        search: "Search:",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        },
                        emptyTable: "No programs available"
                    }
                });
            }
        });

        // Auto-dismiss alerts
        setTimeout(function() {
            $('.alert:not(.dashboard-error)').fadeOut();
        }, 5000);

        // Console debug info
        console.log('Dashboard loaded successfully');
        console.log('Stats:', <?= json_encode($stats) ?>);
        console.log('Recent programs count:', <?= count($recent_programs) ?>);
        
        <?php if (!empty($dashboard_error)): ?>
        console.error('Dashboard error:', <?= json_encode($dashboard_error) ?>);
        <?php endif; ?>
    </script>
</body>
</html>

<?php
// End output buffering and flush the content
ob_end_flush();
?>