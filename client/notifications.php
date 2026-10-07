<?php
// client/notifications.php - Client Notification Center

$pageTitle = "Notifications Center";
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i data-feather="bell" style="width:18px; height:18px;"></i> Account Notifications</h3>
        <button class="btn btn-outline btn-sm" onclick="markAllRead()">Mark All as Read</button>
    </div>

    <div id="notificationsContainer" style="display:flex; flex-direction:column; gap:12px;">
        <p style="color:var(--text-muted);">Loading notifications...</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadNotifications();
});

function loadNotifications() {
    fetch('/client/api.php?action=get_notifications')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const container = document.getElementById('notificationsContainer');
            if (!data.notifications || data.notifications.length === 0) {
                container.innerHTML = '<p style="color:var(--text-muted);">No notifications received yet.</p>';
                return;
            }

            let html = '';
            data.notifications.forEach(n => {
                const isUnread = !n.is_read;
                html += `
                    <div style="border: 1px solid ${isUnread ? '#3b82f6' : '#cbd5e1'}; border-radius: 10px; padding: 16px; background: ${isUnread ? '#eff6ff' : '#ffffff'}; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                        <div style="display:flex; align-items:flex-start; gap:12px;">
                            <div style="width:10px; height:10px; border-radius:50%; background:${isUnread ? '#2563eb' : 'transparent'}; margin-top:6px; flex-shrink:0;"></div>
                            <div>
                                <h4 style="margin:0; font-size:15px; font-weight:700; color:#0f172a;">${n.title}</h4>
                                <p style="font-size:13.5px; color:#475569; margin:4px 0; line-height:1.5; white-space:pre-wrap;">${n.message}</p>
                                <div style="font-size:11px; color:#64748b;">${n.created_at} ${n.sms_sent ? '• 📱 SMS Sent' : ''}</div>
                            </div>
                        </div>

                        <div>
                            ${n.task_id ? `<a href="/client/task.php?id=${n.task_id}" class="btn btn-primary btn-sm">View Task</a>` : ''}
                            ${isUnread ? `<button class="btn btn-outline btn-sm" onclick="markRead(${n.id})" style="margin-left:6px;">Mark Read</button>` : ''}
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        });
}

function markRead(id) {
    fetch('/client/api.php?action=mark_notification_read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ notification_id: id })
    })
    .then(res => res.json())
    .then(() => loadNotifications());
}

function markAllRead() {
    fetch('/client/api.php?action=mark_notification_read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ notification_id: 0 })
    })
    .then(res => res.json())
    .then(() => {
        location.reload();
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
