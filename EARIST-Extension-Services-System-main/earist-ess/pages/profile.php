<?php
// User profile page
requireLogin();

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $first_name = sanitizeInput($_POST['first_name']);
        $last_name = sanitizeInput($_POST['last_name']);
        $email = sanitizeInput($_POST['email']);
        $department = sanitizeInput($_POST['department']);
        
        // Check if email already exists for other users
        $existing = $db->fetch("SELECT user_id FROM users WHERE email = ? AND user_id != ?", 
                              [$email, $_SESSION['user_id']]);
        
        if ($existing) {
            setErrorMessage('Email address already exists.');
        } else {
            $sql = "UPDATE users SET first_name=?, last_name=?, email=?, department=? WHERE user_id=?";
            
            if ($db->query($sql, [$first_name, $last_name, $email, $department, $_SESSION['user_id']])) {
                // Update session data
                $_SESSION['full_name'] = $first_name . ' ' . $last_name;
                $_SESSION['department'] = $department;
                
                logActivity($_SESSION['user_id'], 'Profile Updated', 'users', $_SESSION['user_id']);
                setSuccessMessage('Profile updated successfully!');
            } else {
                setErrorMessage('Failed to update profile.');
            }
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Get current user data
        $user = $db->fetch("SELECT password FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
        
        if (!password_verify($current_password, $user['password'])) {
            setErrorMessage('Current password is incorrect.');
        } elseif ($new_password !== $confirm_password) {
            setErrorMessage('New passwords do not match.');
        } elseif (strlen($new_password) < 6) {
            setErrorMessage('New password must be at least 6 characters long.');
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            if ($db->query("UPDATE users SET password = ? WHERE user_id = ?", [$hashed_password, $_SESSION['user_id']])) {
                logActivity($_SESSION['user_id'], 'Password Changed', 'users', $_SESSION['user_id']);
                setSuccessMessage('Password changed successfully!');
            } else {
                setErrorMessage('Failed to change password.');
            }
        }
    }
    
    // Handle profile image upload
    if (isset($_POST['upload_image']) && isset($_FILES['profile_image'])) {
        $upload_result = uploadFile($_FILES['profile_image'], ['jpg', 'jpeg', 'png', 'gif'], 'uploads/profiles/');
        
        if ($upload_result['success']) {
            // Delete old profile image if exists
            $old_image = $db->fetch("SELECT profile_image FROM users WHERE user_id = ?", [$_SESSION['user_id']])['profile_image'];
            if ($old_image && file_exists($old_image)) {
                unlink($old_image);
            }
            
            // Update database with new image path
            if ($db->query("UPDATE users SET profile_image = ? WHERE user_id = ?", [$upload_result['filepath'], $_SESSION['user_id']])) {
                logActivity($_SESSION['user_id'], 'Profile Image Updated', 'users', $_SESSION['user_id']);
                setSuccessMessage('Profile image updated successfully!');
            } else {
                setErrorMessage('Failed to update profile image in database.');
            }
        } else {
            setErrorMessage('Failed to upload profile image: ' . $upload_result['message']);
        }
    }
}

// Get user profile data
$user_profile = $db->fetch("SELECT * FROM users WHERE user_id = ?", [$_SESSION['user_id']]);

// Get user statistics
$user_stats = [
    'programs_created' => $db->fetch("SELECT COUNT(*) as count FROM programs WHERE created_by = ?", [$_SESSION['user_id']])['count'],
    'total_participants' => $db->fetch("SELECT SUM(actual_participants) as total FROM programs WHERE created_by = ? AND status = 'Completed'", [$_SESSION['user_id']])['total'] ?? 0,
    'total_budget' => $db->fetch("SELECT SUM(budget_allocated) as total FROM programs WHERE created_by = ?", [$_SESSION['user_id']])['total'] ?? 0,
    'activities_count' => $db->fetch("SELECT COUNT(*) as count FROM audit_logs WHERE user_id = ?", [$_SESSION['user_id']])['count']
];

// Get recent activities
$recent_activities = $db->fetchAll("
    SELECT * FROM audit_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10", [$_SESSION['user_id']]);

// Get user's programs
$user_programs = $db->fetchAll("
    SELECT p.*, 
           (SELECT COUNT(*) FROM program_feedback WHERE program_id = p.program_id) as feedback_count,
           (SELECT AVG(rating) FROM program_feedback WHERE program_id = p.program_id) as avg_rating
    FROM programs p
    WHERE p.created_by = ? 
    ORDER BY p.created_at DESC 
    LIMIT 5", [$_SESSION['user_id']]);
?>

<!-- Flash Messages -->
<?php if ($flash_success = getFlashMessage('success')): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($flash_success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($flash_error = getFlashMessage('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($flash_error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Profile Header -->
<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-user text-info"></i> My Profile</h2>
        <p class="text-muted">Manage your account information and preferences</p>
    </div>
</div>

<div class="row">
    <!-- Profile Information Card -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <!-- Profile Image -->
                <div class="position-relative d-inline-block mb-3">
                    <?php if ($user_profile['profile_image'] && file_exists($user_profile['profile_image'])): ?>
                        <img src="<?= htmlspecialchars($user_profile['profile_image']) ?>" 
                             alt="Profile Image" 
                             class="rounded-circle"
                             style="width: 120px; height: 120px; object-fit: cover;">
                    <?php else: ?>
                        <div class="user-avatar mx-auto" style="width: 120px; height: 120px; font-size: 48px;">
                            <?= strtoupper(substr($user_profile['first_name'], 0, 1) . substr($user_profile['last_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Upload button -->
                    <button class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle" 
                            style="width: 32px; height: 32px; padding: 0;"
                            data-bs-toggle="modal" data-bs-target="#uploadImageModal">
                        <i class="fas fa-camera fa-sm"></i>
                    </button>
                </div>
                
                <h4><?= htmlspecialchars($user_profile['first_name'] . ' ' . $user_profile['last_name']) ?></h4>
                <p class="text-muted">@<?= htmlspecialchars($user_profile['username']) ?></p>
                
                <span class="badge bg-<?= $user_profile['role'] === 'Admin' ? 'danger' : ($user_profile['role'] === 'Authorized User' ? 'warning' : 'info') ?> mb-3">
                    <?= htmlspecialchars($user_profile['role']) ?>
                </span>
                
                <div class="text-start">
                    <p class="mb-2"><i class="fas fa-envelope text-muted me-2"></i> <?= htmlspecialchars($user_profile['email']) ?></p>
                    <?php if ($user_profile['department']): ?>
                        <p class="mb-2"><i class="fas fa-building text-muted me-2"></i> <?= htmlspecialchars($user_profile['department']) ?></p>
                    <?php endif; ?>
                    <p class="mb-2"><i class="fas fa-calendar text-muted me-2"></i> Member since <?= formatDate($user_profile['created_at']) ?></p>
                    <?php if ($user_profile['last_login']): ?>
                        <p class="mb-0"><i class="fas fa-clock text-muted me-2"></i> Last login: <?= formatDate($user_profile['last_login'], 'M d, Y H:i') ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- User Statistics -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> My Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 border-end">
                        <div class="fw-bold text-primary fs-4"><?= number_format($user_stats['programs_created']) ?></div>
                        <small class="text-muted">Programs Created</small>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold text-success fs-4"><?= number_format($user_stats['total_participants']) ?></div>
                        <small class="text-muted">Total Participants</small>
                    </div>
                </div>
                <hr>
                <div class="row text-center">
                    <div class="col-6 border-end">
                        <div class="fw-bold text-warning fs-5"><?= formatCurrency($user_stats['total_budget']) ?></div>
                        <small class="text-muted">Total Budget</small>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold text-info fs-5"><?= number_format($user_stats['activities_count']) ?></div>
                        <small class="text-muted">Activities</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profile Management -->
    <div class="col-lg-8">
        <!-- Profile Update Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Update Profile Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" 
                                       value="<?= htmlspecialchars($user_profile['first_name']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" 
                                       value="<?= htmlspecialchars($user_profile['last_name']) ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Address *</label>
                        <input type="email" class="form-control" name="email" 
                               value="<?= htmlspecialchars($user_profile['email']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <input type="text" class="form-control" name="department" 
                               value="<?= htmlspecialchars($user_profile['department']) ?>"
                               placeholder="e.g., College of Engineering">
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Change Password Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-key"></i> Change Password</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Current Password *</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">New Password *</label>
                                <input type="password" class="form-control" name="new_password" 
                                       minlength="6" required>
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password *</label>
                                <input type="password" class="form-control" name="confirm_password" 
                                       minlength="6" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" name="change_password" class="btn btn-warning">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Recent Programs -->
        <?php if (!empty($user_programs)): ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> My Recent Programs</h5>
                    <a href="?page=programs" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Program</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Participants</th>
                                    <th>Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_programs as $program): ?>
                                    <tr>
                                        <td>
                                            <a href="?page=programs&action=view&id=<?= $program['program_id'] ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($program['title']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?= htmlspecialchars($program['type_of_service']) ?>
                                            </span>
                                        </td>
                                        <td><?= formatDate($program['date_start']) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= strtolower($program['status']) ?>">
                                                <?= htmlspecialchars($program['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($program['status'] === 'Completed'): ?>
                                                <?= number_format($program['actual_participants']) ?>
                                            <?php else: ?>
                                                <?= number_format($program['expected_participants']) ?> (expected)
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($program['avg_rating']): ?>
                                                <div class="text-warning">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?= $i <= round($program['avg_rating']) ? '' : '-o' ?> fa-xs"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <small class="text-muted"><?= number_format($program['avg_rating'], 1) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">No rating</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Recent Activities -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history"></i> Recent Activities</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activities)): ?>
                    <p class="text-muted text-center py-3">No recent activities</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0">
                                        <div class="bg-light rounded-circle p-2" style="width: 32px; height: 32px;">
                                            <i class="fas fa-history fa-sm text-muted"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="fw-semibold"><?= htmlspecialchars($activity['action']) ?></div>
                                        <?php if ($activity['table_affected']): ?>
                                            <small class="text-muted">Table: <?= htmlspecialchars($activity['table_affected']) ?></small>
                                            <?php if ($activity['record_id']): ?>
                                                (ID: <?= $activity['record_id'] ?>)
                                            <?php endif; ?>
                                            <br>
                                        <?php endif; ?>
                                        <small class="text-muted"><?= formatDate($activity['created_at'], 'M d, Y H:i:s') ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Upload Profile Image Modal -->
<div class="modal fade" id="uploadImageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-camera text-primary"></i> Update Profile Image
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Image</label>
                        <input type="file" class="form-control" name="profile_image" 
                               accept="image/jpeg,image/jpg,image/png,image/gif" required>
                        <div class="form-text">Supported formats: JPG, PNG, GIF. Maximum size: 10MB</div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Tips:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Use a square image for best results</li>
                            <li>Recommended size: 300x300 pixels or larger</li>
                            <li>Clear, well-lit photos work best</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="upload_image" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload Image
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
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

// Password confirmation validation
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.querySelector('input[name="new_password"]');
    const confirmPassword = document.querySelector('input[name="confirm_password"]');
    
    if (newPassword && confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            if (this.value !== newPassword.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        newPassword.addEventListener('input', function() {
            if (confirmPassword.value !== this.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        });
    }
});

// Image preview
document.querySelector('input[name="profile_image"]').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            // You could add image preview here if needed
            console.log('Image selected:', file.name);
        };
        reader.readAsDataURL(file);
    }
});
</script>