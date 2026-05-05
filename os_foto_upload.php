<?php
// os_foto_upload.php — Upload assíncrono de fotos da OS

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

function makeImageFromUpload($path)
{
    $info = @getimagesize($path);
    if (!$info || empty($info['mime'])) {
        return null;
    }

    switch ($info['mime']) {
        case 'image/jpeg':
            return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : null;
        case 'image/png':
            return function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : null;
        case 'image/gif':
            return function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : null;
        default:
            return null;
    }
}

function saveJpegResized($srcPath, $dstPath, $maxW = 1600, $maxH = 1600, $quality = 85)
{
    $img = makeImageFromUpload($srcPath);
    if (!$img) {
        return false;
    }

    $w = imagesx($img);
    $h = imagesy($img);
    $ratio = min($maxW / max(1, $w), $maxH / max(1, $h), 1);
    $nw = (int)floor($w * $ratio);
    $nh = (int)floor($h * $ratio);

    $canvas = imagecreatetruecolor($nw, $nh);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);
    imagecopyresampled($canvas, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $ok = imagejpeg($canvas, $dstPath, $quality);
    imagedestroy($canvas);
    imagedestroy($img);

    return $ok;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonOut(false, 'Método inválido.');
}

if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
    http_response_code(500);
    jsonOut(false, 'Biblioteca de imagens indisponível.');
}

$pdo = db();
$tid = function_exists('tenantId') ? (int)tenantId() : (int)($_SESSION['tenant_id'] ?? 0);
$osId = (int)($_POST['os_id'] ?? 0);

if ($tid <= 0) {
    http_response_code(400);
    jsonOut(false, 'Tenant inválido.');
}

if ($osId <= 0) {
    http_response_code(400);
    jsonOut(false, 'OS inválida.');
}

try {
    $st = $pdo->prepare("
        SELECT id
          FROM hf_os
         WHERE id = :id
           AND tenant_id = :t
           AND deleted_at IS NULL
         LIMIT 1
    ");
    $st->execute([':id' => $osId, ':t' => $tid]);
    if (!$st->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        jsonOut(false, 'OS não encontrada.');
    }
} catch (Throwable $e) {
    http_response_code(500);
    jsonOut(false, 'Erro ao validar OS.');
}

if (empty($_FILES['fotos']) || !isset($_FILES['fotos']['tmp_name']) || !is_array($_FILES['fotos']['tmp_name'])) {
    http_response_code(400);
    jsonOut(false, 'Nenhuma foto enviada.');
}

$maxFiles = 10;
$maxBytes = 2 * 1024 * 1024;
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];

$baseDir = __DIR__ . '/uploads/os/' . $tid . '/' . date('Y') . '/' . date('m') . '/' . $osId . '/';
$thumbDir = $baseDir . 'thumbs/';

if (!is_dir($thumbDir) && !@mkdir($thumbDir, 0775, true) && !is_dir($thumbDir)) {
    http_response_code(500);
    jsonOut(false, 'Não foi possível criar a pasta de upload.');
}

try {
    $insFotoTenant = $pdo->prepare("
        INSERT INTO hf_os_fotos (tenant_id, os_id, caminho, thumb, original_nome, created_at)
        VALUES (:t, :os, :c, :th, :n, NOW())
    ");
    $insFotoNoTenant = $pdo->prepare("
        INSERT INTO hf_os_fotos (os_id, caminho, thumb, original_nome, created_at)
        VALUES (:os, :c, :th, :n, NOW())
    ");
} catch (Throwable $e) {
    http_response_code(500);
    jsonOut(false, 'Erro ao preparar gravação das fotos.');
}

$fotos = [];
$errors = [];
$count = min(count($_FILES['fotos']['tmp_name']), $maxFiles);

for ($i = 0; $i < $count; $i++) {
    $tmp = $_FILES['fotos']['tmp_name'][$i] ?? null;
    $name = (string)($_FILES['fotos']['name'][$i] ?? '');
    $size = (int)($_FILES['fotos']['size'][$i] ?? 0);
    $error = (int)($_FILES['fotos']['error'][$i] ?? UPLOAD_ERR_NO_FILE);

    if ($error !== UPLOAD_ERR_OK || !$tmp || !is_uploaded_file($tmp)) {
        $errors[] = $name !== '' ? $name : 'Arquivo inválido';
        continue;
    }

    if ($size <= 0 || $size > $maxBytes) {
        $errors[] = $name !== '' ? $name : 'Arquivo acima do limite';
        continue;
    }

    $info = @getimagesize($tmp);
    $mime = $info['mime'] ?? '';
    if (!$info || !in_array($mime, $allowedMimes, true)) {
        $errors[] = $name !== '' ? $name : 'Tipo inválido';
        continue;
    }

    $basename = date('Ymd_His') . '_' . substr(md5($name . microtime(true) . random_int(1000, 9999)), 0, 8) . '.jpg';
    $dest = $baseDir . $basename;
    $thumb = $thumbDir . $basename;

    if (!saveJpegResized($tmp, $dest, 1600, 1600, 85)) {
        $errors[] = $name !== '' ? $name : 'Falha ao salvar imagem';
        continue;
    }

    saveJpegResized($dest, $thumb, 320, 320, 80);

    $cRel = 'uploads/os/' . $tid . '/' . date('Y') . '/' . date('m') . '/' . $osId . '/' . $basename;
    $tRel = 'uploads/os/' . $tid . '/' . date('Y') . '/' . date('m') . '/' . $osId . '/thumbs/' . $basename;

    try {
        try {
            $insFotoTenant->execute([
                ':t' => $tid,
                ':os' => $osId,
                ':c' => $cRel,
                ':th' => $tRel,
                ':n' => $name,
            ]);
        } catch (Throwable $e) {
            $insFotoNoTenant->execute([
                ':os' => $osId,
                ':c' => $cRel,
                ':th' => $tRel,
                ':n' => $name,
            ]);
        }

        $fotoId = (int)$pdo->lastInsertId();
        $fotos[] = [
            'id' => $fotoId,
            'caminho' => $cRel,
            'thumb' => $tRel,
            'original_nome' => $name,
        ];
    } catch (Throwable $e) {
        @unlink($dest);
        @unlink($thumb);
        $errors[] = $name !== '' ? $name : 'Falha ao gravar foto';
    }
}

if (empty($fotos)) {
    http_response_code(400);
    jsonOut(false, 'Nenhuma foto válida foi enviada.');
}

$msg = 'Upload concluído.';
if (!empty($errors)) {
    $msg .= ' Alguns arquivos foram ignorados.';
}

jsonOut(true, $msg, [
    'fotos' => $fotos,
    'erros' => $errors,
]);
