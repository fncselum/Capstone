<?php
/**
 * Penalty System Class
 * Handles penalty creation, updates, and calculations
 */
class PenaltySystem {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Create a new penalty with guideline support
     */
    public function createPenalty($transaction_id, $rfid_id, $equipment_id, $equipment_name, $penalty_data, $notes = '', $guideline_id = null) {
        $penalty_type = $this->conn->real_escape_string($penalty_data['penalty_type']);
        $penalty_amount = floatval($penalty_data['penalty_amount']);
        $penalty_points = intval($penalty_data['penalty_points'] ?? $penalty_data['days_overdue'] ?? 0);
        $days_overdue = intval($penalty_data['days_overdue'] ?? 0);
        $violation_date = $this->conn->real_escape_string($penalty_data['violation_date']);
        $rfid_id_escaped = $this->conn->real_escape_string($rfid_id);
        $equipment_name_escaped = $this->conn->real_escape_string($equipment_name);
        $notes_escaped = $this->conn->real_escape_string($notes);
        $description = $this->conn->real_escape_string($penalty_data['description'] ?? "$equipment_name_escaped - $notes_escaped");
        $guideline_id_value = $guideline_id ? intval($guideline_id) : 'NULL';
        $admin_id = $_SESSION['admin_id'] ?? 1;
        
        $sql = "INSERT INTO penalties 
                (guideline_id, user_id, transaction_id, penalty_type, penalty_amount, penalty_points, 
                 days_overdue, description, equipment_name, notes, status, date_imposed, violation_date, 
                 last_modified_by, created_at, updated_at) 
                VALUES 
                ($guideline_id_value, '$rfid_id_escaped', $transaction_id, '$penalty_type', $penalty_amount, $penalty_points, 
                 $days_overdue, '$description', '$equipment_name_escaped', '$notes_escaped', 'Pending', NOW(), '$violation_date', 
                 $admin_id, NOW(), NOW())";
        
        return $this->conn->query($sql);
    }
    
    /**
     * Get penalty guideline by ID
     */
    public function getGuidelineById($guideline_id) {
        $guideline_id = intval($guideline_id);
        $sql = "SELECT * FROM penalty_guidelines WHERE id = $guideline_id AND status = 'active'";
        $result = $this->conn->query($sql);
        return $result ? $result->fetch_assoc() : null;
    }
    
    /**
     * Get all active guidelines
     */
    public function getActiveGuidelines() {
        $sql = "SELECT * FROM penalty_guidelines WHERE status = 'active' ORDER BY title ASC";
        $result = $this->conn->query($sql);
        $guidelines = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $guidelines[] = $row;
            }
        }
        return $guidelines;
    }
    
    /**
     * Update penalty status
     */
    public function updatePenaltyStatus($penalty_id, $status, $payment_method = null, $notes = '') {
        $penalty_id = intval($penalty_id);
        $status_escaped = $this->conn->real_escape_string($status);
        $notes_escaped = $this->conn->real_escape_string($notes);
        
        $date_resolved = ($status === 'Resolved') ? "NOW()" : "NULL";
        $resolved_by = ($status === 'Resolved') ? "'" . ($_SESSION['admin_id'] ?? 1) . "'" : "NULL";
        
        $sql = "UPDATE penalties 
                SET status = '$status_escaped', 
                    date_resolved = $date_resolved,
                    resolved_by = $resolved_by,
                    description = CONCAT(description, ' - ', '$notes_escaped'),
                    updated_at = NOW()
                WHERE id = $penalty_id";
        
        return $this->conn->query($sql);
    }
    
    /**
     * Auto-calculate overdue penalties
     */
    public function autoCalculateOverduePenalties() {
        $penalties_created = 0;
        
        // Find overdue transactions that don't have penalties yet
        $sql = "SELECT t.id as transaction_id, t.rfid_id, t.equipment_id, e.name as equipment_name,
                       DATEDIFF(CURDATE(), t.planned_return) as days_overdue
                FROM transactions t
                LEFT JOIN equipment e ON t.equipment_id = e.id
                LEFT JOIN penalties p ON t.id = p.transaction_id AND p.penalty_type = 'Overdue'
                WHERE t.type = 'Borrow' 
                  AND t.planned_return < CURDATE()
                  AND p.id IS NULL";
        
        $result = $this->conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $days_overdue = intval($row['days_overdue']);
                
                // Calculate penalty amount based on days overdue
                if ($days_overdue <= 1) {
                    $penalty_amount = 50.00;
                    $penalty_points = 1;
                } elseif ($days_overdue <= 3) {
                    $penalty_amount = 100.00;
                    $penalty_points = 2;
                } elseif ($days_overdue <= 7) {
                    $penalty_amount = 150.00;
                    $penalty_points = 3;
                } else {
                    $penalty_amount = 250.00;
                    $penalty_points = 5;
                }
                
                $penalty_data = [
                    'penalty_type' => 'Overdue',
                    'penalty_amount' => $penalty_amount,
                    'days_overdue' => $penalty_points,
                    'violation_date' => date('Y-m-d')
                ];
                
                $notes = "Auto-calculated: $days_overdue days overdue";
                
                if ($this->createPenalty(
                    $row['transaction_id'],
                    $row['rfid_id'],
                    $row['equipment_id'],
                    $row['equipment_name'],
                    $penalty_data,
                    $notes
                )) {
                    $penalties_created++;
                }
            }
        }
        
        return $penalties_created;
    }
    
    /**
     * Get penalty by ID
     */
    public function getPenaltyById($penalty_id) {
        $penalty_id = intval($penalty_id);
        $sql = "SELECT * FROM penalties WHERE id = $penalty_id";
        $result = $this->conn->query($sql);
        return $result ? $result->fetch_assoc() : null;
    }
    
    /**
     * Update penalty details
     */
    public function updatePenalty($penalty_id, $penalty_data, $guideline_id = null) {
        $penalty_id = intval($penalty_id);
        $penalty_type = $this->conn->real_escape_string($penalty_data['penalty_type']);
        $penalty_amount = floatval($penalty_data['penalty_amount']);
        $penalty_points = intval($penalty_data['penalty_points'] ?? 0);
        $days_overdue = intval($penalty_data['days_overdue'] ?? 0);
        $description = $this->conn->real_escape_string($penalty_data['description'] ?? '');
        $notes = $this->conn->real_escape_string($penalty_data['notes'] ?? '');
        $guideline_id_value = $guideline_id ? intval($guideline_id) : 'NULL';
        $admin_id = $_SESSION['admin_id'] ?? 1;
        
        $sql = "UPDATE penalties 
                SET guideline_id = $guideline_id_value,
                    penalty_type = '$penalty_type',
                    penalty_amount = $penalty_amount,
                    penalty_points = $penalty_points,
                    days_overdue = $days_overdue,
                    description = '$description',
                    notes = '$notes',
                    last_modified_by = $admin_id,
                    last_modified_at = NOW(),
                    updated_at = NOW()
                WHERE id = $penalty_id";
        
        return $this->conn->query($sql);
    }
    
    /**
     * Delete penalty
     */
    public function deletePenalty($penalty_id) {
        $penalty_id = intval($penalty_id);
        $sql = "DELETE FROM penalties WHERE id = $penalty_id";
        return $this->conn->query($sql);
    }
    
    /**
     * Get penalties with filters and search
     */
    public function getPenalties($filters = []) {
        $where_conditions = [];
        
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $status = $this->conn->real_escape_string($filters['status']);
            $where_conditions[] = "p.status = '$status'";
        }
        
        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $type = $this->conn->real_escape_string($filters['type']);
            $where_conditions[] = "p.penalty_type = '$type'";
        }
        
        if (!empty($filters['search'])) {
            $search = $this->conn->real_escape_string($filters['search']);
            $where_conditions[] = "(p.user_id LIKE '%$search%' OR p.equipment_name LIKE '%$search%' OR p.transaction_id LIKE '%$search%')";
        }
        
        if (!empty($filters['date_from'])) {
            $date_from = $this->conn->real_escape_string($filters['date_from']);
            $where_conditions[] = "p.date_imposed >= '$date_from'";
        }
        
        if (!empty($filters['date_to'])) {
            $date_to = $this->conn->real_escape_string($filters['date_to']);
            $where_conditions[] = "p.date_imposed <= '$date_to'";
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        $sql = "SELECT p.*, 
                       pg.title as guideline_title,
                       pg.penalty_description as guideline_description
                FROM penalties p
                LEFT JOIN penalty_guidelines pg ON p.guideline_id = pg.id
                $where_clause
                ORDER BY p.created_at DESC";
        
        return $this->conn->query($sql);
    }
    
    /**
     * Get penalty statistics
     */
    public function getPenaltyStatistics() {
        $stats = [
            'total_penalties' => 0,
            'pending_penalties' => 0,
            'resolved_penalties' => 0,
            'total_amount' => 0,
            'collected_amount' => 0,
            'pending_amount' => 0
        ];
        
        // Get total penalties
        $total_query = "SELECT COUNT(*) as total FROM penalties";
        $result = $this->conn->query($total_query);
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['total_penalties'] = intval($row['total']);
        }
        
        // Get pending penalties
        $pending_query = "SELECT COUNT(*) as pending, SUM(penalty_amount) as pending_amount 
                         FROM penalties WHERE status = 'Pending'";
        $result = $this->conn->query($pending_query);
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['pending_penalties'] = intval($row['pending']);
            $stats['pending_amount'] = floatval($row['pending_amount'] ?? 0);
        }
        
        // Get resolved penalties
        $resolved_query = "SELECT COUNT(*) as resolved, SUM(penalty_amount) as collected_amount 
                          FROM penalties WHERE status = 'Resolved'";
        $result = $this->conn->query($resolved_query);
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['resolved_penalties'] = intval($row['resolved']);
            $stats['collected_amount'] = floatval($row['collected_amount'] ?? 0);
        }
        
        // Get total amount
        $total_amount_query = "SELECT SUM(penalty_amount) as total_amount FROM penalties";
        $result = $this->conn->query($total_amount_query);
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['total_amount'] = floatval($row['total_amount'] ?? 0);
        }
        
        return $stats;
    }
}
?>
