<?php
// operations.php - Shoot Schedules & Domain/Server Renewals Hub
require_once __DIR__ . '/auth.php';
checkAuth();

$user = getCurrentUser();
$db = getDbConnection();
ensureOperationsSchema($db);

$isSuperAdmin = isSuperAdminUser($user);
$hasOperationsAccess = $isSuperAdmin || canAccessOperations($user, $db);

$activeTab = ($_GET['tab'] ?? 'shoots') === 'renewals' ? 'renewals' : 'shoots';

$pageBreadcrumb = "Operations & Assets";
$pageTitle = ($activeTab === 'renewals') ? "Domain & Server Renewal Tracking" : "Shoot Schedule & Production Management";
$pageSubtitle = "Centralized tracking for client video shoot schedules, production call sheets, and domain & server renewal cycles.";

require_once __DIR__ . '/header.php';

// Fetch clients for dropdowns
$clients = $db->query("SELECT id, company_name, contact_person, phone, email FROM clients ORDER BY company_name ASC")->fetchAll();

// Fetch agency staff for employee assignment (Super Admin only)
$staffMembers = $db->query("SELECT u.id, u.full_name, u.username, u.department, r.name as role_name 
                            FROM users u 
                            JOIN roles r ON u.role_id = r.id 
                            WHERE COALESCE(u.is_active, 1) = 1 AND r.name != 'Client' 
                            ORDER BY u.full_name ASC")->fetchAll();
?>

<style>
.ops-tabs {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 8px;
}
.ops-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    background: transparent;
    border: none;
    color: #64748b;
    transition: all 0.2s ease;
    text-decoration: none;
}
.ops-tab-btn:hover {
    color: #1e293b;
    background: #f1f5f9;
}
.ops-tab-btn.active {
    background: #2563eb;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(37,99,235,0.25);
}
.ops-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.ops-stat-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 18px 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.ops-stat-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.ops-stat-val {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
}
.ops-stat-sub {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 4px;
}
.badge-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 9999px;
    font-size: 11.5px;
    font-weight: 700;
    white-space: nowrap;
}
.badge-venue-yes { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.badge-venue-no { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.badge-venue-pending-venue { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.badge-venue-pending-client { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }

.badge-script-yes { background: #dcfce7; color: #166534; }
.badge-script-no { background: #fee2e2; color: #991b1b; }
.badge-script-pending { background: #fef3c7; color: #92400e; }
.badge-script-progress { background: #e0f2fe; color: #0369a1; }

.badge-days-urgent { background: #fee2e2; color: #b91c1c; font-weight: 800; animation: pulseRed 2s infinite; }
.badge-days-warning { background: #fef3c7; color: #b45309; font-weight: 700; }
.badge-days-ok { background: #dcfce7; color: #15803d; }
.badge-days-expired { background: #f1f5f9; color: #64748b; text-decoration: line-through; }

@keyframes pulseRed {
    0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
    70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

.assigned-avatar-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 600;
    color: #334155;
    margin: 2px 3px 2px 0;
}
.assigned-avatar-pill i {
    width: 12px;
    height: 12px;
    color: #2563eb;
}
.table-ops th {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #475569;
    background: #f8fafc;
    padding: 12px 14px;
    border-bottom: 1px solid #e2e8f0;
}
.table-ops td {
    padding: 14px;
    font-size: 13.5px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
.table-ops tr:hover td {
    background: #f8fafc;
}
</style>

<div class="content-body" style="padding: 24px;">

    <?php if (!$hasOperationsAccess): ?>
        <div style="background:#ffffff; border-radius:12px; padding:40px; text-align:center; max-width:600px; margin:40px auto; border:1px solid #e2e8f0; box-shadow:0 10px 25px rgba(0,0,0,0.04);">
            <div style="width:64px; height:64px; border-radius:50%; background:#f1f5f9; display:inline-flex; align-items:center; justify-content:center; color:#64748b; margin-bottom:16px;">
                <i data-feather="lock" style="width:28px; height:28px;"></i>
            </div>
            <h3 style="color:#0f172a; margin-bottom:8px;">Access Restricted</h3>
            <p style="color:#64748b; font-size:14px; line-height:1.6; margin-bottom:20px;">
                You currently do not have any Shoot Schedules or Domain/Server Renewals assigned to your account by the Super Admin.
            </p>
            <a href="projects.php" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:8px;">
                <i data-feather="arrow-left"></i> Return to Tasks Workspace
            </a>
        </div>
    <?php else: ?>

        <!-- Top Navigation Tabs -->
        <div class="ops-tabs">
            <button class="ops-tab-btn <?= $activeTab === 'shoots' ? 'active' : '' ?>" onclick="switchOpsTab('shoots')">
                <i data-feather="video"></i>
                <span>🎬 Shoot Schedules</span>
            </button>
            <button class="ops-tab-btn <?= $activeTab === 'renewals' ? 'active' : '' ?>" onclick="switchOpsTab('renewals')">
                <i data-feather="globe"></i>
                <span>🌐 Domain & Server Renewals</span>
            </button>
        </div>

        <!-- ========================================================= -->
        <!-- TAB 1: 🎬 SHOOT SCHEDULES                                 -->
        <!-- ========================================================= -->
        <div id="tabContentShoots" style="display: <?= $activeTab === 'shoots' ? 'block' : 'none' ?>;">
            
            <!-- Shoot Stats Cards -->
            <div class="ops-stat-grid">
                <div class="ops-stat-card">
                    <div class="ops-stat-label">
                        <span>Total Shoots</span>
                        <i data-feather="film" style="color:#2563eb; width:16px; height:16px;"></i>
                    </div>
                    <div class="ops-stat-val" id="statShootTotal">—</div>
                    <div class="ops-stat-sub">Across all clients</div>
                </div>

                <div class="ops-stat-card">
                    <div class="ops-stat-label">
                        <span>Upcoming Shoots</span>
                        <i data-feather="calendar" style="color:#10b981; width:16px; height:16px;"></i>
                    </div>
                    <div class="ops-stat-val" style="color:#10b981;" id="statShootUpcoming">—</div>
                    <div class="ops-stat-sub">Scheduled for future dates</div>
                </div>

                <div class="ops-stat-card">
                    <div class="ops-stat-label">
                        <span>Scripts Approved</span>
                        <i data-feather="check-circle" style="color:#8b5cf6; width:16px; height:16px;"></i>
                    </div>
                    <div class="ops-stat-val" style="color:#8b5cf6;" id="statShootScripts">—</div>
                    <div class="ops-stat-sub">Ready for video production</div>
                </div>

                <div class="ops-stat-card">
                    <div class="ops-stat-label">
                        <span>Venue Blocked (Confirmed)</span>
                        <i data-feather="map-pin" style="color:#f59e0b; width:16px; height:16px;"></i>
                    </div>
                    <div class="ops-stat-val" style="color:#f59e0b;" id="statShootVenues">—</div>
                    <div class="ops-stat-sub">Locations locked in</div>
                </div>
            </div>

            <!-- Action & Search Toolbar -->
            <div class="card" style="padding:16px 20px; margin-bottom:20px;">
                <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between;">
                    <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; flex:1; min-width:300px;">
                        <div style="position:relative; flex:1; min-width:220px; max-width:320px;">
                            <i data-feather="search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); width:15px; height:15px; color:#94a3b8;"></i>
                            <input type="text" id="shootSearchInput" oninput="debounceLoadShoots()" placeholder="Search client, venue, shoot type..." class="form-control" style="padding-left:36px; height:38px; font-size:13px; border-radius:8px;">
                        </div>

                        <select id="shootStatusFilter" onchange="loadShootSchedules()" class="form-control" style="width:auto; height:38px; font-size:13px; border-radius:8px;">
                            <option value="">All Statuses</option>
                            <option value="Scheduled">Scheduled</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="Postponed">Postponed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>

                        <select id="shootTypeFilter" onchange="loadShootSchedules()" class="form-control" style="width:auto; height:38px; font-size:13px; border-radius:8px;">
                            <option value="">All Shoot Types</option>
                            <option value="Podcast">Podcast</option>
                            <option value="Anchor Reel">Anchor Reel</option>
                            <option value="Client Informative Video">Client Informative Video</option>
                            <option value="Product Photography">Product Photography</option>
                            <option value="Product Videography">Product Videography</option>
                        </select>

                        <select id="shootVenueStatusFilter" onchange="loadShootSchedules()" class="form-control" style="width:auto; height:38px; font-size:13px; border-radius:8px;">
                            <option value="">All Venue States</option>
                            <option value="Yes">Venue Blocked: Yes</option>
                            <option value="No">Venue Blocked: No</option>
                            <option value="Pending from Venue Side">Pending from Venue Side</option>
                            <option value="Pending from Client Side">Pending from Client Side</option>
                        </select>

                        <button type="button" class="btn btn-outline btn-sm" onclick="resetShootFilters()" title="Reset Filters" style="height:38px; padding:0 12px; border-radius:8px;">
                            <i data-feather="rotate-ccw" style="width:14px; height:14px;"></i>
                        </button>
                    </div>

                    <?php if ($isSuperAdmin): ?>
                    <button class="btn btn-primary" onclick="openAddShootModal()" style="display:inline-flex; align-items:center; gap:8px; font-weight:700; height:38px; border-radius:8px; padding:0 18px;">
                        <i data-feather="plus-circle" style="width:16px; height:16px;"></i>
                        <span>+ Schedule New Shoot</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Shoot Schedules Table -->
            <div class="card" style="padding:0; overflow:hidden;">
                <div class="table-responsive">
                    <table class="table table-ops" id="shootsTable">
                        <thead>
                            <tr>
                                <th>Client Name</th>
                                <th>Shoot Date & Time</th>
                                <th>Shoot Type</th>
                                <th>Videos Planned</th>
                                <th>Scripts Approved</th>
                                <th>Shoot Venue & Status</th>
                                <th>Assigned Team Members</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="shootsTableBody">
                            <tr>
                                <td colspan="9" style="text-align:center; padding:40px; color:#94a3b8;">
                                    <div class="spinner-border spinner-border-sm" role="status"></div> Loading shoot schedules...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- ========================================================= -->
        <!-- TAB 2: 🌐 DOMAIN & SERVER RENEWALS                        -->
        <!-- ========================================================= -->
        <div id="tabContentRenewals" style="display: <?= $activeTab === 'renewals' ? 'block' : 'none' ?>;">
            
            <!-- Renewal Stats Cards -->
            <div class="ops-stat-grid">
                <div class="ops-stat-card">
                    <div class="ops-stat-label">
                        <span>Total Domains</span>
                        <i data-feather="globe" style="color:#2563eb; width:16px; height:16px;"></i>
                    </div>
                    <div class="ops-stat-val" id="statRenTotal">—</div>
                    <div class="ops-stat-sub">Client domains & servers</div>
                </div>

                <div class="ops-stat-card" style="border-left: 4px solid #f59e0b;">
                    <div class="ops-stat-label">
                        <span>Expiring in &le; 30 Days</span>
                        <i data-feather="alert-triangle" style="color:#f59e0b; width:16px; height:16px;"></i>
                    </div>
                    <div class="ops-stat-val" style="color:#d97706;" id="statRenExpiringSoon">—</div>
                    <div class="ops-stat-sub">Action required immediately</div>
                </div>

                <div class="ops-stat-card">
                    <div class="ops-stat-label">
                        <span>Domain Charges (Annual)</span>
                        <i data-feather="credit-card" style="color:#10b981; width:16px; height:16px;"></i>
                    </div>
                    <div class="ops-stat-val" style="color:#10b981;" id="statRenDomainSum">—</div>
                    <div class="ops-stat-sub">Total domain billing</div>
                </div>

                <div class="ops-stat-card">
                    <div class="ops-stat-label">
                        <span>Server Charges (Annual)</span>
                        <i data-feather="server" style="color:#6366f1; width:16px; height:16px;"></i>
                    </div>
                    <div class="ops-stat-val" style="color:#6366f1;" id="statRenServerSum">—</div>
                    <div class="ops-stat-sub">Total hosting & VPS charges</div>
                </div>
            </div>

            <!-- Renewal Toolbar -->
            <div class="card" style="padding:16px 20px; margin-bottom:20px;">
                <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between;">
                    <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; flex:1; min-width:300px;">
                        <div style="position:relative; flex:1; min-width:220px; max-width:320px;">
                            <i data-feather="search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); width:15px; height:15px; color:#94a3b8;"></i>
                            <input type="text" id="renewalSearchInput" oninput="debounceLoadRenewals()" placeholder="Search domain, client, phone, hosting..." class="form-control" style="padding-left:36px; height:38px; font-size:13px; border-radius:8px;">
                        </div>

                        <select id="renewalStatusFilter" onchange="loadDomainRenewals()" class="form-control" style="width:auto; height:38px; font-size:13px; border-radius:8px;">
                            <option value="">All Renewal States</option>
                            <option value="Active">Active</option>
                            <option value="Expiring Soon">Expiring Soon</option>
                            <option value="Expired">Expired</option>
                            <option value="Renewed">Renewed</option>
                        </select>

                        <select id="renewalWindowFilter" onchange="loadDomainRenewals()" class="form-control" style="width:auto; height:38px; font-size:13px; border-radius:8px;">
                            <option value="">All Expiry Windows</option>
                            <option value="30_days">Expiring within 30 Days</option>
                            <option value="expired">Already Expired</option>
                        </select>

                        <button type="button" class="btn btn-outline btn-sm" onclick="resetRenewalFilters()" title="Reset Filters" style="height:38px; padding:0 12px; border-radius:8px;">
                            <i data-feather="rotate-ccw" style="width:14px; height:14px;"></i>
                        </button>
                    </div>

                    <?php if ($isSuperAdmin): ?>
                    <button class="btn btn-primary" onclick="openAddRenewalModal()" style="display:inline-flex; align-items:center; gap:8px; font-weight:700; height:38px; border-radius:8px; padding:0 18px;">
                        <i data-feather="plus-circle" style="width:16px; height:16px;"></i>
                        <span>+ Add Domain & Server Renewal</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Domain & Server Renewals Table -->
            <div class="card" style="padding:0; overflow:hidden;">
                <div class="table-responsive">
                    <table class="table table-ops" id="renewalsTable">
                        <thead>
                            <tr>
                                <th>Domain Name & Provider</th>
                                <th>Client Name & Contact</th>
                                <th>Domain Expiry & Charges</th>
                                <th>Server Expiry & Charges</th>
                                <th>Total Charges</th>
                                <th>Assigned Incharge</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="renewalsTableBody">
                            <tr>
                                <td colspan="8" style="text-align:center; padding:40px; color:#94a3b8;">
                                    <div class="spinner-border spinner-border-sm" role="status"></div> Loading domain & server renewals...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    <?php endif; ?>

</div>

<!-- ========================================================================= -->
<!-- MODAL 1: ADD / EDIT SHOOT SCHEDULE (SUPER ADMIN)                           -->
<!-- ========================================================================= -->
<div id="shootModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); z-index:99999; justify-content:center; align-items:center; overflow-y:auto; padding:20px;">
    <div class="modal-content" style="background:#ffffff; width:100%; max-width:680px; border-radius:14px; padding:28px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); max-height:92vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">
            <h3 id="shootModalTitle" style="margin:0; font-size:18px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <i data-feather="video" style="color:#2563eb;"></i> <span>Schedule Video Shoot</span>
            </h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeShootModal()" style="border:none; font-size:22px; cursor:pointer;">&times;</button>
        </div>

        <form id="shootForm" onsubmit="event.preventDefault(); submitShootForm();">
            <input type="hidden" id="shoot_id" value="">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:14px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">Select Existing Client (Optional)</label>
                    <select id="shoot_client_id" class="form-control" onchange="onShootClientSelect(this.value)">
                        <option value="">-- Choose Existing Client --</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['company_name']) ?>" data-phone="<?= htmlspecialchars($c['phone'] ?? '') ?>">
                                <?= htmlspecialchars($c['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">Client Name *</label>
                    <input type="text" id="shoot_client_name" class="form-control" required placeholder="Enter Client / Brand Name">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:14px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">Shoot Date & Time *</label>
                    <input type="datetime-local" id="shoot_date" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">Shoot Type *</label>
                    <select id="shoot_type" class="form-control" required>
                        <option value="Podcast">Podcast</option>
                        <option value="Anchor Reel">Anchor Reel</option>
                        <option value="Client Informative Video" selected>Client Informative Video</option>
                        <option value="Product Photography">Product Photography</option>
                        <option value="Product Videography">Product Videography</option>
                        <option value="Brand Film">Brand Film</option>
                        <option value="Commercial Ad">Commercial Ad</option>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:14px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">No of Video's Planned *</label>
                    <input type="number" id="shoot_videos_planned" class="form-control" min="1" value="1" required>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">No of Client Participants</label>
                    <input type="number" id="shoot_client_participants" class="form-control" min="0" value="1">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:14px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">Scripts Approved *</label>
                    <select id="shoot_scripts_approved" class="form-control">
                        <option value="Yes">Yes (Approved)</option>
                        <option value="No">No (Pending Revision)</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Pending" selected>Pending</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">Shoot Venue Blocked *</label>
                    <select id="shoot_venue_blocked" class="form-control">
                        <option value="Yes">Yes (Venue Confirmed & Blocked)</option>
                        <option value="No">No (Not Blocked)</option>
                        <option value="Pending from Venue Side" selected>Pending from Venue Side</option>
                        <option value="Pending from Client Side">Pending from Client Side</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">Shoot Venue (Location / Studio) *</label>
                <input type="text" id="shoot_venue" class="form-control" required placeholder="e.g. Studio A / Client Office / Outdoor Location">
            </div>

            <!-- Assign Employees (Super Admin Only) -->
            <div class="form-group" style="margin-bottom:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px;">
                <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:6px; display:block; color:#1e293b;">
                    👥 Assign Employees (Only Super Admin & Assigned Employees can view this shoot)
                </label>
                <div style="max-height:140px; overflow-y:auto; display:grid; grid-template-columns:1fr 1fr; gap:8px; padding:6px; background:#ffffff; border:1px solid #cbd5e1; border-radius:8px;">
                    <?php foreach ($staffMembers as $st): ?>
                        <label style="display:flex; align-items:center; gap:8px; font-size:12.5px; color:#334155; cursor:pointer; padding:4px 6px; border-radius:4px;" class="hover-bg-slate">
                            <input type="checkbox" name="shoot_assignees[]" value="<?= $st['id'] ?>" class="shoot-assignee-cb">
                            <span><strong><?= htmlspecialchars($st['full_name']) ?></strong> <small style="color:#64748b;">(<?= htmlspecialchars($st['role_name']) ?>)</small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 2fr; gap:16px; margin-bottom:14px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">Shoot Status</label>
                    <select id="shoot_status" class="form-control">
                        <option value="Scheduled" selected>Scheduled</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Postponed">Postponed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">Notes / Call Sheet Details</label>
                    <input type="text" id="shoot_notes" class="form-control" placeholder="Equipment required, call time, anchor details...">
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid #e2e8f0; padding-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeShootModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSaveShoot">Save Shoot Schedule</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 2: ADD / EDIT DOMAIN & SERVER RENEWAL (SUPER ADMIN)                  -->
<!-- ========================================================================= -->
<div id="renewalModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); z-index:99999; justify-content:center; align-items:center; overflow-y:auto; padding:20px;">
    <div class="modal-content" style="background:#ffffff; width:100%; max-width:680px; border-radius:14px; padding:28px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); max-height:92vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">
            <h3 id="renewalModalTitle" style="margin:0; font-size:18px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <i data-feather="globe" style="color:#2563eb;"></i> <span>Domain & Server Renewal Tracking</span>
            </h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeRenewalModal()" style="border:none; font-size:22px; cursor:pointer;">&times;</button>
        </div>

        <form id="renewalForm" onsubmit="event.preventDefault(); submitRenewalForm();">
            <input type="hidden" id="renewal_id" value="">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:14px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">Select Existing Client (Optional)</label>
                    <select id="ren_client_id" class="form-control" onchange="onRenewalClientSelect(this.value)">
                        <option value="">-- Choose Existing Client --</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['company_name']) ?>" data-phone="<?= htmlspecialchars($c['phone'] ?? '') ?>">
                                <?= htmlspecialchars($c['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">Client Name *</label>
                    <input type="text" id="ren_client_name" class="form-control" required placeholder="Client / Company Name">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:14px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">Domain Name *</label>
                    <input type="text" id="ren_domain_name" class="form-control" required placeholder="e.g. example.com">
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">Contact Number</label>
                    <input type="tel" id="ren_contact_number" class="form-control" placeholder="+91 98765 43210">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:14px; background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;">
                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block; color:#2563eb;">🌐 Domain Expiry Date</label>
                    <input type="date" id="ren_domain_expiry" class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block; color:#2563eb;">Domain Charges (₹)</label>
                    <input type="number" step="0.01" id="ren_domain_charges" class="form-control" placeholder="e.g. 1200">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:14px; background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;">
                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block; color:#7c3aed;">🖥️ Server / Hosting Expiry Date</label>
                    <input type="date" id="ren_server_expiry" class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block; color:#7c3aed;">Server Charges (₹)</label>
                    <input type="number" step="0.01" id="ren_server_charges" class="form-control" placeholder="e.g. 6500">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:14px;">
                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">Hosting Provider / Registrar</label>
                    <input type="text" id="ren_hosting_provider" class="form-control" placeholder="e.g. Hostinger, AWS, Cloudflare, GoDaddy">
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">Renewal Status</label>
                    <select id="ren_renewal_status" class="form-control">
                        <option value="Active" selected>Active</option>
                        <option value="Expiring Soon">Expiring Soon</option>
                        <option value="Expired">Expired</option>
                        <option value="Renewed">Renewed</option>
                    </select>
                </div>
            </div>

            <!-- Assign Employees (Super Admin Only) -->
            <div class="form-group" style="margin-bottom:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px;">
                <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:6px; display:block; color:#1e293b;">
                    👥 Assign Employees Incharge (Only Super Admin & Assigned Employees can view this renewal)
                </label>
                <div style="max-height:140px; overflow-y:auto; display:grid; grid-template-columns:1fr 1fr; gap:8px; padding:6px; background:#ffffff; border:1px solid #cbd5e1; border-radius:8px;">
                    <?php foreach ($staffMembers as $st): ?>
                        <label style="display:flex; align-items:center; gap:8px; font-size:12.5px; color:#334155; cursor:pointer; padding:4px 6px; border-radius:4px;">
                            <input type="checkbox" name="renewal_assignees[]" value="<?= $st['id'] ?>" class="renewal-assignee-cb">
                            <span><strong><?= htmlspecialchars($st['full_name']) ?></strong> <small style="color:#64748b;">(<?= htmlspecialchars($st['role_name']) ?>)</small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label" style="font-weight:700; font-size:13px; margin-bottom:4px; display:block;">Notes / IP / Access Details</label>
                <textarea id="ren_notes" class="form-control" rows="2" placeholder="Server IP, account reference, billing cycle notes..."></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid #e2e8f0; padding-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeRenewalModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSaveRenewal">Save Renewal Record</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 3: VIEW DETAILS & QUICK STATUS UPDATE (FOR ASSIGNED EMPLOYEES)      -->
<!-- ========================================================================= -->
<div id="quickStatusModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); z-index:99999; justify-content:center; align-items:center; padding:20px;">
    <div class="modal-content" style="background:#ffffff; width:100%; max-width:480px; border-radius:14px; padding:24px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">
            <h4 id="quickModalTitle" style="margin:0; font-size:16px; font-weight:800; color:#0f172a;">Update Status & Progress</h4>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeQuickStatusModal()" style="border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <form id="quickStatusForm" onsubmit="event.preventDefault(); submitQuickStatus();">
            <input type="hidden" id="qs_item_type" value="shoot"> <!-- 'shoot' or 'renewal' -->
            <input type="hidden" id="qs_item_id" value="">

            <div id="qs_shoot_fields">
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" style="font-weight:700; font-size:12.5px;">Shoot Status</label>
                    <select id="qs_shoot_status" class="form-control">
                        <option value="Scheduled">Scheduled</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Postponed">Postponed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" style="font-weight:700; font-size:12.5px;">Scripts Approved</label>
                    <select id="qs_shoot_scripts" class="form-control">
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Pending">Pending</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" style="font-weight:700; font-size:12.5px;">Shoot Venue Blocked</label>
                    <select id="qs_shoot_venue_blocked" class="form-control">
                        <option value="Yes">Yes (Venue Confirmed)</option>
                        <option value="No">No</option>
                        <option value="Pending from Venue Side">Pending from Venue Side</option>
                        <option value="Pending from Client Side">Pending from Client Side</option>
                    </select>
                </div>
            </div>

            <div id="qs_renewal_fields" style="display:none;">
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" style="font-weight:700; font-size:12.5px;">Renewal Status</label>
                    <select id="qs_ren_status" class="form-control">
                        <option value="Active">Active</option>
                        <option value="Expiring Soon">Expiring Soon</option>
                        <option value="Expired">Expired</option>
                        <option value="Renewed">Renewed</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" style="font-weight:700; font-size:12.5px;">Notes & Updates</label>
                <textarea id="qs_notes" class="form-control" rows="3" placeholder="Add progress note or remarks..."></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-secondary" onclick="closeQuickStatusModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
const IS_SUPER_ADMIN = <?= $isSuperAdmin ? 'true' : 'false' ?>;
let shootsData = [];
let renewalsData = [];
let searchShootTimer = null;
let searchRenewalTimer = null;

document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('tabContentShoots').style.display !== 'none') {
        loadShootSchedules();
    } else {
        loadDomainRenewals();
    }
});

function switchOpsTab(tab) {
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.pushState({}, '', url);

    document.querySelectorAll('.ops-tab-btn').forEach(b => b.classList.remove('active'));
    if (tab === 'shoots') {
        document.querySelectorAll('.ops-tab-btn')[0].classList.add('active');
        document.getElementById('tabContentShoots').style.display = 'block';
        document.getElementById('tabContentRenewals').style.display = 'none';
        if (!shootsData.length) loadShootSchedules();
    } else {
        document.querySelectorAll('.ops-tab-btn')[1].classList.add('active');
        document.getElementById('tabContentShoots').style.display = 'none';
        document.getElementById('tabContentRenewals').style.display = 'block';
        if (!renewalsData.length) loadDomainRenewals();
    }
}

// =========================================================================
// 🎬 SHOOT SCHEDULES JAVASCRIPT
// =========================================================================

function debounceLoadShoots() {
    clearTimeout(searchShootTimer);
    searchShootTimer = setTimeout(loadShootSchedules, 300);
}

function resetShootFilters() {
    document.getElementById('shootSearchInput').value = '';
    document.getElementById('shootStatusFilter').value = '';
    document.getElementById('shootTypeFilter').value = '';
    document.getElementById('shootVenueStatusFilter').value = '';
    loadShootSchedules();
}

function loadShootSchedules() {
    const search = document.getElementById('shootSearchInput')?.value || '';
    const status = document.getElementById('shootStatusFilter')?.value || '';
    const type = document.getElementById('shootTypeFilter')?.value || '';
    const venue = document.getElementById('shootVenueStatusFilter')?.value || '';

    const url = new URL('api.php?action=get_shoot_schedules', window.location.origin + window.location.pathname);
    if (search) url.searchParams.set('search', search);
    if (status) url.searchParams.set('status', status);
    if (type) url.searchParams.set('shoot_type', type);
    if (venue) url.searchParams.set('venue_blocked_status', venue);

    fetch(url)
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                shootsData = res.shoots || [];
                renderShootStats(res.stats || {});
                renderShootsTable(shootsData);
            } else {
                document.getElementById('shootsTableBody').innerHTML = `<tr><td colspan="9" style="text-align:center; color:#ef4444; padding:30px;">${res.message || 'Error loading shoots.'}</td></tr>`;
            }
        })
        .catch(err => {
            document.getElementById('shootsTableBody').innerHTML = `<tr><td colspan="9" style="text-align:center; color:#ef4444; padding:30px;">Network error loading shoots.</td></tr>`;
        });
}

function renderShootStats(stats) {
    document.getElementById('statShootTotal').textContent = stats.total_shoots ?? 0;
    document.getElementById('statShootUpcoming').textContent = stats.upcoming_shoots ?? 0;
    document.getElementById('statShootScripts').textContent = stats.scripts_approved_count ?? 0;
    document.getElementById('statShootVenues').textContent = stats.venue_blocked_yes ?? 0;
}

function renderShootsTable(shoots) {
    const tbody = document.getElementById('shootsTableBody');
    if (!shoots || shoots.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:40px; color:#94a3b8;">
            <i data-feather="film" style="width:32px; height:32px; margin-bottom:8px; opacity:0.5;"></i>
            <p style="margin:0; font-size:14px;">No shoot schedules found matching your criteria.</p>
        </td></tr>`;
        if (window.feather) feather.replace();
        return;
    }

    let html = '';
    shoots.forEach(s => {
        // Venue status badge
        let venueBadgeClass = 'badge-venue-pending-venue';
        if (s.venue_blocked_status === 'Yes') venueBadgeClass = 'badge-venue-yes';
        else if (s.venue_blocked_status === 'No') venueBadgeClass = 'badge-venue-no';
        else if (s.venue_blocked_status === 'Pending from Client Side') venueBadgeClass = 'badge-venue-pending-client';

        // Script badge
        let scriptBadgeClass = 'badge-script-pending';
        if (s.scripts_approved === 'Yes') scriptBadgeClass = 'badge-script-yes';
        else if (s.scripts_approved === 'No') scriptBadgeClass = 'badge-script-no';
        else if (s.scripts_approved === 'In Progress') scriptBadgeClass = 'badge-script-progress';

        // Assigned employees avatars
        let assigneesHtml = '';
        if (s.assigned_employees && s.assigned_employees.length > 0) {
            s.assigned_employees.forEach(emp => {
                assigneesHtml += `<span class="assigned-avatar-pill" title="${escapeHtml(emp.role_name)}"><i data-feather="user"></i> ${escapeHtml(emp.full_name)}</span>`;
            });
        } else {
            assigneesHtml = '<span style="color:#94a3b8; font-size:12px; font-style:italic;">Unassigned</span>';
        }

        // Countdown pill
        const now = new Date().getTime();
        const shootTime = s.shoot_timestamp * 1000;
        const diffDays = Math.ceil((shootTime - now) / (1000 * 60 * 60 * 24));
        let countdownPill = '';
        if (diffDays === 0) {
            countdownPill = '<span class="badge-pill badge-days-urgent">Today</span>';
        } else if (diffDays === 1) {
            countdownPill = '<span class="badge-pill badge-days-warning">Tomorrow</span>';
        } else if (diffDays > 1) {
            countdownPill = `<span class="badge-pill badge-days-ok">In ${diffDays} days</span>`;
        } else {
            countdownPill = '<span class="badge-pill" style="background:#f1f5f9; color:#64748b;">Past</span>';
        }

        html += `<tr>
            <td>
                <div style="font-weight:700; color:#0f172a; font-size:14px;">${escapeHtml(s.client_name)}</div>
                <div style="font-size:11.5px; color:#64748b; margin-top:2px;">
                    <span style="display:inline-flex; align-items:center; gap:3px;"><i data-feather="users" style="width:11px; height:11px;"></i> ${s.client_participants_count || 0} Participants</span>
                </div>
            </td>
            <td>
                <div style="font-weight:600; color:#1e293b;">${s.formatted_date}</div>
                <div style="font-size:12px; color:#64748b;">${s.formatted_time} &bull; ${countdownPill}</div>
            </td>
            <td>
                <span style="display:inline-block; font-weight:600; color:#3b82f6; font-size:12.5px;">${escapeHtml(s.shoot_type)}</span>
            </td>
            <td>
                <span class="badge-pill" style="background:#f1f5f9; color:#1e293b; font-size:12px; font-weight:700;">
                    <i data-feather="video" style="width:12px; height:12px; color:#2563eb;"></i> ${s.videos_planned || 1} Videos
                </span>
            </td>
            <td>
                <span class="badge-pill ${scriptBadgeClass}">${escapeHtml(s.scripts_approved || 'Pending')}</span>
            </td>
            <td>
                <div style="font-weight:600; color:#1e293b; font-size:13px; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    ${escapeHtml(s.shoot_venue)}
                </div>
                <div style="margin-top:3px;">
                    <span class="badge-pill ${venueBadgeClass}">${escapeHtml(s.venue_blocked_status || 'Pending')}</span>
                </div>
            </td>
            <td>
                <div style="display:flex; flex-wrap:wrap; max-width:240px;">${assigneesHtml}</div>
            </td>
            <td>
                <span class="badge badge-${(s.status === 'Completed' ? 'success' : (s.status === 'In Progress' ? 'warning' : 'primary'))}">${escapeHtml(s.status || 'Scheduled')}</span>
            </td>
            <td style="text-align:right; white-space:nowrap;">
                ${IS_SUPER_ADMIN ? `
                    <button class="btn btn-outline btn-sm" onclick="openEditShootModal(${s.id})" title="Edit Shoot" style="padding:4px 8px;">
                        <i data-feather="edit-2" style="width:13px; height:13px;"></i>
                    </button>
                    <button class="btn btn-outline btn-sm text-danger" onclick="deleteShootSchedule(${s.id}, '${escapeHtml(s.client_name)}')" title="Delete Shoot" style="padding:4px 8px; color:#ef4444;">
                        <i data-feather="trash-2" style="width:13px; height:13px;"></i>
                    </button>
                ` : `
                    <button class="btn btn-outline btn-sm" onclick="openQuickStatusModal('shoot', ${s.id})" title="Update Status & Progress" style="padding:4px 10px; font-size:12px;">
                        <i data-feather="check-square" style="width:13px; height:13px; margin-right:4px;"></i> Update
                    </button>
                `}
            </td>
        </tr>`;
    });

    tbody.innerHTML = html;
    if (window.feather) feather.replace();
}

function onShootClientSelect(clientId) {
    if (!clientId) return;
    const opt = document.querySelector(`#shoot_client_id option[value="${clientId}"]`);
    if (opt) {
        document.getElementById('shoot_client_name').value = opt.getAttribute('data-name') || '';
    }
}

function openAddShootModal() {
    document.getElementById('shootModalTitle').innerHTML = '<i data-feather="video" style="color:#2563eb;"></i> <span>Schedule New Shoot</span>';
    document.getElementById('shootForm').reset();
    document.getElementById('shoot_id').value = '';
    document.querySelectorAll('.shoot-assignee-cb').forEach(cb => cb.checked = false);
    document.getElementById('shootModal').style.display = 'flex';
    if (window.feather) feather.replace();
}

function openEditShootModal(id) {
    const shoot = shootsData.find(s => parseInt(s.id) === parseInt(id));
    if (!shoot) return;

    document.getElementById('shootModalTitle').innerHTML = '<i data-feather="edit" style="color:#2563eb;"></i> <span>Edit Shoot Schedule</span>';
    document.getElementById('shoot_id').value = shoot.id;
    document.getElementById('shoot_client_id').value = shoot.client_id || '';
    document.getElementById('shoot_client_name').value = shoot.client_name || '';
    
    // Format shoot_date for datetime-local (YYYY-MM-DDTHH:MM)
    if (shoot.shoot_date) {
        const dt = new Date(shoot.shoot_date);
        const y = dt.getFullYear();
        const m = String(dt.getMonth() + 1).padStart(2, '0');
        const d = String(dt.getDate()).padStart(2, '0');
        const h = String(dt.getHours()).padStart(2, '0');
        const min = String(dt.getMinutes()).padStart(2, '0');
        document.getElementById('shoot_date').value = `${y}-${m}-${d}T${h}:${min}`;
    }

    document.getElementById('shoot_type').value = shoot.shoot_type || 'Client Informative Video';
    document.getElementById('shoot_videos_planned').value = shoot.videos_planned || 1;
    document.getElementById('shoot_client_participants').value = shoot.client_participants_count || 0;
    document.getElementById('shoot_scripts_approved').value = shoot.scripts_approved || 'Pending';
    document.getElementById('shoot_venue_blocked').value = shoot.venue_blocked_status || 'Pending from Venue Side';
    document.getElementById('shoot_venue').value = shoot.shoot_venue || '';
    document.getElementById('shoot_status').value = shoot.status || 'Scheduled';
    document.getElementById('shoot_notes').value = shoot.notes || '';

    // Check assigned employees
    const assignedIds = (shoot.assigned_employees || []).map(e => parseInt(e.id));
    document.querySelectorAll('.shoot-assignee-cb').forEach(cb => {
        cb.checked = assignedIds.includes(parseInt(cb.value));
    });

    document.getElementById('shootModal').style.display = 'flex';
    if (window.feather) feather.replace();
}

function closeShootModal() {
    document.getElementById('shootModal').style.display = 'none';
}

function submitShootForm() {
    const id = document.getElementById('shoot_id').value;
    const clientId = document.getElementById('shoot_client_id').value;
    const clientName = document.getElementById('shoot_client_name').value.trim();
    const shootDate = document.getElementById('shoot_date').value;
    const shootType = document.getElementById('shoot_type').value;
    const videosPlanned = document.getElementById('shoot_videos_planned').value;
    const clientParticipants = document.getElementById('shoot_client_participants').value;
    const scriptsApproved = document.getElementById('shoot_scripts_approved').value;
    const venueBlocked = document.getElementById('shoot_venue_blocked').value;
    const shootVenue = document.getElementById('shoot_venue').value.trim();
    const status = document.getElementById('shoot_status').value;
    const notes = document.getElementById('shoot_notes').value.trim();

    const assignedIds = Array.from(document.querySelectorAll('.shoot-assignee-cb:checked')).map(cb => parseInt(cb.value));

    if (!clientName || !shootDate || !shootVenue) {
        alert('Please fill in all required fields.');
        return;
    }

    const payload = {
        id: id ? parseInt(id) : null,
        client_id: clientId ? parseInt(clientId) : null,
        client_name: clientName,
        shoot_date: shootDate,
        shoot_type: shootType,
        videos_planned: videosPlanned,
        client_participants_count: clientParticipants,
        scripts_approved: scriptsApproved,
        venue_blocked_status: venueBlocked,
        shoot_venue: shootVenue,
        status: status,
        notes: notes,
        assigned_employee_ids: assignedIds
    };

    const btn = document.getElementById('btnSaveShoot');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    fetch('api.php?action=save_shoot_schedule', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.textContent = 'Save Shoot Schedule';
        if (res.success) {
            closeShootModal();
            loadShootSchedules();
        } else {
            alert(res.message || 'Error saving shoot schedule.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = 'Save Shoot Schedule';
        alert('Failed to save shoot schedule.');
    });
}

function deleteShootSchedule(id, clientName) {
    if (!confirm(`Are you sure you want to delete the shoot schedule for "${clientName}"?`)) return;

    fetch('api.php?action=delete_shoot_schedule', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            loadShootSchedules();
        } else {
            alert(res.message || 'Error deleting shoot.');
        }
    });
}


// =========================================================================
// 🌐 DOMAIN & SERVER RENEWALS JAVASCRIPT
// =========================================================================

function debounceLoadRenewals() {
    clearTimeout(searchRenewalTimer);
    searchRenewalTimer = setTimeout(loadDomainRenewals, 300);
}

function resetRenewalFilters() {
    document.getElementById('renewalSearchInput').value = '';
    document.getElementById('renewalStatusFilter').value = '';
    document.getElementById('renewalWindowFilter').value = '';
    loadDomainRenewals();
}

function loadDomainRenewals() {
    const search = document.getElementById('renewalSearchInput')?.value || '';
    const status = document.getElementById('renewalStatusFilter')?.value || '';
    const windowFilter = document.getElementById('renewalWindowFilter')?.value || '';

    const url = new URL('api.php?action=get_domain_renewals', window.location.origin + window.location.pathname);
    if (search) url.searchParams.set('search', search);
    if (status) url.searchParams.set('renewal_status', status);
    if (windowFilter) url.searchParams.set('expiring_window', windowFilter);

    fetch(url)
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                renewalsData = res.renewals || [];
                renderRenewalStats(res.stats || {});
                renderRenewalsTable(renewalsData);
            } else {
                document.getElementById('renewalsTableBody').innerHTML = `<tr><td colspan="8" style="text-align:center; color:#ef4444; padding:30px;">${res.message || 'Error loading renewals.'}</td></tr>`;
            }
        })
        .catch(err => {
            document.getElementById('renewalsTableBody').innerHTML = `<tr><td colspan="8" style="text-align:center; color:#ef4444; padding:30px;">Network error loading renewals.</td></tr>`;
        });
}

function renderRenewalStats(stats) {
    document.getElementById('statRenTotal').textContent = stats.total_records ?? 0;
    document.getElementById('statRenExpiringSoon').textContent = (stats.domains_expiring_soon || 0) + (stats.servers_expiring_soon || 0);
    document.getElementById('statRenDomainSum').textContent = '₹' + Number(stats.total_domain_charges || 0).toLocaleString();
    document.getElementById('statRenServerSum').textContent = '₹' + Number(stats.total_server_charges || 0).toLocaleString();
}

function renderRenewalsTable(renewals) {
    const tbody = document.getElementById('renewalsTableBody');
    if (!renewals || renewals.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:40px; color:#94a3b8;">
            <i data-feather="globe" style="width:32px; height:32px; margin-bottom:8px; opacity:0.5;"></i>
            <p style="margin:0; font-size:14px;">No domain & server renewal records found.</p>
        </td></tr>`;
        if (window.feather) feather.replace();
        return;
    }

    let html = '';
    renewals.forEach(r => {
        // Domain days badge
        let domBadge = '';
        if (r.domain_days_left !== null) {
            if (r.domain_days_left < 0) domBadge = `<span class="badge-pill badge-days-expired">Expired (${Math.abs(r.domain_days_left)}d ago)</span>`;
            else if (r.domain_days_left <= 30) domBadge = `<span class="badge-pill badge-days-urgent">${r.domain_days_left} days left</span>`;
            else if (r.domain_days_left <= 60) domBadge = `<span class="badge-pill badge-days-warning">${r.domain_days_left} days</span>`;
            else domBadge = `<span class="badge-pill badge-days-ok">${r.domain_days_left} days</span>`;
        }

        // Server days badge
        let srvBadge = '';
        if (r.server_days_left !== null) {
            if (r.server_days_left < 0) srvBadge = `<span class="badge-pill badge-days-expired">Expired (${Math.abs(r.server_days_left)}d ago)</span>`;
            else if (r.server_days_left <= 30) srvBadge = `<span class="badge-pill badge-days-urgent">${r.server_days_left} days left</span>`;
            else if (r.server_days_left <= 60) srvBadge = `<span class="badge-pill badge-days-warning">${r.server_days_left} days</span>`;
            else srvBadge = `<span class="badge-pill badge-days-ok">${r.server_days_left} days</span>`;
        }

        // Assigned incharge
        let assigneesHtml = '';
        if (r.assigned_employees && r.assigned_employees.length > 0) {
            r.assigned_employees.forEach(emp => {
                assigneesHtml += `<span class="assigned-avatar-pill" title="${escapeHtml(emp.role_name)}"><i data-feather="user"></i> ${escapeHtml(emp.full_name)}</span>`;
            });
        } else {
            assigneesHtml = '<span style="color:#94a3b8; font-size:12px; font-style:italic;">Unassigned</span>';
        }

        const totalCharges = Number(r.domain_charges || 0) + Number(r.server_charges || 0);

        html += `<tr>
            <td>
                <div style="font-weight:700; color:#0f172a; font-size:14px; display:flex; align-items:center; gap:6px;">
                    <i data-feather="globe" style="width:14px; height:14px; color:#2563eb;"></i>
                    <a href="https://${escapeHtml(r.domain_name)}" target="_blank" style="color:#2563eb; text-decoration:none;">${escapeHtml(r.domain_name)}</a>
                </div>
                ${r.hosting_provider ? `<div style="font-size:11.5px; color:#64748b; margin-top:2px;">Host: <strong>${escapeHtml(r.hosting_provider)}</strong></div>` : ''}
            </td>
            <td>
                <div style="font-weight:600; color:#1e293b;">${escapeHtml(r.client_name)}</div>
                ${r.contact_number ? `<div style="font-size:12px; color:#64748b; margin-top:2px;"><i data-feather="phone" style="width:11px; height:11px;"></i> ${escapeHtml(r.contact_number)}</div>` : ''}
            </td>
            <td>
                <div style="font-weight:600; color:#1e293b;">${r.formatted_domain_expiry}</div>
                <div style="margin-top:2px;">${domBadge}</div>
                <div style="font-size:11.5px; color:#475569; margin-top:3px;">₹${Number(r.domain_charges || 0).toLocaleString()}</div>
            </td>
            <td>
                <div style="font-weight:600; color:#1e293b;">${r.formatted_server_expiry}</div>
                <div style="margin-top:2px;">${srvBadge}</div>
                <div style="font-size:11.5px; color:#475569; margin-top:3px;">₹${Number(r.server_charges || 0).toLocaleString()}</div>
            </td>
            <td>
                <div style="font-weight:800; color:#0f172a; font-size:14px;">₹${totalCharges.toLocaleString()}</div>
                <div style="font-size:11px; color:#94a3b8;">Total Annual</div>
            </td>
            <td>
                <div style="display:flex; flex-wrap:wrap; max-width:200px;">${assigneesHtml}</div>
            </td>
            <td>
                <span class="badge badge-${(r.renewal_status === 'Active' ? 'success' : (r.renewal_status === 'Expiring Soon' ? 'warning' : (r.renewal_status === 'Expired' ? 'danger' : 'info')))}">
                    ${escapeHtml(r.renewal_status || 'Active')}
                </span>
            </td>
            <td style="text-align:right; white-space:nowrap;">
                ${IS_SUPER_ADMIN ? `
                    <button class="btn btn-outline btn-sm" onclick="openEditRenewalModal(${r.id})" title="Edit Renewal" style="padding:4px 8px;">
                        <i data-feather="edit-2" style="width:13px; height:13px;"></i>
                    </button>
                    <button class="btn btn-outline btn-sm text-danger" onclick="deleteDomainRenewal(${r.id}, '${escapeHtml(r.domain_name)}')" title="Delete Renewal" style="padding:4px 8px; color:#ef4444;">
                        <i data-feather="trash-2" style="width:13px; height:13px;"></i>
                    </button>
                ` : `
                    <button class="btn btn-outline btn-sm" onclick="openQuickStatusModal('renewal', ${r.id})" title="Update Renewal Status" style="padding:4px 10px; font-size:12px;">
                        <i data-feather="check-square" style="width:13px; height:13px; margin-right:4px;"></i> Update
                    </button>
                `}
            </td>
        </tr>`;
    });

    tbody.innerHTML = html;
    if (window.feather) feather.replace();
}

function onRenewalClientSelect(clientId) {
    if (!clientId) return;
    const opt = document.querySelector(`#ren_client_id option[value="${clientId}"]`);
    if (opt) {
        document.getElementById('ren_client_name').value = opt.getAttribute('data-name') || '';
        document.getElementById('ren_contact_number').value = opt.getAttribute('data-phone') || '';
    }
}

function openAddRenewalModal() {
    document.getElementById('renewalModalTitle').innerHTML = '<i data-feather="globe" style="color:#2563eb;"></i> <span>Add Domain & Server Renewal</span>';
    document.getElementById('renewalForm').reset();
    document.getElementById('renewal_id').value = '';
    document.querySelectorAll('.renewal-assignee-cb').forEach(cb => cb.checked = false);
    document.getElementById('renewalModal').style.display = 'flex';
    if (window.feather) feather.replace();
}

function openEditRenewalModal(id) {
    const ren = renewalsData.find(r => parseInt(r.id) === parseInt(id));
    if (!ren) return;

    document.getElementById('renewalModalTitle').innerHTML = '<i data-feather="edit" style="color:#2563eb;"></i> <span>Edit Domain & Server Renewal</span>';
    document.getElementById('renewal_id').value = ren.id;
    document.getElementById('ren_client_id').value = ren.client_id || '';
    document.getElementById('ren_client_name').value = ren.client_name || '';
    document.getElementById('ren_contact_number').value = ren.contact_number || '';
    document.getElementById('ren_domain_name').value = ren.domain_name || '';
    document.getElementById('ren_domain_expiry').value = ren.domain_expiry || '';
    document.getElementById('ren_domain_charges').value = ren.domain_charges || '';
    document.getElementById('ren_server_expiry').value = ren.server_expiry || '';
    document.getElementById('ren_server_charges').value = ren.server_charges || '';
    document.getElementById('ren_hosting_provider').value = ren.hosting_provider || '';
    document.getElementById('ren_renewal_status').value = ren.renewal_status || 'Active';
    document.getElementById('ren_notes').value = ren.notes || '';

    // Check assigned employees
    const assignedIds = (ren.assigned_employees || []).map(e => parseInt(e.id));
    document.querySelectorAll('.renewal-assignee-cb').forEach(cb => {
        cb.checked = assignedIds.includes(parseInt(cb.value));
    });

    document.getElementById('renewalModal').style.display = 'flex';
    if (window.feather) feather.replace();
}

function closeRenewalModal() {
    document.getElementById('renewalModal').style.display = 'none';
}

function submitRenewalForm() {
    const id = document.getElementById('renewal_id').value;
    const clientId = document.getElementById('ren_client_id').value;
    const clientName = document.getElementById('ren_client_name').value.trim();
    const contactNumber = document.getElementById('ren_contact_number').value.trim();
    const domainName = document.getElementById('ren_domain_name').value.trim();
    const domainExpiry = document.getElementById('ren_domain_expiry').value;
    const domainCharges = document.getElementById('ren_domain_charges').value;
    const serverExpiry = document.getElementById('ren_server_expiry').value;
    const serverCharges = document.getElementById('ren_server_charges').value;
    const hostingProvider = document.getElementById('ren_hosting_provider').value.trim();
    const renewalStatus = document.getElementById('ren_renewal_status').value;
    const notes = document.getElementById('ren_notes').value.trim();

    const assignedIds = Array.from(document.querySelectorAll('.renewal-assignee-cb:checked')).map(cb => parseInt(cb.value));

    if (!domainName || !clientName) {
        alert('Domain Name and Client Name are required.');
        return;
    }

    const payload = {
        id: id ? parseInt(id) : null,
        client_id: clientId ? parseInt(clientId) : null,
        client_name: clientName,
        contact_number: contactNumber,
        domain_name: domainName,
        domain_expiry: domainExpiry || null,
        domain_charges: domainCharges ? parseFloat(domainCharges) : 0,
        server_expiry: serverExpiry || null,
        server_charges: serverCharges ? parseFloat(serverCharges) : 0,
        hosting_provider: hostingProvider,
        renewal_status: renewalStatus,
        notes: notes,
        assigned_employee_ids: assignedIds
    };

    const btn = document.getElementById('btnSaveRenewal');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    fetch('api.php?action=save_domain_renewal', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.textContent = 'Save Renewal Record';
        if (res.success) {
            closeRenewalModal();
            loadDomainRenewals();
        } else {
            alert(res.message || 'Error saving renewal record.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = 'Save Renewal Record';
        alert('Failed to save renewal record.');
    });
}

function deleteDomainRenewal(id, domainName) {
    if (!confirm(`Are you sure you want to delete the renewal tracking for "${domainName}"?`)) return;

    fetch('api.php?action=delete_domain_renewal', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            loadDomainRenewals();
        } else {
            alert(res.message || 'Error deleting renewal.');
        }
    });
}


// =========================================================================
// 🚀 QUICK STATUS MODAL (FOR ASSIGNED EMPLOYEES)
// =========================================================================

function openQuickStatusModal(itemType, id) {
    document.getElementById('qs_item_type').value = itemType;
    document.getElementById('qs_item_id').value = id;

    if (itemType === 'shoot') {
        const item = shootsData.find(s => parseInt(s.id) === parseInt(id));
        if (!item) return;
        document.getElementById('quickModalTitle').textContent = `Update Shoot: ${item.client_name}`;
        document.getElementById('qs_shoot_fields').style.display = 'block';
        document.getElementById('qs_renewal_fields').style.display = 'none';
        document.getElementById('qs_shoot_status').value = item.status || 'Scheduled';
        document.getElementById('qs_shoot_scripts').value = item.scripts_approved || 'Pending';
        document.getElementById('qs_shoot_venue_blocked').value = item.venue_blocked_status || 'Pending from Venue Side';
        document.getElementById('qs_notes').value = item.notes || '';
    } else {
        const item = renewalsData.find(r => parseInt(r.id) === parseInt(id));
        if (!item) return;
        document.getElementById('quickModalTitle').textContent = `Update Renewal: ${item.domain_name}`;
        document.getElementById('qs_shoot_fields').style.display = 'none';
        document.getElementById('qs_renewal_fields').style.display = 'block';
        document.getElementById('qs_ren_status').value = item.renewal_status || 'Active';
        document.getElementById('qs_notes').value = item.notes || '';
    }

    document.getElementById('quickStatusModal').style.display = 'flex';
}

function closeQuickStatusModal() {
    document.getElementById('quickStatusModal').style.display = 'none';
}

function submitQuickStatus() {
    const itemType = document.getElementById('qs_item_type').value;
    const id = parseInt(document.getElementById('qs_item_id').value);
    const notes = document.getElementById('qs_notes').value.trim();

    if (itemType === 'shoot') {
        const status = document.getElementById('qs_shoot_status').value;
        const scripts = document.getElementById('qs_shoot_scripts').value;
        const venueBlocked = document.getElementById('qs_shoot_venue_blocked').value;

        fetch('api.php?action=update_shoot_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: id,
                status: status,
                scripts_approved: scripts,
                venue_blocked_status: venueBlocked,
                notes: notes
            })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                closeQuickStatusModal();
                loadShootSchedules();
            } else {
                alert(res.message || 'Error updating shoot.');
            }
        });
    } else {
        const status = document.getElementById('qs_ren_status').value;

        fetch('api.php?action=update_domain_renewal_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: id,
                renewal_status: status,
                notes: notes
            })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                closeQuickStatusModal();
                loadDomainRenewals();
            } else {
                alert(res.message || 'Error updating renewal.');
            }
        });
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

