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

$resolutionTypes = ['Completed', 'Repaired', 'Replaced', 'Waived', 'Dismissed', 'Other'];

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
                // Fallback: infer penalty_type from guideline name when missing
                if ($penalty_type === '') {
                    $gname = strtolower(trim((string)($guideline['guideline_name'] ?? $guideline['title'] ?? '')));
                    if ($gname !== '') {
                        if (strpos($gname, 'loss') !== false || strpos($gname, 'lost') !== false) {
                            $penalty_type = 'Loss';
                        } elseif (strpos($gname, 'damage') !== false || strpos($gname, 'damaged') !== false || strpos($gname, 'repair') !== false) {
                            $penalty_type = 'Damage';
                        } elseif (strpos($gname, 'late') !== false || strpos($gname, 'overdue') !== false) {
                            $penalty_type = 'Late Return';
                        }
                    }
                }
            } else {
                $guideline_is_active = false;
            }
        }
        
        $payload = [
            'transaction_id' => (int)($_POST['transaction_id'] ?? 0),
            'user_id' => (int)($_POST['user_id'] ?? 0) ?: null,
            // Borrower identifier can be RFID or Student ID (manual)
            'borrower_identifier' => trim($_POST['rfid_id'] ?? ($_POST['manual_student_id'] ?? ($_POST['student_id'] ?? ''))),
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
        } elseif ($payload['penalty_type'] === '' || $payload['equipment_name'] === '' ||
                  (empty($payload['transaction_id']) && empty($payload['user_id']) && $payload['borrower_identifier'] === '')) {
            $error_message = "Please provide a Penalty Guideline and Equipment Name, and either a Transaction ID or a Student/RFID identifier.";
        } else {

            // Block duplicate penalties for the same transaction
            if (!empty($payload['transaction_id']) && $penaltySystem->penaltyExistsForTransaction((int)$payload['transaction_id'])) {
                $error_message = "A penalty already exists for this transaction. Only one penalty can be issued per transaction.";
            } elseif ($payload['penalty_type'] === 'Late Return' && $payload['days_overdue'] > 0) {
                $dailyRate = $payload['daily_rate'] ?: PenaltySystem::DEFAULT_DAILY_RATE;
                $amount = $dailyRate * $payload['days_overdue'];
                $payload['penalty_amount'] = $amount;
                $payload['amount_owed'] = $amount;
                $payload['amount_note'] = sprintf('%d day(s) × ₱%0.2f per day', $payload['days_overdue'], $dailyRate);
            }

            if (empty($error_message) && $penaltySystem->createPenalty($payload)) {
                $_SESSION['success_message'] = "Penalty record created successfully.";
                header('Location: admin-penalty-management.php');
                exit;
            } else {
                if (empty($error_message)) {
                    $error_message = "Failed to create penalty. Please verify the details.";
                }
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
            $_SESSION['success_message'] = "Penalty status updated successfully.";
            header('Location: admin-penalty-management.php');
            exit;
        } else {
            $error_message = "Failed to update penalty status.";
        }
    }
    
    if ($action === 'cancel_penalty' && isset($_POST['penalty_id'])) {
        $penaltyId = (int)$_POST['penalty_id'];
        $reason = $_POST['cancel_reason'] ?? 'Cancelled by admin';
        $statusPayload = [
            'status' => 'Cancelled',
            'notes' => $reason,
            'resolution_type' => 'Waived',
            'resolution_notes' => $reason
        ];

        if ($penaltySystem->updatePenaltyStatus($penaltyId, $statusPayload)) {
            $_SESSION['success_message'] = "Penalty cancelled successfully.";
            header('Location: admin-penalty-management.php');
            exit;
        } else {
            $error_message = "Failed to cancel penalty.";
        }
    }
    
    // Payment processing removed - penalties are tracked for record-keeping only
    
    if ($action === 'process_appeal' && isset($_POST['penalty_id'])) {
        $penaltyId = (int)$_POST['penalty_id'];
        $appealDecision = $_POST['appeal_decision'] ?? 'Under Review';
        $appealNotes = $_POST['appeal_notes'] ?? '';
        
        $statusPayload = [
            'status' => $appealDecision,
            'notes' => 'Appeal processed: ' . $appealNotes,
            'resolution_type' => ($appealDecision === 'Resolved') ? 'Waived' : null,
            'resolution_notes' => $appealNotes
        ];

        if ($penaltySystem->updatePenaltyStatus($penaltyId, $statusPayload)) {
            $_SESSION['success_message'] = "Appeal processed successfully.";
            header('Location: admin-penalty-management.php');
            exit;
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
                    <button type="button" id="quickPenaltyBtn" class="btn btn-success" onclick="openQuickPenalty()">
                        <i class="fas fa-bolt"></i> Quick Penalty
                    </button>
                </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function norm(s){ return (s||'').toString().toLowerCase(); }
        const statusEl = document.getElementById('statusFilter');
        const typeEl = document.getElementById('typeFilter');
        const searchEl = document.getElementById('searchInput');

        function applyFilter(){
            const st = norm(statusEl && statusEl.value);
            const tp = norm(typeEl && typeEl.value);
            const q = norm(searchEl && searchEl.value).trim();
            const rows = document.querySelectorAll('#penaltiesTable tbody .penalty-row');
            let shown = 0;
            rows.forEach(row => {
                const rStatus = row.getAttribute('data-status');
                const rType = row.getAttribute('data-type');
                const text = norm(row.innerText);
                const okStatus = !st || st === 'all' || rStatus === st;
                const okType = !tp || tp === 'all' || rType === tp;
                const okText = !q || text.indexOf(q) !== -1;
                const show = okStatus && okType && okText;
                row.style.display = show ? '' : 'none';
                if (show) shown++;
            });
            const noData = document.querySelector('.no-data');
            if (noData) noData.style.display = shown === 0 ? '' : 'none';
        }

        if (statusEl) statusEl.addEventListener('change', applyFilter);
        if (typeEl) typeEl.addEventListener('change', applyFilter);
        if (searchEl) searchEl.addEventListener('input', applyFilter);

        // Initial filter on load to reflect current values
        applyFilter();
    });
    </script>
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

            <!-- Penalty Statistics (Simplified) -->
            <section class="content-section">
                <div class="section-header" style="align-items:center; gap:12px;">
                    <h2 style="margin:0;"><i class="fas fa-chart-bar"></i> Penalty Snapshot</h2>
                    <?php
                        $activeStatuses = ['Pending','Under Review','Awaiting Student Action','Repair in Progress','Awaiting Inspection','Appealed'];
                        $activeCount = 0;
                        foreach ($activeStatuses as $s) {
                            $activeCount += (int)($stats['by_status'][$s]['count'] ?? 0);
                        }
                        $resolvedCount = (int)($stats['by_status']['Resolved']['count'] ?? 0);
                        $damageCases = (int)($stats['damage_cases'] ?? 0);
                        $lateReturns = (int)($stats['late_return_cases'] ?? 0);
                    ?>
                </div>

                <div class="stats-grid" style="grid-template-columns: repeat(4, minmax(160px,1fr));">
                    <div class="stat-card">
                        <div class="stat-icon" style="background:#0ea5e9;color:#fff;">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number"><?= number_format($activeCount) ?></h3>
                            <p class="stat-label">Active Penalties</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background:#ef4444;color:#fff;">
                            <i class="fas fa-hammer"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number"><?= number_format($damageCases) ?></h3>
                            <p class="stat-label">Damage Cases</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background:#f59e0b;color:#fff;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number"><?= number_format($lateReturns) ?></h3>
                            <p class="stat-label">Late Returns</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background:#22c55e;color:#fff;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number"><?= number_format($resolvedCount) ?></h3>
                            <p class="stat-label">Resolved</p>
                        </div>
                    </div>
                </div>

                <div style="margin-top:10px;">
                    <button type="button" id="toggleBreakdown" class="btn btn-secondary" style="padding:6px 10px; font-size:12px;">
                        <i class="fas fa-list"></i> Show Status Breakdown
                    </button>
                </div>

                <div id="statusBreakdown" style="display:none; margin-top:12px;">
                    <div class="stats-grid" style="grid-template-columns: repeat(5, minmax(160px,1fr));">
                        <?php foreach ($activeStatuses as $label): ?>
                            <?php
                                $color = '#94a3b8';
                                $icon = 'circle';
                                switch ($label) {
                                    case 'Pending':
                                        $color = '#f59e0b'; $icon = 'exclamation-triangle'; break;
                                    case 'Under Review':
                                        $color = '#0ea5e9'; $icon = 'search'; break;
                                    case 'Awaiting Student Action':
                                        $color = '#fb923c'; $icon = 'user-clock'; break;
                                    case 'Repair in Progress':
                                        $color = '#14b8a6'; $icon = 'screwdriver-wrench'; break;
                                    case 'Awaiting Inspection':
                                        $color = '#8b5cf6'; $icon = 'clipboard-check'; break;
                                }
                            ?>
                            <div class="stat-card">
                                <div class="stat-icon" style="background:<?= $color ?>;color:#fff;filter:none;">
                                    <i class="fas fa-<?= $icon ?>"></i>
                                </div>
                                <div class="stat-content">
                                    <h3 class="stat-number"><?= number_format($stats['by_status'][$label]['count'] ?? 0) ?></h3>
                                    <p class="stat-label"><?= htmlspecialchars($label) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="stat-card">
                            <div class="stat-icon" style="background:#6b7280;color:#fff;filter:none;">
                                <i class="fas fa-gavel"></i>
                            </div>
                            <div class="stat-content">
                                <h3 class="stat-number"><?= number_format($stats['by_status']['Appealed']['count'] ?? 0) ?></h3>
                                <p class="stat-label">Appealed</p>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    (function(){
                        const btn = document.getElementById('toggleBreakdown');
                        const panel = document.getElementById('statusBreakdown');
                        if (!btn || !panel) return;
                        btn.addEventListener('click', () => {
                            const open = panel.style.display !== 'none';
                            panel.style.display = open ? 'none' : '';
                            btn.innerHTML = open
                                ? '<i class="fas fa-list"></i> Show Status Breakdown'
                                : '<i class="fas fa-compress"></i> Hide Status Breakdown';
                        });
                    })();
                </script>
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

                        <?php
                            $suggestedGuidelineId = $damage_penalty_data['suggested_guideline_id'] ?? '';
                            $suggestedGuidelineReason = $damage_penalty_data['suggested_guideline_reason'] ?? 'Auto-suggested based on damage severity';
                            $suggestedGuidelineName = 'N/A';
                            if (!empty($suggestedGuidelineId)) {
                                foreach ($activeGuidelines as $guideline) {
                                    if (isset($guideline['id']) && (int)$guideline['id'] === (int)$suggestedGuidelineId) {
                                        $suggestedGuidelineName = $guideline['guideline_name'] ?? $guideline['title'] ?? 'Unnamed Guideline';
                                        break;
                                    }
                                }
                            }
                        ?>
                        <input type="hidden" id="suggested_guideline_id" value="<?= htmlspecialchars((string)$suggestedGuidelineId) ?>">
                        <?php if (!empty($suggestedGuidelineId)): ?>
                        <div id="guidelineSuggestionContainer" class="alert suggested-guideline" style="margin-top: 0;">
                            <i class="fas fa-lightbulb"></i>
                            <div>
                                <strong>Suggested Guideline:</strong>
                                <span id="guidelineSuggestionName"><?= htmlspecialchars($suggestedGuidelineName) ?></span>
                                <br>
                                <small id="guidelineSuggestionReason"><?= htmlspecialchars($suggestedGuidelineReason) ?></small>
                                <div id="guidelineOverrideNote" class="override-note" style="display: none;">
                                    Manual override active — confirm the selected guideline fits this damage case.
                                </div>
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
                            <label for="penalty_guideline">Select Penalty Guideline to Issue: <span class="required">*</span> <span id="guidelineSelectionBadge" class="suggestion-badge" style="display: none;"></span></label>
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

                <form method="GET" class="filter-form" id="penaltyFilterForm" onsubmit="return false;">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select name="status" id="statusFilter">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Under Review" <?= $status_filter === 'Under Review' ? 'selected' : '' ?>>Under Review</option>
                            <option value="Awaiting Student Action" <?= $status_filter === 'Awaiting Student Action' ? 'selected' : '' ?>>Awaiting Student Action</option>
                            <option value="Repair in Progress" <?= $status_filter === 'Repair in Progress' ? 'selected' : '' ?>>Repair in Progress</option>
                            <option value="Awaiting Inspection" <?= $status_filter === 'Awaiting Inspection' ? 'selected' : '' ?>>Awaiting Inspection</option>
                            <option value="Resolved" <?= $status_filter === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                            <option value="Cancelled" <?= $status_filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            <option value="Appealed" <?= $status_filter === 'Appealed' ? 'selected' : '' ?>>Appealed</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="type">Penalty Type</label>
                        <select name="type" id="typeFilter">
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
                        <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Student ID, RFID, transaction, equipment" autocomplete="off">
                    </div>
                </form>
            </section>

            <!-- Penalties List -->
            <section class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-list"></i> Penalties</h2>
                </div>

                <?php if ($penalties_result && $penalties_result->num_rows > 0): ?>
                    <div class="table-container" id="penaltiesTableContainer">
                        <table class="penalties-table simplified" id="penaltiesTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Student</th>
                                    <th>Equipment</th>
                                    <th>Type</th>
                                    <th>Penalty Guideline</th>
                                    <th>Severity</th>
                                    <th>Status</th>
                                    <th>Date Imposed</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($penalty = $penalties_result->fetch_assoc()): ?>
                                    <tr class="penalty-row" data-status="<?= htmlspecialchars(strtolower($penalty['status'])) ?>" data-type="<?= htmlspecialchars(strtolower($penalty['penalty_type'])) ?>">
                                        <td>#<?= $penalty['id'] ?></td>
                                        <td><?= htmlspecialchars($penalty['user_id'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($penalty['equipment_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <span class="badge penalty-type <?= strtolower($penalty['penalty_type']) ?>">
                                                <?= htmlspecialchars($penalty['penalty_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($penalty['guideline_type'])): ?>
                                                <span class="guideline-text">
                                                    <?= htmlspecialchars($penalty['guideline_type']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">No guideline</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $penalty['damage_severity'] ?? 'N/A'))) ?></td>
                                        <td>
                                            <span class="badge status <?= strtolower(str_replace(' ', '-', $penalty['status'])) ?>">
                                                <?= htmlspecialchars($penalty['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= !empty($penalty['date_imposed']) ? date('M d, Y', strtotime($penalty['date_imposed'])) : 'N/A' ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn btn-small btn-info" onclick="viewPenaltyDetail(<?= $penalty['id'] ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if (in_array($penalty['status'], ['Pending', 'Under Review', 'Awaiting Student Action', 'Repair in Progress', 'Awaiting Inspection'], true)): ?>
                                                    <button type="button" class="btn btn-small btn-danger" onclick="dismissPenaltyModal(event, <?= $penalty['id'] ?>)" title="Dismiss Penalty">
                                                        <i class="fas fa-times-circle"></i> Dismiss
                                                    </button>
                                                <?php elseif ($penalty['status'] === 'Appealed'): ?>
                                                    <button class="btn btn-small btn-secondary" onclick="processAppealModal(<?= $penalty['id'] ?>)" title="Process Appeal">
                                                        <i class="fas fa-gavel"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($penalty['status'] === 'Resolved' || $penalty['status'] === 'Cancelled'): ?>
                                                    <span class="text-muted" style="font-size: 0.85rem;">
                                                        <i class="fas fa-check"></i> Completed
                                                    </span>
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
                        <option value="Awaiting Student Action">Awaiting Student Action</option>
                        <option value="Repair in Progress">Repair in Progress</option>
                        <option value="Awaiting Inspection">Awaiting Inspection</option>
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
            </form>
        </div>
    </div>

    <!-- Dismiss Penalty Modal -->
    <div id="dismissPenaltyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDismissPenaltyModal()">&times;</span>
            <h2><i class="fas fa-times-circle"></i> Dismiss Penalty</h2>
            <form method="POST">
                <input type="hidden" name="action" value="cancel_penalty">
                <input type="hidden" name="penalty_id" id="dismiss_penalty_id">
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> Dismissing a penalty will mark it as waived and close the record.
                </div>
                
                <div class="form-group">
                    <label for="dismiss_reason">Dismissal Reason <span class="required">*</span></label>
                    <textarea name="cancel_reason" id="dismiss_reason" rows="4" required placeholder="Explain why this penalty is being dismissed..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> Dismiss Penalty
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeDismissPenaltyModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Process Appeal Modal -->
    <div id="processAppealModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeProcessAppealModal()">&times;</span>
            <h2><i class="fas fa-gavel"></i> Process Appeal</h2>
            <form method="POST">
                <input type="hidden" name="action" value="process_appeal">
                <input type="hidden" name="penalty_id" id="process_appeal_penalty_id">
                
                <div class="form-group">
                    <label for="appeal_decision">Appeal Decision <span class="required">*</span></label>
                    <select name="appeal_decision" id="appeal_decision" required>
                        <option value="Under Review">Keep Under Review</option>
                        <option value="Resolved">Approve Appeal (Waive Penalty)</option>
                        <option value="Pending">Reject Appeal (Keep Pending)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="appeal_notes">Decision Notes <span class="required">*</span></label>
                    <textarea name="appeal_notes" id="appeal_notes" rows="4" required placeholder="Explain the decision on this appeal..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-gavel"></i> Submit Decision
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeProcessAppealModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Penalty Modal -->
    <div id="createPenaltyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeManualPenaltyModal()">&times;</span>
            <h2>Add Penalty Manually</h2>
            <form id="createPenaltyForm" method="POST">
                <input type="hidden" name="action" value="create_penalty">
                <input type="hidden" id="user_id" name="user_id">

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="manual_student_id">Student ID <span class="required">*</span></label>
                        <input type="text" id="manual_student_id" name="manual_student_id" placeholder="Enter Student ID" onblur="searchStudent()">
                        <small class="form-hint">Enter student ID to auto-fill details</small>
                    </div>

                    <div class="form-group">
                        <label for="rfid_id">RFID Tag</label>
                        <input type="text" id="rfid_id" name="rfid_id" placeholder="RFID Tag (optional)" readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label for="transaction_id">Transaction ID (Optional)</label>
                    <div style="display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: center;">
                        <div style="display:flex; gap:8px; align-items:center;">
                            <select id="transaction_select" style="min-width: 220px; display:none;">
                                <option value="">-- Select eligible transaction --</option>
                            </select>
                            <input type="number" id="transaction_id" name="transaction_id" min="1" placeholder="Leave blank for manual entry" style="flex: 1;">
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="loadTransactionDetails()" style="white-space: nowrap;">
                            <i class="fas fa-download"></i> Load from Transaction
                        </button>
                    </div>
                    <small class="form-hint">If Student ID/RFID is provided, you'll see only Damage/Flagged transactions here.</small>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 2fr; gap: 15px;">
                    <div class="form-group">
                        <label for="equipment_id">Equipment ID</label>
                        <input type="text" id="equipment_id" name="equipment_id" placeholder="Equipment ID">
                    </div>

                    <div class="form-group">
                        <label for="equipment_name">Equipment Name <span class="required">*</span></label>
                        <input type="text" id="equipment_name" name="equipment_name" required placeholder="Enter equipment name">
                    </div>
                </div>

                <div class="form-group">
                    <label for="manual_guideline_id">Select Penalty Guideline <span class="required">*</span></label>
                    <select id="manual_guideline_id" name="guideline_id" required onchange="updatePenaltyFromGuideline()">
                        <option value="">-- Select Penalty Guideline --</option>
                        <?php foreach ($activeGuidelines as $guideline): ?>
                            <option value="<?= htmlspecialchars($guideline['id']) ?>" 
                                    data-type="<?= htmlspecialchars($guideline['penalty_type']) ?>"
                                    data-amount="<?= htmlspecialchars($guideline['penalty_amount']) ?>"
                                    data-points="<?= htmlspecialchars($guideline['penalty_points'] ?? '') ?>">
                                <?= htmlspecialchars($guideline['guideline_name'] ?? $guideline['title']) ?> 
                                (<?= htmlspecialchars($guideline['penalty_type']) ?>) - 
                                ₱<?= number_format($guideline['penalty_amount'], 2) ?>
                                <?php if (!empty($guideline['penalty_points'])): ?>
                                    | <?= htmlspecialchars($guideline['penalty_points']) ?> points
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint">Penalty amount will be automatically set based on guideline</small>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;" id="late_return_fields" style="display: none;">
                    <div class="form-group">
                        <label for="days_overdue">Days Overdue</label>
                        <input type="number" id="days_overdue" name="days_overdue" min="0" value="0" onchange="calculateLatePenalty()">
                    </div>

                    <div class="form-group">
                        <label for="daily_rate">Daily Rate (₱)</label>
                        <input type="number" id="daily_rate" name="daily_rate" step="0.01" min="0" value="50.00">
                    </div>
                </div>

                <div class="form-group" id="damage_severity_group" style="display: none;">
                    <label for="damage_severity">Damage Severity</label>
                    <select id="damage_severity" name="damage_severity">
                        <option value="">-- Select Severity --</option>
                        <option value="minor">Minor - Superficial damage</option>
                        <option value="moderate">Moderate - Visible damage</option>
                        <option value="severe">Severe - Major damage</option>
                        <option value="total_loss">Total Loss - Beyond repair</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="manual_notes">Notes / Description</label>
                    <textarea id="manual_notes" name="notes" rows="3" placeholder="Add details about the penalty reason..."></textarea>
                </div>

                <div class="penalty-preview" id="manual_penalty_preview" style="background: #e7f3ff; padding: 12px; border-radius: 6px; margin: 15px 0;">
                    <strong>Penalty Amount: ₱<span id="preview_amount">0.00</span></strong>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Create Penalty
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeManualPenaltyModal()">
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
            padding: 12px 16px;
            margin: 16px 0;
            border-radius: 8px;
            display: flex;
            align-items: center;
        }

        /* Sticky header with horizontal status tracker */
        .penalty-detail-header {
            position: sticky;
            top: 0;
            z-index: 20;
            background: #ffffff;
            border-bottom: 1px solid #e9ecef;
            padding: 10px 14px;
        }

        .penalty-detail-header .header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .status-tracker {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: nowrap;
            flex: 1 1 auto;
            max-width: 520px;
            justify-content: space-between;
            padding: 4px 6px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            min-height: 34px;
        }

        .status-step {
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            font-weight: 600;
            color: #6c757d;
            flex: 1 1 0;
            min-width: 0;
            font-size: 0.82rem;
        }

        .status-step .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #dee2e6;
            box-shadow: 0 0 0 2px #e9ecef inset;
        }

        .status-step.done { color: #2e7d32; }
        .status-step.done .dot {
            background: #28a745;
            box-shadow: 0 0 0 2px rgba(40,167,69,0.25);
        }

        .status-step.current { color: #0d6efd; }
        .status-step.current .dot {
            background: #0d6efd;
            box-shadow: 0 0 0 2px rgba(13,110,253,0.25);
        }

        .status-divider {
            width: 14px;
            height: 2px;
            background: #e0e0e0;
            flex: 0 0 14px;
        }

        .status-step span:last-child {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Label behavior per state */
        .status-step .label {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .status-step.done .label {
            display: none;
        }

        .status-step.current { flex: 2 1 0; }
        .status-step.current .label { font-size: 0.9rem; }
        .status-step.future .label { opacity: 0.9; }

        @media (max-width: 640px) {
            .status-tracker { max-width: 100%; }
            .status-step span:last-child { font-size: 0.78rem; }
            .status-divider { width: 10px; flex-basis: 10px; }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .filter-form {
            display: flex;
            gap: 15px;
            align-items: end;
            margin-bottom: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
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
        }

        .filter-group select,
        .filter-group input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.95rem;
        }

        .filter-group select:focus,
        .filter-group input[type="text"]:focus {
            outline: none;
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }

        .table-container {
            overflow-x: auto;
        }

        .penalties-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .penalties-table th,
        .penalties-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        /* Compact Actions column */
        .penalties-table th:last-child,
        .penalties-table td:last-child {
            width: 150px;
            max-width: 150px;
            white-space: nowrap;
            padding-right: 8px;
        }

        /* Tighter spacing for action buttons inside table */
        .penalties-table .action-buttons {
            gap: 6px;
            justify-content: flex-start;
        }

        .penalties-table th {
            background: #006633;
            font-weight: 600;
            color: white;
            font-size: 0.9rem;
        }

        .penalties-table tbody tr:hover {
            background: #f8f9fa;
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
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge.penalty-type.overdue,
        .badge.penalty-type.late-return {
            background: #ffc107;
            color: #000;
        }

        .badge.penalty-type.damaged,
        .badge.penalty-type.damage {
            background: #dc3545;
            color: white;
        }

        .badge.penalty-type.lost,
        .badge.penalty-type.loss {
            background: #6c757d;
            color: white;
        }

        .badge.status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge.status.pending {
            background: #ffc107;
            color: #000;
        }

        .badge.status.resolved,
        .badge.status.paid {
            background: #28a745;
            color: white;
        }

        .badge.status.cancelled,
        .badge.status.waived {
            background: #6c757d;
            color: white;
        }

        .badge.status.under-review {
            background: #17a2b8;
            color: white;
        }

        .badge.status.awaiting-student-action {
            background: #ff7043;
            color: white;
        }

        .badge.status.repair-in-progress {
            background: #20c997;
            color: white;
        }

        .badge.status.awaiting-inspection {
            background: #6f42c1;
            color: white;
        }

        .badge.status.appealed {
            background: #6c5ce7;
            color: white;
        }

        .amount {
            font-weight: 600;
            color: #dc3545;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn-small {
            padding: 6px 10px;
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .text-muted {
            color: #6c757d;
            font-style: italic;
        }

        .suggested-guideline {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-left: 4px solid #2196f3;
            color: #0d47a1;
        }

        .suggested-guideline .override-note {
            margin-top: 8px;
            font-size: 0.85rem;
            color: #c62828;
            font-weight: 600;
        }

        .suggestion-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            background: #e8f5e9;
            color: #2e7d32;
        }

        .suggestion-badge.manual {
            background: #fff3e0;
            color: #e65100;
        }

        .guideline-text {
            align-items: center;
            gap: 10px;
        }

        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }

        .alert-info {
            background-color: #d1ecf1;
            border: 1px solid #17a2b8;
            color: #0c5460;
        }

        .alert i {
            font-size: 1.2rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 24px;
            border-radius: 8px;
            width: 85%;
            max-width: 650px;
            position: relative;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .modal-content h2 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .close {
            position: absolute;
            right: 15px;
            top: 15px;
            color: #999;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            width: 30px;
            height: 30px;
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
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.95rem;
            font-family: inherit;
        }

        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
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
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 16px;
            margin: 16px 0;
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
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 16px;
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .quick-penalty-item:hover {
            border-color: #007bff;
            background: #f8f9fa;
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
            background: #17a2b8;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 600;
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        /* Damage Penalty Section Styles */
        .damage-penalty-section {
            background: #fff;
            border: 1px solid #f8d7da;
        }
        
        .damage-penalty-card {
            background: white;
            border-radius: 8px;
            padding: 16px;
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
        }

        /* Button Enhancements */
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
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
            background-color: rgba(0,0,0,0.5);
        }

        .penalty-detail-content {
            background: white;
            margin: 3% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 900px;
            position: relative;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-height: 90vh;
            overflow-y: auto;
        }

        .penalty-detail-header {
            background: #006633;
            color: white;
            padding: 20px 24px;
            border-radius: 8px 8px 0 0;
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
            font-size: 24px;
            width: 32px;
            height: 32px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .penalty-detail-close:hover {
            background: rgba(255,255,255,0.3);
        }

        .penalty-detail-body {
            padding: 24px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }

        .detail-section {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
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

        .required {
            color: #dc3545;
            font-weight: bold;
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

            const history = Array.isArray(data.status_history) ? data.status_history : [];
            const statusSequence = ['Pending', 'Under Review', 'Awaiting Student Action', 'Repair in Progress', 'Awaiting Inspection', 'Resolved'];
            let currentStatusIndex = statusSequence.indexOf(data.status || '');
            if (currentStatusIndex === -1) {
                currentStatusIndex = history.length ? statusSequence.indexOf(history[history.length - 1].new_status) : 0;
            }

            const historyMarkup = history.map(entry => {
                const statusIdx = statusSequence.indexOf(entry.new_status);
                let stateClass = 'future';
                if (statusIdx !== -1) {
                    if (statusIdx < currentStatusIndex) stateClass = 'done';
                    else if (statusIdx === currentStatusIndex) stateClass = 'current';
                }

                const formattedDate = entry.created_at ? new Date(entry.created_at).toLocaleString() : '';
                const adminName = entry.admin_username ? entry.admin_username : (entry.changed_by ? `Admin #${entry.changed_by}` : 'System');
                const notes = entry.notes ? `<div class="timeline-notes">${entry.notes.replace(/\n/g, '<br>')}</div>` : '';
                return `
                    <li class="timeline-step ${stateClass}">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <span class="timeline-status">${entry.new_status}</span>
                                <span class="timeline-meta">${formattedDate}</span>
                            </div>
                            <div class="timeline-submeta">Updated by ${adminName}</div>
                            ${notes}
                        </div>
                    </li>`;
            }).join('');

            const trackerSteps = statusSequence.map((label, idx) => {
                const cls = idx < currentStatusIndex ? 'done' : (idx === currentStatusIndex ? 'current' : 'future');
                return `
                    <div class="status-step ${cls}">
                        <span class="dot"></span>
                        <span class="label">${label}</span>
                    </div>`;
            }).join('<div class="status-divider"></div>');

            const trackerMarkup = `<div class="status-tracker">${trackerSteps}</div>`;

            content.innerHTML = `
                    <div class="penalty-detail-header">
                        <div class="header-row">
                            <h2><i class="fas fa-file-invoice"></i> Damage Penalty Details #${data.id}</h2>
                            ${trackerMarkup}
                            <button class="penalty-detail-close" onclick="closePenaltyDetailModal()">×</button>
                        </div>
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
            const statusModal = document.getElementById('statusModal');
            const dismissModal = document.getElementById('dismissPenaltyModal');
            const appealModal = document.getElementById('processAppealModal');
            const createModal = document.getElementById('createPenaltyModal');
            if (dismissModal) dismissModal.style.display = 'none';
            if (appealModal) appealModal.style.display = 'none';
            if (createModal) createModal.style.display = 'none';
            if (statusModal) {
                document.getElementById('status_penalty_id').value = penaltyId;
                statusModal.style.display = 'block';
            }
        }

        // Dismiss Penalty Modal
        function dismissPenaltyModal(e, penaltyId) {
            if (e && typeof e.stopPropagation === 'function') { e.stopPropagation(); }
            const statusModal = document.getElementById('statusModal');
            const dismissModal = document.getElementById('dismissPenaltyModal');
            const appealModal = document.getElementById('processAppealModal');
            const createModal = document.getElementById('createPenaltyModal');
            if (statusModal) statusModal.style.display = 'none';
            if (appealModal) appealModal.style.display = 'none';
            if (createModal) createModal.style.display = 'none';
            if (dismissModal) {
                const idField = document.getElementById('dismiss_penalty_id');
                if (idField) idField.value = penaltyId;
                try { if (dismissModal.parentElement !== document.body) document.body.appendChild(dismissModal); } catch (_) {}
                dismissModal.style.display = 'block';
                dismissModal.style.visibility = 'visible';
                dismissModal.style.opacity = '1';
                dismissModal.style.pointerEvents = 'auto';
                dismissModal.style.zIndex = '5000';
                try { void dismissModal.offsetHeight; } catch (_) {}
                setTimeout(() => { if (dismissModal) dismissModal.style.display = 'block'; }, 0);
            }
        }

        function closeDismissPenaltyModal() {
            document.getElementById('dismissPenaltyModal').style.display = 'none';
        }

        // Process Appeal Modal
        function processAppealModal(penaltyId) {
            const statusModal = document.getElementById('statusModal');
            const dismissModal = document.getElementById('dismissPenaltyModal');
            const appealModal = document.getElementById('processAppealModal');
            const createModal = document.getElementById('createPenaltyModal');
            if (statusModal) statusModal.style.display = 'none';
            if (dismissModal) dismissModal.style.display = 'none';
            if (createModal) createModal.style.display = 'none';
            if (appealModal) {
                document.getElementById('process_appeal_penalty_id').value = penaltyId;
                appealModal.style.display = 'block';
            }
        }

        function closeProcessAppealModal() {
            document.getElementById('processAppealModal').style.display = 'none';
        }

        window.addEventListener('click', (event) => {
            const modal = document.getElementById('statusModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Manual Penalty Modal Functions
        window.openManualPenaltyModal = function(e) {
            if (e && typeof e.stopPropagation === 'function') { e.stopPropagation(); }
            const modal = document.getElementById('createPenaltyModal');
            if (!modal) {
                if (window.Toast) Toast.error('Create Penalty modal not found in DOM.');
                return;
            }

            // Close other modals to avoid conflicts
            const statusModal = document.getElementById('statusModal');
            const dismissModal = document.getElementById('dismissPenaltyModal');
            const appealModal = document.getElementById('processAppealModal');
            if (statusModal) statusModal.style.display = 'none';
            if (dismissModal) dismissModal.style.display = 'none';
            if (appealModal) appealModal.style.display = 'none';

            const form = document.getElementById('createPenaltyForm');
            if (form && typeof form.reset === 'function') {
                try { form.reset(); } catch (_) {}
            }
            const preview = document.getElementById('preview_amount');
            if (preview) { preview.textContent = '0.00'; }
            const lateFields = document.getElementById('late_return_fields');
            if (lateFields) { lateFields.style.display = 'none'; }
            const dmgGroup = document.getElementById('damage_severity_group');
            if (dmgGroup) { dmgGroup.style.display = 'none'; }

            // Ensure modal is at top-level to avoid any stacking context issues
            try { if (modal.parentElement !== document.body) document.body.appendChild(modal); } catch (_) {}
            modal.style.display = 'block';
            modal.style.visibility = 'visible';
            modal.style.opacity = '1';
            modal.style.pointerEvents = 'auto';
            modal.style.zIndex = '5000';
            // Force reflow to apply
            try { void modal.offsetHeight; } catch (_) {}
            // Reinforce after event loop in case any global click handler toggles it
            setTimeout(() => { if (modal) modal.style.display = 'block'; }, 0);
            try { document.documentElement.scrollTop = 0; document.body.scrollTop = 0; } catch (_) {}
        }

        function closeManualPenaltyModal() {
            const modal = document.getElementById('createPenaltyModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function searchStudent() {
            const studentId = document.getElementById('manual_student_id').value.trim();
            if (!studentId) return;

            fetch(`api/search_student.php?student_id=${encodeURIComponent(studentId)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.student) {
                        document.getElementById('user_id').value = data.student.id || '';
                        document.getElementById('rfid_id').value = data.student.rfid_tag || '';
                        // Populate eligible transactions for this student
                        fetchEligibleTransactions(studentId, data.student.rfid_tag || '');
                    } else {
                        alert('Student not found. Please verify the Student ID.');
                        // Clear dropdown
                        populateTransactionSelect([]);
                    }
                })
                .catch(error => {
                    console.error('Error searching student:', error);
                });
        }

        function fetchEligibleTransactions(studentId, rfid) {
            const params = new URLSearchParams();
            if (studentId) params.set('student_id', studentId);
            if (rfid) params.set('rfid', rfid);
            if ([...params.keys()].length === 0) return;

            fetch(`api/list_user_transactions.php?${params.toString()}`)
                .then(r => r.json())
                .then(d => {
                    if (d && d.success) {
                        populateTransactionSelect(d.transactions || []);
                    } else {
                        populateTransactionSelect([]);
                    }
                })
                .catch(err => {
                    console.error('Failed to fetch eligible transactions', err);
                    populateTransactionSelect([]);
                });
        }

        function populateTransactionSelect(items) {
            const sel = document.getElementById('transaction_select');
            const input = document.getElementById('transaction_id');
            if (!sel || !input) return;

            // Reset
            sel.innerHTML = '<option value="">-- Select eligible transaction --</option>';

            if (Array.isArray(items) && items.length > 0) {
                items.forEach(it => {
                    const label = `#${it.id} — ${it.equipment_name || it.equipment_id || 'Equipment'} (${it.status})`;
                    const opt = document.createElement('option');
                    opt.value = it.id;
                    opt.textContent = label;
                    sel.appendChild(opt);
                });
                sel.style.display = '';
                // Prefer dropdown; keep input visible for manual overrides
                if (!input.value) {
                    input.value = items[0].id;
                }
            } else {
                // No eligible items
                sel.style.display = 'none';
            }
        }

        // Sync dropdown to input
        (function(){
            const sel = document.getElementById('transaction_select');
            const input = document.getElementById('transaction_id');
            if (sel && input) {
                sel.addEventListener('change', () => {
                    input.value = sel.value || '';
                });
            }
            // Also try to fetch list if RFID is prefilled
            const rfidEl = document.getElementById('rfid_id');
            const studEl = document.getElementById('manual_student_id');
            if (rfidEl && studEl && (rfidEl.value || studEl.value)) {
                fetchEligibleTransactions(studEl.value || '', rfidEl.value || '');
            }
        })();

        function loadTransactionDetails() {
            const transactionId = document.getElementById('transaction_id').value;
            if (!transactionId) {
                alert('Please enter a Transaction ID');
                return;
            }

            fetch(`api/get_transaction_details.php?id=${transactionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.transaction) {
                        const txn = data.transaction;
                        document.getElementById('manual_student_id').value = txn.student_id || '';
                        document.getElementById('rfid_id').value = txn.rfid_tag || '';
                        document.getElementById('user_id').value = txn.user_id || '';
                        document.getElementById('equipment_id').value = txn.equipment_id || '';
                        document.getElementById('equipment_name').value = txn.equipment_name || '';
                        alert('Transaction details loaded successfully!');
                    } else {
                        alert(data.message || 'Transaction not found');
                    }
                })
                .catch(error => {
                    console.error('Error loading transaction:', error);
                    alert('Failed to load transaction details');
                });
        }

        function refreshGuidelineSuggestionIndicators() {
            const select = document.getElementById('penalty_guideline');
            const suggestionIdField = document.getElementById('suggested_guideline_id');
            const suggestionBadge = document.getElementById('guidelineSelectionBadge');
            const overrideNote = document.getElementById('guidelineOverrideNote');

            if (!select || !suggestionBadge) {
                return;
            }

            const suggestedId = suggestionIdField ? suggestionIdField.value : '';
            const currentValue = select.value;

            if (!suggestedId) {
                suggestionBadge.style.display = 'none';
                if (overrideNote) {
                    overrideNote.style.display = 'none';
                }
                return;
            }

            if (currentValue === suggestedId) {
                suggestionBadge.textContent = 'Suggested';
                suggestionBadge.classList.remove('manual');
                suggestionBadge.style.display = 'inline-flex';
                if (overrideNote) {
                    overrideNote.style.display = 'none';
                }
            } else if (currentValue) {
                suggestionBadge.textContent = 'Manual override';
                suggestionBadge.classList.add('manual');
                suggestionBadge.style.display = 'inline-flex';
                if (overrideNote) {
                    overrideNote.style.display = 'block';
                }
            } else {
                suggestionBadge.style.display = 'none';
                if (overrideNote) {
                    overrideNote.style.display = 'none';
                }
            }
        }

        function updatePenaltyFromGuideline() {
            const select = document.getElementById('manual_guideline_id');
            const selectedOption = select.options[select.selectedIndex];
            
            if (!selectedOption || !selectedOption.value) {
                document.getElementById('preview_amount').textContent = '0.00';
                document.getElementById('late_return_fields').style.display = 'none';
                document.getElementById('damage_severity_group').style.display = 'none';
                return;
            }

            const penaltyType = selectedOption.dataset.type || '';
            const amount = parseFloat(selectedOption.dataset.amount) || 0;

            // Show/hide fields based on penalty type
            if (penaltyType.toLowerCase().includes('late') || penaltyType.toLowerCase().includes('overdue')) {
                document.getElementById('late_return_fields').style.display = 'grid';
                document.getElementById('damage_severity_group').style.display = 'none';
            } else if (penaltyType.toLowerCase().includes('damage')) {
                document.getElementById('late_return_fields').style.display = 'none';
                document.getElementById('damage_severity_group').style.display = 'block';
            } else {
                document.getElementById('late_return_fields').style.display = 'none';
                document.getElementById('damage_severity_group').style.display = 'none';
            }

            document.getElementById('preview_amount').textContent = amount.toFixed(2);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const guidelineSelect = document.getElementById('penalty_guideline');
            if (guidelineSelect) {
                guidelineSelect.addEventListener('change', refreshGuidelineSuggestionIndicators);
                refreshGuidelineSuggestionIndicators();
            }
        });

        function calculateLatePenalty() {
            const days = parseInt(document.getElementById('days_overdue').value) || 0;
            const rate = parseFloat(document.getElementById('daily_rate').value) || 50;
            const total = days * rate;
            document.getElementById('preview_amount').textContent = total.toFixed(2);
        }

        // Close modal when clicking outside
        window.addEventListener('click', (event) => {
            const createModal = document.getElementById('createPenaltyModal');
            if (event.target === createModal) {
                closeManualPenaltyModal();
            }
        });

        // Sidebar toggle functionality handled by sidebar component
    </script>

    <script>
        function openQuickPenalty() {
            // Redirect to All Transactions page focused on flagged/damage return reviews
            const params = new URLSearchParams({ view: 'flagged' });
            window.location.href = `admin-all-transaction.php?${params.toString()}`;
        }
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
