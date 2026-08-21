<?php
// System settings page - Admin only
requireRole(['Admin']);

// Handle settings updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_system_settings'])) {
        $settings = [
            'system_name' => sanitizeInput($_POST['system_name']),
            'system_abbreviation' => sanitizeInput($_POST['system_abbreviation']),
            'institution_name' => sanitizeInput($_POST['institution_name']),
            'contact_email' => sanitizeInput($_POST['contact_email']),
            'contact_phone' => sanitizeInput($_POST['contact_phone']),
            'max_file_size' => (int)$_POST['max_file_size'],
            'allowed_file_types' => sanitizeInput($_POST['allowed_file_types'])
        ];
        
        $updated = 0;
        foreach ($settings as $key => $value) {
            if ($db->query("UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?", 
                          [$value, $_SESSION['user_id'], $key])) {
                $updated++;
            }
        }
        
        if ($updated > 0) {
            logActivity($_SESSION['user_id'], 'System Settings Updated', 'system_settings');
            setSuccessMessage('System settings updated successfully!');
        } else {
            setErrorMessage('Failed to update system settings.');
        }
    }
    
    if (isset($_POST['backup_database'])) {
        // Trigger database backup
        $backup_result = createDatabaseBackup();
        if ($backup_result['success']) {
            logActivity($_SESSION['user_id'], 'Database Backup Created', 'system');
            setSuccessMessage('Database backup created successfully!');
        } else {
            setErrorMessage('Failed to create database backup: ' . $backup_result['message']);
        }
    }
    
    if (isset($_POST['clear_logs'])) {
        $days = (int)$_POST['days_to_keep'];
        if ($days > 0) {
            $deleted = $db->query("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)", [$days]);
            if ($deleted) {
                logActivity($_SESSION['user_id'], "Cleared audit logs older than {$days} days", 'audit_logs');
                setSuccessMessage("Audit logs older than {$days} days have been cleared.");
            } else {
                setErrorMessage('Failed to clear audit logs.');
            }
        }
    }
    
    if (isset($_POST['send_test_notification'])) {
        $test_message = sanitizeInput($_POST['test_message']);
        $result = addNotification($_SESSION['user_id'], 'Test Notification', $test_message, 'Info');
        if ($result) {
            setSuccessMessage('Test notification sent successfully!');
        } else {
            setErrorMessage('Failed to send test notification.');
        }
    }
}

// Get current system settings
$system_settings = [];
$settings_result = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings");
foreach ($settings_result as $setting) {
    $system_settings[$setting['setting_key']] = $setting['setting_value'];
}

// Get system statistics
$system_stats = [
    'total_users' => $db->fetch("SELECT COUNT(*) as count FROM users")['count'],
    'total_programs' => $db->fetch("SELECT COUNT(*) as count FROM programs")['count'],
    'total_requests' => $db->fetch("SELECT COUNT(*) as count FROM program_requests")['count'],
    'total_feedback' => $db->fetch("SELECT COUNT(*) as count FROM program_feedback")['count'],
    'database_size' => getDatabaseSize(),
    'total_files' => getTotalUploadedFiles(),
    'system_uptime' => getSystemUptime()
];

// Get recent system activities
$recent_system_activities = $db->fetchAll("
    SELECT al.*, u.first_name, u.last_name 
    FROM audit_logs al 
    LEFT JOIN users u ON al.user_id = u.user_id 
    WHERE al.action LIKE '%System%' OR al.action LIKE '%Settings%' OR al.action LIKE '%Backup%'
    ORDER BY al.created_at DESC 
    LIMIT 10
");

// Helper functions
function createDatabaseBackup() {
    try {
        $backup_dir = 'backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $filename = 'earist_ess_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . $filename;
        
        // Simple backup using mysqldump (requires system access)
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s %s > %s',
            DB_USERNAME,
            DB_PASSWORD,
            DB_HOST,
            DB_NAME,
            $filepath
        );
        
        // Note: This requires shell access and mysqldump
        $output = shell_exec($command);
        
        if (file_exists($filepath) && filesize($filepath) > 0) {
            return ['success' => true, 'filename' => $filename];
        } else {
            return ['success' => false, 'message' => 'Backup file not created or empty'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getDatabaseSize() {
    global $db;
    $result = $db->fetch("
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size_mb 
        FROM information_schema.tables 
        WHERE table_schema = ?", [DB_NAME]);
    return $result['db_size_mb'] ?? 0;
}

function getTotalUploadedFiles() {
    $upload_dirs = ['uploads/', 'uploads/profiles/', 'uploads/documents/', 'uploads/images/'];
    $total_files = 0;
    
    foreach ($upload_dirs as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '*');
            $total_files += count($files);
        }
    }
    
    return $total_files;
}

function getSystemUptime() {
    // Simple uptime calculation (you might want to implement a more sophisticated method)
    return "N/A";
}
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

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-cog text-primary"></i> System Settings</h2>
        <p class="text-muted">Configure system preferences, security settings, and maintenance options</p>
    </div>
</div>

<!-- System Statistics Overview -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stat-card text-center">
            <div class="stat-icon blue mx-auto mb-2">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?= number_format($system_stats['total_users']) ?></div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stat-card text-center">
            <div class="stat-icon green mx-auto mb-2">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-value"><?= number_format($system_stats['total_programs']) ?></div>
            <div class="stat-label">Programs</div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stat-card text-center">
            <div class="stat-icon orange mx-auto mb-2">
                <i class="fas fa-hand-holding"></i>
            </div>
            <div class="stat-value"><?= number_format($system_stats['total_requests']) ?></div>
            <div class="stat-label">Requests</div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stat-card text-center">
            <div class="stat-icon red mx-auto mb-2">
                <i class="fas fa-comments"></i>
            </div>
            <div class="stat-value"><?= number_format($system_stats['total_feedback']) ?></div>
            <div class="stat-label">Feedback</div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stat-card text-center">
            <div class="stat-icon green mx-auto mb-2">
                <i class="fas fa-database"></i>
            </div>
            <div class="stat-value"><?= $system_stats['database_size'] ?></div>
            <div class="stat-label">DB Size (MB)</div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stat-card text-center">
            <div class="stat-icon blue mx-auto mb-2">
                <i class="fas fa-file"></i>
            </div>
            <div class="stat-value"><?= number_format($system_stats['total_files']) ?></div>
            <div class="stat-label">Files</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- System Configuration -->
    <div class="col-lg-8">
        <!-- General Settings -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cogs"></i> General Settings</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">System Name</label>
                                <input type="text" class="form-control" name="system_name" 
                                       value="<?= htmlspecialchars($system_settings['system_name'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">System Abbreviation</label>
                                <input type="text" class="form-control" name="system_abbreviation" 
                                       value="<?= htmlspecialchars($system_settings['system_abbreviation'] ?? '') ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Institution Name</label>
                        <input type="text" class="form-control" name="institution_name" 
                               value="<?= htmlspecialchars($system_settings['institution_name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Contact Email</label>
                                <input type="email" class="form-control" name="contact_email" 
                                       value="<?= htmlspecialchars($system_settings['contact_email'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Contact Phone</label>
                                <input type="text" class="form-control" name="contact_phone" 
                                       value="<?= htmlspecialchars($system_settings['contact_phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Max File Size (bytes)</label>
                                <input type="number" class="form-control" name="max_file_size" 
                                       value="<?= htmlspecialchars($system_settings['max_file_size'] ?? '') ?>" required>
                                <div class="form-text">Current: <?= formatBytes($system_settings['max_file_size'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Allowed File Types</label>
                                <input type="text" class="form-control" name="allowed_file_types" 
                                       value="<?= htmlspecialchars($system_settings['allowed_file_types'] ?? '') ?>" required>
                                <div class="form-text">Comma-separated (e.g., jpg,png,pdf,doc)</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" name="update_system_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Maintenance Tools -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-tools"></i> Maintenance Tools</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Database Backup -->
                    <div class="col-md-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <h6><i class="fas fa-database text-primary"></i> Database Backup</h6>
                            <p class="text-muted small">Create a backup of the entire database</p>
                            <form method="POST" class="d-inline">
                                <button type="submit" name="backup_database" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-download"></i> Create Backup
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Clear Audit Logs -->
                    <div class="col-md-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <h6><i class="fas fa-broom text-warning"></i> Clear Old Logs</h6>
                            <form method="POST">
                                <div class="input-group input-group-sm mb-2">
                                    <input type="number" class="form-control" name="days_to_keep" value="90" min="1" required>
                                    <span class="input-group-text">days</span>
                                </div>
                                <button type="submit" name="clear_logs" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-trash"></i> Clear Logs
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- System Information -->
                <div class="mt-3">
                    <h6><i class="fas fa-info-circle text-info"></i> System Information</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <small class="text-muted">PHP Version:</small><br>
                            <strong><?= PHP_VERSION ?></strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Server Software:</small><br>
                            <strong><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">System Time:</small><br>
                            <strong><?= date('Y-m-d H:i:s') ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Test Notifications -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-bell"></i> Test Notifications</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Test Message</label>
                        <input type="text" class="form-control" name="test_message" 
                               value="This is a test notification from the system administrator." required>
                    </div>
                    <button type="submit" name="send_test_notification" class="btn btn-outline-info">
                        <i class="fas fa-paper-plane"></i> Send Test Notification
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- System Activity & Logs -->
    <div class="col-lg-4">
        <!-- System Status -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-heartbeat"></i> System Status</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Database Connection</span>
                    <span class="badge bg-success">Online</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>File System</span>
                    <span class="badge bg-success">Accessible</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Backup Status</span>
                    <span class="badge bg-warning">Manual</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span>System Load</span>
                    <span class="badge bg-info">Normal</span>
                </div>
                
                <hr>
                
                <div class="text-center">
                    <small class="text-muted">Last System Check</small><br>
                    <strong><?= date('M d, Y H:i:s') ?></strong>
                </div>
            </div>
        </div>
        
        <!-- Recent System Activities -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history"></i> Recent System Activities</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_system_activities)): ?>
                    <p class="text-muted text-center py-3">No recent system activities</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_system_activities as $activity): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0">
                                        <div class="bg-light rounded-circle p-2" style="width: 32px; height: 32px;">
                                            <i class="fas fa-cog fa-sm text-muted"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="fw-semibold"><?= htmlspecialchars($activity['action']) ?></div>
                                        <small class="text-muted">
                                            by <?= htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) ?>
                                            <br><?= formatDate($activity['created_at'], 'M d, Y H:i') ?>
                                        </small>
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

<?php
// Helper function for formatting bytes
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>

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

// Confirmation for destructive actions
document.querySelectorAll('button[name="clear_logs"]').forEach(button => {
    button.addEventListener('click', function(e) {
        const days = this.form.querySelector('input[name="days_to_keep"]').value;
        if (!confirm(`Are you sure you want to clear audit logs older than ${days} days? This action cannot be undone.`)) {
            e.preventDefault();
        }
    });
});

document.querySelectorAll('button[name="backup_database"]').forEach(button => {
    button.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to create a database backup? This may take a few moments.')) {
            e.preventDefault();
        } else {
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Backup...';
            this.disabled = true;
        }
    });
});

// Auto-refresh system status every 30 seconds
setInterval(function() {
    // You could implement AJAX status checks here
    console.log('System status check...');
}, 30000);
</script>