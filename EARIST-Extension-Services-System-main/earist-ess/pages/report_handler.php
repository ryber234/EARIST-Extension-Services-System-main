<?php
// report_handler.php - Separate file to handle report generation
require_once 'config.php';
requireLogin();

// Handle report generation
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
            header('Location: dashboard.php?page=reports');
            exit();
    }
} else {
    // Redirect if accessed directly
    header('Location: dashboard.php?page=reports');
    exit();
}

// Report generation functions
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
        header('Location: dashboard.php?page=reports&type=generated&report=programs');
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
        header('Location: dashboard.php?page=reports&type=generated&report=beneficiaries');
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
        header('Location: dashboard.php?page=reports&type=generated&report=financial');
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
        header('Location: dashboard.php?page=reports&type=generated&report=activities');
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
?>