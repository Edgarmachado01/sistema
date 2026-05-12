<?php

if (!function_exists('hfPlanPricingAnnualCents')) {
    function hfPlanPricingAnnualCents($monthlyCents)
    {
        $monthlyCents = max(0, (int)$monthlyCents);
        if ($monthlyCents <= 0) {
            return 0;
        }

        // Regra comercial atual: anual com desconto equivalente a 2 meses.
        return $monthlyCents * 10;
    }
}

if (!function_exists('hfPlanPricingFeatureMap')) {
    function hfPlanPricingFeatureMap()
    {
        return [
            'basico' => ['2 usuarios', '100 OS por mes', 'Financeiro simples', 'Relatorios basicos'],
            'profissional' => ['5 usuarios', '500 OS por mes', 'Financeiro completo', 'Exportacoes e relatorios'],
            'premium' => ['15 usuarios', '2000 OS por mes', 'Relatorios avancados', 'Branding completo'],
        ];
    }
}

if (!function_exists('hfPlanPricingFallbackCatalog')) {
    function hfPlanPricingFallbackCatalog()
    {
        return [
            [
                'code' => 'basico',
                'name' => 'Basico',
                'monthly' => 4990,
                'annual' => hfPlanPricingAnnualCents(4990),
                'highlight' => false,
                'features' => hfPlanPricingFeatureMap()['basico'],
            ],
            [
                'code' => 'profissional',
                'name' => 'Profissional',
                'monthly' => 7990,
                'annual' => hfPlanPricingAnnualCents(7990),
                'highlight' => true,
                'features' => hfPlanPricingFeatureMap()['profissional'],
            ],
            [
                'code' => 'premium',
                'name' => 'Premium',
                'monthly' => 12990,
                'annual' => hfPlanPricingAnnualCents(12990),
                'highlight' => false,
                'features' => hfPlanPricingFeatureMap()['premium'],
            ],
        ];
    }
}

if (!function_exists('hfPlanPricingFetchCatalog')) {
    function hfPlanPricingFetchCatalog(PDO $pdo)
    {
        $featuresMap = hfPlanPricingFeatureMap();
        $highlightCode = 'profissional';

        $stmt = $pdo->prepare("
            SELECT code, name, monthly_price_cents
            FROM plans
            WHERE is_active = 1
            ORDER BY monthly_price_cents ASC, name ASC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $catalog = [];
        foreach ($rows as $row) {
            $code = trim((string)($row['code'] ?? ''));
            $name = trim((string)($row['name'] ?? ''));
            $monthly = (int)($row['monthly_price_cents'] ?? 0);
            if ($code === '' || $name === '') {
                continue;
            }

            $catalog[] = [
                'code' => $code,
                'name' => $name,
                'monthly' => $monthly,
                'annual' => hfPlanPricingAnnualCents($monthly),
                'highlight' => $code === $highlightCode,
                'features' => $featuresMap[$code] ?? ['Plano comercial para operacao HelpDesk Facil'],
            ];
        }

        return $catalog;
    }
}

if (!function_exists('hfPlanPricingIndexByCode')) {
    function hfPlanPricingIndexByCode(array $catalog)
    {
        $index = [];
        foreach ($catalog as $item) {
            $code = trim((string)($item['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $index[$code] = [
                'monthly' => (int)($item['monthly'] ?? 0),
                'annual' => (int)($item['annual'] ?? 0),
            ];
        }

        return $index;
    }
}
