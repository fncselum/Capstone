<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
if (empty($_SESSION['face_verified'])) {
    header('Location: index.php?face=required');
    exit;
}

$student_id = $_SESSION['student_id'] ?? 'Guest';
$penalty_points = $_SESSION['penalty_points'] ?? 0;
$user_id = $_SESSION['user_id'];

// Database connection to check if user has borrowed items
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
$has_borrowed_items = false;
$borrowed_count = 0;

if (!$conn->connect_error) {
    // Check if user has any active borrowed items
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transactions 
                           WHERE user_id = ? AND transaction_type = 'Borrow' 
                           AND status = 'Active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $borrowed_count = $row['count'];
        $has_borrowed_items = $borrowed_count > 0;
    }
    
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Action - Equipment Kiosk</title>
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="scanner-styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="borrow-return.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Background Animation -->
        <div class="background-animation">
            <div class="circle circle-1"></div>
            <div class="circle circle-2"></div>
            <div class="circle circle-3"></div>
        </div>

        <div class="kiosk-content">
            <!-- Header with Logo and Title -->
            <div class="header">
                <div class="header-content">
                    <img src="../uploads/De lasalle ASMC.png" alt="De La Salle ASMC Logo" class="header-logo">
                    <div class="header-text">
                        <h1 class="welcome-title">Select an Action</h1>
                        <p class="subtitle">Choose what you'd like to do</p>
                    </div>
                </div>
            </div>
            
            <!-- User Info Bar -->
            <div class="user-info-bar">
                <div style="display: flex; gap: 30px;">
                    <div class="user-detail">
                        <i class="fas fa-user-circle"></i>
                        <span><strong>Student ID:</strong> <?= htmlspecialchars($student_id) ?></span>
                    </div>
                    <?php if ($has_borrowed_items): ?>
                    <div class="user-detail">
                        <i class="fas fa-box"></i>
                        <span><strong>Borrowed:</strong> <?= $borrowed_count ?> item(s)</span>
                    </div>
                    <?php endif; ?>
                    <div class="user-detail clickable" role="button" title="View penalty overview" tabindex="0" onclick="openPenaltyOverview()" onkeypress="if(event.key==='Enter'){openPenaltyOverview()}">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><strong>Penalty Points:</strong> <?= (int)$penalty_points ?></span>
                    </div>
                </div>
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
                <div class="user-info-hint-row"><span class="penalty-hint" aria-hidden="true">Tip: Click "Penalty Points" to view your penalties.</span></div>
            </div>
            
            <!-- Action Selection Cards -->
            <div class="action-selection">
                <!-- Borrow Card -->
                <div class="action-card borrow" onclick="handleBorrow()">
                    <i class="fas fa-box-open action-icon"></i>
                    <h2 class="action-title">Borrow Equipment</h2>
                    <p class="action-description">Select equipment to borrow from the inventory</p>
                    <span class="action-badge success">
                        <i class="fas fa-check-circle"></i> Available
                    </span>
                </div>
                
                <!-- Return Card -->
                <?php if ($has_borrowed_items): ?>
                <div class="action-card return" onclick="handleReturn()">
                    <i class="fas fa-undo-alt action-icon"></i>
                    <h2 class="action-title">Return Equipment</h2>
                    <p class="action-description">Return your borrowed equipment</p>
                    <span class="action-badge info">
                        <i class="fas fa-info-circle"></i> <?= $borrowed_count ?> item(s) to return
                    </span>
                </div>
                <?php else: ?>
                <div class="action-card return disabled">
                    <i class="fas fa-undo-alt action-icon" style="color: #999;"></i>
                    <h2 class="action-title" style="color: #999;">Return Equipment</h2>
                    <p class="action-description">Return your borrowed equipment</p>
                    <span class="action-badge warning">
                        <i class="fas fa-times-circle"></i> No borrowed items
                    </span>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Footer -->
        <div class="footer">
            <p>&copy; <?= date('Y') ?> De La Salle Andres Soriano Memorial College (ASMC). All rights reserved.</p>
        </div>
    </div>

    <script>
        function handleBorrow() {
            window.location.href = 'borrow.php';
        }
        
        function handleReturn() {
            <?php if ($has_borrowed_items): ?>
            window.location.href = 'return.php';
            <?php endif; ?>
        }
        
        function logout() {
            // Create modern confirmation modal
            const modal = document.createElement('div');
            modal.innerHTML = `
                <div style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    backdrop-filter: blur(5px);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 9999;
                    animation: fadeIn 0.3s ease;
                ">
                    <div style="
                        background: white;
                        border-radius: 25px;
                        padding: 50px 60px;
                        text-align: center;
                        max-width: 500px;
                        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                        animation: slideUp 0.3s ease;
                    ">
                        <div style="
                            width: 80px;
                            height: 80px;
                            background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto 25px;
                            box-shadow: 0 10px 30px rgba(238, 90, 111, 0.3);
                        ">
                            <i class="fas fa-sign-out-alt" style="font-size: 40px; color: white;"></i>
                        </div>
                        <h2 style="
                            font-size: 1.8rem;
                            color: #333;
                            margin-bottom: 15px;
                            font-weight: 700;
                        ">Confirm Logout</h2>
                        <p style="
                            font-size: 1.1rem;
                            color: #666;
                            margin-bottom: 35px;
                            line-height: 1.6;
                        ">Are you sure you want to logout?<br>You will need to scan your RFID again to continue.</p>
                        <div style="display: flex; gap: 15px; justify-content: center;">
                            <button onclick="this.closest('div').parentElement.parentElement.remove()" style="
                                background: #f5f5f5;
                                color: #666;
                                border: none;
                                padding: 15px 35px;
                                border-radius: 12px;
                                font-size: 1rem;
                                font-weight: 600;
                                cursor: pointer;
                                transition: all 0.3s ease;
                            " onmouseover="this.style.background='#e0e0e0'" onmouseout="this.style.background='#f5f5f5'">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button onclick="confirmLogout()" style="
                                background: linear-gradient(135deg, #1e5631, #2d7a45);
                                color: white;
                                border: none;
                                padding: 15px 35px;
                                border-radius: 12px;
                                font-size: 1rem;
                                font-weight: 600;
                                cursor: pointer;
                                transition: all 0.3s ease;
                                box-shadow: 0 4px 15px rgba(30, 86, 49, 0.3);
                            " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(30, 86, 49, 0.4)'" onmouseout="this.style.transform=''; this.style.boxShadow='0 4px 15px rgba(30, 86, 49, 0.3)'">
                                <i class="fas fa-check"></i> Yes, Logout
                            </button>
                        </div>
                    </div>
                </div>
                <style>
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes slideUp {
                        from { transform: translateY(50px); opacity: 0; }
                        to { transform: translateY(0); opacity: 1; }
                    }
                    @keyframes spin {
                        from { transform: rotate(0deg); }
                        to { transform: rotate(360deg); }
                    }
                </style>
            `;
            document.body.appendChild(modal);
        }

        function openPenaltyOverview() {
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px);
                display:flex; align-items:center; justify-content:center; z-index:9999; animation: fadeIn 0.2s ease;`;

            const modal = document.createElement('div');
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('aria-labelledby', 'penaltyTitle');
            modal.style.cssText = `
                background:#fff; border-radius:20px; padding:28px 30px; width:92%; max-width:720px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideUp 0.2s ease;`;

            modal.innerHTML = `
                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <i class="fas fa-scale-balanced" style="color:#1e5631; font-size: 20px;"></i>
                        <h2 id="penaltyTitle" style="margin:0; font-size:1.3rem; color:#333; font-weight:700;">Penalty Overview</h2>
                    </div>
                    <button id="closePenaltyModal" style="background:#f5f5f5; color:#666; border:none; padding:8px 12px; border-radius:10px; font-weight:600; cursor:pointer;">Close</button>
                </div>
                <p style="margin:4px 0 14px; color:#555; font-size:0.98rem;">Current penalty points: <strong><?= (int)$penalty_points ?></strong></p>
                <div id="penaltySummaryContent" style="
                    display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:12px;">
                    <div id="tileOutstanding" style="background:#f4fbf6; border:1px solid #e8f5e9; border-radius:14px; padding:14px;">
                        <div style="font-size:0.85rem; color:#577; margin-bottom:6px;">Outstanding Amount</div>
                        <div id="sumOutstanding" style="font-size:1.2rem; font-weight:700; color:#1e5631;">₱0.00</div>
                    </div>
                    <div style="background:#f4fbf6; border:1px solid #e8f5e9; border-radius:14px; padding:14px;">
                        <div style="font-size:0.85rem; color:#577; margin-bottom:6px;">Active Penalties</div>
                        <div id="sumActive" style="font-size:1.2rem; font-weight:700; color:#1e5631;">0</div>
                    </div>
                    <div style="background:#f6f9fb; border:1px solid #e9f0f6; border-radius:14px; padding:14px;">
                        <div style="font-size:0.85rem; color:#577; margin-bottom:6px;">Resolved Cases</div>
                        <div id="sumResolved" style="font-size:1.2rem; font-weight:700; color:#1e5631;">0</div>
                    </div>
                    <div style="background:#f6f9fb; border:1px solid #e9f0f6; border-radius:14px; padding:14px;">
                        <div style="font-size:0.85rem; color:#577; margin-bottom:6px;">Latest Penalty</div>
                        <div id="sumLatest" style="font-size:1.1rem; font-weight:700; color:#234;">—</div>
                    </div>
                </div>
                <div id="penaltyNotice" style="
                    display:none; margin-top:14px; background:#fff4e6; border:1px solid #f3d6b3; color:#8a5a2b;
                    border-radius:12px; padding:12px; font-size:0.95rem;">
                    <i class="fas a-info-circle"></i> Please settle outstanding penalties at the admin desk before borrowing again.
                </div>
                <div id="activePenaltiesSection" style="margin-top:16px;">
                    <h3 style="margin:0 0 8px; font-size:1.05rem; color:#234;">Active Penalties</h3>
                    <div id="activePenaltiesList" style="display:flex; flex-direction:column; gap:8px;"></div>
                    <div id="noActivePenalties" style="display:none; color:#667; font-size:0.95rem;">No active penalties.</div>
                </div>
            `;

            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            // Close events
            modal.querySelector('#closePenaltyModal').addEventListener('click', () => overlay.remove());
            overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });

            // Load summary
            fetch('api/get_penalty_summary.php', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const s = data.summary || {};
                    const peso = (v) => `₱${Number(v || 0).toFixed(2)}`;
                    const fmtDate = (d) => {
                        if (!d) return '—';
                        const dt = new Date(d.replace(' ', 'T'));
                        if (isNaN(dt.getTime())) return '—';
                        return dt.toLocaleDateString(undefined, { month: 'short', day: '2-digit', year: 'numeric' });
                    };
                    document.getElementById('sumOutstanding').textContent = peso(s.outstanding_amount);
                    document.getElementById('sumActive').textContent = s.active_penalties ?? 0;
                    document.getElementById('sumResolved').textContent = s.resolved_cases ?? 0;
                    document.getElementById('sumLatest').textContent = fmtDate(s.latest_penalty);

                    const tileOutstanding = document.getElementById('tileOutstanding');
                    if (tileOutstanding) {
                        if (!s.has_outstanding || (s.outstanding_amount || 0) <= 0) {
                            tileOutstanding.style.display = 'none';
                        } else {
                            tileOutstanding.style.display = '';
                        }
                    }

                    const notice = document.getElementById('penaltyNotice');
                    if ((s.active_penalties || 0) > 0 || (s.outstanding_amount || 0) > 0) {
                        notice.style.display = 'block';
                    } else {
                        notice.style.display = 'none';
                    }
                })
                .catch(() => {
                    // Leave defaults if fetch fails
                });

            // Load active penalties list
            const fmtDateTime = (d) => {
                if (!d) return '—';
                const dt = new Date(d.replace(' ', 'T'));
                if (isNaN(dt.getTime())) return '—';
                return dt.toLocaleString(undefined, { month: 'short', day: '2-digit', year: 'numeric' });
            };
            const peso = (v) => `₱${Number(v || 0).toFixed(2)}`;

            function openPenaltyDetail(p) {
                const detail = document.createElement('div');
                detail.style.cssText = 'position:fixed; inset:0; background:rgba(0,0,0,0.65); display:flex; align-items:center; justify-content:center; z-index:10000;';
                const box = document.createElement('div');
                box.style.cssText = 'background:#fff; border-radius:14px; max-width:560px; width:92%; padding:20px;';
                box.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <h3 style="margin:0; font-size:1.1rem; color:#1e293b;">Penalty #${p.id}</h3>
                        <button style="border:none; background:#f3f4f6; padding:6px 10px; border-radius:8px; cursor:pointer;">Close</button>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div><div style="color:#64748b; font-size:0.85rem;">Type</div><div style="font-weight:700;">${p.penalty_type || '—'}</div></div>
                        <div><div style="color:#64748b; font-size:0.85rem;">Amount</div><div style="font-weight:700;">${peso(p.amount)}</div></div>
                        <div><div style="color:#64748b; font-size:0.85rem;">Equipment</div><div style="font-weight:700;">${(p.equipment_name || p.equipment_id || '—')}</div></div>
                        <div><div style="color:#64748b; font-size:0.85rem;">Severity</div><div style="font-weight:700; text-transform:capitalize;">${p.damage_severity || '—'}</div></div>
                        <div><div style="color:#64748b; font-size:0.85rem;">Status</div><div style="font-weight:700;">${p.status || '—'}</div></div>
                        <div><div style="color:#64748b; font-size:0.85rem;">Date Imposed</div><div style="font-weight:700;">${fmtDateTime(p.date_imposed || p.created_at)}</div></div>
                    </div>
                    <div style="margin-top:10px;">
                        <div style="color:#64748b; font-size:0.85rem;">Description</div>
                        <div style="white-space:pre-wrap;">${(p.description || '').toString().trim() || '—'}</div>
                    </div>
                    ${p.damage_notes ? `<div style="margin-top:10px;"><div style="color:#64748b; font-size:0.85rem;">Admin Notes</div><div style="white-space:pre-wrap;">${p.damage_notes}</div></div>` : ''}
                `;
                const closeBtn = box.querySelector('button');
                closeBtn.addEventListener('click', () => detail.remove());
                detail.addEventListener('click', (e) => { if (e.target === detail) detail.remove(); });
                detail.appendChild(box);
                document.body.appendChild(detail);
            }

            fetch('api/get_penalties_list.php', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    const list = document.getElementById('activePenaltiesList');
                    const empty = document.getElementById('noActivePenalties');
                    if (!data.success) {
                        empty.style.display = '';
                        return;
                    }
                    const items = data.penalties || [];
                    if (items.length === 0) {
                        empty.style.display = '';
                        return;
                    }
                    empty.style.display = 'none';
                    items.forEach(p => {
                        const row = document.createElement('button');
                        row.type = 'button';
                        row.style.cssText = 'display:flex; justify-content:space-between; align-items:center; padding:10px 12px; border:1px solid #e5e7eb; background:#fff; border-radius:10px; cursor:pointer; text-align:left;';
                        row.innerHTML = `
                            <div>
                                <div style="font-weight:700; color:#111827;">${p.penalty_type || 'Penalty'} · ${p.equipment_name || p.equipment_id || ''}</div>
                                <div style="font-size:0.85rem; color:#6b7280;">Imposed: ${fmtDateTime(p.date_imposed || p.created_at)} · Status: ${p.status || '—'}</div>
                            </div>
                            <div style="font-weight:700; color:#14532d;">${peso(p.amount)}</div>
                        `;
                        row.addEventListener('click', () => openPenaltyDetail(p));
                        list.appendChild(row);
                    });
                })
                .catch(() => {
                    const empty = document.getElementById('noActivePenalties');
                    empty.style.display = '';
                });
        }
        
        function confirmLogout() {
            // Show modern loading overlay
            document.body.innerHTML = `
                <div style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(135deg, #1e5631, #2d7a45);
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    z-index: 9999;
                    animation: fadeIn 0.3s ease;
                ">
                    <div style="text-align: center;">
                        <div style="
                            width: 120px;
                            height: 120px;
                            border: 8px solid rgba(255, 255, 255, 0.2);
                            border-top-color: white;
                            border-radius: 50%;
                            margin: 0 auto 30px;
                            animation: spin 1s linear infinite;
                        "></div>
                        <h2 style="
                            font-size: 2.5rem;
                            color: white;
                            margin-bottom: 15px;
                            font-weight: 700;
                            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
                        ">Logging Out...</h2>
                        <p style="
                            font-size: 1.3rem;
                            color: rgba(255, 255, 255, 0.9);
                            font-weight: 500;
                        ">Thank you for using the kiosk</p>
                        <div style="
                            margin-top: 30px;
                            display: flex;
                            gap: 8px;
                            justify-content: center;
                        ">
                            <div style="width: 12px; height: 12px; background: white; border-radius: 50%; animation: bounce 1s infinite 0s;"></div>
                            <div style="width: 12px; height: 12px; background: white; border-radius: 50%; animation: bounce 1s infinite 0.2s;"></div>
                            <div style="width: 12px; height: 12px; background: white; border-radius: 50%; animation: bounce 1s infinite 0.4s;"></div>
                        </div>
                    </div>
                </div>
                <style>
                    @keyframes spin {
                        from { transform: rotate(0deg); }
                        to { transform: rotate(360deg); }
                    }
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes bounce {
                        0%, 100% { transform: translateY(0); opacity: 0.4; }
                        50% { transform: translateY(-15px); opacity: 1; }
                    }
                </style>
            `;
            setTimeout(() => {
                window.location.href = 'logout.php';
            }, 1500);
        }
        
        // Auto-logout after 5 minutes of inactivity
        let inactivityTime = function () {
            let time;
            window.onload = resetTimer;
            document.onmousemove = resetTimer;
            document.onkeypress = resetTimer;
            document.onclick = resetTimer;
            document.ontouchstart = resetTimer;

            function logout() {
                window.location.href = 'logout.php';
            }

            function resetTimer() {
                clearTimeout(time);
                time = setTimeout(logout, 300000); // 5 minutes
            }
        };
        
        inactivityTime();
    </script>
</body>
</html>
