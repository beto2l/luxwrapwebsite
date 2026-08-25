<?php
/**
 * LuxWrap Studio - Admin Dashboard
 * Portfolio management system
 */
require_once __DIR__ . '/../scripts/config.php';
session_start();

if (!isAdminAuthenticated()) {
    header('Location: index.php');
    exit;
}

$portfolio = getPortfolioData();
$csrf = generateCSRFToken();
$successMsg = $_SESSION['success_msg'] ?? '';
$errorMsg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Secreto de despliegue leído del .env del servidor.
// Solo se expone dentro de esta página, que está protegida por login de administrador.
$deploySecret = function_exists('luxwrap_env') ? luxwrap_env('DEPLOY_SECRET', '') : '';

/* ============================================================
 *  Historial de versiones (botón "Última versión" + modal)
 * ============================================================ */
$siteRoot = dirname(__DIR__);

/** Ejecuta un comando git dentro de la raíz del sitio y devuelve la salida. */
function admin_git($siteRoot, $args) {
    if (!is_dir($siteRoot . '/.git') || !function_exists('shell_exec')) {
        return null;
    }
    $cmd = 'cd ' . escapeshellarg($siteRoot) . ' && git ' . $args . ' 2>/dev/null';
    $out = @shell_exec($cmd);
    return ($out === null) ? null : trim($out);
}

// 1) Versión actual instalada: data/version.json (lo escribe deploy.php) → fallback git.
$currentVersion = null;
$currentBranch = null;
$versionFile = $siteRoot . '/data/version.json';

if (is_file($versionFile)) {
    $versionData = @json_decode(file_get_contents($versionFile), true);
    if ($versionData && is_array($versionData)) {
        $currentVersion = [
            'hash'    => $versionData['commit_hash'] ?? null,
            'date'    => $versionData['commit_date'] ?? $versionData['deployed_at'] ?? null,
            'author'  => $versionData['author'] ?? null,
            'subject' => $versionData['subject'] ?? null,
            'method'  => $versionData['method'] ?? null,
        ];
        $currentBranch = $versionData['branch'] ?? null;
    }
}

if (!$currentVersion) {
    $rawCurrent = admin_git($siteRoot, "log -1 --date=format:'%Y-%m-%d %H:%M' --format='%h|%cd|%an|%s'");
    if ($rawCurrent) {
        $parts = explode('|', $rawCurrent, 4);
        if (count($parts) === 4) {
            $currentVersion = [
                'hash' => $parts[0], 'date' => $parts[1],
                'author' => $parts[2], 'subject' => $parts[3], 'method' => 'git',
            ];
        }
    }
    if (!$currentBranch) {
        $currentBranch = admin_git($siteRoot, 'rev-parse --abbrev-ref HEAD');
    }
}

// 2) Historial curado: data/versions.json → fallback a git log (últimas 3).
$versionHistory = [];
$versionsFile = $siteRoot . '/data/versions.json';
if (is_file($versionsFile)) {
    $vh = @json_decode(file_get_contents($versionsFile), true);
    if (isset($vh['versions']) && is_array($vh['versions'])) {
        $versionHistory = $vh['versions'];
    }
}

// Fallback: construir historial desde git si no hay versions.json.
if (empty($versionHistory)) {
    $rawLog = admin_git($siteRoot, "log -n 3 --date=format:'%Y-%m-%d' --format='%h|%cd|%an|%s'");
    if ($rawLog) {
        foreach (explode("\n", $rawLog) as $line) {
            $p = explode('|', $line, 4);
            if (count($p) === 4) {
                $versionHistory[] = [
                    'version' => $p[0],
                    'date'    => $p[1],
                    'author'  => $p[2],
                    'title'   => $p[3],
                    'changes' => [$p[3]],
                ];
            }
        }
    }
}

// Mostrar solo las últimas 3 versiones (las más recientes primero).
$latestVersions = array_slice($versionHistory, 0, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — LuxWrap Studio Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/jpeg" href="../assets/images/logo.jpeg">
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="admin-header">
        <div class="admin-header-left">
            <img src="../assets/images/logo.jpeg" alt="LuxWrap Studio" class="admin-logo">
            <h1>Portfolio Manager</h1>
        </div>
        <div class="admin-header-right">
            <a href="../" target="_blank" class="btn-sm btn-outline-light"><i class="fas fa-external-link-alt"></i> View Site</a>
            <a href="logout.php" class="btn-sm btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <?php if ($successMsg): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMsg) ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <!-- Add New Project -->
        <section class="admin-section">
            <div class="section-header" onclick="toggleSection('addProject')">
                <h2><i class="fas fa-plus-circle"></i> Add New Project</h2>
                <i class="fas fa-chevron-down toggle-icon" id="addProject-icon"></i>
            </div>
            <div class="section-body" id="addProject" style="display:none;">
                <form method="POST" action="upload.php" enctype="multipart/form-data" class="project-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="create_project">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Project Name (English) *</label>
                            <input type="text" name="name_en" required placeholder="e.g. Angel's Roofing & Restoration">
                        </div>
                        <div class="form-group">
                            <label>Nombre del Proyecto (Español) *</label>
                            <input type="text" name="name_es" required placeholder="e.g. Angel's Roofing & Restoration">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Description (English)</label>
                            <textarea name="description_en" rows="3" placeholder="Brief description of the project..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Descripción (Español)</label>
                            <textarea name="description_es" rows="3" placeholder="Descripción breve del proyecto..."></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Project Images *</label>
                        <div class="upload-zone" id="uploadZone">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Drag & drop images here or <span class="browse-link">click to browse</span></p>
                            <p class="upload-hint">JPG, PNG, WebP — Max 10MB per image</p>
                            <input type="file" name="images[]" id="fileInput" multiple accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;">
                        </div>
                        <div class="preview-grid" id="previewGrid"></div>
                    </div>
                    
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Project</button>
                </form>
            </div>
        </section>

        <!-- Existing Projects -->
        <section class="admin-section">
            <h2><i class="fas fa-th-large"></i> Current Projects (<?= count($portfolio['projects']) ?>)</h2>
            
            <?php if (empty($portfolio['projects'])): ?>
                <div class="empty-state">
                    <i class="fas fa-images"></i>
                    <p>No projects yet. Add your first project above!</p>
                </div>
            <?php else: ?>
                <div class="projects-list" id="projectsList">
                    <?php foreach ($portfolio['projects'] as $index => $project): ?>
                        <div class="project-card" data-id="<?= htmlspecialchars($project['id']) ?>">
                            <div class="project-card-header">
                                <div class="drag-handle"><i class="fas fa-grip-vertical"></i></div>
                                <div class="project-info">
                                    <h3><?= htmlspecialchars($project['name_en']) ?></h3>
                                    <span class="project-meta"><?= count($project['images'] ?? []) ?> images · Created: <?= htmlspecialchars($project['created'] ?? 'N/A') ?></span>
                                </div>
                                <div class="project-actions">
                                    <button class="btn-sm btn-edit" onclick="toggleEdit('<?= htmlspecialchars($project['id']) ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this entire project and all its images?');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="delete_project">
                                        <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                        <button type="submit" class="btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Image thumbnails -->
                            <div class="project-images-grid">
                                <?php foreach (($project['images'] ?? []) as $img): ?>
                                    <div class="thumb-item">
                                        <img src="../<?= htmlspecialchars($img['path']) ?>" alt="" loading="lazy">
                                        <form method="POST" action="delete.php" class="thumb-delete" onsubmit="return confirm('Delete this image?');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="action" value="delete_image">
                                            <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                            <input type="hidden" name="filename" value="<?= htmlspecialchars($img['filename']) ?>">
                                            <button type="submit" class="btn-icon-danger"><i class="fas fa-times"></i></button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Edit form (hidden) -->
                            <div class="edit-form" id="edit-<?= htmlspecialchars($project['id']) ?>" style="display:none;">
                                <form method="POST" action="upload.php" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="action" value="update_project">
                                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Name (EN)</label>
                                            <input type="text" name="name_en" value="<?= htmlspecialchars($project['name_en'] ?? '') ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Nombre (ES)</label>
                                            <input type="text" name="name_es" value="<?= htmlspecialchars($project['name_es'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Description (EN)</label>
                                            <textarea name="description_en" rows="2"><?= htmlspecialchars($project['description_en'] ?? '') ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label>Descripción (ES)</label>
                                            <textarea name="description_es" rows="2"><?= htmlspecialchars($project['description_es'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Add More Images</label>
                                        <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp,image/gif">
                                    </div>
                                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Update Site from GitHub -->
        <section class="admin-section">
            <div class="section-header" onclick="toggleSection('updateSite')">
                <h2><i class="fas fa-sync-alt"></i> Update Site / Actualizar Sitio</h2>
                <i class="fas fa-chevron-down toggle-icon" id="updateSite-icon"></i>
            </div>
            <div class="section-body" id="updateSite" style="display:none;">
                <p style="margin-top:0;color:#c9c9d6;line-height:1.6;">
                    Trae los últimos cambios publicados en GitHub al sitio en vivo.
                    No necesitas escribir ninguna clave: el sistema ya la incluye de forma segura.
                </p>
                <?php if (empty($deploySecret)): ?>
                    <div class="alert alert-error" style="display:flex;">
                        <i class="fas fa-exclamation-triangle"></i>
                        No se encontró <code>DEPLOY_SECRET</code> en el archivo <code>.env</code> del servidor.
                        Agrégalo para poder actualizar desde aquí.
                    </div>
                <?php else: ?>
                    <button type="button" id="deployBtn" class="btn-primary">
                        <i class="fas fa-cloud-download-alt"></i> Actualizar sitio desde GitHub
                    </button>
                    <pre id="deployResult" style="display:none;white-space:pre-wrap;margin-top:18px;background:#0b0b14;border:1px solid rgba(255,255,255,.14);border-radius:12px;padding:16px;max-height:340px;overflow:auto;color:#e8e8f2;"></pre>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Footer del panel admin -->
    <footer class="admin-footer">
        <span class="admin-footer-text">
            &copy; <?= date('Y') ?> LuxWrap Studio · Portfolio Manager
        </span>
        <button type="button" class="btn-version" onclick="openVersionModal()">
            <i class="fas fa-code-branch"></i> Última versión
            <?php if (!empty($currentVersion['hash'])): ?>
                <code class="version-badge"><?= htmlspecialchars($currentVersion['hash']) ?></code>
            <?php endif; ?>
        </button>
    </footer>

    <!-- Modal flotante: historial de versiones -->
    <div class="version-modal-overlay" id="versionModal" onclick="if(event.target===this)closeVersionModal()">
        <div class="version-modal" role="dialog" aria-modal="true" aria-labelledby="versionModalTitle">
            <div class="version-modal-header">
                <h2 id="versionModalTitle"><i class="fas fa-code-branch"></i> Historial de versiones</h2>
                <button type="button" class="version-modal-close" onclick="closeVersionModal()" aria-label="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="version-modal-body">
                <!-- Versión actual instalada -->
                <div class="version-current">
                    <span class="version-current-label">Versión actual instalada</span>
                    <?php if ($currentVersion): ?>
                        <dl class="version-grid">
                            <?php if (!empty($currentVersion['hash'])): ?>
                            <dt>Versión</dt><dd><code><?= htmlspecialchars($currentVersion['hash']) ?></code></dd>
                            <?php endif; ?>
                            <?php if (!empty($currentVersion['date'])): ?>
                            <dt>Fecha</dt><dd><?= htmlspecialchars($currentVersion['date']) ?></dd>
                            <?php endif; ?>
                            <?php if (!empty($currentVersion['author'])): ?>
                            <dt>Autor</dt><dd><?= htmlspecialchars($currentVersion['author']) ?></dd>
                            <?php endif; ?>
                            <?php if (!empty($currentVersion['subject'])): ?>
                            <dt>Cambio</dt><dd><?= htmlspecialchars($currentVersion['subject']) ?></dd>
                            <?php endif; ?>
                            <?php if ($currentBranch): ?>
                            <dt>Rama</dt><dd><?= htmlspecialchars($currentBranch) ?></dd>
                            <?php endif; ?>
                        </dl>
                    <?php else: ?>
                        <p class="muted">No se pudo determinar la versión instalada.</p>
                    <?php endif; ?>
                </div>

                <!-- Últimas 3 versiones -->
                <h3 class="version-history-title">Últimos cambios</h3>
                <?php if (!empty($latestVersions)): ?>
                    <ul class="version-timeline">
                        <?php foreach ($latestVersions as $v): ?>
                            <li class="version-item">
                                <div class="version-item-head">
                                    <span class="version-item-tag"><?= htmlspecialchars($v['version'] ?? '—') ?></span>
                                    <span class="version-item-date"><?= htmlspecialchars($v['date'] ?? '') ?></span>
                                </div>
                                <?php if (!empty($v['title'])): ?>
                                    <p class="version-item-title"><?= htmlspecialchars($v['title']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($v['changes']) && is_array($v['changes'])): ?>
                                    <ul class="version-item-changes">
                                        <?php foreach ($v['changes'] as $change): ?>
                                            <li><?= htmlspecialchars($change) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <?php if (!empty($v['author'])): ?>
                                    <span class="version-item-author"><i class="fas fa-user"></i> <?= htmlspecialchars($v['author']) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted">Aún no hay historial de cambios disponible.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
    /* ===== Footer + botón de versión ===== */
    .admin-footer {
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 12px;
        padding: 20px 32px; margin-top: 24px;
        border-top: 1px solid rgba(255,255,255,.08);
        color: #9a9ab0; font-size: 13px;
    }
    .admin-footer-text { opacity: .85; }
    .btn-version {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 9px 16px; border-radius: 10px; cursor: pointer;
        font-size: 13px; font-weight: 600; color: #fff;
        background: linear-gradient(135deg, #6a5cff, #b14cff);
        border: 1px solid rgba(255,255,255,.14);
        transition: transform .15s ease, box-shadow .15s ease, opacity .15s ease;
    }
    .btn-version:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(140,80,255,.35); }
    .btn-version .version-badge {
        background: rgba(0,0,0,.35); border-radius: 6px; padding: 1px 7px;
        font-size: 12px; color: #d9c9ff;
    }

    /* ===== Modal ===== */
    .version-modal-overlay {
        display: none; position: fixed; inset: 0; z-index: 1000;
        background: rgba(6,6,14,.72); backdrop-filter: blur(4px);
        align-items: center; justify-content: center; padding: 20px;
    }
    .version-modal-overlay.open { display: flex; }
    .version-modal {
        width: 100%; max-width: 540px; max-height: 88vh; overflow: hidden;
        display: flex; flex-direction: column;
        background: linear-gradient(180deg, #16161f, #0e0e16);
        border: 1px solid rgba(255,255,255,.12); border-radius: 18px;
        box-shadow: 0 24px 60px rgba(0,0,0,.55);
        animation: versionModalIn .22s ease;
    }
    @keyframes versionModalIn { from { opacity: 0; transform: translateY(14px) scale(.98); } to { opacity: 1; transform: none; } }
    .version-modal-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 20px 24px; border-bottom: 1px solid rgba(255,255,255,.08);
    }
    .version-modal-header h2 {
        margin: 0; font-size: 18px; font-weight: 700;
        background: linear-gradient(135deg, #7aa8ff, #c56bff);
        -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    }
    .version-modal-header h2 i { -webkit-text-fill-color: initial; color: #a97bff; margin-right: 6px; }
    .version-modal-close {
        background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12);
        color: #cfcfe0; width: 34px; height: 34px; border-radius: 9px; cursor: pointer;
        transition: background .15s ease;
    }
    .version-modal-close:hover { background: rgba(255,255,255,.14); }
    .version-modal-body { padding: 22px 24px; overflow-y: auto; }

    .version-current {
        background: rgba(120,90,255,.08); border: 1px solid rgba(140,90,255,.25);
        border-radius: 12px; padding: 16px 18px; margin-bottom: 22px;
    }
    .version-current-label {
        display: block; font-size: 12px; text-transform: uppercase; letter-spacing: .5px;
        color: #a97bff; font-weight: 600; margin-bottom: 12px;
    }
    .version-grid { display: grid; grid-template-columns: 90px 1fr; gap: 8px 14px; font-size: 14px; margin: 0; }
    .version-grid dt { color: #9a9ab0; }
    .version-grid dd { color: #e8e8f2; word-break: break-word; margin: 0; }
    .version-grid dd code {
        background: #0b0b14; border: 1px solid rgba(255,255,255,.14);
        border-radius: 6px; padding: 2px 8px; font-size: 13px; color: #65ff9a;
    }
    .muted { color: #9a9ab0; font-size: 13px; line-height: 1.5; margin: 0; }

    .version-history-title { font-size: 14px; color: #cfcfe0; margin: 0 0 14px; font-weight: 600; }
    .version-timeline { list-style: none; margin: 0; padding: 0; }
    .version-item {
        position: relative; padding: 14px 16px; margin-bottom: 12px;
        background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.08);
        border-radius: 12px; border-left: 3px solid #a97bff;
    }
    .version-item-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
    .version-item-tag {
        font-size: 12px; font-weight: 700; color: #d9c9ff;
        background: rgba(140,90,255,.18); border-radius: 6px; padding: 2px 9px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }
    .version-item-date { font-size: 12px; color: #9a9ab0; }
    .version-item-title { margin: 0 0 8px; font-size: 14px; font-weight: 600; color: #f0f0f7; }
    .version-item-changes { margin: 0 0 8px; padding-left: 18px; }
    .version-item-changes li { font-size: 13px; color: #c9c9d6; line-height: 1.55; margin-bottom: 4px; }
    .version-item-author { font-size: 12px; color: #8a8aa0; }
    .version-item-author i { margin-right: 4px; }

    @media (max-width: 600px) {
        .admin-footer { flex-direction: column; align-items: flex-start; padding: 18px 20px; }
        .version-grid { grid-template-columns: 78px 1fr; }
    }
    </style>

    <script>
    // ===== Modal de historial de versiones =====
    function openVersionModal() {
        document.getElementById('versionModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeVersionModal() {
        document.getElementById('versionModal').classList.remove('open');
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeVersionModal();
    });
    </script>

    <script>
    // Toggle sections
    function toggleSection(id) {
        const el = document.getElementById(id);
        const icon = document.getElementById(id + '-icon');
        if (el.style.display === 'none') {
            el.style.display = 'block';
            if (icon) icon.style.transform = 'rotate(180deg)';
        } else {
            el.style.display = 'none';
            if (icon) icon.style.transform = '';
        }
    }
    
    // Toggle edit form
    function toggleEdit(id) {
        const el = document.getElementById('edit-' + id);
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }

    // Drag & Drop Upload
    const uploadZone = document.getElementById('uploadZone');
    const fileInput = document.getElementById('fileInput');
    const previewGrid = document.getElementById('previewGrid');

    if (uploadZone && fileInput) {
        uploadZone.addEventListener('click', () => fileInput.click());
        uploadZone.addEventListener('dragover', (e) => { e.preventDefault(); uploadZone.classList.add('dragover'); });
        uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            showPreviews(e.dataTransfer.files);
        });
        fileInput.addEventListener('change', () => showPreviews(fileInput.files));
    }

    function showPreviews(files) {
        if (!previewGrid) return;
        previewGrid.innerHTML = '';
        Array.from(files).forEach(file => {
            if (!file.type.startsWith('image/')) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                const div = document.createElement('div');
                div.className = 'preview-item';
                div.innerHTML = `<img src="${e.target.result}" alt="Preview"><span>${file.name}</span>`;
                previewGrid.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    }

    // Auto-hide alerts
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => alert.style.display = 'none', 5000);
    });

    // ===== Update Site from GitHub (AJAX) =====
    (function () {
        const deployBtn = document.getElementById('deployBtn');
        if (!deployBtn) return;
        const deployResult = document.getElementById('deployResult');
        // Secreto inyectado desde el .env del servidor (solo visible para admin autenticado).
        const DEPLOY_SECRET = <?= json_encode($deploySecret) ?>;

        deployBtn.addEventListener('click', async () => {
            if (!confirm('¿Actualizar el sitio con los últimos cambios de GitHub?')) return;

            const originalHtml = deployBtn.innerHTML;
            deployBtn.disabled = true;
            deployBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
            deployResult.style.display = 'block';
            deployResult.style.color = '#e8e8f2';
            deployResult.textContent = 'Conectando con el servidor...';

            try {
                const response = await fetch('../deploy.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json'
                    },
                    body: new URLSearchParams({ secret: DEPLOY_SECRET })
                });
                const text = await response.text();
                let json = null;
                try { json = JSON.parse(text); } catch (e) {}

                if (json && json.success) {
                    deployResult.style.color = '#65ff9a';
                    deployResult.textContent = '✅ ' + (json.message || 'Sitio actualizado correctamente') +
                        '\n\n' + JSON.stringify(json, null, 2);
                } else {
                    deployResult.style.color = '#ff7777';
                    deployResult.textContent = '❌ No se pudo actualizar.\n\n' +
                        (json ? JSON.stringify(json, null, 2) : text);
                }
            } catch (error) {
                deployResult.style.color = '#ff7777';
                deployResult.textContent = '❌ Error de conexión: ' + error.message;
            } finally {
                deployBtn.disabled = false;
                deployBtn.innerHTML = originalHtml;
            }
        });
    })();
    </script>
</body>
</html>
