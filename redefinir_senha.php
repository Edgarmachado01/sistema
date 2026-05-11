<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__.'/_password_reset.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$feedback = '';
$isSuccess = false;

$novaSenha = (string)($_POST['nova_senha'] ?? '');
$confirmarSenha = (string)($_POST['confirmar_senha'] ?? '');

$requestData = hfResetFindValidRequestByToken($token);
$tokenValido = $requestData !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $postToken = $_POST['csrf_token'] ?? '';

    if ($sessionToken === '' || $postToken === '' || !hash_equals($sessionToken, $postToken)) {
        $feedback = 'Sessao expirada. Recarregue a pagina e tente novamente.';
    } elseif (!$tokenValido) {
        $feedback = 'Link de redefinicao invalido, expirado ou ja utilizado.';
    } elseif ($novaSenha === '' || $confirmarSenha === '') {
        $feedback = 'Informe e confirme a nova senha.';
    } elseif (strlen($novaSenha) < 8) {
        $feedback = 'A nova senha deve ter no minimo 8 caracteres.';
    } elseif (!hash_equals($novaSenha, $confirmarSenha)) {
        $feedback = 'A confirmacao da senha nao confere.';
    } else {
        try {
            $updated = hfResetConsumeTokenAndUpdatePassword($token, $novaSenha);
            if ($updated) {
                $isSuccess = true;
                $feedback = 'Senha redefinida com sucesso. Voce ja pode fazer login.';
                $tokenValido = false;
                $requestData = null;
            } else {
                $feedback = 'Link de redefinicao invalido, expirado ou ja utilizado.';
            }
        } catch (Exception $e) {
            error_log('redefinir_senha.php: '.$e->getMessage());
            $feedback = 'Nao foi possivel redefinir sua senha agora. Tente novamente.';
        }
    }
}
?><!doctype html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Redefinir senha - HelpDesk Facil</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5" style="max-width: 560px;">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-2">Redefinir senha</h1>
                <p class="text-secondary mb-4">Defina sua nova senha de acesso.</p>

                <?php if ($feedback !== ''): ?>
                    <div class="alert <?= $isSuccess ? 'alert-success' : 'alert-warning' ?>"><?= htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <?php if (!$tokenValido && !$isSuccess): ?>
                    <div class="alert alert-warning">Link de redefinicao invalido, expirado ou ja utilizado.</div>
                    <a class="btn btn-outline-primary w-100" href="/esqueci_senha.php">Solicitar novo link</a>
                <?php elseif ($isSuccess): ?>
                    <a class="btn btn-primary w-100" href="/login.php">Ir para login</a>
                <?php else: ?>
                    <form method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label for="nova_senha" class="form-label">Nova senha</label>
                            <input id="nova_senha" name="nova_senha" type="password" class="form-control" minlength="8" required>
                        </div>

                        <div class="mb-3">
                            <label for="confirmar_senha" class="form-label">Confirmar nova senha</label>
                            <input id="confirmar_senha" name="confirmar_senha" type="password" class="form-control" minlength="8" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Salvar nova senha</button>
                    </form>
                <?php endif; ?>

                <div class="mt-3 text-center">
                    <a href="/login.php">Voltar para login</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
