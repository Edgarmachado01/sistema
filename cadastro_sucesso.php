<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$success = $_SESSION['HF_SIGNUP_SUCCESS'] ?? null;
if (!$success || !is_array($success)) {
    header('Location: /cadastro.php');
    exit;
}

unset($_SESSION['HF_SIGNUP_SUCCESS']);

$planos = [
    'basico' => 'Basico',
    'profissional' => 'Profissional',
    'premium' => 'Premium',
];

$empresaNome = trim((string)($success['empresa_nome'] ?? ''));
$tenantCode = trim((string)($success['tenant_code'] ?? ($success['empresa_slug'] ?? '')));
$email = trim((string)($success['email'] ?? ''));
$documento = trim((string)($success['documento'] ?? ''));
$planoKey = strtolower(trim((string)($success['plano'] ?? 'profissional')));
$planoNome = trim((string)($success['plano_nome'] ?? ($planos[$planoKey] ?? 'Profissional')));
$trialDays = (int)($success['trial_days'] ?? 0);
$trialEndAt = trim((string)($success['trial_end_at'] ?? ''));
$loginUrl = trim((string)($success['login_url'] ?? '/login.php'));

if ($loginUrl === '') {
    $loginUrl = '/login.php';
}

$trialResumo = $trialDays > 0 ? 'Teste gratis de '.$trialDays.' dias' : 'Teste gratis ativo';
$trialEndLabel = '';

if ($trialEndAt !== '') {
    try {
        $trialEndDate = new DateTime($trialEndAt);
        $trialEndLabel = $trialEndDate->format('d/m/Y');
    } catch (Exception $e) {
        $trialEndLabel = '';
    }
}

if ($trialEndLabel !== '') {
    $trialResumo .= ' (ate '.$trialEndLabel.')';
}

$siteTitle = 'Cadastro concluido - HelpDesk Facil';
$siteDescription = 'Seu ambiente foi criado com sucesso. Use os dados abaixo para entrar no login.';
$siteBodyClass = 'hf-signup-success-page';

include __DIR__.'/_site_start.php';
?>
<section class="py-5">
    <div class="container" style="max-width: 860px;">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
                    <div>
                        <span class="badge text-bg-success mb-2">Cadastro concluido</span>
                        <h1 class="h3 mb-2">Seu ambiente esta pronto para acesso</h1>
                        <p class="text-secondary mb-0">Guarde estes dados para o primeiro login da empresa.</p>
                    </div>
                    <a class="btn btn-primary" href="/login.php">
                        <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>
                        Ir para login
                    </a>
                </div>

                <div class="alert alert-light border mb-4">
                    <div class="small text-uppercase text-secondary fw-semibold mb-1">Empresa</div>
                    <div class="fw-semibold"><?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <tbody>
                            <tr>
                                <th scope="row" class="text-secondary fw-semibold" style="width: 220px;">URL de acesso</th>
                                <td>
                                    <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?></a>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" class="text-secondary fw-semibold">Codigo da empresa</th>
                                <td>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <code id="tenantCodeValue" class="fs-6"><?= htmlspecialchars($tenantCode, ENT_QUOTES, 'UTF-8') ?></code>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="copyTenantCodeBtn" data-tenant-code="<?= htmlspecialchars($tenantCode, ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="bi bi-clipboard me-1" aria-hidden="true"></i>
                                            Copiar codigo
                                        </button>
                                        <span id="copyTenantCodeFeedback" class="small text-success d-none">Codigo copiado</span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" class="text-secondary fw-semibold">E-mail do acesso</th>
                                <td><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <?php if ($documento !== ''): ?>
                                <tr>
                                    <th scope="row" class="text-secondary fw-semibold">CPF/CNPJ</th>
                                    <td><?= htmlspecialchars($documento, ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <th scope="row" class="text-secondary fw-semibold">Plano e trial</th>
                                <td><?= htmlspecialchars($planoNome.' - '.$trialResumo, ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <h2 class="h6 fw-bold mb-2">Como entrar</h2>
                    <p class="text-secondary mb-0">Na tela de login, informe o codigo da empresa, o e-mail e a senha definidos no cadastro.</p>
                    <p class="text-secondary mb-0 mt-2">Em breve, o codigo da empresa tambem sera enviado por e-mail automaticamente. Nesta fase, guarde este codigo em local seguro.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    var copyBtn = document.getElementById('copyTenantCodeBtn');
    var feedback = document.getElementById('copyTenantCodeFeedback');
    if (!copyBtn || !feedback) {
        return;
    }

    copyBtn.addEventListener('click', function () {
        var tenantCode = copyBtn.getAttribute('data-tenant-code') || '';
        if (!tenantCode) {
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(tenantCode).then(showFeedback).catch(function () {
                fallbackCopy(tenantCode);
            });
            return;
        }

        fallbackCopy(tenantCode);
    });

    function fallbackCopy(text) {
        var textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.setAttribute('readonly', 'readonly');
        textArea.style.position = 'absolute';
        textArea.style.left = '-9999px';
        document.body.appendChild(textArea);
        textArea.select();

        try {
            document.execCommand('copy');
            showFeedback();
        } catch (e) {
            // Sem acao adicional: mantem fluxo sem quebrar a pagina.
        }

        document.body.removeChild(textArea);
    }

    function showFeedback() {
        feedback.classList.remove('d-none');
        window.setTimeout(function () {
            feedback.classList.add('d-none');
        }, 2000);
    }
})();
</script>
<?php include __DIR__.'/_site_end.php'; ?>
