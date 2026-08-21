<?php
require_once '../config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ../dashboard.php');
    exit();
}

$token = $_GET['token'] ?? '';
$valid_token = false;
$user = null;

// Validate token
if ($token) {
    // Check if token exists and is not expired
    $reset_data = $db->fetch("
        SELECT pr.user_id, pr.expires_at, u.username, u.email, u.first_name, u.last_name
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.user_id
        WHERE pr.token = ? AND pr.expires_at > NOW() AND u.status = 'Active'", [$token]);
    
    if ($reset_data) {
        $valid_token = true;
        $user = $reset_data;
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password']) && $valid_token) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    if (empty($new_password)) {
        $errors[] = "Please enter a new password.";
    }
    
    if (strlen($new_password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    
    // Check for password strength
    if (!empty($new_password)) {
        if (!preg_match('/[A-Za-z]/', $new_password)) {
            $errors[] = "Password must contain at least one letter.";
        }
        if (!preg_match('/[0-9]/', $new_password)) {
            $errors[] = "Password must contain at least one number.";
        }
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        if ($db->query("UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?", [$hashed_password, $user['user_id']])) {
            // Delete the reset token
            $db->query("DELETE FROM password_resets WHERE user_id = ?", [$user['user_id']]);
            
            // Log the password reset
            logActivity($user['user_id'], 'Password Reset Completed', 'users', $user['user_id']);
            
            // Add notification
            addNotification($user['user_id'], 'Password Reset Successful', 'Your password has been successfully reset.', 'Success');
            
            // Send email notification about password change
            $email_subject = "Password Changed - " . SYSTEM_ABBR;
            $email_body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: #a7000e; color: white; padding: 20px; text-align: center;'>
                    <h2>" . SYSTEM_ABBR . "</h2>
                    <p>Password Changed Successfully</p>
                </div>
                <div style='padding: 20px; background: white;'>
                    <h3>Hello " . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . ",</h3>
                    <p>Your password has been successfully changed for your " . SYSTEM_ABBR . " account.</p>
                    <p><strong>Details:</strong></p>
                    <ul>
                        <li>Account: " . htmlspecialchars($user['username']) . "</li>
                        <li>Email: " . htmlspecialchars($user['email']) . "</li>
                        <li>Date: " . date('F j, Y \a\t g:i A') . "</li>
                        <li>IP Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "</li>
                    </ul>
                    <p>If you did not make this change, please contact our support team immediately.</p>
                    <p>Best regards,<br>The " . SYSTEM_ABBR . " Team</p>
                </div>
                <div style='background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666;'>
                    <p>&copy; " . date('Y') . " " . EMAIL_FOOTER_TEXT . "</p>
                </div>
            </div>";
            
            sendEmail($user['email'], $email_subject, $email_body, true);
            
            // Set success message and redirect to login
            setSuccessMessage('Your password has been reset successfully! You can now login with your new password.');
            header('Location: login.php');
            exit();
        } else {
            $errors[] = "Failed to reset password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SYSTEM_ABBR ?> - Reset Password</title>
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

        .reset-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
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

        .reset-header {
            background: linear-gradient(135deg, var(--earist-red) 0%, #8c000c 100%);
            padding: 20px 15px;
            text-align: center;
            color: white;
            position: relative;
            border-bottom: 3px solid var(--earist-gold);
        }

        .reset-header::before {
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

        .reset-header > * {
            position: relative;
            z-index: 1;
        }

        .reset-logo {
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

        .reset-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .reset-logo i {
            font-size: 24px;
            color: var(--earist-red);
        }

        .reset-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
            color: white;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
        }

        .reset-subtitle {
            font-size: 14px;
            margin-bottom: 3px;
            color: var(--earist-gold);
            font-weight: 500;
        }

        .reset-institution {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.8);
        }

        .reset-form {
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

        .user-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
            text-align: center;
            border: 1px solid #e9ecef;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--earist-red);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
            margin: 0 auto 8px;
            box-shadow: 0 2px 5px rgba(167, 0, 14, 0.3);
        }

        .user-info h6 {
            margin: 0 0 3px 0;
            font-size: 14px;
            font-weight: 600;
            color: #495057;
        }

        .user-info small {
            font-size: 11px;
            color: #6c757d;
        }

        .password-requirements {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 12px;
            font-size: 12px;
            border: 1px solid #e9ecef;
        }

        .password-requirements strong {
            display: block;
            margin-bottom: 6px;
            color: #495057;
        }

        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 3px;
            font-size: 11px;
        }

        .requirement i {
            margin-right: 6px;
            width: 12px;
            font-size: 10px;
        }

        .requirement.valid {
            color: #28a745;
        }

        .requirement.invalid {
            color: #dc3545;
        }

        .error-container {
            text-align: center;
            padding: 30px 20px;
        }

        .error-icon {
            font-size: 40px;
            color: #dc3545;
            margin-bottom: 15px;
        }

        .error-container h5 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 16px;
        }

        .error-container p {
            color: #6c757d;
            font-size: 13px;
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--earist-red) 0%, #8c000c 100%);
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #8c000c 0%, #a7000e 100%);
            transform: translateY(-1px);
        }

        /* Responsive design */
        @media (max-width: 576px) {
            .reset-container {
                margin: 10px;
                max-width: 100%;
            }
            
            .reset-form {
                padding: 15px;
            }
            
            .reset-header {
                padding: 15px;
            }
            
            .reset-logo {
                width: 50px;
                height: 50px;
            }
            
            .reset-title {
                font-size: 18px;
            }
        }

        @media (max-height: 600px) {
            .reset-header {
                padding: 15px;
            }
            
            .reset-logo {
                width: 45px;
                height: 45px;
                margin-bottom: 8px;
            }
            
            .reset-title {
                font-size: 18px;
            }
            
            .reset-form {
                padding: 15px;
            }
            
            .form-floating {
                margin-bottom: 10px;
            }
            
            .back-section {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <div class="reset-logo">
                <?php if (file_exists('../images/earist_logo_png.png')): ?>
                    <img src="../images/earist_logo_png.png" alt="EARIST Logo">
                <?php else: ?>
                    <i class="fas fa-shield-alt"></i>
                <?php endif; ?>
            </div>
            <h1 class="reset-title">Reset Password</h1>
            <p class="reset-subtitle">Create a new password</p>
            <p class="reset-institution"><?= SYSTEM_ABBR ?></p>
        </div>
        
        <?php if (!$valid_token): ?>
            <!-- Invalid or Expired Token -->
            <div class="error-container">
                <div class="error-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h5>Invalid or Expired Link</h5>
                <p>
                    This password reset link is invalid or has expired. 
                    Please request a new password reset.
                </p>
                <a href="forgot_password.php" class="btn btn-primary">
                    <i class="fas fa-key me-2"></i>Request New Reset
                </a>
            </div>
        <?php else: ?>
            <!-- Valid Token - Show Reset Form -->
            <form class="reset-form" method="POST" id="resetForm" novalidate>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <!-- User Information -->
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                    </div>
                    <h6><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h6>
                    <small>@<?= htmlspecialchars($user['username']) ?></small>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php if (count($errors) === 1): ?>
                            <?= htmlspecialchars($errors[0]) ?>
                        <?php else: ?>
                            <ul class="mb-0" style="padding-left: 15px;">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="new_password" name="new_password" 
                           placeholder="New Password" required minlength="6" autocomplete="new-password">
                    <label for="new_password"><i class="fas fa-lock me-2"></i>New Password</label>
                </div>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                           placeholder="Confirm Password" required minlength="6" autocomplete="new-password">
                    <label for="confirm_password"><i class="fas fa-lock me-2"></i>Confirm New Password</label>
                </div>

                <!-- Password Requirements -->
                <div class="password-requirements">
                    <strong>Password Requirements:</strong>
                    <div class="requirement" id="req-length">
                        <i class="fas fa-times"></i>
                        At least 6 characters long
                    </div>
                    <div class="requirement" id="req-letter">
                        <i class="fas fa-times"></i>
                        Contains at least one letter
                    </div>
                    <div class="requirement" id="req-number">
                        <i class="fas fa-times"></i>
                        Contains at least one number
                    </div>
                    <div class="requirement" id="req-match">
                        <i class="fas fa-times"></i>
                        Passwords match
                    </div>
                </div>
                
                <button type="submit" name="reset_password" class="btn btn-reset" id="submitBtn">
                    <i class="fas fa-save me-2"></i>Reset Password
                </button>
            </form>
        <?php endif; ?>
        
        <div class="back-section">
            <a href="login.php"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($valid_token): ?>
        // Real-time password validation
        const passwordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const reqLength = document.getElementById('req-length');
        const reqLetter = document.getElementById('req-letter');
        const reqNumber = document.getElementById('req-number');
        const reqMatch = document.getElementById('req-match');
        const submitBtn = document.getElementById('submitBtn');

        function updateRequirement(element, isValid) {
            const icon = element.querySelector('i');
            if (isValid) {
                element.classList.add('valid');
                element.classList.remove('invalid');
                icon.className = 'fas fa-check';
            } else {
                element.classList.add('invalid');
                element.classList.remove('valid');
                icon.className = 'fas fa-times';
            }
        }

        function validatePassword() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            // Check length
            const hasLength = password.length >= 6;
            updateRequirement(reqLength, hasLength);

            // Check for letter
            const hasLetter = /[A-Za-z]/.test(password);
            updateRequirement(reqLetter, hasLetter);

            // Check for number
            const hasNumber = /[0-9]/.test(password);
            updateRequirement(reqNumber, hasNumber);

            // Check match
            const passwordsMatch = password === confirmPassword && password.length > 0;
            updateRequirement(reqMatch, passwordsMatch);

            // Enable/disable submit button
            const allValid = hasLength && hasLetter && hasNumber && passwordsMatch;
            submitBtn.disabled = !allValid;

            // Update form validation styling
            if (hasLength && hasLetter && hasNumber) {
                passwordInput.classList.add('is-valid');
                passwordInput.classList.remove('is-invalid');
            } else if (password.length > 0) {
                passwordInput.classList.add('is-invalid');
                passwordInput.classList.remove('is-valid');
            } else {
                passwordInput.classList.remove('is-valid', 'is-invalid');
            }

            if (passwordsMatch) {
                confirmPasswordInput.classList.add('is-valid');
                confirmPasswordInput.classList.remove('is-invalid');
            } else if (confirmPassword.length > 0) {
                confirmPasswordInput.classList.add('is-invalid');
                confirmPasswordInput.classList.remove('is-valid');
            } else {
                confirmPasswordInput.classList.remove('is-valid', 'is-invalid');
            }
        }

        passwordInput.addEventListener('input', validatePassword);
        confirmPasswordInput.addEventListener('input', validatePassword);

        // Custom validation messages
        confirmPasswordInput.addEventListener('input', function() {
            if (this.value !== passwordInput.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        passwordInput.addEventListener('input', function() {
            if (confirmPasswordInput.value !== this.value && confirmPasswordInput.value.length > 0) {
                confirmPasswordInput.setCustomValidity('Passwords do not match');
            } else {
                confirmPasswordInput.setCustomValidity('');
            }
        });

        // Form submission loading state
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            // Final validation
            if (password.length < 6 || !/[A-Za-z]/.test(password) || !/[0-9]/.test(password) || password !== confirmPassword) {
                e.preventDefault();
                alert('Please ensure all password requirements are met.');
                return false;
            }
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Resetting Password...';
            submitBtn.disabled = true;
        });

        // Initial validation
        validatePassword();

        // Focus on password input
        document.getElementById('new_password').focus();
        <?php endif; ?>

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
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