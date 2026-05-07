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
    <section class="hf-hero">
      <div class="container">
        <div class="hf-hero-grid">
          <div>
            <span class="hf-section-kicker">
              <i class="bi bi-rocket-takeoff" aria-hidden="true"></i>
              Teste gratis
            </span>

            <h1 class="hf-hero-title">Crie o acesso da sua assist&ecirc;ncia.</h1>

            <p class="hf-hero-text">
              Preencha os dados da empresa para preparar seu ambiente de teste no HelpDesk Facil. Depois, voce acessa o painel com o codigo da empresa, e-mail e senha.
            </p>

            <div class="hf-hero-proof" aria-label="Resumo do cadastro">
              <span><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Sem cobranca agora</span>
              <span><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Ambiente por empresa</span>
              <span><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Plano <?= htmlspecialchars($planoAtual['nome'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          </div>

          <div class="hf-hero-visual" aria-label="Resumo do teste gratis">
            <div class="hf-hero-visual-bar">
              <div class="hf-window-controls">
                <span class="hf-hero-dot"></span>
                <span class="hf-hero-dot"></span>
                <span class="hf-hero-dot"></span>
              </div>
              <div class="hf-app-pill">
                <i class="bi bi-shield-check" aria-hidden="true"></i>
                cadastro.helpdeskfacil.com
              </div>
              <span class="hf-live-badge">Trial</span>
            </div>

            <div class="hf-hero-visual-body">
              <div class="hf-product-shell">
                <aside class="hf-product-sidebar" aria-hidden="true">
                  <div class="hf-product-mark"></div>
                  <span class="is-active"><i class="bi bi-building-add"></i></span>
                  <span><i class="bi bi-person-check"></i></span>
                  <span><i class="bi bi-key"></i></span>
                  <span><i class="bi bi-stars"></i></span>
                </aside>

                <div class="hf-product-main">
                  <div class="hf-product-head">
                    <div>
                      <p>Configuracao inicial</p>
                      <h2>Empresa em teste</h2>
                    </div>
                    <span class="hf-status-pill"><i class="bi bi-clock-history" aria-hidden="true"></i> Aguardando criacao</span>
                  </div>

                  <div class="hf-metric-grid">
                    <div class="hf-metric-card metric-blue">
                      <span><i class="bi bi-layers" aria-hidden="true"></i> Plano</span>
                      <strong><?= htmlspecialchars($planoAtual['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                      <small>Selecionado</small>
                    </div>
                    <div class="hf-metric-card metric-green">
                      <span><i class="bi bi-people" aria-hidden="true"></i> Usuarios</span>
                      <strong><?= htmlspecialchars($planoAtual['usuarios'], ENT_QUOTES, 'UTF-8') ?></strong>
                      <small>Limite inicial</small>
                    </div>
                    <div class="hf-metric-card metric-purple">
                      <span><i class="bi bi-clipboard-check" aria-hidden="true"></i> OS</span>
                      <strong><?= htmlspecialchars($planoAtual['os'], ENT_QUOTES, 'UTF-8') ?></strong>
                      <small>Volume mensal</small>
                    </div>
                  </div>

                  <div class="hf-panel-card">
                    <div class="hf-panel-head">
                      <h3>Proximos passos</h3>
                      <span>Trial</span>
                    </div>

                    <div class="hf-os-list">
                      <div class="hf-os-row">
                        <span class="hf-os-icon bg-blue"><i class="bi bi-building"></i></span>
                        <div>
                          <strong>Criar empresa</strong>
                          <small>Gerar ambiente separado por codigo</small>
                        </div>
                        <span class="hf-tag tag-primary">1</span>
                      </div>
                      <div class="hf-os-row">
                        <span class="hf-os-icon bg-green"><i class="bi bi-person-lock"></i></span>
                        <div>
                          <strong>Criar administrador</strong>
                          <small>Responsavel acessa o painel</small>
                        </div>
                        <span class="hf-tag tag-success">2</span>
                      </div>
                      <div class="hf-os-row">
                        <span class="hf-os-icon bg-purple"><i class="bi bi-speedometer2"></i></span>
                        <div>
                          <strong>Liberar painel</strong>
                          <small>Comecar a cadastrar OS e clientes</small>
                        </div>
                        <span class="hf-tag tag-warning">3</span>
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

    <section class="hf-section">
      <div class="container">
        <div class="row g-4 align-items-start">
          <div class="col-lg-7">
            <div class="hf-plan-card">
              <span class="hf-plan-badge">Teste gratis</span>
              <h2 class="h1 fw-bold mb-2">Dados para criar sua conta</h2>
              <p class="text-secondary mb-4">Preencha os dados abaixo para criar o ambiente de teste da sua empresa.</p>

              <?php if ($signupError): ?>
                <div class="alert alert-danger rounded-4 mb-4" role="alert">
                  <i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>
                  <?= htmlspecialchars($signupError, ENT_QUOTES, 'UTF-8') ?>
                </div>
              <?php endif; ?>

              <form method="post" action="/cadastro_save.php">
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
                    <label class="form-label fw-bold" for="empresa_slug">Codigo da empresa</label>
                    <input class="form-control form-control-lg" id="empresa_slug" name="empresa_slug" type="text" placeholder="assistencia-pro" autocomplete="organization" value="<?= hfSignupField($oldInput, 'empresa_slug') ?>" required>
                    <div class="form-text">Voce usara esse codigo no login da empresa.</div>
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

          <div class="col-lg-5">
            <aside class="hf-plan-card is-featured">
              <span class="hf-plan-badge">Plano selecionado</span>
              <h2 class="hf-plan-name"><?= htmlspecialchars($planoAtual['nome'], ENT_QUOTES, 'UTF-8') ?></h2>
              <p class="hf-plan-description"><?= htmlspecialchars($planoAtual['descricao'], ENT_QUOTES, 'UTF-8') ?></p>

              <ul class="hf-plan-list">
                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= htmlspecialchars($planoAtual['usuarios'], ENT_QUOTES, 'UTF-8') ?></li>
                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= htmlspecialchars($planoAtual['os'], ENT_QUOTES, 'UTF-8') ?></li>
                <?php foreach ($planoAtual['destaques'] as $destaque): ?>
                  <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= htmlspecialchars($destaque, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
              </ul>

              <div class="border rounded-4 p-3 bg-white mt-4">
                <div class="d-flex gap-3">
                  <span class="hf-card-icon mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i></span>
                  <div>
                    <h3 class="h6 fw-bold mb-1">Sem pagamento agora</h3>
                    <p class="text-secondary mb-0">Esta tela prepara o cadastro do trial. A cobranca recorrente entra em uma etapa futura.</p>
                  </div>
                </div>
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

    <a class="hf-floating-whatsapp" href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" aria-label="Falar no WhatsApp">
      <i class="bi bi-whatsapp" aria-hidden="true"></i>
      <span>WhatsApp</span>
    </a>
<?php include __DIR__.'/_site_end.php'; ?>
