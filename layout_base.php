<?php /* layout_base.php — dashboard com seletor de tema */ 
// (Opcional) Branding por tenant: se existir brand.php, usa como padrão
$brand = ['primary'=>'#0d6efd','mode'=>'light','logo'=>null,'name'=>'Help Fácil'];
if (file_exists(__DIR__.'/brand.php')) {
  require __DIR__.'/brand.php';
  $b = brandFromRequest();
  $brand['primary'] = $b['primary'] ?? $brand['primary'];
  $brand['mode']    = $b['mode'] ?? $brand['mode'];
  $brand['logo']    = $b['logo'] ?? null;
  $brand['name']    = $b['name'] ?? 'Help Fácil';
}
$hex = ltrim($brand['primary'],'#'); if(strlen($hex)!==6)$hex='0d6efd';
list($r,$g,$b) = sscanf($hex, "%02x%02x%02x");
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="<?= $brand['mode']==='dark'?'dark':'light' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Help Fácil — Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/theme.css">
  <style>
    :root{ --bs-primary:#<?= $hex ?>; --bs-primary-rgb:<?= "$r,$g,$b" ?>; }
  </style>
</head>
<body>
  <!-- TOPBAR -->
  <nav class="navbar navbar-expand-lg" style="background: var(--bs-primary);">
    <div class="container-fluid">
      <button class="btn btn-light me-2" onclick="document.getElementById('hf-sidebar').classList.toggle('d-none')">
        <i class="bi bi-list"></i>
      </button>
      <a class="navbar-brand text-white fw-semibold" href="#">
        <?php if(!empty($brand['logo'])): ?>
          <img src="<?= htmlspecialchars($brand['logo']) ?>" style="height:28px" alt="Logo">
        <?php else: ?>
          Help Fácil
        <?php endif; ?>
      </a>
      <div class="ms-auto d-flex align-items-center gap-2">
        <button class="btn btn-light btn-sm" onclick="hfToggleSwitcher()"><i class="bi bi-gear"></i></button>
        <button class="btn btn-light btn-sm"><i class="bi bi-bell"></i></button>
        <img src="https://i.pravatar.cc/40" class="rounded-circle" alt="avatar">
      </div>
    </div>
  </nav>

  <!-- SWITCHER (cores) -->
  <div id="hf-switcher" class="hf-switcher">
    <h6 class="mb-2">Tema & cores</h6>
    <div class="form-check mb-1"><span class="hf-swatch" style="background:#0d6efd"></span>
      <input class="form-check-input" type="radio" name="hf-theme" value="primary" onclick="hfSetTheme(this.value)"> Primary
    </div>
    <div class="form-check mb-1"><span class="hf-swatch" style="background:#198754"></span>
      <input class="form-check-input" type="radio" name="hf-theme" value="success" onclick="hfSetTheme(this.value)"> Success
    </div>
    <div class="form-check mb-1"><span class="hf-swatch" style="background:#ffc107"></span>
      <input class="form-check-input" type="radio" name="hf-theme" value="warning" onclick="hfSetTheme(this.value)"> Warning
    </div>
    <div class="form-check mb-1"><span class="hf-swatch" style="background:#dc3545"></span>
      <input class="form-check-input" type="radio" name="hf-theme" value="danger" onclick="hfSetTheme(this.value)"> Danger
    </div>
    <div class="form-check mb-1"><span class="hf-swatch" style="background:#d63384"></span>
      <input class="form-check-input" type="radio" name="hf-theme" value="pink" onclick="hfSetTheme(this.value)"> Pink
    </div>
    <div class="form-check mb-1"><span class="hf-swatch" style="background:#6f42c1"></span>
      <input class="form-check-input" type="radio" name="hf-theme" value="purple" onclick="hfSetTheme(this.value)"> Purple
    </div>
    <div class="form-check mb-1"><span class="hf-swatch" style="background:#111827"></span>
      <input class="form-check-input" type="radio" name="hf-theme" value="inverse" onclick="hfSetTheme(this.value)"> Inverse
    </div>
    <div class="form-check mb-2"><span class="hf-swatch" style="background:#212529"></span>
      <input class="form-check-input" type="radio" name="hf-theme" value="dark" onclick="hfSetTheme(this.value)"> Dark
    </div>
    <button class="btn btn-outline-secondary btn-sm w-100" onclick="document.getElementById('hf-switcher').classList.remove('show')">Fechar</button>
  </div>

  <!-- BODY -->
  <div class="d-flex">
    <!-- SIDEBAR -->
    <aside id="hf-sidebar" class="hf-sidebar p-2">
      <div class="px-3 py-3">
        <div class="d-flex align-items-center gap-2">
          <img src="https://i.pravatar.cc/44" class="rounded-circle" alt="">
          <div>
            <div class="fw-semibold">John Doe</div>
            <div class="text-muted" style="font-size:.85rem">Web Developer</div>
          </div>
        </div>
      </div>
      <hr class="my-2">
      <nav class="nav flex-column">
        <a class="nav-link active" href="#"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
        <a class="nav-link" href="#"><i class="bi bi-clipboard2-check me-2"></i>Ordens de Serviço</a>
        <a class="nav-link" href="#"><i class="bi bi-people me-2"></i>Clientes</a>
        <a class="nav-link" href="#"><i class="bi bi-box-seam me-2"></i>Produtos</a>
        <a class="nav-link" href="#"><i class="bi bi-cash-coin me-2"></i>Financeiro</a>
        <a class="nav-link" href="#"><i class="bi bi-gear me-2"></i>Configurações</a>
      </nav>
    </aside>

    <!-- CONTEÚDO -->
    <main class="hf-content">
      <div class="row g-3">
        <div class="col-12 col-lg-3">
          <div class="hf-kpi">
            <div class="text-muted">Total OS</div>
            <div class="h4 mb-0">1.284</div>
            <div class="progress mt-3"><div class="progress-bar" style="width:66%"></div></div>
          </div>
        </div>
        <div class="col-12 col-lg-3">
          <div class="hf-kpi">
            <div class="text-muted">Pendentes</div>
            <div class="h4 mb-0 text-danger">143</div>
            <div class="progress mt-3"><div class="progress-bar bg-danger" style="width:35%"></div></div>
          </div>
        </div>
        <div class="col-12 col-lg-3">
          <div class="hf-kpi">
            <div class="text-muted">Concluídas</div>
            <div class="h4 mb-0 text-success">980</div>
            <div class="progress mt-3"><div class="progress-bar bg-success" style="width:80%"></div></div>
          </div>
        </div>
        <div class="col-12 col-lg-3">
          <div class="hf-kpi">
            <div class="text-muted">SLA médio</div>
            <div class="h4 mb-0">2,7d</div>
            <div class="progress mt-3"><div class="progress-bar bg-warning" style="width:50%"></div></div>
          </div>
        </div>
      </div>

      <div class="mt-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h5 class="card-title mb-3">Sales in 2014</h5>
            <p class="text-muted mb-0">Placeholder… depois trocamos por gráficos reais.</p>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/assets/theme.js"></script>
</body>
</html>
