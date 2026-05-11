<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__.'/db.php';

function hfSignupPlanosPermitidos()
{
    return ['basico', 'profissional', 'premium'];
}

function hfSignupPlanoValido($plano)
{
    $plano = strtolower(trim((string)$plano));
    return in_array($plano, hfSignupPlanosPermitidos(), true);
}

function hfSignupPlanoSeguro($plano)
{
    $plano = strtolower(trim((string)$plano));
    return hfSignupPlanoValido($plano) ? $plano : 'profissional';
}

function hfSignupOldInput($data)
{
    return [
        'empresa_nome'     => trim((string)($data['empresa_nome'] ?? '')),
        'responsavel_nome' => trim((string)($data['responsavel_nome'] ?? '')),
        'email'            => trim((string)($data['email'] ?? '')),
        'whatsapp'         => trim((string)($data['whatsapp'] ?? '')),
        'documento'        => trim((string)($data['documento'] ?? '')),
        'plano'            => hfSignupPlanoSeguro($data['plano'] ?? 'profissional'),
    ];
}

function hfSignupRedirectError($message, $oldInput = [])
{
    $plano = hfSignupPlanoSeguro($oldInput['plano'] ?? ($_POST['plano'] ?? 'profissional'));

    $_SESSION['HF_SIGNUP_ERROR'] = $message;
    $_SESSION['HF_SIGNUP_OLD'] = hfSignupOldInput($oldInput ?: $_POST);

    header('Location: /cadastro.php?plano='.rawurlencode($plano));
    exit;
}

function hfSignupNormalizeSlug($value)
{
    $slug = trim((string)$value);

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
        if ($converted !== false) {
            $slug = $converted;
        }
    }

    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    return $slug;
}

function hfSignupSlugReservado($slug)
{
    $reservados = [
        'admin',
        'api',
        'assets',
        'cadastro',
        'cadastro-save',
        'config',
        'dashboard',
        'financeiro',
        'helpdesk',
        'home',
        'login',
        'logout',
        'planos',
        'relatorios',
        'sistema',
        'uploads',
        'usuarios',
    ];

    return in_array($slug, $reservados, true);
}

function hfSignupValidarCsrf()
{
    $sessionToken = $_SESSION['csrf_token'] ?? $_SESSION['HF_SIGNUP_CSRF'] ?? '';
    $postToken = $_POST['csrf_token'] ?? '';

    return is_string($sessionToken)
        && is_string($postToken)
        && $sessionToken !== ''
        && $postToken !== ''
        && hash_equals($sessionToken, $postToken);
}

function hfSignupNormalizeDocumento($value)
{
    return preg_replace('/\D+/', '', trim((string)$value));
}

function hfSignupDocumentoValido($documento)
{
    $len = strlen((string)$documento);
    return $len === 11 || $len === 14;
}

function hfSignupSlugExiste(PDO $pdo, $slug)
{
    $st = $pdo->prepare('SELECT id FROM tenants WHERE slug = ? LIMIT 1');
    $st->execute([$slug]);
    return (bool)$st->fetchColumn();
}

function hfSignupBuildCandidateSlug($base, $suffix = '')
{
    $base = trim((string)$base, '-');
    $suffix = trim((string)$suffix, '-');

    if ($base === '') {
        $base = 'empresa';
    }

    if ($suffix === '') {
        return substr($base, 0, 40);
    }

    $maxBaseLen = 40 - (strlen($suffix) + 1);
    if ($maxBaseLen < 3) {
        $maxBaseLen = 3;
    }

    $baseCut = substr($base, 0, $maxBaseLen);
    $baseCut = rtrim($baseCut, '-');
    if ($baseCut === '') {
        $baseCut = 'emp';
    }

    return $baseCut.'-'.$suffix;
}

function hfSignupGenerateTenantCode(PDO $pdo, $empresaNome)
{
    $base = hfSignupNormalizeSlug((string)$empresaNome);
    if ($base === '') {
        $base = 'empresa';
    }
    $base = substr($base, 0, 30);
    $base = trim($base, '-');

    for ($i = 0; $i < 25; $i++) {
        $suffix = '';
        if ($i > 0) {
            $suffix = bin2hex(random_bytes(2));
        }

        $candidate = hfSignupBuildCandidateSlug($base, $suffix);
        if ($candidate === '' || strlen($candidate) < 3 || hfSignupSlugReservado($candidate)) {
            continue;
        }

        if (!hfSignupSlugExiste($pdo, $candidate)) {
            return $candidate;
        }
    }

    throw new RuntimeException('Nao foi possivel gerar um codigo de empresa disponivel.');
}

function hfSignupBuildLoginUrl()
{
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '/login.php';
    }

    $scheme = 'http';
    $forwardedProto = trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwardedProto !== '') {
        $scheme = strtolower(explode(',', $forwardedProto)[0]);
    } elseif (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        $scheme = 'https';
    }

    if ($scheme !== 'http' && $scheme !== 'https') {
        $scheme = 'https';
    }

    return $scheme.'://'.$host.'/login.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /cadastro.php');
    exit;
}

if (!hfSignupValidarCsrf()) {
    hfSignupRedirectError('Sessao expirada. Recarregue a pagina e tente novamente.');
}

$empresaNome = trim((string)($_POST['empresa_nome'] ?? ''));
$responsavelNome = trim((string)($_POST['responsavel_nome'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$whatsapp = trim((string)($_POST['whatsapp'] ?? ''));
$documento = hfSignupNormalizeDocumento($_POST['documento'] ?? '');
$senha = (string)($_POST['senha'] ?? '');
$senhaConfirmar = (string)($_POST['senha_confirmar'] ?? '');
$planoRecebido = strtolower(trim((string)($_POST['plano'] ?? '')));
$plano = hfSignupPlanoSeguro($planoRecebido);

$oldInput = hfSignupOldInput([
    'empresa_nome' => $empresaNome,
    'responsavel_nome' => $responsavelNome,
    'email' => $email,
    'whatsapp' => $whatsapp,
    'documento' => $documento,
    'plano' => $plano,
]);

if ($empresaNome === '') {
    hfSignupRedirectError('Informe o nome da empresa.', $oldInput);
}

if ($responsavelNome === '') {
    hfSignupRedirectError('Informe o nome do responsavel.', $oldInput);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    hfSignupRedirectError('Informe um e-mail valido.', $oldInput);
}

if ($whatsapp === '') {
    hfSignupRedirectError('Informe o WhatsApp da empresa.', $oldInput);
}

if (!hfSignupDocumentoValido($documento)) {
    hfSignupRedirectError('Informe um CPF ou CNPJ valido (somente numeros).', $oldInput);
}

if (!hfSignupPlanoValido($planoRecebido)) {
    hfSignupRedirectError('Escolha um plano valido para iniciar o teste gratis.', $oldInput);
}

if ($senha === '' || strlen($senha) < 8) {
    hfSignupRedirectError('A senha deve ter no minimo 8 caracteres.', $oldInput);
}

if ($senha !== $senhaConfirmar) {
    hfSignupRedirectError('Senha e confirmacao nao conferem.', $oldInput);
}

$userFriendlyError = 'Nao foi possivel criar o teste gratis. Revise os dados e tente novamente.';

try {
    $pdo = db();

    $pdo->beginTransaction();

    $stPlan = $pdo->prepare("
        SELECT id, code, name, trial_days
        FROM plans
        WHERE code = :code
          AND is_active = 1
        LIMIT 1
    ");
    $stPlan->execute([':code' => $plano]);
    $plan = $stPlan->fetch(PDO::FETCH_ASSOC);

    if (!$plan || empty($plan['id'])) {
        $userFriendlyError = 'O plano escolhido nao esta disponivel no momento. Escolha outro plano e tente novamente.';
        throw new RuntimeException('Plano indisponivel no cadastro: '.$plano);
    }

    $planId = (int)$plan['id'];
    $planName = trim((string)($plan['name'] ?? ''));
    $trialDays = (int)($plan['trial_days'] ?? 14);

    if ($planId <= 0) {
        $userFriendlyError = 'O plano escolhido nao esta disponivel no momento. Escolha outro plano e tente novamente.';
        throw new RuntimeException('Plano sem ID valido no cadastro: '.$plano);
    }

    if ($trialDays <= 0) {
        $trialDays = 14;
    }

    $slug = hfSignupGenerateTenantCode($pdo, $empresaNome);

    $sqlTenant = "
        INSERT INTO tenants (
            slug,
            name,
            is_active,
            brand_primary,
            brand_mode
        ) VALUES (
            :slug,
            :name,
            1,
            '#0d6efd',
            'light'
        )
    ";
    $stTenant = $pdo->prepare($sqlTenant);
    $stTenant->execute([
        ':slug' => $slug,
        ':name' => $empresaNome,
    ]);
    $tenantId = (int)$pdo->lastInsertId();

    if ($tenantId <= 0) {
        throw new Exception('Tenant nao foi criado.');
    }

    $sqlSubscription = "
        INSERT INTO tenant_subscriptions (
            tenant_id,
            plan_id,
            status,
            trial_start_at,
            trial_end_at,
            current_period_start,
            current_period_end
        ) VALUES (
            :tenant_id,
            :plan_id,
            'trial',
            NOW(),
            DATE_ADD(NOW(), INTERVAL :trial_days DAY),
            NOW(),
            DATE_ADD(NOW(), INTERVAL :trial_days_end DAY)
        )
    ";
    $stSubscription = $pdo->prepare($sqlSubscription);
    $stSubscription->execute([
        ':tenant_id' => $tenantId,
        ':plan_id' => $planId,
        ':trial_days' => $trialDays,
        ':trial_days_end' => $trialDays,
    ]);

    $subscriptionId = (int)$pdo->lastInsertId();
    if ($subscriptionId <= 0) {
        throw new Exception('Assinatura comercial nao foi criada.');
    }

    $stTrial = $pdo->prepare("
        SELECT trial_end_at
        FROM tenant_subscriptions
        WHERE id = :id
        LIMIT 1
    ");
    $stTrial->execute([':id' => $subscriptionId]);
    $trialEndAt = (string)$stTrial->fetchColumn();

    $sqlConfig = "
        INSERT INTO tenant_config (
            tenant_id,
            razao_social,
            nome_fantasia,
            cnpj,
            ie,
            im,
            telefone,
            whatsapp,
            email,
            site,
            cep,
            endereco,
            numero,
            complemento,
            bairro,
            cidade,
            uf,
            logo_path,
            cor_primaria,
            cor_secundaria,
            sla_prazo_resposta_min,
            sla_prazo_solucao_padrao,
            sla_baixa_horas,
            sla_media_horas,
            sla_alta_horas,
            sla_critica_horas,
            horario_inicio,
            horario_fim,
            considera_sabado,
            considera_domingo
        ) VALUES (
            :tenant_id,
            :razao_social,
            :nome_fantasia,
            :cnpj,
            '',
            '',
            :telefone,
            :whatsapp,
            :email,
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '#0d6efd',
            '#6c757d',
            30,
            48,
            72,
            48,
            24,
            4,
            '08:00:00',
            '18:00:00',
            0,
            0
        )
    ";
    $stConfig = $pdo->prepare($sqlConfig);
    $stConfig->execute([
        ':tenant_id' => $tenantId,
        ':razao_social' => $empresaNome,
        ':nome_fantasia' => $empresaNome,
        ':cnpj' => $documento,
        ':telefone' => $whatsapp,
        ':whatsapp' => $whatsapp,
        ':email' => $email,
    ]);

    $stEmail = $pdo->prepare('SELECT id FROM users WHERE email = :email AND (tenant_id <=> :tenant_id) LIMIT 1');
    $stEmail->execute([
        ':email' => $email,
        ':tenant_id' => $tenantId,
    ]);
    if ($stEmail->fetchColumn()) {
        throw new RuntimeException('E-mail duplicado no tenant recem-criado.');
    }

    $stRole = $pdo->prepare("SELECT id FROM roles WHERE role_key = 'TENANT_ADMIN' LIMIT 1");
    $stRole->execute();
    $roleId = (int)$stRole->fetchColumn();

    if ($roleId <= 0) {
        throw new RuntimeException('Role TENANT_ADMIN nao encontrada.');
    }

    $passwordHash = password_hash($senha, PASSWORD_DEFAULT);

    $sqlUser = "
        INSERT INTO users (
            name,
            email,
            password_hash,
            tenant_id,
            is_active,
            created_at
        ) VALUES (
            :name,
            :email,
            :password_hash,
            :tenant_id,
            1,
            NOW()
        )
    ";
    $stUser = $pdo->prepare($sqlUser);
    $stUser->execute([
        ':name' => $responsavelNome,
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':tenant_id' => $tenantId,
    ]);
    $userId = (int)$pdo->lastInsertId();

    if ($userId <= 0) {
        throw new Exception('Usuario administrador nao foi criado.');
    }

    $stUserRole = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
    $stUserRole->execute([
        ':user_id' => $userId,
        ':role_id' => $roleId,
    ]);

    $pdo->commit();

    $_SESSION['HF_SIGNUP_SUCCESS'] = [
        'empresa_nome' => $empresaNome,
        'empresa_slug' => $slug,
        'tenant_code' => $slug,
        'responsavel_nome' => $responsavelNome,
        'email' => $email,
        'documento' => $documento,
        'plano' => $plano,
        'plano_nome' => $planName !== '' ? $planName : ucfirst($plano),
        'trial_status' => 'trial',
        'trial_days' => $trialDays,
        'trial_end_at' => $trialEndAt,
        'login_url' => hfSignupBuildLoginUrl(),
    ];
    unset($_SESSION['HF_SIGNUP_ERROR'], $_SESSION['HF_SIGNUP_OLD']);

    header('Location: /cadastro_sucesso.php');
    exit;
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('cadastro_save.php: '.$e->getMessage());

    $msg = $e->getMessage();
    if (stripos($msg, 'duplicate') !== false || stripos($msg, 'uq') !== false || stripos($msg, 'slug') !== false) {
        hfSignupRedirectError('Nao foi possivel gerar um codigo de empresa disponivel agora. Tente novamente.', $oldInput);
    }

    hfSignupRedirectError($userFriendlyError, $oldInput);
}
