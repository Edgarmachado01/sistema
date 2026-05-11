<?php
// Centraliza plano, assinatura, uso e limites comerciais do tenant.

if (!function_exists('hfTenantUsage')) {
    function hfTenantUsage(PDO $pdo, $tenantId)
    {
        $tenantId = (int)$tenantId;
        $isSysAdmin = hfTenantUsageIsSysAdmin();

        $usage = hfTenantUsageDefault();

        if ($tenantId <= 0) {
            error_log('_plan_usage.php hfTenantUsage: tenant_id invalido.');
            return $usage;
        }

        try {
            $periodStart = date('Y-m-01 00:00:00');
            $periodEnd = date('Y-m-01 00:00:00', strtotime('first day of next month'));

            $subscription = hfTenantUsageFetchSubscription($pdo, $tenantId);
            $counts = hfTenantUsageFetchCounts($pdo, $tenantId, $periodStart, $periodEnd);

            $usage['active_users'] = (int)($counts['active_users'] ?? 0);
            $usage['monthly_os_count'] = (int)($counts['monthly_os_count'] ?? 0);

            if (!$subscription) {
                error_log('_plan_usage.php hfTenantUsage: assinatura/plano nao encontrado tenant_id='.$tenantId);
                return hfTenantUsageFinalize($usage, $isSysAdmin);
            }

            $usage['subscription_status'] = (string)($subscription['subscription_status'] ?? '');
            $usage['trial_start_at'] = $subscription['trial_start_at'] ?? null;
            $usage['trial_end_at'] = $subscription['trial_end_at'] ?? null;
            $usage['current_period_end'] = $subscription['current_period_end'] ?? null;

            $usage['plan_code'] = (string)($subscription['plan_code'] ?? '');
            $usage['plan_name'] = (string)($subscription['plan_name'] ?? '');
            $usage['user_limit'] = (int)($subscription['user_limit'] ?? 0);
            $usage['monthly_os_limit'] = (int)($subscription['monthly_os_limit'] ?? 0);
            $usage['has_financial'] = (int)($subscription['has_financial'] ?? 0) === 1;
            $usage['has_reports'] = (int)($subscription['has_reports'] ?? 0) === 1;
            $usage['has_branding'] = (int)($subscription['has_branding'] ?? 0) === 1;

            $usage['is_trial'] = $usage['plan_code'] !== 'cortesia' && $usage['subscription_status'] === 'trial';
            $usage['is_active'] = $usage['plan_code'] === 'cortesia'
                || in_array($usage['subscription_status'], ['trial', 'ativo'], true);

            return hfTenantUsageFinalize($usage, $isSysAdmin);
        } catch (Exception $e) {
            error_log('_plan_usage.php hfTenantUsage: '.$e->getMessage());
            return hfTenantUsageFinalize($usage, $isSysAdmin);
        }
    }
}

if (!function_exists('hfTenantUsageDefault')) {
    function hfTenantUsageDefault()
    {
        return [
            'subscription_status' => null,
            'trial_start_at' => null,
            'trial_end_at' => null,
            'current_period_end' => null,

            'plan_code' => null,
            'plan_name' => null,
            'user_limit' => 0,
            'monthly_os_limit' => 0,
            'has_financial' => false,
            'has_reports' => false,
            'has_branding' => false,

            'active_users' => 0,
            'monthly_os_count' => 0,

            'users_usage_percent' => 0,
            'os_usage_percent' => 0,

            'can_create_user' => true,
            'can_create_os' => true,
            'is_near_user_limit' => false,
            'is_near_os_limit' => false,
            'is_trial' => false,
            'is_active' => true,
        ];
    }
}

if (!function_exists('hfTenantUsageFetchSubscription')) {
    function hfTenantUsageFetchSubscription(PDO $pdo, $tenantId)
    {
        $sql = "
            SELECT
                ts.status AS subscription_status,
                ts.trial_start_at,
                ts.trial_end_at,
                ts.current_period_end,
                p.code AS plan_code,
                p.name AS plan_name,
                p.user_limit,
                p.monthly_os_limit,
                p.has_financial,
                p.has_reports,
                p.has_branding
            FROM tenant_subscriptions ts
            JOIN plans p ON p.id = ts.plan_id
            WHERE ts.tenant_id = :tenant_id
            ORDER BY ts.id DESC
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('hfTenantUsageFetchCounts')) {
    function hfTenantUsageFetchCounts(PDO $pdo, $tenantId, $periodStart, $periodEnd)
    {
        $osDateExpression = 'COALESCE(data_abertura, created_at)';

        $sql = "
            SELECT
                (
                    SELECT COUNT(*)
                    FROM users
                    WHERE tenant_id <=> :tenant_users
                      AND is_active = 1
                ) AS active_users,
                (
                    SELECT COUNT(*)
                    FROM hf_os
                    WHERE tenant_id = :tenant_os
                      AND deleted_at IS NULL
                      AND {$osDateExpression} >= :period_start
                      AND {$osDateExpression} < :period_end
                ) AS monthly_os_count
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':tenant_users' => $tenantId,
            ':tenant_os' => $tenantId,
            ':period_start' => $periodStart,
            ':period_end' => $periodEnd,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('hfTenantUsageFinalize')) {
    function hfTenantUsageFinalize(array $usage, $isSysAdmin = false)
    {
        $userLimit = (int)($usage['user_limit'] ?? 0);
        $osLimit = (int)($usage['monthly_os_limit'] ?? 0);

        $activeUsers = (int)($usage['active_users'] ?? 0);
        $monthlyOsCount = (int)($usage['monthly_os_count'] ?? 0);

        $usage['users_usage_percent'] = hfTenantUsagePercent($activeUsers, $userLimit);
        $usage['os_usage_percent'] = hfTenantUsagePercent($monthlyOsCount, $osLimit);

        $usage['is_near_user_limit'] = $userLimit > 0 && $usage['users_usage_percent'] >= 80;
        $usage['is_near_os_limit'] = $osLimit > 0 && $usage['os_usage_percent'] >= 80;

        $usage['can_create_user'] = $isSysAdmin || $userLimit <= 0 || $activeUsers < $userLimit;
        $usage['can_create_os'] = $isSysAdmin || $osLimit <= 0 || $monthlyOsCount < $osLimit;

        return $usage;
    }
}

if (!function_exists('hfTenantUsagePercent')) {
    function hfTenantUsagePercent($used, $limit)
    {
        $used = max(0, (int)$used);
        $limit = (int)$limit;

        if ($limit <= 0) {
            return 0;
        }

        return min(100, (int)round(($used / max($limit, 1)) * 100));
    }
}

if (!function_exists('hfTenantUsageIsSysAdmin')) {
    function hfTenantUsageIsSysAdmin()
    {
        if (!empty($_SESSION['ROLES']) && is_array($_SESSION['ROLES'])) {
            return in_array('SYS_ADMIN', $_SESSION['ROLES'], true);
        }

        return false;
    }
}
