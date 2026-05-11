<?php
require_once __DIR__.'/_admin_auth.php';
requireSaasAdmin();
require_once __DIR__.'/db.php';

function hfAdminTenantRedirect($tenantId)
{
    header('Location: /admin_tenant_form.php?id='.(int)$tenantId);
    exit;
}

function hfAdminTenantFail($tenantId, $message)
{
    $_SESSION['SAAS_ADMIN_FLASH_ERROR'] = $message;
    hfAdminTenantRedirect($tenantId);
}

function hfAdminTenantNormalizeSlug($value)
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

function hfAdminTenantDateOrNull($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    return $value.' 23:59:59';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin_tenants.php');
    exit;
}

$tenantId = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : 0;
if ($tenantId <= 0) {
    $_SESSION['SAAS_ADMIN_FLASH_ERROR'] = 'Empresa invalida.';
    header('Location: /admin_tenants.php');
    exit;
}

$sessionToken = $_SESSION['SAAS_ADMIN_TENANT_CSRF'] ?? '';
$postToken = $_POST['csrf_token'] ?? '';
if ($sessionToken === '' || $postToken === '' || !hash_equals($sessionToken, $postToken)) {
    hfAdminTenantFail($tenantId, 'Sessao expirada. Recarregue a pagina e tente novamente.');
}

$name = trim((string)($_POST['name'] ?? ''));
$slug = hfAdminTenantNormalizeSlug($_POST['slug'] ?? '');
$tenantActive = (int)($_POST['tenant_active'] ?? 0) === 1 ? 1 : 0;
$planId = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0;
$status = trim((string)($_POST['status'] ?? ''));
$trialEndAt = hfAdminTenantDateOrNull($_POST['trial_end_at'] ?? '');
$currentPeriodEnd = hfAdminTenantDateOrNull($_POST['current_period_end'] ?? '');
$validStatuses = ['trial', 'ativo', 'vencido', 'bloqueado', 'cancelado'];

if ($name === '') {
    hfAdminTenantFail($tenantId, 'Informe o nome da empresa.');
}

if ($slug === '' || strlen($slug) < 3 || strlen($slug) > 40) {
    hfAdminTenantFail($tenantId, 'Informe um codigo valido com 3 a 40 caracteres.');
}

if ($planId <= 0) {
    hfAdminTenantFail($tenantId, 'Selecione um plano valido.');
}

if (!in_array($status, $validStatuses, true)) {
    hfAdminTenantFail($tenantId, 'Selecione um status de assinatura valido.');
}

if ($trialEndAt === false || $currentPeriodEnd === false) {
    hfAdminTenantFail($tenantId, 'Informe datas validas.');
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    $stmtTenant = $pdo->prepare("SELECT id FROM tenants WHERE id = :id LIMIT 1");
    $stmtTenant->execute([':id' => $tenantId]);
    if (!$stmtTenant->fetchColumn()) {
        throw new RuntimeException('Tenant nao encontrado: '.$tenantId);
    }

    $stmtSlug = $pdo->prepare("
        SELECT id
        FROM tenants
        WHERE slug = :slug
          AND id <> :id
        LIMIT 1
    ");
    $stmtSlug->execute([
        ':slug' => $slug,
        ':id' => $tenantId,
    ]);
    if ($stmtSlug->fetchColumn()) {
        $_SESSION['SAAS_ADMIN_FLASH_ERROR'] = 'Este codigo de empresa ja esta em uso.';
        $pdo->rollBack();
        hfAdminTenantRedirect($tenantId);
    }

    $stmtPlan = $pdo->prepare("
        SELECT id, code
        FROM plans
        WHERE id = :id
          AND is_active = 1
        LIMIT 1
    ");
    $stmtPlan->execute([':id' => $planId]);
    $selectedPlan = $stmtPlan->fetch(PDO::FETCH_ASSOC);
    if (!$selectedPlan) {
        $_SESSION['SAAS_ADMIN_FLASH_ERROR'] = 'O plano selecionado nao esta disponivel.';
        $pdo->rollBack();
        hfAdminTenantRedirect($tenantId);
    }

    $selectedPlanCode = trim((string)($selectedPlan['code'] ?? ''));
    if ($selectedPlanCode === 'cortesia' && $status === 'trial') {
        $status = 'ativo';
        $trialEndAt = null;
    }

    $stmtUpdateTenant = $pdo->prepare("
        UPDATE tenants
        SET name = :name,
            slug = :slug,
            is_active = :is_active
        WHERE id = :id
    ");
    $stmtUpdateTenant->execute([
        ':name' => $name,
        ':slug' => $slug,
        ':is_active' => $tenantActive,
        ':id' => $tenantId,
    ]);

    $stmtSubscription = $pdo->prepare("
        SELECT id
        FROM tenant_subscriptions
        WHERE tenant_id = :tenant_id
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmtSubscription->execute([':tenant_id' => $tenantId]);
    $subscriptionId = (int)$stmtSubscription->fetchColumn();

    $blockedAtSql = $status === 'bloqueado' ? 'NOW()' : 'NULL';
    $cancelledAtSql = $status === 'cancelado' ? 'NOW()' : 'NULL';

    if ($subscriptionId > 0) {
        $stmtUpdateSubscription = $pdo->prepare("
            UPDATE tenant_subscriptions
            SET plan_id = :plan_id,
                status = :status,
                trial_end_at = :trial_end_at,
                current_period_end = :current_period_end,
                blocked_at = {$blockedAtSql},
                cancelled_at = {$cancelledAtSql},
                updated_at = NOW()
            WHERE id = :id
              AND tenant_id = :tenant_id
        ");
        $stmtUpdateSubscription->execute([
            ':plan_id' => $planId,
            ':status' => $status,
            ':trial_end_at' => $trialEndAt,
            ':current_period_end' => $currentPeriodEnd,
            ':id' => $subscriptionId,
            ':tenant_id' => $tenantId,
        ]);
    } else {
        $stmtInsertSubscription = $pdo->prepare("
            INSERT INTO tenant_subscriptions (
                tenant_id,
                plan_id,
                status,
                trial_start_at,
                trial_end_at,
                current_period_start,
                current_period_end,
                blocked_at,
                cancelled_at,
                created_at,
                updated_at
            ) VALUES (
                :tenant_id,
                :plan_id,
                :status,
                CASE WHEN :status_trial = 'trial' THEN NOW() ELSE NULL END,
                :trial_end_at,
                NOW(),
                :current_period_end,
                {$blockedAtSql},
                {$cancelledAtSql},
                NOW(),
                NOW()
            )
        ");
        $stmtInsertSubscription->execute([
            ':tenant_id' => $tenantId,
            ':plan_id' => $planId,
            ':status' => $status,
            ':status_trial' => $status,
            ':trial_end_at' => $trialEndAt,
            ':current_period_end' => $currentPeriodEnd,
        ]);
    }

    $pdo->commit();

    $_SESSION['SAAS_ADMIN_FLASH_SUCCESS'] = 'Empresa atualizada com sucesso.';
    header('Location: /admin_tenants.php');
    exit;
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('admin_tenant_save.php: '.$e->getMessage());
    hfAdminTenantFail($tenantId, 'Nao foi possivel salvar a empresa agora.');
}
