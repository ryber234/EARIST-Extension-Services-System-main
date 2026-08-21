<?php
require_once '../config.php';

// If already logged in, redirect
if (isLoggedIn()) {
    header('Location: ../dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password.';
    } else {
        // Attempt login
        $user = verifyLogin($email, $password);
        
        if ($user) {
            // Login successful - set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['department'] = $user['department'];
            
            // Update last login
            $db->query("UPDATE users SET last_login = NOW() WHERE user_id = ?", [$user['user_id']]);
            
            // Redirect to dashboard
            header('Location: ../dashboard.php');
            exit();
        } else {
            $error_message = 'Invalid email or password. Please check your credentials and try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SYSTEM_ABBR ?> - Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --earist-red: #a7000e;
            --earist-gold: #ffd000;
            --success-green: #28a745;
            --error-red: #dc3545;
            --warning-orange: #fd7e14;
            --info-blue: #17a2b8;
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

        .login-container {
           background: white;
           border-radius: 12px;
           box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
           overflow: hidden;
           width: 100%;
           max-width: 400px;
           height: 50; 
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

        @keyframes slideInFromTop {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .login-header {
            background: linear-gradient(135deg, var(--earist-red) 0%, #8c000c 100%);
            padding: 30px 20px;
            text-align: center;
            color: white;
            position: relative;
            border-bottom: 3px solid var(--earist-gold);
        }

        .login-header::before {
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

        .login-header > * {
            position: relative;
            z-index: 3;
        }

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
            min-height: 330px; /* Adjusted for compactness */
            animation: slideUp 0.6s ease-out;
            border: 2px solid rgba(167, 0, 14, 0.1);
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
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

        @keyframes slideInFromTop {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .login-header {
            background: linear-gradient(135deg, var(--earist-red) 0%, #8c000c 100%);
            padding: 18px 20px; /* reduced vertical padding */
            text-align: center;
            color: white;
            position: relative;
            border-bottom: 3px solid var(--earist-gold);
        }

        .login-header::before {
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

        .login-header > * {
            position: relative;
            z-index: 3;
        }

        .login-logo img {
            max-width: 15%;
            max-height: 10%;
            object-fit: contain;
        }

        .login-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
            color: white;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
        }

        .login-subtitle {
            font-size: 16px;
            margin-bottom: 5px;
            color: var(--earist-gold);
            font-weight: 500;
        }

        .login-institution {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.3;
        }

        .login-form {
            padding: 18px 25px; /* reduced vertical padding */
            background: rgba(255, 255, 255, 0.98);
        }

        /* Enhanced Alert Styles */
        .custom-alert {
            border: none;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            position: relative;
            overflow: hidden;
            animation: slideInFromTop 0.4s ease-out;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .custom-alert::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: currentColor;
        }

        .custom-alert .alert-icon {
            font-size: 18px;
            margin-right: 12px;
            vertical-align: middle;
        }

        .custom-alert .alert-content {
            display: inline-block;
            vertical-align: middle;
            line-height: 1.4;
        }

        .alert-danger {
            background: linear-gradient(135deg, #ffe6e6 0%, #ffcccc 100%);
            color: #721c24;
            border-left: 4px solid var(--error-red);
            animation: slideInFromTop 0.4s ease-out, shake 0.5s ease-in-out 0.2s;
        }

        .alert-success {
            background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
            color: #155724;
            border-left: 4px solid var(--success-green);
        }

        .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border-left: 4px solid var(--warning-orange);
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
            border-left: 4px solid var(--info-blue);
        }

        .alert-close {
            position: absolute;
            top: 8px;
            right: 12px;
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            opacity: 0.6;
            transition: opacity 0.3s ease;
            color: inherit;
            padding: 4px;
        }

        .alert-close:hover {
            opacity: 1;
        }

        .form-floating {
            margin-bottom: 20px;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 15px;
            height: auto;
            transition: all 0.3s ease;
        }

        .form-floating label {
            font-size: 14px;
            padding: 0.7rem 0.9rem;
            font-weight: 500;
            color: #6c757d;
        }

        .form-control:focus {
            border-color: var(--earist-red);
            box-shadow: 0 0 0 3px rgba(167, 0, 14, 0.1);
            transform: translateY(-1px);
        }

        .form-control.is-invalid {
            border-color: var(--error-red);
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
            animation: shake 0.5s ease-in-out;
        }

        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: var(--error-red);
            font-weight: 500;
        }

        .password-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 8px;
            z-index: 10;
            transition: color 0.3s ease;
            font-size: 16px;
        }

        .password-toggle:hover {
            color: var(--earist-red);
        }

        .password-toggle:focus {
            outline: none;
            color: var(--earist-red);
        }

        .password-container .form-control {
            padding-right: 50px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 5px 0;
        }

        .remember-me input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.1);
            accent-color: var(--earist-red);
        }

        .remember-me label {
            color: #495057;
            font-size: 14px;
            font-weight: 500;
            margin: 0;
            cursor: pointer;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--earist-red) 0%, #8c000c 100%);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(167, 0, 14, 0.3);
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #8c000c 0%, #a7000e 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(167, 0, 14, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.7;
            transform: none;
            cursor: not-allowed;
        }

        .login-links {
            text-align: center;
            margin-bottom: 15px;
        }

        .login-links a {
            color: var(--earist-red);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .login-links a:hover {
            color: #8c000c;
            text-decoration: underline;
        }

        .home-section {
            text-align: center;
            padding: 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-top: 1px solid #dee2e6;
        }

        .home-section a {
            color: var(--earist-red);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .home-section a:hover {
            color: #8c000c;
            text-decoration: underline;
        }


        /* Responsive design */
        @media (max-width: 576px) {
            .login-container {
                margin: 10px;
                max-width: 100%;
            }
            
            .login-form {
                padding: 20px;
            }
            
            .login-header {
                padding: 25px 15px;
            }
            
            .login-logo {
                width: 60px;
                height: 60px;
            }
            
            .login-title {
                font-size: 20px;
            }

            .custom-alert {
                padding: 12px 15px;
                font-size: 13px;
            }
        }

        /* Loading states */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-spinner {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--earist-red);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Signing you in...</p>
        </div>
    </div>

    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">
                <?php if (file_exists('../images/earist_logo_png.png')): ?>
                    <img src="../images/earist_logo_png.png" alt="EARIST Logo">
                <?php else: ?>
                    <span style="color: var(--earist-red); font-size: 2rem;"><i class="fas fa-university"></i></span>
                <?php endif; ?>
            </div>
            <h1 class="login-title"><?= SYSTEM_ABBR ?></h1>
            <p class="login-subtitle">Extension Service System</p>
            <p class="login-institution"><?= INSTITUTION_NAME ?></p>
        </div>

        <form class="login-form" method="POST" novalidate>
            <!-- Error Messages -->
            <?php if (!empty($error_message)): ?>
                <div class="custom-alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle alert-icon"></i>
                    <span class="alert-content"><?= htmlspecialchars($error_message) ?></span>
                    <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <?php if ($flash_error = getFlashMessage('error')): ?>
                <div class="custom-alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle alert-icon"></i>
                    <span class="alert-content"><?= htmlspecialchars($flash_error) ?></span>
                    <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Success Messages -->
            <?php if ($flash_success = getFlashMessage('success')): ?>
                <div class="custom-alert alert-success" role="alert">
                    <i class="fas fa-check-circle alert-icon"></i>
                    <span class="alert-content"><?= htmlspecialchars($flash_success) ?></span>
                    <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="form-floating">
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                       placeholder="Email Address" required autocomplete="email">
                <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
                <div class="invalid-feedback" id="emailError"></div>
            </div>

            <div class="form-floating password-container">
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Password" required autocomplete="current-password">
                <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                <button type="button" class="password-toggle" id="togglePassword">
                    <i class="fas fa-eye-slash"></i>
                </button>
                <div class="invalid-feedback" id="passwordError"></div>
            </div>

            <div class="remember-me">
                <input type="checkbox" id="remember_me" name="remember_me" value="1">
                <label for="remember_me">
                    Remember me
                </label>
            </div>

            <div class="login-links">
                <a href="forgot_password.php">
                    <i class="fas fa-key me-1"></i>Forgot Password?
                </a>
            </div>

            <button type="submit" name="login" class="btn btn-login" id="loginBtn">
                <i class="fas fa-sign-in-alt me-2"></i>Sign In to Dashboard
            </button>
        </form>

        <div class="home-section">
            <a href="../public-view/public_homepage.php"><i class="fas fa-home me-1"></i>Back to Public Homepage</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 6 seconds
        setTimeout(() => {
            document.querySelectorAll('.custom-alert').forEach(alert => {
                if (!alert.classList.contains('manual-close')) {
                    alert.style.transition = 'opacity 0.5s, transform 0.5s';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 6000);

        // Email validation
        function validateEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Show validation error
        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorDiv = document.getElementById(fieldId + 'Error');
            
            field.classList.add('is-invalid');
            errorDiv.textContent = message;
        }

        // Clear validation error
        function clearFieldError(fieldId) {
            const field = document.getElementById(fieldId);
            const errorDiv = document.getElementById(fieldId + 'Error');
            
            field.classList.remove('is-invalid');
            errorDiv.textContent = '';
        }

        // Real-time email validation
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value.trim();
            if (email && !validateEmail(email)) {
                showFieldError('email', 'Please enter a valid email address');
            } else {
                clearFieldError('email');
            }
        });

        // Clear validation on input
        document.getElementById('email').addEventListener('input', function() {
            clearFieldError('email');
        });

        document.getElementById('password').addEventListener('input', function() {
            clearFieldError('password');
        });

        // Form submission handling
        document.querySelector('.login-form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const btn = document.getElementById('loginBtn');
            let hasErrors = false;
            
            // Clear previous errors
            clearFieldError('email');
            clearFieldError('password');
            
            // Validate email
            if (!email) {
                showFieldError('email', 'Email address is required');
                hasErrors = true;
            } else if (!validateEmail(email)) {
                showFieldError('email', 'Please enter a valid email address');
                hasErrors = true;
            }
            
            // Validate password
            if (!password) {
                showFieldError('password', 'Password is required');
                hasErrors = true;
            }
            
            if (hasErrors) {
                e.preventDefault();
                return;
            }
            
            // Show loading state
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
            btn.disabled = true;
            
            // Show loading overlay
            document.getElementById('loadingOverlay').style.display = 'flex';
        });

        // Focus on email field when page loads
        window.addEventListener('load', function() {
            document.getElementById('email').focus();
        });

        // Password toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const passwordIcon = togglePassword.querySelector('i');

        togglePassword.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Toggle the type attribute
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle the eye icon
            if (type === 'password') {
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            } else {
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            }
        });

        // Demo credential quick fill
        document.addEventListener('click', function(e) {
            if (e.target.closest('.credential-item')) {
                const item = e.target.closest('.credential-item');
                const text = item.textContent;
                
                if (text.includes('admin@earist.edu.ph')) {
                    document.getElementById('email').value = 'admin@earist.edu.ph';
                    document.getElementById('password').value = 'password';
                } else if (text.includes('staff@earist.edu.ph')) {
                    document.getElementById('email').value = 'staff@earist.edu.ph';
                    document.getElementById('password').value = 'password';
                }
                
                // Clear any validation errors
                clearFieldError('email');
                clearFieldError('password');
            }
        });

        // Add hover effect to demo credentials
        document.querySelectorAll('.credential-item').forEach(item => {
            item.style.cursor = 'pointer';
            item.addEventListener('mouseenter', function() {
                this.style.background = 'rgba(255, 255, 255, 0.9)';
                this.style.transform = 'translateX(5px)';
                this.style.transition = 'all 0.2s ease';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.background = 'rgba(255, 255, 255, 0.7)';
                this.style.transform = 'translateX(0)';
            });
        });
    </script>
</body>
</html>