<?php
// users.php - User & Team Lead Management Portal

require_once __DIR__ . '/auth.php';
checkAuth('user-management');

$user = getCurrentUser();
$db = getDbConnection();

$pageTitle = "User & Team Lead Management Portal";
require_once __DIR__ . '/header.php';

// Fetch all roles
$roles = $db->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();

// Fetch department list
$departments = ['Development', 'Management', 'Sales', 'Design / UI/UX', 'Analytics', 'Support', 'Quality Assurance', 'Marketing'];
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:20px; font-weight:700;">User & Team Lead Management</h2>
        <p style="color:var(--text-muted); font-size:14px;">Create system accounts for Team Leads, Super Admins, Developers, and Sales Managers with department assignment.</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="openCreateUserModal()">
            <i data-feather="user-plus"></i> + Add New User / Team Lead
        </button>
    </div>
</div>

<!-- Metrics Cards -->
<div class="grid-4" style="margin-bottom: 24px;">
    <div class="card" style="padding:18px;">
        <span style="color:var(--text-muted); font-size:12px; font-weight:700; text-transform:uppercase;">Total System Users</span>
        <h3 id="statTotalUsers" style="font-size:24px; font-weight:800; color:var(--primary); margin-top:4px;">0</h3>
    </div>
    <div class="card" style="padding:18px;">
        <span style="color:var(--text-muted); font-size:12px; font-weight:700; text-transform:uppercase;">Super Admins</span>
        <h3 id="statAdmins" style="font-size:24px; font-weight:800; color:#9333ea; margin-top:4px;">0</h3>
    </div>
    <div class="card" style="padding:18px;">
        <span style="color:var(--text-muted); font-size:12px; font-weight:700; text-transform:uppercase;">Team Leads</span>
        <h3 id="statTeamLeads" style="font-size:24px; font-weight:800; color:#2563eb; margin-top:4px;">0</h3>
    </div>
    <div class="card" style="padding:18px;">
        <span style="color:var(--text-muted); font-size:12px; font-weight:700; text-transform:uppercase;">Developers & Staff</span>
        <h3 id="statDevelopers" style="font-size:24px; font-weight:800; color:#16a34a; margin-top:4px;">0</h3>
    </div>
</div>

<!-- Filter Bar & User Directory Table -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; gap:10px; flex-wrap:wrap; flex:1;">
            <input type="text" id="userSearchInput" class="form-control" style="max-width:260px;" placeholder="Search name, username, email..." onkeyup="filterUsersTable()">
            <select id="roleFilter" class="form-select" style="max-width:200px;" onchange="fetchUsersList()">
                <option value="0">All System Roles</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="deptFilter" class="form-select" style="max-width:200px;" onchange="fetchUsersList()">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d ?>"><?= $d ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-outline btn-sm" onclick="fetchUsersList()">
            <i data-feather="refresh-cw"></i> Refresh Directory
        </button>
    </div>

    <div class="table-responsive">
        <table class="table" style="width:100%; font-size:14px;">
            <thead>
                <tr>
                    <th>User & Identity</th>
                    <th>Email Address</th>
                    <th>System Role</th>
                    <th>Department</th>
                    <th>Account Status</th>
                    <th>Created Date</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="usersTbody">
                <tr><td colspan="7" style="text-align:center; padding:30px;">Loading user accounts...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: CREATE NEW USER & ASSIGN ROLE/DEPT  -->
<!-- ========================================== -->
<div id="createUserModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:650px;">
        <div class="modal-header">
            <h3 class="modal-title">Create New User / Team Lead Account</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('createUserModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitCreateUser();">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" id="nu_full_name" class="form-control" required placeholder="e.g. Ajmal Team Lead">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" id="nu_username" class="form-control" required placeholder="e.g. lead_ajmal">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" id="nu_email" class="form-control" required placeholder="e.g. ajmal@hemitodigital.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" id="nu_password" class="form-control" required placeholder="••••••••">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">System Role *</label>
                        <select id="nu_role_id" class="form-select" required>
                            <option value="">-- Choose Role --</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['description']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Department Selection *</label>
                        <select id="nu_department" class="form-select" required>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d ?>" <?= $d === 'Development' ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:12px; font-size:12px; color:#1e40af;">
                    <strong>Module Access Auto-Assignment:</strong> Selecting a role will automatically grant relevant module permissions (e.g. Team Leads get Project & Task Management access; Sales Managers get Client & Proposal access).
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User Account</button>
            </div>
        </form>
    </div>
</div>

<script>
    let usersCache = [];
    const isSuperAdmin = <?= json_encode($user['role'] === 'Super Admin' || ($user['role_id'] ?? 0) == 1) ?>;
    const currentUserId = <?= json_encode(intval($user['id'])) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        fetchUsersList();
    });

    function fetchUsersList() {
        const roleId = document.getElementById('roleFilter').value;
        const dept = document.getElementById('deptFilter').value;
        let url = `api.php?action=get_users&role_id=${roleId}`;
        if (dept) url += `&department=${encodeURIComponent(dept)}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    usersCache = data.users;
                    renderUsersTable(data.users);
                    updateUserMetrics(data.users);
                } else {
                    alert(data.message);
                }
            });
    }

    function renderUsersTable(users) {
        const tbody = document.getElementById('usersTbody');
        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:#94a3b8;">No users found matching criteria.</td></tr>';
            return;
        }

        let html = '';
        users.forEach(u => {
            let roleBadgeClass = 'badge-primary';
            if (u.role_name.includes('Admin')) roleBadgeClass = 'badge-danger';
            else if (u.role_name.includes('Lead')) roleBadgeClass = 'badge-primary';
            else if (u.role_name.includes('Developer')) roleBadgeClass = 'badge-success';
            else if (u.role_name.includes('Sales')) roleBadgeClass = 'badge-warning';

            let resetBtnHtml = '';
            let deleteBtnHtml = '';
            if (isSuperAdmin) {
                resetBtnHtml = `
                    <button class="btn btn-outline btn-sm" onclick="openResetUserPasswordModal(${u.id}, '${escapeHtml(u.username)}', '${escapeHtml(u.full_name)}')" style="margin-left:4px;" title="Reset User Password">
                        <i data-feather="key"></i> Reset Password
                    </button>
                `;
                if (parseInt(u.id) !== currentUserId) {
                    deleteBtnHtml = `
                        <button class="btn btn-outline-danger btn-sm" onclick="deleteUserAccount(${u.id}, '${escapeHtml(u.username)}')" style="margin-left:4px;" title="Delete User Account">
                            <i data-feather="trash-2"></i> Delete
                        </button>
                    `;
                }
            }

            html += `
                <tr>
                    <td>
                        <strong>${escapeHtml(u.full_name)}</strong><br>
                        <small style="color:#64748b;">@${escapeHtml(u.username)}</small>
                    </td>
                    <td>${escapeHtml(u.email)}</td>
                    <td><span class="badge ${roleBadgeClass}">${escapeHtml(u.role_name)}</span></td>
                    <td><span class="badge badge-secondary">${escapeHtml(u.department || 'General')}</span></td>
                    <td>
                        <span class="badge ${u.is_active ? 'badge-success' : 'badge-danger'}">
                            ${u.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td style="font-size:12px; color:#64748b;">${u.created_at ? u.created_at.substring(0, 10) : 'N/A'}</td>
                    <td style="text-align:right;">
                        <button class="btn ${u.is_active ? 'btn-outline' : 'btn-success'} btn-sm" onclick="toggleUserStatus(${u.id})">
                            <i data-feather="${u.is_active ? 'user-minus' : 'user-check'}"></i> ${u.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                        ${resetBtnHtml}
                        ${deleteBtnHtml}
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
        if (window.feather) feather.replace();
    }

    function updateUserMetrics(users) {
        document.getElementById('statTotalUsers').textContent = users.length;
        document.getElementById('statAdmins').textContent = users.filter(u => u.role_name.includes('Admin')).length;
        document.getElementById('statTeamLeads').textContent = users.filter(u => u.role_name.includes('Lead')).length;
        document.getElementById('statDevelopers').textContent = users.filter(u => u.role_name.includes('Developer')).length;
    }

    function filterUsersTable() {
        const q = document.getElementById('userSearchInput').value.toLowerCase();
        const filtered = usersCache.filter(u => 
            u.full_name.toLowerCase().includes(q) ||
            u.username.toLowerCase().includes(q) ||
            u.email.toLowerCase().includes(q) ||
            u.role_name.toLowerCase().includes(q) ||
            (u.department && u.department.toLowerCase().includes(q))
        );
        renderUsersTable(filtered);
    }

    function openCreateUserModal() {
        document.getElementById('nu_full_name').value = '';
        document.getElementById('nu_username').value = '';
        document.getElementById('nu_email').value = '';
        document.getElementById('nu_password').value = '';
        document.getElementById('createUserModal').style.display = 'flex';
    }

    function submitCreateUser() {
        const payload = {
            full_name: document.getElementById('nu_full_name').value,
            username: document.getElementById('nu_username').value,
            email: document.getElementById('nu_email').value,
            password: document.getElementById('nu_password').value,
            role_id: document.getElementById('nu_role_id').value,
            department: document.getElementById('nu_department').value
        };

        fetch('api.php?action=create_user', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('createUserModal');
                alert(data.message);
                fetchUsersList();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function toggleUserStatus(userId) {
        fetch('api.php?action=toggle_user_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchUsersList();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function openResetUserPasswordModal(userId, username, fullName) {
        document.getElementById('rup_user_id').value = userId;
        document.getElementById('rup_user_name').textContent = fullName;
        document.getElementById('rup_username').textContent = '@' + username;
        document.getElementById('rup_new_password').value = '';
        document.getElementById('resetUserPasswordModal').style.display = 'flex';
    }

    function submitResetUserPassword() {
        const userId = document.getElementById('rup_user_id').value;
        const newPassword = document.getElementById('rup_new_password').value;

        fetch('api.php?action=reset_user_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, password: newPassword })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('resetUserPasswordModal');
                alert(data.message);
                fetchUsersList();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function deleteUserAccount(userId, username) {
        if (!confirm(`Are you sure you want to permanently delete user account '@${username}'?\n\nThis action cannot be undone.`)) {
            return;
        }

        fetch('api.php?action=delete_user', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                fetchUsersList();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    function escapeHtml(t) { return t ? t.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;") : ''; }
</script>

<!-- MODAL: RESET USER PASSWORD (SUPER ADMIN ONLY) -->
<div id="resetUserPasswordModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title">Reset User Account Password</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('resetUserPasswordModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitResetUserPassword();">
            <div class="modal-body">
                <input type="hidden" id="rup_user_id" value="0">
                <p style="font-size:13px; color:#475569; margin-bottom:12px;">
                    Setting new password for <strong id="rup_user_name">User</strong> (<code id="rup_username">@username</code>)
                </p>
                <div class="form-group">
                    <label class="form-label">New Custom Password *</label>
                    <input type="text" id="rup_new_password" class="form-control" required placeholder="Enter new custom password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('resetUserPasswordModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
