<?php
/**
 * LuxWrap Studio - Deploy/Update endpoint
 * Lee configuración sensible desde .env.
 * Permite actualizar el sitio desde GitHub usando un botón en update.html.
 */

require_once __DIR__ . '/scripts/env-loader.php';

header('Content-Type: application/json; charset=utf-8');

$secret = luxwrap_env('DEPLOY_SECRET');
$providedSecret = $_POST['secret'] ?? $_GET['secret'] ?? '';

if (!$secret || !hash_equals($secret, $providedSecret)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

// deploy.php SIEMPRE vive en la raíz del sitio, así que __DIR__ es la ruta real y confiable.
// Solo usamos SITE_PATH del .env si apunta a una carpeta que realmente existe;
// de lo contrario caemos a __DIR__ para evitar el error "SITE_PATH no existe".
$configuredPath = rtrim(luxwrap_env('SITE_PATH', ''), '/');
$sitePath = ($configuredPath !== '' && is_dir($configuredPath)) ? $configuredPath : __DIR__;
$branch = luxwrap_env('GIT_BRANCH', 'main');
$repoZipUrl = luxwrap_env('GITHUB_ZIP_URL', 'https://github.com/beto2l/luxwrapwebsite/archive/refs/heads/' . $branch . '.zip');
$logFile = __DIR__ . '/deploy.log';

function deploy_log($message) {
    global $logFile;
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
}

function run_cmd($command) {
    deploy_log('CMD: ' . $command);
    exec($command . ' 2>&1', $output, $code);
    $text = implode("\n", $output);
    deploy_log('OUT: ' . $text);
    deploy_log('CODE: ' . $code);
    return ['code' => $code, 'output' => $text];
}

function recursive_copy($src, $dst) {
    $excluded = ['.git', '.env', 'deploy.log'];
    $items = scandir($src);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || in_array($item, $excluded, true)) {
            continue;
        }

        $srcPath = $src . '/' . $item;
        $dstPath = $dst . '/' . $item;

        if ($item === 'uploads' || $item === 'data') {
            if (!is_dir($dstPath)) {
                @mkdir($dstPath, 0755, true);
            }
            continue;
        }

        if (is_dir($srcPath)) {
            if (!is_dir($dstPath)) {
                @mkdir($dstPath, 0755, true);
            }
            recursive_copy($srcPath, $dstPath);
        } else {
            @copy($srcPath, $dstPath);
            @chmod($dstPath, 0644);
        }
    }
}

function update_with_git($sitePath, $branch) {
    chdir($sitePath);
    $steps = [];
    $steps[] = ['step' => 'git fetch', 'result' => run_cmd('git fetch origin ' . escapeshellarg($branch))];
    $steps[] = ['step' => 'git reset', 'result' => run_cmd('git reset --hard origin/' . escapeshellarg($branch))];
    $ok = end($steps)['result']['code'] === 0;
    return ['success' => $ok, 'method' => 'git', 'steps' => $steps];
}

function update_with_zip($sitePath, $repoZipUrl) {
    if (!class_exists('ZipArchive')) {
        return ['success' => false, 'method' => 'zip', 'message' => 'ZipArchive no está disponible en este hosting'];
    }

    $tmpBase = sys_get_temp_dir() . '/luxwrap_deploy_' . uniqid();
    $zipFile = $tmpBase . '.zip';
    @mkdir($tmpBase, 0755, true);

    deploy_log('Descargando ZIP: ' . $repoZipUrl);
    $zipData = @file_get_contents($repoZipUrl);
    if ($zipData === false) {
        return ['success' => false, 'method' => 'zip', 'message' => 'No se pudo descargar el ZIP de GitHub. Si el repo es privado, agrega un token o usa Git SSH.'];
    }
    file_put_contents($zipFile, $zipData);

    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        return ['success' => false, 'method' => 'zip', 'message' => 'No se pudo abrir el ZIP descargado'];
    }
    $zip->extractTo($tmpBase);
    $zip->close();

    $folders = glob($tmpBase . '/*', GLOB_ONLYDIR);
    if (!$folders) {
        return ['success' => false, 'method' => 'zip', 'message' => 'El ZIP no contiene carpeta de proyecto'];
    }

    recursive_copy($folders[0], $sitePath);
    @unlink($zipFile);
    return ['success' => true, 'method' => 'zip', 'message' => 'Archivos copiados desde ZIP de GitHub'];
}

deploy_log('===== INICIO DE ACTUALIZACIÓN =====');
deploy_log('IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
deploy_log('SITE_PATH: ' . $sitePath);

if (!is_dir($sitePath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'SITE_PATH no existe: ' . $sitePath], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if (is_dir($sitePath . '/.git')) {
    $result = update_with_git($sitePath, $branch);
} else {
    $result = update_with_zip($sitePath, $repoZipUrl);
}

@chmod($sitePath . '/assets', 0755);
@chmod($sitePath . '/scripts', 0755);
@chmod($sitePath . '/uploads', 0775);
@chmod($sitePath . '/data', 0775);

// Escribir data/version.json con los datos de la última versión instalada.
// Es la fuente de datos que consume el panel admin (botón "Última versión" + modal).
if (!empty($result['success'])) {
    $versionInfo = [
        'deployed_at' => date('Y-m-d H:i:s'),
        'method'      => $result['method'] ?? 'unknown',
        'branch'      => $branch,
    ];

    // Si hay git disponible, agregar datos del último commit.
    if (is_dir($sitePath . '/.git') && function_exists('shell_exec')) {
        $gitLog = @shell_exec(
            'cd ' . escapeshellarg($sitePath) .
            " && git log -1 --date=format:'%Y-%m-%d %H:%M' --format='%h|%cd|%an|%s' 2>/dev/null"
        );
        if ($gitLog) {
            $parts = explode('|', trim($gitLog), 4);
            if (count($parts) === 4) {
                $versionInfo['commit_hash'] = $parts[0];
                $versionInfo['commit_date'] = $parts[1];
                $versionInfo['author']      = $parts[2];
                $versionInfo['subject']     = $parts[3];
            }
        }
    }

    $dataDir = $sitePath . '/data';
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0775, true);
    }
    @file_put_contents(
        $dataDir . '/version.json',
        json_encode($versionInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
    deploy_log('VERSION.JSON: ' . json_encode($versionInfo));
}

deploy_log('RESULT: ' . json_encode($result));
deploy_log('===== FIN DE ACTUALIZACIÓN =====');

http_response_code($result['success'] ? 200 : 500);
echo json_encode([
    'success' => $result['success'],
    'message' => $result['success'] ? 'Sitio actualizado correctamente desde GitHub' : 'No se pudo actualizar el sitio',
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $result['method'] ?? 'unknown',
    'details' => $result
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
