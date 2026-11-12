<?php
// calendar.php - Ultra-Premium Bitrix24-Style Interactive Task Calendar & Work Scheduling Hub

require_once __DIR__ . '/auth.php';
checkAuth('task-management');

$user = getCurrentUser();
$db = getDbConnection();
$isLead = isLeadOrAdmin();

$pageBreadcrumb = "Module 4 &bull; Scheduling";
$pageTitle = "Task Calendar & Work Scheduler";
$pageSubtitle = "Visual task planning, start & end date management, developer assignment, and SLA tracking.";

// Fetch active developers and staff
$developers = $db->query("SELECT u.*, r.name as role_name 
                          FROM users u 
                          JOIN roles r ON u.role_id = r.id 
                          WHERE u.is_active = 1 
                          ORDER BY u.full_name ASC")->fetchAll();

// Fetch clients & active projects
$projects = $db->query("SELECT p.*, c.company_name 
                        FROM projects p 
                        JOIN clients c ON p.client_id = c.id 
                        ORDER BY p.project_name ASC")->fetchAll();

$clients = $db->query("SELECT * FROM clients ORDER BY company_name ASC")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<!-- Calendar Stylesheet & FullCalendar Engine -->
<link rel="stylesheet" href="asset/calendar.css">
<script src="asset/fullcalendar.min.js"></script>

<div class="calendar-page-container">

    <!-- ========================================================= -->
    <!-- BITRIX24 STYLE TOP CONTROL BAR                            -->
    <!-- ========================================================= -->
    <div class="calendar-top-bar">
        <div class="calendar-top-left">
            <!-- + Create Button -->
            <?php if ($isLead): ?>
                <button class="btn-pill-primary" onclick="openCreateCalendarTaskModal()" style="padding: 9px 18px;">
                    <i data-feather="plus" class="icon-prefix"></i>
                    <span>+ Create Task</span>
                </button>
            <?php endif; ?>

            <!-- Calendar Mode Switcher (Bitrix24 style: My Calendar vs Company Calendar) -->
            <div style="position:relative; display:inline-block;">
                <button type="button" class="calendar-view-dropdown-btn" onclick="toggleCalendarModeMenu(event)" id="calModeBtn">
                    <i data-feather="calendar" style="width:16px; height:16px; color:#2563eb;"></i>
                    <span id="calModeLabel"><?= !$isLead ? 'My Calendar' : 'Company Calendar' ?></span>
                    <i data-feather="chevron-down" style="width:14px; height:14px; color:#64748b;"></i>
                </button>

                <div id="calModeMenu" class="dropdown-menu-premium" style="top:115%; left:0; min-width:210px; display:none;">
                    <?php if ($isLead): ?>
                    <a class="dropdown-item" href="javascript:void(0)" onclick="setCalendarMode('company')">
                        <i data-feather="globe"></i>
                        <span>Company Calendar (All)</span>
                    </a>
                    <?php endif; ?>
                    <a class="dropdown-item" href="javascript:void(0)" onclick="setCalendarMode('my')">
                        <i data-feather="user"></i>
                        <span>My Calendar (Assigned to Me)</span>
                    </a>
                </div>
            </div>

            <!-- Real-time Search Box -->
            <div class="cal-search-box">
                <i data-feather="search" class="cal-search-icon"></i>
                <input type="text" class="cal-search-input" id="calSearchInput" placeholder="Search tasks, projects, developers..." oninput="debounceFilter()">
            </div>
        </div>

        <div class="calendar-top-right">
            <!-- Month Title & Navigation -->
            <div class="cal-nav-group">
                <button type="button" class="cal-nav-btn" onclick="navCalendar('prev')" title="Previous">
                    <i data-feather="chevron-left" style="width:18px; height:18px;"></i>
                </button>
                <button type="button" class="cal-today-btn" onclick="navCalendar('today')">
                    Today
                </button>
                <button type="button" class="cal-nav-btn" onclick="navCalendar('next')" title="Next">
                    <i data-feather="chevron-right" style="width:18px; height:18px;"></i>
                </button>
                <span class="cal-current-title" id="calCurrentMonthTitle">October, 2026</span>
            </div>

            <!-- View Switcher (Day, Week, Month, List) -->
            <div class="cal-view-pills">
                <button type="button" class="cal-view-pill-btn" data-view="timeGridDay" onclick="switchCalendarView('timeGridDay', this)">Day</button>
                <button type="button" class="cal-view-pill-btn" data-view="timeGridWeek" onclick="switchCalendarView('timeGridWeek', this)">Week</button>
                <button type="button" class="cal-view-pill-btn active" data-view="dayGridMonth" onclick="switchCalendarView('dayGridMonth', this)">Month</button>
                <button type="button" class="cal-view-pill-btn" data-view="listMonth" onclick="switchCalendarView('listMonth', this)">List</button>
            </div>

            <!-- Link to Kanban Table View in projects.php -->
            <a href="projects.php" class="btn btn-outline btn-sm" style="font-weight:700; border-radius:10px; padding:7px 14px; text-decoration:none;" title="Switch to Kanban Board View">
                <i data-feather="trello" style="width:15px; height:15px;"></i> Kanban
            </a>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- SECONDARY FILTER & LEGEND BAR                             -->
    <!-- ========================================================= -->
    <div class="calendar-filter-bar">
        <div class="calendar-filters-left">
            <!-- Project Filter -->
            <select id="calFilterProject" class="form-select" style="font-size:12.5px; width:190px;" onchange="reloadCalendarEvents()">
                <option value="0">All Projects</option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Developer Filter (If Admin/Lead) -->
            <?php if ($isLead): ?>
                <select id="calFilterDeveloper" class="form-select" style="font-size:12.5px; width:170px;" onchange="reloadCalendarEvents()">
                    <option value="0">All Developers</option>
                    <?php foreach ($developers as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <!-- Status Filter -->
            <select id="calFilterStatus" class="form-select" style="font-size:12.5px; width:160px;" onchange="reloadCalendarEvents()">
                <option value="">All Statuses</option>
                <option value="PENDING">Pending</option>
                <option value="IN PROGRESS">In Progress</option>
                <option value="UNDER REVIEW">Under Review</option>
                <option value="REVISION REQUIRED">Revision Needed</option>
                <option value="COMPLETED">Completed</option>
            </select>

            <!-- Priority Filter -->
            <select id="calFilterPriority" class="form-select" style="font-size:12.5px; width:140px;" onchange="reloadCalendarEvents()">
                <option value="">All Priorities</option>
                <option value="Urgent">Urgent</option>
                <option value="High">High</option>
                <option value="Medium">Medium</option>
                <option value="Low">Low</option>
            </select>

            <button type="button" class="btn btn-outline btn-sm" onclick="reloadCalendarEvents()" title="Refresh Calendar">
                <i data-feather="refresh-cw" style="width:14px; height:14px;"></i>
            </button>
        </div>

        <!-- Visual Status Legend -->
        <div class="calendar-legend-bar">
            <span class="legend-item"><span class="legend-dot" style="background:#2563eb;"></span> In Progress</span>
            <span class="legend-item"><span class="legend-dot" style="background:#10b981;"></span> Completed</span>
            <span class="legend-item"><span class="legend-dot" style="background:#f59e0b;"></span> Under Review</span>
            <span class="legend-item"><span class="legend-dot" style="background:#ef4444;"></span> Revision Needed</span>
            <span class="legend-item"><span class="legend-dot" style="background:#64748b;"></span> Pending</span>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- INTERACTIVE CALENDAR CANVAS CARD                          -->
    <!-- ========================================================= -->
    <div class="calendar-canvas-card">
        <div id="calendarEl"></div>
    </div>

</div>

<!-- ========================================================= -->
<!-- MODAL: QUICK CREATE & ASSIGN TASK (From Calendar)        -->
<!-- ========================================================= -->
<div id="createCalendarTaskModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:680px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i data-feather="calendar" style="width:20px; height:20px; color:#2563eb;"></i>
                Create & Assign Task
            </h3>
            <button class="btn btn-outline btn-sm" onclick="closeCalendarModal('createCalendarTaskModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitCreateCalendarTask();">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Project *</label>
                    <select id="quick_project_id" class="form-select" required>
                        <option value="">-- Choose Project --</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['company_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Task Title *</label>
                    <input type="text" id="quick_title" class="form-control" required placeholder="e.g. Website Front-End Redesign & Testing">
                </div>

                <div class="form-group">
                    <label class="form-label">Task Description & Requirements</label>
                    <textarea id="quick_description" class="form-control" rows="3" placeholder="Provide clear technical deliverables, instructions, or scope..."></textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Assign To Developer *</label>
                        <select id="quick_assigned_to" class="form-select" required>
                            <option value="">-- Select Developer --</option>
                            <?php foreach ($developers as $d): ?>
                                <option value="<?= $d['id'] ?>">
                                    <?= htmlspecialchars($d['full_name']) ?> (<?= htmlspecialchars($d['role_name'] ?? $d['department']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Priority *</label>
                        <select id="quick_priority" class="form-select" required>
                            <option value="Urgent">Urgent</option>
                            <option value="High" selected>High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Start Date & Time *</label>
                        <input type="datetime-local" id="quick_start_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">End Date & Time (Deadline) *</label>
                        <input type="datetime-local" id="quick_end_date" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">SLA Hours (Calculated automatically from dates)</label>
                    <input type="number" id="quick_sla_hours" class="form-control" min="1" max="1000" value="24" placeholder="24">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCalendarModal('createCalendarTaskModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitQuickCreate">
                    <i data-feather="check"></i> Create & Schedule Task
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================= -->
<!-- MODAL: TASK DETAIL & QUICK SCHEDULE MANAGER (On Click)    -->
<!-- ========================================================= -->
<div id="editCalendarTaskModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:680px;">
        <div class="modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <span id="ed_task_code_badge" class="badge badge-primary" style="font-size:13px; font-weight:800;">TSK-000</span>
                <h3 class="modal-title" id="ed_modal_title">Task Schedule Manager</h3>
            </div>
            <button class="btn btn-outline btn-sm" onclick="closeCalendarModal('editCalendarTaskModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitUpdateCalendarTask();">
            <div class="modal-body">
                <input type="hidden" id="ed_task_id" value="0">

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 16px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div>
                        <div style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase;">Project & Client</div>
                        <strong id="ed_project_name" style="font-size:14px; color:#0f172a;">-</strong>
                    </div>
                    <div>
                        <span id="ed_sla_badge" class="badge badge-primary" style="font-size:12px;">Within SLA</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Task Title *</label>
                    <input type="text" id="ed_title" class="form-control" required <?= !$isLead ? 'readonly' : '' ?>>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Assigned Developer *</label>
                        <?php if ($isLead): ?>
                            <select id="ed_assigned_to" class="form-select" required>
                                <option value="">-- Choose Developer --</option>
                                <?php foreach ($developers as $d): ?>
                                    <option value="<?= $d['id'] ?>">
                                        <?= htmlspecialchars($d['full_name']) ?> (<?= htmlspecialchars($d['role_name'] ?? $d['department']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" id="ed_assigned_to_name" class="form-control" readonly>
                            <input type="hidden" id="ed_assigned_to">
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Project</label>
                        <?php if ($isLead): ?>
                            <select id="ed_project_id" class="form-select">
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" id="ed_project_name_static" class="form-control" readonly>
                            <input type="hidden" id="ed_project_id">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Start Date & Time *</label>
                        <input type="datetime-local" id="ed_start_date" class="form-control" required>
                        <small style="color:var(--text-muted); font-size:11px;">You can also drag the task on the calendar to adjust dates</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">End Date & Time (Deadline) *</label>
                        <input type="datetime-local" id="ed_end_date" class="form-control" required>
                        <small style="color:var(--text-muted); font-size:11px;">Resize task bar edges on the calendar to lengthen/shorten</small>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Task Status *</label>
                        <select id="ed_status" class="form-select" required>
                            <option value="PENDING">⏳ Pending</option>
                            <option value="IN PROGRESS">⚡ In Progress</option>
                            <option value="UNDER REVIEW">🔍 Under Review</option>
                            <option value="REVISION REQUIRED">🔄 Revision Needed</option>
                            <option value="COMPLETED">✅ Completed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Priority *</label>
                        <select id="ed_priority" class="form-select" required <?= !$isLead ? 'disabled' : '' ?>>
                            <option value="Urgent">Urgent</option>
                            <option value="High">High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Task Description</label>
                    <textarea id="ed_description" class="form-control" rows="3" <?= !$isLead ? 'readonly' : '' ?>></textarea>
                </div>
            </div>
            <div class="modal-footer" style="justify-content:space-between;">
                <div>
                    <?php if ($isLead): ?>
                        <button type="button" class="btn btn-outline btn-sm" style="color:#ef4444; border-color:#fca5a5;" onclick="deleteCalendarTask()" title="Delete this task">
                            <i data-feather="trash-2"></i> Delete
                        </button>
                    <?php endif; ?>
                </div>
                <div style="display:flex; gap:8px;">
                    <a id="ed_view_full_btn" href="#" class="btn btn-outline" target="_blank" title="Open full comments, audit trail and review workspace">
                        Workspace <i data-feather="external-link" style="width:14px; height:14px; margin-left:4px;"></i>
                    </a>
                    <button type="button" class="btn btn-secondary" onclick="closeCalendarModal('editCalendarTaskModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveTaskDates">
                        <i data-feather="save"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Floating Animated Toast Notification -->
<div id="calToast" class="cal-toast">
    <span class="cal-toast-icon" id="calToastIcon">⚡</span>
    <span id="calToastMsg">Dates updated successfully</span>
</div>

<!-- ========================================================= -->
<!-- JAVASCRIPT LOGIC & FULLCALENDAR ENGINE                    -->
<!-- ========================================================= -->
<script>
    let calendarInstance = null;
    let calendarMode = '<?= !$isLead ? 'my' : 'company' ?>';
    let debounceTimer = null;
    const currentUserId = <?= intval($user['id']) ?>;
    const currentUserRole = <?= json_encode($user['role']) ?>;
    const isLeadUser = <?= json_encode($isLead) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        initFullCalendar();
        if (window.feather) feather.replace();
    });

    function initFullCalendar() {
        const calendarEl = document.getElementById('calendarEl');

        calendarInstance = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: false, // We use the custom modern Bitrix24 control bar!
            editable: true,       // Enables interactive Drag & Drop and Resizing!
            eventResizableFromStart: true, // Enables dragging left/start handle!
            selectable: true,     // Enables dragging across days or clicking a date cell!
            selectMirror: true,
            dayMaxEvents: 4,      // Shows "+more" if multiple tasks on same day
            navLinks: true,
            nowIndicator: true,
            timeZone: 'local',

            // Dates rendered callback - update the Month title e.g. "October, 2026"
            datesSet: function(info) {
                updateMonthTitle(info.view.title);
            },

            // 1. DRAG AND DROP: Moving tasks to new dates
            eventDrop: function(info) {
                handleTaskDateChange(info);
            },

            // 2. RESIZE: Dragging left or right edges to extend or shorten duration
            eventResize: function(info) {
                handleTaskDateChange(info);
            },

            // 3. DATE CLICK / SELECTION: Quick create task pre-filled with selected dates
            select: function(info) {
                openCreateWithSelectedDates(info.startStr, info.endStr, info.allDay);
            },

            // 4. TASK CLICK: Open Task Schedule Manager Modal
            eventClick: function(info) {
                openEditCalendarTask(info.event);
            },

            // 5. CUSTOM EVENT RENDERING (Bitrix24 Pill Design)
            eventContent: function(arg) {
                const props = arg.event.extendedProps || {};
                const taskCode = props.task_code || '';
                const rawTitle = props.raw_title || arg.event.title;
                const assigneeName = props.assigned_to_name || '';

                const customEl = document.createElement('div');
                customEl.className = 'cal-event-pill';
                customEl.innerHTML = `
                    <span class="cal-event-dot"></span>
                    ${taskCode ? `<span class="cal-event-code">${escapeHtml(taskCode)}</span>` : ''}
                    <span class="cal-event-title">${escapeHtml(rawTitle)}</span>
                    ${assigneeName ? `<span class="cal-event-assignee">${escapeHtml(assigneeName.split(' ')[0])}</span>` : ''}
                `;
                return { domNodes: [customEl] };
            },

            // 6. DYNAMIC EVENT FEED FROM API
            events: function(fetchInfo, successCallback, failureCallback) {
                const projectId = document.getElementById('calFilterProject') ? document.getElementById('calFilterProject').value : 0;
                const devElem = document.getElementById('calFilterDeveloper');
                const devId = devElem ? devElem.value : 0;
                const status = document.getElementById('calFilterStatus') ? document.getElementById('calFilterStatus').value : '';
                const priority = document.getElementById('calFilterPriority') ? document.getElementById('calFilterPriority').value : '';
                const search = document.getElementById('calSearchInput') ? document.getElementById('calSearchInput').value.trim() : '';

                let url = `api.php?action=get_calendar_tasks&view_mode=${calendarMode}&developer_id=${devId}&project_id=${projectId}&status=${encodeURIComponent(status)}&priority=${encodeURIComponent(priority)}&search=${encodeURIComponent(search)}&start=${encodeURIComponent(fetchInfo.startStr)}&end=${encodeURIComponent(fetchInfo.endStr)}`;

                fetch(url)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && Array.isArray(data.events)) {
                            successCallback(data.events);
                        } else {
                            successCallback([]);
                        }
                    })
                    .catch(err => {
                        console.error('Failed to fetch calendar tasks:', err);
                        failureCallback(err);
                    });
            }
        });

        calendarInstance.render();
    }

    // =========================================================
    // DRAG-AND-DROP & RESIZE HANDLER (AJAX DB SYNC)
    // =========================================================
    function handleTaskDateChange(info) {
        const event = info.event;
        const taskId = event.id;
        
        let startStr = formatISODateTime(event.start);
        let endStr = event.end ? formatISODateTime(event.end) : null;

        // If all-day event and no end or end is same day, make it end of day
        if (!endStr) {
            let nextDay = new Date(event.start);
            nextDay.setDate(nextDay.getDate() + 1);
            endStr = formatISODateTime(nextDay);
        }

        fetch('api.php?action=update_task_dates', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                task_id: taskId,
                start_date: startStr,
                end_date: endStr
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(`🗓️ ${data.message}`, '⚡');
                // Update local event props
                event.setExtendedProp('start_date', data.start_date);
                event.setExtendedProp('end_date', data.end_date);
            } else {
                alert('Could not update dates: ' + data.message);
                info.revert();
            }
        })
        .catch(err => {
            console.error('Error updating dates:', err);
            alert('Network error while updating dates. Reverting.');
            info.revert();
        });
    }

    function formatISODateTime(dateObj) {
        if (!dateObj) return '';
        const y = dateObj.getFullYear();
        const m = String(dateObj.getMonth() + 1).padStart(2, '0');
        const d = String(dateObj.getDate()).padStart(2, '0');
        const h = String(dateObj.getHours()).padStart(2, '0');
        const min = String(dateObj.getMinutes()).padStart(2, '0');
        const s = String(dateObj.getSeconds()).padStart(2, '0');
        return `${y}-${m}-${d} ${h}:${min}:${s}`;
    }

    function formatForInput(dateObj) {
        if (!dateObj) return '';
        const y = dateObj.getFullYear();
        const m = String(dateObj.getMonth() + 1).padStart(2, '0');
        const d = String(dateObj.getDate()).padStart(2, '0');
        const h = String(dateObj.getHours()).padStart(2, '0');
        const min = String(dateObj.getMinutes()).padStart(2, '0');
        return `${y}-${m}-${d}T${h}:${min}`;
    }

    // =========================================================
    // NAVIGATION & VIEW SWITCHING CONTROLS
    // =========================================================
    function navCalendar(action) {
        if (!calendarInstance) return;
        if (action === 'prev') calendarInstance.prev();
        else if (action === 'next') calendarInstance.next();
        else if (action === 'today') calendarInstance.today();
    }

    function switchCalendarView(viewName, btn) {
        if (!calendarInstance) return;
        calendarInstance.changeView(viewName);

        document.querySelectorAll('.cal-view-pill-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
    }

    function updateMonthTitle(title) {
        const titleEl = document.getElementById('calCurrentMonthTitle');
        if (titleEl) {
            titleEl.textContent = title;
        }
    }

    function reloadCalendarEvents() {
        if (calendarInstance) {
            calendarInstance.refetchEvents();
        }
    }

    function debounceFilter() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            reloadCalendarEvents();
        }, 300);
    }

    // =========================================================
    // CALENDAR MODE DROPDOWN (My Calendar vs Company Calendar)
    // =========================================================
    function toggleCalendarModeMenu(e) {
        e.stopPropagation();
        const menu = document.getElementById('calModeMenu');
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    }

    document.addEventListener('click', () => {
        const menu = document.getElementById('calModeMenu');
        if (menu) menu.style.display = 'none';
    });

    function setCalendarMode(mode) {
        calendarMode = mode;
        const label = document.getElementById('calModeLabel');
        if (mode === 'my') {
            label.textContent = 'My Calendar';
        } else {
            label.textContent = 'Company Calendar';
        }
        reloadCalendarEvents();
    }

    // =========================================================
    // QUICK CREATE TASK MODAL
    // =========================================================
    function openCreateCalendarTaskModal() {
        const now = new Date();
        const in24 = new Date(now.getTime() + (24 * 3600 * 1000));

        document.getElementById('quick_title').value = '';
        document.getElementById('quick_description').value = '';
        document.getElementById('quick_start_date').value = formatForInput(now);
        document.getElementById('quick_end_date').value = formatForInput(in24);
        document.getElementById('quick_sla_hours').value = '24';

        document.getElementById('createCalendarTaskModal').style.display = 'flex';
        if (window.feather) feather.replace();
    }

    function openCreateWithSelectedDates(startStr, endStr, allDay) {
        if (!isLeadUser) {
            // Only Leads/Admins can create tasks
            return;
        }

        let startDate = new Date(startStr);
        let endDate = endStr ? new Date(endStr) : new Date(startDate.getTime() + 24 * 3600 * 1000);

        // If clicking a single date in month view, set 9am to 6pm
        if (allDay) {
            startDate.setHours(9, 0, 0, 0);
            if (endStr && endStr !== startStr) {
                // Dragged multiple days in month view
                endDate.setHours(18, 0, 0, 0);
            } else {
                endDate = new Date(startDate);
                endDate.setHours(18, 0, 0, 0);
            }
        }

        // Calculate hours
        const diffHours = Math.max(1, Math.round((endDate - startDate) / (1000 * 3600)));

        document.getElementById('quick_title').value = '';
        document.getElementById('quick_description').value = '';
        document.getElementById('quick_start_date').value = formatForInput(startDate);
        document.getElementById('quick_end_date').value = formatForInput(endDate);
        document.getElementById('quick_sla_hours').value = diffHours;

        document.getElementById('createCalendarTaskModal').style.display = 'flex';
        if (window.feather) feather.replace();
    }

    function submitCreateCalendarTask() {
        const projectId = document.getElementById('quick_project_id').value;
        const title = document.getElementById('quick_title').value.trim();
        const description = document.getElementById('quick_description').value.trim();
        const assignedTo = document.getElementById('quick_assigned_to').value;
        const priority = document.getElementById('quick_priority').value;
        const startDate = document.getElementById('quick_start_date').value;
        const endDate = document.getElementById('quick_end_date').value;
        const slaHours = document.getElementById('quick_sla_hours').value;

        if (!projectId || !title || !assignedTo || !startDate || !endDate) {
            alert('Please fill in Project, Task Title, Assigned Developer, and Dates.');
            return;
        }

        const btn = document.getElementById('btnSubmitQuickCreate');
        btn.disabled = true;
        btn.textContent = 'Scheduling...';

        fetch('api.php?action=create_task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                project_id: projectId,
                title: title,
                description: description,
                assigned_to: assignedTo,
                priority: priority,
                start_date: startDate.replace('T', ' ') + ':00',
                end_date: endDate.replace('T', ' ') + ':00',
                sla_hours: slaHours
            })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i data-feather="check"></i> Create & Schedule Task';
            if (data.success) {
                closeCalendarModal('createCalendarTaskModal');
                showToast(`✅ ${data.message}`, '🎉');
                reloadCalendarEvents();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i data-feather="check"></i> Create & Schedule Task';
            alert('Failed to create task: ' + err);
        });
    }

    // =========================================================
    // EDIT TASK DETAILS & SCHEDULE MODAL
    // =========================================================
    function openEditCalendarTask(event) {
        const props = event.extendedProps || {};
        const taskId = props.task_id || event.id;

        document.getElementById('ed_task_id').value = taskId;
        document.getElementById('ed_task_code_badge').textContent = props.task_code || `TSK-${taskId}`;
        document.getElementById('ed_modal_title').textContent = props.raw_title || event.title;
        document.getElementById('ed_project_name').textContent = `${props.project_name || 'Project'} • ${props.client_name || 'Client'}`;
        document.getElementById('ed_title').value = props.raw_title || event.title;
        document.getElementById('ed_description').value = props.description || '';
        document.getElementById('ed_status').value = props.status || 'PENDING';
        document.getElementById('ed_priority').value = props.priority || 'Medium';

        // Set Project selector
        const projSelect = document.getElementById('ed_project_id');
        if (projSelect) {
            projSelect.value = props.project_id || '';
        }
        const projStatic = document.getElementById('ed_project_name_static');
        if (projStatic) {
            projStatic.value = props.project_name || '';
        }

        // Set Developer selector
        const devSelect = document.getElementById('ed_assigned_to');
        if (devSelect) {
            devSelect.value = props.assigned_to || '';
        }
        const devStatic = document.getElementById('ed_assigned_to_name');
        if (devStatic) {
            devStatic.value = props.assigned_to_name || 'Unassigned';
        }

        // Set SLA badge
        const slaBadge = document.getElementById('ed_sla_badge');
        if (slaBadge) {
            slaBadge.textContent = props.sla_status || 'Active';
            slaBadge.className = 'badge ' + (props.sla_status === 'SLA Breached' ? 'badge-danger' : (props.sla_status === 'Near SLA Warning' ? 'badge-warning' : 'badge-primary'));
        }

        // Dates for pickers
        let sDate = event.start ? formatForInput(event.start) : (props.start_date ? props.start_date.substring(0, 16).replace(' ', 'T') : '');
        let eDate = event.end ? formatForInput(event.end) : (props.end_date ? props.end_date.substring(0, 16).replace(' ', 'T') : '');
        if (!eDate && sDate) eDate = sDate;

        document.getElementById('ed_start_date').value = sDate;
        document.getElementById('ed_end_date').value = eDate;

        // View full link
        document.getElementById('ed_view_full_btn').href = `task_details.php?id=${taskId}`;

        document.getElementById('editCalendarTaskModal').style.display = 'flex';
        if (window.feather) feather.replace();
    }

    function submitUpdateCalendarTask() {
        const taskId = document.getElementById('ed_task_id').value;
        const title = document.getElementById('ed_title').value.trim();
        const description = document.getElementById('ed_description').value.trim();
        const assignedTo = document.getElementById('ed_assigned_to') ? document.getElementById('ed_assigned_to').value : 0;
        const projectId = document.getElementById('ed_project_id') ? document.getElementById('ed_project_id').value : 0;
        const status = document.getElementById('ed_status').value;
        const priority = document.getElementById('ed_priority').value;
        const startDate = document.getElementById('ed_start_date').value;
        const endDate = document.getElementById('ed_end_date').value;

        if (!taskId || !title || !startDate || !endDate) {
            alert('Title, start date, and end date are required.');
            return;
        }

        const btn = document.getElementById('btnSaveTaskDates');
        btn.disabled = true;
        btn.textContent = 'Saving...';

        fetch('api.php?action=update_task_calendar_details', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                task_id: taskId,
                title: title,
                description: description,
                assigned_to: assignedTo,
                project_id: projectId,
                status: status,
                priority: priority,
                start_date: startDate.replace('T', ' ') + ':00',
                end_date: endDate.replace('T', ' ') + ':00'
            })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i data-feather="save"></i> Save Changes';
            if (data.success) {
                closeCalendarModal('editCalendarTaskModal');
                showToast(`✅ ${data.message}`, '💾');
                reloadCalendarEvents();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i data-feather="save"></i> Save Changes';
            alert('Failed to update: ' + err);
        });
    }

    function deleteCalendarTask() {
        const taskId = document.getElementById('ed_task_id').value;
        if (!confirm('Are you sure you want to delete this task? It will be removed from the calendar.')) {
            return;
        }

        fetch('api.php?action=delete_task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task_id: taskId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeCalendarModal('editCalendarTaskModal');
                showToast(`🗑️ Task deleted successfully`, '🗑️');
                reloadCalendarEvents();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    // Modal Helpers
    function closeCalendarModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    // Toast Notification Helper
    function showToast(msg, icon = '⚡') {
        const toast = document.getElementById('calToast');
        document.getElementById('calToastMsg').textContent = msg;
        document.getElementById('calToastIcon').textContent = icon;
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 4000);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
