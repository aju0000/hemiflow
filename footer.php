        </main>
    </div>
</div>

<!-- ========================================== -->
<!-- GLOBAL MODAL: CHANGE MY PASSWORD           -->
<!-- ========================================== -->
<div id="changeMyPasswordModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:99999; justify-content:center; align-items:center;">
    <div class="modal-content" style="background:#ffffff; width:100%; max-width:440px; border-radius:12px; padding:24px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 class="modal-title" style="margin:0; font-size:18px; font-weight:700; color:#0f172a;">🔑 Change Account Password</h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeChangeMyPasswordModal()" style="border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitChangeMyPassword();">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label" style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Current Password *</label>
                    <input type="password" id="cmp_current_password" class="form-control" required placeholder="Enter current password" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label" style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">New Password *</label>
                    <input type="password" id="cmp_new_password" class="form-control" required placeholder="Enter new password (min 6 chars)" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label" style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Confirm New Password *</label>
                    <input type="password" id="cmp_confirm_password" class="form-control" required placeholder="Confirm new password" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
            </div>
            <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeChangeMyPasswordModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openChangeMyPasswordModal() {
        document.getElementById('cmp_current_password').value = '';
        document.getElementById('cmp_new_password').value = '';
        document.getElementById('cmp_confirm_password').value = '';
        document.getElementById('changeMyPasswordModal').style.display = 'flex';
    }

    function closeChangeMyPasswordModal() {
        document.getElementById('changeMyPasswordModal').style.display = 'none';
    }

    function submitChangeMyPassword() {
        const cur = document.getElementById('cmp_current_password').value.trim();
        const np = document.getElementById('cmp_new_password').value.trim();
        const cp = document.getElementById('cmp_confirm_password').value.trim();

        if (!cur || !np) {
            alert('Current password and new password are required.');
            return;
        }

        if (np.length < 6) {
            alert('New password must be at least 6 characters long.');
            return;
        }

        if (np !== cp) {
            alert('New password and confirmation password do not match.');
            return;
        }

        fetch('api.php?action=change_my_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ current_password: cur, new_password: np, confirm_password: cp })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeChangeMyPasswordModal();
                alert('✅ ' + data.message);
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    if (typeof feather !== 'undefined') {
        feather.replace();
    }
</script>
</body>
</html>
