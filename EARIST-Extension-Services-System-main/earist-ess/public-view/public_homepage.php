<?php
require_once '../config.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_request'])) {
        // Handle program request submission
        $requester_name = sanitizeInput($_POST['requester_name']);
        $requester_email = sanitizeInput($_POST['requester_email']);
        $contact_number = sanitizeInput($_POST['contact_number']);
        $organization = sanitizeInput($_POST['organization']);
        $barangay = sanitizeInput($_POST['barangay']);
        $municipality = sanitizeInput($_POST['municipality']);
        $program_type = sanitizeInput($_POST['program_type']);
        $preferred_date = $_POST['preferred_date'] ?: null;
        $program_title = sanitizeInput($_POST['program_title']);
        $program_description = sanitizeInput($_POST['program_description']);
        
        $sql = "INSERT INTO program_requests (requester_name, requester_email, contact_number, organization, 
                barangay, municipality, program_type, preferred_date, program_title, program_description, 
                status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";
        
        $params = [$requester_name, $requester_email, $contact_number, $organization, $barangay, 
                  $municipality, $program_type, $preferred_date, $program_title, $program_description];
        
        if ($db->query($sql, $params)) {
            $success_message = "Your program request has been submitted successfully! We'll get back to you soon.";
        } else {
            $error_message = "Failed to submit your request. Please try again.";
        }
    } 
    elseif (isset($_POST['submit_feedback'])) {
        // Handle feedback submission
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
        
        $params = [$program_id, $participant_name, $participant_email, $contact_number, 
                  $rating, $feedback_text, $suggestions, $is_anonymous];
        
        if ($db->query($sql, $params)) {
            $success_message = "Thank you for your feedback! Your input helps us improve our programs.";
        } else {
            $error_message = "Failed to submit your feedback. Please try again.";
        }
    }
}

// Fetch data from database
$programs = $db->fetchAll("SELECT * FROM programs WHERE approval_status = 'Approved' ORDER BY date_start DESC LIMIT 6");
$featured_programs = $db->fetchAll("SELECT * FROM programs WHERE approval_status = 'Approved' AND status = 'Completed' ORDER BY actual_participants DESC LIMIT 3");
$program_types = $db->fetchAll("SELECT DISTINCT type_of_service FROM programs WHERE approval_status = 'Approved'");
$public_stats = $db->fetch("SELECT 
    COUNT(*) as total_programs,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_programs,
    SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END) as ongoing_programs,
    COALESCE(SUM(actual_participants), 0) as total_beneficiaries
    FROM programs WHERE approval_status = 'Approved'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SYSTEM_NAME ?> - Extension Services</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --earist-red:rgb(163, 0, 14);
            --earist-gold: #ffd000;
            --earist-light: #fff9e6;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        /* Header/Navbar */
        .navbar {
            background:rgb(255, 255, 255) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--earist-red) !important;
        }

        .nav-link {
            font-weight: 500;
            color: #555 !important;
        }

        .nav-link:hover {
            color: var(--earist-red) !important;
        }

        /* Hero Section */
        .hero-section {
            background: var(--earist-red);
            color: white;
            padding: 100px 0;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
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

        .hero-title {
            font-weight: 700;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.3);
        }

        .hero-stats {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(5px);
            border-radius: 10px;
            padding: 20px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
        }

        /* Buttons */
        .btn-earist {
            background-color: var(--earist-red);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .btn-earist:hover {
            background-color: #8c000c;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(167,0,14,0.3);
        }

        .btn-earist-outline {
            border: 2px solid var(--earist-red);
            color: var(--earist-red);
            background: transparent;
        }

        .btn-earist-outline:hover {
            background-color: var(--earist-red);
            color: white;
        }

        /* Cards */
        .program-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            height: 100%;
        }

        .program-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .program-badge {
            background-color: var(--earist-gold);
            color: #333;
            font-weight: 600;
        }

        /* Sections */
        .section {
            padding: 80px 0;
        }

        .section-title {
            position: relative;
            margin-bottom: 40px;
        }

        .section-title h2 {
            font-weight: 700;
            color: var(--earist-red);
        }

        .section-title h2::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background: var(--earist-gold);
            margin: 15px auto 0;
        }

        /* Section Header */
        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-header .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--earist-red);
            margin-bottom: 15px;
            position: relative;
        }

        .section-header .section-title::after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: var(--earist-gold);
            margin: 20px auto 0;
            border-radius: 2px;
        }

        .section-subtitle {
            font-size: 1.1rem;
            color: #6c757d;
            max-width: 600px;
            margin: 0 auto;
        }

        /* About Features */
        .about-feature {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
        }

        .about-feature:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: var(--earist-red);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            transition: all 0.3s ease;
        }

        .about-feature:hover .feature-icon {
            background: var(--earist-gold);
            color: #333;
            transform: scale(1.1);
        }

        .about-feature h4 {
            font-weight: 600;
            color: var(--earist-red);
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .about-feature p {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 0;
        }

        /* Forms */
        .form-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
        }

        .form-control:focus {
            border-color: var(--earist-red);
            box-shadow: 0 0 0 3px rgba(167,0,14,0.1);
        }

        /* Footer */
        .footer {
            background: var(--earist-red);
            color: white;
            padding: 60px 0 30px;
        }

        .footer a {
            color: var(--earist-light);
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer a:hover {
            color: var(--earist-gold);
        }

        .footer h5 {
            color: var(--earist-gold);
            font-weight: 600;
            margin-bottom: 20px;
        }

        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* New styles for Extension Info section */
        .text-earist-red {
            color: var(--earist-red);
        }

        .text-earist-gold {
            color: var(--earist-gold);
        }

        .bg-earist-red {
            background-color: var(--earist-red);
        }

        .card {
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }

        /* Star Rating */
        .rating {
            display: flex;
            gap: 5px;
            margin-bottom: 10px;
        }

        .star-rating {
            color: #ddd;
            cursor: pointer;
            transition: color 0.3s;
        }

        .star-rating:hover,
        .star-rating.active {
            color: var(--earist-gold);
        }

        /* Alert Styles */
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-section {
                padding: 80px 0;
            }
            
            .section {
                padding: 60px 0;
            }

            .hero-section::before {
                background-size: 200px 200px;
            }

            .section-header .section-title {
                font-size: 2rem;
            }

            .about-feature {
                margin-bottom: 30px;
            }
        }

        @media (max-width: 576px) {
            .hero-section::before {
                background-size: 150px 150px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="../images/logo.png" height="40" class="me-2" alt="EARIST Logo">
                <?= SYSTEM_ABBR?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#programs">Programs</a></li>
                    <li class="nav-item"><a class="nav-link" href="#request">Request</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#extension-info">Extension Info</a></li>
                    <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                        <a href="../authentication/login.php" class="btn btn-earist-outline">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Success/Error Messages -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 80px; right: 20px; z-index: 1050; max-width: 400px;">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 80px; right: 20px; z-index: 1050; max-width: 400px;">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 text-center text-lg-start mb-5 mb-lg-0 fade-in">
                    <h1 class="hero-title display-4 mb-4">EARIST Extension Services</h1>
                    <p class="lead mb-4">Bridging academic excellence with community development through impactful extension programs.</p>
                    <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                        <a href="#programs" class="btn btn-earist btn-lg">
                            <i class="fas fa-calendar-alt me-2"></i>View Programs
                        </a>
                        <a href="#request" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-handshake me-2"></i>Request Program
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 fade-in">
                    <div class="hero-stats">
                        <div class="row text-center">
                            <div class="col-6 col-md-3 mb-4 mb-md-0">
                                <div class="stat-number"><?= number_format($public_stats['total_programs']) ?></div>
                                <div>Programs</div>
                            </div>
                            <div class="col-6 col-md-3 mb-4 mb-md-0">
                                <div class="stat-number"><?= number_format($public_stats['completed_programs']) ?></div>
                                <div>Completed</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-number"><?= number_format($public_stats['total_beneficiaries']) ?></div>
                                <div>Beneficiaries</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-number"><?= number_format($public_stats['ongoing_programs']) ?></div>
                                <div>Ongoing</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Programs Section -->
    <section id="programs" class="section bg-light">
        <div class="container">
            <div class="section-title text-center fade-in">
                <h2>Our Extension Programs</h2>
                <p class="lead">Discover our current and upcoming community programs</p>
            </div>
            
            <div class="row g-4">
                <?php foreach ($programs as $program): ?>
                <div class="col-md-6 col-lg-4 fade-in">
                    <div class="program-card card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-3">
                                <span class="badge program-badge"><?= htmlspecialchars($program['type_of_service']) ?></span>
                                <small class="text-muted"><?= formatDate($program['date_start']) ?></small>
                            </div>
                            <h5 class="card-title"><?= htmlspecialchars($program['title']) ?></h5>
                            <p class="card-text text-muted">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?= htmlspecialchars($program['location']) ?>
                            </p>
                            <p class="card-text"><?= htmlspecialchars(substr($program['description'], 0, 100)) ?>...</p>
                        </div>
                        <div class="card-footer bg-white border-0">
                            <a href="program-detail.php?id=<?= $program['program_id'] ?>" class="btn btn-sm btn-earist">
                                View Details
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-5 fade-in">
                <a href="programs.php" class="btn btn-earist btn-lg">
                    <i class="fas fa-arrow-right me-2"></i>View All Programs
                </a>
            </div>
        </div>
    </section>

    <!-- Featured Programs -->
    <section id="featured" class="section">
        <div class="container">
            <div class="section-title text-center fade-in">
                <h2>Featured Programs</h2>
                <p class="lead">Our most impactful community initiatives</p>
            </div>
            
            <div class="row g-4">
                <?php foreach ($featured_programs as $program): ?>
                <div class="col-lg-4 fade-in">
                    <div class="program-card card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-3">
                                <span class="badge program-badge"><?= htmlspecialchars($program['type_of_service']) ?></span>
                                <small class="text-muted">Completed</small>
                            </div>
                            <h5 class="card-title"><?= htmlspecialchars($program['title']) ?></h5>
                            <p class="card-text text-muted">
                                <i class="fas fa-users me-1"></i>
                                <?= number_format($program['actual_participants']) ?> participants
                            </p>
                            <p class="card-text"><?= htmlspecialchars(substr($program['description'], 0, 120)) ?>...</p>
                        </div>
                        <div class="card-footer bg-white border-0 d-flex justify-content-between">
                            <a href="program-detail.php?id=<?= $program['program_id'] ?>" class="btn btn-sm btn-earist">
                                View Details
                            </a>
                            <button class="btn btn-sm btn-outline-secondary" 
                                    onclick="showFeedbackModal(<?= $program['program_id'] ?>, '<?= htmlspecialchars($program['title']) ?>')">
                                Give Feedback
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Request Program Section -->
    <section id="request" class="section bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="section-title text-center fade-in">
                        <h2>Request a Program</h2>
                        <p class="lead">Let us know how we can serve your community</p>
                    </div>
                    
                    <div class="form-card fade-in">
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Your Name *</label>
                                    <input type="text" name="requester_name" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" name="requester_email" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contact Number</label>
                                    <input type="tel" name="contact_number" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Organization</label>
                                    <input type="text" name="organization" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Barangay *</label>
                                    <input type="text" name="barangay" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Municipality/City *</label>
                                    <input type="text" name="municipality" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Program Type *</label>
                                    <select name="program_type" class="form-select" required>
                                        <option value="">Select Type</option>
                                        <?php foreach ($program_types as $type): ?>
                                            <option value="<?= htmlspecialchars($type['type_of_service']) ?>">
                                                <?= htmlspecialchars($type['type_of_service']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Preferred Date</label>
                                    <input type="date" name="preferred_date" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Program Title *</label>
                                    <input type="text" name="program_title" class="form-control" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Program Description *</label>
                                    <textarea name="program_description" class="form-control" rows="4" required></textarea>
                                </div>
                                <div class="col-12 text-center mt-4">
                                    <button type="submit" name="submit_request" class="btn btn-earist btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Request
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="section">
        <div class="container">
            <div class="section-header fade-in">
                <h2 class="section-title">About Extension Services</h2>
                <p class="section-subtitle">EARIST's commitment to community development and academic excellence</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4 fade-in">
                    <div class="about-feature">
                        <div class="feature-icon">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                        <h4>Community Partnership</h4>
                        <p>Building strong partnerships between the university and local communities through collaborative programs that foster mutual growth and development.</p>
                    </div>
                </div>
                <div class="col-md-4 fade-in">
                    <div class="about-feature">
                        <div class="feature-icon">
                            <i class="fas fa-graduation-cap fa-2x"></i>
                        </div>
                        <h4>Academic Integration</h4>
                        <p>Connecting classroom learning with real-world application through community-based extension activities that enhance educational experience.</p>
                    </div>
                </div>
                <div class="col-md-4 fade-in">
                    <div class="about-feature">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                        <h4>Sustainable Impact</h4>
                        <p>Creating lasting positive change in communities through well-planned and executed extension programs that address real needs.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Extension Services Info Section -->
    <section id="extension-info" class="section bg-light">
        <div class="container">
            <div class="section-title text-center fade-in">
                <h2>Extension Services</h2>
                <p class="lead">Bridging academic excellence with community development</p>
            </div>
            
            <div class="row">
                <div class="col-lg-6 fade-in">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h4 class="card-title text-earist-red mb-4">Introduction</h4>
                            <p class="card-text">
                                Extension, as one of the quadruple functions of higher educational institutions, translates 
                                the academic institutions' involvement in community development and people empowerment. It is 
                                an avenue where relevance and responsiveness of curricular programs are validated by enriched 
                                quality of people's lives and responding to community needs.
                            </p>
                            <p class="card-text">
                                Essentially, Extension Services enable the academic institution to be a catalyst in social 
                                transformation of the students, faculty and communities through the developmental, integrated, 
                                comprehensive and sustainable programs, projects and activities.
                            </p>
                            <p class="card-text">
                                The Eulogio "Amang" Rodriguez Institute of Science and Technology (EARIST) Extension Program 
                                is considered as a set of projects and activities involving alumni relation, linkages, 
                                placements, community development Livelihood opportunity program regularly undertaken by 
                                faculty, staff and students through the Office of the Extension Services of the Institute 
                                and the college/Office Base Extension Units.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 fade-in">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h4 class="card-title text-earist-red mb-4">Mission, Vision & Core Values</h4>
                            
                            <div class="mb-4">
                                <h5 class="text-earist-gold">Vision</h5>
                                <p>"A dynamic services oriented center for community development and people empowerment."</p>
                            </div>
                            
                            <div class="mb-4">
                                <h5 class="text-earist-gold">Mission</h5>
                                <p>"Generate extension projects for effective technology transfer, continuing education, and training for self-reliance and community welfare."</p>
                            </div>
                            
                            <div class="mb-4">
                                <h5 class="text-earist-gold">Goals</h5>
                                <ol>
                                    <li>Develop and deliver appropriate programs/projects/activities which are responsive to the needs of its clientele</li>
                                    <li>Upgrade competence, work skills and competitiveness of out-of-school youth</li>
                                    <li>Provide technology transfer for sustainable socio-economic development</li>
                                </ol>
                            </div>
                            
                            <div class="mb-4">
                                <h5 class="text-earist-gold">Objectives</h5>
                                <ol>
                                    <li>Conduct skills development, entrepreneurship training, and community education needed by service sector</li>
                                    <li>Assist small and medium scale enterprises by sharing the various expertise of the institute</li>
                                    <li>Undertake identification and assessment of gaps and needs in extension service sectors</li>
                                    <li>Establish and maintain good relationship with funding donors, sponsors, and other benefactors</li>
                                    <li>Sustain alumni support for programs and projects of the Institute</li>
                                </ol>
                            </div>
                            
                            <div class="mb-4">
                                <h5 class="text-earist-gold">Core Values</h5>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-earist-red">Excellence</span>
                                    <span class="badge bg-earist-red">Community Service</span>
                                    <span class="badge bg-earist-red">Servant Leadership</span>
                                    <span class="badge bg-earist-red">Humanity</span>
                                    <span class="badge bg-earist-red">Commitment</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h4 class="text-white mb-4">
                        <img src="../images/logo.png" height="40" class="me-2" alt="EARIST Logo">
                        <?= SYSTEM_ABBR?>
                    </h4>
                    <p>Extension Services System</p>
                    <p>Bridging academic excellence with community development.</p>
                </div>
                <div class="col-lg-2 col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#home">Home</a></li>
                        <li class="mb-2"><a href="#programs">Programs</a></li>
                        <li class="mb-2"><a href="#request">Request</a></li>
                        <li class="mb-2"><a href="#about">About</a></li>
                        <li class="mb-2"><a href="#extension-info">Extension Info</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4">
                    <h5>Contact Us</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-envelope me-2"></i> earistofficial1945@gmail.com</li>
                        <li class="mb-2"><i class="fas fa-phone me-2"></i> (028)243-9467</li>
                        <li class="mb-2"><i class="fas fa-map-marker-alt me-2"></i> Nagtahan St, Sampaloc, Manila, 1008 Metro Manila</li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4">
                    <h5>Connect</h5>
                    <div class="d-flex gap-3">
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

    <!-- Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-earist-red text-white">
                    <h5 class="modal-title">Program Feedback</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="program_id" id="modalProgramId">
                        <div class="alert alert-info">
                            <strong>Program:</strong> <span id="modalProgramTitle"></span>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Your Name *</label>
                            <input type="text" name="participant_name" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="participant_email" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" name="contact_number" class="form-control">
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
                            <textarea name="feedback_text" class="form-control" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Suggestions</label>
                            <textarea name="suggestions" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_anonymous" id="anonymousCheck">
                            <label class="form-check-label" for="anonymousCheck">Submit anonymously</label>
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
        // Initialize animations
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

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.padding = '10px 0';
                navbar.style.boxShadow = '0 2px 15px rgba(0,0,0,0.1)';
            } else {
                navbar.style.padding = '20px 0';
                navbar.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
            }
        });

        // Auto-close navbar toggle when nav link is clicked
        document.querySelectorAll('.navbar-nav .nav-link').forEach(navLink => {
            navLink.addEventListener('click', () => {
                const navbarToggler = document.querySelector('.navbar-toggler');
                const navbarCollapse = document.querySelector('#navbarNav');
                
                // Check if navbar is expanded (mobile view)
                if (navbarCollapse.classList.contains('show')) {
                    // Close the navbar
                    const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                        toggle: false
                    });
                    bsCollapse.hide();
                }
            });
        });

        // Also close navbar when clicking on buttons inside navbar
        document.querySelectorAll('.navbar .btn').forEach(button => {
            button.addEventListener('click', () => {
                const navbarCollapse = document.querySelector('#navbarNav');
                if (navbarCollapse.classList.contains('show')) {
                    const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                        toggle: false
                    });
                    bsCollapse.hide();
                }
            });
        });

        // Feedback modal
        function showFeedbackModal(programId, programTitle) {
            document.getElementById('modalProgramId').value = programId;
            document.getElementById('modalProgramTitle').textContent = programTitle;
            
            // Reset rating
            document.querySelectorAll('.star-rating').forEach(star => {
                star.classList.remove('active');
            });
            document.getElementById('selectedRating').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('feedbackModal'));
            modal.show();
        }

        // Star rating
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
        });

        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                const forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Smooth scrolling for anchor links
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

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>