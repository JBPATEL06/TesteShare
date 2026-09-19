<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('getDB')) {
    require_once __DIR__ . '/../../db.php';
}
if (!function_exists('view')) {
    require_once __DIR__ . '/../../config.php';
}
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'edit_user') {
        $userId = intval($_POST['user_id'] ?? 0);
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $status = trim($_POST['status'] ?? '');
        if ($userId && $fullname && $email && $status) {
            $stmt = $db->prepare("UPDATE users SET fullname = ?, email = ?, status = ? WHERE id = ?");
            $stmt->execute([$fullname, $email, $status, $userId]);
        }
        exit;
    } elseif ($action === 'toggle_status') {
        $userId = intval($_POST['user_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        if ($userId && $status) {
            $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$status, $userId]);
        }
        exit;
    }
}

// Fetch all consumers (role = 'customer')
$users = $db->query("
    SELECT u.*, COUNT(o.id) as lifetime_orders
    FROM users u
    LEFT JOIN orders o ON u.id = o.customer_id AND o.payment_status = 'Paid'
    WHERE u.role = 'customer'
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();

$pageTitle = 'Consumer Accounts Management';
$activeNav = 'users';
view('partials/admin_header', get_defined_vars());
view('partials/admin_sidebar', get_defined_vars());
?>

<div class="flex-grow p-8 overflow-y-auto">
    <!-- Content Canvas -->
    <div class="max-w-6xl mx-auto space-y-8">
        
        <!-- Header & Action Row -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-outline pb-6">
            <div>
                <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-1">Registered Users</h2>
                <p class="text-body-md text-on-surface-variant mb-0">Suspend, activate, edit profiles, or review order metrics for consumers.</p>
            </div>
            <div class="flex gap-3 w-full md:w-auto">
                <input id="userSearchInput" oninput="filterUsersTable()" type="text" class="bg-surface border border-outline rounded-xl px-4 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/40 w-full md:w-64" placeholder="Search by name or email...">
                <select id="statusFilter" onchange="filterUsersTable()" class="bg-surface border border-outline rounded-xl px-4 py-2.5 text-sm text-on-surface">
                    <option value="all">All Statuses</option>
                    <option value="Active">Active Only</option>
                    <option value="Suspended">Suspended Only</option>
                    <option value="Banned">Banned Only</option>
                </select>
            </div>
        </div>

        <!-- Users Roster Table -->
        <div class="bg-surface-container rounded-2xl border border-outline shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-outline bg-surface-container-low font-bold text-xs uppercase tracking-wider text-on-surface-variant">
                            <th class="py-4 px-6">Consumer Details</th>
                            <th class="py-4 px-6">Joined Date</th>
                            <th class="py-4 px-6">Lifetime Orders</th>
                            <th class="py-4 px-6">Status</th>
                            <th class="py-4 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <?php foreach ($users as $uRow): ?>
                            <?php 
                            $nameParts = explode(' ', $uRow['fullname']);
                            $initials = strtoupper(substr($nameParts[0] ?? '', 0, 1) . substr($nameParts[1] ?? 'U', 0, 1));
                            if (empty($initials)) {
                                $initials = 'US';
                            }
                            $createdDate = date('Y-m-d', strtotime($uRow['created_at']));
                            $status = $uRow['status'];
                            ?>
                            <tr class="border-b border-outline/30 hover:bg-surface-container-high transition-colors align-middle user-row" id="user-row-<?php echo $uRow['id']; ?>" data-status="<?php echo $status; ?>">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center font-bold text-primary"><?php echo htmlspecialchars($initials); ?></div>
                                        <div>
                                            <div class="text-sm font-bold text-on-surface font-name"><?php echo htmlspecialchars($uRow['fullname']); ?></div>
                                            <div class="text-xs text-on-surface-variant font-email"><?php echo htmlspecialchars($uRow['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6 font-mono text-xs"><?php echo $createdDate; ?></td>
                                <td class="py-4 px-6 font-mono text-xs font-bold"><?php echo number_format($uRow['lifetime_orders']); ?> Orders</td>
                                <td class="py-4 px-6">
                                    <?php if ($status === 'Active'): ?>
                                        <span class="status-badge text-[10px] font-bold px-2.5 py-1 bg-green-500/10 text-green-400 border border-green-500/20 rounded-full uppercase tracking-wider">Active</span>
                                    <?php elseif ($status === 'Suspended'): ?>
                                        <span class="status-badge text-[10px] font-bold px-2.5 py-1 bg-yellow-500/10 text-yellow-400 border border-yellow-500/20 rounded-full uppercase tracking-wider">Suspended</span>
                                    <?php else: ?>
                                        <span class="status-badge text-[10px] font-bold px-2.5 py-1 bg-red-500/10 text-red-400 border border-red-500/20 rounded-full uppercase tracking-wider">Banned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6 text-right space-x-2">
                                    <button onclick="openEditUserModal('<?php echo $uRow['id']; ?>', '<?php echo addslashes($uRow['fullname']); ?>', '<?php echo addslashes($uRow['email']); ?>')" class="px-3 py-1.5 bg-surface-container-highest border border-outline hover:border-primary text-on-surface rounded-lg text-xs font-bold cursor-pointer transition-all">Edit</button>
                                    <button onclick="toggleUserStatus('<?php echo $uRow['id']; ?>', '<?php echo addslashes($uRow['fullname']); ?>', '<?php echo $status; ?>')" class="status-btn px-3 py-1.5 border <?php echo $status === 'Active' ? 'border-red-500/30 text-red-400 bg-red-500/5 hover:bg-red-500/10' : 'border-green-500/30 text-green-400 bg-green-500/5 hover:bg-green-500/10'; ?> rounded-lg text-xs font-bold cursor-pointer transition-all">
                                        <?php echo $status === 'Active' ? 'Suspend' : 'Activate'; ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="5" class="py-4 text-center text-on-surface-variant italic">No consumer users registered.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- ================= MODALS ================= -->

<!-- Edit User Modal -->
<div id="editUserModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300">
    <div class="bg-surface-container border border-outline rounded-2xl w-full max-w-md p-6 shadow-2xl scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center border-b border-outline pb-3 mb-4">
            <h3 class="font-bold text-on-surface text-base mb-0">Modify Consumer Profile</h3>
            <button onclick="closeModal('editUserModal')" class="bg-transparent border-0 text-on-surface-variant hover:text-on-surface cursor-pointer p-0">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <input type="hidden" id="editUserId">
        <div class="space-y-4">
            <div>
                <label class="text-xs text-on-surface-variant font-bold block mb-1">Full Name</label>
                <input type="text" id="editUserName" class="w-full bg-surface border border-outline rounded-xl px-3 py-2.5 text-sm text-on-surface focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="text-xs text-on-surface-variant font-bold block mb-1">Email Address</label>
                <input type="email" id="editUserEmail" class="w-full bg-surface border border-outline rounded-xl px-3 py-2.5 text-sm text-on-surface focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="text-xs text-on-surface-variant font-bold block mb-1">System Account Status</label>
                <select id="editUserStatus" class="w-full bg-surface border border-outline rounded-xl px-3 py-2.5 text-sm text-on-surface">
                    <option value="Active">Active</option>
                    <option value="Suspended">Suspended</option>
                    <option value="Banned">Banned (Revoke system login)</option>
                </select>
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button onclick="closeModal('editUserModal')" class="px-4 py-2 border border-outline text-on-surface bg-transparent font-bold text-xs uppercase tracking-wider rounded-lg hover:bg-surface-container-highest cursor-pointer">
                Cancel
            </button>
            <button onclick="submitEditUser()" class="px-4 py-2 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg border-0 shadow-md cursor-pointer hover:brightness-110 active:scale-95 transition-all">
                Save Details
            </button>
        </div>
    </div>
</div>

<script>
    // Toggle active / suspend states
    function toggleUserStatus(userId, name, currentStatus) {
        const newStatus = currentStatus === 'Active' ? 'Suspended' : 'Active';
        if (confirm(`Are you sure you want to change the status of ${name} to ${newStatus}?`)) {
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('user_id', userId);
            formData.append('status', newStatus);
            fetch('', { method: 'POST', body: formData })
                .then(() => window.location.reload());
        }
    }

    // Modal triggers
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.querySelector('.scale-95')?.classList.remove('scale-95');
            }, 10);
        }
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('opacity-0');
            modal.querySelector('.max-w-md')?.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
    }

    function openEditUserModal(userId, name, email) {
        const row = document.getElementById(`user-row-${userId}`);
        if (!row) return;

        document.getElementById('editUserId').value = userId;
        document.getElementById('editUserName').value = name;
        document.getElementById('editUserEmail').value = email;
        document.getElementById('editUserStatus').value = row.getAttribute('data-status');

        openModal('editUserModal');
    }

    function submitEditUser() {
        const id = document.getElementById('editUserId').value;
        const name = document.getElementById('editUserName').value;
        const email = document.getElementById('editUserEmail').value;
        const status = document.getElementById('editUserStatus').value;

        if (!name || !email) {
            alert('All credentials must be valid.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'edit_user');
        formData.append('user_id', id);
        formData.append('fullname', name);
        formData.append('email', email);
        formData.append('status', status);

        fetch('', { method: 'POST', body: formData })
            .then(() => window.location.reload());
    }

    // Filter roster
    function filterUsersTable() {
        const query = document.getElementById('userSearchInput').value.toLowerCase();
        const status = document.getElementById('statusFilter').value;
        const rows = document.querySelectorAll('.user-row');

        rows.forEach(row => {
            const name = row.querySelector('.font-name').textContent.toLowerCase();
            const email = row.querySelector('.font-email').textContent.toLowerCase();
            const rowStatus = row.getAttribute('data-status');

            const matchesQuery = name.includes(query) || email.includes(query);
            const matchesStatus = status === 'all' || rowStatus === status;

            if (matchesQuery && matchesStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>

<?php view('partials/admin_footer'); ?>
