<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__.'/db.php';

function hfSignupPlanosPermitidos()
{
    return ['basico', 'profissional', 'premium'];
}

function hfSignupPlanoSeguro($plano)
{
    $plano = strtolower(trim((string)$plano));
    return in_array($plano, hfSignupPlanosPermitidos(), true) ? $plano : 'profissional';
}

function hfSignupOldInput($data)
{
    return [
        'empresa_nome'     => trim((string)($data['empresa_nome'] ?? '')),
        'responsavel_nome' => trim((string)($data['responsavel_nome'] ?? '')),
        'email'            => trim((string)($data['email'] ?? '')),
        'whatsapp'         => trim((string)($data['whatsapp'] ?? '')),
        'empresa_slug'     => trim((string)($data['empresa_slug'] ?? '')),
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
$slugInformado = trim((string)($_POST['empresa_slug'] ?? ''));
$senha = (string)($_POST['senha'] ?? '');
$senhaConfirmar = (string)($_POST['senha_confirmar'] ?? '');
$plano = hfSignupPlanoSeguro($_POST['plano'] ?? 'profissional');

$oldInput = hfSignupOldInput([
    'empresa_nome' => $empresaNome,
    'responsavel_nome' => $responsavelNome,
    'email' => $email,
    'whatsapp' => $whatsapp,
    'empresa_slug' => $slugInformado,
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

$slug = hfSignupNormalizeSlug($slugInformado);
$oldInput['empresa_slug'] = $slug;

if ($slug === '' || strlen($slug) < 3 || strlen($slug) > 40) {
    hfSignupRedirectError('Informe um codigo de empresa valido, com 3 a 40 caracteres.', $oldInput);
}

if (hfSignupSlugReservado($slug)) {
    hfSignupRedirectError('Este codigo de empresa nao pode ser usado. Escolha outro.', $oldInput);
}

if ($senha === '' || strlen($senha) < 8) {
    hfSignupRedirectError('A senha deve ter no minimo 8 caracteres.', $oldInput);
}

if ($senha !== $senhaConfirmar) {
    hfSignupRedirectError('Senha e confirmacao nao conferem.', $oldInput);
}

try {
    $pdo = db();

    $stSlug = $pdo->prepare('SELECT id FROM tenants WHERE slug = ? LIMIT 1');
    $stSlug->execute([$slug]);
    if ($stSlug->fetchColumn()) {
        hfSignupRedirectError('Este codigo de empresa ja esta em uso. Escolha outro.', $oldInput);
    }

    $pdo->beginTransaction();

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
            '',
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
        'responsavel_nome' => $responsavelNome,
        'email' => $email,
        'plano' => $plano,
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
        hfSignupRedirectError('Este codigo de empresa ja esta em uso. Escolha outro.', $oldInput);
    }

    hfSignupRedirectError('Nao foi possivel criar o teste gratis. Revise os dados e tente novamente.', $oldInput);
}
