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
    <title>Redefinir acesso - HelpDesk Facil</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --hf-reset-primary: #0d6efd;
            --hf-reset-primary-rgb: 13, 110, 253;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(circle at 16% 0%, rgba(var(--hf-reset-primary-rgb), .14), transparent 28rem),
                radial-gradient(circle at 85% 88%, rgba(16, 185, 129, .08), transparent 22rem),
                linear-gradient(180deg, #f7f9fc, #eef3f8);
            color: #0f172a;
        }

        .hf-reset-shell {
            width: 100%;
            max-width: 520px;
            padding: 1.15rem;
        }

        .hf-reset-card {
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 1.1rem;
            background: rgba(255, 255, 255, .95);
            box-shadow: 0 18px 46px rgba(15, 23, 42, .10);
            overflow: hidden;
        }

        .hf-reset-head {
            padding: 1.2rem 1.2rem .95rem;
            border-bottom: 1px solid rgba(226, 232, 240, .9);
            background: linear-gradient(180deg, rgba(248, 250, 252, .95), rgba(255, 255, 255, .95));
        }

        .hf-reset-head h1 {
            margin: 0;
            font-size: 1.22rem;
            font-weight: 880;
        }

        .hf-reset-head p {
            margin: .35rem 0 0;
            color: #64748b;
        }

        .hf-reset-body {
            padding: 1.1rem 1.2rem 1.2rem;
        }

        .hf-reset-body .form-label {
            margin-bottom: .34rem;
            color: #64748b;
            font-size: .76rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .hf-reset-input {
            position: relative;
        }

        .hf-reset-input i {
            position: absolute;
            left: .82rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }

        .hf-reset-input .form-control {
            min-height: 44px;
            padding-left: 2.35rem;
            border-radius: .72rem;
            border-color: #dbe3ee;
            background: #f8fafc;
        }

        .hf-reset-input .form-control:focus {
            border-color: rgba(var(--hf-reset-primary-rgb), .56);
            box-shadow: 0 0 0 .2rem rgba(var(--hf-reset-primary-rgb), .12);
            background: #fff;
        }

        .hf-reset-btn {
            min-height: 44px;
            border-radius: .72rem;
            font-weight: 860;
        }

        .hf-reset-link {
            margin-top: 1rem;
            text-align: center;
        }

        .hf-reset-note {
            margin-top: .35rem;
            color: #64748b;
            font-size: .82rem;
        }

        @media (max-width: 575.98px) {
            body {
                align-items: flex-start;
            }

            .hf-reset-shell {
                padding: .9rem;
            }

            .hf-reset-head,
            .hf-reset-body {
                padding-left: .95rem;
                padding-right: .95rem;
            }
        }
    </style>
</head>
<body>
    <main class="hf-reset-shell">
        <div class="hf-reset-card">
            <div class="hf-reset-head">
                <h1>Redefinir senha</h1>
                <p>Defina uma nova senha para concluir a recuperacao da sua conta.</p>
            </div>

            <div class="hf-reset-body">
                <?php if ($feedback !== ''): ?>
                    <div class="alert <?= $isSuccess ? 'alert-success' : 'alert-warning' ?>">
                        <i class="bi <?= $isSuccess ? 'bi-check2-circle' : 'bi-exclamation-triangle' ?> me-2"></i>
                        <?= htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <?php if (!$tokenValido && !$isSuccess): ?>
                    <div class="alert alert-warning mb-3">Link de redefinicao invalido, expirado ou ja utilizado.</div>
                    <a class="btn btn-outline-primary w-100 hf-reset-btn" href="/esqueci_senha.php">Solicitar novo link</a>
                <?php elseif ($isSuccess): ?>
                    <a class="btn btn-primary w-100 hf-reset-btn" href="/login.php">Ir para login</a>
                <?php else: ?>
                    <form method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label for="nova_senha" class="form-label">Nova senha</label>
                            <div class="hf-reset-input">
                                <i class="bi bi-lock"></i>
                                <input id="nova_senha" name="nova_senha" type="password" class="form-control" minlength="8" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="confirmar_senha" class="form-label">Confirmar nova senha</label>
                            <div class="hf-reset-input">
                                <i class="bi bi-shield-lock"></i>
                                <input id="confirmar_senha" name="confirmar_senha" type="password" class="form-control" minlength="8" required>
                            </div>
                            <div class="hf-reset-note">Use no minimo 8 caracteres para manter sua conta segura.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 hf-reset-btn">Salvar nova senha</button>
                    </form>
                <?php endif; ?>

                <div class="hf-reset-link">
                    <a href="/login.php">Voltar para login</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
