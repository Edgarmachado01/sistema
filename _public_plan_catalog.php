<?php

if (!function_exists('hfPublicPlanAnnualCents')) {
    function hfPublicPlanAnnualCents($monthlyCents)
    {
        $monthlyCents = max(0, (int)$monthlyCents);
        return $monthlyCents > 0 ? $monthlyCents * 10 : 0;
    }
}

if (!function_exists('hfPublicPlanMoney')) {
    function hfPublicPlanMoney($cents)
    {
        return 'R$ '.number_format(((float)$cents) / 100, 2, ',', '.');
    }
}

if (!function_exists('hfPublicPlanCatalogFallback')) {
    function hfPublicPlanCatalogFallback()
    {
        return [
            'basico' => [
                'code' => 'basico',
                'name' => 'Basico',
                'monthly_cents' => 4990,
                'annual_cents' => hfPublicPlanAnnualCents(4990),
                'user_limit' => 2,
                'monthly_os_limit' => 100,
            ],
            'profissional' => [
                'code' => 'profissional',
                'name' => 'Profissional',
                'monthly_cents' => 7990,
                'annual_cents' => hfPublicPlanAnnualCents(7990),
                'user_limit' => 5,
                'monthly_os_limit' => 500,
            ],
            'premium' => [
                'code' => 'premium',
                'name' => 'Premium',
                'monthly_cents' => 12990,
                'annual_cents' => hfPublicPlanAnnualCents(12990),
                'user_limit' => 15,
                'monthly_os_limit' => 2000,
            ],
        ];
    }
}

if (!function_exists('hfPublicPlanCatalogFetch')) {
    function hfPublicPlanCatalogFetch(PDO $pdo)
    {
        $catalog = hfPublicPlanCatalogFallback();

        $stmt = $pdo->prepare("
            SELECT code, name, monthly_price_cents, user_limit, monthly_os_limit
            FROM plans
            WHERE code IN ('basico', 'profissional', 'premium')
            ORDER BY CASE code
                WHEN 'basico' THEN 1
                WHEN 'profissional' THEN 2
                WHEN 'premium' THEN 3
                ELSE 4
            END
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $code = strtolower(trim((string)($row['code'] ?? '')));
            if (!isset($catalog[$code])) {
                continue;
            }

            $monthly = max(0, (int)($row['monthly_price_cents'] ?? 0));
            $userLimit = max(0, (int)($row['user_limit'] ?? 0));
            $osLimit = max(0, (int)($row['monthly_os_limit'] ?? 0));
            $name = trim((string)($row['name'] ?? ''));

            $catalog[$code]['name'] = $name !== '' ? $name : $catalog[$code]['name'];
            $catalog[$code]['monthly_cents'] = $monthly > 0 ? $monthly : $catalog[$code]['monthly_cents'];
            $catalog[$code]['annual_cents'] = hfPublicPlanAnnualCents($catalog[$code]['monthly_cents']);
            if ($userLimit > 0) {
                $catalog[$code]['user_limit'] = $userLimit;
            }
            if ($osLimit > 0) {
                $catalog[$code]['monthly_os_limit'] = $osLimit;
            }
        }

        return $catalog;
    }
}

