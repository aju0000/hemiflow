<?php
// client/projects.php - Client Projects Overview

$pageTitle = "My Projects";
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i data-feather="folder" style="width:18px; height:18px;"></i> Active & Past Projects</h3>
    </div>
    
    <div id="projectsGrid" class="grid-2" style="grid-template-columns: 1fr 1fr; gap:20px;">
        <p style="color:var(--text-muted);">Loading your projects...</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    fetch('/client/api.php?action=get_projects')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const container = document.getElementById('projectsGrid');
            if (!data.projects || data.projects.length === 0) {
                container.innerHTML = '<p style="color:var(--text-muted);">No projects found for your account.</p>';
                return;
            }

            let html = '';
            data.projects.forEach(p => {
                html += `
                    <div style="border: 1px solid #cbd5e1; border-radius: 12px; padding: 22px; background: #ffffff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display:flex; flex-direction:column; justify-space:between;">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                                <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">${p.project_name}</h3>
                                <span class="badge ${p.status === 'Completed' ? 'badge-success' : 'badge-primary'}" style="font-size:12px;">${p.status}</span>
                            </div>
                            <p style="font-size:13.5px; color:#475569; line-height:1.5; margin-bottom:16px;">
                                ${p.description || 'Corporate project deliverables and task timeline.'}
                            </p>
                        </div>

                        <div>
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:16px;">
                                <div style="display:flex; justify-content:space-between; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;">
                                    <span>Overall Task Completion</span>
                                    <span>${p.progress_pct}% (${p.completed_tasks}/${p.total_tasks} Tasks)</span>
                                </div>
                                <div style="background:#cbd5e1; height:8px; border-radius:4px; overflow:hidden;">
                                    <div style="background:#2563eb; width:${p.progress_pct}%; height:100%; border-radius:4px;"></div>
                                </div>
                            </div>

                            <div style="display:flex; justify-content:space-between; align-items:center; font-size:12px; color:#64748b;">
                                <span>Expected Target: <strong>${p.deadline || 'N/A'}</strong></span>
                                <a href="/client/project.php?id=${p.id}" class="btn btn-primary btn-sm">
                                    <i data-feather="eye"></i> View Project Details
                                </a>
                            </div>
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
