<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__.'/_password_reset.php';
require_once __DIR__.'/_email.php';

if (!function_exists('hfRecoverMaskEmail')) {
    function hfRecoverMaskEmail($email)
    {
        $email = strtolower(trim((string)$email));
        if ($email === '' || strpos($email, '@') === false) {
            return $email;
        }

        [$name, $domain] = explode('@', $email, 2);
        $nameLen = strlen($name);
        if ($nameLen <= 2) {
            $maskedName = substr($name, 0, 1).'*';
        } else {
            $maskedName = substr($name, 0, 2).str_repeat('*', max(1, $nameLen - 2));
        }

        return $maskedName.'@'.$domain;
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = (string)$_SESSION['csrf_token'];
$feedback = '';
$isSuccess = false;

$senhaEmpresa = trim((string)($_POST['empresa'] ?? ''));
$senhaEmail = strtolower(trim((string)($_POST['email'] ?? '')));
$codigoDocumento = hfResetNormalizeDocumento($_POST['documento'] ?? '');
$codigoEmail = strtolower(trim((string)($_POST['codigo_email'] ?? '')));
$activeTab = trim((string)($_POST['acao'] ?? 'recover_password'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    $postToken = (string)($_POST['csrf_token'] ?? '');
    $acao = trim((string)($_POST['acao'] ?? 'recover_password'));
    $activeTab = $acao === 'recover_company_code' ? 'recover_company_code' : 'recover_password';

    if ($sessionToken === '' || $postToken === '' || !hash_equals($sessionToken, $postToken)) {
        $feedback = 'Nao foi possivel validar a solicitacao. Tente novamente.';
    } elseif ($activeTab === 'recover_company_code') {
        if (!hfResetDocumentoValido($codigoDocumento) || !filter_var($codigoEmail, FILTER_VALIDATE_EMAIL)) {
            $feedback = 'Informe CPF/CNPJ e e-mail validos.';
        } else {
            $isSuccess = true;
            $feedback = 'E-mail enviado com sucesso para '.hfRecoverMaskEmail($codigoEmail).'. Se os dados estiverem corretos, voce recebera as orientacoes de acesso.';

            try {
                $tenant = hfResetFindTenantByDocumentoAndAdminEmail($codigoDocumento, $codigoEmail);
                if ($tenant) {
                    $tenantCode = trim((string)($tenant['slug'] ?? ''));
                    $tenantName = trim((string)($tenant['name'] ?? ''));
                    $loginUrl = hfResetBuildAbsoluteUrl('/login.php');

                    $mailOk = hfSendTenantCodeRecoveryEmail([
                        'email' => $codigoEmail,
                        'tenant_code' => $tenantCode,
                        'tenant_name' => $tenantName,
                        'login_url' => $loginUrl,
                    ]);

                    if (!$mailOk) {
                        error_log('esqueci_senha.php recover_company_code: falha ao enviar e-mail para '.$codigoEmail);
                    }
                }
            } catch (Exception $e) {
                error_log('esqueci_senha.php recover_company_code: '.$e->getMessage());
            }
        }
    } else {
        if ($senhaEmpresa === '' || !filter_var($senhaEmail, FILTER_VALIDATE_EMAIL)) {
            $feedback = 'Informe o codigo da empresa e um e-mail valido.';
        } else {
            $isSuccess = true;
            $feedback = 'E-mail enviado com sucesso para '.hfRecoverMaskEmail($senhaEmail).'. Se os dados estiverem corretos, voce recebera o link de redefinicao.';

            try {
                $user = hfResetFindUserByTenantAndEmail($senhaEmpresa, $senhaEmail);
                if ($user && !empty($user['id'])) {
                    $request = hfResetCreateRequest((int)$user['id'], 60);
                    $resetLink = hfResetBuildAbsoluteUrl('/redefinir_senha.php', [
                        'token' => (string)$request['raw_token'],
                    ]);

                    $expiresLabel = '';
                    try {
                        $expiresLabel = (new DateTime((string)$request['expires_at']))->format('d/m/Y H:i');
                    } catch (Exception $e) {
                        $expiresLabel = '';
                    }

                    $mailOk = hfSendPasswordResetEmail([
                        'email' => $senhaEmail,
                        'tenant_code' => $senhaEmpresa,
                        'reset_link' => $resetLink,
                        'expires_label' => $expiresLabel,
                    ]);

                    if (!$mailOk) {
                        error_log('esqueci_senha.php recover_password: falha ao enviar e-mail para '.$senhaEmail);
                    }
                }
            } catch (Exception $e) {
                error_log('esqueci_senha.php recover_password: '.$e->getMessage());
            }
        }
    }
}
?><!doctype html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Recuperar acesso - HelpDesk Facil</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --hf-rec-primary: #0d6efd;
            --hf-rec-primary-rgb: 13, 110, 253;
        }

        body {
            min-height: 100vh;
            margin: 0;
            background:
                radial-gradient(circle at 12% 0%, rgba(var(--hf-rec-primary-rgb), .12), transparent 30rem),
                radial-gradient(circle at 90% 90%, rgba(16, 185, 129, .10), transparent 24rem),
                linear-gradient(180deg, #f7f9fc, #eef3f8);
            color: #0f172a;
        }

        .hf-rec-shell {
            max-width: 980px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .hf-rec-hero {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 1.15rem;
            background: rgba(255, 255, 255, .95);
            box-shadow: 0 18px 46px rgba(15, 23, 42, .10);
            padding: 1.2rem 1.25rem;
            margin-bottom: 1rem;
        }

        .hf-rec-hero h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 900;
            color: #0f172a;
        }

        .hf-rec-hero p {
            margin: .35rem 0 0;
            color: #64748b;
        }

        .hf-rec-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .hf-rec-card {
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 1rem;
            background: rgba(255, 255, 255, .94);
            box-shadow: 0 14px 36px rgba(15, 23, 42, .08);
            overflow: hidden;
        }

        .hf-rec-head {
            padding: 1rem 1rem .8rem;
            border-bottom: 1px solid rgba(226, 232, 240, .92);
            background: linear-gradient(180deg, rgba(248, 250, 252, .95), rgba(255, 255, 255, .95));
        }

        .hf-rec-head h2 {
            margin: 0;
            font-size: 1.08rem;
            font-weight: 850;
            color: #0f172a;
        }

        .hf-rec-head p {
            margin: .35rem 0 0;
            color: #64748b;
            font-size: .9rem;
        }

        .hf-rec-body {
            padding: 1rem;
        }

        .hf-rec-body .form-label {
            margin-bottom: .35rem;
            color: #64748b;
            font-size: .75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .hf-rec-input {
            position: relative;
        }

        .hf-rec-input > i {
            position: absolute;
            left: .82rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }

        .hf-rec-input .form-control {
            min-height: 44px;
            padding-left: 2.35rem;
            border-radius: .7rem;
            border-color: #dbe3ee;
            background: #f8fafc;
        }

        .hf-rec-input .form-control:focus {
            border-color: rgba(var(--hf-rec-primary-rgb), .58);
            box-shadow: 0 0 0 .2rem rgba(var(--hf-rec-primary-rgb), .12);
            background: #fff;
        }

        .hf-rec-help {
            margin-top: .32rem;
            color: #64748b;
            font-size: .8rem;
        }

        .hf-rec-btn {
            min-height: 44px;
            border-radius: .7rem;
            font-weight: 850;
        }

        .hf-rec-feedback {
            border: 1px solid rgba(148, 163, 184, .25);
            border-radius: .95rem;
            margin-bottom: 1rem;
        }

        .hf-rec-link {
            margin-top: 1rem;
            text-align: center;
        }

        @media (max-width: 991.98px) {
            .hf-rec-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="hf-rec-shell">
        <section class="hf-rec-hero">
            <h1>Recuperar acesso</h1>
            <p>Escolha uma opcao para recuperar sua conta sem expor informacoes sensiveis.</p>
        </section>

        <?php if ($feedback !== ''): ?>
            <div class="alert hf-rec-feedback <?= $isSuccess ? 'alert-success' : 'alert-warning' ?>">
                <i class="bi <?= $isSuccess ? 'bi-check2-circle' : 'bi-exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="hf-rec-grid">
            <article class="hf-rec-card">
                <div class="hf-rec-head">
                    <h2>Recuperar senha</h2>
                    <p>Informe codigo da empresa e e-mail de acesso.</p>
                </div>
                <div class="hf-rec-body">
                    <form method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="acao" value="recover_password">

                        <div class="mb-3">
                            <label for="empresa" class="form-label">Codigo da empresa</label>
                            <div class="hf-rec-input">
                                <i class="bi bi-building"></i>
                                <input id="empresa" name="empresa" class="form-control" value="<?= htmlspecialchars($senhaEmpresa, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ex.: minha-empresa" required>
                            </div>
                            <div class="hf-rec-help">Identificador da empresa usado no login.</div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail</label>
                            <div class="hf-rec-input">
                                <i class="bi bi-envelope"></i>
                                <input id="email" name="email" type="email" class="form-control" value="<?= htmlspecialchars($senhaEmail, ENT_QUOTES, 'UTF-8') ?>" placeholder="voce@empresa.com" required>
                            </div>
                            <div class="hf-rec-help">De preferencia, o e-mail do usuario administrador.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 hf-rec-btn">Continuar recuperacao</button>
                    </form>
                </div>
            </article>

            <article class="hf-rec-card">
                <div class="hf-rec-head">
                    <h2>Esqueci codigo da empresa</h2>
                    <p>Recupere o codigo com CPF/CNPJ e e-mail da conta admin.</p>
                </div>
                <div class="hf-rec-body">
                    <form method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="acao" value="recover_company_code">

                        <div class="mb-3">
                            <label for="documento" class="form-label">CPF/CNPJ</label>
                            <div class="hf-rec-input">
                                <i class="bi bi-card-text"></i>
                                <input id="documento" name="documento" class="form-control" maxlength="20" value="<?= htmlspecialchars($codigoDocumento, ENT_QUOTES, 'UTF-8') ?>" placeholder="Somente numeros" required>
                            </div>
                            <div class="hf-rec-help">Use o documento informado no cadastro da empresa.</div>
                        </div>

                        <div class="mb-3">
                            <label for="codigo_email" class="form-label">E-mail admin</label>
                            <div class="hf-rec-input">
                                <i class="bi bi-envelope-at"></i>
                                <input id="codigo_email" name="codigo_email" type="email" class="form-control" value="<?= htmlspecialchars($codigoEmail, ENT_QUOTES, 'UTF-8') ?>" placeholder="admin@empresa.com" required>
                            </div>
                            <div class="hf-rec-help">Mesmo e-mail usado para administrar a empresa.</div>
                        </div>

                        <button type="submit" class="btn btn-outline-primary w-100 hf-rec-btn">Recuperar codigo</button>
                    </form>
                </div>
            </article>
        </section>

        <div class="hf-rec-link">
            <a href="/login.php">Voltar para login</a>
        </div>
    </main>
</body>
</html>
