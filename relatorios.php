<?php
require_once __DIR__ . '/_layout_start.php';
require_once __DIR__ . '/auth.php';

requireLogin();
?>

<?php include __DIR__ . '/_sidebar.php'; ?>

<main class="hf-content hf-reports-page">
  <div class="container-fluid py-4 hf-reports-wrap">
    <header class="hf-reports-header mb-4">
      <div class="hf-header-icon">
        <i class="bi bi-bar-chart-line"></i>
      </div>

      <div class="hf-header-copy">
        <div class="hf-page-kicker">Central de relat&oacute;rios</div>
        <h1>Relat&oacute;rios</h1>
        <p>Consulte informa&ccedil;&otilde;es operacionais e financeiras com filtros claros e exporta&ccedil;&atilde;o quando necess&aacute;rio.</p>
      </div>
    </header>

    <section class="hf-report-grid" aria-label="Modulos de relatorios">
      <a class="hf-report-card hf-report-card-primary" href="/relatorio_os.php?m=relatorios">
        <span class="hf-report-topline">
          <span class="hf-report-icon">
            <i class="bi bi-clipboard2-check"></i>
          </span>
          <span class="hf-report-badge">Mais usado</span>
        </span>

        <span class="hf-report-body">
          <span class="hf-report-title">Ordens de Servi&ccedil;o</span>
          <span class="hf-report-text">
            Acompanhe OS por per&iacute;odo, status, t&eacute;cnico, cliente e situa&ccedil;&atilde;o financeira.
          </span>
        </span>

        <span class="hf-report-footer">
          <span>Visualizar relat&oacute;rio</span>
          <i class="bi bi-arrow-right"></i>
        </span>
      </a>

      <a class="hf-report-card hf-report-card-muted" href="/relatorio_financeiro.php?m=relatorios">
        <span class="hf-report-topline">
          <span class="hf-report-icon">
            <i class="bi bi-cash-coin"></i>
          </span>
        </span>

        <span class="hf-report-body">
          <span class="hf-report-title">Financeiro</span>
          <span class="hf-report-text">
            Consolida&ccedil;&atilde;o de receitas, recebimentos, despesas e previs&atilde;o de caixa.
          </span>
        </span>

        <span class="hf-report-footer">
          <span>Visualizar relat&oacute;rio</span>
          <i class="bi bi-arrow-right"></i>
        </span>
      </a>

      <div class="hf-report-card hf-report-card-muted" aria-disabled="true">
        <span class="hf-report-topline">
          <span class="hf-report-icon">
            <i class="bi bi-people"></i>
          </span>
          <span class="hf-coming-label">Em breve</span>
        </span>

        <span class="hf-report-body">
          <span class="hf-report-title">Clientes</span>
          <span class="hf-report-text">
            An&aacute;lises de carteira, recorr&ecirc;ncia e hist&oacute;rico de atendimento.
          </span>
        </span>
      </div>
    </section>
  </div>
</main>

<style>
.hf-reports-page {
  min-height: calc(100vh - var(--topbar-h));
  background:
    radial-gradient(circle at 20% 0%, rgba(var(--bs-primary-rgb), .08), transparent 24rem),
    linear-gradient(180deg, #f8fafc 0%, #eef3f8 100%);
}

.hf-reports-wrap {
  max-width: 1240px;
}

.hf-reports-header {
  max-width: 760px;
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: .25rem .1rem .35rem;
}

.hf-header-icon {
  width: 48px;
  height: 48px;
  flex: 0 0 48px;
  display: grid;
  place-items: center;
  border: 1px solid rgba(var(--bs-primary-rgb), .18);
  border-radius: .95rem;
  color: var(--bs-primary);
  background: rgba(var(--bs-primary-rgb), .08);
  font-size: 1.28rem;
}

.hf-header-copy {
  min-width: 0;
}

.hf-page-kicker {
  margin-bottom: .16rem;
  color: rgba(var(--bs-primary-rgb), .9);
  font-size: .72rem;
  font-weight: 850;
  text-transform: uppercase;
  letter-spacing: .08em;
}

.hf-reports-header h1 {
  margin: 0;
  color: #0f172a;
  font-size: clamp(1.55rem, 2.2vw, 2rem);
  font-weight: 900;
  line-height: 1.08;
}

.hf-reports-header p {
  max-width: 620px;
  margin: .45rem 0 0;
  color: #64748b;
  font-size: .95rem;
  line-height: 1.55;
}

.hf-report-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr) minmax(0, 1fr);
  gap: 1rem;
  align-items: stretch;
}

.hf-report-card {
  min-height: 230px;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1.15rem;
  border: 1px solid rgba(148, 163, 184, .22);
  border-radius: 1rem;
  color: inherit;
  text-decoration: none;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 12px 30px rgba(15, 23, 42, .065);
  transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background-color .18s ease;
}

.hf-report-card-primary {
  min-height: 270px;
  border-color: rgba(var(--bs-primary-rgb), .24);
}

.hf-report-card-primary:hover {
  color: inherit;
  transform: translateY(-3px);
  border-color: rgba(var(--bs-primary-rgb), .42);
  background: #fff;
  box-shadow: 0 18px 42px rgba(15, 23, 42, .10);
}

.hf-report-card-muted {
  background: rgba(255, 255, 255, .78);
}

a.hf-report-card-muted:hover {
  color: inherit;
  transform: translateY(-3px);
  border-color: rgba(var(--bs-primary-rgb), .30);
  background: #fff;
  box-shadow: 0 18px 42px rgba(15, 23, 42, .09);
}

.hf-report-topline {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: .75rem;
}

.hf-report-icon {
  width: 44px;
  height: 44px;
  flex: 0 0 44px;
  display: grid;
  place-items: center;
  border-radius: .9rem;
  color: var(--bs-primary);
  background: rgba(var(--bs-primary-rgb), .09);
  font-size: 1.2rem;
}

.hf-report-card-primary .hf-report-icon {
  width: 52px;
  height: 52px;
  flex-basis: 52px;
  font-size: 1.42rem;
}

.hf-report-badge,
.hf-coming-label {
  display: inline-flex;
  align-items: center;
  padding: .32rem .56rem;
  border-radius: 999px;
  font-size: .72rem;
  font-weight: 850;
  white-space: nowrap;
}

.hf-report-badge {
  color: #047857;
  background: #d1fae5;
}

.hf-coming-label {
  color: #64748b;
  background: #f1f5f9;
}

.hf-report-body {
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.hf-report-title {
  color: #0f172a;
  font-size: 1.08rem;
  font-weight: 900;
  line-height: 1.25;
}

.hf-report-card-primary .hf-report-title {
  font-size: 1.24rem;
}

.hf-report-text {
  max-width: 470px;
  margin-top: .45rem;
  color: #64748b;
  font-size: .9rem;
  line-height: 1.55;
}

.hf-report-footer {
  width: fit-content;
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  margin-top: auto;
  padding: .56rem .74rem;
  border: 1px solid rgba(var(--bs-primary-rgb), .18);
  border-radius: .78rem;
  color: var(--bs-primary);
  background: rgba(var(--bs-primary-rgb), .07);
  font-size: .82rem;
  font-weight: 850;
}

.hf-report-card-primary:hover .hf-report-footer {
  color: #fff;
  background: var(--bs-primary);
  border-color: var(--bs-primary);
  box-shadow: 0 10px 18px rgba(var(--bs-primary-rgb), .18);
}

a.hf-report-card-muted:hover .hf-report-footer {
  color: #fff;
  background: var(--bs-primary);
  border-color: var(--bs-primary);
  box-shadow: 0 10px 18px rgba(var(--bs-primary-rgb), .16);
}

@media (max-width: 1199.98px) {
  .hf-report-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .hf-report-card-primary {
    grid-column: 1 / -1;
  }
}

@media (max-width: 767.98px) {
  .hf-reports-page {
    padding-left: .25rem;
    padding-right: .25rem;
  }

  .hf-reports-header {
    gap: .8rem;
  }

  .hf-header-icon {
    width: 44px;
    height: 44px;
    flex-basis: 44px;
    border-radius: .85rem;
  }

  .hf-report-grid {
    grid-template-columns: 1fr;
  }

  .hf-report-card,
  .hf-report-card-primary {
    min-height: auto;
  }
}

@media (max-width: 575.98px) {
  .hf-reports-header {
    flex-direction: column;
  }

  .hf-report-card {
    padding: 1rem;
  }
}

[data-bs-theme="dark"] .hf-reports-page {
  background:
    radial-gradient(circle at 20% 0%, rgba(var(--bs-primary-rgb), .14), transparent 24rem),
    linear-gradient(180deg, #111827 0%, #0f172a 100%);
}

[data-bs-theme="dark"] .hf-reports-header h1,
[data-bs-theme="dark"] .hf-report-title {
  color: #e5e7eb;
}

[data-bs-theme="dark"] .hf-report-card {
  background: rgba(17, 24, 39, .9);
  border-color: rgba(148, 163, 184, .18);
}

[data-bs-theme="dark"] .hf-report-card-primary {
  border-color: rgba(var(--bs-primary-rgb), .28);
}

[data-bs-theme="dark"] .hf-report-card-primary:hover {
  background: rgba(17, 24, 39, .98);
}

[data-bs-theme="dark"] a.hf-report-card-muted:hover {
  background: rgba(17, 24, 39, .98);
}

[data-bs-theme="dark"] .hf-coming-label {
  color: #cbd5e1;
  background: rgba(15, 23, 42, .82);
}
</style>

<?php require_once __DIR__ . '/_layout_end.php'; ?>
