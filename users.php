<?php
// users.php - User & Team Lead Management Portal

require_once __DIR__ . '/auth.php';
checkAuth();

$user = getCurrentUser();
$db = getDbConnection();

$isSuperAdmin = ($user['role'] === 'Super Admin' || ($user['role_id'] ?? 0) == 1);
$isTeamLead = ($user['role'] === 'Team Lead' || ($user['role_id'] ?? 0) == 4);

if (!$isSuperAdmin && !$isTeamLead) {
    echo "<div style='font-family:sans-serif; padding:40px; text-align:center;'><h2>Access Denied</h2><p>Only Super Admins and Team Leads can access User Management.</p><a href='index.php'>Return to Dashboard</a></div>";
    exit;
}

$pageTitle = "User & Team Lead Management Portal";
require_once __DIR__ . '/header.php';

// Fetch all roles
$roles = $db->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();

// Fetch department list
try {
    $departments = $db->query("SELECT name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $departments = [];
}
if (empty($departments)) {
    $departments = ['Development', 'Management', 'Sales', 'Design / UI/UX', 'Analytics', 'Support', 'Quality Assurance', 'Marketing'];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:20px; font-weight:700;">User & Team Lead Management</h2>
        <p style="color:var(--text-muted); font-size:14px;">
            <?= $isSuperAdmin ? 'Super Admin Portal: Create Team Leads, Admins, Developers, manage departments, and user invitations.' : 'Team Lead Portal: Create developer/staff accounts, assign departments, and generate signup links.' ?>
        </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <button class="btn btn-outline" onclick="openCreateDeptModal()">
            <i data-feather="folder-plus"></i> + Add Department
        </button>
        <button class="btn btn-primary" onclick="openCreateUserModal()">
            <i data-feather="user-plus"></i> + Add New User Account
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
                    <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
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
                    <th>Invite / Signup Token</th>
                    <th>Created Date</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="usersTbody">
                <tr><td colspan="8" style="text-align:center; padding:30px;">Loading user accounts...</td></tr>
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
            <h3 class="modal-title">👤 Create New User Account</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('createUserModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitCreateUser();">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" id="nu_full_name" class="form-control" required placeholder="e.g. Rahul Developer">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" id="nu_username" class="form-control" required placeholder="e.g. dev_rahul">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" id="nu_email" class="form-control" required placeholder="e.g. rahul@hemitodigital.com">
                </div>

                <!-- Password Setup Mode Selection -->
                <div class="form-group" style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:14px; margin-bottom:18px;">
                    <label class="form-label" style="color:#0f172a; font-weight:700;">Password Creation Mode</label>
                    <div style="display:flex; gap:16px; margin-top:8px;">
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:13px;">
                            <input type="radio" name="nu_pass_mode" value="set_now" checked onchange="togglePasswordInputMode()">
                            <span>Set Initial Password Now</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:13px; color:#2563eb; font-weight:600;">
                            <input type="radio" name="nu_pass_mode" value="user_signup" onchange="togglePasswordInputMode()">
                            <span>User Will Set Own Password via Signup Link 🔗</span>
                        </label>
                    </div>

                    <div id="passwordInputContainer" style="margin-top:12px;">
                        <input type="password" id="nu_password" class="form-control" placeholder="Enter initial custom password">
                    </div>
                    <div id="selfSignupNotice" style="display:none; margin-top:10px; font-size:12px; color:#1d4ed8; background:#eff6ff; padding:8px 12px; border-radius:6px;">
                        💡 <strong>Self Signup Enabled:</strong> A unique setup invite link will be generated. The created user can open this link to set their own custom password!
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">System Role *</label>
                        <select id="nu_role_id" class="form-select" required>
                            <option value="">-- Choose Role --</option>
                            <?php foreach ($roles as $r): ?>
                                <?php 
                                    // Team Leads CANNOT create Super Admin (1) or Team Lead (4)
                                    if ($isTeamLead && !$isSuperAdmin && ($r['id'] == 1 || $r['id'] == 4)) continue;
                                ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isTeamLead && !$isSuperAdmin): ?>
                            <small style="color:#64748b; font-size:11px;">(Team Leads can create Developers, Sales, Agency Admins, etc.)</small>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Department Selection *</label>
                        <select id="nu_department" class="form-select" required>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= htmlspecialchars($d) ?>" <?= $d === 'Development' ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:12px; font-size:12px; color:#1e40af;">
                    ⚡ <strong>Module Access Auto-Assignment:</strong> Selecting a role will automatically grant relevant module permissions (e.g. Developer get Task Management access; Sales Managers get Client & Proposal access).
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User Account</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: ADD NEW DEPARTMENT                  -->
<!-- ========================================== -->
<div id="createDeptModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title">📁 Add New Department</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('createDeptModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitCreateDept();">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Department Name *</label>
                    <input type="text" id="nd_dept_name" class="form-control" required placeholder="e.g. Mobile Development, SEO & Marketing">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createDeptModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Department</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: RESET USER PASSWORD (SUPER ADMIN ONLY) -->
<!-- ========================================== -->
<div id="resetUserPasswordModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title">🔑 Reset User Account Password</h3>
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

<script>
    let usersCache = [];
    const isSuperAdmin = <?= json_encode($isSuperAdmin) ?>;
    const isTeamLead = <?= json_encode($isTeamLead) ?>;
    const currentUserId = <?= json_encode(intval($user['id'])) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        fetchUsersList();
        loadDepartmentsDropdown();
    });

    function loadDepartmentsDropdown() {
        fetch('api.php?action=get_departments')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.departments) {
                    const filterSel = document.getElementById('deptFilter');
                    const modalSel = document.getElementById('nu_department');
                    
                    let html = '<option value="">All Departments</option>';
                    let modalHtml = '';

                    data.departments.forEach(d => {
                        html += `<option value="${escapeHtml(d.name)}">${escapeHtml(d.name)}</option>`;
                        modalHtml += `<option value="${escapeHtml(d.name)}">${escapeHtml(d.name)}</option>`;
                    });

                    if (filterSel) filterSel.innerHTML = html;
                    if (modalSel) modalSel.innerHTML = modalHtml;
                }
            });
    }

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
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:30px; color:#94a3b8;">No users found matching criteria.</td></tr>';
            return;
        }

        const protocol = window.location.protocol;
        const host = window.location.host;

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

            let signupLinkBtn = '';
            if (u.signup_token) {
                const signupUrl = `${protocol}//${host}/signup.php?token=${u.signup_token}`;
                signupLinkBtn = `
                    <button class="btn btn-outline btn-sm" style="color:#2563eb; border-color:#93c5fd;" onclick="copySignupUrl('${signupUrl}')" title="Copy Signup / Password Link">
                        📋 Copy Invite Link
                    </button>
                `;
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
                    <td>
                        ${u.signup_token ? '<span class="badge badge-warning">Invite Link Active</span>' : '<span style="color:#64748b; font-size:12px;">Password Set</span>'}
                        ${signupLinkBtn}
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

    function copySignupUrl(url) {
        navigator.clipboard.writeText(url).then(() => {
            alert('📋 Signup link copied to clipboard!\n\nSend this link to the user so they can create their password:\n' + url);
        }).catch(() => {
            prompt('Copy this signup invite URL:', url);
        });
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

    function togglePasswordInputMode() {
        const modes = document.getElementsByName('nu_pass_mode');
        let selectedMode = 'set_now';
        for (const m of modes) {
            if (m.checked) selectedMode = m.value;
        }

        const passContainer = document.getElementById('passwordInputContainer');
        const passInput = document.getElementById('nu_password');
        const selfNotice = document.getElementById('selfSignupNotice');

        if (selectedMode === 'user_signup') {
            passContainer.style.display = 'none';
            passInput.removeAttribute('required');
            passInput.value = '';
            selfNotice.style.display = 'block';
        } else {
            passContainer.style.display = 'block';
            passInput.setAttribute('required', 'required');
            selfNotice.style.display = 'none';
        }
    }

    function openCreateUserModal() {
        document.getElementById('nu_full_name').value = '';
        document.getElementById('nu_username').value = '';
        document.getElementById('nu_email').value = '';
        document.getElementById('nu_password').value = '';
        document.getElementById('createUserModal').style.display = 'flex';
    }

    function submitCreateUser() {
        const modes = document.getElementsByName('nu_pass_mode');
        let selectedMode = 'set_now';
        for (const m of modes) {
            if (m.checked) selectedMode = m.value;
        }

        const payload = {
            full_name: document.getElementById('nu_full_name').value,
            username: document.getElementById('nu_username').value,
            email: document.getElementById('nu_email').value,
            password: document.getElementById('nu_password').value,
            role_id: document.getElementById('nu_role_id').value,
            department: document.getElementById('nu_department').value,
            set_own_password: (selectedMode === 'user_signup')
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
                if (data.signup_url) {
                    alert('✅ ' + data.message + '\n\nSignup Invite Link:\n' + data.signup_url);
                } else {
                    alert('✅ ' + data.message);
                }
                fetchUsersList();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function openCreateDeptModal() {
        document.getElementById('nd_dept_name').value = '';
        document.getElementById('createDeptModal').style.display = 'flex';
    }

    function submitCreateDept() {
        const deptName = document.getElementById('nd_dept_name').value;
        fetch('api.php?action=create_department', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: deptName })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('createDeptModal');
                alert('✅ ' + data.message);
                loadDepartmentsDropdown();
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
                alert('✅ ' + data.message);
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
                alert('✅ ' + data.message);
                fetchUsersList();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    function escapeHtml(t) { return t ? t.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;") : ''; }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
