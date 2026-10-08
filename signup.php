<?php
// signup.php - User Self Registration & Password Creation Portal for HemiFlow Agency OS

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = trim($_GET['token'] ?? '');
$username = trim($_GET['username'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Setup & Password Creation | HemiFlow Agency OS</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <style>
        body {
            background-color: #0f172a;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .signup-card {
            background: #ffffff;
            color: #0f172a;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 480px;
            padding: 40px;
        }

        .signup-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .signup-header img {
            height: 48px;
            max-width: 220px;
            object-fit: contain;
            margin-bottom: 12px;
        }

        .signup-header h2 {
            font-size: 22px;
            font-weight: 800;
            color: #2563eb;
            margin-bottom: 6px;
        }

        .signup-header p {
            font-size: 13px;
            color: #64748b;
        }

        .user-invite-badge {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #1e40af;
        }

        .user-invite-badge strong {
            display: block;
            font-size: 15px;
            color: #1e3a8a;
            margin-bottom: 2px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #334155;
        }

        .form-control {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            color: #0f172a;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: #1d4ed8;
        }

        .alert-box {
            padding: 12px 14px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
            display: none;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #16a34a;
        }
    </style>
</head>
<body>

<div class="signup-card">
    <div class="signup-header">
        <img src="asset/Hemiflow Blue Wave Logo.png" alt="HemiFlow Logo">
        <h2>Set Up Your Password</h2>
        <p>Complete your HemiFlow Agency OS account setup by creating your own secure password.</p>
    </div>

    <div id="alertBox" class="alert-box alert-danger"></div>

    <div id="userInviteBanner" class="user-invite-badge" style="display:none;">
        <strong id="inviteFullName">User Account</strong>
        <span id="inviteMeta">Loading account identity...</span>
    </div>

    <form id="signupForm" onsubmit="event.preventDefault(); submitPasswordSetup();">
        <input type="hidden" id="signupToken" value="<?= htmlspecialchars($token) ?>">

        <div class="form-group" id="identifierGroup">
            <label class="form-label">Username or Registered Email Address *</label>
            <input type="text" id="signupIdentifier" class="form-control" value="<?= htmlspecialchars($username) ?>" placeholder="e.g. lead_ajmal or user@hemitodigital.com" required>
        </div>

        <div class="form-group">
            <label class="form-label">Create New Password *</label>
            <input type="password" id="newPassword" class="form-control" placeholder="Minimum 6 characters" minlength="6" required>
        </div>

        <div class="form-group">
            <label class="form-label">Confirm New Password *</label>
            <input type="password" id="confirmPassword" class="form-control" placeholder="Re-enter new password" minlength="6" required>
        </div>

        <button type="submit" id="submitBtn" class="btn-submit">
            <i data-feather="check-circle"></i> Create Password & Set Up Account
        </button>
    </form>

    <div id="successView" style="display:none; text-align:center; padding-top:10px;">
        <div style="background:#dcfce7; color:#15803d; border-radius:50%; width:64px; height:64px; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
            <i data-feather="check" style="width:36px; height:36px; stroke-width:3;"></i>
        </div>
        <h3 style="font-size:20px; font-weight:800; color:#0f172a; margin-bottom:8px;">Account Setup Complete!</h3>
        <p style="color:#475569; font-size:14px; margin-bottom:24px;">Your custom password has been saved successfully. You can now log into your HemiFlow portal.</p>
        <a href="login.php" class="btn-submit" style="text-decoration:none;">
            <i data-feather="log-in"></i> Go to Login Portal
        </a>
    </div>

    <div style="text-align:center; margin-top:24px; font-size:12px; color:#94a3b8; border-top:1px solid #f1f5f9; padding-top:16px;">
        Already have your password set? <a href="login.php" style="color:#2563eb; text-decoration:none; font-weight:700;">Sign in to Portal</a>
    </div>
</div>

<script>
    const token = document.getElementById('signupToken').value;

    document.addEventListener('DOMContentLoaded', () => {
        if (window.feather) feather.replace();

        if (token) {
            verifyToken(token);
        }
    });

    function verifyToken(tok) {
        fetch(`api.php?action=verify_signup_token&token=${encodeURIComponent(tok)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.user) {
                    const u = data.user;
                    document.getElementById('signupIdentifier').value = u.username;
                    document.getElementById('identifierGroup').style.display = 'none';
                    document.getElementById('inviteFullName').textContent = u.full_name;
                    document.getElementById('inviteMeta').textContent = `@${u.username} | ${u.email} (${u.role_name} - ${u.department || 'General'})`;
                    document.getElementById('userInviteBanner').style.display = 'block';
                }
            });
    }

    function submitPasswordSetup() {
        const tok = document.getElementById('signupToken').value;
        const identifier = document.getElementById('signupIdentifier').value;
        const pass = document.getElementById('newPassword').value;
        const confirmPass = document.getElementById('confirmPassword').value;

        const alertBox = document.getElementById('alertBox');
        alertBox.style.display = 'none';

        if (pass !== confirmPass) {
            alertBox.textContent = 'Passwords do not match. Please re-type your confirm password.';
            alertBox.style.display = 'block';
            return;
        }

        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Saving password...';

        fetch('api.php?action=complete_signup', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                token: tok,
                identifier: identifier,
                password: pass
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('signupForm').style.display = 'none';
                document.getElementById('userInviteBanner').style.display = 'none';
                document.getElementById('successView').style.display = 'block';
                if (window.feather) feather.replace();
            } else {
                alertBox.textContent = data.message;
                alertBox.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i data-feather="check-circle"></i> Create Password & Set Up Account';
                if (window.feather) feather.replace();
            }
        });
    }
</script>

</body>
</html>
