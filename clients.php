<?php
// clients.php - Client Management & Documents Repository

require_once __DIR__ . '/auth.php';
checkAuth('client-management');

$user = getCurrentUser();
$db = getDbConnection();

$pageTitle = "Client Management & Repository";
require_once __DIR__ . '/header.php';

$clients = $db->query("SELECT c.*, u.full_name as created_by_name,
                        usr.username as client_username,
                        (SELECT COUNT(*) FROM client_services cs WHERE cs.client_id = c.id) as service_count,
                        (SELECT COUNT(*) FROM documents d WHERE d.client_id = c.id) as document_count
                        FROM clients c
                        LEFT JOIN users u ON c.created_by = u.id
                        LEFT JOIN users usr ON usr.client_id = c.id
                        ORDER BY c.id DESC")->fetchAll();
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:20px; font-weight:700;">Client Directory</h2>
        <p style="color:var(--text-muted); font-size:14px;">Manage accounts, portal credentials, package subscriptions, and generated document repository.</p>
    </div>
    <div style="display:flex; gap:8px;">
        <button class="btn btn-outline" onclick="openServicePackagesModal()">
            <i data-feather="package"></i> Service Packages
        </button>
        <button class="btn btn-primary" onclick="openAddClientModal()">
            <i data-feather="user-plus"></i> + Add New Client
        </button>
        <a href="template_generator.php" class="btn btn-outline">
            <i data-feather="file-plus"></i> Generate Document
        </a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Company Name</th>
                    <th>Contact Person</th>
                    <th>Email & Phone</th>
                    <th>Portal Account</th>
                    <th>Status</th>
                    <th>Packages</th>
                    <th>Saved Docs</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $c): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($c['company_name']) ?></strong><br>
                            <small style="color:var(--text-muted);">Tax ID: <?= htmlspecialchars($c['tax_id'] ?: 'N/A') ?></small>
                        </td>
                        <td><?= htmlspecialchars($c['contact_person']) ?></td>
                        <td>
                            <?= htmlspecialchars($c['email']) ?><br>
                            <small style="color:var(--text-muted);"><?= htmlspecialchars($c['phone'] ?: 'N/A') ?></small>
                        </td>
                        <td>
                            <?php if (!empty($c['client_username'])): ?>
                                <span class="badge badge-info" style="font-family:monospace;">@<?= htmlspecialchars($c['client_username']) ?></span><br>
                                <button class="btn btn-outline btn-sm" style="margin-top:4px; padding:2px 8px; font-size:11px;" onclick="openResetPasswordModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['company_name'])) ?>', '<?= htmlspecialchars($c['client_username']) ?>')">
                                    <i data-feather="key"></i> Reset Password
                                </button>
                            <?php else: ?>
                                <span class="badge badge-secondary">No Account</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $c['status'] === 'Approved / Active' ? 'badge-success' : 'badge-primary' ?>">
                                <?= htmlspecialchars($c['status']) ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:12px;" onclick="openServicePackagesModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['company_name'])) ?>')">
                                <i data-feather="package" style="width:12px; height:12px;"></i> <?= $c['service_count'] ?> Package(s)
                            </button>
                        </td>
                        <td><span class="badge badge-primary"><?= $c['document_count'] ?> Document(s)</span></td>
                        <td>
                            <div style="display:flex; gap:6px; align-items:center;">
                                <button type="button" class="btn btn-outline btn-sm" onclick="openEditClientModal(<?= htmlspecialchars(json_encode($c)) ?>)" title="Edit Client Profile">
                                    <i data-feather="edit-2"></i> Edit Client
                                </button>
                                <button type="button" class="btn btn-outline btn-sm" onclick="openServicePackagesModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['company_name'])) ?>')" title="Manage Packages">
                                    <i data-feather="plus-circle"></i> Packages
                                </button>
                                <a href="template_generator.php" class="btn btn-outline btn-sm">Generate Document</a>
                                <?php if ($user['role'] === 'Super Admin' || ($user['role_id'] ?? 0) == 1): ?>
                                    <button class="btn btn-outline-danger btn-sm" onclick="deleteClientAccount(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['company_name'])) ?>')" title="Delete Client Account">
                                        <i data-feather="trash-2"></i> Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: ADD NEW CLIENT -->
<div id="addClientModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:650px;">
        <div class="modal-header">
            <h3 class="modal-title">👤 Add New Client & Set Portal Credentials</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('addClientModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitCreateClient();">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Company Name *</label>
                        <input type="text" id="ac_company_name" class="form-control" required placeholder="e.g. Acme Tech Solutions">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Person *</label>
                        <input type="text" id="ac_contact_person" class="form-control" required placeholder="e.g. John Doe">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" id="ac_email" class="form-control" required placeholder="john@acme.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" id="ac_phone" class="form-control" placeholder="+91 98765 43210">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Service Package Name</label>
                        <input type="text" id="ac_service_name" class="form-control" placeholder="e.g. Performance Marketing Suite">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Package Price (INR)</label>
                        <input type="number" id="ac_price" class="form-control" placeholder="50000">
                    </div>
                </div>

                <!-- Custom Portal Login Credentials Section -->
                <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:14px; margin-top:10px;">
                    <strong style="color:#0369a1; font-size:13px; display:block; margin-bottom:8px;">🔑 Separate Portal Login Credentials for this Client:</strong>
                    <div class="grid-2">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Portal Username (Optional)</label>
                            <input type="text" id="ac_username" class="form-control" placeholder="e.g. acme_client">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Portal Password (Optional)</label>
                            <input type="text" id="ac_password" class="form-control" placeholder="e.g. AcmePass#2026">
                        </div>

<!-- MODAL: EDIT CLIENT -->
<div id="editClientModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:650px;">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Edit Client Profile & Details</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('editClientModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitUpdateClient();">
            <div class="modal-body">
                <input type="hidden" id="ec_client_id" value="0">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Company Name *</label>
                        <input type="text" id="ec_company_name" class="form-control" required placeholder="e.g. Acme Tech Solutions">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Person *</label>
                        <input type="text" id="ec_contact_person" class="form-control" required placeholder="e.g. John Doe">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" id="ec_email" class="form-control" required placeholder="john@acme.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" id="ec_phone" class="form-control" placeholder="+91 98765 43210">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Tax ID / GSTIN</label>
                        <input type="text" id="ec_tax_id" class="form-control" placeholder="Tax ID">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <select id="ec_status" class="form-select">
                            <option value="Approved / Active">Approved / Active</option>
                            <option value="Pending Approval">Pending Approval</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Client Notes & Remarks</label>
                    <textarea id="ec_notes" class="form-control" rows="3" placeholder="Additional client notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editClientModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Client Profile</button>
            </div>
        </form>
    </div>
</div>
                    </div>
                    <small style="color:#0284c7; display:block; margin-top:6px;">If left blank, a unique username (@client_company) and a unique random password (e.g. Client#8942) will be auto-generated.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addClientModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Client Account</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: MANAGE SERVICE PACKAGES -->
<div id="servicePackagesModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:850px; width:95%;">
        <div class="modal-header">
            <h3 class="modal-title">📦 Service Packages Directory & Management</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('servicePackagesModal')">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Filter & Action Controls -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <label style="font-weight:600; font-size:13px; color:var(--text-muted);">Client Filter:</label>
                    <select id="sp_filter_client" class="form-select" style="min-width:220px;" onchange="onSpClientFilterChange()">
                        <option value="0">-- All Clients Service Packages --</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="showAddServicePackageForm()">
                    <i data-feather="plus"></i> + Add Service Package
                </button>
            </div>

            <!-- Form Card for Add/Edit -->
            <div id="sp_form_card" style="display:none; background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:18px; margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h4 id="sp_form_title" style="margin:0; font-size:15px; color:#0f172a; font-weight:700;">+ Add New Service Package</h4>
                    <button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px;" onclick="hideServicePackageForm()">&times;</button>
                </div>
                <form onsubmit="event.preventDefault(); submitSaveServicePackage();">
                    <input type="hidden" id="sp_package_id" value="0">
                    
                    <div class="form-group">
                        <label class="form-label">Client Company *</label>
                        <select id="sp_client_id" class="form-select" required>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Service Name *</label>
                            <input type="text" id="sp_service_name" class="form-control" required placeholder="e.g. Website Development">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Package Name *</label>
                            <input type="text" id="sp_package_name" class="form-control" required placeholder="e.g. Corporate Web Suite">
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="form-group">
                            <label class="form-label">Price *</label>
                            <input type="number" step="0.01" id="sp_price" class="form-control" required placeholder="150000">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Currency</label>
                            <select id="sp_currency" class="form-select">
                                <option value="INR">INR (₹)</option>
                                <option value="USD">USD ($)</option>
                                <option value="EUR">EUR (€)</option>
                                <option value="GBP">GBP (£)</option>
                                <option value="AED">AED</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Package Status</label>
                            <select id="sp_status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Paused">Paused</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Billing Terms</label>
                            <input type="text" id="sp_billing_terms" class="form-control" placeholder="e.g. 50% Advance, 50% on Completion">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Payment Terms</label>
                            <input type="text" id="sp_payment_terms" class="form-control" placeholder="e.g. Net 15 days from Invoice">
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:10px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="hideServicePackageForm()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Service Package</button>
                    </div>
                </form>
            </div>

            <!-- Table of Packages -->
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Package Name</th>
                            <th>Service Category</th>
                            <th>Price</th>
                            <th>Terms</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sp_table_body">
                        <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:20px;">Loading service packages...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: RESET CLIENT PASSWORD -->
<div id="resetPasswordModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title">🔑 Reset Client Portal Password</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('resetPasswordModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitResetClientPassword();">
            <div class="modal-body">
                <input type="hidden" id="rp_client_id" value="0">
                <p style="font-size:13px; color:#475569; margin-bottom:12px;">
                    Setting new password for <strong id="rp_client_name">Client</strong> (<code id="rp_username">@username</code>)
                </p>
                <div class="form-group">
                    <label class="form-label">New Custom Password *</label>
                    <input type="text" id="rp_new_password" class="form-control" required placeholder="Enter new custom password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('resetPasswordModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
let allClients = <?= json_encode($clients) ?>;
let loadedServices = [];

function openAddClientModal() {
    document.getElementById('ac_company_name').value = '';
    document.getElementById('ac_contact_person').value = '';
    document.getElementById('ac_email').value = '';
    document.getElementById('ac_phone').value = '';
    document.getElementById('ac_service_name').value = '';
    document.getElementById('ac_price').value = '';
    document.getElementById('ac_username').value = '';
    document.getElementById('ac_password').value = '';
    document.getElementById('addClientModal').style.display = 'flex';
}

function submitCreateClient() {
    const payload = {
        company_name: document.getElementById('ac_company_name').value,
        contact_person: document.getElementById('ac_contact_person').value,
        email: document.getElementById('ac_email').value,
        phone: document.getElementById('ac_phone').value,
        service_name: document.getElementById('ac_service_name').value,
        price: document.getElementById('ac_price').value,
        username: document.getElementById('ac_username').value,
        password: document.getElementById('ac_password').value
    };

    fetch('api.php?action=create_client', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal('addClientModal');
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function openEditClientModal(client) {
    document.getElementById('ec_client_id').value = client.id;
    document.getElementById('ec_company_name').value = client.company_name || '';
    document.getElementById('ec_contact_person').value = client.contact_person || '';
    document.getElementById('ec_email').value = client.email || '';
    document.getElementById('ec_phone').value = client.phone || '';
    document.getElementById('ec_tax_id').value = client.tax_id || '';
    document.getElementById('ec_status').value = client.status || 'Approved / Active';
    document.getElementById('ec_notes').value = client.notes || '';
    document.getElementById('editClientModal').style.display = 'flex';
}

function submitUpdateClient() {
    const payload = {
        client_id: document.getElementById('ec_client_id').value,
        company_name: document.getElementById('ec_company_name').value,
        contact_person: document.getElementById('ec_contact_person').value,
        email: document.getElementById('ec_email').value,
        phone: document.getElementById('ec_phone').value,
        tax_id: document.getElementById('ec_tax_id').value,
        status: document.getElementById('ec_status').value,
        notes: document.getElementById('ec_notes').value
    };

    fetch('api.php?action=update_client', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal('editClientModal');
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function openResetPasswordModal(clientId, companyName, username) {
    document.getElementById('rp_client_id').value = clientId;
    document.getElementById('rp_client_name').textContent = companyName;
    document.getElementById('rp_username').textContent = '@' + username;
    document.getElementById('rp_new_password').value = '';
    document.getElementById('resetPasswordModal').style.display = 'flex';
}

function submitResetClientPassword() {
    const clientId = document.getElementById('rp_client_id').value;
    const newPassword = document.getElementById('rp_new_password').value;

    fetch('api.php?action=reset_client_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ client_id: clientId, password: newPassword })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal('resetPasswordModal');
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function deleteClientAccount(clientId, companyName) {
    if (!confirm(`Are you sure you want to permanently delete client '${companyName}'?\n\nThis will remove all linked portal data, client user accounts, and document records. This action cannot be undone.`)) {
        return;
    }

    fetch('api.php?action=delete_client', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ client_id: clientId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// --- SERVICE PACKAGES MANAGE FUNCTIONS ---
function openServicePackagesModal(clientId = 0, companyName = '') {
    const filterSelect = document.getElementById('sp_filter_client');
    filterSelect.value = clientId;
    hideServicePackageForm();
    document.getElementById('servicePackagesModal').style.display = 'flex';
    loadServicePackages(clientId);
}

function loadServicePackages(clientId = 0) {
    fetch(`api.php?action=get_client_services&client_id=${clientId}`)
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadedServices = data.services;
            renderServicePackagesTable(loadedServices);
        }
    });
}

function renderServicePackagesTable(services) {
    const tbody = document.getElementById('sp_table_body');
    if (!services || services.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:20px;">No service packages found. Click '+ Add Service Package' above to create one!</td></tr>`;
        return;
    }
    tbody.innerHTML = services.map(s => `
        <tr>
            <td><strong>${escapeHtml(s.company_name || 'Client #' + s.client_id)}</strong></td>
            <td><strong>${escapeHtml(s.package_name)}</strong></td>
            <td><span class="badge badge-primary">${escapeHtml(s.service_name)}</span></td>
            <td><strong>${s.currency || 'INR'} ${parseFloat(s.price).toLocaleString()}</strong></td>
            <td>
                <small style="display:block; color:var(--text-muted);">${escapeHtml(s.billing_terms || 'Standard')}</small>
                <small style="display:block; color:var(--text-muted);">${escapeHtml(s.payment_terms || 'Net 15')}</small>
            </td>
            <td><span class="badge ${s.status === 'Active' ? 'badge-success' : 'badge-secondary'}">${escapeHtml(s.status || 'Active')}</span></td>
            <td>
                <div style="display:flex; gap:6px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="editServicePackage(${s.id})">Edit</button>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteServicePackage(${s.id}, '${escapeHtml(addslashes(s.package_name))}')">Delete</button>
                </div>
            </td>
        </tr>
    `).join('');
    if (window.feather) feather.replace();
}

function showAddServicePackageForm() {
    document.getElementById('sp_package_id').value = 0;
    const filterClientId = document.getElementById('sp_filter_client').value;
    document.getElementById('sp_client_id').value = filterClientId > 0 ? filterClientId : (allClients[0] ? allClients[0].id : 0);
    document.getElementById('sp_service_name').value = '';
    document.getElementById('sp_package_name').value = '';
    document.getElementById('sp_price').value = '';
    document.getElementById('sp_currency').value = 'INR';
    document.getElementById('sp_billing_terms').value = '50% Advance, 50% on Completion';
    document.getElementById('sp_payment_terms').value = 'Net 15 days';
    document.getElementById('sp_status').value = 'Active';
    document.getElementById('sp_form_title').textContent = '+ Add New Service Package';
    document.getElementById('sp_form_card').style.display = 'block';
}

function hideServicePackageForm() {
    document.getElementById('sp_form_card').style.display = 'none';
}

function editServicePackage(packageId) {
    const pkg = loadedServices.find(s => s.id == packageId);
    if (!pkg) return;
    document.getElementById('sp_package_id').value = pkg.id;
    document.getElementById('sp_client_id').value = pkg.client_id;
    document.getElementById('sp_service_name').value = pkg.service_name;
    document.getElementById('sp_package_name').value = pkg.package_name;
    document.getElementById('sp_price').value = pkg.price;
    document.getElementById('sp_currency').value = pkg.currency || 'INR';
    document.getElementById('sp_billing_terms').value = pkg.billing_terms || '';
    document.getElementById('sp_payment_terms').value = pkg.payment_terms || '';
    document.getElementById('sp_status').value = pkg.status || 'Active';
    document.getElementById('sp_form_title').textContent = '✏️ Edit Service Package #' + pkg.id;
    document.getElementById('sp_form_card').style.display = 'block';
}

function submitSaveServicePackage() {
    const pkgId = document.getElementById('sp_package_id').value;
    const payload = {
        id: pkgId,
        client_id: document.getElementById('sp_client_id').value,
        service_name: document.getElementById('sp_service_name').value,
        package_name: document.getElementById('sp_package_name').value,
        price: document.getElementById('sp_price').value,
        currency: document.getElementById('sp_currency').value,
        billing_terms: document.getElementById('sp_billing_terms').value,
        payment_terms: document.getElementById('sp_payment_terms').value,
        status: document.getElementById('sp_status').value
    };

    const action = pkgId > 0 ? 'update_service_package' : 'add_service_package';

    fetch(`api.php?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            hideServicePackageForm();
            alert('✅ ' + data.message);
            const currentFilter = document.getElementById('sp_filter_client').value;
            loadServicePackages(currentFilter);
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function deleteServicePackage(id, packageName) {
    if (!confirm(`Are you sure you want to delete service package '${packageName}'?`)) return;
    fetch('api.php?action=delete_service_package', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            const currentFilter = document.getElementById('sp_filter_client').value;
            loadServicePackages(currentFilter);
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function onSpClientFilterChange() {
    const clientId = document.getElementById('sp_filter_client').value;
    loadServicePackages(clientId);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function addslashes(str) {
    if (!str) return '';
    return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

function closeModal(id) { document.getElementById(id).style.display = 'none'; }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
