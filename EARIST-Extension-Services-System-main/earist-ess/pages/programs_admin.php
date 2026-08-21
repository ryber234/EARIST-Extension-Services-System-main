<?php
// Programs management page
$action = $_GET['action'] ?? 'list';
$program_id = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_program']) && hasRole(['Admin', 'Authorized User'])) {
        // Add new program
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $objectives = sanitizeInput($_POST['objectives']);
        $type_of_service = sanitizeInput($_POST['type_of_service']);
        $target_beneficiaries = sanitizeInput($_POST['target_beneficiaries']);
        $location = sanitizeInput($_POST['location']);
        $barangay = sanitizeInput($_POST['barangay']);
        $date_start = $_POST['date_start'];
        $date_end = $_POST['date_end'] ?: null;
        $time_start = $_POST['time_start'];
        $time_end = $_POST['time_end'] ?: null;
        $expected_participants = (int)$_POST['expected_participants'];
        $budget_allocated = (float)$_POST['budget_allocated'];
        
        $sql = "INSERT INTO programs (title, description, objectives, type_of_service, target_beneficiaries, 
                location, barangay, date_start, date_end, time_start, time_end, expected_participants, 
                budget_allocated, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [$title, $description, $objectives, $type_of_service, $target_beneficiaries, 
                  $location, $barangay, $date_start, $date_end, $time_start, $time_end, 
                  $expected_participants, $budget_allocated, $_SESSION['user_id']];
        
        if ($db->query($sql, $params)) {
            $new_program_id = $db->lastInsertId();
            logActivity($_SESSION['user_id'], 'Program Created', 'programs', $new_program_id);
            setSuccessMessage('Program created successfully!');
            header('Location: ?page=programs');
            exit();
        } else {
            setErrorMessage('Failed to create program.');
        }
    }
    
    if (isset($_POST['update_program']) && hasRole(['Admin', 'Authorized User'])) {
        // Update program
        $id = (int)$_POST['program_id'];
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $objectives = sanitizeInput($_POST['objectives']);
        $type_of_service = sanitizeInput($_POST['type_of_service']);
        $target_beneficiaries = sanitizeInput($_POST['target_beneficiaries']);
        $location = sanitizeInput($_POST['location']);
        $barangay = sanitizeInput($_POST['barangay']);
        $date_start = $_POST['date_start'];
        $date_end = $_POST['date_end'] ?: null;
        $time_start = $_POST['time_start'];
        $time_end = $_POST['time_end'] ?: null;
        $expected_participants = (int)$_POST['expected_participants'];
        $actual_participants = (int)$_POST['actual_participants'];
        $budget_allocated = (float)$_POST['budget_allocated'];
        $budget_used = (float)$_POST['budget_used'];
        $status = $_POST['status'];
        
        $sql = "UPDATE programs SET title=?, description=?, objectives=?, type_of_service=?, target_beneficiaries=?, 
                location=?, barangay=?, date_start=?, date_end=?, time_start=?, time_end=?, expected_participants=?, 
                actual_participants=?, budget_allocated=?, budget_used=?, status=? WHERE program_id=?";
        
        $params = [$title, $description, $objectives, $type_of_service, $target_beneficiaries, 
                  $location, $barangay, $date_start, $date_end, $time_start, $time_end, 
                  $expected_participants, $actual_participants, $budget_allocated, $budget_used, $status, $id];
        
        if ($db->query($sql, $params)) {
            logActivity($_SESSION['user_id'], 'Program Updated', 'programs', $id);
            setSuccessMessage('Program updated successfully!');
            header('Location: ?page=programs');
            exit();
        } else {
            setErrorMessage('Failed to update program.');
        }
    }
    
    if (isset($_POST['delete_program']) && hasRole(['Admin'])) {
        $id = (int)$_POST['program_id'];
        if ($db->query("DELETE FROM programs WHERE program_id = ?", [$id])) {
            logActivity($_SESSION['user_id'], 'Program Deleted', 'programs', $id);
            setSuccessMessage('Program deleted successfully!');
        } else {
            setErrorMessage('Failed to delete program.');
        }
        header('Location: ?page=programs');
        exit();
    }
}

// Get programs with pagination and filtering
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page_num = (int)($_GET['p'] ?? 1);
$per_page = 10; // Number of programs per page

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.title LIKE ? OR p.description LIKE ? OR p.location LIKE ?)";
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

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM programs p $where_clause";
$total_programs = $db->fetch($count_sql, $params)['total'] ?? 0;

// Calculate pagination
$total_pages = ceil($total_programs / $per_page);
$current_page = max(1, min($page_num, $total_pages));
$offset = ($current_page - 1) * $per_page;

// Get programs with pagination
$sql = "SELECT p.*, u.first_name, u.last_name, 
        (SELECT COUNT(*) FROM program_feedback pf WHERE pf.program_id = p.program_id) as feedback_count
        FROM programs p 
        LEFT JOIN users u ON p.created_by = u.user_id 
        $where_clause 
        ORDER BY p.created_at DESC 
        LIMIT $per_page OFFSET $offset";

$programs = $db->fetchAll($sql, $params);

// Create programs_data array for pagination
$programs_data = [
    'programs' => $programs,
    'total' => $total_programs,
    'total_pages' => $total_pages,
    'current_page' => $current_page,
    'per_page' => $per_page,
    'has_prev' => $current_page > 1,
    'has_next' => $current_page < $total_pages
];

// Get program types for filter
$program_types = $db->fetchAll("SELECT DISTINCT type_of_service FROM programs ORDER BY type_of_service");

// If viewing/editing a specific program
$current_program = null;
if ($program_id && in_array($action, ['view', 'edit'])) {
    $current_program = $db->fetch("
        SELECT p.*, u.first_name, u.last_name 
        FROM programs p 
        LEFT JOIN users u ON p.created_by = u.user_id 
        WHERE p.program_id = ?", [$program_id]);
    
    if (!$current_program) {
        setErrorMessage('Program not found.');
        header('Location: ?page=programs');
        exit();
    }
    
    // Get program resources
    $program_resources = $db->fetchAll("SELECT * FROM program_resources WHERE program_id = ?", [$program_id]);
    
    // Get program feedback
    $program_feedback = $db->fetchAll("SELECT * FROM program_feedback WHERE program_id = ? ORDER BY created_at DESC", [$program_id]);
}
?>

<!-- Flash Messages -->
<?php if ($flash_success = getFlashMessage('success')): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($flash_success ?? '') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($flash_error = getFlashMessage('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($flash_error ?? '') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Programs List View -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-calendar-alt text-primary"></i> Extension Programs</h2>
        <?php if (hasRole(['Admin', 'Authorized User'])): ?>
            <a href="?page=programs&action=add" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Program
            </a>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="page" value="programs">
                
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Search programs...">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Program Type</label>
                    <select class="form-select" name="type">
                        <option value="">All Types</option>
                        <?php foreach ($program_types as $type): ?>
                            <option value="<?= htmlspecialchars($type['type_of_service'] ?? '') ?>" <?= $type_filter === $type['type_of_service'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['type_of_service'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="Planned" <?= $status_filter === 'Planned' ? 'selected' : '' ?>>Planned</option>
                        <option value="Ongoing" <?= $status_filter === 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                        <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="Cancelled" <?= $status_filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
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

    <!-- Programs Table -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Program Details</th>
                        <th>Type</th>
                        <th>Date & Time</th>
                        <th>Location</th>
                        <th>Participants</th>
                        <th>Budget</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($programs)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i><br>
                                <h5 class="text-muted">No programs found</h5>
                                <p class="text-muted">Try adjusting your filters or add a new program.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($programs as $program): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($program['title'] ?? '') ?></strong><br>
                                        <small class="text-muted">
                                            Created by <?= htmlspecialchars(($program['first_name'] ?? '') . ' ' . ($program['last_name'] ?? '')) ?>
                                            <br><?= formatDate($program['created_at']) ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?= htmlspecialchars($program['type_of_service'] ?? '') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-nowrap">
                                        <i class="fas fa-calendar text-muted"></i> <?= formatDate($program['date_start']) ?><br>
                                        <i class="fas fa-clock text-muted"></i> <?= date('g:i A', strtotime($program['time_start'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <?= htmlspecialchars($program['location'] ?? '') ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($program['barangay'] ?? '') ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($program['status'] === 'Completed'): ?>
                                        <strong><?= number_format($program['actual_participants']) ?></strong> actual
                                    <?php else: ?>
                                        <?= number_format($program['expected_participants']) ?> expected
                                    <?php endif; ?>
                                    <?php if ($program['feedback_count'] > 0): ?>
                                        <br><small class="text-success">
                                            <i class="fas fa-comments"></i> <?= $program['feedback_count'] ?> feedback
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= formatCurrency($program['budget_allocated']) ?></strong><br>
                                        <?php if ($program['budget_used'] > 0): ?>
                                            <small class="text-muted">Used: <?= formatCurrency($program['budget_used']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($program['status']) ?>">
                                        <?= htmlspecialchars($program['status'] ?? '') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="?page=programs&action=view&id=<?= $program['program_id'] ?>" class="btn btn-sm btn-outline-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (hasRole(['Admin', 'Authorized User'])): ?>
                                            <a href="?page=programs&action=edit&id=<?= $program['program_id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (hasRole(['Admin'])): ?>
                                            <button class="btn btn-sm btn-outline-danger" title="Delete" onclick="deleteProgram(<?= $program['program_id'] ?>)">
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
        <?php if ($programs_data['total_pages'] > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($programs_data['has_prev']): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=programs&p=<?= $programs_data['current_page'] - 1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($type_filter) ?>&status=<?= urlencode($status_filter) ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $programs_data['current_page'] - 2); $i <= min($programs_data['total_pages'], $programs_data['current_page'] + 2); $i++): ?>
                            <li class="page-item <?= $i === $programs_data['current_page'] ? 'active' : '' ?>">
                                <a class="page-link" href="?page=programs&p=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($type_filter) ?>&status=<?= urlencode($status_filter) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($programs_data['has_next']): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=programs&p=<?= $programs_data['current_page'] + 1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($type_filter) ?>&status=<?= urlencode($status_filter) ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <div class="text-center text-muted mt-2">
                    Showing <?= number_format(($programs_data['current_page'] - 1) * $programs_data['per_page'] + 1) ?> to 
                    <?= number_format(min($programs_data['current_page'] * $programs_data['per_page'], $programs_data['total'])) ?> of 
                    <?= number_format($programs_data['total']) ?> entries
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'add' && hasRole(['Admin', 'Authorized User'])): ?>
    <!-- Add Program Form -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-plus text-success"></i> Add New Program</h2>
        <a href="?page=programs" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Programs
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label">Program Title *</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" rows="4" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Objectives</label>
                            <textarea class="form-control" name="objectives" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Program Type *</label>
                            <select class="form-select" name="type_of_service" required>
                                <option value="">Select Type</option>
                                <option value="Health">Health</option>
                                <option value="Education">Education</option>
                                <option value="Agriculture">Agriculture</option>
                                <option value="Technology">Technology</option>
                                <option value="Environment">Environment</option>
                                <option value="Social Services">Social Services</option>
                                <option value="Skills Training">Skills Training</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Target Beneficiaries</label>
                            <input type="text" class="form-control" name="target_beneficiaries" placeholder="e.g., Students, Farmers, Seniors">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Expected Participants *</label>
                            <input type="number" class="form-control" name="expected_participants" min="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Budget Allocated *</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" class="form-control" name="budget_allocated" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Location *</label>
                            <input type="text" class="form-control" name="location" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Barangay</label>
                            <input type="text" class="form-control" name="barangay">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date *</label>
                                    <input type="date" class="form-control" name="date_start" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" name="date_end">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Time *</label>
                                    <input type="time" class="form-control" name="time_start" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">End Time</label>
                                    <input type="time" class="form-control" name="time_end">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <a href="?page=programs" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" name="add_program" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Program
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'view' && $current_program): ?>
    <!-- View Program Details -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-eye text-info"></i> Program Details</h2>
        <div>
            <?php if (hasRole(['Admin', 'Authorized User'])): ?>
                <a href="?page=programs&action=edit&id=<?= $current_program['program_id'] ?>" class="btn btn-warning me-2">
                    <i class="fas fa-edit"></i> Edit Program
                </a>
            <?php endif; ?>
            <a href="?page=programs" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Programs
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Program Information -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Program Information</h5>
                </div>
                <div class="card-body">
                    <h4><?= htmlspecialchars($current_program['title'] ?? '') ?></h4>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Type:</strong> <?= htmlspecialchars($current_program['type_of_service'] ?? '') ?><br>
                            <strong>Location:</strong> <?= htmlspecialchars($current_program['location'] ?? '') ?><br>
                            <?php if ($current_program['barangay']): ?>
                                <strong>Barangay:</strong> <?= htmlspecialchars($current_program['barangay'] ?? '') ?><br>
                            <?php endif; ?>
                            <strong>Status:</strong> 
                            <span class="status-badge status-<?= strtolower($current_program['status']) ?>">
                                <?= htmlspecialchars($current_program['status'] ?? '') ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <strong>Date:</strong> <?= formatDate($current_program['date_start']) ?> 
                            <?php if ($current_program['date_end']): ?>
                                to <?= formatDate($current_program['date_end']) ?>
                            <?php endif; ?><br>
                            <strong>Time:</strong> <?= date('g:i A', strtotime($current_program['time_start'])) ?>
                            <?php if ($current_program['time_end']): ?>
                                to <?= date('g:i A', strtotime($current_program['time_end'])) ?>
                            <?php endif; ?><br>
                            <strong>Created by:</strong> <?= htmlspecialchars(($current_program['first_name'] ?? '') . ' ' . ($current_program['last_name'] ?? '')) ?>
                        </div>
                    </div>
                    
                    <?php if ($current_program['description']): ?>
                        <h6>Description:</h6>
                        <p><?= nl2br(htmlspecialchars($current_program['description'] ?? '')) ?></p>
                    <?php endif; ?>
                    
                    <?php if ($current_program['objectives']): ?>
                        <h6>Objectives:</h6>
                        <p><?= nl2br(htmlspecialchars($current_program['objectives'] ?? '')) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Program Resources -->
            <?php if (!empty($program_resources)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-boxes"></i> Program Resources</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Resource</th>
                                        <th>Quantity</th>
                                        <th>Cost per Unit</th>
                                        <th>Total Cost</th>
                                        <th>Provider</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($program_resources as $resource): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($resource['resource_name'] ?? '') ?></td>
                                            <td><?= number_format($resource['quantity']) ?> <?= htmlspecialchars($resource['unit'] ?? '') ?></td>
                                            <td><?= formatCurrency($resource['cost_per_unit']) ?></td>
                                            <td><?= formatCurrency($resource['total_cost']) ?></td>
                                            <td><?= htmlspecialchars($resource['provider'] ?? '') ?></td>
                                            <td>
                                                <span class="badge bg-<?= $resource['status'] === 'Delivered' ? 'success' : 'warning' ?>">
                                                    <?= htmlspecialchars($resource['status'] ?? '') ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Program Feedback -->
            <?php if (!empty($program_feedback)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-comments"></i> Participant Feedback</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($program_feedback as $feedback): ?>
                            <div class="border-bottom pb-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?= htmlspecialchars($feedback['participant_name'] ?? '') ?></strong>
                                        <div class="text-warning">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?= $i <= $feedback['rating'] ? '' : '-o' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <small class="text-muted"><?= formatDate($feedback['created_at']) ?></small>
                                </div>
                                <p class="mt-2 mb-1"><?= nl2br(htmlspecialchars($feedback['feedback_text'] ?? '')) ?></p>
                                <?php if ($feedback['suggestions']): ?>
                                    <div class="alert alert-light py-2">
                                        <strong>Suggestions:</strong> <?= nl2br(htmlspecialchars($feedback['suggestions'] ?? '')) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <!-- Program Statistics -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Program Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 border-end">
                            <div class="fw-bold text-primary fs-4">
                                <?= $current_program['status'] === 'Completed' ? number_format($current_program['actual_participants']) : number_format($current_program['expected_participants']) ?>
                            </div>
                            <small class="text-muted">
                                <?= $current_program['status'] === 'Completed' ? 'Actual' : 'Expected' ?> Participants
                            </small>
                        </div>
                        <div class="col-6">
                            <div class="fw-bold text-success fs-4"><?= count($program_feedback) ?></div>
                            <small class="text-muted">Feedback Received</small>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-2">
                        <div class="d-flex justify-content-between">
                            <span>Budget Allocated:</span>
                            <strong><?= formatCurrency($current_program['budget_allocated']) ?></strong>
                        </div>
                    </div>
                    
                    <?php if ($current_program['budget_used'] > 0): ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span>Budget Used:</span>
                                <strong><?= formatCurrency($current_program['budget_used']) ?></strong>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span>Remaining:</span>
                                <strong class="text-success"><?= formatCurrency($current_program['budget_allocated'] - $current_program['budget_used']) ?></strong>
                            </div>
                        </div>
                        
                        <div class="progress">
                            <div class="progress-bar" style="width: <?= ($current_program['budget_used'] / $current_program['budget_allocated']) * 100 ?>%"></div>
                        </div>
                        <small class="text-muted">
                            <?= number_format(($current_program['budget_used'] / $current_program['budget_allocated']) * 100, 1) ?>% of budget used
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <?php if (hasRole(['Admin', 'Authorized User'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-file-download"></i> Generate Report
                            </button>
                            <button class="btn btn-outline-success btn-sm">
                                <i class="fas fa-share"></i> Share Program
                            </button>
                            <button class="btn btn-outline-info btn-sm">
                                <i class="fas fa-copy"></i> Duplicate Program
                            </button>
                            <?php if ($current_program['status'] !== 'Completed'): ?>
                                <button class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-edit"></i> Update Status
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($action === 'edit' && $current_program && hasRole(['Admin', 'Authorized User'])): ?>
    <!-- Edit Program Form -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-edit text-warning"></i> Edit Program</h2>
        <div>
            <a href="?page=programs&action=view&id=<?= $current_program['program_id'] ?>" class="btn btn-info me-2">
                <i class="fas fa-eye"></i> View Details
            </a>
            <a href="?page=programs" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Programs
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="program_id" value="<?= $current_program['program_id'] ?>">
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label">Program Title *</label>
                            <input type="text" class="form-control" name="title" value="<?= htmlspecialchars($current_program['title'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" rows="4" required><?= htmlspecialchars($current_program['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Objectives</label>
                            <textarea class="form-control" name="objectives" rows="3"><?= htmlspecialchars($current_program['objectives'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Program Type *</label>
                            <select class="form-select" name="type_of_service" required>
                                <option value="">Select Type</option>
                                <?php
                                $types = ['Health', 'Education', 'Agriculture', 'Technology', 'Environment', 'Social Services', 'Skills Training'];
                                foreach ($types as $type): ?>
                                    <option value="<?= $type ?>" <?= $current_program['type_of_service'] === $type ? 'selected' : '' ?>>
                                        <?= $type ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Target Beneficiaries</label>
                            <input type="text" class="form-control" name="target_beneficiaries" value="<?= htmlspecialchars($current_program['target_beneficiaries'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <?php foreach (['Planned', 'Ongoing', 'Completed', 'Cancelled'] as $status): ?>
                                    <option value="<?= $status ?>" <?= $current_program['status'] === $status ? 'selected' : '' ?>>
                                        <?= $status ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Location *</label>
                            <input type="text" class="form-control" name="location" value="<?= htmlspecialchars($current_program['location'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Barangay</label>
                            <input type="text" class="form-control" name="barangay" value="<?= htmlspecialchars($current_program['barangay'] ?? '') ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Expected Participants *</label>
                                    <input type="number" class="form-control" name="expected_participants" value="<?= $current_program['expected_participants'] ?>" min="1" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Actual Participants</label>
                                    <input type="number" class="form-control" name="actual_participants" value="<?= $current_program['actual_participants'] ?>" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date *</label>
                                    <input type="date" class="form-control" name="date_start" value="<?= $current_program['date_start'] ?>" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" name="date_end" value="<?= $current_program['date_end'] ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Time *</label>
                                    <input type="time" class="form-control" name="time_start" value="<?= $current_program['time_start'] ?>" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">End Time</label>
                                    <input type="time" class="form-control" name="time_end" value="<?= $current_program['time_end'] ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Budget Allocated *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control" name="budget_allocated" value="<?= $current_program['budget_allocated'] ?>" step="0.01" min="0" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Budget Used</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control" name="budget_used" value="<?= $current_program['budget_used'] ?>" step="0.01" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <a href="?page=programs&action=view&id=<?= $current_program['program_id'] ?>" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" name="update_program" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Program
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> Invalid action or program not found.
    </div>
<?php endif; ?>

<!-- Delete Program Modal -->
<div class="modal fade" id="deleteProgramModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-trash text-danger"></i> Delete Program
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this program? This action cannot be undone.</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This will also delete all associated resources, feedback, and documents.
                </div>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteProgramForm">
                    <input type="hidden" name="program_id" id="deleteProgramId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_program" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Program
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteProgram(programId) {
    document.getElementById('deleteProgramId').value = programId;
    const modal = new bootstrap.Modal(document.getElementById('deleteProgramModal'));
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

// Date validation
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.querySelector('input[name="date_start"]');
    const endDateInput = document.querySelector('input[name="date_end"]');
    
    if (startDateInput && endDateInput) {
        startDateInput.addEventListener('change', function() {
            endDateInput.min = this.value;
        });
    }
});
</script>