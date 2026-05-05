<?php
// os_foto_delete.php — Exclusão assíncrona de fotos da OS

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php';
requireLogin();

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function jsonOut($ok, $msg = '', $extra = [])
{
    echo json_encode(array_merge([
        'ok' => (bool)$ok,
        'msg' => (string)$msg,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeRelPath($path)
{
    $path = str_replace('\\', '/', (string)$path);
    $path = ltrim($path, '/');
    $path = preg_replace('#/+#', '/', $path);
    return $path;
}

function isSafeOsUploadPath($relPath, $tenantId, $osId)
{
    $relPath = normalizeRelPath($relPath);
    $tenantId = (int)$tenantId;
    $osId = (int)$osId;

    $basePattern = '#^uploads/os/' . preg_quote((string)$tenantId, '#') . '/\d{4}/\d{2}/' . preg_quote((string)$osId, '#') . '/#';
    if (!preg_match($basePattern, $relPath)) {
        return false;
    }

    if (strpos($relPath, '../') !== false || strpos($relPath, '..\\') !== false) {
        return false;
    }

    return true;
}

function deleteUploadFileIfSafe($relPath, $tenantId, $osId)
{
    $relPath = normalizeRelPath($relPath);
    if ($relPath === '' || !isSafeOsUploadPath($relPath, $tenantId, $osId)) {
        return false;
    }

    $fullPath = __DIR__ . '/' . $relPath;
    $realFile = realpath($fullPath);
    if ($realFile === false || !is_file($realFile)) {
        return true;
    }

    $realUploads = realpath(__DIR__ . '/uploads/os/' . (int)$tenantId);
    if ($realUploads === false) {
        return false;
    }

    $realUploads = rtrim($realUploads, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strncmp($realFile, $realUploads, strlen($realUploads)) !== 0) {
        return false;
    }

    return @unlink($realFile);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonOut(false, 'Método inválido.');
}

$pdo = db();
$tid = function_exists('tenantId') ? (int)tenantId() : (int)($_SESSION['tenant_id'] ?? 0);
$fotoId = (int)($_POST['id'] ?? 0);

if ($tid <= 0) {
    http_response_code(400);
    jsonOut(false, 'Tenant inválido.');
}

if ($fotoId <= 0) {
    http_response_code(400);
    jsonOut(false, 'Foto inválida.');
}

try {
    try {
        $st = $pdo->prepare("
            SELECT f.id, f.os_id, f.caminho, f.thumb, f.original_nome
              FROM hf_os_fotos f
              JOIN hf_os o ON o.id = f.os_id
             WHERE f.id = :id
               AND o.tenant_id = :t
               AND o.deleted_at IS NULL
               AND (f.tenant_id = :t OR f.tenant_id IS NULL)
             LIMIT 1
        ");
        $st->execute([':id' => $fotoId, ':t' => $tid]);
    } catch (Throwable $e) {
        $st = $pdo->prepare("
            SELECT f.id, f.os_id, f.caminho, f.thumb, f.original_nome
              FROM hf_os_fotos f
              JOIN hf_os o ON o.id = f.os_id
             WHERE f.id = :id
               AND o.tenant_id = :t
               AND o.deleted_at IS NULL
             LIMIT 1
        ");
        $st->execute([':id' => $fotoId, ':t' => $tid]);
    }

    $foto = $st->fetch(PDO::FETCH_ASSOC);
    if (!$foto) {
        http_response_code(404);
        jsonOut(false, 'Foto não encontrada.');
    }

    $osId = (int)$foto['os_id'];
    $caminho = normalizeRelPath($foto['caminho'] ?? '');
    $thumb = normalizeRelPath($foto['thumb'] ?? '');

    if (!isSafeOsUploadPath($caminho, $tid, $osId)) {
        http_response_code(400);
        jsonOut(false, 'Caminho da foto inválido.');
    }

    if ($thumb !== '' && !isSafeOsUploadPath($thumb, $tid, $osId)) {
        http_response_code(400);
        jsonOut(false, 'Caminho da miniatura inválido.');
    }

    $pdo->beginTransaction();
    $del = $pdo->prepare("DELETE FROM hf_os_fotos WHERE id = :id");
    $del->execute([':id' => $fotoId]);
    $pdo->commit();

    $fileOk = deleteUploadFileIfSafe($caminho, $tid, $osId);
    $thumbOk = true;
    if ($thumb !== '' && $thumb !== $caminho) {
        $thumbOk = deleteUploadFileIfSafe($thumb, $tid, $osId);
    }

    if (!$fileOk || !$thumbOk) {
        jsonOut(true, 'Foto removida do registro. Não foi possível apagar todos os arquivos físicos.');
    }

    jsonOut(true, 'Foto excluída.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    jsonOut(false, 'Erro ao excluir foto.');
}
