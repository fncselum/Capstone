<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Check users table columns
$user_columns = [];
$check_users = $conn->query("SHOW TABLES LIKE 'users'");
$users_table_exists = $check_users && $check_users->num_rows > 0;
if ($users_table_exists) {
    $cols_result = $conn->query("SHOW COLUMNS FROM users");
    if ($cols_result) {
        while ($col = $cols_result->fetch_assoc()) { $user_columns[] = $col['Field']; }
    }
}
$user_name_col = '';
if ($users_table_exists) {
    if (in_array('name', $user_columns)) { $user_name_col = 'u.name as user_name,'; }
    elseif (in_array('full_name', $user_columns)) { $user_name_col = 'u.full_name as user_name,'; }
    elseif (in_array('username', $user_columns)) { $user_name_col = 'u.username as user_name,'; }
}
$student_id_col = $users_table_exists ? 'u.student_id' : 't.rfid_id as student_id';
if ($users_table_exists && !in_array('student_id', $user_columns) && in_array('id', $user_columns)) {
    $student_id_col = 'u.id as student_id';
}

// Archived definition: verified OR has penalty record (returned alone is not enough)
$whereArchived = "(t.return_verification_status = 'Verified' OR EXISTS (SELECT 1 FROM penalties p WHERE p.transaction_id = t.id))";

$query = "SELECT t.*, 
                COALESCE(t.transaction_date, t.created_at) AS txn_datetime,
                e.name as equipment_name,
                e.image_path as equipment_image_path,
                $user_name_col
                $student_id_col,
                t.approved_by,
                t.processed_by,
                inv.availability_status AS inventory_status,
                inv.available_quantity AS inventory_available_qty,
                inv.borrowed_quantity AS inventory_borrowed_qty,
                (EXISTS (SELECT 1 FROM penalties p WHERE p.transaction_id = t.id)) AS has_penalty
         FROM transactions t
         LEFT JOIN equipment e ON t.equipment_id = e.rfid_tag
         " . ($users_table_exists ? "LEFT JOIN users u ON t.user_id = u.id" : "") . "
         LEFT JOIN inventory inv ON e.rfid_tag = inv.equipment_id
         WHERE $whereArchived
         ORDER BY t.transaction_date DESC";

$result = $conn->query($query);
$rows = [];
if ($result) {
    while ($r = $result->fetch_assoc()) { $rows[] = $r; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Transactions</title>
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/all-transactions.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .archive-badge { background:#e0e7ff; color:#3730a3; padding:4px 10px; border-radius:12px; font-size:0.8em; font-weight:700; }
        .table-meta { color:#666; font-size:0.85em; }
        .filter-bar { display:flex; gap:12px; align-items:center; justify-content:space-between; margin:14px 0; flex-wrap:wrap; }
        .search-input { display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #e5e7eb; padding:8px 12px; border-radius:8px; min-width:260px; box-shadow:0 1px 2px rgba(0,0,0,0.03); }
        .search-input input { border:none; outline:none; font-size:14px; width:220px; }
        .select-reason { border:1px solid #e5e7eb; background:#fff; padding:8px 10px; border-radius:8px; font-size:14px; }
    </style>
</head>
<body>
<div class="admin-container">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="top-header">
            <h1 class="page-title">Archive Transactions</h1>
        </header>

        <section class="transactions-table">
            <div class="filter-bar">
                <div class="search-input">
                    <i class="fas fa-search" style="color:#6b7280"></i>
                    <input type="text" id="archiveSearch" placeholder="Search ID, student, equipment..." autocomplete="off" />
                </div>
                <div>
                    <select id="reasonFilter" class="select-reason">
                        <option value="all">All reasons</option>
                        <option value="verified">Verified</option>
                        <option value="penalized">Penalized</option>
                    </select>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Equipment</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Verification</th>
                        <th>Archive Reason</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="no-data">No archived transactions found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $t): ?>
                        <tr class="archive-row">
                            <td>#<?= htmlspecialchars($t['id']) ?></td>
                            <td><?= htmlspecialchars($t['txn_datetime'] ?? $t['transaction_date'] ?? $t['created_at']) ?></td>
                            <td>
                                <?php if (!empty($t['user_name'])): ?>
                                    <?= htmlspecialchars($t['user_name']) ?><br>
                                    <small class="table-meta">ID: <?= htmlspecialchars($t['student_id'] ?? '') ?></small>
                                <?php else: ?>
                                    <?= htmlspecialchars($t['student_id'] ?? 'N/A') ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($t['equipment_name'] ?? 'N/A') ?></td>
                            <td><span class="badge <?= strtolower($t['transaction_type']) === 'borrow' ? 'borrow' : 'return' ?>"><?= htmlspecialchars($t['transaction_type']) ?></span></td>
                            <td><?= htmlspecialchars($t['status'] ?? '') ?></td>
                            <td><?= htmlspecialchars($t['return_verification_status'] ?? '') ?></td>
                            <td>
                                <?php if (($t['return_verification_status'] ?? '') === 'Verified'): ?>
                                    <span class="archive-badge reason-badge" data-reason="verified"><i class="fas fa-check-circle"></i> Verified</span>
                                <?php elseif (!empty($t['has_penalty'])): ?>
                                    <span class="archive-badge reason-badge" data-reason="penalized"><i class="fas fa-gavel"></i> Penalized</span>
                                <?php else: ?>
                                    <span class="archive-badge reason-badge" data-reason="archived">Archived</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

<script>
// Live filter for archive table
(function(){
  const search = document.getElementById('archiveSearch');
  const reason = document.getElementById('reasonFilter');
  const rows = Array.from(document.querySelectorAll('tbody .archive-row'));

  function normalize(s){ return (s||'').toString().toLowerCase().replace(/\s+/g,' ').trim(); }
  function matchReason(row){
    const opt = reason.value;
    if (opt === 'all') return true;
    const badge = row.querySelector('.reason-badge');
    const r = badge ? (badge.getAttribute('data-reason')||'') : '';
    return r === opt;
  }
  function matchText(row, q){
    if (!q) return true;
    const cells = Array.from(row.cells).map(td => td.innerText || '');
    const hay = normalize(cells.join(' '));
    return hay.includes(q);
  }
  function applyFilter(){
    const q = normalize(search.value);
    let visible = 0;
    rows.forEach(row => {
      const show = matchReason(row) && matchText(row, q);
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    // Toggle no-data row
    const noData = document.querySelector('.no-data');
    if (noData) noData.parentElement.parentElement.style.display = visible === 0 ? '' : 'none';
  }
  if (search) search.addEventListener('input', applyFilter);
  if (reason) reason.addEventListener('change', applyFilter);
  // Initial
  applyFilter();
})();

</script>
</body>
</html>
