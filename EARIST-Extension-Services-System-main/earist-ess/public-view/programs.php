<?php
require_once '../config.php';

// Helper functions (only declare if not already defined)
if (!function_exists('formatTime')) {
    function formatTime($time) {
        if (!$time) return '';
        return date('g:i A', strtotime($time));
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$location_filter = $_GET['location'] ?? '';
$page_num = (int)($_GET['p'] ?? 1);
$per_page = 9; // 3x3 grid layout

// Build WHERE conditions for filtering
$where_conditions = [];
$params = [];

// Only show approved programs
$where_conditions[] = "p.approval_status = 'Approved'";

if (!empty($search)) {
    $where_conditions[] = "(p.title LIKE ? OR p.description LIKE ? OR p.location LIKE ? OR p.barangay LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($type_filter)) {
    $where_conditions[] = "p.type_of_service = ?";
    $params[] = $type_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if (!empty($location_filter)) {
    $where_conditions[] = "p.location = ?";
    $params[] = $location_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get programs with pagination
$sql = "SELECT p.*, u.first_name, u.last_name, 
        (SELECT COUNT(*) FROM program_feedback pf WHERE pf.program_id = p.program_id) as feedback_count
        FROM programs p 
        LEFT JOIN users u ON p.created_by = u.user_id 
        $where_clause 
        ORDER BY 
            CASE p.status 
                WHEN 'Ongoing' THEN 1 
                WHEN 'Planned' THEN 2 
                WHEN 'Completed' THEN 3 
                ELSE 4 
            END,
            p.date_start DESC";

try {
    $programs_data = paginate($sql, $params, $page_num, $per_page);
    $programs = $programs_data['data'];
} catch (Exception $e) {
    $programs = [];
    $programs_data = ['total' => 0, 'total_pages' => 0, 'current_page' => 1, 'has_prev' => false, 'has_next' => false];
}

// Get program types and locations for filter dropdown
try {
    $program_types = $db->fetchAll("SELECT DISTINCT type_of_service FROM programs WHERE approval_status = 'Approved' ORDER BY type_of_service");
    $program_locations = $db->fetchAll("SELECT DISTINCT location FROM programs WHERE approval_status = 'Approved' ORDER BY location");
} catch (Exception $e) {
    $program_types = [];
    $program_locations = [];
}

// Get statistics
try {
    $stats = $db->fetch("
        SELECT 
            COUNT(*) as total_programs,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_programs,
            SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END) as ongoing_programs,
            SUM(CASE WHEN status = 'Planned' THEN 1 ELSE 0 END) as planned_programs,
            COALESCE(SUM(CASE WHEN status = 'Completed' THEN actual_participants ELSE expected_participants END), 0) as total_participants
        FROM programs 
        WHERE approval_status = 'Approved'
    ");
} catch (Exception $e) {
    $stats = [
        'total_programs' => 0,
        'completed_programs' => 0,
        'ongoing_programs' => 0,
        'planned_programs' => 0,
        'total_participants' => 0
    ];
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $program_id = (int)$_POST['program_id'];
    $participant_name = sanitizeInput($_POST['participant_name']);
    $participant_email = sanitizeInput($_POST['participant_email']);
    $contact_number = sanitizeInput($_POST['contact_number']);
    $rating = (int)$_POST['rating'];
    $feedback_text = sanitizeInput($_POST['feedback_text']);
    $suggestions = sanitizeInput($_POST['suggestions']);
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    
    // If anonymous, don't store personal info
    if ($is_anonymous) {
        $participant_name = 'Anonymous';
        $participant_email = '';
        $contact_number = '';
    }
    
    $sql = "INSERT INTO program_feedback (program_id, participant_name, participant_email, contact_number, 
            rating, feedback_text, suggestions, is_anonymous, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $params_feedback = [$program_id, $participant_name, $participant_email, $contact_number, 
              $rating, $feedback_text, $suggestions, $is_anonymous];
    
    if ($db->query($sql, $params_feedback)) {
        $success_message = "Thank you for your feedback! Your input helps us improve our programs.";
    } else {
        $error_message = "Failed to submit your feedback. Please try again.";
    }
}

// Helper functions
function getStatusBadgeClass($status) {
    switch(strtolower($status)) {
        case 'planned': return 'bg-primary';
        case 'ongoing': return 'bg-warning text-dark';
        case 'completed': return 'bg-success';
        case 'cancelled': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

function truncateText($text, $length = 150) {
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extension Programs - <?= SYSTEM_NAME ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --earist-red: #a3000e;
            --earist-gold: #ffd000;
            --earist-light: #fff9e6;
            --earist-dark: #8c000c;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            padding-top: 76px;
            line-height: 1.6;
        }

        /* Header/Navbar */
        .navbar {
            background: #ffffff !important;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            padding: 15px 0;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--earist-red) !important;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
        }

        .navbar-brand img {
            height: 45px;
            width: 45px;
            margin-right: 10px;
            object-fit: contain;
        }

        .nav-link {
            font-weight: 500;
            color: #555 !important;
            padding: 8px 16px !important;
            transition: all 0.3s ease;
            border-radius: 6px;
            margin: 0 2px;
        }

        .nav-link:hover {
            color: var(--earist-red) !important;
            background-color: rgba(163, 0, 14, 0.1);
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--earist-red) 0%, var(--earist-dark) 100%);
            color: white;
            padding: 100px 0 80px;
            position: relative;
            overflow: hidden;
            margin-bottom: 60px;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('../images/earist_logo_png.png') center center no-repeat;
            background-size: 400px 400px;
            opacity: 0.08;
            z-index: 0;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            margin-bottom: 1.5rem;
        }

        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            border: none;
            padding: 2.5rem 2rem;
            margin-bottom: 3rem;
            position: relative;
            margin-top: -100px;
            z-index: 10;
            backdrop-filter: blur(10px);
        }

        .stat-item {
            text-align: center;
            padding: 1.5rem 1rem;
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            color: var(--earist-red);
            display: block;
            line-height: 1;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 600;
            margin-top: 0.5rem;
            font-size: 0.95rem;
        }

        /* Buttons */
        .btn-earist {
            background: linear-gradient(135deg, var(--earist-red) 0%, var(--earist-dark) 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .btn-earist:hover {
            background: linear-gradient(135deg, var(--earist-dark) 0%, #6d0009 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(163, 0, 14, 0.3);
        }

        .btn-earist-outline {
            border: 2px solid var(--earist-red);
            color: var(--earist-red);
            background: transparent;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-earist-outline:hover {
            background: var(--earist-red);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(163, 0, 14, 0.3);
        }

        /* Cards */
        .program-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
            transition: all 0.4s ease;
            height: 100%;
            overflow: hidden;
            background: white;
        }

        .program-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .program-badge {
            background: linear-gradient(135deg, var(--earist-gold) 0%, #ffed4e 100%);
            color: #333;
            font-weight: 700;
            padding: 0.6rem 1.2rem;
            border-radius: 25px;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filters */
        .filters-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 3rem;
            padding: 2rem;
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 0.875rem 1.125rem;
            transition: all 0.3s ease;
            background-color: #fafbfc;
            font-size: 0.95rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--earist-red);
            box-shadow: 0 0 0 0.25rem rgba(163, 0, 14, 0.1);
            background-color: white;
        }

        .search-input {
            border: 3px solid #e9ecef;
            border-radius: 50px;
            padding: 1.125rem 1.75rem;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: var(--earist-red);
            box-shadow: 0 0 0 0.25rem rgba(163, 0, 14, 0.1);
        }

        .search-btn {
            border-radius: 50px;
            padding: 1.125rem 2.5rem;
            margin-left: -3px;
            font-weight: 600;
        }

        /* Pagination */
        .pagination {
            gap: 0.5rem;
        }

        .pagination .page-link {
            border: none;
            color: var(--earist-red);
            border-radius: 10px;
            font-weight: 500;
            padding: 12px 18px;
            transition: all 0.3s ease;
        }

        .pagination .page-item.active .page-link {
            background: var(--earist-red);
            border-color: var(--earist-red);
            box-shadow: 0 4px 15px rgba(163, 0, 14, 0.3);
        }

        .pagination .page-link:hover {
            background: var(--earist-red);
            color: white;
            transform: translateY(-2px);
        }

        /* Modal */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.25);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--earist-red) 0%, var(--earist-dark) 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            border: none;
            padding: 1.5rem 2rem;
        }

        /* Footer */
        .footer {
            background: linear-gradient(135deg, var(--earist-red) 0%, var(--earist-dark) 100%);
            color: white;
            padding: 80px 0 40px;
            margin-top: 5rem;
        }

        .footer a {
            color: var(--earist-light);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer a:hover {
            color: var(--earist-gold);
            transform: translateX(5px);
        }

        .footer h5 {
            color: var(--earist-gold);
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        /* Star Rating */
        .rating {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
        }

        .star-rating {
            color: #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.5rem;
        }

        .star-rating:hover,
        .star-rating.active {
            color: var(--earist-gold);
            transform: scale(1.1);
        }

        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(40px);
            transition: all 0.8s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Alert Styles */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }

        /* Section */
        .section {
            padding: 80px 0;
        }

        .section-title {
            position: relative;
            margin-bottom: 50px;
            text-align: center;
        }

        .section-title h2 {
            font-weight: 800;
            color: var(--earist-red);
            font-size: 2.75rem;
            margin-bottom: 1rem;
        }

        .section-title h2::after {
            content: '';
            display: block;
            width: 100px;
            height: 5px;
            background: linear-gradient(135deg, var(--earist-gold) 0%, #ffed4e 100%);
            margin: 25px auto 0;
            border-radius: 3px;
        }

        .section-subtitle {
            font-size: 1.2rem;
            color: #6c757d;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.7;
        }

        /* Enhanced Card Styles */
        .card-body {
            padding: 2rem;
        }

        .card-footer {
            padding: 1.5rem 2rem;
            background: #f8f9fa;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .stats-card {
                margin-top: -80px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 70px;
            }
            
            .navbar {
                padding: 10px 0;
            }
            
            .navbar-brand img {
                height: 35px;
                width: 35px;
            }
            
            .hero-section {
                padding: 80px 0 60px;
            }
            
            .hero-section::before {
                background-size: 250px 250px;
            }
            
            .stats-card {
                margin-top: -60px;
                padding: 1.5rem;
            }
            
            .stat-number {
                font-size: 2.2rem;
            }
            
            .section-title h2 {
                font-size: 2.2rem;
            }
            
            .filters-card {
                padding: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .hero-section::before {
                background-size: 180px 180px;
            }
            
            .section-title h2 {
                font-size: 1.8rem;
            }
            
            .stat-number {
                font-size: 1.8rem;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="public_homepage.php">
                <img src="../images/earist_logo_png.png" alt="EARIST Logo">
                <?= SYSTEM_ABBR ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="public_homepage.php#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="public_homepage.php#programs">Programs</a></li>
                    <li class="nav-item"><a class="nav-link" href="public_homepage.php#request">Request</a></li>
                    <li class="nav-item"><a class="nav-link" href="public_homepage.php#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="public_homepage.php#extension-info">Extension Info</a></li>
                    <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                        <a href="../authentication/login.php" class="btn btn-earist-outline btn-sm">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Success/Error Messages -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 100px; right: 20px; z-index: 1050; max-width: 400px;">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 100px; right: 20px; z-index: 1050; max-width: 400px;">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="hero-title display-4 mb-4">Extension Programs</h1>
                <p class="lead mb-5">
                    Discover our community-focused programs designed to serve, educate, and empower. 
                    Join us in building stronger communities through knowledge sharing and collaborative action.
                </p>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="#programs" class="btn btn-light btn-lg">
                        <i class="fas fa-search me-2"></i>Browse Programs
                    </a>
                    <a href="public_homepage.php#request" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-handshake me-2"></i>Request Program
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <!-- Quick Stats -->
        <div class="stats-card fade-in">
            <div class="row">
                <div class="col-lg-3 col-sm-6 mb-3 mb-lg-0">
                    <div class="stat-item">
                        <span class="stat-number"><?= number_format($stats['total_programs']) ?></span>
                        <div class="stat-label">Total Programs</div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6 mb-3 mb-lg-0">
                    <div class="stat-item">
                        <span class="stat-number" style="color: #059669;"><?= number_format($stats['completed_programs']) ?></span>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6 mb-3 mb-lg-0">
                    <div class="stat-item">
                        <span class="stat-number" style="color: #f59e0b;"><?= number_format($stats['ongoing_programs']) ?></span>
                        <div class="stat-label">Ongoing</div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="stat-item">
                        <span class="stat-number" style="color: #e11d48;"><?= number_format($stats['total_participants']) ?></span>
                        <div class="stat-label">Total Participants</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="filters-card fade-in">
            <form method="GET" action="programs.php">
                <div class="row mb-4">
                    <div class="col-lg-8 mb-3">
                        <div class="input-group input-group-lg">
                            <input type="text" class="form-control search-input" name="search" 
                                   value="<?= htmlspecialchars($search) ?>"
                                   placeholder="Search programs by title, description, or location...">
                            <button class="btn btn-earist search-btn" type="submit">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <button type="button" class="btn btn-earist-outline btn-lg w-100" onclick="toggleFilters()">
                            <i class="fas fa-filter me-2"></i>Filters 
                            <?php if ($type_filter || $status_filter || $location_filter): ?>
                                <span class="badge bg-danger ms-1">Active</span>
                            <?php endif; ?>
                        </button>
                    </div>
                </div>

                <!-- Advanced Filters -->
                <div id="advanced-filters" style="display: <?= ($type_filter || $status_filter || $location_filter) ? 'block' : 'none' ?>;">
                    <hr class="my-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Program Type</label>
                            <select class="form-select" name="type">
                                <option value="">All Types</option>
                                <?php foreach ($program_types as $type): ?>
                                    <option value="<?= htmlspecialchars($type['type_of_service']) ?>" 
                                            <?= $type_filter === $type['type_of_service'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type['type_of_service']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="Planned" <?= $status_filter === 'Planned' ? 'selected' : '' ?>>Upcoming</option>
                                <option value="Ongoing" <?= $status_filter === 'Ongoing' ? 'selected' : '' ?>>Currently Running</option>
                                <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Location</label>
                            <select class="form-select" name="location">
                                <option value="">All Locations</option>
                                <?php foreach ($program_locations as $location): ?>
                                    <option value="<?= htmlspecialchars($location['location']) ?>" 
                                            <?= $location_filter === $location['location'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($location['location']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-earist me-2">
                            <i class="fas fa-search me-2"></i>Apply Filters
                        </button>
                        <a href="programs.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Clear All
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Programs Section -->
        <section id="programs" class="section">
            <div class="section-title fade-in">
                <h2>Available Programs</h2>
                <p class="section-subtitle">Explore our comprehensive list of extension programs designed to make a positive impact in our communities</p>
            </div>

            <!-- Programs Grid -->
            <div class="row g-4" id="programs-grid">
                <?php if (empty($programs)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-4x text-muted mb-4"></i>
                            <h3 class="text-muted mb-3">No programs found</h3>
                            <p class="text-muted mb-4">
                                <?php if ($search || $type_filter || $status_filter || $location_filter): ?>
                                    Try adjusting your search criteria or filters to find the programs you're looking for.
                                <?php else: ?>
                                    No programs are currently available. Check back soon for new opportunities!
                                <?php endif; ?>
                            </p>
                            <?php if ($search || $type_filter || $status_filter || $location_filter): ?>
                                <a href="programs.php" class="btn btn-earist">
                                    <i class="fas fa-refresh me-2"></i>Show All Programs
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($programs as $program): ?>
                        <div class="col-lg-4 col-md-6 mb-4 fade-in">
                            <div class="card program-card h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <span class="program-badge"><?= htmlspecialchars($program['type_of_service']) ?></span>
                                        <span class="badge <?= getStatusBadgeClass($program['status']) ?>">
                                            <?= htmlspecialchars($program['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <h5 class="card-title mb-3"><?= htmlspecialchars($program['title']) ?></h5>
                                    <p class="card-text text-muted mb-4 flex-grow-1">
                                        <?= htmlspecialchars(truncateText($program['description'])) ?>
                                    </p>
                                    
                                    <div class="program-info mb-4">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-map-marker-alt text-danger me-2"></i>
                                            <span class="fw-semibold"><?= htmlspecialchars($program['location']) ?></span>
                                        </div>
                                        <?php if (!empty($program['barangay'])): ?>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-building text-muted me-2"></i>
                                                <span class="text-muted"><?= htmlspecialchars($program['barangay']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-calendar text-success me-2"></i>
                                            <span class="text-success"><?= formatDate($program['date_start']) ?></span>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-users text-info me-2"></i>
                                            <span class="text-info">
                                                <?php if ($program['status'] === 'Completed' && !empty($program['actual_participants'])): ?>
                                                    <?= number_format($program['actual_participants']) ?> attended
                                                <?php else: ?>
                                                    <?= number_format($program['expected_participants'] ?? 0) ?> expected
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <?php if ($program['feedback_count'] > 0): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-comments text-warning me-2"></i>
                                                <span class="text-warning"><?= number_format($program['feedback_count']) ?> feedback</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="card-footer border-0">
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-earist" onclick="showProgramDetails(<?= $program['program_id'] ?>)">
                                            <i class="fas fa-info-circle me-2"></i>View Details
                                        </button>
                                        <?php if ($program['status'] === 'Completed'): ?>
                                            <button class="btn btn-earist-outline" 
                                                    onclick="showFeedbackModal(<?= $program['program_id'] ?>, '<?= htmlspecialchars($program['title']) ?>')">
                                                <i class="fas fa-star me-2"></i>Give Feedback
                                            </button>
                                        <?php else: ?>
                                            <a href="public_homepage.php#request" class="btn btn-earist-outline">
                                                <i class="fas fa-hand-paper me-2"></i>Express Interest
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($programs_data['total_pages'] > 1): ?>
                <div class="d-flex justify-content-center mt-5">
                    <nav aria-label="Programs pagination">
                        <ul class="pagination">
                            <?php if ($programs_data['has_prev']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?p=<?= $programs_data['current_page'] - 1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($type_filter) ?>&status=<?= urlencode($status_filter) ?>&location=<?= urlencode($location_filter) ?>" aria-label="Previous">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $programs_data['current_page'] - 2); $i <= min($programs_data['total_pages'], $programs_data['current_page'] + 2); $i++): ?>
                                <li class="page-item <?= $i === $programs_data['current_page'] ? 'active' : '' ?>">
                                    <a class="page-link" href="?p=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($type_filter) ?>&status=<?= urlencode($status_filter) ?>&location=<?= urlencode($location_filter) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($programs_data['has_next']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?p=<?= $programs_data['current_page'] + 1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($type_filter) ?>&status=<?= urlencode($status_filter) ?>&location=<?= urlencode($location_filter) ?>" aria-label="Next">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <div class="text-center text-muted mt-3">
                    Showing <?= number_format(($programs_data['current_page'] - 1) * $per_page + 1) ?> to 
                    <?= number_format(min($programs_data['current_page'] * $per_page, $programs_data['total'])) ?> of 
                    <?= number_format($programs_data['total']) ?> programs
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Program Details Modal -->
    <div class="modal fade" id="programModal" tabindex="-1" aria-labelledby="programModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="programModalLabel">
                        <i class="fas fa-info-circle me-2"></i>Program Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Program details will be populated here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" id="modalInterestBtn" class="btn btn-earist">
                        <i class="fas fa-hand-paper me-2"></i>Express Interest
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="feedbackModalLabel">
                        <i class="fas fa-star me-2"></i>Program Feedback
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="feedbackForm">
                    <div class="modal-body">
                        <input type="hidden" name="program_id" id="modalProgramId">
                        <div class="alert alert-info">
                            <strong>Program:</strong> <span id="modalProgramTitle"></span>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Your Name *</label>
                            <input type="text" name="participant_name" id="participantName" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="participant_email" id="participantEmail" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" name="contact_number" id="contactNumber" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Rating *</label>
                            <div class="rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star star-rating" data-rating="<?= $i ?>"></i>
                                <?php endfor; ?>
                                <input type="hidden" name="rating" id="selectedRating" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Feedback *</label>
                            <textarea name="feedback_text" id="feedbackText" class="form-control" rows="3" required placeholder="Share your experience with this program..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Suggestions</label>
                            <textarea name="suggestions" id="suggestions" class="form-control" rows="2" placeholder="Any suggestions for improvement?"></textarea>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_anonymous" id="anonymousCheck">
                            <label class="form-check-label" for="anonymousCheck">Submit anonymously</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_feedback" class="btn btn-earist">
                            <i class="fas fa-paper-plane me-2"></i>Submit Feedback
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h4 class="text-white mb-4">
                        <img src="../images/logo.png" height="40" class="me-2" alt="EARIST Logo">
                        <?= SYSTEM_ABBR ?>
                    </h4>
                    <p class="mb-3">Extension Services System</p>
                    <p>Bridging academic excellence with community development through innovative programs and partnerships.</p>
                </div>
                <div class="col-lg-2 col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="public_homepage.php">Home</a></li>
                        <li class="mb-2"><a href="programs.php">Programs</a></li>
                        <li class="mb-2"><a href="public_homepage.php#request">Request</a></li>
                        <li class="mb-2"><a href="public_homepage.php#about">About</a></li>
                        <li class="mb-2"><a href="public_homepage.php#extension-info">Extension Info</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4">
                    <h5>Contact Us</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-envelope me-2"></i> earistofficial1945@gmail.com</li>
                        <li class="mb-2"><i class="fas fa-phone me-2"></i> (028)243-9467</li>
                        <li class="mb-2"><i class="fas fa-map-marker-alt me-2"></i> Nagtahan St, Sampaloc, Manila</li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4">
                    <h5>Connect</h5>
                    <div class="d-flex gap-3 mb-3">
                        <a href="https://www.facebook.com/EARISTOfficial" class="text-white fs-4"><i class="fab fa-facebook"></i></a>
                        <a href="https://x.com/earist1945?lang=en" class="text-white fs-4"><i class="fab fa-twitter"></i></a>
                        <a href="https://www.instagram.com/earistofficial/" class="text-white fs-4"><i class="fab fa-instagram"></i></a>
                        <a href="https://www.youtube.com/@earistmis9332" class="text-white fs-4"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            <hr class="my-4 bg-light opacity-25">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; <?= date('Y') ?> <?= INSTITUTION_NAME ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0">Extension Services System v1.0</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Program data for modal display
        const programs = <?= json_encode($programs) ?>;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeAnimations();
            initializeNavbar();
            initializeStarRating();
            initializeForms();
            autoHideAlerts();
        });

        // Initialize animations
        function initializeAnimations() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.fade-in').forEach(el => {
                observer.observe(el);
            });
        }

        // Initialize navbar effects
        function initializeNavbar() {
            // Navbar scroll effect
            window.addEventListener('scroll', function() {
                const navbar = document.querySelector('.navbar');
                if (window.scrollY > 50) {
                    navbar.style.padding = '8px 0';
                    navbar.style.boxShadow = '0 2px 20px rgba(0,0,0,0.15)';
                } else {
                    navbar.style.padding = '15px 0';
                    navbar.style.boxShadow = '0 2px 15px rgba(0,0,0,0.1)';
                }
            });

            // Auto-close navbar toggle
            document.querySelectorAll('.navbar-nav .nav-link, .navbar .btn').forEach(link => {
                link.addEventListener('click', () => {
                    const navbarCollapse = document.querySelector('#navbarNav');
                    if (navbarCollapse.classList.contains('show')) {
                        const bsCollapse = new bootstrap.Collapse(navbarCollapse, { toggle: false });
                        bsCollapse.hide();
                    }
                });
            });
        }

        // Initialize star rating
        function initializeStarRating() {
            document.querySelectorAll('.star-rating').forEach(star => {
                star.addEventListener('click', function() {
                    const rating = this.getAttribute('data-rating');
                    document.getElementById('selectedRating').value = rating;
                    
                    document.querySelectorAll('.star-rating').forEach((s, index) => {
                        if (index < rating) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });

                star.addEventListener('mouseenter', function() {
                    const rating = this.getAttribute('data-rating');
                    document.querySelectorAll('.star-rating').forEach((s, index) => {
                        if (index < rating) {
                            s.style.color = 'var(--earist-gold)';
                        } else {
                            s.style.color = '#ddd';
                        }
                    });
                });
            });

            document.querySelector('.rating').addEventListener('mouseleave', function() {
                const currentRating = document.getElementById('selectedRating').value;
                document.querySelectorAll('.star-rating').forEach((s, index) => {
                    if (index < currentRating) {
                        s.style.color = 'var(--earist-gold)';
                    } else {
                        s.style.color = '#ddd';
                    }
                });
            });
        }

        // Initialize forms
        function initializeForms() {
            // Anonymous checkbox handler
            document.getElementById('anonymousCheck').addEventListener('change', function() {
                const isAnonymous = this.checked;
                const nameField = document.getElementById('participantName');
                const emailField = document.getElementById('participantEmail');
                const contactField = document.getElementById('contactNumber');

                if (isAnonymous) {
                    nameField.value = 'Anonymous';
                    nameField.disabled = true;
                    emailField.disabled = true;
                    contactField.disabled = true;
                } else {
                    nameField.value = '';
                    nameField.disabled = false;
                    emailField.disabled = false;
                    contactField.disabled = false;
                }
            });

            // Form validation
            document.getElementById('feedbackForm').addEventListener('submit', function(e) {
                const rating = document.getElementById('selectedRating').value;
                const feedback = document.getElementById('feedbackText').value.trim();

                if (!rating) {
                    e.preventDefault();
                    alert('Please provide a rating for the program.');
                    return false;
                }

                if (!feedback) {
                    e.preventDefault();
                    alert('Please provide your feedback about the program.');
                    return false;
                }
            });
        }

        // Toggle filters
        function toggleFilters() {
            const filters = document.getElementById('advanced-filters');
            const isVisible = filters.style.display !== 'none';
            filters.style.display = isVisible ? 'none' : 'block';
            
            // Smooth transition
            if (!isVisible) {
                filters.style.opacity = '0';
                filters.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    filters.style.transition = 'all 0.3s ease';
                    filters.style.opacity = '1';
                    filters.style.transform = 'translateY(0)';
                }, 10);
            }
        }

        // Show program details modal
        function showProgramDetails(programId) {
            const program = programs.find(p => p.program_id == programId);
            if (!program) {
                alert('Program details not found.');
                return;
            }

            document.getElementById('programModalLabel').innerHTML = `
                <i class="fas fa-info-circle me-2"></i>${program.title}
            `;

            document.getElementById('modalBody').innerHTML = `
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-4">
                            <span class="program-badge me-2">${program.type_of_service}</span>
                            <span class="badge ${getStatusBadgeClass(program.status)}">${program.status}</span>
                        </div>
                        
                        <h6 class="text-earist">Description</h6>
                        <p class="text-muted mb-4">${program.description}</p>
                        
                        ${program.objectives ? `
                            <h6 class="text-earist">Objectives</h6>
                            <p class="text-muted mb-4">${program.objectives}</p>
                        ` : ''}
                        
                        <h6 class="text-earist">Target Beneficiaries</h6>
                        <p class="text-muted mb-4">${program.target_beneficiaries || 'General Public'}</p>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-light border-0">
                            <div class="card-body">
                                <h6 class="card-title text-earist mb-3">Program Details</h6>
                                
                                <div class="mb-3">
                                    <i class="fas fa-map-marker-alt text-danger me-2"></i>
                                    <strong>Location:</strong><br>
                                    <span class="text-muted">${program.location}</span>
                                    ${program.barangay ? `<br><span class="text-muted">${program.barangay}</span>` : ''}
                                </div>
                                
                                <div class="mb-3">
                                    <i class="fas fa-calendar text-success me-2"></i>
                                    <strong>Date:</strong><br>
                                    <span class="text-muted">${formatDate(program.date_start)}</span>
                                    ${program.date_end ? `<br><span class="text-muted">to ${formatDate(program.date_end)}</span>` : ''}
                                </div>
                                
                                ${program.time_start ? `
                                    <div class="mb-3">
                                        <i class="fas fa-clock text-info me-2"></i>
                                        <strong>Time:</strong><br>
                                        <span class="text-muted">${formatTime(program.time_start)}</span>
                                        ${program.time_end ? ` - ${formatTime(program.time_end)}` : ''}
                                    </div>
                                ` : ''}
                                
                                <div class="mb-3">
                                    <i class="fas fa-users text-warning me-2"></i>
                                    <strong>Participants:</strong><br>
                                    <span class="text-muted">
                                        ${program.status === 'Completed' && program.actual_participants ? 
                                            `${program.actual_participants} attended` : 
                                            `${program.expected_participants || 0} expected`}
                                    </span>
                                </div>
                                
                                ${program.feedback_count > 0 ? `
                                    <div class="mb-3">
                                        <i class="fas fa-comments text-primary me-2"></i>
                                        <strong>Feedback:</strong><br>
                                        <span class="text-muted">${program.feedback_count} responses</span>
                                    </div>
                                ` : ''}
                                
                                ${program.first_name || program.last_name ? `
                                    <div class="mb-3">
                                        <i class="fas fa-user text-secondary me-2"></i>
                                        <strong>Coordinator:</strong><br>
                                        <span class="text-muted">${(program.first_name || '') + ' ' + (program.last_name || '')}</span>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Update interest button
            const interestBtn = document.getElementById('modalInterestBtn');
            if (program.status === 'Completed') {
                interestBtn.style.display = 'none';
            } else {
                interestBtn.style.display = 'inline-block';
                interestBtn.href = `public_homepage.php#request`;
            }

            new bootstrap.Modal(document.getElementById('programModal')).show();
        }

        // Show feedback modal
        function showFeedbackModal(programId, programTitle) {
            document.getElementById('modalProgramId').value = programId;
            document.getElementById('modalProgramTitle').textContent = programTitle;
            
            // Reset form
            document.getElementById('feedbackForm').reset();
            document.querySelectorAll('.star-rating').forEach(star => {
                star.classList.remove('active');
                star.style.color = '#ddd';
            });
            document.getElementById('selectedRating').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('feedbackModal'));
            modal.show();
        }

        // Utility functions
        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function formatTime(timeString) {
            if (!timeString) return '';
            const [hours, minutes] = timeString.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hour12 = hour % 12 || 12;
            return `${hour12}:${minutes} ${ampm}`;
        }

        function getStatusBadgeClass(status) {
            switch(status.toLowerCase()) {
                case 'planned': return 'bg-primary';
                case 'ongoing': return 'bg-warning text-dark';
                case 'completed': return 'bg-success';
                case 'cancelled': return 'bg-danger';
                default: return 'bg-secondary';
            }
        }

        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Auto-hide alerts
        function autoHideAlerts() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        }

        // Add CSS class for text-earist
        const style = document.createElement('style');
        style.textContent = '.text-earist { color: var(--earist-red) !important; }';
        document.head.appendChild(style);
    </script>
</body>
</html>