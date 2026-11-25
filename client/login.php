<?php
// client/login.php - Dedicated Ultra-Premium Client Portal Login Interface

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../auth.php';

$error = '';
$successMsg = '';

// If already logged in as client, redirect directly to dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] === 'CLIENT' && !empty($_SESSION['client_id'])) {
    header('Location: /client/dashboard.php');
    exit;
}

if (isset($_GET['logout'])) {
    $successMsg = 'You have safely logged out of your Client Portal session.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($identifier) || empty($password)) {
        $error = 'Please enter your Mobile Number / Email and Password.';
    } else {
        $res = loginClientUser($identifier, $password);
        if ($res['success']) {
            header('Location: /client/dashboard.php');
            exit;
        } else {
            $error = $res['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal Login — HemiFlow</title>
    <!-- Premium Fonts: Plus Jakarta Sans & Google Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Google+Sans+Text:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'Google Sans Text', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #060b18;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow-x: hidden;
            color: #f8fafc;
        }

        /* Ambient Glowing Luxury Mesh Backdrop */
        .ambient-mesh {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
            background: 
                radial-gradient(circle at 20% 15%, rgba(14, 116, 144, 0.35) 0%, transparent 45%),
                radial-gradient(circle at 80% 85%, rgba(59, 130, 246, 0.30) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(15, 23, 42, 0.95) 0%, #030712 100%);
        }

        .ambient-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.45;
            pointer-events: none;
        }

        .orb-top-left {
            width: 480px;
            height: 480px;
            background: linear-gradient(135deg, #0284c7, #2563eb);
            top: -100px;
            left: -120px;
        }

        .orb-bottom-right {
            width: 520px;
            height: 520px;
            background: linear-gradient(135deg, #4f46e5, #0ea5e9);
            bottom: -120px;
            right: -120px;
        }

        .orb-center {
            width: 320px;
            height: 320px;
            background: #38bdf8;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.18;
            filter: blur(120px);
        }

        /* Subtle Geometric Grid Lines */
        .ambient-grid {
            position: fixed;
            inset: 0;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 48px 48px;
            pointer-events: none;
            z-index: 1;
        }

        /* Main Floating Card */
        .client-portal-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 460px;
            background: rgba(15, 23, 42, 0.78);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 26px;
            box-shadow: 
                0 30px 80px -15px rgba(0, 0, 0, 0.8),
                0 0 50px rgba(14, 165, 233, 0.15),
                inset 0 1px 1px rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            overflow: hidden;
            animation: cardFadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes cardFadeIn {
            from {
                opacity: 0;
                transform: translateY(18px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Card Header */
        .card-header {
            padding: 38px 36px 26px 36px;
            text-align: center;
            position: relative;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            background: linear-gradient(180deg, rgba(30, 41, 59, 0.45) 0%, rgba(15, 23, 42, 0) 100%);
        }

        .brand-logo-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .brand-logo-img {
            height: 48px;
            max-width: 190px;
            object-fit: contain;
            filter: drop-shadow(0 6px 16px rgba(14, 165, 233, 0.35));
        }

        .portal-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(14, 165, 233, 0.12);
            border: 1px solid rgba(56, 189, 248, 0.3);
            color: #38bdf8;
            font-size: 11px;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 12px;
        }

        .pulse-indicator {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #38bdf8;
            box-shadow: 0 0 10px #38bdf8;
            animation: pulseGlow 2s infinite;
        }

        @keyframes pulseGlow {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }

        .portal-title {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.4px;
            margin-bottom: 6px;
            line-height: 1.25;
        }

        .portal-subtitle {
            font-size: 13.5px;
            color: #94a3b8;
            line-height: 1.5;
            max-width: 360px;
            margin: 0 auto;
        }

        /* Card Body */
        .card-body {
            padding: 32px 36px 36px 36px;
        }

        /* Alerts */
        .alert-box {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 13px 16px;
            border-radius: 12px;
            font-size: 13px;
            line-height: 1.45;
            margin-bottom: 22px;
            animation: fadeIn 0.3s ease;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.35);
            color: #86efac;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12.5px;
            font-weight: 700;
            color: #cbd5e1;
            margin-bottom: 8px;
            letter-spacing: 0.2px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            width: 17px;
            height: 17px;
            color: #64748b;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .form-input {
            width: 100%;
            height: 48px;
            background: rgba(2, 6, 23, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 0 42px 0 42px;
            font-size: 14px;
            color: #ffffff;
            font-family: inherit;
            transition: all 0.2s ease;
            outline: none;
        }

        .form-input::placeholder {
            color: #64748b;
            font-size: 13.5px;
        }

        .form-input:focus {
            border-color: #38bdf8;
            background: rgba(2, 6, 23, 0.9);
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.18);
        }

        .input-wrapper:focus-within .input-icon {
            color: #38bdf8;
        }

        .toggle-password-btn {
            position: absolute;
            right: 12px;
            background: transparent;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: color 0.2s ease, background 0.2s ease;
        }

        .toggle-password-btn:hover {
            color: #cbd5e1;
            background: rgba(255, 255, 255, 0.05);
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            height: 50px;
            background: linear-gradient(135deg, #0284c7 0%, #2563eb 50%, #4f46e5 100%);
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-size: 14.5px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 10px 25px -4px rgba(37, 99, 235, 0.5), 0 0 20px rgba(14, 165, 233, 0.25);
            margin-top: 10px;
            letter-spacing: 0.2px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px -4px rgba(37, 99, 235, 0.65), 0 0 28px rgba(14, 165, 233, 0.4);
            filter: brightness(1.06);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* Card Footer */
        .card-footer {
            margin-top: 26px;
            padding-top: 22px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .security-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 11.5px;
            color: #94a3b8;
            font-weight: 500;
        }

        .security-badge svg {
            color: #38bdf8;
            width: 13px;
            height: 13px;
        }

        .staff-link {
            font-size: 12px;
            color: #64748b;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .staff-link a {
            color: #38bdf8;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .staff-link a:hover {
            color: #7dd3fc;
            text-decoration: underline;
        }

        @media (max-width: 520px) {
            .client-portal-card {
                border-radius: 20px;
            }
            .card-header {
                padding: 30px 24px 20px 24px;
            }
            .card-body {
                padding: 24px;
            }
            .portal-title {
                font-size: 21px;
            }
        }
    </style>
</head>
<body>

<div class="ambient-mesh">
    <div class="ambient-orb orb-top-left"></div>
    <div class="ambient-orb orb-bottom-right"></div>
    <div class="ambient-orb orb-center"></div>
</div>
<div class="ambient-grid"></div>

<div class="client-portal-card">
    <!-- Header -->
    <div class="card-header">
        <div class="brand-logo-wrap">
            <img src="/asset/Hemiflow Blue Wave Logo.png" alt="HemiFlow" class="brand-logo-img" onerror="this.onerror=null; this.src='../asset/Hemiflow Blue Wave Logo.png';">
        </div>
        <div class="portal-pill">
            <span class="pulse-indicator"></span>
            <span>Client Experience Portal</span>
        </div>
        <h1 class="portal-title">Client Sign In</h1>
        <p class="portal-subtitle">Access your active projects, monthly strategy plans, deliverables, and team progress.</p>
    </div>

    <!-- Body -->
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert-box alert-error">
                <i data-feather="alert-circle" style="width:18px; height:18px; flex-shrink:0; margin-top:1px;"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($successMsg): ?>
            <div class="alert-box alert-success">
                <i data-feather="check-circle" style="width:18px; height:18px; flex-shrink:0; margin-top:1px;"></i>
                <div><?= htmlspecialchars($successMsg) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="/client/login.php" autocomplete="on">
            <!-- Identifier (Mobile / Email / Username) -->
            <div class="form-group">
                <label for="identifier" class="form-label">
                    <span>Mobile Number or Email</span>
                </label>
                <div class="input-wrapper">
                    <i data-feather="user" class="input-icon"></i>
                    <input 
                        type="text" 
                        name="identifier" 
                        id="identifier" 
                        class="form-input" 
                        placeholder="e.g. mobile number or company email" 
                        required 
                        autofocus
                        autocomplete="username"
                        value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                    >
                </div>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label for="password" class="form-label">
                    <span>Password</span>
                </label>
                <div class="input-wrapper">
                    <i data-feather="lock" class="input-icon"></i>
                    <input 
                        type="password" 
                        name="password" 
                        id="password" 
                        class="form-input" 
                        placeholder="Enter your client portal password" 
                        required 
                        autocomplete="current-password"
                    >
                    <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility()" title="Toggle password visibility" tabindex="-1">
                        <i data-feather="eye" id="togglePasswordIcon" style="width:16px; height:16px;"></i>
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-submit" id="submitBtn">
                <span>Sign In to Client Portal</span>
                <i data-feather="arrow-right" style="width:18px; height:18px;"></i>
            </button>
        </form>

        <!-- Footer -->
        <div class="card-footer">
            <div class="security-badge">
                <i data-feather="shield"></i>
                <span>256-Bit SSL Encrypted Client Workspace</span>
            </div>
            <div class="staff-link">
                Agency Team Member? <a href="/login.php">Staff Login &rarr;</a>
            </div>
        </div>
    </div>
</div>

<script>
    function togglePasswordVisibility() {
        const pwdInput = document.getElementById('password');
        const icon = document.getElementById('togglePasswordIcon');
        if (!pwdInput) return;
        
        if (pwdInput.type === 'password') {
            pwdInput.type = 'text';
            icon.setAttribute('data-feather', 'eye-off');
        } else {
            pwdInput.type = 'password';
            icon.setAttribute('data-feather', 'eye');
        }
        if (window.feather) feather.replace();
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (window.feather) feather.replace();

        const form = document.querySelector('form');
        const submitBtn = document.getElementById('submitBtn');
        if (form && submitBtn) {
            form.addEventListener('submit', () => {
                submitBtn.style.opacity = '0.85';
                submitBtn.innerHTML = '<span>Verifying credentials...</span>';
            });
        }
    });
</script>
</body>
</html>
