<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$success = $_SESSION['HF_SIGNUP_SUCCESS'] ?? null;
if (!$success || !is_array($success)) {
  header('Location: /cadastro.php');
  exit;
}

unset($_SESSION['HF_SIGNUP_SUCCESS']);

$planos = [
  'basico' => 'Basico',
  'profissional' => 'Profissional',
  'premium' => 'Premium',
];

$empresaNome = trim((string)($success['empresa_nome'] ?? ''));
$empresaSlug = trim((string)($success['empresa_slug'] ?? ''));
$email = trim((string)($success['email'] ?? ''));
$planoKey = strtolower(trim((string)($success['plano'] ?? 'profissional')));
$planoNome = $planos[$planoKey] ?? 'Profissional';

$siteTitle = 'Teste gratis criado - HelpDesk Facil';
$siteDescription = 'Seu teste gratis do HelpDesk Facil foi criado com sucesso.';
$siteBodyClass = 'hf-signup-success-page';
$whatsappUrl = 'https://wa.me/5500000000000?text=Meu%20teste%20gratis%20do%20HelpDesk%20Facil%20foi%20criado';

include __DIR__.'/_site_start.php';
?>
    <section class="hf-hero">
      <div class="container">
        <div class="hf-hero-grid">
          <div>
            <span class="hf-section-kicker">
              <i class="bi bi-check-circle" aria-hidden="true"></i>
              Teste criado
            </span>

            <h1 class="hf-hero-title">Seu ambiente est&aacute; pronto.</h1>

            <p class="hf-hero-text">
              Use o c&oacute;digo da empresa, e-mail e senha cadastrados para acessar o painel do HelpDesk Facil.
            </p>

            <div class="hf-hero-actions">
              <a class="btn btn-primary hf-btn-primary" href="/login.php">
                Ir para login
                <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
              </a>
              <a class="btn hf-btn-whatsapp" href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                <i class="bi bi-whatsapp" aria-hidden="true"></i>
                Falar no WhatsApp
              </a>
            </div>

            <div class="hf-hero-proof" aria-label="Dados de acesso">
              <span><i class="bi bi-building-check" aria-hidden="true"></i> Empresa criada</span>
              <span><i class="bi bi-person-check" aria-hidden="true"></i> Administrador ativo</span>
              <span><i class="bi bi-shield-check" aria-hidden="true"></i> Dados por tenant</span>
            </div>
          </div>

          <div class="hf-hero-visual" aria-label="Resumo do cadastro criado">
            <div class="hf-hero-visual-bar">
              <div class="hf-window-controls">
                <span class="hf-hero-dot"></span>
                <span class="hf-hero-dot"></span>
                <span class="hf-hero-dot"></span>
              </div>
              <div class="hf-app-pill">
                <i class="bi bi-shield-check" aria-hidden="true"></i>
                login.helpdeskfacil.com
              </div>
              <span class="hf-live-badge">Pronto</span>
            </div>

            <div class="hf-hero-visual-body">
              <div class="hf-product-shell">
                <aside class="hf-product-sidebar" aria-hidden="true">
                  <div class="hf-product-mark"></div>
                  <span class="is-active"><i class="bi bi-check2-circle"></i></span>
                  <span><i class="bi bi-building"></i></span>
                  <span><i class="bi bi-person-lock"></i></span>
                  <span><i class="bi bi-box-arrow-in-right"></i></span>
                </aside>

                <div class="hf-product-main">
                  <div class="hf-product-head">
                    <div>
                      <p>Cadastro finalizado</p>
                      <h2>Acesso liberado</h2>
                    </div>
                    <span class="hf-status-pill"><i class="bi bi-stars" aria-hidden="true"></i> Plano <?= htmlspecialchars($planoNome, ENT_QUOTES, 'UTF-8') ?></span>
                  </div>

                  <div class="hf-panel-card">
                    <div class="hf-panel-head">
                      <h3>Dados para login</h3>
                      <span>Guarde estes dados</span>
                    </div>

                    <div class="hf-os-list">
                      <div class="hf-os-row">
                        <span class="hf-os-icon bg-blue"><i class="bi bi-building"></i></span>
                        <div>
                          <strong><?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?></strong>
                          <small>Nome da empresa</small>
                        </div>
                        <span class="hf-tag tag-primary">Empresa</span>
                      </div>
                      <div class="hf-os-row">
                        <span class="hf-os-icon bg-green"><i class="bi bi-key"></i></span>
                        <div>
                          <strong><?= htmlspecialchars($empresaSlug, ENT_QUOTES, 'UTF-8') ?></strong>
                          <small>Codigo da empresa</small>
                        </div>
                        <span class="hf-tag tag-success">Codigo</span>
                      </div>
                      <div class="hf-os-row">
                        <span class="hf-os-icon bg-purple"><i class="bi bi-envelope"></i></span>
                        <div>
                          <strong><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></strong>
                          <small>E-mail do administrador</small>
                        </div>
                        <span class="hf-tag tag-warning">Admin</span>
                      </div>
                    </div>
                  </div>

                  <div class="hf-panel-card mt-3">
                    <div class="d-flex gap-3">
                      <span class="hf-card-icon mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i></span>
                      <div>
                        <h3 class="h6 fw-bold mb-1">Como acessar</h3>
                        <p class="text-secondary mb-0">No login, informe o c&oacute;digo da empresa, o e-mail do administrador e a senha cadastrada. A senha n&atilde;o &eacute; exibida por seguran&ccedil;a.</p>
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

    <section class="hf-section-tight">
      <div class="container">
        <div class="hf-cta-band">
          <div class="row align-items-center g-4">
            <div class="col-lg-8">
              <span class="hf-section-kicker">Pr&oacute;ximo passo</span>
              <h2 class="h1 fw-bold mb-3">Entre no painel e comece a cadastrar suas OS.</h2>
              <p class="mb-0 text-secondary fs-5">A partir de agora, sua empresa j&aacute; pode acessar o HelpDesk Facil usando os dados criados no teste gr&aacute;tis.</p>
            </div>
            <div class="col-lg-4">
              <div class="d-grid">
                <a class="btn btn-primary hf-btn-primary" href="/login.php">
                  Ir para login
                  <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
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
