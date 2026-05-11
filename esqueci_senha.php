<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__.'/_password_reset.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$feedback = '';
$isSuccess = false;
$resetLink = '';
$expiresLabel = '';

$empresa = trim((string)($_POST['empresa'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $postToken = $_POST['csrf_token'] ?? '';

    if ($sessionToken === '' || $postToken === '' || !hash_equals($sessionToken, $postToken)) {
        $feedback = 'Nao foi possivel validar a solicitacao. Tente novamente.';
    } elseif ($empresa === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $feedback = 'Informe o codigo da empresa e um e-mail valido.';
    } else {
        $isSuccess = true;
        $feedback = 'Se os dados informados estiverem corretos, a redefinicao de senha foi iniciada.';

        try {
            $user = hfResetFindUserByTenantAndEmail($empresa, $email);
            if ($user && !empty($user['id'])) {
                $request = hfResetCreateRequest((int)$user['id'], 60);
                $resetLink = hfResetBuildAbsoluteUrl('/redefinir_senha.php', [
                    'token' => (string)$request['raw_token'],
                ]);

                try {
                    $expiresLabel = (new DateTime((string)$request['expires_at']))->format('d/m/Y H:i');
                } catch (Exception $e) {
                    $expiresLabel = '';
                }
            }
        } catch (Exception $e) {
            error_log('esqueci_senha.php: '.$e->getMessage());
        }
    }
}
?><!doctype html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Esqueci minha senha - HelpDesk Facil</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5" style="max-width: 560px;">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-2">Esqueci minha senha</h1>
                <p class="text-secondary mb-4">Informe o codigo da empresa e o e-mail para iniciar a redefinicao de senha.</p>

                <?php if ($feedback !== ''): ?>
                    <div class="alert <?= $isSuccess ? 'alert-success' : 'alert-warning' ?>"><?= htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <?php if ($resetLink !== '' && defined('APP_DEBUG') && APP_DEBUG): ?>
                    <div class="alert alert-info">
                        <div class="fw-semibold mb-2">Ambiente sem envio de e-mail (APP_DEBUG):</div>
                        <p class="mb-2">Use este link temporario para concluir a redefinicao.</p>
                        <div class="small text-break"><a href="<?= htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') ?></a></div>
                        <?php if ($expiresLabel !== ''): ?>
                            <div class="small mt-2 text-secondary">Valido ate: <?= htmlspecialchars($expiresLabel, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">
                        <label for="empresa" class="form-label">Codigo da empresa</label>
                        <input id="empresa" name="empresa" class="form-control" value="<?= htmlspecialchars($empresa, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input id="email" name="email" type="email" class="form-control" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Continuar</button>
                </form>

                <div class="mt-3 text-center">
                    <a href="/login.php">Voltar para login</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
