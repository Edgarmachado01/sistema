<?php
require_once __DIR__.'/auth.php';
requireLogin();
require_once __DIR__.'/db.php';
require_once __DIR__.'/_plan_usage.php';
require_once __DIR__.'/_plan_pricing.php';

$pdo = db();
$tid = function_exists('tenantId') ? (int)tenantId() : 0;
$usage = null;
$billingError = false;

if (!function_exists('hfBillingMoney')) {
    function hfBillingMoney($cents)
    {
        return 'R$ '.number_format(((float)$cents) / 100, 2, ',', '.');
    }
}

if (!function_exists('hfBillingDate')) {
    function hfBillingDate($value)
    {
        if (!$value) {
            return '-';
        }

        try {
            return (new DateTime((string)$value))->format('d/m/Y');
        } catch (Exception $e) {
            return '-';
        }
    }
}

if (!function_exists('hfBillingStatusLabel')) {
    function hfBillingStatusLabel($status)
    {
        $status = trim((string)$status);
        $labels = [
            'trial' => 'Trial',
            'ativo' => 'Ativo',
            'vencido' => 'Vencido',
            'bloqueado' => 'Bloqueado',
            'cancelado' => 'Cancelado',
        ];

        return $labels[$status] ?? ($status !== '' ? ucfirst($status) : 'Sem assinatura');
    }
}

if (!function_exists('hfBillingLimitText')) {
    function hfBillingLimitText($used, $limit)
    {
        $used = (int)$used;
        $limit = (int)$limit;

        if ($limit <= 0) {
            return number_format($used, 0, ',', '.').' / ilimitado';
        }

        return number_format($used, 0, ',', '.').' / '.number_format($limit, 0, ',', '.');
    }
}

if (!function_exists('hfBillingCycleLabel')) {
    function hfBillingCycleLabel($periodStart, $periodEnd, $isTrial, $isCortesia)
    {
        if ($isCortesia) {
            return 'Cortesia';
        }
        if ($isTrial) {
            return 'Trial';
        }

        if (!$periodStart || !$periodEnd) {
            return 'Mensal';
        }

        try {
            $start = new DateTime((string)$periodStart);
            $end = new DateTime((string)$periodEnd);
            $days = (int)$start->diff($end)->format('%a');

            if ($days >= 330) {
                return 'Anual';
            }

            return 'Mensal';
        } catch (Exception $e) {
            return 'Mensal';
        }
    }
}

try {
    if ($tid > 0) {
        $usage = hfTenantUsage($pdo, $tid);
    }
} catch (Exception $e) {
    error_log('billing.php usage: '.$e->getMessage());
    $billingError = true;
}

$planCode = trim((string)($usage['plan_code'] ?? ''));
$planName = trim((string)($usage['plan_name'] ?? ''));
$subscriptionStatus = trim((string)($usage['subscription_status'] ?? ''));
$isCortesia = $planCode === 'cortesia';
$isTrial = !$isCortesia && !empty($usage['is_trial']);
$isExpired = in_array($subscriptionStatus, ['vencido', 'bloqueado', 'cancelado'], true);
$trialDaysLeft = null;

if ($isTrial && !empty($usage['trial_end_at'])) {
    try {
        $today = new DateTime('today');
        $trialEnd = new DateTime((string)$usage['trial_end_at']);
        $trialDaysLeft = max(0, (int)$today->diff($trialEnd)->format('%r%a'));
    } catch (Exception $e) {
        $trialDaysLeft = null;
    }
}

$pricingPlans = [];
try {
    $pricingPlans = hfPlanPricingFetchCatalog($pdo);
} catch (Exception $e) {
    error_log('billing.php pricing catalog: '.$e->getMessage());
}
if (!$pricingPlans) {
    $pricingPlans = hfPlanPricingFallbackCatalog();
}
$pricingByCode = hfPlanPricingIndexByCode($pricingPlans);
$pricingPlansForDisplay = array_values(array_filter($pricingPlans, function ($plan) {
    return trim((string)($plan['code'] ?? '')) !== 'cortesia';
}));
if (!$pricingPlansForDisplay) {
    $pricingPlansForDisplay = $pricingPlans;
}

$currentPeriodStart = null;
$currentMonthlyCents = 0;
try {
    if ($tid > 0) {
        $stmtBillingSub = $pdo->prepare("
            SELECT
                ts.current_period_start,
                p.monthly_price_cents
            FROM tenant_subscriptions ts
            JOIN plans p ON p.id = ts.plan_id
            WHERE ts.tenant_id = :tenant_id
            ORDER BY ts.id DESC
            LIMIT 1
        ");
        $stmtBillingSub->execute([':tenant_id' => $tid]);
        $billingSubRow = $stmtBillingSub->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($billingSubRow) {
            $currentPeriodStart = $billingSubRow['current_period_start'] ?? null;
            $currentMonthlyCents = (int)($billingSubRow['monthly_price_cents'] ?? 0);
        }
    }
} catch (Exception $e) {
    error_log('billing.php subscription: '.$e->getMessage());
    $currentPeriodStart = null;
    $currentMonthlyCents = 0;
}

$currentPlanPricing = $pricingByCode[$planCode] ?? [
    'monthly' => $currentMonthlyCents,
    'annual' => hfPlanPricingAnnualCents($currentMonthlyCents),
];
$billingCycleLabel = hfBillingCycleLabel(
    $currentPeriodStart,
    $usage['current_period_end'] ?? null,
    $isTrial,
    $isCortesia
);
$referenceAmountCents = $billingCycleLabel === 'Anual'
    ? (int)$currentPlanPricing['annual']
    : (int)$currentPlanPricing['monthly'];

$pixKey = 'pix@helpdeskfacil.com.br';
$whatsappUrl = 'https://wa.me/5500000000000?text=Quero%20enviar%20o%20comprovante%20do%20pagamento%20da%20assinatura';

include __DIR__.'/_layout_start.php';
include __DIR__.'/_sidebar.php';
?>

<main class="hf-content hf-billing-page">
  <style>
    .hf-billing-page {
      background:
        radial-gradient(circle at 8% 0%, rgba(var(--bs-primary-rgb), .08), transparent 28rem),
        linear-gradient(180deg, rgba(248, 250, 252, .86), rgba(255, 255, 255, .96));
    }

    .hf-billing-shell {
      display: flex;
      flex-direction: column;
      gap: 1.2rem;
      max-width: 1220px;
      margin: 0 auto;
      padding-bottom: 2rem;
    }

    .hf-billing-hero {
      overflow: hidden;
      position: relative;
      border: 1px solid rgba(148, 163, 184, .22);
      border-radius: 1.1rem;
      background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), .96), rgba(15, 23, 42, .94));
      color: #fff;
      box-shadow: 0 18px 46px rgba(15, 23, 42, .14);
    }

    .hf-billing-hero::after {
      content: "";
      position: absolute;
      right: -5rem;
      top: -5rem;
      width: 16rem;
      height: 16rem;
      border-radius: 50%;
      background: rgba(255, 255, 255, .12);
    }

    .hf-billing-hero-inner {
      position: relative;
      z-index: 1;
      display: grid;
      grid-template-columns: minmax(0, 1.35fr) minmax(280px, .65fr);
      gap: 1rem;
      padding: 1.4rem;
      align-items: stretch;
    }

    .hf-billing-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      padding: .34rem .65rem;
      border-radius: 999px;
      background: rgba(255, 255, 255, .13);
      color: rgba(255, 255, 255, .86);
      font-size: .78rem;
      font-weight: 850;
      text-transform: uppercase;
      letter-spacing: .04em;
    }

    .hf-billing-hero h1 {
      margin: .85rem 0 .35rem;
      font-size: clamp(1.75rem, 3vw, 2.7rem);
      font-weight: 950;
      letter-spacing: 0;
    }

    .hf-billing-hero p {
      max-width: 46rem;
      margin: 0;
      color: rgba(255, 255, 255, .78);
      line-height: 1.65;
    }

    .hf-current-plan-card {
      border: 1px solid rgba(255, 255, 255, .18);
      border-radius: 1rem;
      background: rgba(255, 255, 255, .12);
      backdrop-filter: blur(10px);
      padding: 1rem;
    }

    .hf-current-plan-card small {
      display: block;
      color: rgba(255, 255, 255, .66);
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .04em;
    }

    .hf-current-plan-card strong {
      display: block;
      margin-top: .45rem;
      font-size: 1.35rem;
      font-weight: 950;
    }

    .hf-billing-status {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      margin-top: .7rem;
      padding: .34rem .62rem;
      border-radius: 999px;
      background: rgba(255, 255, 255, .16);
      color: #fff;
      font-size: .78rem;
      font-weight: 850;
    }

    .hf-billing-card {
      border: 1px solid rgba(148, 163, 184, .22);
      border-radius: 1rem;
      background: rgba(255, 255, 255, .96);
      box-shadow: 0 14px 34px rgba(15, 23, 42, .06);
    }

    .hf-billing-alert {
      display: flex;
      gap: .8rem;
      align-items: flex-start;
      padding: 1rem;
      border-radius: 1rem;
      border: 1px solid rgba(249, 115, 22, .24);
      background: #fff7ed;
      color: #9a3412;
      font-weight: 650;
    }

    .hf-billing-alert.is-info {
      border-color: rgba(var(--bs-primary-rgb), .20);
      background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), .08), rgba(20, 184, 166, .10));
      color: #075985;
    }

    .hf-usage-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: .9rem;
    }

    .hf-usage-item {
      padding: 1rem;
    }

    .hf-usage-item span {
      color: #64748b;
      font-size: .78rem;
      font-weight: 850;
      text-transform: uppercase;
      letter-spacing: .04em;
    }

    .hf-usage-item strong {
      display: block;
      margin-top: .45rem;
      color: #0f172a;
      font-size: 1.18rem;
      font-weight: 950;
    }

    .hf-pricing-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 1rem;
    }

    .hf-price-card {
      position: relative;
      display: flex;
      flex-direction: column;
      min-height: 100%;
      padding: 1.1rem;
    }

    .hf-price-card.is-featured {
      border-color: rgba(var(--bs-primary-rgb), .36);
      box-shadow: 0 18px 44px rgba(var(--bs-primary-rgb), .13);
      transform: translateY(-4px);
    }

    .hf-price-badge {
      display: inline-flex;
      align-items: center;
      align-self: flex-start;
      gap: .35rem;
      padding: .32rem .62rem;
      border-radius: 999px;
      background: rgba(var(--bs-primary-rgb), .10);
      color: var(--bs-primary);
      font-size: .76rem;
      font-weight: 900;
    }

    .hf-price-card h3 {
      margin: .8rem 0 .4rem;
      font-size: 1.1rem;
      font-weight: 950;
    }

    .hf-price-main {
      color: #0f172a;
      font-size: 2rem;
      font-weight: 950;
      letter-spacing: 0;
    }

    .hf-price-main small {
      color: #64748b;
      font-size: .85rem;
      font-weight: 750;
    }

    .hf-price-annual {
      margin-top: .2rem;
      color: #16a34a;
      font-size: .9rem;
      font-weight: 850;
    }

    .hf-price-card ul {
      display: grid;
      gap: .5rem;
      margin: 1rem 0 0;
      padding: 0;
      list-style: none;
      color: #475569;
      font-size: .92rem;
    }

    .hf-price-card li {
      display: flex;
      gap: .5rem;
      align-items: flex-start;
    }

    .hf-pix-grid {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 230px;
      gap: 1rem;
      align-items: stretch;
    }

    .hf-pix-key {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      padding: .72rem .85rem;
      border-radius: .85rem;
      background: #f8fafc;
      border: 1px solid rgba(148, 163, 184, .24);
      color: #0f172a;
      font-weight: 850;
      word-break: break-all;
    }

    .hf-qr-placeholder {
      min-height: 230px;
      display: grid;
      place-items: center;
      border-radius: 1rem;
      border: 1px dashed rgba(148, 163, 184, .55);
      background:
        linear-gradient(90deg, rgba(15, 23, 42, .05) 1px, transparent 1px),
        linear-gradient(rgba(15, 23, 42, .05) 1px, transparent 1px),
        #fff;
      background-size: 18px 18px;
      color: #64748b;
      text-align: center;
      font-weight: 800;
    }

    @media (max-width: 1040px) {
      .hf-billing-hero-inner,
      .hf-pix-grid {
        grid-template-columns: 1fr;
      }

      .hf-usage-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .hf-pricing-grid {
        grid-template-columns: 1fr;
      }

      .hf-price-card.is-featured {
        transform: none;
      }
    }

    @media (max-width: 560px) {
      .hf-billing-hero-inner {
        padding: 1rem;
      }

      .hf-usage-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>

  <div class="hf-billing-shell">
    <section class="hf-billing-hero">
      <div class="hf-billing-hero-inner">
        <div>
          <span class="hf-billing-eyebrow">
            <i class="bi bi-credit-card-2-front" aria-hidden="true"></i>
            Plano &amp; Cobranca
          </span>
          <h1>Gerencie sua assinatura</h1>
          <p>Confira plano atual, uso, trial e dados para pagamento manual via PIX. A integracao com gateway entra depois, sem mudar sua operacao agora.</p>
        </div>

        <aside class="hf-current-plan-card">
          <small>Plano atual</small>
          <strong><?= htmlspecialchars($planName !== '' ? $planName : 'Sem plano vinculado', ENT_QUOTES, 'UTF-8') ?></strong>
          <span class="hf-billing-status">
            <i class="bi bi-circle-fill" aria-hidden="true" style="font-size:.48rem"></i>
            <?= htmlspecialchars($isCortesia ? 'Cortesia' : hfBillingStatusLabel($subscriptionStatus), ENT_QUOTES, 'UTF-8') ?>
          </span>
        </aside>
      </div>
    </section>

    <?php if ($billingError): ?>
      <div class="hf-billing-alert">
        <i class="bi bi-exclamation-triangle fs-5" aria-hidden="true"></i>
        <span>Nao foi possivel carregar todos os dados da assinatura agora.</span>
      </div>
    <?php endif; ?>

    <?php if ($isCortesia): ?>
      <div class="hf-billing-alert is-info">
        <i class="bi bi-stars fs-5" aria-hidden="true"></i>
        <span>Plano liberado pela administracao. Esta empresa possui uso especial sem cobranca recorrente.</span>
      </div>
    <?php elseif ($isExpired): ?>
      <div class="hf-billing-alert">
        <i class="bi bi-exclamation-circle fs-5" aria-hidden="true"></i>
        <span>Sua assinatura esta <?= htmlspecialchars(strtolower(hfBillingStatusLabel($subscriptionStatus)), ENT_QUOTES, 'UTF-8') ?>. O acesso nao sera bloqueado automaticamente nesta etapa, mas recomendamos regularizar o pagamento.</span>
      </div>
    <?php elseif ($isTrial): ?>
      <div class="hf-billing-alert is-info">
        <i class="bi bi-hourglass-split fs-5" aria-hidden="true"></i>
        <span>
          Voce esta em periodo de teste.
          <?php if ($trialDaysLeft !== null): ?>
            Restam <?= htmlspecialchars((string)$trialDaysLeft, ENT_QUOTES, 'UTF-8') ?> <?= $trialDaysLeft === 1 ? 'dia' : 'dias' ?>.
          <?php endif; ?>
        </span>
      </div>
    <?php endif; ?>

    <section class="hf-usage-grid">
      <article class="hf-billing-card hf-usage-item">
        <span>Plano atual</span>
        <strong><?= htmlspecialchars($planName !== '' ? $planName : 'Sem plano', ENT_QUOTES, 'UTF-8') ?></strong>
      </article>
      <article class="hf-billing-card hf-usage-item">
        <span>Status</span>
        <strong><?= htmlspecialchars($isCortesia ? 'Cortesia' : hfBillingStatusLabel($subscriptionStatus), ENT_QUOTES, 'UTF-8') ?></strong>
      </article>
      <article class="hf-billing-card hf-usage-item">
        <span>Ciclo</span>
        <strong><?= htmlspecialchars($billingCycleLabel, ENT_QUOTES, 'UTF-8') ?></strong>
      </article>
      <article class="hf-billing-card hf-usage-item">
        <span>Trial termina</span>
        <strong><?= htmlspecialchars($isCortesia ? '-' : hfBillingDate($usage['trial_end_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></strong>
      </article>
      <article class="hf-billing-card hf-usage-item">
        <span>Vencimento</span>
        <strong><?= htmlspecialchars($isCortesia ? '-' : hfBillingDate($usage['current_period_end'] ?? null), ENT_QUOTES, 'UTF-8') ?></strong>
      </article>
      <article class="hf-billing-card hf-usage-item">
        <span>Valor mensal</span>
        <strong><?= htmlspecialchars($isCortesia ? '-' : hfBillingMoney((int)$currentPlanPricing['monthly']), ENT_QUOTES, 'UTF-8') ?></strong>
      </article>
      <article class="hf-billing-card hf-usage-item">
        <span>Valor anual</span>
        <strong><?= htmlspecialchars($isCortesia ? '-' : hfBillingMoney((int)$currentPlanPricing['annual']), ENT_QUOTES, 'UTF-8') ?></strong>
      </article>
      <article class="hf-billing-card hf-usage-item">
        <span>Usuarios</span>
        <strong><?= htmlspecialchars(hfBillingLimitText($usage['active_users'] ?? 0, $usage['user_limit'] ?? 0), ENT_QUOTES, 'UTF-8') ?></strong>
      </article>
      <article class="hf-billing-card hf-usage-item">
        <span>OS do mes</span>
        <strong><?= htmlspecialchars(hfBillingLimitText($usage['monthly_os_count'] ?? 0, $usage['monthly_os_limit'] ?? 0), ENT_QUOTES, 'UTF-8') ?></strong>
      </article>
    </section>

    <section class="hf-billing-card p-4">
      <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
        <div>
          <div class="text-uppercase fw-bold text-primary small mb-2">Planos</div>
          <h2 class="h4 fw-bold mb-1">Escolha o melhor ritmo de pagamento</h2>
          <p class="text-muted mb-0">No anual, voce economiza 2 meses. A liberacao ainda e manual via comprovante.</p>
        </div>
      </div>

      <div class="hf-pricing-grid">
        <?php foreach ($pricingPlansForDisplay as $pricePlan): ?>
          <?php $isCurrent = $planCode === $pricePlan['code']; ?>
          <article class="hf-billing-card hf-price-card <?= !empty($pricePlan['highlight']) ? 'is-featured' : '' ?>">
            <span class="hf-price-badge">
              <?php if (!empty($pricePlan['highlight'])): ?>
                <i class="bi bi-star-fill" aria-hidden="true"></i>Mais escolhido
              <?php elseif ($isCurrent): ?>
                <i class="bi bi-check-circle" aria-hidden="true"></i>Plano atual
              <?php else: ?>
                <i class="bi bi-box" aria-hidden="true"></i>Plano
              <?php endif; ?>
            </span>
            <h3><?= htmlspecialchars($pricePlan['name'], ENT_QUOTES, 'UTF-8') ?></h3>
            <div class="hf-price-main">
              <?= htmlspecialchars(hfBillingMoney($pricePlan['monthly']), ENT_QUOTES, 'UTF-8') ?>
              <small>/mes</small>
            </div>
            <div class="hf-price-annual">
              <?= htmlspecialchars(hfBillingMoney($pricePlan['annual']), ENT_QUOTES, 'UTF-8') ?> anual - Economize 2 meses
            </div>
            <ul>
              <?php foreach ($pricePlan['features'] as $feature): ?>
                <li>
                  <i class="bi bi-check2 text-success" aria-hidden="true"></i>
                  <span><?= htmlspecialchars($feature, ENT_QUOTES, 'UTF-8') ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="hf-billing-card p-4">
      <div class="hf-pix-grid">
        <div>
          <div class="text-uppercase fw-bold text-primary small mb-2">PIX manual</div>
          <h2 class="h4 fw-bold mb-2">Pagamento por comprovante</h2>
          <p class="text-muted">Use a chave PIX abaixo e envie o comprovante pelo WhatsApp. A baixa sera feita manualmente pela administracao.</p>

          <?php if (!$isCortesia): ?>
            <div class="alert alert-light border mb-3">
              <div class="fw-bold mb-1">Resumo para pagamento</div>
              <div class="small text-muted">Ciclo atual: <?= htmlspecialchars($billingCycleLabel, ENT_QUOTES, 'UTF-8') ?></div>
              <div class="small text-muted">Valor de referencia: <?= htmlspecialchars(hfBillingMoney($referenceAmountCents), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          <?php endif; ?>

          <div class="hf-pix-key mb-3">
            <i class="bi bi-qr-code" aria-hidden="true"></i>
            <span><?= htmlspecialchars($pixKey, ENT_QUOTES, 'UTF-8') ?></span>
          </div>

          <a class="btn btn-primary" href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
            <i class="bi bi-whatsapp me-1" aria-hidden="true"></i>Enviar comprovante
          </a>
        </div>

        <div class="hf-qr-placeholder">
          <div>
            <i class="bi bi-qr-code fs-1 d-block mb-2" aria-hidden="true"></i>
            QR Code PIX<br>
            <small>placeholder</small>
          </div>
        </div>
      </div>
    </section>
  </div>
</main>

<?php include __DIR__.'/_layout_end.php'; ?>
