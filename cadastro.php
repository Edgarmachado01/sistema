<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$siteTitle = 'Teste gratis - HelpDesk Facil';
$siteDescription = 'Cadastre sua empresa para iniciar o teste gratis do HelpDesk Facil.';
$siteBodyClass = 'hf-signup-page';
$csrfToken = $_SESSION['csrf_token'];
$signupError = $_SESSION['HF_SIGNUP_ERROR'] ?? '';
$oldInput = $_SESSION['HF_SIGNUP_OLD'] ?? [];
unset($_SESSION['HF_SIGNUP_ERROR'], $_SESSION['HF_SIGNUP_OLD']);

function hfSignupField($oldInput, $field)
{
  return htmlspecialchars((string)($oldInput[$field] ?? ''), ENT_QUOTES, 'UTF-8');
}

$planos = [
  'basico' => [
    'nome' => 'Basico',
    'descricao' => 'Para comecar a organizar a assistencia.',
    'usuarios' => '2 usuarios',
    'os' => '100 OS/mes',
    'destaques' => ['Clientes ilimitados', 'Produtos e servicos', 'Financeiro simples', 'Relatorios basicos'],
  ],
  'profissional' => [
    'nome' => 'Profissional',
    'descricao' => 'Recomendado para equipes que precisam de controle completo.',
    'usuarios' => '5 usuarios',
    'os' => '500 OS/mes',
    'destaques' => ['Financeiro completo', 'Relatorios com exportacao', 'Logo e cores da empresa', 'Suporte prioritario'],
  ],
  'premium' => [
    'nome' => 'Premium',
    'descricao' => 'Para equipes maiores, com mais volume e personalizacao.',
    'usuarios' => '15 usuarios',
    'os' => '2.000 OS/mes',
    'destaques' => ['Relatorios avancados', 'Branding completo', 'Dominio proprio futuramente', 'Suporte premium'],
  ],
];

$planoEscolhido = strtolower(trim($_GET['plano'] ?? ($oldInput['plano'] ?? 'profissional')));
if (!isset($planos[$planoEscolhido])) {
  $planoEscolhido = 'profissional';
}
$planoAtual = $planos[$planoEscolhido];
$whatsappUrl = 'https://wa.me/5500000000000?text=Quero%20come%C3%A7ar%20um%20teste%20gratis%20do%20HelpDesk%20Facil';

include __DIR__.'/_site_start.php';
?>
    <section class="hf-section pt-4 pb-2">
      <div class="container">
        <div class="hf-signup-head-card">
          <div>
            <span class="hf-section-kicker">
              <i class="bi bi-rocket-takeoff" aria-hidden="true"></i>
              Teste gratis
            </span>
            <h1 class="h3 fw-bold mb-1">Crie sua conta de teste</h1>
            <p class="text-secondary mb-0">Preencha os dados abaixo e comece a usar o painel em instantes.</p>
          </div>
          <span class="hf-plan-chip">
            Plano <?= htmlspecialchars($planoAtual['nome'], ENT_QUOTES, 'UTF-8') ?>
          </span>
        </div>
      </div>
    </section>

    <section class="hf-section">
      <div class="container">
        <div class="row g-4 align-items-start">
          <div class="col-lg-8">
            <div class="hf-plan-card hf-signup-form-card">
              <span class="hf-plan-badge">Teste gratis</span>
              <h2 class="h4 fw-bold mb-2">Cadastro da empresa</h2>
              <p class="text-secondary mb-4">Informe os dados para liberar seu acesso de teste.</p>

              <?php if ($signupError): ?>
                <div class="alert alert-danger rounded-4 mb-4" role="alert">
                  <i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>
                  <?= htmlspecialchars($signupError, ENT_QUOTES, 'UTF-8') ?>
                </div>
              <?php endif; ?>

              <form method="post" action="/cadastro_save.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="empresa_nome">Nome da empresa</label>
                    <input class="form-control form-control-lg" id="empresa_nome" name="empresa_nome" type="text" placeholder="Ex: Assistencia Pro" autocomplete="organization" value="<?= hfSignupField($oldInput, 'empresa_nome') ?>" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="responsavel_nome">Nome do responsavel</label>
                    <input class="form-control form-control-lg" id="responsavel_nome" name="responsavel_nome" type="text" placeholder="Seu nome" autocomplete="name" value="<?= hfSignupField($oldInput, 'responsavel_nome') ?>" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="email">E-mail</label>
                    <input class="form-control form-control-lg" id="email" name="email" type="email" placeholder="voce@empresa.com" autocomplete="email" value="<?= hfSignupField($oldInput, 'email') ?>" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="whatsapp">WhatsApp</label>
                    <input class="form-control form-control-lg" id="whatsapp" name="whatsapp" type="tel" placeholder="(00) 00000-0000" autocomplete="tel" value="<?= hfSignupField($oldInput, 'whatsapp') ?>" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="documento">CPF/CNPJ</label>
                    <input class="form-control form-control-lg" id="documento" name="documento" type="text" maxlength="20" placeholder="Somente numeros" value="<?= hfSignupField($oldInput, 'documento') ?>" required>
                    <div class="form-text">Usado para validar o cadastro e apoiar a recuperacao do codigo da empresa.</div>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="plano">Plano escolhido</label>
                    <select class="form-select form-select-lg" id="plano" name="plano" required>
                      <?php foreach ($planos as $codigo => $plano): ?>
                        <option value="<?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?>" <?= $codigo === $planoEscolhido ? 'selected' : '' ?>>
                          <?= htmlspecialchars($plano['nome'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="senha">Senha</label>
                    <input class="form-control form-control-lg" id="senha" name="senha" type="password" placeholder="Minimo 8 caracteres" autocomplete="new-password" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="senha_confirmar">Confirmar senha</label>
                    <input class="form-control form-control-lg" id="senha_confirmar" name="senha_confirmar" type="password" placeholder="Repita a senha" autocomplete="new-password" required>
                  </div>

                  <div class="col-12">
                    <button class="btn btn-primary hf-btn-primary w-100" type="submit">
                      Criar teste gr&aacute;tis
                      <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>

          <div class="col-lg-4">
            <aside class="hf-plan-card hf-signup-side-card is-featured">
              <span class="hf-plan-badge">Seu acesso</span>
              <h2 class="h5 fw-bold mb-2">Teste gratis do HelpDesk Facil</h2>
              <p class="text-secondary mb-3">Plano inicial: <strong><?= htmlspecialchars($planoAtual['nome'], ENT_QUOTES, 'UTF-8') ?></strong></p>

              <ul class="hf-plan-list hf-signup-side-list">
                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> teste gratis</li>
                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> sem compromisso</li>
                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> acesso imediato</li>
                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> dados protegidos</li>
                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> codigo da empresa gerado automaticamente</li>
              </ul>

              <div class="hf-signup-side-note">
                <div><strong><?= htmlspecialchars($planoAtual['usuarios'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                <small>Limite inicial de usuarios</small>
              </div>
              <div class="hf-signup-side-note">
                <div><strong><?= htmlspecialchars($planoAtual['os'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                <small>Capacidade de ordens de servico</small>
              </div>

              <a class="btn hf-btn-secondary w-100 mt-3" href="/planos.php">Ver outros planos</a>
              <a class="btn hf-btn-secondary w-100 mt-2" href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                <i class="bi bi-whatsapp" aria-hidden="true"></i>
                Tirar duvida no WhatsApp
              </a>
            </aside>
          </div>
        </div>
      </div>
    </section>

    <style>
      .hf-signup-head-card {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .9rem;
        border: 1px solid rgba(148,163,184,.22);
        border-radius: 1rem;
        background: #fff;
        padding: 1rem 1.1rem;
      }

      .hf-plan-chip {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: .38rem .72rem;
        background: rgba(var(--bs-primary-rgb), .08);
        color: var(--bs-primary);
        font-weight: 700;
        font-size: .85rem;
      }

      .hf-signup-form-card {
        padding-top: 1.15rem;
      }

      .hf-signup-side-card {
        position: sticky;
        top: 1rem;
      }

      .hf-signup-side-list li {
        font-size: .95rem;
      }

      .hf-signup-side-note {
        border: 1px solid rgba(148,163,184,.22);
        border-radius: .8rem;
        padding: .65rem .75rem;
        background: #fff;
        margin-top: .6rem;
      }

      .hf-signup-side-note strong {
        font-size: 1rem;
      }

      .hf-signup-side-note small {
        color: #64748b;
      }

      @media (max-width: 991.98px) {
        .hf-signup-side-card {
          position: static;
        }
      }
    </style>

    <a class="hf-floating-whatsapp" href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" aria-label="Falar no WhatsApp">
      <i class="bi bi-whatsapp" aria-hidden="true"></i>
      <span>WhatsApp</span>
    </a>
<?php include __DIR__.'/_site_end.php'; ?>
