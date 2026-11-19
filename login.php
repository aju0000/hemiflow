<?php
// login.php - Centralized Module-Level Authentication Interface

require_once __DIR__ . '/auth.php';

$module = $_GET['module'] ?? $_POST['module'] ?? null;
$error = '';
$successMsg = '';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    session_start();
    $successMsg = 'You have been logged out.';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $module = $_POST['module'] ?? null;

    $res = loginUser($username, $password, $module);
    if ($res['success']) {
        if ($module === 'template-generator' || !$module) {
            header('Location: template_generator.php');
        } elseif ($module === 'task-management') {
            header('Location: projects.php');
        } elseif ($module === 'hr-management') {
            header('Location: hr.php');
        } elseif ($module === 'reports') {
            header('Location: reports.php');
        } elseif ($module === 'client-management' || $module === 'sales') {
            header('Location: clients.php');
        } else {
            header('Location: index.php');
        }
        exit;
    } else {
        $error = $res['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HemiFlow - Centralized Agency Portal</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #0b031b;
            position: relative;
            overflow-x: hidden;
            padding: 24px;
        }

        /* Ambient Glowing Background Waves */
        .ambient-bg {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
            background: 
                radial-gradient(circle at 15% 20%, rgba(99, 40, 200, 0.45) 0%, transparent 45%),
                radial-gradient(circle at 85% 75%, rgba(124, 58, 237, 0.40) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(30, 9, 65, 0.85) 0%, #070114 100%);
        }

        .ambient-wave {
            position: absolute;
            width: 140vw;
            height: 140vh;
            top: -20vh;
            left: -20vw;
            opacity: 0.75;
            filter: blur(4px);
        }

        .ambient-glow-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.5;
        }

        .orb-1 {
            width: 450px;
            height: 450px;
            background: linear-gradient(135deg, #7c3aed, #4f46e5);
            top: 10%;
            left: -5%;
        }

        .orb-2 {
            width: 500px;
            height: 500px;
            background: linear-gradient(135deg, #9333ea, #6366f1);
            bottom: -5%;
            right: -5%;
        }

        .orb-3 {
            width: 320px;
            height: 320px;
            background: #a855f7;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.25;
            filter: blur(100px);
        }

        /* Center Main Floating Card */
        .portal-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 980px;
            min-height: 560px;
            display: grid;
            grid-template-columns: 1.08fr 0.92fr;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 28px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            box-shadow: 
                0 30px 70px -15px rgba(0, 0, 0, 0.75),
                0 0 50px rgba(124, 58, 237, 0.22),
                inset 0 1px 1px rgba(255, 255, 255, 0.2);
            overflow: hidden;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        /* Left Showcase Panel (Frosted Translucent Glass) */
        .showcase-panel {
            position: relative;
            padding: 44px 44px 36px 44px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: linear-gradient(145deg, rgba(88, 28, 135, 0.42) 0%, rgba(30, 16, 75, 0.65) 100%);
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
            overflow: hidden;
        }

        /* Subtle glass inner highlight */
        .showcase-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 20% 15%, rgba(255, 255, 255, 0.12) 0%, transparent 60%);
            pointer-events: none;
        }

        .brand-header {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 2;
        }

        .brand-icon-svg {
            width: 34px;
            height: 34px;
            filter: drop-shadow(0 4px 10px rgba(56, 189, 248, 0.45));
        }

        .brand-name {
            font-size: 23px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
        }

        .showcase-text {
            position: relative;
            z-index: 2;
            margin-top: 30px;
        }

        .showcase-title {
            font-size: 33px;
            font-weight: 800;
            line-height: 1.16;
            color: #ffffff;
            letter-spacing: -0.6px;
            margin-bottom: 12px;
        }

        .showcase-subtitle {
            font-size: 14.5px;
            line-height: 1.55;
            color: rgba(226, 232, 240, 0.88);
            font-weight: 400;
            max-width: 340px;
        }

        /* 3D Glass Dashboard Illustration */
        .showcase-visual {
            position: relative;
            z-index: 2;
            margin-top: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-bottom: 6px;
        }

        .dashboard-svg-wrap {
            width: 100%;
            max-width: 390px;
            height: auto;
            display: block;
            filter: drop-shadow(0 20px 30px rgba(0, 0, 0, 0.5));
        }

        /* Right Login Panel (Crisp Solid White) */
        .login-panel {
            background: #ffffff;
            padding: 48px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            z-index: 2;
        }

        .login-header-group {
            text-align: center;
            margin-bottom: 22px;
        }

        .login-brand-row {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 6px;
        }

        .login-brand-icon {
            width: 32px;
            height: 32px;
        }

        .login-brand-title {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .login-brand-subtitle {
            font-size: 13.5px;
            color: #64748b;
            font-weight: 500;
        }

        /* Alert Pill Message */
        .auth-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 500;
            margin-bottom: 22px;
            animation: fadeIn 0.3s ease;
        }

        .auth-alert.success {
            background: #e8f9ee;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .auth-alert.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .auth-alert.info {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .auth-alert svg {
            width: 17px;
            height: 17px;
            flex-shrink: 0;
        }

        /* Form Styles */
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .field-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .field-label {
            font-size: 13.5px;
            font-weight: 600;
            color: #1e293b;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .input-icon-prefix {
            position: absolute;
            left: 14px;
            width: 18px;
            height: 18px;
            color: #94a3b8;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .field-input {
            width: 100%;
            height: 48px;
            padding-left: 42px;
            padding-right: 14px;
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            color: #0f172a;
            font-family: inherit;
            outline: none;
            transition: all 0.2s ease;
        }

        .field-input.has-toggle {
            padding-right: 44px;
        }

        .field-input::placeholder {
            color: #94a3b8;
            font-size: 13.5px;
        }

        .field-input:hover {
            border-color: #cbd5e1;
        }

        .field-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }

        .input-wrapper:focus-within .input-icon-prefix {
            color: #4f46e5;
        }

        .password-toggle-btn {
            position: absolute;
            right: 12px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            border-radius: 6px;
            transition: color 0.2s ease;
        }

        .password-toggle-btn:hover {
            color: #475569;
        }

        .password-toggle-btn svg {
            width: 18px;
            height: 18px;
        }

        /* Submit Button */
        .btn-submit-login {
            height: 50px;
            background: linear-gradient(135deg, #5046e5 0%, #4338ca 100%);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
            box-shadow: 0 10px 22px -4px rgba(79, 70, 229, 0.45);
            transition: all 0.25s ease;
            text-decoration: none;
            width: 100%;
        }

        .btn-submit-login:hover {
            background: linear-gradient(135deg, #4338ca 0%, #3730a3 100%);
            box-shadow: 0 14px 26px -4px rgba(79, 70, 229, 0.58);
            transform: translateY(-1px);
        }

        .btn-submit-login:active {
            transform: translateY(0);
            box-shadow: 0 6px 14px -3px rgba(79, 70, 229, 0.4);
        }

        .btn-submit-login svg {
            width: 18px;
            height: 18px;
            stroke-width: 2.5;
            transition: transform 0.2s ease;
        }

        .btn-submit-login:hover svg {
            transform: translateX(3px);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Breakpoints */
        @media (max-width: 860px) {
            body {
                padding: 16px;
            }
            .portal-card {
                grid-template-columns: 1fr;
                max-width: 480px;
                border-radius: 24px;
            }
            .showcase-panel {
                padding: 32px 28px 24px 28px;
            }
            .showcase-visual {
                display: none;
            }
            .showcase-title {
                font-size: 26px;
            }
            .login-panel {
                padding: 36px 28px;
            }
        }
    </style>
</head>
<body>

<!-- Ambient Glowing Background Canvas -->
<div class="ambient-bg">
    <div class="ambient-glow-orb orb-1"></div>
    <div class="ambient-glow-orb orb-2"></div>
    <div class="ambient-glow-orb orb-3"></div>
    
    <!-- Neon Flowing Waves SVG -->
    <svg class="ambient-wave" viewBox="0 0 1440 900" fill="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <linearGradient id="waveGrad1" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#9333ea" stop-opacity="0.5"/>
                <stop offset="40%" stop-color="#6366f1" stop-opacity="0.3"/>
                <stop offset="100%" stop-color="#38bdf8" stop-opacity="0"/>
            </linearGradient>
            <linearGradient id="waveGrad2" x1="100%" y1="100%" x2="0%" y2="0%">
                <stop offset="0%" stop-color="#a855f7" stop-opacity="0.45"/>
                <stop offset="60%" stop-color="#4f46e5" stop-opacity="0.2"/>
                <stop offset="100%" stop-color="#06b6d4" stop-opacity="0"/>
            </linearGradient>
            <linearGradient id="strokeGlow" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#c084fc" stop-opacity="0.8"/>
                <stop offset="50%" stop-color="#818cf8" stop-opacity="0.6"/>
                <stop offset="100%" stop-color="#38bdf8" stop-opacity="0.1"/>
            </linearGradient>
        </defs>
        <!-- Flowing Ribbon 1 -->
        <path d="M-100 480 C 220 380, 480 620, 840 460 C 1120 340, 1340 520, 1600 400" stroke="url(#strokeGlow)" stroke-width="4.5" fill="none" opacity="0.6"/>
        <path d="M-100 480 C 220 380, 480 620, 840 460 C 1120 340, 1340 520, 1600 400 L 1600 900 L -100 900 Z" fill="url(#waveGrad1)" opacity="0.35"/>
        
        <!-- Flowing Ribbon 2 -->
        <path d="M-50 780 C 350 620, 680 840, 1020 660 C 1260 540, 1480 720, 1650 600" stroke="url(#strokeGlow)" stroke-width="3" fill="none" opacity="0.5"/>
        <path d="M-50 780 C 350 620, 680 840, 1020 660 C 1260 540, 1480 720, 1650 600 L 1650 900 L -50 900 Z" fill="url(#waveGrad2)" opacity="0.25"/>
    </svg>
</div>

<!-- Main Floating Glass Container -->
<div class="portal-card">

    <!-- LEFT PANEL: Glassmorphism Branded Showcase -->
    <div class="showcase-panel">
        <div>
            <!-- Top Brand Row -->
            <div class="brand-header">
                <!-- Stylized HemiFlow Ribbon Icon -->
                <svg class="brand-icon-svg" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="brandGradL" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#38bdf8"/>
                            <stop offset="50%" stop-color="#3b82f6"/>
                            <stop offset="100%" stop-color="#6366f1"/>
                        </linearGradient>
                        <linearGradient id="brandGradL2" x1="0%" y1="100%" x2="100%" y2="0%">
                            <stop offset="0%" stop-color="#2563eb"/>
                            <stop offset="100%" stop-color="#06b6d4"/>
                        </linearGradient>
                    </defs>
                    <rect x="6" y="8" width="8" height="24" rx="4" fill="url(#brandGradL)" />
                    <rect x="26" y="8" width="8" height="24" rx="4" fill="url(#brandGradL)" />
                    <path d="M14 16 C 18 13, 22 13, 26 16 L 26 24 C 22 21, 18 21, 14 24 Z" fill="url(#brandGradL2)" />
                </svg>
                <span class="brand-name">HemiFlow</span>
            </div>

            <!-- Headline & Subtitle -->
            <div class="showcase-text">
                <h1 class="showcase-title">Centralized<br>Agency Portal</h1>
                <p class="showcase-subtitle">Manage projects, clients, tasks and your team — all in one place.</p>
            </div>
        </div>

        <!-- 3D Glass Dashboard Illustration -->
        <div class="showcase-visual">
            <svg class="dashboard-svg-wrap" viewBox="0 0 420 280" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <!-- Glass Tablet Fill Gradient -->
                    <linearGradient id="glassFill" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#ffffff" stop-opacity="0.18"/>
                        <stop offset="45%" stop-color="#7c3aed" stop-opacity="0.12"/>
                        <stop offset="100%" stop-color="#1e1b4b" stop-opacity="0.45"/>
                    </linearGradient>
                    <!-- Tablet Border Stroke Gradient -->
                    <linearGradient id="glassStroke" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#ffffff" stop-opacity="0.45"/>
                        <stop offset="50%" stop-color="#c084fc" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#38bdf8" stop-opacity="0.25"/>
                    </linearGradient>
                    <!-- Bar Chart Gradient -->
                    <linearGradient id="barGrad" x1="0%" y1="100%" x2="0%" y2="0%">
                        <stop offset="0%" stop-color="#6366f1"/>
                        <stop offset="100%" stop-color="#38bdf8"/>
                    </linearGradient>
                    <!-- Donut Arc Gradients -->
                    <linearGradient id="donutArc1" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#38bdf8"/>
                        <stop offset="100%" stop-color="#818cf8"/>
                    </linearGradient>
                    <linearGradient id="donutArc2" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#c084fc"/>
                        <stop offset="100%" stop-color="#6366f1"/>
                    </linearGradient>
                    <!-- Folder Glass Fill -->
                    <linearGradient id="folderFill" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#ffffff" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#a855f7" stop-opacity="0.25"/>
                    </linearGradient>
                    <!-- Floor Glow Radial -->
                    <radialGradient id="floorGlow" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.45"/>
                        <stop offset="50%" stop-color="#a855f7" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#000000" stop-opacity="0"/>
                    </radialGradient>
                </defs>

                <!-- Neon Floor Ground Glow -->
                <ellipse cx="210" cy="245" rx="160" ry="24" fill="url(#floorGlow)" filter="blur(14px)"/>

                <!-- Perspective Tilted Main Glass Tablet -->
                <g transform="matrix(0.96, -0.08, 0.05, 0.94, 15, 20)">
                    <!-- Tablet Base Glass Body -->
                    <rect x="40" y="30" width="310" height="190" rx="18" fill="url(#glassFill)" stroke="url(#glassStroke)" stroke-width="1.5" />
                    
                    <!-- Top Window Decorative Header Dots/Line -->
                    <circle cx="62" cy="48" r="3" fill="#ffffff" fill-opacity="0.5"/>
                    <circle cx="72" cy="48" r="3" fill="#ffffff" fill-opacity="0.3"/>
                    <circle cx="82" cy="48" r="3" fill="#ffffff" fill-opacity="0.2"/>
                    <line x1="100" y1="48" x2="330" y2="48" stroke="#ffffff" stroke-opacity="0.15" stroke-dasharray="3 3"/>

                    <!-- Left: Ascending Bar Chart -->
                    <g transform="translate(62, 70)">
                        <rect x="0" y="44" width="11" height="28" rx="4" fill="url(#barGrad)" fill-opacity="0.85"/>
                        <rect x="18" y="32" width="11" height="40" rx="4" fill="url(#barGrad)" fill-opacity="0.9"/>
                        <rect x="36" y="20" width="11" height="52" rx="4" fill="url(#barGrad)"/>
                        <rect x="54" y="10" width="11" height="62" rx="4" fill="url(#barGrad)"/>
                        <rect x="72" y="0" width="11" height="72" rx="4" fill="url(#barGrad)"/>
                    </g>

                    <!-- Right: Glowing Donut Chart -->
                    <g transform="translate(255, 105)">
                        <!-- Outer Base Ring -->
                        <circle cx="0" cy="0" r="28" fill="none" stroke="#ffffff" stroke-opacity="0.1" stroke-width="10"/>
                        <!-- Segment 1 (Cyan/Blue) -->
                        <circle cx="0" cy="0" r="28" fill="none" stroke="url(#donutArc1)" stroke-width="10" stroke-dasharray="115 175" stroke-dashoffset="0" stroke-linecap="round"/>
                        <!-- Segment 2 (Violet) -->
                        <circle cx="0" cy="0" r="28" fill="none" stroke="url(#donutArc2)" stroke-width="10" stroke-dasharray="45 175" stroke-dashoffset="-120" stroke-linecap="round"/>
                        <!-- Center Core -->
                        <circle cx="0" cy="0" r="18" fill="#1e1644" fill-opacity="0.75"/>
                    </g>

                    <!-- Bottom Area: Clean List Rows with Green Check Badges -->
                    <!-- Row 1 -->
                    <g transform="translate(60, 160)">
                        <rect x="0" y="0" width="190" height="18" rx="6" fill="#ffffff" fill-opacity="0.08"/>
                        <rect x="8" y="6" width="90" height="6" rx="3" fill="#ffffff" fill-opacity="0.4"/>
                        <circle cx="210" cy="9" r="7.5" fill="#10b981" fill-opacity="0.9"/>
                        <path d="M207 9 L209 11 L213 7" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </g>
                    <!-- Row 2 -->
                    <g transform="translate(60, 186)">
                        <rect x="0" y="0" width="190" height="18" rx="6" fill="#ffffff" fill-opacity="0.06"/>
                        <rect x="8" y="6" width="65" height="6" rx="3" fill="#ffffff" fill-opacity="0.3"/>
                        <circle cx="210" cy="9" r="7.5" fill="#10b981" fill-opacity="0.9"/>
                        <path d="M207 9 L209 11 L213 7" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </g>
                </g>

                <!-- Floating Frosted Glass Folder on Bottom Left -->
                <g transform="translate(30, 160)">
                    <!-- Folder Glow -->
                    <ellipse cx="44" cy="46" rx="36" ry="12" fill="#c084fc" fill-opacity="0.35" filter="blur(8px)"/>
                    
                    <!-- Back Tab -->
                    <path d="M14 16 L 36 16 L 44 23 L 74 23 A 5 5 0 0 1 79 28 L 79 56 A 6 6 0 0 1 73 62 L 14 62 A 6 6 0 0 1 8 56 L 8 22 A 6 6 0 0 1 14 16 Z" 
                          fill="url(#folderFill)" stroke="url(#glassStroke)" stroke-width="1.2"/>
                    
                    <!-- Front Folder Body with Gloss Reflection -->
                    <rect x="8" y="26" width="72" height="36" rx="6" fill="#ffffff" fill-opacity="0.18" stroke="#ffffff" stroke-opacity="0.4" stroke-width="1.2"/>
                    <line x1="16" y1="36" x2="42" y2="36" stroke="#ffffff" stroke-opacity="0.6" stroke-width="2" stroke-linecap="round"/>
                    <line x1="16" y1="44" x2="32" y2="44" stroke="#ffffff" stroke-opacity="0.35" stroke-width="2" stroke-linecap="round"/>
                </g>
            </svg>
        </div>
    </div>

    <!-- RIGHT PANEL: Crisp White Login Form -->
    <div class="login-panel">
        
        <!-- Center Brand Header -->
        <div class="login-header-group">
            <div class="login-brand-row">
                <!-- Brand Logo Icon -->
                <svg class="login-brand-icon" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="brandGradR" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#2563eb"/>
                            <stop offset="100%" stop-color="#4f46e5"/>
                        </linearGradient>
                        <linearGradient id="brandGradR2" x1="0%" y1="100%" x2="100%" y2="0%">
                            <stop offset="0%" stop-color="#0284c7"/>
                            <stop offset="100%" stop-color="#3b82f6"/>
                        </linearGradient>
                    </defs>
                    <rect x="6" y="8" width="8" height="24" rx="4" fill="url(#brandGradR)" />
                    <rect x="26" y="8" width="8" height="24" rx="4" fill="url(#brandGradR)" />
                    <path d="M14 16 C 18 13, 22 13, 26 16 L 26 24 C 22 21, 18 21, 14 24 Z" fill="url(#brandGradR2)" />
                </svg>
                <span class="login-brand-title">HemiFlow</span>
            </div>
            <p class="login-brand-subtitle">Centralized Agency Portal</p>
        </div>

        <!-- Alert Pill Feedback -->
        <?php if ($successMsg): ?>
            <div class="auth-alert success">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;">
                    <circle cx="10" cy="10" r="9" fill="#16a34a"/>
                    <path d="M6.5 10.2L8.8 12.5L13.5 7.5" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span><?= htmlspecialchars($successMsg) ?></span>
            </div>
        <?php elseif ($error): ?>
            <div class="auth-alert error">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;">
                    <circle cx="10" cy="10" r="9" fill="#dc2626"/>
                    <path d="M10 6.5V10.5M10 13.5H10.01" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php elseif ($module): ?>
            <div class="auth-alert info">
                <i data-feather="layers"></i>
                <span>Module: <?= strtoupper(htmlspecialchars($module)) ?></span>
            </div>
        <?php endif; ?>

        <!-- Authentication Form -->
        <form method="POST" action="login.php" class="login-form">
            <input type="hidden" name="module" value="<?= htmlspecialchars($module ?? '') ?>">
            
            <!-- Field: Username or Email -->
            <div class="field-group">
                <label for="username" class="field-label">Username or Email</label>
                <div class="input-wrapper">
                    <i data-feather="mail" class="input-icon-prefix"></i>
                    <input type="text" name="username" id="username" class="field-input" placeholder="Enter your username or email" required autofocus autocomplete="username">
                </div>
            </div>

            <!-- Field: Password with Visibility Toggle -->
            <div class="field-group">
                <label for="password" class="field-label">Password</label>
                <div class="input-wrapper">
                    <i data-feather="lock" class="input-icon-prefix"></i>
                    <input type="password" name="password" id="password" class="field-input has-toggle" placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" class="password-toggle-btn" id="togglePasswordBtn" aria-label="Toggle password visibility">
                        <i data-feather="eye" id="togglePasswordIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-submit-login">
                <span>Log In to <?= $module ? strtoupper(htmlspecialchars($module)) : 'Agency OS' ?></span>
                <i data-feather="arrow-right"></i>
            </button>
        </form>
    </div>

</div>

<!-- Initialize Feather Icons & Password Toggle Interaction -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.feather) {
            feather.replace();
        }

        const toggleBtn = document.getElementById('togglePasswordBtn');
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('togglePasswordIcon');

        if (toggleBtn && passwordInput && toggleIcon) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                
                // Toggle feather icon
                toggleIcon.setAttribute('data-feather', isPassword ? 'eye-off' : 'eye');
                if (window.feather) {
                    feather.replace();
                }
            });
        }
    });
</script>

</body>
</html>