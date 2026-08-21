<?php
// Reports and analytics page
$report_type = $_GET['type'] ?? 'overview';

// Helper function to safely escape HTML
function safeHtml($value, $default = 'N/A') {
    return htmlspecialchars($value ?? $default);
}

// Helper function to safely format numbers
function safeNumber($value, $decimals = 0) {
    return number_format($value ?? 0, $decimals);
}

// Helper function to format currency (if not defined elsewhere)
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return '₱' . number_format($amount ?? 0, 2);
    }
}

// Helper function to format dates (if not defined elsewhere)
if (!function_exists('formatDate')) {
    function formatDate($date) {
        return $date ? date('M d, Y', strtotime($date)) : 'N/A';
    }
}

// IMPORTANT: Handle report generation BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $type = $_POST['report_type'];
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $format = $_POST['format'] ?? 'html';
    
    // Generate report based on type
    switch ($type) {
        case 'programs':
            generateProgramReport($start_date, $end_date, $format);
            break;
        case 'beneficiaries':
            generateBeneficiaryReport($start_date, $end_date, $format);
            break;
        case 'financial':
            generateFinancialReport($start_date, $end_date, $format);
            break;
        case 'activities':
            generateActivityReport($start_date, $end_date, $format);
            break;
        default:
            setErrorMessage('Invalid report type.');
    }
}

// Report generation functions (MOVED TO TOP)
function generateProgramReport($start_date, $end_date, $format) {
    global $db;
    
    $where_clause = "";
    $params = [];
    
    if ($start_date && $end_date) {
        $where_clause = "WHERE date_start BETWEEN ? AND ?";
        $params = [$start_date, $end_date];
    }
    
    $programs = $db->fetchAll("
        SELECT p.*, u.first_name, u.last_name,
               (SELECT COUNT(*) FROM program_feedback WHERE program_id = p.program_id) as feedback_count
        FROM programs p
        LEFT JOIN users u ON p.created_by = u.user_id
        $where_clause
        ORDER BY p.date_start DESC
    ", $params);
    
    if ($format === 'csv') {
        exportToCSV($programs, 'programs_report_' . date('Y-m-d') . '.csv');
    } else {
        $_SESSION['report_data'] = $programs;
        $_SESSION['report_type'] = 'programs';
        header('Location: ?page=reports&type=generated&report=programs');
        exit();
    }
}

function generateBeneficiaryReport($start_date, $end_date, $format) {
    global $db;
    
    $where_clause = "";
    $params = [];
    
    if ($start_date && $end_date) {
        $where_clause = "WHERE date_start BETWEEN ? AND ?";
        $params = [$start_date, $end_date];
    }
    
    $data = $db->fetchAll("
        SELECT type_of_service, location, barangay,
               COUNT(*) as program_count,
               SUM(expected_participants) as expected_total,
               SUM(actual_participants) as actual_total,
               AVG(actual_participants) as avg_participants
        FROM programs 
        $where_clause
        GROUP BY type_of_service, location
        ORDER BY actual_total DESC
    ", $params);
    
    if ($format === 'csv') {
        exportToCSV($data, 'beneficiaries_report_' . date('Y-m-d') . '.csv');
    } else {
        $_SESSION['report_data'] = $data;
        $_SESSION['report_type'] = 'beneficiaries';
        header('Location: ?page=reports&type=generated&report=beneficiaries');
        exit();
    }
}

function generateFinancialReport($start_date, $end_date, $format) {
    global $db;
    
    $where_clause = "";
    $params = [];
    
    if ($start_date && $end_date) {
        $where_clause = "WHERE date_start BETWEEN ? AND ?";
        $params = [$start_date, $end_date];
    }
    
    $data = $db->fetchAll("
        SELECT p.title, p.type_of_service, p.location, p.date_start,
               p.budget_allocated, p.budget_used,
               (p.budget_allocated - p.budget_used) as remaining_budget,
               CASE 
                   WHEN p.budget_allocated > 0 THEN (p.budget_used / p.budget_allocated * 100)
                   ELSE 0 
               END as utilization_percentage
        FROM programs p
        $where_clause
        ORDER BY p.budget_allocated DESC
    ", $params);
    
    if ($format === 'csv') {
        exportToCSV($data, 'financial_report_' . date('Y-m-d') . '.csv');
    } else {
        $_SESSION['report_data'] = $data;
        $_SESSION['report_type'] = 'financial';
        header('Location: ?page=reports&type=generated&report=financial');
        exit();
    }
}

function generateActivityReport($start_date, $end_date, $format) {
    global $db;
    
    $where_clause = "";
    $params = [];
    
    if ($start_date && $end_date) {
        $where_clause = "WHERE p.date_start BETWEEN ? AND ?";
        $params = [$start_date, $end_date];
    }
    
    // Activity report combining program activities and feedback
    $data = $db->fetchAll("
        SELECT 
            'Program Created' as activity_type,
            p.title as description,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            p.date_start as activity_date,
            p.location,
            p.status,
            p.actual_participants as participants,
            p.budget_allocated as budget
        FROM programs p
        LEFT JOIN users u ON p.created_by = u.user_id
        $where_clause
        
        UNION ALL
        
        SELECT 
            'Feedback Submitted' as activity_type,
            CONCAT('Feedback for: ', pr.title) as description,
            pf.participant_name as user_name,
            pf.created_at as activity_date,
            pr.location,
            CONCAT('Rating: ', pf.rating, '/5') as status,
            1 as participants,
            0 as budget
        FROM program_feedback pf
        LEFT JOIN programs pr ON pf.program_id = pr.program_id
        " . ($start_date && $end_date ? "WHERE pf.created_at BETWEEN '$start_date' AND '$end_date'" : "") . "
        
        ORDER BY activity_date DESC
    ", $params);
    
    if ($format === 'csv') {
        exportToCSV($data, 'activities_report_' . date('Y-m-d') . '.csv');
    } else {
        $_SESSION['report_data'] = $data;
        $_SESSION['report_type'] = 'activities';
        header('Location: ?page=reports&type=generated&report=activities');
        exit();
    }
}

function exportToCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit();
}

// Get report statistics (AFTER function definitions)
$current_year = date('Y');
$current_month = date('Y-m');

// Programs statistics
$program_stats = [
    'total_programs' => $db->fetch("SELECT COUNT(*) as count FROM programs")['count'],
    'completed_programs' => $db->fetch("SELECT COUNT(*) as count FROM programs WHERE status = 'Completed'")['count'],
    'ongoing_programs' => $db->fetch("SELECT COUNT(*) as count FROM programs WHERE status = 'Ongoing'")['count'],
    'this_year' => $db->fetch("SELECT COUNT(*) as count FROM programs WHERE YEAR(date_start) = ?", [$current_year])['count'],
    'this_month' => $db->fetch("SELECT COUNT(*) as count FROM programs WHERE DATE_FORMAT(date_start, '%Y-%m') = ?", [$current_month])['count']
];

// Beneficiary statistics
$beneficiary_stats = [
    'total_beneficiaries' => $db->fetch("SELECT SUM(actual_participants) as total FROM programs WHERE status = 'Completed'")['total'] ?? 0,
    'expected_beneficiaries' => $db->fetch("SELECT SUM(expected_participants) as total FROM programs")['total'] ?? 0,
    'avg_participants' => $db->fetch("SELECT AVG(actual_participants) as avg FROM programs WHERE status = 'Completed'")['avg'] ?? 0
];

// Financial statistics
$financial_stats = [
    'total_budget' => $db->fetch("SELECT SUM(budget_allocated) as total FROM programs")['total'] ?? 0,
    'total_spent' => $db->fetch("SELECT SUM(budget_used) as total FROM programs")['total'] ?? 0,
    'avg_budget' => $db->fetch("SELECT AVG(budget_allocated) as avg FROM programs")['avg'] ?? 0
];

// Program types distribution
$program_types = $db->fetchAll("
    SELECT type_of_service, COUNT(*) as count, SUM(budget_allocated) as budget, SUM(actual_participants) as participants
    FROM programs 
    GROUP BY type_of_service 
    ORDER BY count DESC
");

// Monthly program trends (last 12 months)
$monthly_trends = $db->fetchAll("
    SELECT 
        DATE_FORMAT(date_start, '%Y-%m') as month,
        COUNT(*) as programs_count,
        SUM(actual_participants) as participants,
        SUM(budget_used) as budget_used
    FROM programs 
    WHERE date_start >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(date_start, '%Y-%m')
    ORDER BY month DESC
");

// Top performing programs
$top_programs = $db->fetchAll("
    SELECT p.*, u.first_name, u.last_name,
           (SELECT AVG(rating) FROM program_feedback WHERE program_id = p.program_id) as avg_rating,
           (SELECT COUNT(*) FROM program_feedback WHERE program_id = p.program_id) as feedback_count
    FROM programs p
    LEFT JOIN users u ON p.created_by = u.user_id
    WHERE p.status = 'Completed'
    ORDER BY p.actual_participants DESC, avg_rating DESC
    LIMIT 10
");

// Recent feedback
$recent_feedback = $db->fetchAll("
    SELECT pf.*, p.title as program_title
    FROM program_feedback pf
    LEFT JOIN programs p ON pf.program_id = p.program_id
    ORDER BY pf.created_at DESC
    LIMIT 10
");

// HTML OUTPUT STARTS HERE
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

<?php if ($report_type === 'overview'): ?>
    <!-- Reports Overview -->
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-chart-bar text-primary"></i> Reports & Analytics</h2>
            <p class="text-muted">Generate comprehensive reports and analyze extension program data.</p>
        </div>
    </div>

    <!-- Statistics Overview -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon blue">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div class="stat-value"><?= safeNumber($program_stats['total_programs']) ?></div>
                <div class="stat-label">Total Programs</div>
                <small class="text-muted"><?= safeNumber($program_stats['this_year']) ?> this year</small>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon green">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value"><?= safeNumber($beneficiary_stats['total_beneficiaries']) ?></div>
                <div class="stat-label">Total Beneficiaries</div>
                <small class="text-muted">Avg: <?= safeNumber($beneficiary_stats['avg_participants'], 1) ?> per program</small>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon orange">
                        <i class="fas fa-peso-sign"></i>
                    </div>
                </div>
                <div class="stat-value"><?= formatCurrency($financial_stats['total_budget'] ?? 0) ?></div>
                <div class="stat-label">Total Budget</div>
                <small class="text-muted"><?= formatCurrency($financial_stats['total_spent'] ?? 0) ?> spent</small>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon red">
                        <i class="fas fa-percentage"></i>
                    </div>
                </div>
                <div class="stat-value"><?= ($financial_stats['total_budget'] ?? 0) > 0 ? safeNumber((($financial_stats['total_spent'] ?? 0) / ($financial_stats['total_budget'] ?? 1)) * 100, 1) : 0 ?>%</div>
                <div class="stat-label">Budget Utilization</div>
                <small class="text-muted">Overall efficiency</small>
            </div>
        </div>
    </div>

    <!-- Report Generation Cards -->
    <div class="row mb-4">
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="stat-icon blue mx-auto mb-3">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h5>Programs Report</h5>
                    <p class="text-muted">Detailed analysis of all extension programs including timelines, participants, and outcomes.</p>
                    <button class="btn btn-primary" onclick="showReportModal('programs')">
                        <i class="fas fa-download"></i> Generate
                    </button>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="stat-icon green mx-auto mb-3">
                        <i class="fas fa-users"></i>
                    </div>
                    <h5>Beneficiaries Report</h5>
                    <p class="text-muted">Impact assessment showing reach and demographic breakdown of program beneficiaries.</p>
                    <button class="btn btn-success" onclick="showReportModal('beneficiaries')">
                        <i class="fas fa-download"></i> Generate
                    </button>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="stat-icon orange mx-auto mb-3">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h5>Financial Report</h5>
                    <p class="text-muted">Budget allocation, expenditure analysis, and cost-effectiveness of programs.</p>
                    <button class="btn btn-warning" onclick="showReportModal('financial')">
                        <i class="fas fa-download"></i> Generate
                    </button>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="stat-icon red mx-auto mb-3">
                        <i class="fas fa-history"></i>
                    </div>
                    <h5>Activity Report</h5>
                    <p class="text-muted">System usage and user activity logs for administrative oversight.</p>
                    <button class="btn btn-danger" onclick="showReportModal('activities')">
                        <i class="fas fa-download"></i> Generate
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Sections -->
    <div class="row">
        <!-- Program Types Distribution -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie text-info"></i> Program Types Distribution</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($program_types)): ?>
                        <p class="text-muted text-center py-3">No data available</p>
                    <?php else: ?>
                        <canvas id="programTypesChart" width="400" height="200"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Monthly Trends -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-line text-success"></i> Monthly Program Trends</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($monthly_trends)): ?>
                        <p class="text-muted text-center py-3">No data available</p>
                    <?php else: ?>
                        <canvas id="monthlyTrendsChart" width="400" height="200"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performing Programs -->
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-trophy text-warning"></i> Top Performing Programs</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($top_programs)): ?>
                        <p class="text-muted text-center py-3">No completed programs yet</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Program</th>
                                        <th>Type</th>
                                        <th>Participants</th>
                                        <th>Rating</th>
                                        <th>Feedback</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($top_programs, 0, 8) as $program): ?>
                                        <tr>
                                            <td>
                                                <strong><?= safeHtml($program['title']) ?></strong><br>
                                                <small class="text-muted"><?= formatDate($program['date_start'] ?? 'now') ?></small>
                                            </td>
                                            <td><?= safeHtml($program['type_of_service']) ?></td>
                                            <td><?= safeNumber($program['actual_participants']) ?></td>
                                            <td>
                                                <?php if ($program['avg_rating']): ?>
                                                    <div class="text-warning">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star<?= $i <= round($program['avg_rating']) ? '' : '-o' ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <small class="text-muted"><?= number_format($program['avg_rating'], 1) ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">No rating</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= number_format($program['feedback_count']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Feedback -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-comments text-primary"></i> Recent Feedback</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_feedback)): ?>
                        <p class="text-muted text-center py-3">No feedback yet</p>
                    <?php else: ?>
                        <?php foreach (array_slice($recent_feedback, 0, 5) as $feedback): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <small class="fw-bold"><?= safeHtml($feedback['participant_name'], 'Unknown') ?></small>
                                    <div class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?= $i <= ($feedback['rating'] ?? 0) ? '' : '-o' ?> fa-xs"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <small class="text-muted"><?= safeHtml($feedback['program_title'], 'Unknown Program') ?></small><br>
                                <small class="text-muted"><?= formatDate($feedback['created_at'] ?? 'now') ?></small>
                                <p class="small mt-1 mb-0"><?= safeHtml(substr($feedback['feedback_text'] ?? '', 0, 100)) ?><?= strlen($feedback['feedback_text'] ?? '') > 100 ? '...' : '' ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($report_type === 'generated'): ?>
    <!-- Generated Report Display -->
    <?php
    $report_data = $_SESSION['report_data'] ?? [];
    $report_name = $_GET['report'] ?? 'unknown';
    unset($_SESSION['report_data'], $_SESSION['report_type']);
    ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-file-alt text-success"></i> Generated Report: <?= ucfirst($report_name) ?></h2>
        <div>
            <button class="btn btn-success me-2" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
            <a href="?page=reports" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Reports
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($report_data)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> No data available for this report.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <?php foreach (array_keys($report_data[0]) as $header): ?>
                                    <th><?= ucwords(str_replace('_', ' ', $header)) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                        <td><?= safeHtml($value) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3 text-muted">
                    <small>
                        Report generated on <?= date('F d, Y H:i:s') ?> | 
                        Total records: <?= safeNumber(count($report_data)) ?>
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<!-- Report Generation Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-download text-primary"></i> Generate Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="report_type" id="reportType">
                    
                    <div class="mb-3">
                        <label class="form-label">Date Range (Optional)</label>
                        <div class="row">
                            <div class="col-6">
                                <input type="date" class="form-control" name="start_date" placeholder="Start Date">
                            </div>
                            <div class="col-6">
                                <input type="date" class="form-control" name="end_date" placeholder="End Date">
                            </div>
                        </div>
                        <div class="form-text">Leave blank to include all data</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Export Format</label>
                        <select class="form-select" name="format">
                            <option value="html">View Online</option>
                            <option value="csv">Download CSV</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="generate_report" class="btn btn-primary">
                        <i class="fas fa-download"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<script>
function showReportModal(reportType) {
    document.getElementById('reportType').value = reportType;
    const modal = new bootstrap.Modal(document.getElementById('reportModal'));
    modal.show();
}

// Program Types Chart
<?php if (!empty($program_types)): ?>
const programTypesCtx = document.getElementById('programTypesChart');
if (programTypesCtx) {
    new Chart(programTypesCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($program_types, 'type_of_service')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($program_types, 'count')) ?>,
                backgroundColor: [
                    '#1e40af', '#059669', '#d97706', '#ef4444', '#8b5cf6', '#06b6d4', '#84cc16'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}
<?php endif; ?>

// Monthly Trends Chart
<?php if (!empty($monthly_trends)): ?>
const monthlyTrendsCtx = document.getElementById('monthlyTrendsChart');
if (monthlyTrendsCtx) {
    new Chart(monthlyTrendsCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_reverse(array_column($monthly_trends, 'month'))) ?>,
            datasets: [{
                label: 'Programs',
                data: <?= json_encode(array_reverse(array_column($monthly_trends, 'programs_count'))) ?>,
                borderColor: '#1e40af',
                backgroundColor: 'rgba(30, 64, 175, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}
<?php endif; ?>

// Print styles
const printStyles = `
    @media print {
        .btn, .modal, .alert { display: none !important; }
        .card { border: 1px solid #ddd !important; }
        .table { font-size: 12px; }
    }
`;

const styleSheet = document.createElement("style");
styleSheet.type = "text/css";
styleSheet.innerText = printStyles;
document.head.appendChild(styleSheet);
</script>