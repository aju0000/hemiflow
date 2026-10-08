<?php
// seed_templates.php - Seed realistic HTML Document Templates into Agency OS DB

require_once __DIR__ . '/db.php';
$db = getDbConnection();

$templates = [
    [
        'document_type' => 'Proposal',
        'template_name' => 'Agency Management System Proposal (Multi-Page)',
        'subject_template' => 'Agency Management System Proposal | Hemito Digital',
        'variables_json' => json_encode(['CLIENT_COMPANY', 'CLIENT_CONTACT', 'SERVICE_NAME', 'PACKAGE_NAME', 'SERVICE_PRICE', 'TOTAL_AMOUNT', 'AGENCY_NAME', 'CUSTOM_SCOPE']),
        'content_template' => '<div class="proposal-body">
<style>
    @page {
        size: A4;
        margin: 0;
    }

    .proposal-body * {
        box-sizing: border-box;
    }

    .proposal-body {
        margin: 0;
        padding: 0;
        background: #eeeeee;
        font-family: "Google Sans", "Google Sans Text", system-ui, sans-serif;
        color: #111111;
    }

    /* ==============================
       A4 PAGE
    ============================== */

    .proposal-body .page {
        width: 210mm;
        min-height: 297mm;
        background: #ffffff;
        margin: 20px auto;
        position: relative;
        padding: 25mm 20mm 28mm 20mm;
        page-break-after: always;
        overflow: hidden;
    }

    /* ==============================
       HEADER
    ============================== */

    .proposal-body .header {
        width: 100%;
        height: 48px;
        display: flex;
        justify-content: flex-end;
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .proposal-body .header img {
        width: 145px;
        height: auto;
        object-fit: contain;
    }

    /* ==============================
       FOOTER
    ============================== */

    .proposal-body .footer {
        position: absolute;
        left: 0;
        bottom: 0;
        width: 100%;
        height: 16mm;
        background: #1f7fbd;
        color: #ffffff;

        display: flex;
        align-items: center;
        justify-content: center;

        font-size: 12px;
        text-align: center;
    }

    .proposal-body .footer a {
        color: #ffffff;
        text-decoration: none;
    }

    /* ==============================
       COVER PAGE
    ============================== */

    .proposal-body .cover {
        padding-top: 18mm;
        display: flex;
        flex-direction: column;
    }

    .proposal-body .cover-logo {
        text-align: center;
        margin-top: 5mm;
    }

    .proposal-body .cover-logo img {
        width: 190px;
    }

    .proposal-body .cover-title {
        margin-top: 18mm;
        text-align: center;
    }

    .proposal-body .cover-title .small-title {
        font-size: 30px;
        font-weight: 400;
        line-height: 1.2;
    }

    .proposal-body .cover-title .main-title {
        font-size: 29px;
        font-weight: 700;
        color: #1f7fbd;
        text-transform: uppercase;
        line-height: 1.25;
    }

    .proposal-body .cover-title .black-title {
        font-size: 29px;
        font-weight: 400;
    }

    .proposal-body .cover-description {
        text-align: center;
        font-size: 17px;
        margin-top: 10px;
        color: #444;
    }

    .proposal-body .cover-illustration {
        width: 330px;
        height: 220px;
        margin: 20mm auto 10mm;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .proposal-body .cover-illustration img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .proposal-body .submission-area {
        display: flex;
        justify-content: space-between;
        margin-top: auto;
        margin-bottom: 25mm;
        gap: 30px;
    }

    .proposal-body .submission-box {
        width: 48%;
        font-size: 15px;
        line-height: 1.55;
    }

    .proposal-body .submission-box strong {
        font-weight: 700;
    }

    .proposal-body .submission-title {
        font-size: 15px;
        margin-bottom: 3px;
    }

    .proposal-body .blue-line {
        width: 3px;
        background: #1f7fbd;
        min-height: 100px;
    }

    /* ==============================
       GENERAL CONTENT
    ============================== */

    .proposal-body .section-title {
        font-size: 28px;
        font-weight: 700;
        margin: 5px 0 22px;
        color: #111111;
    }

    .proposal-body .section-title span {
        color: #1f7fbd;
    }

    .proposal-body .sub-title {
        font-size: 20px;
        font-weight: 700;
        margin: 22px 0 10px;
    }

    .proposal-body .content {
        font-size: 15px;
        line-height: 1.6;
        text-align: justify;
    }

    .proposal-body .content p {
        margin: 0 0 14px;
    }

    .proposal-body .content ul {
        margin: 8px 0 15px 22px;
        padding: 0;
    }

    .proposal-body .content li {
        margin-bottom: 7px;
    }

    /* ==============================
       BLUE HIGHLIGHT BOX
    ============================== */

    .proposal-body .blue-box {
        background: #1f7fbd;
        color: #ffffff;
        padding: 18px 22px;
        border-radius: 2px;
        margin: 20px 0;
    }

    .proposal-body .blue-box h3 {
        margin: 0 0 8px;
        font-size: 19px;
    }

    .proposal-body .blue-box p {
        margin: 0;
        font-size: 14px;
        line-height: 1.5;
    }

    /* ==============================
       MODULE LABEL
    ============================== */

    .proposal-body .module-label {
        display: inline-block;
        background: #1f7fbd;
        color: #ffffff;
        padding: 7px 18px;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 10px;
    }

    /* ==============================
       WORKFLOW
    ============================== */

    .proposal-body .workflow {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-top: 25px;
        gap: 5px;
    }

    .proposal-body .workflow-item {
        flex: 1;
        text-align: center;
        position: relative;
    }

    .proposal-body .workflow-circle {
        width: 48px;
        height: 48px;
        border: 4px solid #1f7fbd;
        border-radius: 50%;
        margin: 0 auto 10px;

        display: flex;
        align-items: center;
        justify-content: center;

        color: #1f7fbd;
        font-weight: 700;
        font-size: 15px;
    }

    .proposal-body .workflow-item h4 {
        margin: 0;
        font-size: 12px;
        line-height: 1.3;
    }

    .proposal-body .workflow-arrow {
        color: #1f7fbd;
        font-size: 25px;
        margin-top: 10px;
    }

    /* ==============================
       MODULE CARDS
    ============================== */

    .proposal-body .module-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        margin-top: 20px;
    }

    .proposal-body .module-card {
        border: 1px solid #cccccc;
        padding: 16px;
        min-height: 125px;
    }

    .proposal-body .module-card h3 {
        color: #1f7fbd;
        font-size: 17px;
        margin: 0 0 7px;
    }

    .proposal-body .module-card p {
        font-size: 13px;
        line-height: 1.5;
        margin: 0;
    }

    /* ==============================
       TASK FLOW
    ============================== */

    .proposal-body .task-flow {
        margin-top: 20px;
    }

    .proposal-body .task-step {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
    }

    .proposal-body .task-number {
        width: 32px;
        height: 32px;
        background: #1f7fbd;
        color: #ffffff;
        border-radius: 50%;

        display: flex;
        align-items: center;
        justify-content: center;

        font-size: 12px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .proposal-body .task-name {
        margin-left: 12px;
        font-size: 14px;
        font-weight: 600;
    }

    .proposal-body .task-arrow {
        color: #1f7fbd;
        margin-left: 14px;
        font-weight: bold;
    }

    /* ==============================
       STATUS BOXES
    ============================== */

    .proposal-body .status-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-top: 15px;
    }

    .proposal-body .status {
        border: 1px solid #d4d4d4;
        padding: 12px;
        text-align: center;
        font-size: 13px;
        font-weight: 600;
    }

    .proposal-body .status.active {
        border-color: #1f7fbd;
        color: #1f7fbd;
    }

    .proposal-body .status.completed {
        background: #1f7fbd;
        color: #ffffff;
        border-color: #1f7fbd;
    }

    /* ==============================
       CLIENT DASHBOARD
    ============================== */

    .proposal-body .dashboard-box {
        border: 2px solid #1f7fbd;
        padding: 18px;
        margin-top: 20px;
    }

    .proposal-body .dashboard-header {
        background: #1f7fbd;
        color: #ffffff;
        padding: 14px;
        font-size: 18px;
        font-weight: 700;
    }

    .proposal-body .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-top: 15px;
    }

    .proposal-body .dashboard-card {
        border: 1px solid #d0d0d0;
        padding: 15px;
    }

    .proposal-body .dashboard-card strong {
        display: block;
        color: #1f7fbd;
        font-size: 15px;
        margin-bottom: 5px;
    }

    .proposal-body .dashboard-card span {
        font-size: 13px;
    }

    /* ==============================
       APPROVAL FLOW
    ============================== */

    .proposal-body .approval-flow {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 20px;
    }

    .proposal-body .approval-step {
        flex: 1;
        text-align: center;
        border: 1px solid #cccccc;
        padding: 14px 8px;
        min-height: 85px;
    }

    .proposal-body .approval-step strong {
        display: block;
        color: #1f7fbd;
        font-size: 13px;
        margin-bottom: 5px;
    }

    .proposal-body .approval-step span {
        font-size: 11px;
        line-height: 1.3;
    }

    .proposal-body .approval-arrow {
        color: #1f7fbd;
        font-size: 20px;
    }

    /* ==============================
       FEATURE LIST
    ============================== */

    .proposal-body .feature-list {
        columns: 2;
        column-gap: 35px;
        margin-top: 15px;
    }

    .proposal-body .feature-list li {
        break-inside: avoid;
        font-size: 14px;
        margin-bottom: 8px;
    }

    /* ==============================
       TABLE
    ============================== */

    .proposal-body table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        font-size: 13px;
    }

    .proposal-body th {
        background: #1f7fbd;
        color: #ffffff;
        padding: 12px;
        text-align: left;
        border: 1px solid #111111;
    }

    .proposal-body td {
        padding: 11px;
        border: 1px solid #333333;
        vertical-align: top;
    }

    /* ==============================
       TECHNOLOGY
    ============================== */

    .proposal-body .tech-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin-top: 20px;
    }

    .proposal-body .tech-item {
        border: 1px solid #1f7fbd;
        color: #1f7fbd;
        padding: 12px;
        text-align: center;
        font-size: 13px;
        font-weight: 700;
    }

    /* ==============================
       PRINT
    ============================== */

    @media print {
        body {
            background: #ffffff;
        }
        .proposal-body .page {
            margin: 0;
            box-shadow: none;
            page-break-after: always;
        }
    }

    @media screen {
        .proposal-body .page {
            box-shadow: 0 3px 20px rgba(0,0,0,0.12);
        }
    }
</style>

<!-- PAGE 1 — COVER -->
<div class="page cover">
    <div class="cover-logo">
        <img src="asset/hemitologo-dark-blue.webp" alt="Hemito Digital">
    </div>
    <div class="cover-title">
        <div class="small-title">Comprehensive</div>
        <div class="main-title">Agency Management</div>
        <div class="black-title">&amp; Client Workflow Platform</div>
    </div>
    <div class="cover-description">
        Digital Workflow &amp; Project Management Solution
    </div>
    <div class="cover-illustration">
        <img src="asset/hemitologo-dark-blue.webp" alt="Agency Management System">
    </div>
    <div class="submission-area">
        <div class="submission-box">
            <div class="submission-title">Prepared for:</div>
            <strong>{{CLIENT_COMPANY}}</strong><br>
            2nd Floor, ACEL Tower,<br>
            Ambady Lane, Near Little Flower Church,<br>
            Elamkulam, Kadavanthra, Kochi - 682020
        </div>
        <div class="blue-line"></div>
        <div class="submission-box">
            <div class="submission-title">Solution:</div>
            <strong>Agency Management &amp; Client Workflow Platform</strong><br>
            Sales Management, Project Management, Task Management, Client Dashboard &amp; Reporting
        </div>
    </div>
    <div class="footer">
        <a href="https://www.hemitodigital.com">www.hemitodigital.com</a> &nbsp; | &nbsp;
        <a href="mailto:sales@hemitodigital.com">sales@hemitodigital.com</a> &nbsp; | &nbsp;
        +91-8921992187
    </div>
</div>

<!-- PAGE 2 — OVERVIEW -->
<div class="page">
    <div class="header">
        <img src="asset/hemitologo-dark-blue.webp" alt="Hemito Digital">
    </div>
    <h1 class="section-title">About the <span>Platform</span></h1>
    <div class="content">
        <p>The proposed Agency Management &amp; Client Workflow Platform is designed to centralize and streamline Hemito Digital\'s internal business operations and client communication.</p>
        <p>The platform will connect the sales, project management, development and client approval workflows in a single system. This will provide better visibility into ongoing work, improve task coordination and simplify communication between Hemito Digital and its clients.</p>
        <p>The solution will also provide management with centralized reporting and performance visibility across projects, tasks, clients and teams.</p>
    </div>
    <div class="blue-box">
        <h3>Objective</h3>
        <p>To provide a structured digital workflow that allows Hemito Digital to manage the complete journey from client acquisition and approval through project execution, development, client review and final completion.</p>
    </div>
    <h2 class="sub-title">Core Workflow</h2>
    <div class="workflow">
        <div class="workflow-item"><div class="workflow-circle">01</div><h4>Sales</h4></div>
        <div class="workflow-arrow">→</div>
        <div class="workflow-item"><div class="workflow-circle">02</div><h4>Client Approval</h4></div>
        <div class="workflow-arrow">→</div>
        <div class="workflow-item"><div class="workflow-circle">03</div><h4>Project</h4></div>
        <div class="workflow-arrow">→</div>
        <div class="workflow-item"><div class="workflow-circle">04</div><h4>Team Lead</h4></div>
        <div class="workflow-arrow">→</div>
        <div class="workflow-item"><div class="workflow-circle">05</div><h4>Task Assignment</h4></div>
        <div class="workflow-arrow">→</div>
        <div class="workflow-item"><div class="workflow-circle">06</div><h4>Development</h4></div>
    </div>
    <div class="workflow" style="margin-top:30px;">
        <div class="workflow-item"><div class="workflow-circle">07</div><h4>Review</h4></div>
        <div class="workflow-arrow">→</div>
        <div class="workflow-item"><div class="workflow-circle">08</div><h4>Client Approval</h4></div>
        <div class="workflow-arrow">→</div>
        <div class="workflow-item"><div class="workflow-circle">09</div><h4>Completion</h4></div>
        <div class="workflow-arrow">→</div>
        <div class="workflow-item"><div class="workflow-circle">10</div><h4>Reports</h4></div>
    </div>
    <div class="footer">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187</div>
</div>

<!-- PAGE 3 — MODULE 3 -->
<div class="page">
    <div class="header">
        <img src="asset/hemitologo-dark-blue.webp" alt="Hemito Digital">
    </div>
    <div class="module-label">MODULE 03</div>
    <h1 class="section-title">Template <span>Generator</span></h1>
    <div class="content">
        <p>The Template Generator module provides a structured method for creating standardized business documents using predefined templates and existing client and project information.</p>
        <p>The module reduces repetitive manual document preparation and ensures consistency across client-facing documents.</p>
    </div>
    <h2 class="sub-title">Key Features</h2>
    <ul class="feature-list">
        <li>Proposal generation</li>
        <li>Quotation generation</li>
        <li>Contract generation</li>
        <li>Service agreements</li>
        <li>Invoice documents</li>
        <li>Meeting / MOM documents</li>
        <li>Payment reminders</li>
        <li>Renewal documents</li>
        <li>Client information auto-population</li>
        <li>Project information auto-population</li>
        <li>Document preview</li>
        <li>PDF generation</li>
        <li>Document version management</li>
        <li>Document history</li>
    </ul>
    <h2 class="sub-title">Document Workflow</h2>
    <div class="task-flow">
        <div class="task-step"><div class="task-number">01</div><div class="task-name">Select Client</div><div class="task-arrow">→</div></div>
        <div class="task-step"><div class="task-number">02</div><div class="task-name">Select Project</div><div class="task-arrow">→</div></div>
        <div class="task-step"><div class="task-number">03</div><div class="task-name">Select Template</div><div class="task-arrow">→</div></div>
        <div class="task-step"><div class="task-number">04</div><div class="task-name">Auto Populate Information</div><div class="task-arrow">→</div></div>
        <div class="task-step"><div class="task-number">05</div><div class="task-name">Preview Document</div><div class="task-arrow">→</div></div>
        <div class="task-step"><div class="task-number">06</div><div class="task-name">Save / Generate PDF</div></div>
    </div>
    <div class="blue-box">
        <h3>Template-Based Documentation</h3>
        <p>Documents can use predefined placeholders such as client name, project name, service details, amount, dates and other project information. This enables consistent and efficient document generation.</p>
    </div>
    <div class="footer">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187</div>
</div>

<!-- PAGE 4 — MODULE 4 -->
<div class="page">
    <div class="header">
        <img src="asset/hemitologo-dark-blue.webp" alt="Hemito Digital">
    </div>
    <div class="module-label">MODULE 04</div>
    <h1 class="section-title">Project &amp; <span>Task Management</span></h1>
    <div class="content">
        <p>The Project &amp; Task Management module manages the execution stage after a client requirement has been approved. The system allows the Development Team Lead to convert project requirements into actionable tasks and assign them to the appropriate team members.</p>
    </div>
    <h2 class="sub-title">Project Structure</h2>
    <div class="module-grid">
        <div class="module-card"><h3>Client</h3><p>Central client record containing the client\'s projects, requirements and related information.</p></div>
        <div class="module-card"><h3>Project</h3><p>Represents a specific service or engagement being delivered to the client.</p></div>
        <div class="module-card"><h3>Task</h3><p>Individual pieces of work created from the project requirement.</p></div>
        <div class="module-card"><h3>Subtask</h3><p>Smaller activities required to complete a larger task.</p></div>
    </div>
    <h2 class="sub-title">Development Workflow</h2>
    <div class="workflow">
        <div class="workflow-item"><div class="workflow-circle">01</div><h4>Requirement</h4></div><div class="workflow-arrow">→</div>
        <div class="workflow-item"><div class="workflow-circle">02</div><h4>Lead Creates Task</h4></div><div class="workflow-arrow">→</div>
        <div class="workflow-item"><div class="workflow-circle">03</div><h4>Assignment</h4></div><div class="workflow-arrow">→</div>
        <div class="workflow-item"><div class="workflow-circle">04</div><h4>Development</h4></div><div class="workflow-arrow">→</div>
        <div class="workflow-item"><div class="workflow-circle">05</div><h4>Review</h4></div>
    </div>
    <h2 class="sub-title">Task Status</h2>
    <div class="status-grid">
        <div class="status">Pending</div>
        <div class="status active">In Progress</div>
        <div class="status active">Under Review</div>
        <div class="status active">Client Approval</div>
        <div class="status">Revision Required</div>
        <div class="status completed">Completed</div>
    </div>
    <div class="footer">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187</div>
</div>

<!-- PAGE 5 — CLIENT DASHBOARD -->
<div class="page">
    <div class="header">
        <img src="asset/hemitologo-dark-blue.webp" alt="Hemito Digital">
    </div>
    <div class="module-label">CLIENT MODULE</div>
    <h1 class="section-title">Client <span>Dashboard</span></h1>
    <div class="content">
        <p>A dedicated Client Dashboard will provide clients with a secure and transparent view of their projects and work progress.</p>
        <p>Clients will only be able to access their own dashboard, projects, tasks, documents, meetings and approval requests. Internal agency information will remain restricted.</p>
    </div>
    <div class="dashboard-box">
        <div class="dashboard-header">Client Dashboard</div>
        <div class="dashboard-grid">
            <div class="dashboard-card"><strong>Active Projects</strong><span>View current projects and overall progress.</span></div>
            <div class="dashboard-card"><strong>Task Status</strong><span>See where each task currently stands.</span></div>
            <div class="dashboard-card"><strong>Project Timeline</strong><span>Track the progress of project activities.</span></div>
            <div class="dashboard-card"><strong>Pending Approvals</strong><span>Review work waiting for client approval.</span></div>
            <div class="dashboard-card"><strong>Meetings</strong><span>View scheduled meetings and Google Meet links.</span></div>
            <div class="dashboard-card"><strong>Documents</strong><span>Access client-specific project documents.</span></div>
        </div>
    </div>
    <h2 class="sub-title">Client Approval Workflow</h2>
    <div class="approval-flow">
        <div class="approval-step"><strong>Development</strong><span>Work is completed by the development team.</span></div><div class="approval-arrow">→</div>
        <div class="approval-step"><strong>Team Review</strong><span>Team Lead reviews the completed work.</span></div><div class="approval-arrow">→</div>
        <div class="approval-step"><strong>Client Review</strong><span>Client reviews the submitted work.</span></div><div class="approval-arrow">→</div>
        <div class="approval-step"><strong>Approval / Revision</strong><span>Client approves or requests changes.</span></div>
    </div>
    <div class="blue-box">
        <h3>Client-Only Access</h3>
        <p>The Client Dashboard will be completely isolated from internal agency modules. Clients will not have access to Sales, Development Management, Employee information, Reports or other client accounts.</p>
    </div>
    <div class="footer">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187</div>
</div>

<!-- PAGE 6 — GOOGLE MEET + SMS -->
<div class="page">
    <div class="header">
        <img src="asset/hemitologo-dark-blue.webp" alt="Hemito Digital">
    </div>
    <h1 class="section-title">Client <span>Communication</span></h1>
    <h2 class="sub-title">Google Meet Integration</h2>
    <div class="content">
        <p>Project-related meetings can be scheduled by authorized Hemito Digital users and displayed directly within the client\'s dashboard.</p>
    </div>
    <div class="task-flow">
        <div class="task-step"><div class="task-number">01</div><div class="task-name">Meeting Created</div><div class="task-arrow">→</div></div>
        <div class="task-step"><div class="task-number">02</div><div class="task-name">Google Meet Link Added</div><div class="task-arrow">→</div></div>
        <div class="task-step"><div class="task-number">03</div><div class="task-name">Client Notification</div><div class="task-arrow">→</div></div>
        <div class="task-step"><div class="task-number">04</div><div class="task-name">SMS Notification</div><div class="task-arrow">→</div></div>
        <div class="task-step"><div class="task-number">05</div><div class="task-name">Client Joins Meeting</div></div>
    </div>
    <h2 class="sub-title">SMS Notifications</h2>
    <div class="content">
        <p>Important project events can trigger SMS notifications to the client\'s registered mobile number.</p>
    </div>
    <ul>
        <li>Client approval required</li>
        <li>Client approval received</li>
        <li>Revision requested</li>
        <li>Meeting scheduled</li>
        <li>Meeting updated</li>
        <li>Project completion</li>
        <li>Important project updates</li>
    </ul>
    <div class="blue-box">
        <h3>Example Notification</h3>
        <p>Your project task is ready for approval. Please log in to your Client Dashboard to review the work and provide your approval.</p>
    </div>
    <h2 class="sub-title">Secure Client Access</h2>
    <div class="content">
        <p>SMS notifications will direct the client to the secure Client Dashboard. Authentication and server-side authorization will ensure that the client can only access information associated with their own account.</p>
    </div>
    <div class="footer">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187</div>
</div>

<!-- PAGE 7 — MODULE 9 -->
<div class="page">
    <div class="header">
        <img src="asset/hemitologo-dark-blue.webp" alt="Hemito Digital">
    </div>
    <div class="module-label">MODULE 09</div>
    <h1 class="section-title">Reports &amp; <span>Management Dashboard</span></h1>
    <div class="content">
        <p>The Reports &amp; Management Dashboard provides centralized visibility into the performance of clients, projects, tasks, teams and overall agency operations.</p>
        <p>Dashboard information will be generated from actual system data to provide management with real-time operational visibility.</p>
    </div>
    <div class="module-grid">
        <div class="module-card"><h3>Client Overview</h3><p>Active, paused and completed client accounts.</p></div>
        <div class="module-card"><h3>Project Overview</h3><p>Active, completed, delayed and on-hold projects.</p></div>
        <div class="module-card"><h3>Task Overview</h3><p>Pending, active, review, approval and completed tasks.</p></div>
        <div class="module-card"><h3>Team Performance</h3><p>Workload, completed tasks and pending work.</p></div>
        <div class="module-card"><h3>SLA Monitoring</h3><p>On-time tasks, delayed tasks and SLA breaches.</p></div>
        <div class="module-card"><h3>Sales Overview</h3><p>Leads, conversions and sales performance.</p></div>
    </div>
    <h2 class="sub-title">Management Drill-Down</h2>
    <div class="task-flow">
        <div class="task-step"><div class="task-number">01</div><div class="task-name">Management Dashboard</div><div class="task-arrow">→</div></div>
        <div class="task-step"><div class="task-number">02</div><div class="task-name">Department / Project</div><div class="task-arrow">→</div></div>
        <div class="task-step"><div class="task-number">03</div><div class="task-name">Team Lead</div><div class="task-arrow">→</div></div>
        <div class="task-step"><div class="task-number">04</div><div class="task-name">Developer</div><div class="task-arrow">→</div></div>
        <div class="task-step"><div class="task-number">05</div><div class="task-name">Individual Task</div></div>
    </div>
    <div class="footer">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187</div>
</div>

<!-- PAGE 8 — ACCESS & TECHNOLOGY -->
<div class="page">
    <div class="header">
        <img src="asset/hemitologo-dark-blue.webp" alt="Hemito Digital">
    </div>
    <h1 class="section-title">Access Control &amp; <span>Technology</span></h1>
    <div class="content">
        <p>The platform will use role-based and module-level access control so that each user can access only the functionality relevant to their responsibilities.</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>Role</th>
                <th>Primary Access</th>
            </tr>
        </thead>
        <tbody>
            <tr><td><strong>Super Admin</strong></td><td>Complete system access</td></tr>
            <tr><td><strong>Agency Admin</strong></td><td>Agency-wide operational access</td></tr>
            <tr><td><strong>Sales / Account Manager</strong></td><td>Sales, clients and project information</td></tr>
            <tr><td><strong>Development Team Lead</strong></td><td>Projects, tasks, assignments and reviews</td></tr>
            <tr><td><strong>Developer</strong></td><td>Assigned tasks and development activities</td></tr>
            <tr><td><strong>Management</strong></td><td>Reports and management dashboards</td></tr>
            <tr><td><strong>Client</strong></td><td>Client Dashboard only</td></tr>
        </tbody>
    </table>
    <h2 class="sub-title">Technology Stack</h2>
    <div class="tech-grid">
        <div class="tech-item">PHP</div>
        <div class="tech-item">MySQL</div>
        <div class="tech-item">HTML5</div>
        <div class="tech-item">CSS3</div>
        <div class="tech-item">JavaScript</div>
        <div class="tech-item">AJAX</div>
        <div class="tech-item">Bootstrap</div>
        <div class="tech-item">PDO</div>
    </div>
    <h2 class="sub-title">Security</h2>
    <ul>
        <li>Role-based authentication</li>
        <li>Module-level authorization</li>
        <li>Client-specific data isolation</li>
        <li>Secure session management</li>
        <li>Prepared SQL statements</li>
        <li>Secure file access</li>
        <li>Activity and audit logging</li>
        <li>Client approval history</li>
    </ul>
    <div class="blue-box">
        <h3>Outcome</h3>
        <p>The platform will provide Hemito Digital with a centralized workflow for managing clients, projects, development tasks, approvals and management reporting while providing clients with transparent and secure visibility into their work.</p>
    </div>
    <div class="footer">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187</div>
</div>
</div>'
    ],
    [
        'document_type' => 'Invoice',
        'template_name' => 'Official Tax Invoice Template',
        'subject_template' => 'Tax Invoice {{DOCUMENT_NUMBER}} - {{CLIENT_COMPANY}}',
        'variables_json' => json_encode(['DOCUMENT_NUMBER', 'CLIENT_COMPANY', 'CLIENT_CONTACT', 'SERVICE_NAME', 'SERVICE_PRICE', 'TAX_AMOUNT', 'TOTAL_AMOUNT']),
        'content_template' => '<div style="font-family: \'Google Sans\', \'Google Sans Text\', \'Segoe UI\', Helvetica, Arial, sans-serif; color: #1e293b; max-width: 800px; margin: 0 auto; background: #ffffff; padding: 40px; border: 1px solid #e2e8f0; border-radius: 8px;">
  <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 30px;">
    <div>
      <h1 style="color: #0f172a; margin: 0; font-size: 28px; font-weight: 800;">TAX INVOICE</h1>
      <p style="color: #2563eb; margin: 4px 0 0 0; font-size: 14px; font-weight: 700;">{{AGENCY_NAME}}</p>
      <p style="color: #64748b; margin: 4px 0 0 0; font-size: 12px; line-height: 1.4;">{{AGENCY_ADDRESS}}<br>GSTIN: {{AGENCY_TAX_ID}}</p>
    </div>
    <div style="text-align: right; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
      <p style="margin: 0; font-size: 13px; color: #475569;"><strong>Invoice No:</strong> <span style="color:#2563eb; font-weight:700;">{{DOCUMENT_NUMBER}}</span></p>
      <p style="margin: 6px 0 0 0; font-size: 13px; color: #475569;"><strong>Invoice Date:</strong> {{CURRENT_DATE}}</p>
      <p style="margin: 6px 0 0 0; font-size: 13px; color: #dc2626;"><strong>Due Date:</strong> {{DUE_DATE}}</p>
    </div>
  </div>

  <div style="display: flex; justify-content: space-between; background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
    <div>
      <h4 style="margin: 0 0 6px 0; color: #64748b; font-size: 11px; text-transform: uppercase;">BILLED TO:</h4>
      <p style="margin: 0; font-size: 16px; font-weight: 700; color: #0f172a;">{{CLIENT_COMPANY}}</p>
      <p style="margin: 4px 0 0 0; font-size: 13px; color: #475569;">Attn: {{CLIENT_CONTACT}}</p>
      <p style="margin: 2px 0 0 0; font-size: 13px; color: #475569;">Email: {{CLIENT_EMAIL}} | Phone: {{CLIENT_PHONE}}</p>
    </div>
    <div style="text-align: right;">
      <h4 style="margin: 0 0 6px 0; color: #64748b; font-size: 11px; text-transform: uppercase;">CLIENT GST / TAX ID:</h4>
      <p style="margin: 0; font-size: 14px; font-weight: 600; color: #1e293b;">{{CLIENT_TAX_ID}}</p>
    </div>
  </div>

  <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 14px;">
    <thead>
      <tr style="background: #2563eb; color: #ffffff; text-align: left;">
        <th style="padding: 12px 16px;">Item Description</th>
        <th style="padding: 12px 16px; text-align: center;">Qty</th>
        <th style="padding: 12px 16px; text-align: right;">Rate ({{CURRENCY}})</th>
        <th style="padding: 12px 16px; text-align: right;">Amount ({{CURRENCY}})</th>
      </tr>
    </thead>
    <tbody>
      <tr style="border-bottom: 1px solid #e2e8f0;">
        <td style="padding: 14px 16px;">
          <strong>{{SERVICE_NAME}}</strong>
          <p style="margin: 4px 0 0 0; font-size: 12px; color: #64748b;">{{PACKAGE_NAME}} - Professional Agency Services</p>
        </td>
        <td style="padding: 14px 16px; text-align: center;">1</td>
        <td style="padding: 14px 16px; text-align: right;">₹ {{SERVICE_PRICE}}</td>
        <td style="padding: 14px 16px; text-align: right; font-weight: 600;">₹ {{SERVICE_PRICE}}</td>
      </tr>
    </tbody>
  </table>

  <div style="display: flex; justify-content: flex-end; margin-bottom: 30px;">
    <div style="width: 320px;">
      <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #475569;">
        <span>Subtotal:</span>
        <span style="font-weight: 600; color: #1e293b;">₹ {{SERVICE_PRICE}}</span>
      </div>
      <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #475569;">
        <span>CGST (9%) + SGST (9%):</span>
        <span style="font-weight: 600; color: #1e293b;">₹ {{TAX_AMOUNT}}</span>
      </div>
      <div style="display: flex; justify-content: space-between; padding: 12px 14px; background: #eff6ff; border-radius: 6px; font-weight: 700; color: #1e40af; font-size: 16px; margin-top: 8px;">
        <span>TOTAL DUE:</span>
        <span>₹ {{TOTAL_AMOUNT}}</span>
      </div>
    </div>
  </div>

  <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; font-size: 12px; color: #475569;">
    <p style="margin: 0 0 6px 0; font-weight: 700; color: #0f172a;">BANK DETAILS FOR REMITTANCE:</p>
    <p style="margin: 0; line-height: 1.6;">Account Name: {{AGENCY_NAME}}<br>Bank: HDFC Bank Ltd | Branch: Kadavanthra, Kochi<br>Account No: 50200012345678 | IFSC: HDFC0000123</p>
  </div>
</div>'
    ],
    [
        'document_type' => 'Quotation',
        'template_name' => 'Official Commercial Quotation',
        'subject_template' => 'Commercial Quotation for {{CLIENT_COMPANY}}',
        'variables_json' => json_encode(['CLIENT_COMPANY', 'SERVICE_NAME', 'PACKAGE_NAME', 'TOTAL_AMOUNT']),
        'content_template' => '<div style="font-family: \'Google Sans\', \'Google Sans Text\', \'Segoe UI\', Helvetica, Arial, sans-serif; color: #1e293b; max-width: 800px; margin: 0 auto; background: #ffffff; padding: 40px; border-radius: 8px; border: 1px solid #cbd5e1;">
  <div style="display: flex; justify-content: space-between; border-bottom: 2px solid #0f172a; padding-bottom: 16px; margin-bottom: 24px;">
    <div>
      <h2 style="margin: 0; color: #0f172a; font-size: 24px; font-weight: 800;">COMMERCIAL QUOTATION</h2>
      <p style="margin: 4px 0 0 0; color: #64748b; font-size: 13px;">{{AGENCY_NAME}}</p>
    </div>
    <div style="text-align: right;">
      <p style="margin: 0; font-size: 13px;"><strong>Quote Ref:</strong> {{DOCUMENT_NUMBER}}</p>
      <p style="margin: 4px 0 0 0; font-size: 13px;"><strong>Date:</strong> {{CURRENT_DATE}}</p>
    </div>
  </div>

  <div style="margin-bottom: 24px; background: #f8fafc; padding: 16px; border-radius: 6px;">
    <h4 style="margin: 0 0 6px 0; color: #0f172a;">Quotation For: {{CLIENT_COMPANY}}</h4>
    <p style="margin: 0; font-size: 13px; color: #475569;">Contact Person: {{CLIENT_CONTACT}} ({{CLIENT_EMAIL}})</p>
  </div>

  <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 14px;">
    <thead>
      <tr style="background: #f1f5f9; text-align: left;">
        <th style="padding: 10px 14px; border: 1px solid #cbd5e1;">Service Offered</th>
        <th style="padding: 10px 14px; border: 1px solid #cbd5e1;">Package Tier</th>
        <th style="padding: 10px 14px; border: 1px solid #cbd5e1; text-align: right;">Price ({{CURRENCY}})</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td style="padding: 12px 14px; border: 1px solid #cbd5e1;"><strong>{{SERVICE_NAME}}</strong></td>
        <td style="padding: 12px 14px; border: 1px solid #cbd5e1;">{{PACKAGE_NAME}}</td>
        <td style="padding: 12px 14px; border: 1px solid #cbd5e1; text-align: right;">₹ {{SERVICE_PRICE}}</td>
      </tr>
      <tr style="background: #f8fafc; font-weight: 700;">
        <td colspan="2" style="padding: 12px 14px; border: 1px solid #cbd5e1; text-align: right;">Estimated Total (incl. taxes):</td>
        <td style="padding: 12px 14px; border: 1px solid #cbd5e1; text-align: right; color: #2563eb;">₹ {{TOTAL_AMOUNT}}</td>
      </tr>
    </tbody>
  </table>

  <div style="font-size: 13px; color: #475569;">
    <p><strong>Scope Overview:</strong> {{CUSTOM_SCOPE}}</p>
    <p><strong>Validity:</strong> Quotation valid for 15 days from date of issuance.</p>
  </div>
</div>'
    ],
    [
        'document_type' => 'Contract',
        'template_name' => 'Master Services Agreement (Contract)',
        'subject_template' => 'Master Services Agreement - {{CLIENT_COMPANY}}',
        'variables_json' => json_encode(['CLIENT_COMPANY', 'SERVICE_NAME', 'PACKAGE_NAME', 'TOTAL_AMOUNT']),
        'content_template' => '<div style="font-family: \'Google Sans\', \'Google Sans Text\', \'Segoe UI\', Helvetica, Arial, sans-serif; color: #1e293b; max-width: 800px; margin: 0 auto; background: #ffffff; padding: 40px; border: 1px solid #cbd5e1; border-radius: 8px;">
  <h2 style="text-align: center; color: #0f172a; font-size: 22px; border-bottom: 2px solid #2563eb; padding-bottom: 12px; margin-bottom: 24px;">MASTER SERVICES CONTRACT</h2>
  
  <p style="font-size: 14px; line-height: 1.6;">This Master Services Agreement ("Agreement") is entered into on this date <strong>{{CURRENT_DATE}}</strong> by and between <strong>{{AGENCY_NAME}}</strong> ("Service Provider") and <strong>{{CLIENT_COMPANY}}</strong> ("Client").</p>

  <h3 style="font-size: 15px; color: #0f172a; margin-top: 20px;">1. Scope of Work</h3>
  <p style="font-size: 13px; line-height: 1.6; color: #334155;">Service Provider shall deliver digital agency services for <strong>{{SERVICE_NAME}}</strong> under the <strong>{{PACKAGE_NAME}}</strong> plan as detailed below:</p>
  <div style="background: #f8fafc; padding: 14px; border-radius: 6px; font-size: 13px; border-left: 3px solid #2563eb; margin-bottom: 16px;">
    {{CUSTOM_SCOPE}}
  </div>

  <h3 style="font-size: 15px; color: #0f172a; margin-top: 20px;">2. Financial Terms & Payment Terms</h3>
  <p style="font-size: 13px; line-height: 1.6; color: #334155;">The total contract value is <strong>₹ {{TOTAL_AMOUNT}} {{CURRENCY}}</strong> (Base Fee: ₹ {{SERVICE_PRICE}}). Billing terms: <strong>{{BILLING_TERMS}}</strong>. Payment terms: <strong>{{PAYMENT_TERMS}}</strong>.</p>

  <h3 style="font-size: 15px; color: #0f172a; margin-top: 20px;">3. Intellectual Property & Confidentiality</h3>
  <p style="font-size: 13px; line-height: 1.6; color: #334155;">All deliverables, source files, and campaign assets developed specifically for Client shall become Client property upon full settlement of invoice fees. Both parties agree to maintain strict confidentiality of proprietary data.</p>

  <div style="display: flex; justify-content: space-between; margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
    <div>
      <p style="margin: 0; font-weight: 700;">{{AGENCY_NAME}}</p>
      <p style="margin: 4px 0 0 0; font-size: 12px; color: #64748b;">Authorized Representative Signature</p>
    </div>
    <div>
      <p style="margin: 0; font-weight: 700;">{{CLIENT_COMPANY}}</p>
      <p style="margin: 4px 0 0 0; font-size: 12px; color: #64748b;">Client Authorized Signatory</p>
    </div>
  </div>
</div>'
    ],
    [
        'document_type' => 'Service Agreement',
        'template_name' => 'Service Level Agreement (SLA)',
        'subject_template' => 'SLA & Service Level Agreement - {{CLIENT_COMPANY}}',
        'variables_json' => json_encode(['CLIENT_COMPANY', 'SERVICE_NAME']),
        'content_template' => '<div style="font-family: \'Google Sans\', \'Google Sans Text\', \'Segoe UI\', Helvetica, Arial, sans-serif; color: #1e293b; max-width: 800px; margin: 0 auto; background: #ffffff; padding: 40px; border-radius: 8px; border: 1px solid #cbd5e1;">
  <h2 style="color: #0f172a; font-size: 22px; border-bottom: 2px solid #2563eb; padding-bottom: 10px; margin-bottom: 20px;">SERVICE LEVEL AGREEMENT (SLA)</h2>
  
  <p style="font-size: 14px; color: #475569;"><strong>Client:</strong> {{CLIENT_COMPANY}} | <strong>Date:</strong> {{CURRENT_DATE}} | <strong>Doc Ref:</strong> {{DOCUMENT_NUMBER}}</p>

  <h3 style="font-size: 15px; color: #0f172a; margin-top: 20px;">1. Service Availability & Response Metrics</h3>
  <p style="font-size: 13px; line-height: 1.6; color: #334155;">This Service Level Agreement governs technical support and deliverable maintenance for <strong>{{SERVICE_NAME}}</strong>.</p>
  <ul style="font-size: 13px; color: #334155; line-height: 1.8;">
    <li><strong>Critical Priority Issues:</strong> Response within 2 hours; resolution window 12 hours.</li>
    <li><strong>Standard Maintenance & Updates:</strong> Turnaround within 24–48 hours.</li>
    <li><strong>System Uptime Target:</strong> 99.9% availability for hosted services.</li>
  </ul>

  <h3 style="font-size: 15px; color: #0f172a; margin-top: 20px;">2. Maintenance & Scope Boundary</h3>
  <div style="background: #f8fafc; padding: 14px; border-radius: 6px; font-size: 13px; color: #334155;">
    {{CUSTOM_SCOPE}}
  </div>
</div>'
    ],
    [
        'document_type' => 'MOM',
        'template_name' => 'Minutes of Meeting (MOM)',
        'subject_template' => 'Minutes of Meeting - {{CLIENT_COMPANY}}',
        'variables_json' => json_encode(['CLIENT_COMPANY', 'CLIENT_CONTACT']),
        'content_template' => '<div style="font-family: \'Google Sans\', \'Google Sans Text\', \'Segoe UI\', Helvetica, Arial, sans-serif; color: #1e293b; max-width: 800px; margin: 0 auto; background: #ffffff; padding: 40px; border-radius: 8px; border: 1px solid #cbd5e1;">
  <div style="border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 20px;">
    <h2 style="margin: 0; color: #0f172a; font-size: 22px;">MINUTES OF MEETING (MOM)</h2>
    <p style="margin: 4px 0 0 0; color: #64748b; font-size: 13px;">Date: {{CURRENT_DATE}} | Meeting Ref: {{DOCUMENT_NUMBER}}</p>
  </div>

  <div style="background: #f8fafc; padding: 16px; border-radius: 6px; margin-bottom: 20px; font-size: 13px;">
    <p style="margin: 0 0 4px 0;"><strong>Client Company:</strong> {{CLIENT_COMPANY}}</p>
    <p style="margin: 0 0 4px 0;"><strong>Attendees (Client):</strong> {{CLIENT_CONTACT}} ({{CLIENT_EMAIL}})</p>
    <p style="margin: 0;"><strong>Attendees (Agency):</strong> {{AGENCY_NAME}} Team Lead & Account Manager</p>
  </div>

  <h3 style="font-size: 15px; color: #0f172a;">Key Discussion Points & Action Items</h3>
  <div style="font-size: 13px; line-height: 1.7; color: #334155; background: #ffffff; border: 1px solid #e2e8f0; padding: 16px; border-radius: 6px;">
    {{CUSTOM_NOTES}}
  </div>
</div>'
    ]
];

$stmtCheck = $db->prepare("SELECT id FROM document_templates WHERE document_type = ?");
$stmtInsert = $db->prepare("INSERT INTO document_templates (document_type, template_name, subject_template, content_template, variables_json) VALUES (?, ?, ?, ?, ?)");
$stmtUpdate = $db->prepare("UPDATE document_templates SET template_name = ?, subject_template = ?, content_template = ?, variables_json = ? WHERE id = ?");

$count = 0;
foreach ($templates as $t) {
    $stmtCheck->execute([$t['document_type']]);
    $existingId = $stmtCheck->fetchColumn();
    if ($existingId) {
        $stmtUpdate->execute([$t['template_name'], $t['subject_template'], $t['content_template'], $t['variables_json'], $existingId]);
        $count++;
    } else {
        $stmtInsert->execute([$t['document_type'], $t['template_name'], $t['subject_template'], $t['content_template'], $t['variables_json']]);
        $count++;
    }
}

echo "Processed {$count} document templates successfully into Agency OS database.\n";
