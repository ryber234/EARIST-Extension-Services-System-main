<?php
require_once '../config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ../dashboard.php');
    exit();
}

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_request'])) {
    $email = sanitizeInput($_POST['email']);
    
    if (empty($email)) {
        $error_message = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // Check if email exists
        $user = $db->fetch("SELECT user_id, username, first_name, last_name, email FROM users WHERE email = ? AND status = 'Active'", [$email]);
        
        if ($user) {
            // Check if user can send password reset (rate limiting)
            if (!canSendPasswordReset($email)) {
                $error_message = "Please wait " . EMAIL_COOLDOWN_MINUTES . " minutes before requesting another password reset.";
            } else {
                // Generate reset token
                $reset_token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store reset token in database
                $sql = "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE token = ?, expires_at = ?";
                
                if ($db->query($sql, [$user['user_id'], $reset_token, $expires_at, $reset_token, $expires_at])) {
                    // Log the password reset request
                    logActivity($user['user_id'], 'Password Reset Requested', 'users', $user['user_id']);
                    
                    // Send the actual email
                    $emailSent = sendPasswordResetEmail(
                        $user['email'], 
                        $user['username'], 
                        $user['first_name'], 
                        $user['last_name'], 
                        $reset_token
                    );
                    
                    if ($emailSent) {
                        $success_message = "Password reset instructions have been sent to your email address. Please check your inbox and spam folder.";
                        
                        // Add notification to user
                        addNotification($user['user_id'], 'Password Reset Request', 'A password reset email was sent to your email address.', 'Warning');
                    } else {
                        $error_message = "Failed to send reset email. Please try again later or contact support.";
                        
                        // Log the email failure
                        error_log("Failed to send password reset email to: " . $email);
                    }
                } else {
                    $error_message = "Failed to process reset request. Please try again.";
                }
            }
        } else {
            // Don't reveal if email exists or not for security
            // But still show success message to prevent email enumeration
            $success_message = "If your email address exists in our system, you will receive password reset instructions shortly.";
        }
    }
}

// Create password_resets table if it doesn't exist
$db->query("CREATE TABLE IF NOT EXISTS password_resets (
    user_id INT PRIMARY KEY,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SYSTEM_ABBR ?> - Forgot Password</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --earist-red: #a7000e;
            --earist-gold: #ffd000;
        }

        body {
            background: linear-gradient(135deg, #a7000e 0%, #8c000c 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 15px;
        }

        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('../images/earist_logo_png.png');
            background-size: 250px 250px;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            opacity: 0.05;
            z-index: -1;
        }

        .forgot-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 380px;
            animation: slideUp 0.6s ease-out;
            border: 2px solid rgba(167, 0, 14, 0.1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .forgot-header {
            background: linear-gradient(135deg, var(--earist-red) 0%, #8c000c 100%);
            padding: 20px 15px;
            text-align: center;
            color: white;
            position: relative;
            border-bottom: 3px solid var(--earist-gold);
        }

        .forgot-header::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('../images/earist_logo_png.png');
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0.1;
            z-index: 0;
        }

        .forgot-header > * {
            position: relative;
            z-index: 1;
        }

        .forgot-logo {
            width: 60px;
            height: 60px;
            margin: 0 auto 12px;
            border-radius: 50%;
            border: 2px solid var(--earist-gold);
            overflow: hidden;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }

        .forgot-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .forgot-logo i {
            font-size: 24px;
            color: var(--earist-red);
        }

        .forgot-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
            color: white;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
        }

        .forgot-subtitle {
            font-size: 14px;
            margin-bottom: 3px;
            color: var(--earist-gold);
            font-weight: 500;
        }

        .forgot-institution {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.8);
        }

        .forgot-form {
            padding: 20px 18px;
            background: rgba(255, 255, 255, 0.98);
        }

        .form-floating {
            margin-bottom: 12px;
        }

        .form-control {
            border: 1px solid #ced4da;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 14px;
            height: auto;
            transition: all 0.3s ease;
        }

        .form-floating label {
            font-size: 13px;
            padding: 0.6rem 0.8rem;
            font-weight: 500;
        }

        .form-control:focus {
            border-color: var(--earist-red);
            box-shadow: 0 0 0 2px rgba(167, 0, 14, 0.1);
            transform: translateY(-1px);
        }

        .form-control.is-valid {
            border-color: #28a745;
            background-color: rgba(40, 167, 69, 0.05);
        }

        .form-control.is-invalid {
            border-color: #dc3545;
            background-color: rgba(220, 53, 69, 0.05);
        }

        .btn-reset {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, var(--earist-red) 0%, #8c000c 100%);
            border: none;
            border-radius: 6px;
            color: white;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(167, 0, 14, 0.3);
        }

        .btn-reset:hover {
            background: linear-gradient(135deg, #8c000c 0%, #a7000e 100%);
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(167, 0, 14, 0.4);
        }

        .btn-reset:active {
            transform: translateY(0);
        }

        .btn-reset:disabled {
            opacity: 0.7;
            transform: none;
            cursor: not-allowed;
        }

        .alert {
            border-radius: 6px;
            border: none;
            padding: 8px 12px;
            margin-bottom: 12px;
            font-size: 13px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c2c7 100%);
            color: #721c24;
            border-left: 3px solid #dc3545;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1e7dd 0%, #a3cfbb 100%);
            color: #0f5132;
            border-left: 3px solid #198754;
        }

        .info-box {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
            text-align: center;
            border: 1px solid #e9ecef;
        }

        .info-box i {
            font-size: 24px;
            color: var(--earist-red);
            margin-bottom: 8px;
            display: block;
        }

        .info-box h6 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .info-box p {
            color: #6c757d;
            font-size: 12px;
            margin: 0;
            line-height: 1.4;
        }

        .email-hint {
            font-size: 11px;
            color: #6c757d;
            margin-top: 5px;
            padding-left: 5px;
        }

        .back-section {
            text-align: center;
            padding: 12px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-top: 1px solid #dee2e6;
        }

        .back-section a {
            color: var(--earist-red);
            text-decoration: none;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .back-section a:hover {
            color: #8c000c;
            text-decoration: underline;
        }

        /* Responsive design */
        @media (max-width: 576px) {
            .forgot-container {
                margin: 10px;
                max-width: 100%;
            }
            
            .forgot-form {
                padding: 15px;
            }
            
            .forgot-header {
                padding: 15px;
            }
            
            .forgot-logo {
                width: 50px;
                height: 50px;
            }
            
            .forgot-title {
                font-size: 18px;
            }
        }

        @media (max-height: 600px) {
            .forgot-header {
                padding: 15px;
            }
            
            .forgot-logo {
                width: 45px;
                height: 45px;
                margin-bottom: 8px;
            }
            
            .forgot-title {
                font-size: 18px;
            }
            
            .forgot-form {
                padding: 15px;
            }
            
            .form-floating {
                margin-bottom: 10px;
            }
            
            .back-section {
                padding: 8px;
            }
        }

        @media (max-height: 500px) {
            .forgot-header {
                padding: 10px;
            }
            
            .forgot-logo {
                width: 40px;
                height: 40px;
                margin-bottom: 6px;
            }
            
            .forgot-title {
                font-size: 16px;
                margin-bottom: 2px;
            }
            
            .forgot-subtitle {
                font-size: 12px;
                margin-bottom: 2px;
            }
            
            .forgot-institution {
                font-size: 10px;
            }
            
            .forgot-form {
                padding: 12px;
            }
            
            .form-floating {
                margin-bottom: 8px;
            }
            
            .btn-reset {
                padding: 8px;
                margin-bottom: 8px;
            }
            
            .back-section {
                padding: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-header">
            <div class="forgot-logo">
                <?php if (file_exists('../images/earist_logo_png.png')): ?>
                    <img src="../images/earist_logo_png.png" alt="EARIST Logo">
                <?php else: ?>
                    <i class="fas fa-key"></i>
                <?php endif; ?>
            </div>
            <h1 class="forgot-title">Forgot Password</h1>
            <p class="forgot-subtitle">Reset your account password</p>
            <p class="forgot-institution"><?= SYSTEM_ABBR ?></p>
        </div>
        
        <form class="forgot-form" method="POST" id="forgotForm" novalidate>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error_message ?? '') ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= $success_message ?>
                </div>
            <?php else: ?>
                <div class="info-box">
                    <i class="fas fa-envelope"></i>
                    <h6>Reset Your Password</h6>
                    <p>Enter your email address and we'll send you instructions to reset your password.</p>
                </div>
                
                <div class="form-floating">
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                           placeholder="Email Address" required 
                           autocomplete="email">
                    <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
                    <div class="email-hint" id="emailHint">Enter your registered email address</div>
                </div>
                
                <button type="submit" name="reset_request" class="btn btn-reset">
                    <i class="fas fa-paper-plane me-2"></i>Send Reset Instructions
                </button>
            <?php endif; ?>

            <?php if ($flash_error = getFlashMessage('error')): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($flash_error ?? '') ?>
                </div>
            <?php endif; ?>
        </form>
        
        <div class="back-section">
            <a href="login.php"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Email validation and styling
        const emailInput = document.getElementById('email');
        const emailHint = document.getElementById('emailHint');

        if (emailInput && emailHint) {
            emailInput.addEventListener('input', function() {
                const email = this.value.trim();
                
                if (email.length > 0) {
                    // Basic email format validation
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (emailRegex.test(email)) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                        emailHint.textContent = 'Valid email format ✓';
                        emailHint.style.color = '#28a745';
                    } else if (email.includes('@')) {
                        this.classList.add('is-invalid');
                        this.classList.remove('is-valid');
                        emailHint.textContent = 'Please enter a valid email address';
                        emailHint.style.color = '#dc3545';
                    } else {
                        this.classList.remove('is-valid', 'is-invalid');
                        emailHint.textContent = 'Enter your registered email address';
                        emailHint.style.color = '#6c757d';
                    }
                } else {
                    this.classList.remove('is-valid', 'is-invalid');
                    emailHint.textContent = 'Enter your registered email address';
                    emailHint.style.color = '#6c757d';
                }
            });
        }

        // Form validation and submission
        document.getElementById('forgotForm').addEventListener('submit', function(e) {
            const email = emailInput.value.trim();

            if (!email) {
                e.preventDefault();
                
                // Show validation error
                let existingAlert = document.querySelector('.validation-alert');
                if (existingAlert) {
                    existingAlert.remove();
                }
                
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger validation-alert';
                alertDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Please enter your email address.';
                
                const form = document.querySelector('.forgot-form');
                form.insertBefore(alertDiv, form.firstChild);
                
                // Remove alert after 5 seconds
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 5000);
                
                emailInput.focus();
                return;
            }

            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                
                // Show email format error
                let existingAlert = document.querySelector('.validation-alert');
                if (existingAlert) {
                    existingAlert.remove();
                }
                
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger validation-alert';
                alertDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Please enter a valid email address.';
                
                const form = document.querySelector('.forgot-form');
                form.insertBefore(alertDiv, form.firstChild);
                
                // Remove alert after 5 seconds
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 5000);
                
                emailInput.focus();
                return;
            }

            // Show loading state
            const btn = document.querySelector('.btn-reset');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
                btn.disabled = true;
            }
        });

        // Focus on email input
        if (emailInput) {
            emailInput.focus();
        }

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert:not(.validation-alert)');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) alert.remove();
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>