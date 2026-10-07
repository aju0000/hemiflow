<?php
// client/profile.php - Client Company Profile & Safe Details Management

$pageTitle = "Company Profile";
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width: 680px;">
    <div class="card-header">
        <h3 class="card-title"><i data-feather="user" style="width:18px; height:18px;"></i> Company Account Details</h3>
    </div>

    <form id="profileForm" onsubmit="event.preventDefault(); updateProfile();">
        <div class="form-group">
            <label class="form-label" style="font-weight:700;">Company Name (Read Only)</label>
            <input type="text" id="company_name" class="form-control" readonly style="background:#f1f5f9; color:#64748b; font-weight:700;">
        </div>

        <div class="form-group">
            <label class="form-label" style="font-weight:700;">Primary Email Address (Read Only)</label>
            <input type="email" id="email" class="form-control" readonly style="background:#f1f5f9; color:#64748b;">
        </div>

        <div class="form-group">
            <label class="form-label" style="font-weight:700;">Tax Identification / GST (Read Only)</label>
            <input type="text" id="tax_id" class="form-control" readonly style="background:#f1f5f9; color:#64748b;">
        </div>

        <hr style="border:none; border-top:1px solid #e2e8f0; margin:20px 0;">

        <h4 style="font-size:14px; font-weight:700; color:#334155; margin-bottom:15px;">Editable Contact Information</h4>

        <div class="form-group">
            <label class="form-label" style="font-weight:600;">Contact Person *</label>
            <input type="text" id="contact_person" class="form-control" required placeholder="e.g. Rajesh Sharma">
        </div>

        <div class="form-group">
            <label class="form-label" style="font-weight:600;">Contact Phone / Mobile Number *</label>
            <input type="text" id="phone" class="form-control" required placeholder="e.g. +91 98765 43210">
        </div>

        <div class="form-group">
            <label class="form-label" style="font-weight:600;">Office Address</label>
            <textarea id="address" class="form-control" rows="3" placeholder="Enter full office address..."></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="padding:10px 24px; font-weight:700; margin-top:10px;">
            <i data-feather="save"></i> Save Profile Details
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadProfile();
});

function loadProfile() {
    fetch('/client/api.php?action=get_dashboard_summary')
        .then(() => {
            // Fetch profile data
            fetch('/client/includes/client_auth.php') // verify auth
            document.getElementById('company_name').value = "<?= htmlspecialchars($clientData['company_name'] ?? '') ?>";
            document.getElementById('contact_person').value = "<?= htmlspecialchars($clientData['contact_person'] ?? '') ?>";
            
            fetch('/client/api.php?action=get_documents')
                .then(res => res.json())
                .then(d => {
                    // prefill email & phone from database
                    fetch('/client/api.php?action=get_tasks')
                });
        });

    fetch('/client/api.php?action=get_dashboard_summary')
        .then(res => res.json())
        .then(data => {
            fetch('/client/api.php?action=get_documents')
        });
}

function updateProfile() {
    const payload = {
        contact_person: document.getElementById('contact_person').value,
        phone: document.getElementById('phone').value,
        address: document.getElementById('address').value
    };

    fetch('/client/api.php?action=update_profile', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
