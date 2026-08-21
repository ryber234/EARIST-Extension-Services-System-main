<?php
require_once '../config.php';

// Get program ID from URL - FIXED: was using 'user_id' instead of 'id'
$program_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$program_id) {
    header('Location: public_homepage.php');
    exit();
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
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
    
    // Validation
    $errors = [];
    if (empty($participant_name) || empty($feedback_text) || $rating < 1 || $rating > 5) {
        $errors[] = "Please fill in all required fields.";
    }
    
    if (empty($errors)) {
        $sql = "INSERT INTO program_feedback (program_id, participant_name, participant_email, contact_number, rating, feedback_text, suggestions, is_anonymous, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        if ($db->query($sql, [$program_id, $participant_name, $participant_email, $contact_number, $rating, $feedback_text, $suggestions, $is_anonymous])) {
            setSuccessMessage('Thank you for your feedback!');
            header('Location: program-detail.php?id=' . $program_id);
            exit();
        } else {
            $errors[] = "Failed to submit feedback. Please try again.";
        }
    }
}

// Fetch program details
$program = $db->fetch("SELECT * FROM programs WHERE program_id = ? AND approval_status = 'Approved'", [$program_id]);

if (!$program) {
    header('Location: public_homepage.php');
    exit();
}

// Fetch program organizers/faculty (if table exists)
$organizers = [];
try {
    $organizers = $db->fetchAll("SELECT u.first_name, u.last_name, u.email, u.department 
                                FROM program_faculty pf 
                                JOIN users u ON pf.faculty_id = u.user_id 
                                WHERE pf.program_id = ?", [$program_id]);
} catch (Exception $e) {
    // Table might not exist, continue without organizers
    $organizers = [];
}

// Fetch related programs
$related_programs = $db->fetchAll("SELECT * FROM programs 
                                  WHERE type_of_service = ? AND program_id != ? AND approval_status = 'Approved' 
                                  ORDER BY date_start DESC LIMIT 3", 
                                  [$program['type_of_service'], $program_id]);

// Calculate average rating
$avg_rating = $db->fetch("SELECT AVG(rating) as avg_rating, COUNT(*) as total_feedback 
                         FROM program_feedback WHERE program_id = ?", [$program_id]);

// Set default values for missing fields
$program['status'] = $program['status'] ?? 'Planned';
$program['type_of_service'] = $program['type_of_service'] ?? 'Extension';
$program['objectives'] = $program['objectives'] ?? '';
$program['target_beneficiaries'] = $program['target_beneficiaries'] ?? '';

// Determine program status based on dates
$current_date = date('Y-m-d');
$start_date = $program['date_start'];
$end_date = $program['date_end'] ?? $start_date;

if ($start_date > $current_date) {
    $program['status'] = 'Upcoming';
} elseif ($end_date < $current_date) {
    $program['status'] = 'Completed';
} elseif ($start_date <= $current_date && $end_date >= $current_date) {
    $program['status'] = 'Ongoing';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($program['title']) ?> - <?= SYSTEM_ABBR ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --earist-red: #a7000e;
            --earist-gold: #ffd000;
            --earist-light: #fff9e6;
            --earist-dark: #8c000c;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            line-height: 1.6;
        }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            padding: 15px 0;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--earist-red) !important;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }

        .navbar-brand img {
            height: 40px;
            margin-right: 10px;
        }

        .nav-link {
            font-weight: 500;
            color: #333 !important;
            margin: 0 10px;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: var(--earist-red) !important;
        }

        /* Hero Section */
        .program-hero {
            background: linear-gradient(135deg, var(--earist-red) 0%, var(--earist-dark) 100%);
            color: white;
            padding: 120px 0 80px;
            position: relative;
            overflow: hidden;
        }

        .program-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('../images/earist_logo_png.png') center center no-repeat;
            background-size: 300px 300px;
            opacity: 0.1;
            z-index: 0;
        }

        .program-hero-content {
            position: relative;
            z-index: 1;
        }

        .program-title {
            font-weight: 800;
            font-size: 2.8rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .program-meta {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 25px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        /* Status Badges */
        .status-badge {
            font-size: 0.9rem;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }

        .status-ongoing {
            background: #28a745;
            color: white;
        }

        .status-completed {
            background: var(--earist-gold);
            color: #333;
        }

        .status-upcoming {
            background: #17a2b8;
            color: white;
        }
         .status-planned {
            background: #6f42c1;
            color: white;
        }

        /* Buttons */
        .btn-earist {
            background: var(--earist-red);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-earist:hover {
            background: var(--earist-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(167,0,14,0.3);
        }

        .btn-earist-outline {
            border: 2px solid var(--earist-red);
            color: var(--earist-red);
            background: transparent;
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-earist-outline:hover {
            background: var(--earist-red);
            color: white;
        }

        /* Content Cards */
        .content-card {
            background: white;
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 30px;
        }

        .content-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .card-header-custom {
            background: var(--earist-red);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 20px 25px;
            border: none;
        }

        .card-header-custom h4 {
            margin: 0;
            font-weight: 600;
        }

        /* Info Items */
        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: var(--earist-light);
            color: var(--earist-red);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .info-content h6 {
            margin: 0 0 5px 0;
            color: #333;
            font-weight: 600;
        }

        .info-content p {
            margin: 0;
            color: #6c757d;
        }

        /* Rating Stars */
        .rating-stars {
            color: var(--earist-gold);
            margin-right: 10px;
        }

        .rating-display .fas {
            margin-right: 2px;
        }

        /* Forms */
        .form-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            padding: 30px;
            margin-bottom: 30px;
        }

        .form-control {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--earist-red);
            box-shadow: 0 0 0 3px rgba(167,0,14,0.1);
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        /* Related Programs */
        .related-program {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .related-program:hover {
            border-color: var(--earist-red);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            color: inherit;
            text-decoration: none;
        }

        .breadcrumb-nav {
            background: white;
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .breadcrumb {
            background: none;
            margin: 0;
        }

        .breadcrumb-item a {
            color: var(--earist-red);
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: #6c757d;
        }

        /* Star Rating Input */
        .rating-input {
            display: flex;
            gap: 5px;
        }

        .star-rating {
            cursor: pointer;
            transition: color 0.2s;
            font-size: 1.5rem;
        }

        .star-rating:hover {
            color: var(--earist-gold) !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .program-hero {
                padding: 100px 0 60px;
            }
            
            .program-title {
                font-size: 2.2rem;
            }
            
            .info-item {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .info-icon {
                margin-bottom: 10px;
                margin-right: 0;
            }
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
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

    <!-- Breadcrumb -->
    <div class="breadcrumb-nav">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="public_homepage.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="public_homepage.php#programs">Programs</a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($program['title']) ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Program Hero -->
    <section class="program-hero">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 program-hero-content">
                    <div class="mb-3">
                        <span class="badge status-badge status-<?= strtolower($program['status']) ?>">
                            <?= htmlspecialchars($program['status']) ?>
                        </span>
                        <span class="badge bg-light text-dark ms-2">
                            <?= htmlspecialchars($program['type_of_service']) ?>
                        </span>
                    </div>
                    <h1 class="program-title"><?= htmlspecialchars($program['title']) ?></h1>
                    <p class="lead mb-4"><?= htmlspecialchars($program['description']) ?></p>
                    
                    <div class="d-flex flex-wrap gap-3">
                        <a href="public_homepage.php#request" class="btn btn-earist btn-lg">
                            <i class="fas fa-plus me-2"></i>Request Similar Program
                        </a>
                        
                        <a href="public_homepage.php#programs" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-arrow-left me-2"></i>Back to Programs
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="program-meta">
                        <h5 class="text-white mb-3">Program Information</h5>
                        
                        <div class="info-item">
                            <div class="info-icon bg-light">
                                <i class="fas fa-calendar text-danger"></i>
                            </div>
                            <div class="info-content">
                                <h6 class="text-white">Start Date</h6>
                                <p class="text-light"><?= formatDate($program['date_start']) ?></p>
                            </div>
                        </div>
                        
                        <?php if (!empty($program['date_end'])): ?>
                        <div class="info-item">
                            <div class="info-icon bg-light">
                                <i class="fas fa-calendar-check text-danger"></i>
                            </div>
                            <div class="info-content">
                                <h6 class="text-white">End Date</h6>
                                <p class="text-light"><?= formatDate($program['date_end']) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <div class="info-icon bg-light">
                                <i class="fas fa-map-marker-alt text-danger"></i>
                            </div>
                            <div class="info-content">
                                <h6 class="text-white">Location</h6>
                                <p class="text-light"><?= htmlspecialchars($program['location']) ?></p>
                                <?php if (!empty($program['barangay'])): ?>
                                    <p class="text-light"><small><?= htmlspecialchars($program['barangay']) ?></small></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon bg-light">
                                <i class="fas fa-users text-danger"></i>
                            </div>
                            <div class="info-content">
                                <h6 class="text-white">Participants</h6>
                                <p class="text-light">
                                    <?php if ($program['status'] === 'Completed' && $program['actual_participants']): ?>
                                        <?= number_format($program['actual_participants']) ?> actual participants
                                    <?php else: ?>
                                        <?= number_format($program['expected_participants']) ?> expected participants
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if ($avg_rating && $avg_rating['total_feedback'] > 0): ?>
                        <div class="info-item">
                            <div class="info-icon bg-light">
                                <i class="fas fa-star text-warning"></i>
                            </div>
                            <div class="info-content">
                                <h6 class="text-white">Rating</h6>
                                <p class="text-light">
                                    <?= number_format($avg_rating['avg_rating'], 1) ?>/5 
                                    (<?= $avg_rating['total_feedback'] ?> reviews)
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container my-5">
        <?php if ($success_message = getFlashMessage('success')): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <!-- Program Details -->
                <div class="content-card">
                    <div class="card-header-custom">
                        <h4><i class="fas fa-info-circle me-2"></i>Program Details</h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="info-content">
                                        <h6>Duration</h6>
                                        <p>
                                            <?= formatDate($program['date_start']) ?>
                                            <?php if (!empty($program['date_end']) && $program['date_end'] !== $program['date_start']): ?>
                                                - <?= formatDate($program['date_end']) ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="info-content">
                                        <h6>Time</h6>
                                        <p>
                                            <?php if (!empty($program['time_start'])): ?>
                                                <?= date('g:i A', strtotime($program['time_start'])) ?>
                                                <?php if (!empty($program['time_end'])): ?>
                                                    - <?= date('g:i A', strtotime($program['time_end'])) ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                TBA
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fa-solid fa-peso-sign"></i>
                                    </div>
                                    <div class="info-content">
                                        <h6>Budget</h6>
                                        <p>
                                            <?php if (!empty($program['budget_allocated'])): ?>
                                                <?= formatCurrency($program['budget_allocated']) ?>
                                            <?php else: ?>
                                                TBA
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-tag"></i>
                                    </div>
                                    <div class="info-content">
                                        <h6>Service Type</h6>
                                        <p><?= htmlspecialchars($program['type_of_service']) ?></p>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-flag"></i>
                                    </div>
                                    <div class="info-content">
                                        <h6>Status</h6>
                                        <p><?= htmlspecialchars($program['status']) ?></p>
                                    </div>
                                </div>

                                <?php if (!empty($program['target_beneficiaries'])): ?>
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-bullseye"></i>
                                    </div>
                                    <div class="info-content">
                                        <h6>Target Beneficiaries</h6>
                                        <p><?= htmlspecialchars($program['target_beneficiaries']) ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($program['objectives'])): ?>
                        <div class="mt-4">
                            <h5 class="text-danger mb-3">Objectives</h5>
                            <p><?= nl2br(htmlspecialchars($program['objectives'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Organizers/Faculty -->
                <?php if (!empty($organizers)): ?>
                <div class="content-card">
                    <div class="card-header-custom">
                        <h4><i class="fas fa-users me-2"></i>Program Organizers</h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <?php foreach ($organizers as $organizer): ?>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="info-icon me-3">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($organizer['first_name'] . ' ' . $organizer['last_name']) ?></h6>
                                        <p class="mb-0 text-muted"><?= htmlspecialchars($organizer['department'] ?? 'N/A') ?></p>
                                        <small class="text-muted"><?= htmlspecialchars($organizer['email'] ?? 'N/A') ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Related Programs -->
                <?php if (!empty($related_programs)): ?>
                <div class="content-card">
                    <div class="card-header-custom">
                        <h4><i class="fas fa-list me-2"></i>Related Programs</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php foreach ($related_programs as $related): ?>
                        <a href="program-detail.php?id=<?= $related['program_id'] ?>" class="related-program">
                            <h6 class="mb-2"><?= htmlspecialchars($related['title']) ?></h6>
                            <p class="mb-1 text-muted">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?= htmlspecialchars($related['location']) ?>
                            </p>
                            <small class="text-muted"><?= formatDate($related['date_start']) ?></small>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="content-card">
                    <div class="card-header-custom">
                        <h4><i class="fas fa-bolt me-2"></i>Quick Actions</h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-grid gap-2">
                            <button class="btn btn-earist" data-bs-toggle="modal" data-bs-target="#feedbackModal">
                                <i class="fas fa-star me-2"></i>Submit Feedback
                            </button>
                            
                            <a href="public_homepage.php#request" class="btn btn-earist-outline">
                                <i class="fas fa-plus me-2"></i>Request New Program
                            </a>
                            
                            <a href="public_homepage.php#programs" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Programs
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--earist-red); color: white;">
                    <h5 class="modal-title">Program Feedback</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>Program:</strong> <?= htmlspecialchars($program['title']) ?>
                        </div>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Your Name <span class="text-danger">*</span></label>
                                <input type="text" name="participant_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="participant_email" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Number</label>
                                <input type="tel" name="contact_number" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Rating <span class="text-danger">*</span></label>
                                <div class="rating-input">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star star-rating" data-rating="<?= $i ?>" style="cursor: pointer; color: #ddd;"></i>
                                    <?php endfor; ?>
                                    <input type="hidden" name="rating" id="selectedRating" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Your Feedback <span class="text-danger">*</span></label>
                                <textarea name="feedback_text" class="form-control" rows="4" required placeholder="Share your experience with this program..."></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Suggestions for Improvement</label>
                                <textarea name="suggestions" class="form-control" rows="3" placeholder="Any suggestions to make this program better?"></textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_anonymous" id="anonymousCheck">
                                    <label class="form-check-label" for="anonymousCheck">Submit anonymously</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_feedback" class="btn btn-earist">Submit Feedback</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Star rating functionality
        document.querySelectorAll('.star-rating').forEach(star => {
            star.addEventListener('click', function() {
                const rating = this.getAttribute('data-rating');
                document.getElementById('selectedRating').value = rating;
                
                document.querySelectorAll('.star-rating').forEach((s, index) => {
                    if (index < rating) {
                        s.style.color = 'var(--earist-gold)';
                    } else {
                        s.style.color = '#ddd';
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

        document.querySelector('.rating-input').addEventListener('mouseleave', function() {
            const selectedRating = document.getElementById('selectedRating').value;
            document.querySelectorAll('.star-rating').forEach((s, index) => {
                if (index < selectedRating) {
                    s.style.color = 'var(--earist-gold)';
                } else {
                    s.style.color = '#ddd';
                }
            });
        });

        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);
    </script>
</body>
</html>