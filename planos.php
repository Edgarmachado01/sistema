<?php
$siteTitle = 'Planos - HelpDesk Facil';
$siteDescription = 'Escolha o plano do HelpDesk Facil para organizar OS, clientes, financeiro, relatorios e equipe da sua assistencia tecnica.';
$siteBodyClass = 'hf-plans-page';
$whatsappUrl = 'https://wa.me/5500000000000?text=Quero%20conhecer%20os%20planos%20do%20HelpDesk%20Facil';
require_once __DIR__.'/db.php';
require_once __DIR__.'/_public_plan_catalog.php';

$publicPlans = hfPublicPlanCatalogFallback();
try {
    $publicPlans = hfPublicPlanCatalogFetch(db());
} catch (Exception $e) {
    error_log('planos.php public plans: '.$e->getMessage());
}

include __DIR__.'/_site_start.php';
?>
    <section class="hf-hero">
      <div class="container">
        <div class="hf-hero-grid">
          <div>
            <span class="hf-section-kicker">
              <i class="bi bi-layers" aria-hidden="true"></i>
              Planos do HelpDesk Facil
            </span>

            <h1 class="hf-hero-title">Escolha o plano ideal para sua assist&ecirc;ncia.</h1>

            <p class="hf-hero-text">
              Comece com uma estrutura simples para organizar OS, clientes e financeiro, e evolua conforme sua equipe precisar de mais usuarios, relatorios e personalizacao.
            </p>

            <div class="hf-hero-actions">
              <a class="btn btn-primary hf-btn-primary" href="/cadastro.php?plano=profissional">
                Come&ccedil;ar teste gr&aacute;tis
                <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
              </a>
              <a class="btn hf-btn-whatsapp" href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                <i class="bi bi-whatsapp" aria-hidden="true"></i>
                Falar no WhatsApp
              </a>
            </div>

            <div class="hf-hero-proof" aria-label="Resumo dos planos">
              <span><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Sem pagamento integrado ainda</span>
              <span><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Trial preparado para cadastro</span>
              <span><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Upgrade conforme crescimento</span>
            </div>
          </div>

          <div class="hf-hero-visual" aria-label="Comparativo visual de planos">
            <div class="hf-hero-visual-bar">
              <div class="hf-window-controls">
                <span class="hf-hero-dot"></span>
                <span class="hf-hero-dot"></span>
                <span class="hf-hero-dot"></span>
              </div>
              <div class="hf-app-pill">
                <i class="bi bi-shield-check" aria-hidden="true"></i>
                planos.helpdeskfacil.com
              </div>
              <span class="hf-live-badge">SaaS</span>
            </div>

            <div class="hf-hero-visual-body">
              <div class="hf-product-shell">
                <aside class="hf-product-sidebar" aria-hidden="true">
                  <div class="hf-product-mark"></div>
                  <span><i class="bi bi-clipboard2-check"></i></span>
                  <span class="is-active"><i class="bi bi-layers"></i></span>
                  <span><i class="bi bi-cash-coin"></i></span>
                  <span><i class="bi bi-graph-up"></i></span>
                  <span><i class="bi bi-stars"></i></span>
                </aside>

                <div class="hf-product-main">
                  <div class="hf-product-head">
                    <div>
                      <p>Plano recomendado</p>
                      <h2>Profissional</h2>
                    </div>
                    <span class="hf-status-pill"><i class="bi bi-stars" aria-hidden="true"></i> Melhor custo-beneficio</span>
                  </div>

                  <div class="hf-metric-grid">
                    <div class="hf-metric-card metric-blue">
                      <span><i class="bi bi-people" aria-hidden="true"></i> Usuarios</span>
                      <strong>5</strong>
                      <small>Equipe operacional</small>
                    </div>
                    <div class="hf-metric-card metric-green">
                      <span><i class="bi bi-clipboard-check" aria-hidden="true"></i> OS por mes</span>
                      <strong>500</strong>
                      <small>Volume profissional</small>
                    </div>
                    <div class="hf-metric-card metric-purple">
                      <span><i class="bi bi-bar-chart-line" aria-hidden="true"></i> Relatorios</span>
                      <strong>Exporta</strong>
                      <small>Mais controle</small>
                    </div>
                  </div>

                  <div class="hf-panel-card">
                    <div class="hf-panel-head">
                      <h3>Recursos em destaque</h3>
                      <span>Profissional</span>
                    </div>

                    <div class="hf-os-list">
                      <div class="hf-os-row">
                        <span class="hf-os-icon bg-blue"><i class="bi bi-wallet2"></i></span>
                        <div>
                          <strong>Financeiro completo</strong>
                          <small>Lancamentos conectados a operacao</small>
                        </div>
                        <span class="hf-tag tag-primary">Incluido</span>
                      </div>
                      <div class="hf-os-row">
                        <span class="hf-os-icon bg-green"><i class="bi bi-palette"></i></span>
                        <div>
                          <strong>Logo e cores da empresa</strong>
                          <small>Mais identidade para o painel</small>
                        </div>
                        <span class="hf-tag tag-success">Branding</span>
                      </div>
                      <div class="hf-os-row">
                        <span class="hf-os-icon bg-purple"><i class="bi bi-headset"></i></span>
                        <div>
                          <strong>Suporte prioritario</strong>
                          <small>Atendimento mais rapido</small>
                        </div>
                        <span class="hf-tag tag-warning">Prioritario</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="hf-section" id="planos">
      <div class="container">
        <div class="text-center mx-auto" style="max-width: 780px;">
          <span class="hf-section-kicker">
            <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
            Comparativo
          </span>
          <h2 class="hf-section-title mx-auto">Planos simples para vender, testar e evoluir.</h2>
          <p class="hf-section-lead mx-auto">Nesta primeira fase, os planos preparam a estrutura comercial do SaaS. A cobranca recorrente pode entrar depois, sem travar a venda inicial.</p>
        </div>

        <div class="hf-plan-proof justify-content-center" aria-label="Destaques comerciais">
          <span><i class="bi bi-hourglass-split" aria-hidden="true"></i> 14 dias gratis</span>
          <span><i class="bi bi-credit-card-2-front" aria-hidden="true"></i> Sem cartao</span>
          <span><i class="bi bi-lightning-charge" aria-hidden="true"></i> Ativacao imediata</span>
        </div>

        <?php
          $basicoPlan = $publicPlans['basico'] ?? hfPublicPlanCatalogFallback()['basico'];
          $profissionalPlan = $publicPlans['profissional'] ?? hfPublicPlanCatalogFallback()['profissional'];
          $premiumPlan = $publicPlans['premium'] ?? hfPublicPlanCatalogFallback()['premium'];
        ?>

        <div class="hf-plan-grid">
          <article class="hf-plan-card">
            <span class="hf-plan-badge">14 dias gratis</span>
            <h3 class="hf-plan-name">B&aacute;sico</h3>
            <p class="hf-plan-description">Para come&ccedil;ar a organizar a assist&ecirc;ncia.</p>
            <div class="hf-plan-price-wrap" aria-label="Preco do plano Basico">
              <div class="hf-plan-price-main">
                <?= htmlspecialchars(hfPublicPlanMoney((int)$basicoPlan['monthly_cents']), ENT_QUOTES, 'UTF-8') ?>
                <span>/mes</span>
              </div>
              <div class="hf-plan-price-annual">
                <?= htmlspecialchars(hfPublicPlanMoney((int)$basicoPlan['annual_cents']), ENT_QUOTES, 'UTF-8') ?>/ano
              </div>
              <div class="hf-plan-savings">Economize 2 meses</div>
            </div>
            <ul class="hf-plan-list">
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= number_format((int)$basicoPlan['user_limit'], 0, ',', '.') ?> usuarios</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= number_format((int)$basicoPlan['monthly_os_limit'], 0, ',', '.') ?> OS/mes</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Clientes ilimitados</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Produtos e servicos</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Financeiro simples</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Relatorios basicos</li>
            </ul>
            <a class="btn hf-btn-secondary w-100" href="/cadastro.php?plano=basico">Come&ccedil;ar gr&aacute;tis por 14 dias</a>
          </article>

          <article class="hf-plan-card is-featured">
            <span class="hf-plan-badge">Recomendado</span>
            <h3 class="hf-plan-name">Profissional</h3>
            <p class="hf-plan-description">Para equipes que precisam de controle completo no dia a dia.</p>
            <div class="hf-plan-price-wrap" aria-label="Preco do plano Profissional">
              <div class="hf-plan-price-main">
                <?= htmlspecialchars(hfPublicPlanMoney((int)$profissionalPlan['monthly_cents']), ENT_QUOTES, 'UTF-8') ?>
                <span>/mes</span>
              </div>
              <div class="hf-plan-price-annual">
                <?= htmlspecialchars(hfPublicPlanMoney((int)$profissionalPlan['annual_cents']), ENT_QUOTES, 'UTF-8') ?>/ano
              </div>
              <div class="hf-plan-savings">Economize 2 meses</div>
            </div>
            <ul class="hf-plan-list">
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= number_format((int)$profissionalPlan['user_limit'], 0, ',', '.') ?> usuarios</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= number_format((int)$profissionalPlan['monthly_os_limit'], 0, ',', '.') ?> OS/mes</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Financeiro completo</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Relatorios com exportacao</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Logo e cores da empresa</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Suporte prioritario</li>
            </ul>
            <a class="btn btn-primary hf-btn-primary w-100" href="/cadastro.php?plano=profissional">Come&ccedil;ar gr&aacute;tis por 14 dias</a>
          </article>

          <article class="hf-plan-card is-premium">
            <span class="hf-plan-badge is-premium">Premium</span>
            <h3 class="hf-plan-name">Premium</h3>
            <p class="hf-plan-description">Para equipes maiores, com mais volume, branding forte e atendimento prioritario.</p>
            <div class="hf-plan-price-wrap" aria-label="Preco do plano Premium">
              <div class="hf-plan-price-main">
                <?= htmlspecialchars(hfPublicPlanMoney((int)$premiumPlan['monthly_cents']), ENT_QUOTES, 'UTF-8') ?>
                <span>/mes</span>
              </div>
              <div class="hf-plan-price-annual">
                <?= htmlspecialchars(hfPublicPlanMoney((int)$premiumPlan['annual_cents']), ENT_QUOTES, 'UTF-8') ?>/ano
              </div>
              <div class="hf-plan-savings">Economize 2 meses</div>
            </div>
            <ul class="hf-plan-list">
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= number_format((int)$premiumPlan['user_limit'], 0, ',', '.') ?> usuarios</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= number_format((int)$premiumPlan['monthly_os_limit'], 0, ',', '.') ?> OS/mes</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Relatorios avancados</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Branding completo</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Dominio proprio futuramente</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Suporte prioritario</li>
            </ul>
            <a class="btn hf-btn-secondary w-100" href="/cadastro.php?plano=premium">Testar agora</a>
          </article>
        </div>
      </div>
    </section>

    <section class="hf-section-tight">
      <div class="container">
        <div class="hf-cta-band">
          <div class="row align-items-center g-4">
            <div class="col-lg-7">
              <span class="hf-section-kicker">Ainda em duvida?</span>
              <h2 class="h1 fw-bold mb-3">Fale com a gente e escolha o melhor plano.</h2>
              <p class="mb-0 text-secondary fs-5">Se sua assistencia tem uma rotina especifica, podemos orientar qual plano combina melhor com o volume de OS e equipe.</p>
            </div>
            <div class="col-lg-5">
              <div class="d-grid d-sm-flex justify-content-lg-end gap-2">
                <a class="btn btn-primary hf-btn-primary" href="/cadastro.php?plano=profissional">Come&ccedil;ar teste gr&aacute;tis</a>
                <a class="btn hf-btn-secondary" href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                  <i class="bi bi-whatsapp" aria-hidden="true"></i>
                  Falar no WhatsApp
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <a class="hf-floating-whatsapp" href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" aria-label="Falar no WhatsApp">
      <i class="bi bi-whatsapp" aria-hidden="true"></i>
      <span>WhatsApp</span>
    </a>
<?php include __DIR__.'/_site_end.php'; ?>
