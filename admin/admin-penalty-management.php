<?php
// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Simple authentication check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Include penalty system
require_once 'penalty-system.php';

// Database connection
$host = "localhost";
$user = "root";       
$password = "";   // no password for XAMPP
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize penalty system
$penaltySystem = new PenaltySystem($conn);

// Get active guidelines for dropdown
$activeGuidelines = $penaltySystem->getActiveGuidelines();

// Build penalty type list (defaults + distinct from guidelines and existing records)
$defaultPenaltyTypes = ['Late Return', 'Damage', 'Loss'];
$penaltyTypeMap = array_fill_keys($defaultPenaltyTypes, true);

foreach ($activeGuidelines as $guideline) {
    if (!empty($guideline['penalty_type'])) {
        $penaltyTypeMap[$guideline['penalty_type']] = true;
    }
}

$typeQuery = $conn->query("SELECT DISTINCT penalty_type FROM penalties WHERE penalty_type IS NOT NULL AND penalty_type <> '' ORDER BY penalty_type ASC");
if ($typeQuery) {
    while ($typeRow = $typeQuery->fetch_assoc()) {
        $penaltyTypeMap[$typeRow['penalty_type']] = true;
    }
    $typeQuery->free();
}

$penaltyTypes = array_keys($penaltyTypeMap);
natcasesort($penaltyTypes);
$penaltyTypes = array_values($penaltyTypes);

$resolutionTypes = ['Paid', 'Repaired', 'Replaced', 'Waived', 'Other'];

// Handle success/error messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Check if coming from damage penalty workflow or return verification
$damage_penalty_mode = false;
$damage_penalty_data = [];
if (isset($_GET['action']) && ($_GET['action'] === 'create_damage_penalty' || $_GET['action'] === 'create_from_transaction')) {
    $damage_penalty_mode = true;
    $damage_penalty_data = [
        'transaction_id' => (int)($_GET['transaction_id'] ?? 0),
        'equipment_name' => $_GET['equipment_name'] ?? '',
        'detected_issues' => $_GET['detected_issues'] ?? '',
        'similarity_score' => isset($_GET['similarity_score']) ? (float)$_GET['similarity_score'] : null,
        'severity_level' => $_GET['severity_level'] ?? 'medium',
        'student_id' => '',
        'user_rfid' => '',
        'borrower_reference' => '',
        'from_return_verification' => ($_GET['action'] === 'create_from_transaction')
    ];
    
    // Fetch transaction details
    if ($damage_penalty_data['transaction_id'] > 0) {
        $txn_query = $conn->prepare("SELECT t.*, u.student_id, u.rfid_tag AS user_rfid, e.name AS equipment_full_name, e.rfid_tag,
            j.result as ai_result
            FROM transactions t 
            LEFT JOIN users u ON t.user_id = u.id 
            LEFT JOIN equipment e ON t.equipment_id = e.id 
            LEFT JOIN ai_comparison_jobs j ON t.id = j.transaction_id AND j.status = 'completed'
            WHERE t.id = ?");

        if ($txn_query) {
            $txn_query->bind_param('i', $damage_penalty_data['transaction_id']);
            $txn_query->execute();
            $txn_result = $txn_query->get_result();
            if ($txn_result && $txn_result->num_rows > 0) {
                $txn_data = $txn_result->fetch_assoc();
                $damage_penalty_data['user_id'] = $txn_data['user_id'];
                $damage_penalty_data['student_id'] = $txn_data['student_id'] ?? '';
                $damage_penalty_data['user_rfid'] = $txn_data['user_rfid'] ?? '';
                $damage_penalty_data['equipment_id'] = $txn_data['equipment_id'];
                $damage_penalty_data['equipment_full_name'] = $txn_data['equipment_full_name'] ?? $damage_penalty_data['equipment_name'];
                $damage_penalty_data['return_date'] = $txn_data['actual_return_date'] ?? date('Y-m-d');

                // Extract AI comparison data if coming from return verification
                if ($damage_penalty_data['from_return_verification'] && !empty($txn_data['ai_result'])) {
                    $ai_data = json_decode($txn_data['ai_result'], true);
                    if ($ai_data) {
                        $damage_penalty_data['similarity_score'] = $ai_data['ai_similarity_score'] ?? $ai_data['final_blended_score'] ?? null;
                        $damage_penalty_data['severity_level'] = $ai_data['ai_severity_level'] ?? 'medium';
                        $damage_penalty_data['detected_issues'] = $ai_data['ai_detected_issues'] ?? '';
                    }
                }

                $borrower_reference = $damage_penalty_data['student_id'];
                if ($borrower_reference === '' && !empty($damage_penalty_data['user_rfid'])) {
                    $borrower_reference = $damage_penalty_data['user_rfid'];
                }
                if ($borrower_reference === '' && !empty($txn_data['rfid_id'])) {
                    $borrower_reference = $txn_data['rfid_id'];
                }
                $damage_penalty_data['borrower_reference'] = $borrower_reference;
            }
            $txn_query->close();
        } else {
            error_log('Failed to prepare transaction lookup: ' . $conn->error);
        }
    }

    $issuesSummary = trim((string)($damage_penalty_data['detected_issues'] ?? ''));
    $similarityValue = $damage_penalty_data['similarity_score'];
    $summaryParts = [];
    if ($similarityValue !== null) {
        $summaryParts[] = 'Similarity ' . number_format((float)$similarityValue, 2) . '%';
    }
    if ($issuesSummary !== '') {
        $summaryParts[] = 'Issues: ' . preg_replace('/\s+/', ' ', strip_tags($issuesSummary));
    }
    $damage_penalty_data['comparison_summary'] = implode(' | ', $summaryParts);

    // If no valid transaction id, exit damage penalty mode and advise admin
    if ($damage_penalty_data['transaction_id'] <= 0) {
        $damage_penalty_mode = false;
        $error_message = trim(($error_message ?? '') . ' ' . 'Damage penalty parameters were incomplete. Please reopen the transaction and click "Add to Penalty" again.');
        $damage_penalty_data = [];
    }

    if (empty($damage_penalty_data['borrower_reference'])) {
        $warning = 'Borrower identifier was not detected for this transaction. Please confirm the RFID or student ID before saving the penalty.';
        $error_message = trim(($error_message ?? '') . ' ' . $warning);
    }

    // Auto-suggest penalty guideline based on severity and similarity score
    $suggested_guideline_id = null;
    $suggested_guideline_reason = '';
    $scoreValue = $damage_penalty_data['similarity_score'];
    $severityLevel = $damage_penalty_data['severity_level'];
    
    if ($scoreValue !== null && !empty($activeGuidelines)) {
        // Determine severity category
        $severityCategory = '';
        if ($scoreValue < 50) {
            $severityCategory = 'severe'; // Severe damage
        } elseif ($scoreValue < 70) {
            $severityCategory = 'moderate'; // Moderate damage
        } elseif ($scoreValue < 85) {
            $severityCategory = 'minor'; // Minor damage
        }
        
        // Find matching guideline
        foreach ($activeGuidelines as $guideline) {
            // Safely get guideline name and type
            $guidelineName = isset($guideline['guideline_name']) ? strtolower($guideline['guideline_name']) : '';
            $guidelineType = isset($guideline['penalty_type']) ? strtolower($guideline['penalty_type']) : '';
            
            // Skip if guideline name is empty
            if (empty($guidelineName)) {
                continue;
            }
            
            // Match based on severity keywords
            if ($severityCategory === 'severe' && 
                (strpos($guidelineName, 'severe') !== false || 
                 strpos($guidelineName, 'replacement') !== false ||
                 strpos($guidelineName, 'total') !== false)) {
                $suggested_guideline_id = $guideline['id'];
                $suggested_guideline_reason = 'Severe damage detected (score < 50%)';
                break;
            } elseif ($severityCategory === 'moderate' && 
                      (strpos($guidelineName, 'moderate') !== false || 
                       strpos($guidelineName, 'repair') !== false)) {
                $suggested_guideline_id = $guideline['id'];
                $suggested_guideline_reason = 'Moderate damage detected (score 50-70%)';
                break;
            } elseif ($severityCategory === 'minor' && 
                      (strpos($guidelineName, 'minor') !== false || 
                       strpos($guidelineName, 'light') !== false)) {
                $suggested_guideline_id = $guideline['id'];
                $suggested_guideline_reason = 'Minor damage detected (score 70-85%)';
                break;
            }
        }
        
        // Fallback: suggest first damage-related guideline
        if (!$suggested_guideline_id) {
            foreach ($activeGuidelines as $guideline) {
                $guidelineType = isset($guideline['penalty_type']) ? strtolower($guideline['penalty_type']) : '';
                if ($guidelineType === 'damage' || strpos($guidelineType, 'damage') !== false) {
                    $suggested_guideline_id = $guideline['id'];
                    $suggested_guideline_reason = 'General damage guideline';
                    break;
                }
            }
        }
    }
    
    $damage_penalty_data['suggested_guideline_id'] = $suggested_guideline_id;
    $damage_penalty_data['suggested_guideline_reason'] = $suggested_guideline_reason;
}

// Handle penalty operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create_penalty') {
        // First, get guideline details if guideline_id is provided
        $guideline_id = (int)($_POST['guideline_id'] ?? 0);
        $penalty_type = '';
        $penalty_amount = 0;
        $penalty_description = '';
        
        $guideline_is_active = true;
        if ($guideline_id > 0) {
            $guideline = $penaltySystem->getGuidelineById($guideline_id);
            if ($guideline) {
                $penalty_type = $guideline['penalty_type'] ?? '';
                $penalty_amount = (float)($guideline['penalty_amount'] ?? 0);
                $penalty_description = $guideline['penalty_description'] ?? '';
            } else {
                $guideline_is_active = false;
            }
        }
        
        $payload = [
            'transaction_id' => (int)($_POST['transaction_id'] ?? 0),
            'user_id' => (int)($_POST['user_id'] ?? 0) ?: null,
            'borrower_identifier' => trim($_POST['rfid_id'] ?? ''),
            'penalty_type' => $penalty_type,
            'guideline_id' => $guideline_id ?: null,
            'equipment_id' => trim($_POST['equipment_id'] ?? ''),
            'equipment_name' => trim($_POST['equipment_name'] ?? ''),
            'damage_severity' => $_POST['damage_severity'] ?? null,
            'damage_notes' => trim($_POST['damage_notes'] ?? ''),
            'detected_issues' => trim($_POST['detected_issues'] ?? ''),
            'similarity_score' => isset($_POST['similarity_score']) ? (float)$_POST['similarity_score'] : null,
            'comparison_summary' => trim($_POST['comparison_summary'] ?? ''),
            'admin_assessment' => trim($_POST['admin_assessment'] ?? ''),
            'description' => $penalty_description,
            'days_overdue' => (int)($_POST['days_overdue'] ?? 0),
            'daily_rate' => isset($_POST['daily_rate']) ? (float)$_POST['daily_rate'] : null,
            'penalty_amount' => $penalty_amount,
            'amount_owed' => $penalty_amount,
            'amount_note' => $penalty_description,
            'notes' => trim($_POST['notes'] ?? '')
        ];

        if (!$guideline_is_active) {
            $error_message = "Selected guideline is not active. Please pick an active penalty guideline.";
        } elseif ($payload['transaction_id'] <= 0 || $payload['penalty_type'] === '' || $payload['equipment_name'] === '') {
            $error_message = "Please complete all required fields for creating a penalty.";
        } else {

            if ($payload['penalty_type'] === 'Late Return' && $payload['days_overdue'] > 0) {
                $dailyRate = $payload['daily_rate'] ?: PenaltySystem::DEFAULT_DAILY_RATE;
                $amount = $dailyRate * $payload['days_overdue'];
                $payload['penalty_amount'] = $amount;
                $payload['amount_owed'] = $amount;
                $payload['amount_note'] = sprintf('%d day(s) × ₱%0.2f per day', $payload['days_overdue'], $dailyRate);
            }

            if ($penaltySystem->createPenalty($payload)) {
                $_SESSION['success_message'] = "Penalty record created successfully.";
                header('Location: admin-penalty-management.php');
                exit;
            } else {
                $error_message = "Failed to create penalty. Please verify the details.";
            }
        }
    }

    if ($action === 'update_status' && isset($_POST['penalty_id'])) {
        $penaltyId = (int)$_POST['penalty_id'];
        $statusPayload = [
            'status' => $_POST['status'] ?? 'Pending',
            'notes' => $_POST['notes'] ?? '',
            'resolution_type' => $_POST['resolution_type'] ?? null,
            'resolution_notes' => $_POST['resolution_notes'] ?? ''
        ];

        if ($penaltySystem->updatePenaltyStatus($penaltyId, $statusPayload)) {
            $success_message = "Penalty status updated successfully.";
        } else {
            $error_message = "Failed to update penalty status.";
        }
    }

    if ($action === 'auto_calculate') {
        $count = $penaltySystem->autoCalculateOverduePenalties();
        $success_message = "Auto-calculation completed. Created $count late return penalties.";
    }
}

// Handle filtering (status and type only for simplified view)
$status_filter = $_GET['status'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';

$filters = [
    'status' => $status_filter,
    'type' => $type_filter,
];

// Get penalties and statistics
$search_query = $_GET['search'] ?? '';
$filters['search'] = $search_query;
$penalties_result = $penaltySystem->getPenalties($filters);
$stats = $penaltySystem->getPenaltyStatistics();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin-styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main -->
        <main class="main-content">
            <header class="top-header">
                <h1 class="page-title">Penalty Management</h1>
                <div class="header-actions">
                </div>
            </header>

            <!-- Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Penalty Statistics -->
            <section class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-chart-bar"></i> Penalty Snapshot</h2>
                </div>

                <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;">
                    <div class="stat-card warn" style="background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%); border-radius: 16px; padding: 24px; box-shadow: 0 4px 16px rgba(253,203,110,0.3); transition: all 0.3s ease;">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number"><?= number_format($stats['by_status']['Pending']['count'] ?? 0) ?></h3>
                            <p class="stat-label">Pending Decisions</p>
                        </div>
                    </div>

                    <div class="stat-card amount" style="background: linear-gradient(135deg, #a29bfe 0%, #6c5ce7 100%); border-radius: 16px; padding: 24px; box-shadow: 0 4px 16px rgba(108,92,231,0.3); transition: all 0.3s ease;">
                        <div class="stat-icon">
                            <i class="fas fa-peso-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number">₱<?= number_format($stats['total_amount_owed'], 2) ?></h3>
                            <p class="stat-label">Total Amount Tracked</p>
                        </div>
                    </div>

                    <div class="stat-card damage" style="background: linear-gradient(135deg, #fab1a0 0%, #ff7675 100%); border-radius: 16px; padding: 24px; box-shadow: 0 4px 16px rgba(255,118,117,0.3); transition: all 0.3s ease;">
                        <div class="stat-icon">
                            <i class="fas fa-hammer"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number"><?= number_format($stats['damage_cases']) ?></h3>
                            <p class="stat-label">Damage Cases</p>
                        </div>
                    </div>

                    <div class="stat-card resolved" style="background: linear-gradient(135deg, #55efc4 0%, #00b894 100%); border-radius: 16px; padding: 24px; box-shadow: 0 4px 16px rgba(0,184,148,0.3); transition: all 0.3s ease;">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number"><?= number_format($stats['by_status']['Resolved']['count'] ?? 0) ?></h3>
                            <p class="stat-label">Resolved Penalties</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Damage Penalty Creation Form (from Transaction Review) -->
            <?php if ($damage_penalty_mode && !empty($damage_penalty_data['transaction_id'])): ?>
            <section class="content-section damage-penalty-section">
                <div class="section-header">
                    <h2><i class="fas fa-exclamation-triangle"></i> Create Damage Penalty</h2>
                    <p style="color: #666; font-size: 14px; margin-top: 8px;">
                        Equipment returned with detected damage. Review the details below and set the appropriate penalty.
                    </p>
                </div>

                <div class="damage-penalty-card">
                    <div class="damage-info-grid">
                        <div class="damage-info-item">
                            <label>Transaction ID:</label>
                            <strong>#<?= htmlspecialchars($damage_penalty_data['transaction_id']) ?></strong>
                        </div>
                        <div class="damage-info-item">
                            <label>Student:</label>
                            <strong><?= htmlspecialchars($damage_penalty_data['student_id'] ?? 'N/A') ?></strong>
                            <?php if (!empty($damage_penalty_data['user_name'])): ?>
                                <br><small><?= htmlspecialchars($damage_penalty_data['user_name']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="damage-info-item">
                            <label>Equipment:</label>
                            <strong><?= htmlspecialchars($damage_penalty_data['equipment_full_name'] ?? $damage_penalty_data['equipment_name']) ?></strong>
                        </div>
                        <div class="damage-info-item">
                            <label>Similarity Score:</label>
                            <strong class="<?= $damage_penalty_data['similarity_score'] < 50 ? 'text-danger' : ($damage_penalty_data['similarity_score'] < 70 ? 'text-warning' : 'text-success') ?>">
                                <?= number_format($damage_penalty_data['similarity_score'], 2) ?>%
                            </strong>
                        </div>
                    </div>

                    <?php if (!empty($damage_penalty_data['detected_issues'])): ?>
                    <div class="detected-issues-box">
                        <h4><i class="fas fa-clipboard-list"></i> Detected Issues:</h4>
                        <div class="issues-content">
                            <?= nl2br(htmlspecialchars($damage_penalty_data['detected_issues'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form method="POST" class="damage-penalty-form">
                        <input type="hidden" name="action" value="create_penalty">
                        <input type="hidden" name="transaction_id" value="<?= htmlspecialchars($damage_penalty_data['transaction_id']) ?>">
                        <input type="hidden" name="rfid_id" value="<?= htmlspecialchars($damage_penalty_data['borrower_reference'] ?? '') ?>">
                        <input type="hidden" name="equipment_id" value="<?= htmlspecialchars($damage_penalty_data['equipment_id'] ?? '') ?>">
                        <input type="hidden" name="equipment_name" value="<?= htmlspecialchars($damage_penalty_data['equipment_full_name'] ?? $damage_penalty_data['equipment_name']) ?>">
                        <input type="hidden" name="detected_issues" value="<?= htmlspecialchars($damage_penalty_data['detected_issues'] ?? '') ?>">
                        <input type="hidden" name="similarity_score" value="<?= $damage_penalty_data['similarity_score'] !== null ? htmlspecialchars((string)$damage_penalty_data['similarity_score']) : '' ?>">
                        <input type="hidden" name="comparison_summary" value="<?= htmlspecialchars($damage_penalty_data['comparison_summary'] ?? '') ?>">

                        <?php if (empty($damage_penalty_data['borrower_reference'])): ?>
                        <div class="alert alert-error" style="margin-top: 0;">
                            <i class="fas fa-user-circle"></i>
                            Borrower identifier is missing. Please confirm the Student ID or RFID before submitting this penalty.
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($damage_penalty_data['suggested_guideline_id'])): ?>
                        <div class="alert" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-left: 4px solid #2196f3; color: #1565c0; margin-top: 0;">
                            <i class="fas fa-lightbulb"></i>
                            <div>
                                <strong>Suggested Guideline:</strong> 
                                <?php 
                                    $suggestedGuidelineName = 'N/A';
                                    foreach ($activeGuidelines as $guideline) {
                                        if (isset($guideline['id']) && $guideline['id'] == $damage_penalty_data['suggested_guideline_id']) {
                                            $suggestedGuidelineName = isset($guideline['guideline_name']) ? $guideline['guideline_name'] : 'Unnamed Guideline';
                                            break;
                                        }
                                    }
                                    echo htmlspecialchars($suggestedGuidelineName);
                                ?>
                                <br>
                                <small><?= htmlspecialchars($damage_penalty_data['suggested_guideline_reason'] ?? 'Auto-suggested based on damage severity') ?></small>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="damage_severity">Damage Severity: <span class="required">*</span></label>
                                <select name="damage_severity" id="damage_severity" required>
                                    <option value="">-- Select Damage Severity --</option>
                                    <option value="minor" <?= $damage_penalty_data['severity_level'] === 'none' || ($damage_penalty_data['similarity_score'] !== null && $damage_penalty_data['similarity_score'] >= 70) ? 'selected' : '' ?>>
                                        Minor - Superficial scratches, scuffs, or cosmetic wear
                                    </option>
                                    <option value="moderate" <?= $damage_penalty_data['severity_level'] === 'medium' || ($damage_penalty_data['similarity_score'] !== null && $damage_penalty_data['similarity_score'] >= 50 && $damage_penalty_data['similarity_score'] < 70) ? 'selected' : '' ?>>
                                        Moderate - Visible damage affecting appearance or partial functionality
                                    </option>
                                    <option value="severe" <?= $damage_penalty_data['severity_level'] === 'high' || ($damage_penalty_data['similarity_score'] !== null && $damage_penalty_data['similarity_score'] < 50) ? 'selected' : '' ?>>
                                        Severe - Major damage with significant functionality loss or safety concerns
                                    </option>
                                    <option value="total_loss">
                                        Total Loss - Equipment is beyond repair or replacement required
                                    </option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="damage_notes">Admin Damage Assessment:</label>
                                <textarea name="damage_notes" id="damage_notes" rows="3" placeholder="Describe observed damage details and remarks..."></textarea>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="penalty_guideline">Select Penalty Guideline to Issue: <span class="required">*</span></label>
                            <select name="guideline_id" id="penalty_guideline" required>
                                <option value="">-- Select a Penalty Guideline --</option>
                                <?php foreach ($activeGuidelines as $guideline): ?>
                                    <option value="<?= htmlspecialchars($guideline['id']) ?>" 
                                            <?= (!empty($damage_penalty_data['suggested_guideline_id']) && $guideline['id'] == $damage_penalty_data['suggested_guideline_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($guideline['guideline_name'] ?? $guideline['title']) ?> 
                                        (<?= htmlspecialchars($guideline['penalty_type']) ?>) - 
                                        ₱<?= number_format($guideline['penalty_amount'], 2) ?>
                                        <?php if (!empty($guideline['penalty_points'])): ?>
                                            | <?= htmlspecialchars($guideline['penalty_points']) ?> points
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-hint">Select the appropriate penalty guideline based on the damage severity and assessment.</small>
                        </div>

                        <div class="form-group">
                            <label for="admin_notes">Additional Notes (Optional):</label>
                            <textarea name="notes" id="admin_notes" rows="3" placeholder="Add any additional remarks or special circumstances..."></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-gavel"></i> Issue Penalty
                            </button>
                        </div>
                    </form>
                </div>
            </section>
            <?php endif; ?>

            <!-- Penalty Filters -->
            <section class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-filter"></i> Filter Penalties</h2>
                </div>

                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Under Review" <?= $status_filter === 'Under Review' ? 'selected' : '' ?>>Under Review</option>
                            <option value="Resolved" <?= $status_filter === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                            <option value="Cancelled" <?= $status_filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="type">Penalty Type</label>
                        <select name="type" id="type">
                            <option value="all" <?= $type_filter === 'all' ? 'selected' : '' ?>>All Types</option>
                            <?php foreach ($penaltyTypes as $typeOption): ?>
                                <option value="<?= htmlspecialchars($typeOption) ?>" <?= $type_filter === $typeOption ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($typeOption) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group search">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Student ID, RFID, transaction, equipment">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Apply
                    </button>

                    <a href="admin-penalty-management.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </form>
            </section>

            <!-- Penalties List -->
            <section class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-list"></i> Penalties</h2>
                </div>

                <?php if ($penalties_result && $penalties_result->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="penalties-table simplified">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Student</th>
                                    <th>Equipment</th>
                                    <th>Type</th>
                                    <th>Severity</th>
                                    <th>Status</th>
                                    <th>Date Imposed</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($penalty = $penalties_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?= $penalty['id'] ?></td>
                                        <td><?= htmlspecialchars($penalty['user_id'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($penalty['equipment_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <span class="badge penalty-type <?= strtolower($penalty['penalty_type']) ?>">
                                                <?= htmlspecialchars($penalty['penalty_type']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $penalty['damage_severity'] ?? ''))) ?></td>
                                        <td>
                                            <span class="badge status <?= strtolower(str_replace(' ', '-', $penalty['status'])) ?>">
                                                <?= htmlspecialchars($penalty['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= !empty($penalty['date_imposed']) ? date('M d, Y', strtotime($penalty['date_imposed'])) : 'N/A' ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-small btn-primary" onclick="viewPenaltyDetail(<?= $penalty['id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($penalty['status'] === 'Pending' || $penalty['status'] === 'Under Review'): ?>
                                                    <button class="btn btn-small btn-success" onclick="updatePenaltyStatusModal(<?= $penalty['id'] ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-gavel fa-3x"></i>
                        <h3>No Penalties Found</h3>
                        <p>No penalties match your current filter criteria.</p>
                    </div>
                <?php endif; ?>
            </section>

        </main>
    </div>
    
    <!-- Penalty Detail Modal -->
    <div id="penaltyDetailModal" class="penalty-detail-modal">
        <div id="penaltyDetailContent" class="penalty-detail-content">
            <!-- Content will be dynamically loaded here -->
        </div>
    </div>


    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Update Penalty Status</h2>
            <form id="statusForm" method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="penalty_id" id="status_penalty_id">
                
                <div class="form-group">
                    <label for="status_select">Status</label>
                    <select name="status" id="status_select" required onchange="toggleResolutionFields()">
                        <option value="Pending">Pending</option>
                        <option value="Under Review">Under Review</option>
                        <option value="Resolved">Resolved</option>
                        <option value="Cancelled">Cancelled</option>
                        <option value="Appealed">Appealed</option>
                    </select>
                </div>

                <div id="resolution_fields" style="display: none;">
                    <div class="form-group">
                        <label for="resolution_type">Resolution Type</label>
                        <select name="resolution_type" id="resolution_type">
                            <option value="">Select resolution type</option>
                            <?php foreach ($resolutionTypes as $resType): ?>
                                <option value="<?= htmlspecialchars($resType) ?>"><?= htmlspecialchars($resType) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="resolution_notes">Resolution Notes</label>
                        <textarea name="resolution_notes" id="resolution_notes" rows="3" placeholder="Describe how the penalty was resolved..."></textarea>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Additional Notes</label>
                    <textarea name="notes" id="notes" rows="3" placeholder="Add optional context..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Status</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Penalty Modal -->
    <div id="createPenaltyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Create Penalty</h2>
            <form id="createPenaltyForm" method="POST">
                <input type="hidden" name="action" value="create_penalty">

                <div class="form-group">
                    <label for="rfid_id">Borrower RFID ID</label>
                    <input type="text" id="rfid_id" name="rfid_id" required placeholder="Enter RFID ID">
                    <button type="button" class="btn btn-small btn-secondary" onclick="searchByRFID()">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>

                <div class="form-group">
                    <label for="transaction_id">Transaction ID</label>
                    <input type="number" id="transaction_id" name="transaction_id" min="1" required placeholder="Enter Transaction ID">
                    <button type="button" class="btn btn-small btn-secondary" onclick="loadTransactionDetails()">
                        <i class="fas fa-download"></i> Load Details
                    </button>
                </div>

                <div class="form-group">
                    <label for="equipment_id">Equipment ID</label>
                    <input type="number" id="equipment_id" name="equipment_id" min="1" required placeholder="Auto-filled from transaction">
                </div>

                <div class="form-group">
                    <label for="equipment_name">Equipment Name</label>
                    <input type="text" id="equipment_name" name="equipment_name" required placeholder="Auto-filled from transaction">
                </div>

                <div class="form-group">
                    <label for="penalty_type">Penalty Type</label>
                    <select id="penalty_type" name="penalty_type" required onchange="updatePenaltyAmount()">
                        <option value="">Select penalty type</option>
                        <option value="Overdue">Overdue</option>
                        <option value="Damaged">Damaged</option>
                        <option value="Lost">Lost</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="penalty_amount">Amount (â‚±)</label>
                    <input type="number" id="penalty_amount" name="penalty_amount" step="0.01" min="0" value="0.00" required>
                    <small class="form-help">Amount will be auto-calculated for overdue penalties</small>
                </div>

                <div class="form-group" id="days_overdue_group">
                    <label for="days_overdue">Days Overdue (for Overdue type)</label>
                    <input type="number" id="days_overdue" name="days_overdue" min="0" value="0" onchange="calculateOverduePenalty()">
                </div>

                <div class="form-group">
                    <label for="violation_date">Violation Date</label>
                    <input type="date" id="violation_date" name="violation_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Additional notes about the penalty..."></textarea>
                </div>

                <div class="penalty-preview" id="penalty_preview" style="display: none;">
                    <h4>Penalty Preview</h4>
                    <div id="preview_content"></div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Create & Apply Penalty
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Penalty Modal -->
    <div id="quickPenaltyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Quick Penalty Creation</h2>
            <p>Select from active borrowed transactions that may need penalties:</p>
            
            <div id="quick_penalty_list">
                <div class="loading">Loading active transactions...</div>
            </div>
        </div>
    </div>

    <style>
        /* Override shared dashboard styles: show sections by default on this page */
        .content-section {
            display: block;
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert {
            padding: 16px 20px;
            margin: 20px 0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: end;
            margin-bottom: 25px;
            padding: 24px;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid #e9ecef;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
        }

        .filter-group.search {
            flex: 2;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group select,
        .filter-group input[type="text"] {
            padding: 10px 14px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .filter-group select:focus,
        .filter-group input[type="text"]:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .penalties-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        
        .penalties-table th,
        .penalties-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .penalties-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .penalties-table th:first-child {
            border-top-left-radius: 12px;
        }

        .penalties-table th:last-child {
            border-top-right-radius: 12px;
        }
        
        .penalties-table tbody tr {
            transition: all 0.2s ease;
        }

        .penalties-table tbody tr:hover {
            background: #f8f9ff;
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(102,126,234,0.1);
        }

        .penalties-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .student-info {
            line-height: 1.4;
        }
        
        .student-info small {
            color: #666;
        }
        
        .rfid-id {
            font-family: monospace;
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
        }
        
        .badge.penalty-type {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .badge.penalty-type.overdue,
        .badge.penalty-type.late-return {
            background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
            color: #d63031;
            box-shadow: 0 2px 6px rgba(253,203,110,0.3);
        }
        
        .badge.penalty-type.damaged,
        .badge.penalty-type.damage {
            background: linear-gradient(135deg, #fab1a0 0%, #ff7675 100%);
            color: #2d3436;
            box-shadow: 0 2px 6px rgba(255,118,117,0.3);
        }
        
        .badge.penalty-type.lost,
        .badge.penalty-type.loss {
            background: linear-gradient(135deg, #a29bfe 0%, #6c5ce7 100%);
            color: white;
            box-shadow: 0 2px 6px rgba(108,92,231,0.3);
        }
        
        .badge.status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .badge.status.pending {
            background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
            color: #d63031;
            box-shadow: 0 2px 6px rgba(253,203,110,0.3);
        }
        
        .badge.status.resolved,
        .badge.status.paid {
            background: linear-gradient(135deg, #55efc4 0%, #00b894 100%);
            color: #2d3436;
            box-shadow: 0 2px 6px rgba(0,184,148,0.3);
        }
        
        .badge.status.cancelled,
        .badge.status.waived {
            background: linear-gradient(135deg, #dfe6e9 0%, #b2bec3 100%);
            color: #2d3436;
            box-shadow: 0 2px 6px rgba(178,190,195,0.3);
        }
        
        .badge.status.under-review {
            background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
            color: white;
            box-shadow: 0 2px 6px rgba(9,132,227,0.3);
        }

        .badge.status.appealed {
            background: linear-gradient(135deg, #a29bfe 0%, #6c5ce7 100%);
            color: white;
            box-shadow: 0 2px 6px rgba(108,92,231,0.3);
        }
        
        .amount {
            font-weight: 600;
            color: #dc3545;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-small {
            padding: 6px 10px;
            font-size: 0.8rem;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.2s ease;
        }
        
        .modal-content {
            background: white;
            margin: 3% auto;
            padding: 32px;
            border-radius: 16px;
            width: 85%;
            max-width: 650px;
            position: relative;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-content h2 {
            margin: 0 0 24px 0;
            color: #2d3436;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            color: #b2bec3;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .close:hover {
            color: #2d3436;
            background: #f0f0f0;
            transform: rotate(90deg);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }
        
        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .form-help {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
            display: block;
        }

        .form-hint {
            display: block;
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 6px;
            font-style: italic;
        }
        
        .penalty-preview {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #dee2e6;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .penalty-preview h4 {
            margin: 0 0 12px 0;
            color: #495057;
            font-weight: 700;
        }
        
        .loading {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            font-size: 1rem;
        }

        .loading::after {
            content: '...';
            animation: dots 1.5s steps(4, end) infinite;
        }

        @keyframes dots {
            0%, 20% { content: '.'; }
            40% { content: '..'; }
            60%, 100% { content: '...'; }
        }
        
        .quick-penalty-item {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            margin: 12px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .quick-penalty-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 16px rgba(102,126,234,0.15);
            transform: translateY(-2px);
        }
        
        .quick-penalty-info {
            flex: 1;
        }
        
        .quick-penalty-info h5 {
            margin: 0 0 8px 0;
            color: #2d3436;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .quick-penalty-info small {
            color: #636e72;
            display: block;
            line-height: 1.6;
        }
        
        .btn-info {
            background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(9,132,227,0.3);
        }
        
        .btn-info:hover {
            background: linear-gradient(135deg, #0984e3 0%, #0652dd 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(9,132,227,0.4);
        }
        
        /* Damage Penalty Section Styles */
        .damage-penalty-section {
            background: linear-gradient(135deg, #fff5f5 0%, #ffffff 100%);
            border: 2px solid #ffcdd2;
        }
        
        .damage-penalty-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .damage-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .damage-info-item label {
            display: block;
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 4px;
        }
        
        .damage-info-item strong {
            font-size: 1rem;
            color: #333;
        }
        
        .damage-info-item small {
            color: #888;
            font-size: 0.85rem;
        }
        
        .text-danger {
            color: #d32f2f !important;
        }
        
        .text-warning {
            color: #f57c00 !important;
        }
        
        .text-success {
            color: #388e3c !important;
        }
        
        .detected-issues-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .detected-issues-box h4 {
            margin: 0 0 10px 0;
            color: #856404;
            font-size: 1rem;
        }

        /* Stat Card Enhancements */
        .stat-card {
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.15) !important;
        }

        .stat-card .stat-icon {
            font-size: 2.5rem;
            opacity: 0.9;
            margin-bottom: 12px;
        }

        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 800;
            margin: 0;
            color: #2d3436;
        }

        .stat-card .stat-label {
            font-size: 0.9rem;
            font-weight: 600;
            margin: 8px 0 0 0;
            color: #2d3436;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Button Enhancements */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(102,126,234,0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102,126,234,0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #55efc4 0%, #00b894 100%);
            color: #2d3436;
            box-shadow: 0 2px 8px rgba(0,184,148,0.3);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #00b894 0%, #00a383 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,184,148,0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #dfe6e9 0%, #b2bec3 100%);
            color: #2d3436;
            box-shadow: 0 2px 8px rgba(178,190,195,0.3);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #b2bec3 0%, #95a5a6 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(178,190,195,0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
            color: #2d3436;
            box-shadow: 0 2px 8px rgba(253,203,110,0.3);
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #fdcb6e 0%, #f39c12 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(253,203,110,0.4);
        }

        /* No Data State */
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #636e72;
        }

        .no-data i {
            color: #b2bec3;
            margin-bottom: 20px;
        }

        .no-data h3 {
            color: #2d3436;
            margin: 0 0 12px 0;
            font-weight: 700;
        }

        .no-data p {
            color: #636e72;
            margin: 0;
        }

        /* Penalty Detail Modal */
        .penalty-detail-modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.2s ease;
        }

        .penalty-detail-content {
            background: white;
            margin: 2% auto;
            padding: 0;
            border-radius: 16px;
            width: 90%;
            max-width: 900px;
            position: relative;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease-out;
            max-height: 90vh;
            overflow-y: auto;
        }

        .penalty-detail-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px 32px;
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .penalty-detail-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .penalty-detail-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 28px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .penalty-detail-close:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }

        .penalty-detail-body {
            padding: 32px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }

        .detail-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            border: 2px solid #e9ecef;
        }

        .detail-section h3 {
            margin: 0 0 16px 0;
            color: #2d3436;
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #636e72;
            font-size: 0.9rem;
        }

        .detail-value {
            color: #2d3436;
            font-size: 0.9rem;
            text-align: right;
            font-weight: 500;
        }

        .full-width-section {
            grid-column: 1 / -1;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 24px;
            border-top: 2px solid #e9ecef;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .penalties-table {
                font-size: 0.85rem;
            }

            .penalties-table th,
            .penalties-table td {
                padding: 10px;
            }

            .modal-content,
            .penalty-detail-content {
                width: 95%;
                padding: 20px;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .detected-issues-box h4 i {
            margin-right: 8px;
        }
        
        .issues-content {
            color: #856404;
            line-height: 1.6;
            font-size: 0.95rem;
        }
        
        .damage-penalty-form .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .required {
            color: #d32f2f;
        }
        
        .btn-danger {
            background: #d32f2f;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c62828;
        }
        
        /* Penalties Grid Layout */
        .penalties-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
        }

        @media (min-width: 1200px) {
            .penalties-grid {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }
        }

        .penalty-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 6px solid #1976d2;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            overflow: hidden;
        }
        
        .penalty-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .penalty-card.severity-minor {
            border-left-color: #4caf50;
        }
        
        .penalty-card.severity-moderate {
            border-left-color: #ff9800;
        }
        
        .penalty-card.severity-severe {
            border-left-color: #f44336;
        }
        
        .penalty-card.severity-total-loss {
            border-left-color: #9c27b0;
        }
        
        .penalty-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .penalty-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .severity-minor .penalty-badge {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .severity-moderate .penalty-badge {
            background: #fff3e0;
            color: #e65100;
        }
        
        .severity-severe .penalty-badge {
            background: #ffebee;
            color: #c62828;
        }
        
        .severity-total-loss .penalty-badge {
            background: #f3e5f5;
            color: #6a1b9a;
        }
        
        .penalty-id {
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
        }
        
        .penalty-card-body {
            padding: 20px;
        }
        
        .equipment-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin: 0 0 15px 0;
        }
        
        .penalty-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .info-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .info-row i {
            width: 16px;
            color: #999;
        }
        
        .score-low {
            color: #d32f2f;
            font-weight: 600;
        }
        
        .score-medium {
            color: #f57c00;
            font-weight: 600;
        }
        
        .score-high {
            color: #388e3c;
            font-weight: 600;
        }
        
        .penalty-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            background: #fafafa;
            border-top: 1px solid #e0e0e0;
        }
        
        .penalty-date {
            font-size: 0.85rem;
            color: #999;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .penalty-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-under-review {
            background: #cfe2ff;
            color: #084298;
        }
        
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-state h3 {
            margin: 0 0 10px 0;
            color: #666;
        }
        
        /* Penalty Detail Modal */
        .penalty-detail-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            overflow-y: auto;
        }
        
        .penalty-detail-content {
            background: white;
            margin: 40px auto;
            max-width: 1000px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .penalty-detail-header {
            padding: 24px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .penalty-detail-header h2 {
            margin: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .penalty-detail-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 1.5rem;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .penalty-detail-close:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }
        
        .penalty-detail-body {
            padding: 30px;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .detail-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .detail-section h3 {
            margin: 0 0 15px 0;
            font-size: 1rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ddd;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
        }
        
        .detail-value {
            color: #333;
            font-size: 0.9rem;
            text-align: right;
        }
        
        .full-width-section {
            grid-column: 1 / -1;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }
    </style>

    <script>
        function logout() {
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = 'logout.php';
        }

        function updatePenaltyStatus(penaltyId, status) {
            const modal = document.getElementById('statusModal');
            const penaltyField = document.getElementById('status_penalty_id');
            const statusSelect = document.getElementById('status_select');
            if (!modal || !penaltyField || !statusSelect) {
                return;
            }

            penaltyField.value = penaltyId;
            statusSelect.value = status;
            modal.style.display = 'block';
        }

        function closeModal() {
            const modal = document.getElementById('statusModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function toggleResolutionFields() {
            const statusSelect = document.getElementById('status_select');
            const resolutionFields = document.getElementById('resolution_fields');
            if (statusSelect && resolutionFields) {
                resolutionFields.style.display = statusSelect.value === 'Resolved' ? 'block' : 'none';
            }
        }

        function viewPenaltyDetail(penaltyId) {
            fetch(`get_penalty_detail.php?id=${penaltyId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    renderPenaltyDetail(data);
                })['catch'](error => {
                    console.error('Error fetching penalty details:', error);
                    alert('Failed to load penalty details. Please try again.');
                });
        }

        function renderPenaltyDetail(data) {
            const modal = document.getElementById('penaltyDetailModal');
            const content = document.getElementById('penaltyDetailContent');
            if (!modal || !content) {
                return;
            }

            const severityBadge = {
                minor: '<span class="penalty-badge" style="background:#e8f5e9;color:#2e7d32"><i class="fas fa-circle-info"></i> Minor</span>',
                moderate: '<span class="penalty-badge" style="background:#fff3e0;color:#e65100"><i class="fas fa-triangle-exclamation"></i> Moderate</span>',
                severe: '<span class="penalty-badge" style="background:#ffebee;color:#c62828"><i class="fas fa-circle-exclamation"></i> Severe</span>',
                total_loss: '<span class="penalty-badge" style="background:#f3e5f5;color:#6a1b9a"><i class="fas fa-circle-xmark"></i> Total Loss</span>'
            };

            const score = typeof data.similarity_score === 'number' ? data.similarity_score : null;
            const scoreClass = score === null ? '' : (score < 50 ? 'score-low' : (score < 70 ? 'score-medium' : 'score-high'));

            const detectedIssuesHtml = data.detected_issues
                ? `<div class="detail-section full-width-section">
                        <h3><i class="fas fa-clipboard-list"></i> Detected Issues</h3>
                        <div style="padding:10px;background:#fff3cd;border-radius:4px;color:#856404;">${data.detected_issues.replace(/\n/g, '<br>')}</div>
                   </div>`
                : '';

            const damageNotesHtml = data.damage_notes
                ? `<div class="detail-section full-width-section">
                        <h3><i class="fas fa-file-alt"></i> Admin Damage Assessment</h3>
                        <div style="padding:10px;background:#f8f9fa;border-radius:4px;">${data.damage_notes.replace(/\n/g, '<br>')}</div>
                   </div>`
                : '';

            const finalDecisionHtml = data.admin_assessment
                ? `<div class="detail-section full-width-section">
                        <h3><i class="fas fa-gavel"></i> Final Decision / Action</h3>
                        <div style="padding:10px;background:#e3f2fd;border-radius:4px;">${data.admin_assessment.replace(/\n/g, '<br>')}</div>
                   </div>`
                : '';

            const actualReturnHtml = data.actual_return_date
                ? `<div class="detail-row">
                        <span class="detail-label">Actual Return:</span>
                        <span class="detail-value">${data.actual_return_date}</span>
                   </div>`
                : '';

            const scoreHtml = score !== null
                ? `<div class="detail-row">
                        <span class="detail-label">Similarity Score:</span>
                        <span class="detail-value ${scoreClass}">${score}%</span>
                   </div>`
                : '';

            content.innerHTML = `
                    <div class="penalty-detail-header">
                        <h2><i class="fas fa-file-invoice"></i> Damage Penalty Details #${data.id}</h2>
                        <button class="penalty-detail-close" onclick="closePenaltyDetailModal()">×</button>
                    </div>
                    <div class="penalty-detail-body">
                        <div class="detail-grid">
                            <div class="detail-section">
                                <h3><i class="fas fa-box"></i> Equipment Information</h3>
                                <div class="detail-row"><span class="detail-label">Equipment Name:</span><span class="detail-value"><strong>${data.equipment_name || 'N/A'}</strong></span></div>
                                <div class="detail-row"><span class="detail-label">Equipment ID:</span><span class="detail-value">${data.equipment_id || 'N/A'}</span></div>
                                <div class="detail-row"><span class="detail-label">RFID Tag:</span><span class="detail-value">${data.equipment_rfid || 'N/A'}</span></div>
                                <div class="detail-row"><span class="detail-label">Category:</span><span class="detail-value">${data.category || 'N/A'}</span></div>
                            </div>
                            <div class="detail-section">
                                <h3><i class="fas fa-exchange-alt"></i> Transaction Information</h3>
                                <div class="detail-row"><span class="detail-label">Transaction ID:</span><span class="detail-value">#${data.transaction_id}</span></div>
                                <div class="detail-row"><span class="detail-label">Type:</span><span class="detail-value">${data.transaction_type || 'BORROW'}</span></div>
                                <div class="detail-row"><span class="detail-label">Status:</span><span class="detail-value">${data.txn_status || 'N/A'}</span></div>
                                <div class="detail-row"><span class="detail-label">RFID ID:</span><span class="detail-value">${data.user_rfid || 'N/A'}</span></div>
                            </div>
                            <div class="detail-section">
                                <h3><i class="fas fa-calendar"></i> Date Information</h3>
                                <div class="detail-row"><span class="detail-label">Transaction Date:</span><span class="detail-value">${data.transaction_date || 'N/A'}</span></div>
                                <div class="detail-row"><span class="detail-label">Due Date:</span><span class="detail-value">${data.due_date || 'N/A'}</span></div>
                                <div class="detail-row"><span class="detail-label">Planned Return:</span><span class="detail-value">${data.planned_return || 'N/A'}</span></div>
                                ${actualReturnHtml}
                            </div>
                            <div class="detail-section">
                                <h3><i class="fas fa-info-circle"></i> Additional Information</h3>
                                <div class="detail-row"><span class="detail-label">Return Condition:</span><span class="detail-value">${data.return_condition || 'Good'}</span></div>
                                <div class="detail-row"><span class="detail-label">Damage Severity:</span><span class="detail-value">${severityBadge[data.damage_severity] || 'N/A'}</span></div>
                                ${scoreHtml}
                                <div class="detail-row"><span class="detail-label">Notes:</span><span class="detail-value">${data.notes || 'Borrowed via kiosk'}</span></div>
                            </div>
                            ${detectedIssuesHtml}
                            ${damageNotesHtml}
                            ${finalDecisionHtml}
                        </div>
                        <div class="action-buttons">
                            <button class="btn btn-secondary" onclick="closePenaltyDetailModal()"><i class="fas fa-times"></i> Close</button>
                            <button class="btn btn-primary" onclick="updatePenaltyStatusModal(${data.id})"><i class="fas fa-edit"></i> Update Status</button>
                            <button class="btn btn-success" onclick="resolvePenalty(${data.id})"><i class="fas fa-check"></i> Mark as Resolved</button>
                        </div>
                </div>`;

            modal.style.display = 'block';
        }

        function closePenaltyDetailModal() {
            const modal = document.getElementById('penaltyDetailModal');
            if (modal) modal.style.display = 'none';
        }

        function updatePenaltyStatusModal(penaltyId) {
            closePenaltyDetailModal();
            updatePenaltyStatus(penaltyId, 'Under Review');
        }

        function resolvePenalty(penaltyId) {
            if (!confirm('Mark this penalty as resolved?')) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="penalty_id" value="${penaltyId}">
                <input type="hidden" name="status" value="Resolved">
                <input type="hidden" name="notes" value="Penalty resolved by admin">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        window.addEventListener('click', (event) => {
            const modal = document.getElementById('statusModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Sidebar toggle functionality handled by sidebar component
    </script>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"></div>

    <script>
        // Toast Notification System
        const Toast = (() => {
            const container = document.getElementById('toastContainer');
            if (!container) return { success: alert, error: alert, info: alert };

            const createToast = (type, message) => {
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                toast.innerHTML = `
                    <div class="toast-icon">
                        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
                    </div>
                    <div class="toast-content">
                        <strong>${type === 'success' ? 'Success' : type === 'error' ? 'Error' : 'Notice'}</strong>
                        <span>${message}</span>
                    </div>
                    <button class="toast-close" aria-label="Close notification">&times;</button>
                `;

                const close = () => {
                    toast.classList.add('hide');
                    toast.addEventListener('transitionend', () => toast.remove(), { once: true });
                };

                toast.querySelector('.toast-close').addEventListener('click', close);
                container.appendChild(toast);

                requestAnimationFrame(() => toast.classList.add('show'));
                setTimeout(close, 5000);
            };

            return {
                success: msg => createToast('success', msg),
                error: msg => createToast('error', msg),
                info: msg => createToast('info', msg)
            };
        })();

        // Show toast for PHP messages
        <?php if (!empty($success_message)): ?>
            Toast.success(<?= json_encode($success_message) ?>);
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            Toast.error(<?= json_encode($error_message) ?>);
        <?php endif; ?>
    </script>
    </body>
</html>
