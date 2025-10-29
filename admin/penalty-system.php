<?php

class PenaltySystem
{
    public const DEFAULT_DAILY_RATE = 10.00;

    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Create a penalty record following IMC policy (no payment processing)
     */
    public function createPenalty(array $data): bool
    {
        $transactionId = isset($data['transaction_id']) ? (int)$data['transaction_id'] : null;
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
        $borrowerIdentifier = trim($data['borrower_identifier'] ?? '');
        $penaltyType = trim($data['penalty_type'] ?? 'Late Return');
        $guidelineId = isset($data['guideline_id']) ? (int)$data['guideline_id'] : null;
        $equipmentId = trim($data['equipment_id'] ?? '');
        $equipmentName = trim($data['equipment_name'] ?? '');
        $damageSeverity = $data['damage_severity'] ?? null;
        $damageNotes = trim($data['damage_notes'] ?? '');
        $detectedIssues = trim($data['detected_issues'] ?? '');
        $similarityScore = isset($data['similarity_score']) ? (float)$data['similarity_score'] : null;
        $comparisonSummary = trim($data['comparison_summary'] ?? '');
        $adminAssessment = trim($data['admin_assessment'] ?? '');
        $description = trim($data['description'] ?? '');
        $amountNote = trim($data['amount_note'] ?? '');

        $daysOverdue = isset($data['days_overdue']) ? max(0, (int)$data['days_overdue']) : 0;
        $dailyRate = isset($data['daily_rate']) ? round((float)$data['daily_rate'], 2) : self::DEFAULT_DAILY_RATE;
        $penaltyAmount = isset($data['penalty_amount']) ? round((float)$data['penalty_amount'], 2) : 0.00;
        $amountOwed = isset($data['amount_owed']) ? round((float)$data['amount_owed'], 2) : $penaltyAmount;

        $notes = trim($data['notes'] ?? '');
        $adminId = $_SESSION['admin_id'] ?? null;

        if (!$userId && $borrowerIdentifier !== '') {
            $userId = $this->resolveUserId($borrowerIdentifier);
        }

        if ($transactionId === null || $penaltyType === '') {
            return false;
        }

        if ($description === '') {
            $description = sprintf('%s penalty recorded', $penaltyType);
        }

        if ($penaltyAmount <= 0 && $amountOwed > 0) {
            $penaltyAmount = $amountOwed;
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO penalties (
                user_id,
                transaction_id,
                guideline_id,
                equipment_id,
                equipment_name,
                penalty_type,
                penalty_amount,
                amount_owed,
                amount_note,
                days_overdue,
                daily_rate,
                damage_severity,
                damage_notes,
                description,
                notes,
                status,
                imposed_by,
                date_imposed,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, NOW(), NOW(), NOW())"
        );

        if (!$stmt) {
            error_log('PenaltySystem::createPenalty prepare failed: ' . $this->conn->error);
            return false;
        }

        $stmt->bind_param(
            'iiisssddsidssssi',
            $userId,
            $transactionId,
            $guidelineId,
            $equipmentId,
            $equipmentName,
            $penaltyType,
            $penaltyAmount,
            $amountOwed,
            $amountNote,
            $daysOverdue,
            $dailyRate,
            $damageSeverity,
            $damageNotes,
            $description,
            $notes,
            $adminId
        );

        if (!$stmt->execute()) {
            error_log('PenaltySystem::createPenalty execute failed: ' . $stmt->error);
            $stmt->close();
            return false;
        }

        $penaltyId = $stmt->insert_id;
        $stmt->close();

        if ($penaltyId && ($detectedIssues !== '' || $similarityScore !== null || $comparisonSummary !== '' || $adminAssessment !== '')) {
            $assessment = $this->conn->prepare(
                "INSERT INTO penalty_damage_assessments (
                    penalty_id,
                    detected_issues,
                    similarity_score,
                    comparison_summary,
                    admin_assessment
                ) VALUES (?, ?, ?, ?, ?)"
            );

            if ($assessment) {
                $assessment->bind_param(
                    'isdss',
                    $penaltyId,
                    $detectedIssues,
                    $similarityScore,
                    $comparisonSummary,
                    $adminAssessment
                );
                $assessment->execute();
                $assessment->close();
            }
        }

        // Update transaction status to indicate penalty has been issued
        if ($penaltyId && $transactionId) {
            $updateTxn = $this->conn->prepare(
                "UPDATE transactions 
                 SET return_verification = 'Penalty Issued',
                     penalty_id = ?
                 WHERE id = ?"
            );
            
            if ($updateTxn) {
                $updateTxn->bind_param('ii', $penaltyId, $transactionId);
                $updateTxn->execute();
                $updateTxn->close();
            }
        }

        return true;
    }

    public function getGuidelineById(int $guidelineId): ?array
    {
        $query = $this->conn->prepare(
            "SELECT id, title AS guideline_name, penalty_type, penalty_amount, penalty_points, penalty_description, document_path, status 
             FROM penalty_guidelines 
             WHERE id = ? AND status = 'active'"
        );
        if (!$query) {
            return null;
        }
        $query->bind_param('i', $guidelineId);
        $query->execute();
        $result = $query->get_result();
        $guideline = $result ? $result->fetch_assoc() : null;
        $query->close();
        return $guideline ?: null;
    }

    public function getGuidelineByType(string $penaltyType): ?array
    {
        $query = $this->conn->prepare(
            "SELECT *
             FROM penalty_guidelines
             WHERE status = 'active' AND penalty_type = ?
             ORDER BY updated_at DESC
             LIMIT 1"
        );
        if (!$query) {
            return null;
        }
        $query->bind_param('s', $penaltyType);
        $query->execute();
        $result = $query->get_result();
        $guideline = $result ? $result->fetch_assoc() : null;
        $query->close();
        return $guideline ?: null;
    }

    public function getActiveGuidelines(): array
    {
        $result = $this->conn->query(
            "SELECT id, title AS guideline_name, penalty_type, penalty_amount, penalty_points, penalty_description
             FROM penalty_guidelines
             WHERE status = 'active'
             ORDER BY title ASC"
        );

        $guidelines = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $guidelines[] = $row;
            }
        }

        return $guidelines;
    }

    public function updatePenaltyStatus(int $penaltyId, array $payload): bool
    {
        $status = $payload['status'] ?? 'Pending';
        $notes = trim($payload['notes'] ?? '');
        $resolutionType = $payload['resolution_type'] ?? null;
        $resolutionNotes = trim($payload['resolution_notes'] ?? '');

        $dateResolved = null;
        $resolvedBy = null;

        if ($status === 'Resolved') {
            $dateResolved = date('Y-m-d H:i:s');
            $resolvedBy = $_SESSION['admin_id'] ?? null;
        }

        $sql = "UPDATE penalties SET
                    status = ?,
                    date_resolved = ?,
                    resolution_type = ?,
                    resolution_notes = ?,
                    resolved_by = ?,
                    notes = CASE WHEN ? <> '' THEN CONCAT_WS('\n', notes, ?) ELSE notes END,
                    updated_at = NOW()
                WHERE id = ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log('PenaltySystem::updatePenaltyStatus prepare failed: ' . $this->conn->error);
            return false;
        }

        $stmt->bind_param(
            'ssssissi',
            $status,
            $dateResolved,
            $resolutionType,
            $resolutionNotes,
            $resolvedBy,
            $notes,
            $notes,
            $penaltyId
        );

        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function autoCalculateOverduePenalties(): int
    {
        $guideline = $this->getGuidelineByType('Late Return');
        $dailyRate = $guideline ? (float)$guideline['penalty_amount'] : self::DEFAULT_DAILY_RATE;

        $sql = "SELECT
                    t.id AS transaction_id,
                    t.user_id,
                    t.rfid_id,
                    t.equipment_id,
                    e.name AS equipment_name,
                    DATEDIFF(CURDATE(), t.expected_return_date) AS days_overdue
                FROM transactions t
                LEFT JOIN penalties p ON p.transaction_id = t.id AND p.penalty_type = 'Late Return'
                LEFT JOIN equipment e ON e.id = t.equipment_id
                WHERE t.expected_return_date IS NOT NULL
                  AND t.status IN ('Overdue', 'Active')
                  AND t.expected_return_date < CURDATE()
                  AND p.id IS NULL";

        $result = $this->conn->query($sql);
        if (!$result) {
            return 0;
        }

        $created = 0;
        while ($row = $result->fetch_assoc()) {
            $daysOverdue = max(0, (int)$row['days_overdue']);
            if ($daysOverdue <= 0) {
                continue;
            }

            $amount = $dailyRate * $daysOverdue;

            $this->createPenalty([
                'transaction_id' => (int)$row['transaction_id'],
                'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
                'borrower_identifier' => $row['rfid_id'] ?? '',
                'penalty_type' => 'Late Return',
                'guideline_id' => $guideline['id'] ?? null,
                'equipment_id' => (string)($row['equipment_id'] ?? ''),
                'equipment_name' => $row['equipment_name'] ?? '',
                'days_overdue' => $daysOverdue,
                'daily_rate' => $dailyRate,
                'penalty_amount' => $amount,
                'amount_owed' => $amount,
                'amount_note' => sprintf('%d day(s) × ₱%0.2f per day', $daysOverdue, $dailyRate),
                'description' => 'Late return penalty',
                'notes' => sprintf('Auto-generated: %d day(s) overdue', $daysOverdue)
            ]) && $created++;
        }

        $result->free();
        return $created;
    }

    public function getPenaltyById(int $penaltyId): ?array
    {
        $sql = "SELECT
                    p.*,
                    da.detected_issues,
                    da.similarity_score,
                    da.comparison_summary,
                    da.admin_assessment
                FROM penalties p
                LEFT JOIN penalty_damage_assessments da ON da.penalty_id = p.id
                WHERE p.id = ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $penaltyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $penalty = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $penalty ?: null;
    }

    public function getPenalties(array $filters = [])
    {
        $conditions = [];
        $params = [];
        $types = '';

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $conditions[] = 'p.status = ?';
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $conditions[] = 'p.penalty_type = ?';
            $params[] = $filters['type'];
            $types .= 's';
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $conditions[] = '(u.student_id LIKE ? OR u.rfid_tag LIKE ? OR CAST(p.transaction_id AS CHAR) LIKE ? OR p.equipment_name LIKE ?)';
            array_push($params, $search, $search, $search, $search);
            $types .= 'ssss';
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'DATE(p.date_imposed) >= ?';
            $params[] = $filters['date_from'];
            $types .= 's';
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'DATE(p.date_imposed) <= ?';
            $params[] = $filters['date_to'];
            $types .= 's';
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $sql = "SELECT
                    p.id,
                    p.transaction_id,
                    p.user_id,
                    u.student_id,
                    u.rfid_tag,
                    p.equipment_name,
                    p.penalty_type,
                    p.amount_owed,
                    p.amount_note,
                    p.damage_severity,
                    p.days_overdue,
                    p.daily_rate,
                    p.status,
                    p.date_imposed,
                    p.date_resolved,
                    p.resolution_type,
                    p.resolution_notes,
                    da.similarity_score,
                    da.detected_issues
                FROM penalties p
                LEFT JOIN users u ON u.id = p.user_id
                LEFT JOIN penalty_damage_assessments da ON da.penalty_id = p.id
                $where
                ORDER BY p.date_imposed DESC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        if ($params) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt->get_result();
    }

    public function getPenaltyStatistics(): array
    {
        $stats = [
            'total_penalties' => 0,
            'total_amount_owed' => 0.00,
            'by_status' => [
                'Pending' => ['count' => 0, 'amount' => 0.00],
                'Under Review' => ['count' => 0, 'amount' => 0.00],
                'Resolved' => ['count' => 0, 'amount' => 0.00],
                'Cancelled' => ['count' => 0, 'amount' => 0.00],
            ],
            'damage_cases' => 0,
            'lost_cases' => 0
        ];

        $totals = $this->conn->query("SELECT COUNT(*) AS total, SUM(amount_owed) AS amount FROM penalties");
        if ($totals && ($row = $totals->fetch_assoc())) {
            $stats['total_penalties'] = (int)$row['total'];
            $stats['total_amount_owed'] = (float)$row['amount'];
        }

        $statusTotals = $this->conn->query("SELECT status, COUNT(*) AS total, SUM(amount_owed) AS amount FROM penalties GROUP BY status");
        if ($statusTotals) {
            while ($row = $statusTotals->fetch_assoc()) {
                $status = $row['status'];
                if (!isset($stats['by_status'][$status])) {
                    $stats['by_status'][$status] = ['count' => 0, 'amount' => 0.00];
                }
                $stats['by_status'][$status]['count'] = (int)$row['total'];
                $stats['by_status'][$status]['amount'] = (float)$row['amount'];
            }
        }

        $damageCount = $this->conn->query("SELECT COUNT(*) AS total FROM penalties WHERE penalty_type = 'Damage'");
        if ($damageCount && ($row = $damageCount->fetch_assoc())) {
            $stats['damage_cases'] = (int)$row['total'];
        }

        $lostCount = $this->conn->query("SELECT COUNT(*) AS total FROM penalties WHERE penalty_type = 'Loss'");
        if ($lostCount && ($row = $lostCount->fetch_assoc())) {
            $stats['lost_cases'] = (int)$row['total'];
        }

        return $stats;
    }

    private function resolveUserId(string $identifier): ?int
    {
        if ($identifier === '') {
            return null;
        }

        $sql = "SELECT id FROM users WHERE student_id = ? OR rfid_tag = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $user ? (int)$user['id'] : null;
    }
}

