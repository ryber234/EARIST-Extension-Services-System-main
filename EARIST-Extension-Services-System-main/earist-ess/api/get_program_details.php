<?php
require_once '../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Program ID is required']);
    exit();
}

$program_id = (int)$_GET['id'];

try {
    // Get program details
    $program = $db->fetch("
        SELECT p.*, u.first_name, u.last_name,
               (SELECT COUNT(*) FROM program_feedback WHERE program_id = p.program_id) as feedback_count,
               (SELECT AVG(rating) FROM program_feedback WHERE program_id = p.program_id) as avg_rating
        FROM programs p 
        LEFT JOIN users u ON p.created_by = u.user_id 
        WHERE p.program_id = ? AND p.approval_status = 'Approved'", [$program_id]);
    
    if (!$program) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Program not found']);
        exit();
    }
    
    // Get program resources
    $resources = $db->fetchAll("SELECT * FROM program_resources WHERE program_id = ? ORDER BY resource_name", [$program_id]);
    
    // Get program images
    $images = $db->fetchAll("SELECT * FROM program_images WHERE program_id = ? ORDER BY uploaded_at DESC", [$program_id]);
    
    // Get program feedback (limit to recent ones for display)
    $feedback = $db->fetchAll("
        SELECT * FROM program_feedback 
        WHERE program_id = ? AND is_anonymous = 0 
        ORDER BY created_at DESC 
        LIMIT 10", [$program_id]);
    
    // Generate HTML content
    ob_start();
    ?>
    
    <div class="row">
        <div class="col-md-8">
            <!-- Program Information -->
            <div class="mb-4">
                <h4><?= htmlspecialchars($program['title']) ?></h4>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Type:</strong> 
                            <span class="badge bg-<?= $program['type_of_service'] === 'Health' ? 'danger' : ($program['type_of_service'] === 'Education' ? 'primary' : 'success') ?>">
                                <?= htmlspecialchars($program['type_of_service']) ?>
                            </span>
                        </p>
                        <p class="mb-1"><strong>Location:</strong> <?= htmlspecialchars($program['location']) ?></p>
                        <?php if ($program['barangay']): ?>
                            <p class="mb-1"><strong>Barangay:</strong> <?= htmlspecialchars($program['barangay']) ?></p>
                        <?php endif; ?>
                        <p class="mb-1"><strong>Status:</strong> 
                            <span class="status-badge status-<?= strtolower($program['status']) ?>">
                                <?= htmlspecialchars($program['status']) ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Date:</strong> <?= formatDate($program['date_start']) ?>
                        <?php if ($program['date_end'] && $program['date_end'] !== $program['date_start']): ?>
                            to <?= formatDate($program['date_end']) ?>
                        <?php endif; ?>
                        </p>
                        <p class="mb-1"><strong>Time:</strong> <?= date('g:i A', strtotime($program['time_start'])) ?>
                        <?php if ($program['time_end']): ?>
                            to <?= date('g:i A', strtotime($program['time_end'])) ?>
                        <?php endif; ?>
                        </p>
                        <p class="mb-1"><strong>Participants:</strong> 
                        <?php if ($program['status'] === 'Completed'): ?>
                            <?= number_format($program['actual_participants']) ?> (actual)
                        <?php else: ?>
                            <?= number_format($program['expected_participants']) ?> (expected)
                        <?php endif; ?>
                        </p>
                        <p class="mb-1"><strong>Organized by:</strong> <?= htmlspecialchars($program['first_name'] . ' ' . $program['last_name']) ?></p>
                    </div>
                </div>
                
                <?php if ($program['avg_rating']): ?>
                    <div class="mb-3">
                        <strong>Rating:</strong>
                        <div class="d-inline-flex align-items-center">
                            <div class="text-warning me-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?= $i <= round($program['avg_rating']) ? '' : '-o' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span><?= number_format($program['avg_rating'], 1) ?> out of 5 (<?= $program['feedback_count'] ?> reviews)</span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($program['description']): ?>
                    <div class="mb-3">
                        <strong>Description:</strong>
                        <p class="mt-2"><?= nl2br(htmlspecialchars($program['description'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($program['objectives']): ?>
                    <div class="mb-3">
                        <strong>Objectives:</strong>
                        <p class="mt-2"><?= nl2br(htmlspecialchars($program['objectives'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($program['target_beneficiaries']): ?>
                    <div class="mb-3">
                        <strong>Target Beneficiaries:</strong>
                        <p class="mt-2"><?= htmlspecialchars($program['target_beneficiaries']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Program Resources -->
            <?php if (!empty($resources)): ?>
                <div class="mb-4">
                    <h6><i class="fas fa-boxes text-success"></i> Program Resources</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Resource</th>
                                    <th>Quantity</th>
                                    <th>Provider</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resources as $resource): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($resource['resource_name']) ?></td>
                                        <td><?= number_format($resource['quantity']) ?> <?= htmlspecialchars($resource['unit']) ?></td>
                                        <td><?= htmlspecialchars($resource['provider']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $resource['status'] === 'Delivered' ? 'success' : 'warning' ?>">
                                                <?= htmlspecialchars($resource['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Program Images -->
            <?php if (!empty($images)): ?>
                <div class="mb-4">
                    <h6><i class="fas fa-images text-info"></i> Program Gallery</h6>
                    <div class="row">
                        <?php foreach (array_slice($images, 0, 6) as $image): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <img src="<?= htmlspecialchars($image['image_path']) ?>" class="card-img-top" alt="Program Image" style="height: 150px; object-fit: cover;">
                                    <?php if ($image['image_caption']): ?>
                                        <div class="card-body p-2">
                                            <small class="text-muted"><?= htmlspecialchars($image['image_caption']) ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-4">
            <!-- Program Statistics -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Program Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 border-end">
                            <div class="fw-bold text-primary fs-5">
                                <?= $program['status'] === 'Completed' ? number_format($program['actual_participants']) : number_format($program['expected_participants']) ?>
                            </div>
                            <small class="text-muted">
                                <?= $program['status'] === 'Completed' ? 'Actual' : 'Expected' ?> Participants
                            </small>
                        </div>
                        <div class="col-6">
                            <div class="fw-bold text-success fs-5"><?= number_format($program['feedback_count']) ?></div>
                            <small class="text-muted">Feedback Received</small>
                        </div>
                    </div>
                    
                    <?php if ($program['budget_allocated'] > 0): ?>
                        <hr>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span>Budget:</span>
                                <strong><?= formatCurrency($program['budget_allocated']) ?></strong>
                            </div>
                        </div>
                        
                        <?php if ($program['budget_used'] > 0): ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span>Used:</span>
                                    <strong><?= formatCurrency($program['budget_used']) ?></strong>
                                </div>
                            </div>
                            
                            <div class="progress">
                                <div class="progress-bar" style="width: <?= ($program['budget_used'] / $program['budget_allocated']) * 100 ?>%"></div>
                            </div>
                            <small class="text-muted">
                                <?= number_format(($program['budget_used'] / $program['budget_allocated']) * 100, 1) ?>% utilized
                            </small>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Feedback -->
            <?php if (!empty($feedback)): ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-comments"></i> Recent Feedback</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach (array_slice($feedback, 0, 3) as $fb): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <small class="fw-bold"><?= htmlspecialchars($fb['participant_name']) ?></small>
                                    <div class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?= $i <= $fb['rating'] ? '' : '-o' ?> fa-xs"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <small class="text-muted"><?= formatDate($fb['created_at']) ?></small>
                                <p class="small mt-1 mb-0"><?= htmlspecialchars(substr($fb['feedback_text'], 0, 100)) ?><?= strlen($fb['feedback_text']) > 100 ? '...' : '' ?></p>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($feedback) > 3): ?>
                            <div class="text-center">
                                <small class="text-muted">+<?= count($feedback) - 3 ?> more reviews</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
    $html = ob_get_clean();
    
    // Return success response with HTML
    echo json_encode([
        'success' => true,
        'html' => $html,
        'program' => $program
    ]);
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>