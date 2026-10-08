<?php
// template_generator.php - Module 3 Template Generator & Document Customizer Suite

require_once __DIR__ . '/auth.php';
checkAuth('template-generator');

$user = getCurrentUser();
$db = getDbConnection();

$pageTitle = "Module 3 — Premium Template Generator & Customizer Suite";
require_once __DIR__ . '/header.php';

// Fetch active clients
$clients = $db->query("SELECT * FROM clients ORDER BY company_name ASC")->fetchAll();

// Fetch templates
$templates = $db->query("SELECT * FROM document_templates WHERE is_active=1 ORDER BY document_type, template_name")->fetchAll();

// Group templates by document type
$templatesByType = [];
foreach ($templates as $t) {
    $templatesByType[$t['document_type']][] = $t;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:20px; font-weight:700;">Document Generator & Customizer Suite</h2>
        <p style="color:var(--text-muted); font-size:14px;">Create new documents, edit existing saved documents, build custom templates, and manage multi-page proposals.</p>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <button class="btn btn-primary" onclick="openNewDocumentModal()">
            <i data-feather="plus-circle"></i> + Add New Document
        </button>
        <button class="btn btn-outline" onclick="openSavedDocumentsModal()">
            <i data-feather="folder"></i> Saved Documents & Edit
        </button>
        <button class="btn btn-outline" onclick="openTemplateEditorModal()">
            <i data-feather="edit-3"></i> Edit / Add Template
        </button>
        <button class="btn btn-outline" onclick="openDocumentTypesModal()">
            <i data-feather="tag"></i> Document Types
        </button>
        <button class="btn btn-outline" onclick="openQuickClientModal()">
            <i data-feather="user-plus"></i> + Add Client
        </button>
        <button class="btn btn-outline" onclick="openVersionHistoryModal()">
            <i data-feather="clock"></i> Version History
        </button>
    </div>
</div>

<div class="grid-2" style="grid-template-columns: 380px 1fr; align-items: start;">

    <!-- Left Column: Generator Configuration & Custom Fields -->
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h3 class="card-title"><i data-feather="sliders" style="width:16px; height:16px;"></i> Document Configuration</h3>
            <span id="editingDocBadge" class="badge badge-info" style="display:none;">Editing Mode</span>
        </div>

        <form id="generatorForm" onsubmit="event.preventDefault(); previewDocument();">
            <input type="hidden" id="activeDocumentId" value="0">

            <!-- Mode Selector / Saved Document Selector -->
            <div class="form-group" style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; padding:10px;">
                <label class="form-label" style="color:#0369a1; font-weight:700;">Mode / Load Saved Document</label>
                <select id="savedDocQuickSelect" class="form-select" onchange="onQuickSavedDocSelected()">
                    <option value="0">Mode: Create New Document from Template</option>
                    <!-- Populated dynamically -->
                </select>
            </div>

            <!-- Step 1: Select Client -->
            <div class="form-group">
                <label class="form-label">1. Select Client *</label>
                <select id="clientSelect" class="form-select" onchange="onClientChanged()" required>
                    <option value="">-- Choose Existing Client --</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>" 
                            data-company="<?= htmlspecialchars($c['company_name']) ?>"
                            data-contact="<?= htmlspecialchars($c['contact_person']) ?>"
                            data-email="<?= htmlspecialchars($c['email']) ?>"
                            data-taxid="<?= htmlspecialchars($c['tax_id'] ?? '') ?>"
                            data-address="<?= htmlspecialchars($c['address'] ?? '') ?>">
                            <?= htmlspecialchars($c['company_name']) ?> (<?= htmlspecialchars($c['contact_person']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Step 2: Select Client Service Package -->
            <div class="form-group">
                <label class="form-label">2. Select Service Package *</label>
                <select id="serviceSelect" class="form-select" onchange="onServiceChanged()" required>
                    <option value="">-- Select Client First --</option>
                </select>
            </div>

            <!-- Step 3: Document Type & Template -->
            <div class="form-group">
                <label class="form-label">3. Document Type *</label>
                <select id="docTypeSelect" class="form-select" onchange="onDocTypeChanged()" required>
                    <option value="">-- Select Document Type --</option>
                    <option value="Proposal">Business Proposal (Premium Multi-Page)</option>
                    <option value="Invoice">Tax Invoice</option>
                    <option value="Quotation">Official Quotation</option>
                    <option value="Contract">Master Services Contract</option>
                    <option value="Service Agreement">Service Agreement (SLA)</option>
                    <option value="MOM">Minutes of Meeting (MOM)</option>
                    <option value="Payment Reminder">Payment Reminder Letter</option>
                    <option value="Renewal Letter">Contract Renewal Letter</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">4. Select Template *</label>
                <select id="templateSelect" class="form-select" onchange="previewDocument()" required>
                    <option value="">-- Select Template --</option>
                </select>
            </div>

            <!-- Auto-filled Metadata Summary -->
            <div id="autofillSummary" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px; margin-bottom:18px; font-size:12px; display:none;">
                <p style="font-weight:700; color:#334155; margin-bottom:4px;">Auto-Loaded Metadata:</p>
                <div id="autofillDetails" style="color:#64748b; line-height:1.5;"></div>
            </div>

            <!-- Custom Template Variable Input Fields -->
            <div class="card-header" style="margin-top:10px; padding-bottom:8px;">
                <h4 style="font-size:14px; font-weight:700;">Custom Fields & Scope Settings</h4>
            </div>

            <div class="form-group">
                <label class="form-label">Agency Name</label>
                <input type="text" id="agencyName" class="form-control" value="Hemito Digital Pvt Ltd" onchange="previewDocument()">
            </div>

            <div class="form-group">
                <label class="form-label">Agency Address & Phone</label>
                <input type="text" id="agencyAddress" class="form-control" value="2nd Floor, ACEL Tower, Kadavanthra, Kochi | +91-8921992187" onchange="previewDocument()">
            </div>

            <div class="form-group">
                <label class="form-label">Scope of Work / Deliverables</label>
                <textarea id="customScope" class="form-control" placeholder="Enter custom technical scope or key milestones..." onchange="previewDocument()"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Meeting Notes / Custom Terms</label>
                <textarea id="customNotes" class="form-control" placeholder="Enter specific terms or MOM notes..." onchange="previewDocument()"></textarea>
            </div>

            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" style="flex:1;" onclick="previewDocument()">
                    <i data-feather="eye"></i> Refresh Preview
                </button>
                <button type="button" id="saveDocBtn" class="btn btn-primary" style="flex:1;" onclick="generateDocument()">
                    <i data-feather="save"></i> Save Document
                </button>
            </div>
        </form>
    </div>

    <!-- Right Column: Live Document Preview & Actions -->
    <div>
        <div class="card" style="margin-bottom: 16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div>
                    <span id="previewStatusBadge" class="badge badge-warning">Draft Preview</span>
                    <strong id="previewDocTitle" style="margin-left:10px; font-size:15px; color:var(--secondary);">Select template to generate</strong>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button id="directEditBtn" class="btn btn-outline btn-sm" onclick="toggleDirectLiveEdit()" title="Edit Content Directly on Page">
                        <i data-feather="edit-2"></i> Edit Content
                    </button>
                    <button class="btn btn-outline btn-sm" onclick="exportPDF()" title="Export PDF">
                        <i data-feather="download"></i> PDF
                    </button>
                    <button class="btn btn-outline btn-sm" onclick="exportWord()" title="Export Word">
                        <i data-feather="file-text"></i> Word (.doc)
                    </button>
                    <button id="handoverBtn" class="btn btn-success btn-sm" onclick="openHandoverModal()" style="display:none;">
                        <i data-feather="send"></i> Handover to Dev Team
                    </button>
                </div>
            </div>
        </div>

        <!-- Direct Edit Notice Bar (Hidden by default) -->
        <div id="directEditBanner" style="display:none; background:#fef3c7; border:1px solid #fde047; padding:10px 14px; border-radius:6px; margin-bottom:12px; font-size:13px; color:#854d0e; justify-content:space-between; align-items:center;">
            <span><strong>Direct Edit Mode Enabled:</strong> You can edit text directly inside the document preview below. Click "Save Live Edits" when done.</span>
            <button class="btn btn-primary btn-sm" onclick="saveDirectLiveEdits()">Save Live Edits</button>
        </div>

        <!-- Live Document Render Area -->
        <div id="documentRenderArea" class="document-preview-box" style="background:#f1f5f9; padding:20px; min-height:450px;">
            <div style="text-align:center; padding:100px 20px; color:var(--text-muted); background:white; border-radius:8px;">
                <i data-feather="file-text" style="width:48px; height:48px; stroke-width:1; margin-bottom:15px; color:#cbd5e1;"></i>
                <h3>Dynamic Premium Document Live Preview</h3>
                <p>Select a Client and Document Type on the left panel or load an existing saved document to render live preview.</p>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: ADD NEW DOCUMENT (DIRECT CREATION) -->
<!-- ========================================== -->
<div id="newDocumentModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:850px;">
        <div class="modal-header">
            <h3 class="modal-title">Add New Document</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('newDocumentModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitNewCustomDocument();">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Client *</label>
                        <select id="nd_client_id" class="form-select" required onchange="onNewDocClientChanged()">
                            <option value="">-- Select Client --</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?> (<?= htmlspecialchars($c['contact_person']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Service Package (Optional)</label>
                        <select id="nd_service_id" class="form-select">
                            <option value="">-- Choose Client First --</option>
                        </select>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Document Type *</label>
                        <select id="nd_doc_type" class="form-select" required>
                            <option value="Proposal">Proposal</option>
                            <option value="Invoice">Invoice</option>
                            <option value="Quotation">Quotation</option>
                            <option value="Contract">Contract</option>
                            <option value="Service Agreement">Service Agreement</option>
                            <option value="MOM">MOM</option>
                            <option value="Payment Reminder">Payment Reminder</option>
                            <option value="Renewal Letter">Renewal Letter</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Document Title / Subject *</label>
                        <input type="text" id="nd_title" class="form-control" placeholder="e.g. Corporate Website Proposal for ABC Company" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Load Content from Existing Template (Optional)</label>
                    <select id="nd_template_id" class="form-select" onchange="loadTemplateIntoNewDocModal()">
                        <option value="">-- Select Template to Pre-fill Content --</option>
                        <?php foreach ($templates as $t): ?>
                            <option value="<?= $t['id'] ?>">[<?= htmlspecialchars($t['document_type']) ?>] <?= htmlspecialchars($t['template_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Custom Field Insert Helpers -->
                <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; padding:10px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <strong style="color:#0369a1; font-size:12px;">Quick Insert Placeholders:</strong>
                        <button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:11px;" onclick="loadSampleTemplateHTML('nd_content', document.getElementById('nd_doc_type').value)">
                            Load Example HTML Template
                        </button>
                    </div>
                    <div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:6px;">
                        <button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:11px;" onclick="insertPlaceholderToArea('nd_content', '{{CLIENT_COMPANY}}')">{{CLIENT_COMPANY}}</button>
                        <button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:11px;" onclick="insertPlaceholderToArea('nd_content', '{{SERVICE_NAME}}')">{{SERVICE_NAME}}</button>
                        <button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:11px;" onclick="insertPlaceholderToArea('nd_content', '{{TOTAL_AMOUNT}}')">{{TOTAL_AMOUNT}}</button>
                        <button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:11px;" onclick="insertPlaceholderToArea('nd_content', '{{AGENCY_NAME}}')">{{AGENCY_NAME}}</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Document HTML / Text Content *</label>
                    <textarea id="nd_content" class="form-control" style="min-height:280px; font-family:monospace; font-size:13px;" placeholder="Write or paste your document content HTML here..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('newDocumentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create & Save Document</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: SAVED DOCUMENTS LIST & EDIT MANAGER -->
<!-- ========================================== -->
<div id="savedDocumentsModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:950px;">
        <div class="modal-header">
            <h3 class="modal-title">Saved Documents & Edit Directory</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('savedDocumentsModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                <input type="text" id="sd_search" class="form-control" style="max-width:300px;" placeholder="Search by title, client, doc number..." onkeyup="filterSavedDocsTable()">
                <button class="btn btn-primary btn-sm" onclick="closeModal('savedDocumentsModal'); openNewDocumentModal();">
                    <i data-feather="plus"></i> + Add New Document
                </button>
            </div>

            <div style="max-height:450px; overflow-y:auto;">
                <table class="table" id="savedDocsTable" style="width:100%; font-size:13px;">
                    <thead>
                        <tr>
                            <th>Doc #</th>
                            <th>Title</th>
                            <th>Client</th>
                            <th>Type</th>
                            <th>Version</th>
                            <th>Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="savedDocsTbody">
                        <tr><td colspan="7" style="text-align:center; padding:30px;">Loading documents...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('savedDocumentsModal')">Close</button>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: EDIT EXISTING SAVED DOCUMENT        -->
<!-- ========================================== -->
<div id="editDocumentModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:900px;">
        <div class="modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <h3 class="modal-title">Edit Saved Document</h3>
                <span id="ed_doc_number_badge" class="badge badge-primary"></span>
                <span id="ed_version_badge" class="badge badge-info"></span>
            </div>
            <button class="btn btn-outline btn-sm" onclick="closeModal('editDocumentModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitDocumentEdits();">
            <div class="modal-body">
                <input type="hidden" id="ed_document_id" value="0">

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Client *</label>
                        <select id="ed_client_id" class="form-select" required>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Document Type *</label>
                        <select id="ed_doc_type" class="form-select" required>
                            <option value="Proposal">Proposal</option>
                            <option value="Invoice">Invoice</option>
                            <option value="Quotation">Quotation</option>
                            <option value="Contract">Contract</option>
                            <option value="Service Agreement">Service Agreement</option>
                            <option value="MOM">MOM</option>
                            <option value="Payment Reminder">Payment Reminder</option>
                            <option value="Renewal Letter">Renewal Letter</option>
                        </select>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Document Title *</label>
                        <input type="text" id="ed_title" class="form-control" required placeholder="e.g. Master Service Contract for Kivoq">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Revision / Change Note *</label>
                        <input type="text" id="ed_change_summary" class="form-control" required placeholder="e.g. Updated Scope of Work & Pricing Clause">
                    </div>
                </div>

                <!-- Custom Field Insert Helpers -->
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:10px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <strong style="color:#1e40af; font-size:12px;">Insert Placeholders & Example HTML:</strong>
                        <button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:11px;" onclick="loadSampleTemplateHTML('ed_content', document.getElementById('ed_doc_type').value)">
                            Load Example HTML Template
                        </button>
                    </div>
                    <div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:6px;">
                        <button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:11px;" onclick="insertPlaceholderToArea('ed_content', '{{CLIENT_COMPANY}}')">{{CLIENT_COMPANY}}</button>
                        <button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:11px;" onclick="insertPlaceholderToArea('ed_content', '{{SERVICE_NAME}}')">{{SERVICE_NAME}}</button>
                        <button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:11px;" onclick="insertPlaceholderToArea('ed_content', '{{TOTAL_AMOUNT}}')">{{TOTAL_AMOUNT}}</button>
                        <button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:11px;" onclick="insertPlaceholderToArea('ed_content', '{{AGENCY_NAME}}')">{{AGENCY_NAME}}</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Document HTML Content *</label>
                    <textarea id="ed_content" class="form-control" style="min-height:350px; font-family:monospace; font-size:13px;" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editDocumentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Edits (New Version)</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: TEMPLATE EDITOR -->
<div id="templateEditorModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:850px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit / Create Template & Custom Placeholders</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('templateEditorModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); saveTemplateFromEditor();">
            <div class="modal-body">
                <input type="hidden" id="te_template_id" value="0">

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Select Template to Edit (or Create New)</label>
                        <select id="te_select" class="form-select" onchange="onEditorTemplateSelected()">
                            <option value="0">+ Create Brand New Template</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?= $t['id'] ?>">[<?= htmlspecialchars($t['document_type']) ?>] <?= htmlspecialchars($t['template_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Document Type *</label>
                        <select id="te_doc_type" class="form-select" required>
                            <option value="Proposal">Proposal</option>
                            <option value="Invoice">Invoice</option>
                            <option value="Quotation">Quotation</option>
                            <option value="Contract">Contract</option>
                            <option value="Service Agreement">Service Agreement</option>
                            <option value="MOM">MOM</option>
                            <option value="Payment Reminder">Payment Reminder</option>
                            <option value="Renewal Letter">Renewal Letter</option>
                        </select>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Template Name *</label>
                        <input type="text" id="te_name" class="form-control" placeholder="e.g. Hemito Agency Premium Proposal" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Subject / Title Template</label>
                        <input type="text" id="te_subject" class="form-control" placeholder="e.g. Proposal for {{CLIENT_COMPANY}}">
                    </div>
                </div>

                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:12px; margin-bottom:15px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <strong style="color:#1e40af; font-size:13px;">Dynamic Placeholders & Example HTML:</strong>
                        <button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:11px;" onclick="loadSampleTemplateHTML('te_content', document.getElementById('te_doc_type').value)">
                            Load Example HTML Template
                        </button>
                    </div>
                    <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertPlaceholderToArea('te_content', '{{CLIENT_COMPANY}}')">{{CLIENT_COMPANY}}</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertPlaceholderToArea('te_content', '{{CLIENT_CONTACT}}')">{{CLIENT_CONTACT}}</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertPlaceholderToArea('te_content', '{{SERVICE_NAME}}')">{{SERVICE_NAME}}</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertPlaceholderToArea('te_content', '{{PACKAGE_NAME}}')">{{PACKAGE_NAME}}</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertPlaceholderToArea('te_content', '{{SERVICE_PRICE}}')">{{SERVICE_PRICE}}</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertPlaceholderToArea('te_content', '{{TOTAL_AMOUNT}}')">{{TOTAL_AMOUNT}}</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertPlaceholderToArea('te_content', '{{AGENCY_NAME}}')">{{AGENCY_NAME}}</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Template HTML Content *</label>
                    <textarea id="te_content" class="form-control" style="min-height:300px; font-family:monospace; font-size:13px;" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('templateEditorModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Template</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: QUICK ADD CLIENT -->
<div id="quickClientModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Quick Add New Client</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('quickClientModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); saveQuickClient();">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Company Name *</label>
                        <input type="text" id="qc_company_name" class="form-control" required placeholder="e.g. Futureace Healthcare Academy">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Person *</label>
                        <input type="text" id="qc_contact_person" class="form-control" required placeholder="e.g. Vikram Mehta">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" id="qc_email" class="form-control" required placeholder="vikram@futureace.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" id="qc_phone" class="form-control" placeholder="+91 98765 00000">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Service Package Name</label>
                        <input type="text" id="qc_service_name" class="form-control" placeholder="e.g. Digital Marketing Suite">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Package Price (INR)</label>
                        <input type="number" id="qc_price" class="form-control" placeholder="70000">
                    </div>
                </div>

                <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; padding:10px; margin-top:6px;">
                    <strong style="color:#0369a1; font-size:12px; display:block; margin-bottom:6px;">Client Portal Login Credentials (Optional):</strong>
                    <div class="grid-2">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" style="font-size:11px;">Portal Username</label>
                            <input type="text" id="qc_username" class="form-control" style="padding:6px 10px; font-size:12px;" placeholder="Auto-generated if empty">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" style="font-size:11px;">Portal Password</label>
                            <input type="text" id="qc_password" class="form-control" style="padding:6px 10px; font-size:12px;" placeholder="Auto-generated if empty">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('quickClientModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Client & Select</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: MANAGE DOCUMENT TYPES -->
<div id="documentTypesModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:800px; width:95%;">
        <div class="modal-header">
            <h3 class="modal-title">🏷️ Document Types Directory & Management</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('documentTypesModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                <p style="margin:0; font-size:13px; color:var(--text-muted);">
                    Manage active document categories (e.g. Proposal, Invoice, Contract, Service Agreement, MOM, Quotation, NDA).
                </p>
                <button type="button" class="btn btn-primary btn-sm" onclick="showAddDocumentTypeForm()">
                    <i data-feather="plus"></i> + Add Document Type
                </button>
            </div>

            <!-- Form Card for Add / Edit -->
            <div id="dt_form_card" style="display:none; background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:18px; margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h4 id="dt_form_title" style="margin:0; font-size:15px; color:#0f172a; font-weight:700;">+ Add New Document Type</h4>
                    <button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px;" onclick="hideDocumentTypeForm()">&times;</button>
                </div>
                <form onsubmit="event.preventDefault(); submitSaveDocumentType();">
                    <input type="hidden" id="dt_type_id" value="0">
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Document Type Name *</label>
                            <input type="text" id="dt_name" class="form-control" required placeholder="e.g. Non-Disclosure Agreement (NDA)">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description (Optional)</label>
                            <input type="text" id="dt_description" class="form-control" placeholder="e.g. Standard NDA and confidentiality agreement">
                        </div>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:10px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="hideDocumentTypeForm()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Document Type</button>
                    </div>
                </form>
            </div>

            <!-- Table of Document Types -->
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type Name</th>
                            <th>Description</th>
                            <th>Linked Templates</th>
                            <th>Saved Documents</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="dt_table_body">
                        <tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:20px;">Loading document types...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: HANDOVER TO DEV TEAM -->
<div id="handoverModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Work Requirement Handover to Development</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('handoverModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitHandover();">
            <div class="modal-body">
                <div id="ho_client_info_banner" style="background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; padding:10px 14px; border-radius:6px; font-size:13px; font-weight:600; margin-bottom:14px;">
                    Loading target client information...
                </div>
                <div class="form-group">
                    <label class="form-label">Project / Work Title *</label>
                    <input type="text" id="ho_project_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Development Team Lead *</label>
                    <select id="ho_team_lead" class="form-select" required>
                        <option value="3">Ajmal (Team Lead - Development)</option>
                    </select>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select id="ho_priority" class="form-select">
                            <option value="High" selected>High</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deadline *</label>
                        <input type="date" id="ho_deadline" class="form-control" required value="<?= date('Y-m-d', strtotime('+14 days')) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Work Requirement Details</label>
                    <textarea id="ho_description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('handoverModal')">Cancel</button>
                <button type="submit" class="btn btn-success">Approve & Handover Work</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: VERSION HISTORY -->
<div id="versionModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:800px;">
        <div class="modal-header">
            <h3 class="modal-title">Document Version History</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('versionModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Filter Saved Document</label>
                <select id="versionDocSelect" class="form-select" onchange="loadVersionHistory()">
                    <option value="">-- Choose Generated Document --</option>
                </select>
            </div>
            <div id="versionListArea" style="margin-top:20px;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('versionModal')">Close</button>
        </div>
    </div>
</div>

<script>
    const rawTemplates = <?= json_encode($templatesByType) ?>;
    let currentSavedDocId = null;
    let isDirectEditMode = false;
    let savedDocumentsCache = [];

    const sampleTemplatesMap = {
        Proposal: "<div class=\"proposal-body\">\n<style>\n    @page {\n        size: A4;\n        margin: 0;\n    }\n\n    .proposal-body * {\n        box-sizing: border-box;\n    }\n\n    .proposal-body {\n        margin: 0;\n        padding: 0;\n        background: #eeeeee;\n        font-family: 'Google Sans', 'Google Sans Text', system-ui, sans-serif;\n        color: #111111;\n    }\n\n    \/* ==============================\n       A4 PAGE\n    ============================== *\/\n\n    .proposal-body .page {\n        width: 210mm;\n        min-height: 297mm;\n        background: #ffffff;\n        margin: 20px auto;\n        position: relative;\n        padding: 25mm 20mm 28mm 20mm;\n        page-break-after: always;\n        overflow: hidden;\n    }\n\n    \/* ==============================\n       HEADER\n    ============================== *\/\n\n    .proposal-body .header {\n        width: 100%;\n        height: 48px;\n        display: flex;\n        justify-content: flex-end;\n        align-items: flex-start;\n        margin-bottom: 20px;\n    }\n\n    .proposal-body .header img {\n        width: 145px;\n        height: auto;\n        object-fit: contain;\n    }\n\n    \/* ==============================\n       FOOTER\n    ============================== *\/\n\n    .proposal-body .footer {\n        position: absolute;\n        left: 0;\n        bottom: 0;\n        width: 100%;\n        height: 16mm;\n        background: #1f7fbd;\n        color: #ffffff;\n\n        display: flex;\n        align-items: center;\n        justify-content: center;\n\n        font-size: 12px;\n        text-align: center;\n    }\n\n    .proposal-body .footer a {\n        color: #ffffff;\n        text-decoration: none;\n    }\n\n    \/* ==============================\n       COVER PAGE\n    ============================== *\/\n\n    .proposal-body .cover {\n        padding-top: 18mm;\n        display: flex;\n        flex-direction: column;\n    }\n\n    .proposal-body .cover-logo {\n        text-align: center;\n        margin-top: 5mm;\n    }\n\n    .proposal-body .cover-logo img {\n        width: 190px;\n    }\n\n    .proposal-body .cover-title {\n        margin-top: 18mm;\n        text-align: center;\n    }\n\n    .proposal-body .cover-title .small-title {\n        font-size: 30px;\n        font-weight: 400;\n        line-height: 1.2;\n    }\n\n    .proposal-body .cover-title .main-title {\n        font-size: 29px;\n        font-weight: 700;\n        color: #1f7fbd;\n        text-transform: uppercase;\n        line-height: 1.25;\n    }\n\n    .proposal-body .cover-title .black-title {\n        font-size: 29px;\n        font-weight: 400;\n    }\n\n    .proposal-body .cover-description {\n        text-align: center;\n        font-size: 17px;\n        margin-top: 10px;\n        color: #444;\n    }\n\n    .proposal-body .cover-illustration {\n        width: 330px;\n        height: 220px;\n        margin: 20mm auto 10mm;\n        display: flex;\n        align-items: center;\n        justify-content: center;\n    }\n\n    .proposal-body .cover-illustration img {\n        max-width: 100%;\n        max-height: 100%;\n        object-fit: contain;\n    }\n\n    .proposal-body .submission-area {\n        display: flex;\n        justify-content: space-between;\n        margin-top: auto;\n        margin-bottom: 25mm;\n        gap: 30px;\n    }\n\n    .proposal-body .submission-box {\n        width: 48%;\n        font-size: 15px;\n        line-height: 1.55;\n    }\n\n    .proposal-body .submission-box strong {\n        font-weight: 700;\n    }\n\n    .proposal-body .submission-title {\n        font-size: 15px;\n        margin-bottom: 3px;\n    }\n\n    .proposal-body .blue-line {\n        width: 3px;\n        background: #1f7fbd;\n        min-height: 100px;\n    }\n\n    \/* ==============================\n       GENERAL CONTENT\n    ============================== *\/\n\n    .proposal-body .section-title {\n        font-size: 28px;\n        font-weight: 700;\n        margin: 5px 0 22px;\n        color: #111111;\n    }\n\n    .proposal-body .section-title span {\n        color: #1f7fbd;\n    }\n\n    .proposal-body .sub-title {\n        font-size: 20px;\n        font-weight: 700;\n        margin: 22px 0 10px;\n    }\n\n    .proposal-body .content {\n        font-size: 15px;\n        line-height: 1.6;\n        text-align: justify;\n    }\n\n    .proposal-body .content p {\n        margin: 0 0 14px;\n    }\n\n    .proposal-body .content ul {\n        margin: 8px 0 15px 22px;\n        padding: 0;\n    }\n\n    .proposal-body .content li {\n        margin-bottom: 7px;\n    }\n\n    \/* ==============================\n       BLUE HIGHLIGHT BOX\n    ============================== *\/\n\n    .proposal-body .blue-box {\n        background: #1f7fbd;\n        color: #ffffff;\n        padding: 18px 22px;\n        border-radius: 2px;\n        margin: 20px 0;\n    }\n\n    .proposal-body .blue-box h3 {\n        margin: 0 0 8px;\n        font-size: 19px;\n    }\n\n    .proposal-body .blue-box p {\n        margin: 0;\n        font-size: 14px;\n        line-height: 1.5;\n    }\n\n    \/* ==============================\n       MODULE LABEL\n    ============================== *\/\n\n    .proposal-body .module-label {\n        display: inline-block;\n        background: #1f7fbd;\n        color: #ffffff;\n        padding: 7px 18px;\n        font-size: 13px;\n        font-weight: 700;\n        margin-bottom: 10px;\n    }\n\n    \/* ==============================\n       WORKFLOW\n    ============================== *\/\n\n    .proposal-body .workflow {\n        display: flex;\n        align-items: flex-start;\n        justify-content: space-between;\n        margin-top: 25px;\n        gap: 5px;\n    }\n\n    .proposal-body .workflow-item {\n        flex: 1;\n        text-align: center;\n        position: relative;\n    }\n\n    .proposal-body .workflow-circle {\n        width: 48px;\n        height: 48px;\n        border: 4px solid #1f7fbd;\n        border-radius: 50%;\n        margin: 0 auto 10px;\n\n        display: flex;\n        align-items: center;\n        justify-content: center;\n\n        color: #1f7fbd;\n        font-weight: 700;\n        font-size: 15px;\n    }\n\n    .proposal-body .workflow-item h4 {\n        margin: 0;\n        font-size: 12px;\n        line-height: 1.3;\n    }\n\n    .proposal-body .workflow-arrow {\n        color: #1f7fbd;\n        font-size: 25px;\n        margin-top: 10px;\n    }\n\n    \/* ==============================\n       MODULE CARDS\n    ============================== *\/\n\n    .proposal-body .module-grid {\n        display: grid;\n        grid-template-columns: 1fr 1fr;\n        gap: 14px;\n        margin-top: 20px;\n    }\n\n    .proposal-body .module-card {\n        border: 1px solid #cccccc;\n        padding: 16px;\n        min-height: 125px;\n    }\n\n    .proposal-body .module-card h3 {\n        color: #1f7fbd;\n        font-size: 17px;\n        margin: 0 0 7px;\n    }\n\n    .proposal-body .module-card p {\n        font-size: 13px;\n        line-height: 1.5;\n        margin: 0;\n    }\n\n    \/* ==============================\n       TASK FLOW\n    ============================== *\/\n\n    .proposal-body .task-flow {\n        margin-top: 20px;\n    }\n\n    .proposal-body .task-step {\n        display: flex;\n        align-items: center;\n        margin-bottom: 8px;\n    }\n\n    .proposal-body .task-number {\n        width: 32px;\n        height: 32px;\n        background: #1f7fbd;\n        color: #ffffff;\n        border-radius: 50%;\n\n        display: flex;\n        align-items: center;\n        justify-content: center;\n\n        font-size: 12px;\n        font-weight: 700;\n        flex-shrink: 0;\n    }\n\n    .proposal-body .task-name {\n        margin-left: 12px;\n        font-size: 14px;\n        font-weight: 600;\n    }\n\n    .proposal-body .task-arrow {\n        color: #1f7fbd;\n        margin-left: 14px;\n        font-weight: bold;\n    }\n\n    \/* ==============================\n       STATUS BOXES\n    ============================== *\/\n\n    .proposal-body .status-grid {\n        display: grid;\n        grid-template-columns: repeat(3, 1fr);\n        gap: 10px;\n        margin-top: 15px;\n    }\n\n    .proposal-body .status {\n        border: 1px solid #d4d4d4;\n        padding: 12px;\n        text-align: center;\n        font-size: 13px;\n        font-weight: 600;\n    }\n\n    .proposal-body .status.active {\n        border-color: #1f7fbd;\n        color: #1f7fbd;\n    }\n\n    .proposal-body .status.completed {\n        background: #1f7fbd;\n        color: #ffffff;\n        border-color: #1f7fbd;\n    }\n\n    \/* ==============================\n       CLIENT DASHBOARD\n    ============================== *\/\n\n    .proposal-body .dashboard-box {\n        border: 2px solid #1f7fbd;\n        padding: 18px;\n        margin-top: 20px;\n    }\n\n    .proposal-body .dashboard-header {\n        background: #1f7fbd;\n        color: #ffffff;\n        padding: 14px;\n        font-size: 18px;\n        font-weight: 700;\n    }\n\n    .proposal-body .dashboard-grid {\n        display: grid;\n        grid-template-columns: repeat(2, 1fr);\n        gap: 12px;\n        margin-top: 15px;\n    }\n\n    .proposal-body .dashboard-card {\n        border: 1px solid #d0d0d0;\n        padding: 15px;\n    }\n\n    .proposal-body .dashboard-card strong {\n        display: block;\n        color: #1f7fbd;\n        font-size: 15px;\n        margin-bottom: 5px;\n    }\n\n    .proposal-body .dashboard-card span {\n        font-size: 13px;\n    }\n\n    \/* ==============================\n       APPROVAL FLOW\n    ============================== *\/\n\n    .proposal-body .approval-flow {\n        display: flex;\n        align-items: center;\n        gap: 8px;\n        margin-top: 20px;\n    }\n\n    .proposal-body .approval-step {\n        flex: 1;\n        text-align: center;\n        border: 1px solid #cccccc;\n        padding: 14px 8px;\n        min-height: 85px;\n    }\n\n    .proposal-body .approval-step strong {\n        display: block;\n        color: #1f7fbd;\n        font-size: 13px;\n        margin-bottom: 5px;\n    }\n\n    .proposal-body .approval-step span {\n        font-size: 11px;\n        line-height: 1.3;\n    }\n\n    .proposal-body .approval-arrow {\n        color: #1f7fbd;\n        font-size: 20px;\n    }\n\n    \/* ==============================\n       FEATURE LIST\n    ============================== *\/\n\n    .proposal-body .feature-list {\n        columns: 2;\n        column-gap: 35px;\n        margin-top: 15px;\n    }\n\n    .proposal-body .feature-list li {\n        break-inside: avoid;\n        font-size: 14px;\n        margin-bottom: 8px;\n    }\n\n    \/* ==============================\n       TABLE\n    ============================== *\/\n\n    .proposal-body table {\n        width: 100%;\n        border-collapse: collapse;\n        margin-top: 20px;\n        font-size: 13px;\n    }\n\n    .proposal-body th {\n        background: #1f7fbd;\n        color: #ffffff;\n        padding: 12px;\n        text-align: left;\n        border: 1px solid #111111;\n    }\n\n    .proposal-body td {\n        padding: 11px;\n        border: 1px solid #333333;\n        vertical-align: top;\n    }\n\n    \/* ==============================\n       TECHNOLOGY\n    ============================== *\/\n\n    .proposal-body .tech-grid {\n        display: grid;\n        grid-template-columns: repeat(4, 1fr);\n        gap: 10px;\n        margin-top: 20px;\n    }\n\n    .proposal-body .tech-item {\n        border: 1px solid #1f7fbd;\n        color: #1f7fbd;\n        padding: 12px;\n        text-align: center;\n        font-size: 13px;\n        font-weight: 700;\n    }\n\n    \/* ==============================\n       PRINT\n    ============================== *\/\n\n    @media print {\n        body {\n            background: #ffffff;\n        }\n        .proposal-body .page {\n            margin: 0;\n            box-shadow: none;\n            page-break-after: always;\n        }\n    }\n\n    @media screen {\n        .proposal-body .page {\n            box-shadow: 0 3px 20px rgba(0,0,0,0.12);\n        }\n    }\n<\/style>\n\n<!-- PAGE 1 \u2014 COVER -->\n<div class=\"page cover\">\n    <div class=\"cover-logo\">\n        <img src=\"asset\/hemitologo-dark-blue.webp\" alt=\"Hemito Digital\">\n    <\/div>\n    <div class=\"cover-title\">\n        <div class=\"small-title\">Comprehensive<\/div>\n        <div class=\"main-title\">Agency Management<\/div>\n        <div class=\"black-title\">&amp; Client Workflow Platform<\/div>\n    <\/div>\n    <div class=\"cover-description\">\n        Digital Workflow &amp; Project Management Solution\n    <\/div>\n    <div class=\"cover-illustration\">\n        <img src=\"asset\/hemitologo-dark-blue.webp\" alt=\"Agency Management System\">\n    <\/div>\n    <div class=\"submission-area\">\n        <div class=\"submission-box\">\n            <div class=\"submission-title\">Prepared for:<\/div>\n            <strong>{{CLIENT_COMPANY}}<\/strong><br>\n            2nd Floor, ACEL Tower,<br>\n            Ambady Lane, Near Little Flower Church,<br>\n            Elamkulam, Kadavanthra, Kochi - 682020\n        <\/div>\n        <div class=\"blue-line\"><\/div>\n        <div class=\"submission-box\">\n            <div class=\"submission-title\">Solution:<\/div>\n            <strong>Agency Management &amp; Client Workflow Platform<\/strong><br>\n            Sales Management, Project Management, Task Management, Client Dashboard &amp; Reporting\n        <\/div>\n    <\/div>\n    <div class=\"footer\">\n        <a href=\"https:\/\/www.hemitodigital.com\">www.hemitodigital.com<\/a> &nbsp; | &nbsp;\n        <a href=\"mailto:sales@hemitodigital.com\">sales@hemitodigital.com<\/a> &nbsp; | &nbsp;\n        +91-8921992187\n    <\/div>\n<\/div>\n\n<!-- PAGE 2 \u2014 OVERVIEW -->\n<div class=\"page\">\n    <div class=\"header\">\n        <img src=\"asset\/hemitologo-dark-blue.webp\" alt=\"Hemito Digital\">\n    <\/div>\n    <h1 class=\"section-title\">About the <span>Platform<\/span><\/h1>\n    <div class=\"content\">\n        <p>The proposed Agency Management &amp; Client Workflow Platform is designed to centralize and streamline Hemito Digital's internal business operations and client communication.<\/p>\n        <p>The platform will connect the sales, project management, development and client approval workflows in a single system. This will provide better visibility into ongoing work, improve task coordination and simplify communication between Hemito Digital and its clients.<\/p>\n        <p>The solution will also provide management with centralized reporting and performance visibility across projects, tasks, clients and teams.<\/p>\n    <\/div>\n    <div class=\"blue-box\">\n        <h3>Objective<\/h3>\n        <p>To provide a structured digital workflow that allows Hemito Digital to manage the complete journey from client acquisition and approval through project execution, development, client review and final completion.<\/p>\n    <\/div>\n    <h2 class=\"sub-title\">Core Workflow<\/h2>\n    <div class=\"workflow\">\n        <div class=\"workflow-item\"><div class=\"workflow-circle\">01<\/div><h4>Sales<\/h4><\/div>\n        <div class=\"workflow-arrow\">\u2192<\/div>\n        <div class=\"workflow-item\"><div class=\"workflow-circle\">02<\/div><h4>Client Approval<\/h4><\/div>\n        <div class=\"workflow-arrow\">\u2192<\/div>\n        <div class=\"workflow-item\"><div class=\"workflow-circle\">03<\/div><h4>Project<\/h4><\/div>\n        <div class=\"workflow-arrow\">\u2192<\/div>\n        <div class=\"workflow-item\"><div class=\"workflow-circle\">04<\/div><h4>Team Lead<\/h4><\/div>\n        <div class=\"workflow-arrow\">\u2192<\/div>\n        <div class=\"workflow-item\"><div class=\"workflow-circle\">05<\/div><h4>Task Assignment<\/h4><\/div>\n        <div class=\"workflow-arrow\">\u2192<\/div>\n        <div class=\"workflow-item\"><div class=\"workflow-circle\">06<\/div><h4>Development<\/h4><\/div>\n    <\/div>\n    <div class=\"workflow\" style=\"margin-top:30px;\">\n        <div class=\"workflow-item\"><div class=\"workflow-circle\">07<\/div><h4>Review<\/h4><\/div>\n        <div class=\"workflow-arrow\">\u2192<\/div>\n        <div class=\"workflow-item\"><div class=\"workflow-circle\">08<\/div><h4>Client Approval<\/h4><\/div>\n        <div class=\"workflow-arrow\">\u2192<\/div>\n        <div class=\"workflow-item\"><div class=\"workflow-circle\">09<\/div><h4>Completion<\/h4><\/div>\n        <div class=\"workflow-arrow\">\u2192<\/div>\n        <div class=\"workflow-item\"><div class=\"workflow-circle\">10<\/div><h4>Reports<\/h4><\/div>\n    <\/div>\n    <div class=\"footer\">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187<\/div>\n<\/div>\n\n<!-- PAGE 3 \u2014 MODULE 3 -->\n<div class=\"page\">\n    <div class=\"header\">\n        <img src=\"asset\/hemitologo-dark-blue.webp\" alt=\"Hemito Digital\">\n    <\/div>\n    <div class=\"module-label\">MODULE 03<\/div>\n    <h1 class=\"section-title\">Template <span>Generator<\/span><\/h1>\n    <div class=\"content\">\n        <p>The Template Generator module provides a structured method for creating standardized business documents using predefined templates and existing client and project information.<\/p>\n        <p>The module reduces repetitive manual document preparation and ensures consistency across client-facing documents.<\/p>\n    <\/div>\n    <h2 class=\"sub-title\">Key Features<\/h2>\n    <ul class=\"feature-list\">\n        <li>Proposal generation<\/li>\n        <li>Quotation generation<\/li>\n        <li>Contract generation<\/li>\n        <li>Service agreements<\/li>\n        <li>Invoice documents<\/li>\n        <li>Meeting \/ MOM documents<\/li>\n        <li>Payment reminders<\/li>\n        <li>Renewal documents<\/li>\n        <li>Client information auto-population<\/li>\n        <li>Project information auto-population<\/li>\n        <li>Document preview<\/li>\n        <li>PDF generation<\/li>\n        <li>Document version management<\/li>\n        <li>Document history<\/li>\n    <\/ul>\n    <h2 class=\"sub-title\">Document Workflow<\/h2>\n    <div class=\"task-flow\">\n        <div class=\"task-step\"><div class=\"task-number\">01<\/div><div class=\"task-name\">Select Client<\/div><div class=\"task-arrow\">\u2192<\/div><\/div>\n        <div class=\"task-step\"><div class=\"task-number\">02<\/div><div class=\"task-name\">Select Project<\/div><div class=\"task-arrow\">\u2192<\/div><\/div>\n        <div class=\"task-step\"><div class=\"task-number\">03<\/div><div class=\"task-name\">Select Template<\/div><div class=\"task-arrow\">\u2192<\/div><\/div>\n        <div class=\"task-step\"><div class=\"task-number\">04<\/div><div class=\"task-name\">Auto Populate Information<\/div><div class=\"task-arrow\">\u2192<\/div><\/div>\n        <div class=\"task-step\"><div class=\"task-number\">05<\/div><div class=\"task-name\">Preview Document<\/div><div class=\"task-arrow\">\u2192<\/div><\/div>\n        <div class=\"task-step\"><div class=\"task-number\">06<\/div><div class=\"task-name\">Save \/ Generate PDF<\/div><\/div>\n    <\/div>\n    <div class=\"blue-box\">\n        <h3>Template-Based Documentation<\/h3>\n        <p>Documents can use predefined placeholders such as client name, project name, service details, amount, dates and other project information. This enables consistent and efficient document generation.<\/p>\n    <\/div>\n    <div class=\"footer\">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187<\/div>\n<\/div>\n\n<!-- PAGE 4 \u2014 MODULE 4 -->\n<div class=\"page\">\n    <div class=\"header\">\n        <img src=\"asset\/hemitologo-dark-blue.webp\" alt=\"Hemito Digital\">\n    <\/div>\n    <div class=\"module-label\">MODULE 04<\/div>\n    <h1 class=\"section-title\">Project &amp; <span>Task Management<\/span><\/h1>\n    <div class=\"content\">\n        <p>The Project &amp; Task Management module manages the execution stage after a client requirement has been approved. The system allows the Development Team Lead to convert project requirements into actionable tasks and assign them to the appropriate team members.<\/p>\n    <\/div>\n    <h2 class=\"sub-title\">Project Structure<\/h2>\n    <div class=\"module-grid\">\n        <div class=\"module-card\"><h3>Client<\/h3><p>Central client record containing the client's projects, requirements and related information.<\/p><\/div>\n        <div class=\"module-card\"><h3>Project<\/h3><p>Represents a specific service or engagement being delivered to the client.<\/p><\/div>\n        <div class=\"module-card\"><h3>Task<\/h3><p>Individual pieces of work created from the project requirement.<\/p><\/div>\n        <div class=\"module-card\"><h3>Subtask<\/h3><p>Smaller activities required to complete a larger task.<\/p><\/div>\n    <\/div>\n    <h2 class=\"sub-title\">Development Workflow<\/h2>\n    <div class=\"workflow\">\n        <div class=\"workflow-item\"><div class=\"workflow-circle\">01<\/div><h4>Requirement<\/h4><\/div><div class=\"workflow-arrow\">\u2192<\/div>\n        <div class=\"workflow-item\"><div class=\"workflow-circle\">02<\/div><h4>Lead Creates Task<\/h4><\/div><div class=\"workflow-arrow\">\u2192<\/div>\n        <div class=\"workflow-item\"><div class=\"workflow-circle\">03<\/div><h4>Assignment<\/h4><\/div><div class=\"workflow-arrow\">\u2192<\/div>\n        <div class=\"workflow-item\"><div class=\"workflow-circle\">04<\/div><h4>Development<\/h4><\/div><div class=\"workflow-arrow\">\u2192<\/div>\n        <div class=\"workflow-item\"><div class=\"workflow-circle\">05<\/div><h4>Review<\/h4><\/div>\n    <\/div>\n    <h2 class=\"sub-title\">Task Status<\/h2>\n    <div class=\"status-grid\">\n        <div class=\"status\">Pending<\/div>\n        <div class=\"status active\">In Progress<\/div>\n        <div class=\"status active\">Under Review<\/div>\n        <div class=\"status active\">Client Approval<\/div>\n        <div class=\"status\">Revision Required<\/div>\n        <div class=\"status completed\">Completed<\/div>\n    <\/div>\n    <div class=\"footer\">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187<\/div>\n<\/div>\n\n<!-- PAGE 5 \u2014 CLIENT DASHBOARD -->\n<div class=\"page\">\n    <div class=\"header\">\n        <img src=\"asset\/hemitologo-dark-blue.webp\" alt=\"Hemito Digital\">\n    <\/div>\n    <div class=\"module-label\">CLIENT MODULE<\/div>\n    <h1 class=\"section-title\">Client <span>Dashboard<\/span><\/h1>\n    <div class=\"content\">\n        <p>A dedicated Client Dashboard will provide clients with a secure and transparent view of their projects and work progress.<\/p>\n        <p>Clients will only be able to access their own dashboard, projects, tasks, documents, meetings and approval requests. Internal agency information will remain restricted.<\/p>\n    <\/div>\n    <div class=\"dashboard-box\">\n        <div class=\"dashboard-header\">Client Dashboard<\/div>\n        <div class=\"dashboard-grid\">\n            <div class=\"dashboard-card\"><strong>Active Projects<\/strong><span>View current projects and overall progress.<\/span><\/div>\n            <div class=\"dashboard-card\"><strong>Task Status<\/strong><span>See where each task currently stands.<\/span><\/div>\n            <div class=\"dashboard-card\"><strong>Project Timeline<\/strong><span>Track the progress of project activities.<\/span><\/div>\n            <div class=\"dashboard-card\"><strong>Pending Approvals<\/strong><span>Review work waiting for client approval.<\/span><\/div>\n            <div class=\"dashboard-card\"><strong>Meetings<\/strong><span>View scheduled meetings and Google Meet links.<\/span><\/div>\n            <div class=\"dashboard-card\"><strong>Documents<\/strong><span>Access client-specific project documents.<\/span><\/div>\n        <\/div>\n    <\/div>\n    <h2 class=\"sub-title\">Client Approval Workflow<\/h2>\n    <div class=\"approval-flow\">\n        <div class=\"approval-step\"><strong>Development<\/strong><span>Work is completed by the development team.<\/span><\/div><div class=\"approval-arrow\">\u2192<\/div>\n        <div class=\"approval-step\"><strong>Team Review<\/strong><span>Team Lead reviews the completed work.<\/span><\/div><div class=\"approval-arrow\">\u2192<\/div>\n        <div class=\"approval-step\"><strong>Client Review<\/strong><span>Client reviews the submitted work.<\/span><\/div><div class=\"approval-arrow\">\u2192<\/div>\n        <div class=\"approval-step\"><strong>Approval \/ Revision<\/strong><span>Client approves or requests changes.<\/span><\/div>\n    <\/div>\n    <div class=\"blue-box\">\n        <h3>Client-Only Access<\/h3>\n        <p>The Client Dashboard will be completely isolated from internal agency modules. Clients will not have access to Sales, Development Management, Employee information, Reports or other client accounts.<\/p>\n    <\/div>\n    <div class=\"footer\">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187<\/div>\n<\/div>\n\n<!-- PAGE 6 \u2014 GOOGLE MEET + SMS -->\n<div class=\"page\">\n    <div class=\"header\">\n        <img src=\"asset\/hemitologo-dark-blue.webp\" alt=\"Hemito Digital\">\n    <\/div>\n    <h1 class=\"section-title\">Client <span>Communication<\/span><\/h1>\n    <h2 class=\"sub-title\">Google Meet Integration<\/h2>\n    <div class=\"content\">\n        <p>Project-related meetings can be scheduled by authorized Hemito Digital users and displayed directly within the client's dashboard.<\/p>\n    <\/div>\n    <div class=\"task-flow\">\n        <div class=\"task-step\"><div class=\"task-number\">01<\/div><div class=\"task-name\">Meeting Created<\/div><div class=\"task-arrow\">\u2192<\/div><\/div>\n        <div class=\"task-step\"><div class=\"task-number\">02<\/div><div class=\"task-name\">Google Meet Link Added<\/div><div class=\"task-arrow\">\u2192<\/div><\/div>\n        <div class=\"task-step\"><div class=\"task-number\">03<\/div><div class=\"task-name\">Client Notification<\/div><div class=\"task-arrow\">\u2192<\/div><\/div>\n        <div class=\"task-step\"><div class=\"task-number\">04<\/div><div class=\"task-name\">SMS Notification<\/div><div class=\"task-arrow\">\u2192<\/div><\/div>\n        <div class=\"task-step\"><div class=\"task-number\">05<\/div><div class=\"task-name\">Client Joins Meeting<\/div><\/div>\n    <\/div>\n    <h2 class=\"sub-title\">SMS Notifications<\/h2>\n    <div class=\"content\">\n        <p>Important project events can trigger SMS notifications to the client's registered mobile number.<\/p>\n    <\/div>\n    <ul>\n        <li>Client approval required<\/li>\n        <li>Client approval received<\/li>\n        <li>Revision requested<\/li>\n        <li>Meeting scheduled<\/li>\n        <li>Meeting updated<\/li>\n        <li>Project completion<\/li>\n        <li>Important project updates<\/li>\n    <\/ul>\n    <div class=\"blue-box\">\n        <h3>Example Notification<\/h3>\n        <p>Your project task is ready for approval. Please log in to your Client Dashboard to review the work and provide your approval.<\/p>\n    <\/div>\n    <h2 class=\"sub-title\">Secure Client Access<\/h2>\n    <div class=\"content\">\n        <p>SMS notifications will direct the client to the secure Client Dashboard. Authentication and server-side authorization will ensure that the client can only access information associated with their own account.<\/p>\n    <\/div>\n    <div class=\"footer\">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187<\/div>\n<\/div>\n\n<!-- PAGE 7 \u2014 MODULE 9 -->\n<div class=\"page\">\n    <div class=\"header\">\n        <img src=\"asset\/hemitologo-dark-blue.webp\" alt=\"Hemito Digital\">\n    <\/div>\n    <div class=\"module-label\">MODULE 09<\/div>\n    <h1 class=\"section-title\">Reports &amp; <span>Management Dashboard<\/span><\/h1>\n    <div class=\"content\">\n        <p>The Reports &amp; Management Dashboard provides centralized visibility into the performance of clients, projects, tasks, teams and overall agency operations.<\/p>\n        <p>Dashboard information will be generated from actual system data to provide management with real-time operational visibility.<\/p>\n    <\/div>\n    <div class=\"module-grid\">\n        <div class=\"module-card\"><h3>Client Overview<\/h3><p>Active, paused and completed client accounts.<\/p><\/div>\n        <div class=\"module-card\"><h3>Project Overview<\/h3><p>Active, completed, delayed and on-hold projects.<\/p><\/div>\n        <div class=\"module-card\"><h3>Task Overview<\/h3><p>Pending, active, review, approval and completed tasks.<\/p><\/div>\n        <div class=\"module-card\"><h3>Team Performance<\/h3><p>Workload, completed tasks and pending work.<\/p><\/div>\n        <div class=\"module-card\"><h3>SLA Monitoring<\/h3><p>On-time tasks, delayed tasks and SLA breaches.<\/p><\/div>\n        <div class=\"module-card\"><h3>Sales Overview<\/h3><p>Leads, conversions and sales performance.<\/p><\/div>\n    <\/div>\n    <h2 class=\"sub-title\">Management Drill-Down<\/h2>\n    <div class=\"task-flow\">\n        <div class=\"task-step\"><div class=\"task-number\">01<\/div><div class=\"task-name\">Management Dashboard<\/div><div class=\"task-arrow\">\u2192<\/div><\/div>\n        <div class=\"task-step\"><div class=\"task-number\">02<\/div><div class=\"task-name\">Department \/ Project<\/div><div class=\"task-arrow\">\u2192<\/div><\/div>\n        <div class=\"task-step\"><div class=\"task-number\">03<\/div><div class=\"task-name\">Team Lead<\/div><div class=\"task-arrow\">\u2192<\/div><\/div>\n        <div class=\"task-step\"><div class=\"task-number\">04<\/div><div class=\"task-name\">Developer<\/div><div class=\"task-arrow\">\u2192<\/div><\/div>\n        <div class=\"task-step\"><div class=\"task-number\">05<\/div><div class=\"task-name\">Individual Task<\/div><\/div>\n    <\/div>\n    <div class=\"footer\">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187<\/div>\n<\/div>\n\n<!-- PAGE 8 \u2014 ACCESS & TECHNOLOGY -->\n<div class=\"page\">\n    <div class=\"header\">\n        <img src=\"asset\/hemitologo-dark-blue.webp\" alt=\"Hemito Digital\">\n    <\/div>\n    <h1 class=\"section-title\">Access Control &amp; <span>Technology<\/span><\/h1>\n    <div class=\"content\">\n        <p>The platform will use role-based and module-level access control so that each user can access only the functionality relevant to their responsibilities.<\/p>\n    <\/div>\n    <table>\n        <thead>\n            <tr>\n                <th>Role<\/th>\n                <th>Primary Access<\/th>\n            <\/tr>\n        <\/thead>\n        <tbody>\n            <tr><td><strong>Super Admin<\/strong><\/td><td>Complete system access<\/td><\/tr>\n            <tr><td><strong>Agency Admin<\/strong><\/td><td>Agency-wide operational access<\/td><\/tr>\n            <tr><td><strong>Sales \/ Account Manager<\/strong><\/td><td>Sales, clients and project information<\/td><\/tr>\n            <tr><td><strong>Development Team Lead<\/strong><\/td><td>Projects, tasks, assignments and reviews<\/td><\/tr>\n            <tr><td><strong>Developer<\/strong><\/td><td>Assigned tasks and development activities<\/td><\/tr>\n            <tr><td><strong>Management<\/strong><\/td><td>Reports and management dashboards<\/td><\/tr>\n            <tr><td><strong>Client<\/strong><\/td><td>Client Dashboard only<\/td><\/tr>\n        <\/tbody>\n    <\/table>\n    <h2 class=\"sub-title\">Technology Stack<\/h2>\n    <div class=\"tech-grid\">\n        <div class=\"tech-item\">PHP<\/div>\n        <div class=\"tech-item\">MySQL<\/div>\n        <div class=\"tech-item\">HTML5<\/div>\n        <div class=\"tech-item\">CSS3<\/div>\n        <div class=\"tech-item\">JavaScript<\/div>\n        <div class=\"tech-item\">AJAX<\/div>\n        <div class=\"tech-item\">Bootstrap<\/div>\n        <div class=\"tech-item\">PDO<\/div>\n    <\/div>\n    <h2 class=\"sub-title\">Security<\/h2>\n    <ul>\n        <li>Role-based authentication<\/li>\n        <li>Module-level authorization<\/li>\n        <li>Client-specific data isolation<\/li>\n        <li>Secure session management<\/li>\n        <li>Prepared SQL statements<\/li>\n        <li>Secure file access<\/li>\n        <li>Activity and audit logging<\/li>\n        <li>Client approval history<\/li>\n    <\/ul>\n    <div class=\"blue-box\">\n        <h3>Outcome<\/h3>\n        <p>The platform will provide Hemito Digital with a centralized workflow for managing clients, projects, development tasks, approvals and management reporting while providing clients with transparent and secure visibility into their work.<\/p>\n    <\/div>\n    <div class=\"footer\">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187<\/div>\n<\/div>\n<\/div>",
Invoice: `<div style="font-family: 'Google Sans', 'Google Sans Text', 'Segoe UI', Helvetica, Arial, sans-serif; color: #1e293b; max-width: 800px; margin: 0 auto; background: #ffffff; padding: 40px; border: 1px solid #e2e8f0; border-radius: 8px;">
  <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 30px;">
    <div>
      <h1 style="color: #0f172a; margin: 0; font-size: 28px; font-weight: 800;">TAX INVOICE</h1>
      <p style="color: #2563eb; margin: 4px 0 0 0; font-size: 14px; font-weight: 700;">{{AGENCY_NAME}}</p>
      <p style="color: #64748b; margin: 4px 0 0 0; font-size: 12px;">{{AGENCY_ADDRESS}}<br>GSTIN: {{AGENCY_TAX_ID}}</p>
    </div>
    <div style="text-align: right; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
      <p style="margin: 0; font-size: 13px;"><strong>Invoice No:</strong> <span style="color:#2563eb; font-weight:700;">{{DOCUMENT_NUMBER}}</span></p>
      <p style="margin: 6px 0 0 0; font-size: 13px;"><strong>Invoice Date:</strong> {{CURRENT_DATE}}</p>
      <p style="margin: 6px 0 0 0; font-size: 13px; color: #dc2626;"><strong>Due Date:</strong> {{DUE_DATE}}</p>
    </div>
  </div>
  <div style="display: flex; justify-content: space-between; background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
    <div>
      <h4 style="margin: 0 0 6px 0; color: #64748b; font-size: 11px; text-transform: uppercase;">BILLED TO:</h4>
      <p style="margin: 0; font-size: 16px; font-weight: 700;">{{CLIENT_COMPANY}}</p>
      <p style="margin: 4px 0 0 0; font-size: 13px; color: #475569;">Attn: {{CLIENT_CONTACT}} ({{CLIENT_EMAIL}})</p>
    </div>
    <div style="text-align: right;">
      <h4 style="margin: 0 0 6px 0; color: #64748b; font-size: 11px; text-transform: uppercase;">GST / TAX ID:</h4>
      <p style="margin: 0; font-size: 14px; font-weight: 600;">{{CLIENT_TAX_ID}}</p>
    </div>
  </div>
  <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 14px;">
    <thead>
      <tr style="background: #2563eb; color: #ffffff; text-align: left;">
        <th style="padding: 12px 16px;">Description</th>
        <th style="padding: 12px 16px; text-align: center;">Qty</th>
        <th style="padding: 12px 16px; text-align: right;">Amount ({{CURRENCY}})</th>
      </tr>
    </thead>
    <tbody>
      <tr style="border-bottom: 1px solid #e2e8f0;">
        <td style="padding: 14px 16px;"><strong>{{SERVICE_NAME}}</strong> ({{PACKAGE_NAME}})</td>
        <td style="padding: 14px 16px; text-align: center;">1</td>
        <td style="padding: 14px 16px; text-align: right; font-weight: 600;">₹ {{SERVICE_PRICE}}</td>
      </tr>
    </tbody>
  </table>
  <div style="display: flex; justify-content: flex-end;">
    <div style="width: 320px;">
      <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0; font-size: 14px;">
        <span>GST Tax (18%):</span>
        <span style="font-weight: 600;">₹ {{TAX_AMOUNT}}</span>
      </div>
      <div style="display: flex; justify-content: space-between; padding: 12px 14px; background: #eff6ff; border-radius: 6px; font-weight: 700; color: #1e40af; font-size: 16px; margin-top: 8px;">
        <span>TOTAL DUE:</span>
        <span>₹ {{TOTAL_AMOUNT}}</span>
      </div>
    </div>
  </div>
</div>`,
        Quotation: `<div style="font-family: 'Google Sans', 'Google Sans Text', 'Segoe UI', Helvetica, Arial, sans-serif; color: #1e293b; max-width: 800px; margin: 0 auto; background: #ffffff; padding: 40px; border-radius: 8px; border: 1px solid #cbd5e1;">
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
    <p style="margin: 0; font-size: 13px; color: #475569;">Attn: {{CLIENT_CONTACT}} ({{CLIENT_EMAIL}})</p>
  </div>
  <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 14px;">
    <thead>
      <tr style="background: #f1f5f9; text-align: left;">
        <th style="padding: 10px 14px; border: 1px solid #cbd5e1;">Service Offered</th>
        <th style="padding: 10px 14px; border: 1px solid #cbd5e1; text-align: right;">Price ({{CURRENCY}})</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td style="padding: 12px 14px; border: 1px solid #cbd5e1;"><strong>{{SERVICE_NAME}}</strong> ({{PACKAGE_NAME}})</td>
        <td style="padding: 12px 14px; border: 1px solid #cbd5e1; text-align: right;">₹ {{SERVICE_PRICE}}</td>
      </tr>
      <tr style="background: #f8fafc; font-weight: 700;">
        <td style="padding: 12px 14px; border: 1px solid #cbd5e1; text-align: right;">Estimated Total (incl. tax):</td>
        <td style="padding: 12px 14px; border: 1px solid #cbd5e1; text-align: right; color: #2563eb;">₹ {{TOTAL_AMOUNT}}</td>
      </tr>
    </tbody>
  </table>
</div>`
    };

    function loadSampleTemplateHTML(targetId, type) {
        const textarea = document.getElementById(targetId);
        if (!textarea) return;
        const html = sampleTemplatesMap[type] || sampleTemplatesMap['Proposal'];
        textarea.value = html;
        textarea.focus();
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadSavedDocsQuickDropdown();
        const autoClientId = sessionStorage.getItem('autoSelectClientId');
        if (autoClientId) {
            sessionStorage.removeItem('autoSelectClientId');
            const select = document.getElementById('clientSelect');
            if (select) {
                select.value = autoClientId;
                onClientChanged();
            }
        }
    });

    function loadSavedDocsQuickDropdown() {
        fetch('api.php?action=get_documents')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    savedDocumentsCache = data.documents;
                    const select = document.getElementById('savedDocQuickSelect');
                    select.innerHTML = '<option value="0">Mode: Create New Document from Template</option>';
                    data.documents.forEach(d => {
                        const opt = document.createElement('option');
                        opt.value = d.id;
                        opt.textContent = `[${d.document_number}] ${d.title} (v${d.version})`;
                        select.appendChild(opt);
                    });
                }
            });
    }

    function onQuickSavedDocSelected() {
        const id = document.getElementById('savedDocQuickSelect').value;
        if (id === '0') {
            currentSavedDocId = null;
            document.getElementById('activeDocumentId').value = 0;
            document.getElementById('editingDocBadge').style.display = 'none';
            document.getElementById('saveDocBtn').innerHTML = '<i data-feather="save"></i> Save Document';
            if (window.feather) feather.replace();
            return;
        }
        loadSavedDocumentToWorkspace(id);
    }

    function loadSavedDocumentToWorkspace(docId) {
        fetch(`api.php?action=get_document_details&id=${docId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const doc = data.document;
                    currentSavedDocId = doc.id;
                    document.getElementById('activeDocumentId').value = doc.id;
                    
                    document.getElementById('clientSelect').value = doc.client_id;
                    onClientChanged();
                    
                    document.getElementById('docTypeSelect').value = doc.document_type;
                    document.getElementById('previewDocTitle').textContent = doc.title;
                    document.getElementById('documentRenderArea').innerHTML = doc.content_html;
                    document.getElementById('previewStatusBadge').textContent = `Saved (${doc.document_number} v${doc.version})`;
                    document.getElementById('previewStatusBadge').className = 'badge badge-success';
                    document.getElementById('handoverBtn').style.display = 'inline-flex';
                    
                    document.getElementById('editingDocBadge').style.display = 'inline-block';
                    document.getElementById('editingDocBadge').textContent = `Editing #${doc.document_number} (v${doc.version})`;
                    document.getElementById('saveDocBtn').innerHTML = '<i data-feather="save"></i> Update Saved Document';
                    
                    if (window.feather) feather.replace();
                    closeModal('savedDocumentsModal');
                } else {
                    alert(data.message);
                }
            });
    }

    function onClientChanged() {
        const clientId = document.getElementById('clientSelect').value;
        const svcSelect = document.getElementById('serviceSelect');
        svcSelect.innerHTML = '<option value="">Loading services...</option>';

        if (!clientId) {
            svcSelect.innerHTML = '<option value="">-- Select Client First --</option>';
            document.getElementById('autofillSummary').style.display = 'none';
            return;
        }

        fetch(`api.php?action=get_client_services&client_id=${clientId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.services.length > 0) {
                    svcSelect.innerHTML = '<option value="">-- Select Service Package --</option>';
                    data.services.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.id;
                        opt.textContent = `${s.service_name} - ${s.package_name} (INR ${Number(s.price).toLocaleString('en-IN')})`;
                        opt.dataset.service = JSON.stringify(s);
                        svcSelect.appendChild(opt);
                    });
                    svcSelect.selectedIndex = 1;
                    onServiceChanged();
                } else {
                    svcSelect.innerHTML = '<option value="0">General Digital Marketing Service</option>';
                    onServiceChanged();
                }
            });
    }

    function onServiceChanged() {
        const clientOpt = document.getElementById('clientSelect').selectedOptions[0];
        const svcOpt = document.getElementById('serviceSelect').selectedOptions[0];

        if (!clientOpt || !clientOpt.value) return;

        let svcData = null;
        if (svcOpt && svcOpt.dataset.service) {
            svcData = JSON.parse(svcOpt.dataset.service);
        }

        const details = `
            <strong>Client:</strong> ${clientOpt.dataset.company} (Attn: ${clientOpt.dataset.contact}, ${clientOpt.dataset.email})<br>
            <strong>Service:</strong> ${svcData ? svcData.service_name + ' (' + svcData.package_name + ')' : 'Digital Marketing & Social Media'}<br>
            <strong>Pricing:</strong> INR ${svcData ? Number(svcData.price).toLocaleString('en-IN') : '70,000'}<br>
            <strong>Terms:</strong> ${svcData ? svcData.billing_terms + ' | ' + svcData.payment_terms : 'Monthly in advance'}
        `;

        document.getElementById('autofillDetails').innerHTML = details;
        document.getElementById('autofillSummary').style.display = 'block';

        if (document.getElementById('templateSelect').value && !currentSavedDocId) {
            previewDocument();
        }
    }

    function onDocTypeChanged() {
        const docType = document.getElementById('docTypeSelect').value;
        const tmplSelect = document.getElementById('templateSelect');
        tmplSelect.innerHTML = '<option value="">-- Select Template --</option>';

        if (rawTemplates[docType]) {
            rawTemplates[docType].forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.template_name;
                tmplSelect.appendChild(opt);
            });
            if (tmplSelect.options.length > 1) {
                tmplSelect.selectedIndex = 1;
                if (!currentSavedDocId) previewDocument();
            }
        }
    }

    function previewDocument() {
        const clientId = document.getElementById('clientSelect').value;
        const serviceId = document.getElementById('serviceSelect').value;
        const templateId = document.getElementById('templateSelect').value;

        if (!clientId || !templateId) return;

        const customFields = {
            AGENCY_NAME: document.getElementById('agencyName').value,
            AGENCY_ADDRESS: document.getElementById('agencyAddress').value,
            CUSTOM_SCOPE: document.getElementById('customScope').value,
            CUSTOM_NOTES: document.getElementById('customNotes').value
        };

        fetch('api.php?action=preview_document', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                client_id: clientId,
                service_id: serviceId,
                template_id: templateId,
                custom_fields: customFields
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('documentRenderArea').innerHTML = data.content_html;
                document.getElementById('previewDocTitle').textContent = data.title;
                if (!currentSavedDocId) {
                    document.getElementById('previewStatusBadge').textContent = 'Draft Live Preview';
                    document.getElementById('previewStatusBadge').className = 'badge badge-warning';
                }
            }
        });
    }

    function generateDocument() {
        const docId = intval(document.getElementById('activeDocumentId').value);
        const clientId = document.getElementById('clientSelect').value;
        const serviceId = document.getElementById('serviceSelect').value;
        const templateId = document.getElementById('templateSelect').value;
        const docType = document.getElementById('docTypeSelect').value;
        const title = document.getElementById('previewDocTitle').textContent;
        const contentHtml = document.getElementById('documentRenderArea').innerHTML;

        if (!clientId || (!templateId && !docId)) {
            alert('Please select a Client, Document Type, and Template.');
            return;
        }

        const customFields = {
            AGENCY_NAME: document.getElementById('agencyName').value,
            AGENCY_ADDRESS: document.getElementById('agencyAddress').value,
            CUSTOM_SCOPE: document.getElementById('customScope').value,
            CUSTOM_NOTES: document.getElementById('customNotes').value
        };

        if (docId > 0) {
            const changeNote = prompt("Enter a change summary / version note for this update:", "Updated document content");
            if (changeNote === null) return;

            fetch('api.php?action=update_document', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    document_id: docId,
                    client_id: clientId,
                    service_id: serviceId,
                    document_type: docType,
                    title: title,
                    content_html: contentHtml,
                    custom_fields: customFields,
                    change_summary: changeNote
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    loadSavedDocsQuickDropdown();
                    document.getElementById('previewStatusBadge').textContent = `Saved (v${data.version})`;
                    document.getElementById('previewStatusBadge').className = 'badge badge-success';
                } else {
                    alert('Error: ' + data.message);
                }
            });
        } else {
            fetch('api.php?action=generate_document', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    client_id: clientId,
                    service_id: serviceId,
                    template_id: templateId,
                    document_type: docType,
                    title: title,
                    content_html: contentHtml,
                    custom_fields: customFields
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    currentSavedDocId = data.document_id;
                    document.getElementById('activeDocumentId').value = data.document_id;
                    document.getElementById('previewStatusBadge').textContent = `Saved (${data.document_number})`;
                    document.getElementById('previewStatusBadge').className = 'badge badge-success';
                    document.getElementById('handoverBtn').style.display = 'inline-flex';
                    loadSavedDocsQuickDropdown();
                    alert('✅ ' + data.message);
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
    }

    function toggleDirectLiveEdit() {
        const renderArea = document.getElementById('documentRenderArea');
        const banner = document.getElementById('directEditBanner');
        const btn = document.getElementById('directEditBtn');

        isDirectEditMode = !isDirectEditMode;

        if (isDirectEditMode) {
            renderArea.contentEditable = "true";
            renderArea.style.border = "2px dashed #3b82f6";
            banner.style.display = "flex";
            btn.className = "btn btn-primary btn-sm";
            btn.innerHTML = '<i data-feather="check"></i> Live Edit Mode Active';
        } else {
            renderArea.contentEditable = "false";
            renderArea.style.border = "none";
            banner.style.display = "none";
            btn.className = "btn btn-outline btn-sm";
            btn.innerHTML = '<i data-feather="edit-2"></i> Edit Content';
        }
        if (window.feather) feather.replace();
    }

    function saveDirectLiveEdits() {
        toggleDirectLiveEdit();
        if (currentSavedDocId) {
            generateDocument();
        } else {
            alert("Edits applied to live preview! Click 'Save Document' to persist your changes.");
        }
    }

    function openNewDocumentModal() {
        document.getElementById('nd_title').value = '';
        document.getElementById('nd_content').value = '';
        document.getElementById('newDocumentModal').style.display = 'flex';
    }

    function onNewDocClientChanged() {
        const clientId = document.getElementById('nd_client_id').value;
        const svcSelect = document.getElementById('nd_service_id');
        svcSelect.innerHTML = '<option value="">Loading services...</option>';

        if (!clientId) {
            svcSelect.innerHTML = '<option value="">-- Choose Client First --</option>';
            return;
        }

        fetch(`api.php?action=get_client_services&client_id=${clientId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.services.length > 0) {
                    svcSelect.innerHTML = '<option value="">-- Select Service Package --</option>';
                    data.services.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.id;
                        opt.textContent = `${s.service_name} (${s.package_name})`;
                        svcSelect.appendChild(opt);
                    });
                } else {
                    svcSelect.innerHTML = '<option value="0">General Service</option>';
                }
            });
    }

    function loadTemplateIntoNewDocModal() {
        const id = document.getElementById('nd_template_id').value;
        if (!id) return;
        fetch(`api.php?action=get_template_details&id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('nd_doc_type').value = data.template.document_type;
                    document.getElementById('nd_content').value = data.template.content_template;
                    if (data.template.subject_template) {
                        document.getElementById('nd_title').value = data.template.subject_template;
                    }
                }
            });
    }

    function submitNewCustomDocument() {
        const clientId = document.getElementById('nd_client_id').value;
        const serviceId = document.getElementById('nd_service_id').value;
        const docType = document.getElementById('nd_doc_type').value;
        const title = document.getElementById('nd_title').value;
        const contentHtml = document.getElementById('nd_content').value;

        if (!clientId || !title || !contentHtml) {
            alert('Client, Document Title, and Content are required.');
            return;
        }

        fetch('api.php?action=generate_document', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                client_id: clientId,
                service_id: serviceId,
                document_type: docType,
                title: title,
                content_html: contentHtml
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('newDocumentModal');
                alert('✅ New Document Created Successfully!');
                loadSavedDocsQuickDropdown();
                loadSavedDocumentToWorkspace(data.document_id);
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function openSavedDocumentsModal() {
        document.getElementById('savedDocumentsModal').style.display = 'flex';
        fetchSavedDocsTable();
    }

    function fetchSavedDocsTable() {
        const tbody = document.getElementById('savedDocsTbody');
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px;">Loading documents...</td></tr>';

        fetch('api.php?action=get_documents')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.documents.length > 0) {
                    savedDocumentsCache = data.documents;
                    renderSavedDocsTable(data.documents);
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:#94a3b8;">No documents found. Click "+ Add New Document" to create one.</td></tr>';
                }
            });
    }

    function renderSavedDocsTable(docs) {
        const tbody = document.getElementById('savedDocsTbody');
        let html = '';
        docs.forEach(d => {
            html += `
                <tr>
                    <td><strong>${d.document_number}</strong></td>
                    <td>${escapeHtml(d.title)}</td>
                    <td>${escapeHtml(d.client_name)}</td>
                    <td><span class="badge badge-outline">${d.document_type}</span></td>
                    <td><span class="badge badge-info">v${d.version}</span></td>
                    <td><span class="badge badge-success">${d.status}</span></td>
                    <td style="text-align:right;">
                        <button class="btn btn-outline btn-sm" onclick="openEditDocumentModal(${d.id})" title="Edit Document Content & Details">
                            <i data-feather="edit"></i> Edit
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="loadSavedDocumentToWorkspace(${d.id})" title="Load in Workspace Preview">
                            <i data-feather="eye"></i> View
                        </button>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
        if (window.feather) feather.replace();
    }

    function filterSavedDocsTable() {
        const q = document.getElementById('sd_search').value.toLowerCase();
        const filtered = savedDocumentsCache.filter(d => 
            d.document_number.toLowerCase().includes(q) ||
            d.title.toLowerCase().includes(q) ||
            d.client_name.toLowerCase().includes(q) ||
            d.document_type.toLowerCase().includes(q)
        );
        renderSavedDocsTable(filtered);
    }

    function openEditDocumentModal(docId) {
        fetch(`api.php?action=get_document_details&id=${docId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const d = data.document;
                    document.getElementById('ed_document_id').value = d.id;
                    document.getElementById('ed_doc_number_badge').textContent = d.document_number;
                    document.getElementById('ed_version_badge').textContent = `v${d.version} -> v${d.version + 1}`;
                    document.getElementById('ed_client_id').value = d.client_id;
                    document.getElementById('ed_doc_type').value = d.document_type;
                    document.getElementById('ed_title').value = d.title;
                    document.getElementById('ed_change_summary').value = 'Updated document content';
                    document.getElementById('ed_content').value = d.content_html;

                    document.getElementById('editDocumentModal').style.display = 'flex';
                } else {
                    alert(data.message);
                }
            });
    }

    function submitDocumentEdits() {
        const docId = document.getElementById('ed_document_id').value;
        const clientId = document.getElementById('ed_client_id').value;
        const docType = document.getElementById('ed_doc_type').value;
        const title = document.getElementById('ed_title').value;
        const changeSummary = document.getElementById('ed_change_summary').value;
        const contentHtml = document.getElementById('ed_content').value;

        fetch('api.php?action=update_document', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                document_id: docId,
                client_id: clientId,
                document_type: docType,
                title: title,
                change_summary: changeSummary,
                content_html: contentHtml
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('editDocumentModal');
                alert('✅ ' + data.message);
                loadSavedDocsQuickDropdown();
                fetchSavedDocsTable();
                if (currentSavedDocId == docId) {
                    loadSavedDocumentToWorkspace(docId);
                }
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function insertPlaceholderToArea(targetId, placeholder) {
        const textarea = document.getElementById(targetId);
        if (!textarea) return;
        const start = textarea.selectionStart || 0;
        const end = textarea.selectionEnd || 0;
        const text = textarea.value;
        textarea.value = text.substring(0, start) + placeholder + text.substring(end);
        textarea.focus();
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function intval(val) { return parseInt(val, 10) || 0; }

    function openTemplateEditorModal() { document.getElementById('templateEditorModal').style.display = 'flex'; }

    function onEditorTemplateSelected() {
        const id = document.getElementById('te_select').value;
        if (id === '0') {
            document.getElementById('te_template_id').value = 0;
            document.getElementById('te_name').value = '';
            document.getElementById('te_subject').value = '';
            document.getElementById('te_content').value = '';
            return;
        }

        fetch(`api.php?action=get_template_details&id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const t = data.template;
                    document.getElementById('te_template_id').value = t.id;
                    document.getElementById('te_doc_type').value = t.document_type;
                    document.getElementById('te_name').value = t.template_name;
                    document.getElementById('te_subject').value = t.subject_template || '';
                    document.getElementById('te_content').value = t.content_template || '';
                }
            });
    }

    function saveTemplateFromEditor() {
        const payload = {
            template_id: document.getElementById('te_template_id').value,
            document_type: document.getElementById('te_doc_type').value,
            template_name: document.getElementById('te_name').value,
            subject_template: document.getElementById('te_subject').value,
            content_template: document.getElementById('te_content').value
        };

        fetch('api.php?action=save_template', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('templateEditorModal');
                alert('✅ ' + data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function exportPDF() { window.print(); }
    function exportWord() {
        const content = document.getElementById('documentRenderArea').innerHTML;
        const title = document.getElementById('previewDocTitle').textContent || 'Document';
        const html = `<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'><head><meta charset='utf-8'><title>${title}</title></head><body>${content}</body></html>`;
        const blob = new Blob(['\ufeff', html], { type: 'application/msword' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${title.replace(/[^a-zA-Z0-9]/g, '_')}.doc`;
        a.click();
    }

    function openQuickClientModal() { document.getElementById('quickClientModal').style.display = 'flex'; }
    function openHandoverModal() {
        if (!currentSavedDocId) { alert('Please save the document first.'); return; }
        
        fetch(`api.php?action=get_document_details&id=${currentSavedDocId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.document) {
                    const banner = document.getElementById('ho_client_info_banner');
                    if (banner) {
                        banner.innerHTML = `<strong>Target Client:</strong> ${escapeHtml(data.document.company_name)} &nbsp;|&nbsp; <strong>Doc:</strong> ${escapeHtml(data.document.title)}`;
                    }
                }
            });

        document.getElementById('ho_project_name').value = document.getElementById('previewDocTitle').textContent;
        document.getElementById('handoverModal').style.display = 'flex';
    }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    function saveQuickClient() {
        const payload = {
            company_name: document.getElementById('qc_company_name').value,
            contact_person: document.getElementById('qc_contact_person').value,
            email: document.getElementById('qc_email').value,
            phone: document.getElementById('qc_phone').value,
            service_name: document.getElementById('qc_service_name').value,
            price: document.getElementById('qc_price').value,
            username: document.getElementById('qc_username').value,
            password: document.getElementById('qc_password').value
        };

        fetch('api.php?action=create_client', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('quickClientModal');
                if (data.client_id) {
                    sessionStorage.setItem('autoSelectClientId', data.client_id);
                }
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function submitHandover() {
        const payload = {
            document_id: currentSavedDocId,
            project_name: document.getElementById('ho_project_name').value,
            team_lead_id: document.getElementById('ho_team_lead').value,
            priority: document.getElementById('ho_priority').value,
            deadline: document.getElementById('ho_deadline').value,
            description: document.getElementById('ho_description').value
        };

        fetch('api.php?action=handover_to_project', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('handoverModal');
                alert(data.message);
                window.location.href = 'projects.php';
            }
        });
    }

    function openVersionHistoryModal() {
        fetch('api.php?action=get_documents')
            .then(res => res.json())
            .then(data => {
                const select = document.getElementById('versionDocSelect');
                select.innerHTML = '<option value="">-- Choose Generated Document --</option>';
                if (data.success) {
                    data.documents.forEach(d => {
                        const opt = document.createElement('option');
                        opt.value = d.id;
                        opt.textContent = `${d.document_number} - ${d.title} (v${d.version})`;
                        select.appendChild(opt);
                    });
                }
                document.getElementById('versionModal').style.display = 'flex';
            });
    }

    function loadVersionHistory() {
        const docId = document.getElementById('versionDocSelect').value;
        const area = document.getElementById('versionListArea');
        if (!docId) return;

        fetch(`api.php?action=get_document_versions&document_id=${docId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.versions.length > 0) {
                    let html = '<div style="display:flex; flex-direction:column; gap:12px;">';
                    data.versions.forEach(v => {
                        html += `
                            <div style="border:1px solid #cbd5e1; border-radius:6px; padding:12px; background:#f8fafc;">
                                <div><strong>Version ${v.version_number} - ${v.title}</strong></div>
                                <p style="font-size:13px; color:#475569;">Change Note: ${v.change_summary}</p>
                            </div>
                        `;
                    });
                    html += '</div>';
                    area.innerHTML = html;
                }
            });
    }

    // --- DOCUMENT TYPES MANAGER JS ---
    let loadedDocTypes = [];

    function openDocumentTypesModal() {
        hideDocumentTypeForm();
        document.getElementById('documentTypesModal').style.display = 'flex';
        loadDocumentTypes();
    }

    function loadDocumentTypes() {
        fetch('api.php?action=get_document_types')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadedDocTypes = data.document_types;
                renderDocumentTypesTable(loadedDocTypes);
                populateDocTypeSelects(loadedDocTypes);
            }
        });
    }

    function populateDocTypeSelects(types) {
        const selectIds = ['nd_doc_type', 'ed_doc_type', 'te_doc_type'];
        selectIds.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            const currentVal = el.value;
            let html = types.map(t => `<option value="${escapeHtml(t.name)}">${escapeHtml(t.name)}</option>`).join('');
            el.innerHTML = html;
            if (currentVal && types.some(t => t.name === currentVal)) {
                el.value = currentVal;
            }
        });
    }

    function renderDocumentTypesTable(types) {
        const tbody = document.getElementById('dt_table_body');
        if (!tbody) return;
        if (!types || types.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:20px;">No document types found. Click '+ Add Document Type' to create one!</td></tr>`;
            return;
        }
        tbody.innerHTML = types.map(t => `
            <tr>
                <td><strong>${escapeHtml(t.name)}</strong></td>
                <td><span style="color:var(--text-muted); font-size:13px;">${escapeHtml(t.description || 'N/A')}</span></td>
                <td><span class="badge badge-primary">${t.template_count || 0} Template(s)</span></td>
                <td><span class="badge badge-info">${t.document_count || 0} Document(s)</span></td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="editDocumentType(${t.id})">Edit</button>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteDocumentType(${t.id}, '${escapeHtml(addslashes(t.name))}')">Delete</button>
                    </div>
                </td>
            </tr>
        `).join('');
        if (window.feather) feather.replace();
    }

    function showAddDocumentTypeForm() {
        document.getElementById('dt_type_id').value = 0;
        document.getElementById('dt_name').value = '';
        document.getElementById('dt_description').value = '';
        document.getElementById('dt_form_title').textContent = '+ Add New Document Type';
        document.getElementById('dt_form_card').style.display = 'block';
    }

    function hideDocumentTypeForm() {
        document.getElementById('dt_form_card').style.display = 'none';
    }

    function editDocumentType(typeId) {
        const t = loadedDocTypes.find(item => item.id == typeId);
        if (!t) return;
        document.getElementById('dt_type_id').value = t.id;
        document.getElementById('dt_name').value = t.name;
        document.getElementById('dt_description').value = t.description || '';
        document.getElementById('dt_form_title').textContent = '✏️ Edit Document Type #' + t.id;
        document.getElementById('dt_form_card').style.display = 'block';
    }

    function submitSaveDocumentType() {
        const typeId = document.getElementById('dt_type_id').value;
        const name = document.getElementById('dt_name').value;
        const description = document.getElementById('dt_description').value;

        const action = typeId > 0 ? 'update_document_type' : 'add_document_type';

        fetch(`api.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: typeId, name: name, description: description })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                hideDocumentTypeForm();
                alert('✅ ' + data.message);
                loadDocumentTypes();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function deleteDocumentType(id, typeName) {
        if (!confirm(`Are you sure you want to delete document type '${typeName}'?`)) return;
        fetch('api.php?action=delete_document_type', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                loadDocumentTypes();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    // Auto-load document types on page initialization
    document.addEventListener('DOMContentLoaded', function() {
        loadDocumentTypes();
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
