<?php
// User management page - Admin only
requireRole(['Admin']);

$action = $_GET['action'] ?? 'list';
$user_id = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = sanitizeInput($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = sanitizeInput($_POST['email']);
        $first_name = sanitizeInput($_POST['first_name']);
        $last_name = sanitizeInput($_POST['last_name']);
        $role = $_POST['role'];
        $department = sanitizeInput($_POST['department']);
        
        // Check if username or email already exists
        $existing = $db->fetch("SELECT user_id FROM users WHERE username = ? OR email = ?", [$username, $email]);
        
        if ($existing) {
            setErrorMessage('Username or email already exists.');
        } else {
            $sql = "INSERT INTO users (username, password, email, first_name, last_name, role, department) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            if ($db->query($sql, [$username, $password, $email, $first_name, $last_name, $role, $department])) {
                $new_user_id = $db->lastInsertId();
                logActivity($_SESSION['user_id'], 'User Created', 'users', $new_user_id);
                addNotification($new_user_id, 'Welcome to EESS', 'Your account has been created successfully.', 'Success');
                setSuccessMessage('User created successfully!');
                header('Location: ?page=users');
                exit();
            } else {
                setErrorMessage('Failed to create user.');
            }
        }
    }
    
    if (isset($_POST['update_user'])) {
        $id = (int)$_POST['user_id'];
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $first_name = sanitizeInput($_POST['first_name']);
        $last_name = sanitizeInput($_POST['last_name']);
        $role = $_POST['role'];
        $department = sanitizeInput($_POST['department']);
        $status = $_POST['status'];
        
        // Check if username or email already exists for other users
        $existing = $db->fetch("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?", 
                              [$username, $email, $id]);
        
        if ($existing) {
            setErrorMessage('Username or email already exists.');
        } else {
            $sql = "UPDATE users SET username=?, email=?, first_name=?, last_name=?, role=?, department=?, status=? 
                    WHERE user_id=?";
            
            if ($db->query($sql, [$username, $email, $first_name, $last_name, $role, $department, $status, $id])) {
                logActivity($_SESSION['user_id'], 'User Updated', 'users', $id);
                setSuccessMessage('User updated successfully!');
                header('Location: ?page=users');
                exit();
            } else {
                setErrorMessage('Failed to update user.');
            }
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $id = (int)$_POST['user_id'];
        
        // Cannot delete self
        if ($id == $_SESSION['user_id']) {
            setErrorMessage('You cannot delete your own account.');
        } else {
            if ($db->query("DELETE FROM users WHERE user_id = ?", [$id])) {
                logActivity($_SESSION['user_id'], 'User Deleted', 'users', $id);
                setSuccessMessage('User deleted successfully!');
            } else {
                setErrorMessage('Failed to delete user.');
            }
        }
        header('Location: ?page=users');
        exit();
    }
    
    if (isset($_POST['reset_password'])) {
        $id = (int)$_POST['user_id'];
        $new_password = $_POST['new_password'];
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        if ($db->query("UPDATE users SET password = ? WHERE user_id = ?", [$hashed_password, $id])) {
            logActivity($_SESSION['user_id'], 'Password Reset', 'users', $id);
            addNotification($id, 'Password Reset', 'Your password has been reset by an administrator.', 'Warning');
            setSuccessMessage('Password reset successfully!');
        } else {
            setErrorMessage('Failed to reset password.');
        }
        header('Location: ?page=users');
        exit();
    }
}

// Get users with pagination and filtering
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page_num = (int)($_GET['p'] ?? 1);

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR department LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$sql = "SELECT *, 
        (SELECT COUNT(*) FROM programs WHERE created_by = users.user_id) as programs_created,
        (SELECT COUNT(*) FROM audit_logs WHERE user_id = users.user_id) as activities_count
        FROM users 
        $where_clause 
        ORDER BY created_at DESC";

$users_data = paginate($sql, $params, $page_num, 15);
$users = $users_data['data'];

// Get current user for editing
$current_user = null;
if ($user_id && in_array($action, ['view', 'edit'])) {
    $current_user = $db->fetch("
        SELECT *, 
        (SELECT COUNT(*) FROM programs WHERE created_by = users.user_id) as programs_created,
        (SELECT COUNT(*) FROM audit_logs WHERE user_id = users.user_id) as activities_count
        FROM users WHERE user_id = ?", [$user_id]);
    
    if (!$current_user) {
        setErrorMessage('User not found.');
        header('Location: ?page=users');
        exit();
    }
}

// Get user statistics
$user_stats = [
    'total_users' => $db->fetch("SELECT COUNT(*) as count FROM users")['count'],
    'active_users' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE status = 'Active'")['count'],
    'admins' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'Admin'")['count'],
    'authorized_users' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'Authorized User'")['count'],
    'public_users' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'Public User'")['count']
];
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

<?php if ($action === 'list'): ?>
    <!-- User Management Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users text-primary"></i> User Management</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-plus"></i> Add New User
        </button>
    </div>

    <!-- User Statistics -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card text-center">
                <div class="stat-icon blue mx-auto mb-2">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?= number_format($user_stats['total_users']) ?></div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card text-center">
                <div class="stat-icon green mx-auto mb-2">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-value"><?= number_format($user_stats['active_users']) ?></div>
                <div class="stat-label">Active Users</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card text-center">
                <div class="stat-icon red mx-auto mb-2">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-value"><?= number_format($user_stats['admins']) ?></div>
                <div class="stat-label">Admins</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card text-center">
                <div class="stat-icon orange mx-auto mb-2">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-value"><?= number_format($user_stats['authorized_users']) ?></div>
                <div class="stat-label">Authorized</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="stat-card text-center">
                <div class="stat-icon green mx-auto mb-2">
                    <i class="fas fa-user"></i>
                </div>
                <div class="stat-value"><?= number_format($user_stats['public_users']) ?></div>
                <div class="stat-label">Public</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="page" value="users">
                
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search users...">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role">
                        <option value="">All Roles</option>
                        <option value="Admin" <?= $role_filter === 'Admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="Authorized User" <?= $role_filter === 'Authorized User' ? 'selected' : '' ?>>Authorized User</option>
                        <option value="Public User" <?= $role_filter === 'Public User' ? 'selected' : '' ?>>Public User</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="Active" <?= $status_filter === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= $status_filter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User Details</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Programs</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i><br>
                                <h5 class="text-muted">No users found</h5>
                                <p class="text-muted">Try adjusting your filters or add a new user.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-3">
                                            <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong><br>
                                            <small class="text-muted">
                                                @<?= htmlspecialchars($user['username']) ?><br>
                                                <?= htmlspecialchars($user['email']) ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $user['role'] === 'Admin' ? 'danger' : ($user['role'] === 'Authorized User' ? 'warning' : 'info') ?>">
                                        <?= htmlspecialchars($user['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($user['department'] ?: 'Not assigned') ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($user['status']) ?>">
                                        <?= htmlspecialchars($user['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <strong><?= number_format($user['programs_created']) ?></strong><br>
                                        <small class="text-muted">Created</small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($user['last_login']): ?>
                                        <?= formatDate($user['last_login'], 'M d, Y H:i') ?>
                                    <?php else: ?>
                                        <span class="text-muted">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="?page=users&action=view&id=<?= $user['user_id'] ?>" class="btn btn-sm btn-outline-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-warning" title="Edit" onclick="editUser(<?= $user['user_id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" title="Reset Password" onclick="resetPassword(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-outline-danger" title="Delete" onclick="deleteUser(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($users_data['total_pages'] > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($users_data['has_prev']): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=users&p=<?= $users_data['current_page'] - 1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>&status=<?= urlencode($status_filter) ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $users_data['current_page'] - 2); $i <= min($users_data['total_pages'], $users_data['current_page'] + 2); $i++): ?>
                            <li class="page-item <?= $i === $users_data['current_page'] ? 'active' : '' ?>">
                                <a class="page-link" href="?page=users&p=<?= $i ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>&status=<?= urlencode($status_filter) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($users_data['has_next']): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=users&p=<?= $users_data['current_page'] + 1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>&status=<?= urlencode($status_filter) ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <div class="text-center text-muted mt-2">
                    Showing <?= number_format(($users_data['current_page'] - 1) * $users_data['per_page'] + 1) ?> to 
                    <?= number_format(min($users_data['current_page'] * $users_data['per_page'], $users_data['total'])) ?> of 
                    <?= number_format($users_data['total']) ?> entries
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'view' && $current_user): ?>
    <!-- View User Details -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-user text-info"></i> User Details</h2>
        <div>
            <button class="btn btn-warning me-2" onclick="editUser(<?= $current_user['user_id'] ?>)">
                <i class="fas fa-edit"></i> Edit User
            </button>
            <a href="?page=users" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <!-- User Profile Card -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 24px;">
                        <?= strtoupper(substr($current_user['first_name'], 0, 1) . substr($current_user['last_name'], 0, 1)) ?>
                    </div>
                    <h4><?= htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']) ?></h4>
                    <p class="text-muted">@<?= htmlspecialchars($current_user['username']) ?></p>
                    <span class="badge bg-<?= $current_user['role'] === 'Admin' ? 'danger' : ($current_user['role'] === 'Authorized User' ? 'warning' : 'info') ?> mb-3">
                        <?= htmlspecialchars($current_user['role']) ?>
                    </span>
                    <br>
                    <span class="status-badge status-<?= strtolower($current_user['status']) ?>">
                        <?= htmlspecialchars($current_user['status']) ?>
                    </span>
                </div>
            </div>

            <!-- User Statistics -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar"></i> User Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 border-end">
                            <div class="fw-bold text-primary fs-4"><?= number_format($current_user['programs_created']) ?></div>
                            <small class="text-muted">Programs Created</small>
                        </div>
                        <div class="col-6">
                            <div class="fw-bold text-success fs-4"><?= number_format($current_user['activities_count']) ?></div>
                            <small class="text-muted">Total Activities</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <!-- User Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> User Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-muted">Email Address</label>
                                <div><?= htmlspecialchars($current_user['email']) ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Department</label>
                                <div><?= htmlspecialchars($current_user['department'] ?: 'Not assigned') ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Account Created</label>
                                <div><?= formatDate($current_user['created_at']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-muted">Last Login</label>
                                <div>
                                    <?php if ($current_user['last_login']): ?>
                                        <?= formatDate($current_user['last_login'], 'M d, Y H:i') ?>
                                    <?php else: ?>
                                        <span class="text-muted">Never logged in</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Last Updated</label>
                                <div><?= formatDate($current_user['updated_at']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Recent Activities</h5>
                </div>
                <div class="card-body">
                    <?php
                    $user_activities = $db->fetchAll("
                        SELECT * FROM audit_logs 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 10", [$current_user['user_id']]);
                    ?>
                    
                    <?php if (empty($user_activities)): ?>
                        <p class="text-muted text-center py-3">No recent activities</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($user_activities as $activity): ?>
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
                                                <small class="text-muted">Table: <?= htmlspecialchars($activity['table_affected']) ?></small><br>
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

<?php else: ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> Invalid action or user not found.
    </div>
<?php endif; ?>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus text-success"></i> Add New User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Username *</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Password *</label>
                                <input type="password" class="form-control" name="password" required minlength="6">
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Role *</label>
                                <select class="form-select" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="Admin">Admin</option>
                                    <option value="Authorized User">Authorized User</option>
                                    <option value="Public User">Public User</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <input type="text" class="form-control" name="department" placeholder="e.g., College of Engineering">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit text-warning"></i> Edit User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editUserForm" class="needs-validation" novalidate>
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" id="editFirstName" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" id="editLastName" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Username *</label>
                                <input type="text" class="form-control" name="username" id="editUsername" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" id="editEmail" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Role *</label>
                                <select class="form-select" name="role" id="editRole" required>
                                    <option value="">Select Role</option>
                                    <option value="Admin">Admin</option>
                                    <option value="Authorized User">Authorized User</option>
                                    <option value="Public User">Public User</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status *</label>
                                <select class="form-select" name="status" id="editStatus" required>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <input type="text" class="form-control" name="department" id="editDepartment">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_user" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-key text-secondary"></i> Reset Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="resetPasswordForm">
                <input type="hidden" name="user_id" id="resetUserId">
                <div class="modal-body">
                    <p>Reset password for user: <strong id="resetUsername"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">New Password *</label>
                        <input type="password" class="form-control" name="new_password" required minlength="6">
                        <div class="form-text">Minimum 6 characters</div>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        The user will be notified of this password reset.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reset_password" class="btn btn-warning">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-trash text-danger"></i> Delete User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete user: <strong id="deleteUsername"></strong>?</p>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action cannot be undone. All associated data will be removed.
                </div>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteUserForm">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_user" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete User
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editUser(userId) {
    // Fetch user data and populate edit modal
    fetch(`api/get_user.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                document.getElementById('editUserId').value = user.user_id;
                document.getElementById('editFirstName').value = user.first_name;
                document.getElementById('editLastName').value = user.last_name;
                document.getElementById('editUsername').value = user.username;
                document.getElementById('editEmail').value = user.email;
                document.getElementById('editRole').value = user.role;
                document.getElementById('editStatus').value = user.status;
                document.getElementById('editDepartment').value = user.department || '';
                
                const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
                modal.show();
            } else {
                alert('Error loading user data: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading user data. Please try again.');
        });
}

function resetPassword(userId, username) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetUsername').textContent = username;
    const modal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
    modal.show();
}

function deleteUser(userId, username) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUsername').textContent = username;
    const modal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
    modal.show();
}

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
</script>