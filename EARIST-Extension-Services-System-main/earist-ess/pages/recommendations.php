<?php
// Program recommendations page - Authorized Users only
requireRole(['Authorized User']);

// Handle recommendation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_from_recommendation'])) {
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $type_of_service = sanitizeInput($_POST['type_of_service']);
        $target_beneficiaries = sanitizeInput($_POST['target_beneficiaries']);
        $location = sanitizeInput($_POST['location']);
        $barangay = sanitizeInput($_POST['barangay']);
        $expected_participants = (int)$_POST['expected_participants'];
        $budget_allocated = (float)$_POST['budget_allocated'];
        $date_start = $_POST['date_start'];
        $time_start = $_POST['time_start'];
        
        $sql = "INSERT INTO programs (title, description, type_of_service, target_beneficiaries, 
                location, barangay, expected_participants, budget_allocated, date_start, time_start, 
                created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Planned')";
        
        $params = [$title, $description, $type_of_service, $target_beneficiaries, 
                  $location, $barangay, $expected_participants, $budget_allocated, 
                  $date_start, $time_start, $_SESSION['user_id']];
        
        if ($db->query($sql, $params)) {
            $new_program_id = $db->lastInsertId();
            logActivity($_SESSION['user_id'], 'Program Created from Recommendation', 'programs', $new_program_id);
            setSuccessMessage('Program created successfully from recommendation!');
            header('Location: ?page=programs&action=view&id=' . $new_program_id);
            exit();
        } else {
            setErrorMessage('Failed to create program from recommendation.');
        }
    }
}

// Get user's department for personalized recommendations
$user_department = $_SESSION['department'] ?? '';

// Get program statistics for generating recommendations
$program_stats = [
    'popular_types' => $db->fetchAll("
        SELECT type_of_service, COUNT(*) as count, AVG(actual_participants) as avg_participants
        FROM programs 
        WHERE status = 'Completed' 
        GROUP BY type_of_service 
        ORDER BY count DESC, avg_participants DESC
    "),
    'high_demand_barangays' => $db->fetchAll("
        SELECT barangay, COUNT(*) as program_count, SUM(actual_participants) as total_participants
        FROM programs 
        WHERE status = 'Completed' AND barangay IS NOT NULL 
        GROUP BY barangay 
        ORDER BY program_count DESC 
        LIMIT 10
    "),
    'recent_requests' => $db->fetchAll("
        SELECT program_type, barangay, COUNT(*) as request_count
        FROM program_requests 
        WHERE status = 'Pending' AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
        GROUP BY program_type, barangay 
        ORDER BY request_count DESC
        LIMIT 15
    "),
    'seasonal_programs' => $db->fetchAll("
        SELECT type_of_service, MONTH(date_start) as month, COUNT(*) as count
        FROM programs 
        WHERE status = 'Completed'
        GROUP BY type_of_service, MONTH(date_start)
        ORDER BY count DESC
    ")
];

// Generate AI-style recommendations based on user department and data analysis
function generateRecommendations($user_department, $program_stats) {
    $recommendations = [];
    
    // Department-specific recommendations
    $department_mapping = [
        'College of Engineering' => ['Technology', 'Infrastructure', 'Skills Training'],
        'College of Information Technology' => ['Technology', 'Digital Literacy', 'Skills Training'],
        'College of Education' => ['Education', 'Literacy', 'Youth Development'],
        'College of Science' => ['Health', 'Environment', 'Research'],
        'College of Arts and Sciences' => ['Social Services', 'Cultural', 'Community Development'],
        'College of Business Administration' => ['Entrepreneurship', 'Skills Training', 'Economic Development']
    ];
    
    $dept_services = $department_mapping[$user_department] ?? ['Community Development', 'Social Services'];
    
    // Recommendation 1: Based on department expertise
    if (!empty($dept_services)) {
        $primary_service = $dept_services[0];
        $recommendations[] = [
            'id' => 'dept_1',
            'title' => getDepartmentSpecificTitle($user_department, $primary_service),
            'type_of_service' => $primary_service,
            'description' => getDepartmentSpecificDescription($user_department, $primary_service),
            'target_beneficiaries' => getDepartmentSpecificBeneficiaries($user_department),
            'expected_participants' => rand(25, 60),
            'recommended_budget' => rand(15000, 45000),
            'confidence' => 95,
            'reason' => "Based on your department's expertise in {$primary_service}",
            'priority' => 'High',
            'suggested_locations' => getHighDemandLocations($program_stats['high_demand_barangays'])
        ];
    }
    
    // Recommendation 2: Based on popular program types
    if (!empty($program_stats['popular_types'])) {
        $popular_type = $program_stats['popular_types'][0];
        $recommendations[] = [
            'id' => 'popular_1',
            'title' => getPopularProgramTitle($popular_type['type_of_service']),
            'type_of_service' => $popular_type['type_of_service'],
            'description' => getPopularProgramDescription($popular_type['type_of_service']),
            'target_beneficiaries' => getPopularProgramBeneficiaries($popular_type['type_of_service']),
            'expected_participants' => (int)$popular_type['avg_participants'],
            'recommended_budget' => rand(20000, 50000),
            'confidence' => 88,
            'reason' => "This type of program has been very successful with an average of {$popular_type['avg_participants']} participants",
            'priority' => 'Medium',
            'suggested_locations' => getHighDemandLocations($program_stats['high_demand_barangays'])
        ];
    }
    
    // Recommendation 3: Based on recent requests
    if (!empty($program_stats['recent_requests'])) {
        $top_request = $program_stats['recent_requests'][0];
        $recommendations[] = [
            'id' => 'request_1',
            'title' => getRequestBasedTitle($top_request['program_type']),
            'type_of_service' => $top_request['program_type'],
            'description' => getRequestBasedDescription($top_request['program_type'], $top_request['barangay']),
            'target_beneficiaries' => getRequestBasedBeneficiaries($top_request['program_type']),
            'expected_participants' => rand(30, 50),
            'recommended_budget' => rand(18000, 40000),
            'confidence' => 92,
            'reason' => "High demand in {$top_request['barangay']} with {$top_request['request_count']} recent requests",
            'priority' => 'High',
            'suggested_locations' => [$top_request['barangay']]
        ];
    }
    
    // Recommendation 4: Seasonal/timely recommendation
    $current_month = (int)date('n');
    $seasonal_rec = getSeasonalRecommendation($current_month);
    if ($seasonal_rec) {
        $recommendations[] = $seasonal_rec;
    }
    
    // Recommendation 5: Innovation/new approach
    $recommendations[] = [
        'id' => 'innovation_1',
        'title' => 'Digital Community Hub Setup',
        'type_of_service' => 'Technology',
        'description' => 'Establish a digital learning center with computers and internet access for community members to develop digital literacy skills and access online resources.',
        'target_beneficiaries' => 'Students, Adults, Senior Citizens',
        'expected_participants' => 40,
        'recommended_budget' => 60000,
        'confidence' => 78,
        'reason' => 'Emerging need for digital literacy in the post-pandemic era',
        'priority' => 'Medium',
        'suggested_locations' => ['Sampaloc', 'Tondo', 'Santa Mesa']
    ];
    
    return $recommendations;
}

// Helper functions for generating recommendation content
function getDepartmentSpecificTitle($department, $service) {
    $titles = [
        'College of Engineering' => [
            'Technology' => 'Community Infrastructure Assessment and Planning',
            'Skills Training' => 'Basic Engineering Skills Workshop for Youth'
        ],
        'College of Information Technology' => [
            'Technology' => 'Digital Literacy and Basic Computer Skills Training',
            'Skills Training' => 'Web Development Basics for Entrepreneurs'
        ],
        'College of Education' => [
            'Education' => 'Adult Literacy and Numeracy Program',
            'Youth Development' => 'Educational Support for Out-of-School Youth'
        ]
    ];
    
    return $titles[$department][$service] ?? "Community {$service} Program";
}

function getDepartmentSpecificDescription($department, $service) {
    $descriptions = [
        'Technology' => 'A comprehensive program designed to improve technological literacy and digital skills within the community, leveraging our technical expertise.',
        'Education' => 'An educational initiative aimed at enhancing learning opportunities and academic support for community members.',
        'Skills Training' => 'Practical skills development program that empowers participants with valuable competencies for personal and professional growth.'
    ];
    
    return $descriptions[$service] ?? 'A specialized program tailored to community needs using departmental expertise.';
}

function getDepartmentSpecificBeneficiaries($department) {
    $beneficiaries = [
        'College of Engineering' => 'Community leaders, Local workers, Technical enthusiasts',
        'College of Information Technology' => 'Students, Job seekers, Small business owners',
        'College of Education' => 'Teachers, Parents, Students, Out-of-school youth',
        'College of Science' => 'Health workers, Community members, Students'
    ];
    
    return $beneficiaries[$department] ?? 'Community members, Local residents';
}

function getHighDemandLocations($barangays) {
    return array_slice(array_column($barangays, 'barangay'), 0, 3);
}

function getPopularProgramTitle($type) {
    $titles = [
        'Health' => 'Community Health and Wellness Program',
        'Education' => 'Educational Enhancement Initiative',
        'Technology' => 'Digital Innovation Workshop',
        'Agriculture' => 'Sustainable Farming Techniques Training',
        'Skills Training' => 'Livelihood and Skills Development Program'
    ];
    
    return $titles[$type] ?? "Advanced {$type} Program";
}

function getPopularProgramDescription($type) {
    $descriptions = [
        'Health' => 'Comprehensive health education and wellness program focusing on preventive care, nutrition awareness, and basic health practices.',
        'Education' => 'Educational support program designed to enhance learning outcomes and provide academic assistance to community members.',
        'Technology' => 'Technology-focused initiative to improve digital literacy and introduce modern technological solutions to the community.',
        'Agriculture' => 'Agricultural development program promoting sustainable farming practices and modern agricultural techniques.',
        'Skills Training' => 'Practical skills development initiative aimed at enhancing employability and entrepreneurial capabilities.'
    ];
    
    return $descriptions[$type] ?? "Specialized {$type} program designed to meet community needs.";
}

function getPopularProgramBeneficiaries($type) {
    $beneficiaries = [
        'Health' => 'Community members, Families, Health workers',
        'Education' => 'Students, Teachers, Parents',
        'Technology' => 'Youth, Professionals, Entrepreneurs',
        'Agriculture' => 'Farmers, Agricultural workers, Rural communities',
        'Skills Training' => 'Job seekers, Unemployed individuals, Entrepreneurs'
    ];
    
    return $beneficiaries[$type] ?? 'Community members';
}

function getRequestBasedTitle($type) {
    return "Community-Requested {$type} Program";
}

function getRequestBasedDescription($type, $barangay) {
    return "A {$type} program specifically designed to address the expressed needs and requests from {$barangay} community members.";
}

function getRequestBasedBeneficiaries($type) {
    return getPopularProgramBeneficiaries($type);
}

function getSeasonalRecommendation($month) {
    $seasonal = [
        1 => ['title' => 'New Year Health Resolution Program', 'type' => 'Health', 'reason' => 'Perfect timing for health and wellness initiatives'],
        2 => ['title' => 'Love Your Community Valentine Program', 'type' => 'Social Services', 'reason' => 'Community bonding activities are popular in February'],
        3 => ['title' => 'Spring Cleaning Environmental Program', 'type' => 'Environment', 'reason' => 'Spring season ideal for environmental activities'],
        6 => ['title' => 'Summer Skills Development Camp', 'type' => 'Skills Training', 'reason' => 'School break provides opportunities for intensive training'],
        12 => ['title' => 'Holiday Community Outreach Program', 'type' => 'Social Services', 'reason' => 'Holiday season promotes community giving and sharing']
    ];
    
    if (isset($seasonal[$month])) {
        $rec = $seasonal[$month];
        return [
            'id' => 'seasonal_1',
            'title' => $rec['title'],
            'type_of_service' => $rec['type'],
            'description' => "A timely program designed to take advantage of seasonal opportunities and community readiness.",
            'target_beneficiaries' => 'Community members, Families',
            'expected_participants' => rand(35, 55),
            'recommended_budget' => rand(25000, 45000),
            'confidence' => 85,
            'reason' => $rec['reason'],
            'priority' => 'Medium',
            'suggested_locations' => ['Various barangays']
        ];
    }
    
    return null;
}

// Generate recommendations
$recommendations = generateRecommendations($user_department, $program_stats);

// Get user's recent program creation history
$user_programs = $db->fetchAll("
    SELECT * FROM programs 
    WHERE created_by = ? 
    ORDER BY created_at DESC 
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

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fas fa-lightbulb text-warning"></i> Program Recommendations</h2>
                <p class="text-muted mb-0">AI-powered suggestions based on your department expertise and community needs</p>
            </div>
            <div class="text-end">
                <small class="text-muted">
                    Department: <strong><?= htmlspecialchars($user_department ?: 'Not specified') ?></strong>
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Recommendation Overview -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card text-center">
            <div class="stat-icon blue mx-auto mb-2">
                <i class="fas fa-brain"></i>
            </div>
            <div class="stat-value"><?= count($recommendations) ?></div>
            <div class="stat-label">Smart Recommendations</div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card text-center">
            <div class="stat-icon green mx-auto mb-2">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-value"><?= count(array_filter($recommendations, fn($r) => $r['priority'] === 'High')) ?></div>
            <div class="stat-label">High Priority</div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card text-center">
            <div class="stat-icon orange mx-auto mb-2">
                <i class="fas fa-bullseye"></i>
            </div>
            <div class="stat-value"><?= number_format(array_sum(array_column($recommendations, 'confidence')) / count($recommendations), 0) ?>%</div>
            <div class="stat-label">Avg Confidence</div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card text-center">
            <div class="stat-icon red mx-auto mb-2">
                <i class="fas fa-calendar-plus"></i>
            </div>
            <div class="stat-value"><?= count($user_programs) ?></div>
            <div class="stat-label">Your Programs</div>
        </div>
    </div>
</div>

<!-- Recommendations Grid -->
<div class="row">
    <?php foreach ($recommendations as $index => $recommendation): ?>
        <div class="col-lg-6 mb-4">
            <div class="card h-100 shadow-sm recommendation-card">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-<?= $recommendation['priority'] === 'High' ? 'danger' : 'warning' ?> me-2">
                                <?= $recommendation['priority'] ?> Priority
                            </span>
                            <span class="badge bg-info">
                                <?= $recommendation['confidence'] ?>% Match
                            </span>
                        </div>
                        <small class="text-muted">Recommendation #<?= $index + 1 ?></small>
                    </div>
                </div>
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($recommendation['title']) ?></h5>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted">Type:</small><br>
                            <span class="badge bg-primary"><?= htmlspecialchars($recommendation['type_of_service']) ?></span>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Expected Participants:</small><br>
                            <strong><?= number_format($recommendation['expected_participants']) ?></strong>
                        </div>
                    </div>
                    
                    <p class="card-text"><?= htmlspecialchars($recommendation['description']) ?></p>
                    
                    <div class="mb-3">
                        <small class="text-muted">Target Beneficiaries:</small><br>
                        <?= htmlspecialchars($recommendation['target_beneficiaries']) ?>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted">Recommended Budget:</small><br>
                        <strong class="text-success"><?= formatCurrency($recommendation['recommended_budget']) ?></strong>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted">Suggested Locations:</small><br>
                        <?php foreach ($recommendation['suggested_locations'] as $location): ?>
                            <span class="badge bg-light text-dark me-1"><?= htmlspecialchars($location) ?></span>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="alert alert-light">
                        <i class="fas fa-info-circle text-info"></i>
                        <strong>Why this recommendation?</strong><br>
                        <?= htmlspecialchars($recommendation['reason']) ?>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary flex-fill" onclick="createFromRecommendation('<?= $recommendation['id'] ?>')">
                            <i class="fas fa-plus"></i> Create Program
                        </button>
                        <button class="btn btn-outline-secondary" onclick="viewRecommendationDetails('<?= $recommendation['id'] ?>')">
                            <i class="fas fa-eye"></i> Details
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Recent Programs Section -->
<?php if (!empty($user_programs)): ?>
    <div class="mt-5">
        <h4><i class="fas fa-history text-secondary"></i> Your Recent Programs</h4>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Program Title</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Participants</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($user_programs as $program): ?>
                        <tr>
                            <td><?= htmlspecialchars($program['title']) ?></td>
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
                                <a href="?page=programs&action=view&id=<?= $program['program_id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Create Program Modal -->
<div class="modal fade" id="createProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus text-success"></i> Create Program from Recommendation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb"></i>
                        <strong>Based on Recommendation:</strong> <span id="recommendationSource"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Program Title *</label>
                        <input type="text" class="form-control" name="title" id="recTitle" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Program Type *</label>
                                <select class="form-select" name="type_of_service" id="recType" required>
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
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Target Beneficiaries *</label>
                                <input type="text" class="form-control" name="target_beneficiaries" id="recBeneficiaries" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description *</label>
                        <textarea class="form-control" name="description" id="recDescription" rows="4" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Location *</label>
                                <input type="text" class="form-control" name="location" id="recLocation" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Barangay</label>
                                <input type="text" class="form-control" name="barangay" id="recBarangay">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Expected Participants *</label>
                                <input type="number" class="form-control" name="expected_participants" id="recParticipants" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Start Date *</label>
                                <input type="date" class="form-control" name="date_start" min="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Start Time *</label>
                                <input type="time" class="form-control" name="time_start" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Budget Allocated *</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" class="form-control" name="budget_allocated" id="recBudget" step="0.01" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_from_recommendation" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Program
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.recommendation-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.recommendation-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.recommendation-card .card-header {
    border-bottom: 2px solid #e9ecef;
}
</style>

<script>
// Store recommendations data for JavaScript access
const recommendations = <?= json_encode($recommendations) ?>;

function createFromRecommendation(recommendationId) {
    const recommendation = recommendations.find(r => r.id === recommendationId);
    
    if (recommendation) {
        // Populate modal with recommendation data
        document.getElementById('recommendationSource').textContent = recommendation.title;
        document.getElementById('recTitle').value = recommendation.title;
        document.getElementById('recType').value = recommendation.type_of_service;
        document.getElementById('recBeneficiaries').value = recommendation.target_beneficiaries;
        document.getElementById('recDescription').value = recommendation.description;
        document.getElementById('recParticipants').value = recommendation.expected_participants;
        document.getElementById('recBudget').value = recommendation.recommended_budget;
        
        // Set suggested location if available
        if (recommendation.suggested_locations && recommendation.suggested_locations.length > 0) {
            document.getElementById('recLocation').value = recommendation.suggested_locations[0];
            document.getElementById('recBarangay').value = recommendation.suggested_locations[0];
        }
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('createProgramModal'));
        modal.show();
    }
}

function viewRecommendationDetails(recommendationId) {
    const recommendation = recommendations.find(r => r.id === recommendationId);
    
    if (recommendation) {
        let details = `
            <strong>Program Title:</strong> ${recommendation.title}<br>
            <strong>Type:</strong> ${recommendation.type_of_service}<br>
            <strong>Expected Participants:</strong> ${recommendation.expected_participants}<br>
            <strong>Recommended Budget:</strong> ₱${recommendation.recommended_budget.toLocaleString()}<br>
            <strong>Confidence Level:</strong> ${recommendation.confidence}%<br>
            <strong>Priority:</strong> ${recommendation.priority}<br><br>
            <strong>Description:</strong><br>${recommendation.description}<br><br>
            <strong>Target Beneficiaries:</strong><br>${recommendation.target_beneficiaries}<br><br>
            <strong>Reason for Recommendation:</strong><br>${recommendation.reason}<br><br>
            <strong>Suggested Locations:</strong><br>${recommendation.suggested_locations.join(', ')}
        `;
        
        // Create a simple alert with details (you could create a proper modal for this)
        const detailModal = document.createElement('div');
        detailModal.className = 'modal fade';
        detailModal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Recommendation Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${details}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="createFromRecommendation('${recommendationId}')" data-bs-dismiss="modal">Create Program</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(detailModal);
        const modal = new bootstrap.Modal(detailModal);
        modal.show();
        
        // Remove modal from DOM when hidden
        detailModal.addEventListener('hidden.bs.modal', function() {
            document.body.removeChild(detailModal);
        });
    }
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