<?php
// client/meetings.php - Client Google Meet Schedule & Join Links

$pageTitle = "Project Meetings";
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i data-feather="video" style="width:18px; height:18px;"></i> Scheduled Google Meetings</h3>
    </div>

    <div id="meetingsContainer" style="display:flex; flex-direction:column; gap:16px;">
        <p style="color:var(--text-muted);">Loading meetings...</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    fetch('/client/api.php?action=get_meetings')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const container = document.getElementById('meetingsContainer');
            if (!data.meetings || data.meetings.length === 0) {
                container.innerHTML = '<p style="color:var(--text-muted);">No meetings currently scheduled.</p>';
                return;
            }

            let html = '';
            data.meetings.forEach(m => {
                html += `
                    <div style="border: 1px solid #cbd5e1; border-radius: 12px; padding: 20px; background: #ffffff; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.04);">
                        <div>
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                                <span class="badge badge-primary" style="font-size:11px;">${m.status}</span>
                                <h3 style="margin:0; font-size:17px; font-weight:800; color:#0f172a;">${m.title}</h3>
                            </div>
                            <p style="font-size:13.5px; color:#475569; margin:4px 0 10px 0; line-height:1.5;">
                                ${m.description || 'Project review and progress sync.'}
                            </p>
                            <div style="display:flex; gap:16px; font-size:13px; color:#64748b;">
                                <span>📅 Date: <strong style="color:#0f172a;">${m.meeting_date}</strong></span>
                                <span>⏰ Time: <strong style="color:#0f172a;">${m.meeting_time}</strong></span>
                                ${m.project_name ? `<span>📁 Project: <strong>${m.project_name}</strong></span>` : ''}
                            </div>
                        </div>

                        <div>
                            <a href="${m.meet_url}" target="_blank" rel="noopener" class="btn btn-primary" style="background:#0284c7; border-color:#0284c7; padding:12px 20px; font-weight:700;">
                                <i data-feather="video"></i> Join Google Meet
                            </a>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
            if (window.feather) feather.replace();
        });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
