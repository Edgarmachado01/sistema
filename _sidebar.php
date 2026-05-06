<?php /* Sidebar moderna */ ?>
<?php require_once __DIR__.'/auth.php'; ?>

<aside id="hf-sidebar" class="hf-sidebar p-2">
  <nav class="nav flex-column hf-sidebar-nav" aria-label="Menu principal">
    <div class="section hf-menu-section">
      <span class="hf-section-title">Principal</span>
    </div>

    <a class="nav-link hf-menu-item <?= ($_GET['m']??'')==='dash'?'active':'' ?>" href="/dashboard.php?m=dash" title="Dashboard">
      <span class="hf-ico hf-menu-icon"><i class="bi bi-speedometer2"></i></span>
      <span class="hf-menu-label">Dashboard</span>
    </a>

    <a class="nav-link hf-menu-item <?= ($_GET['m']??'')==='os'?'active':'' ?>" href="/os_list.php?m=os" title="Ordens de Serviço">
      <span class="hf-ico hf-menu-icon"><i class="bi bi-clipboard2-check"></i></span>
      <span class="hf-menu-label">Ordens de Serviço</span>
    </a>

    <a class="nav-link hf-menu-item <?= ($_GET['m']??'')==='clientes'?'active':'' ?>" href="/clientes.php?m=clientes" title="Clientes">
      <span class="hf-ico hf-menu-icon"><i class="bi bi-people"></i></span>
      <span class="hf-menu-label">Clientes</span>
    </a>

    <div class="section hf-menu-section">
      <span class="hf-section-title">Cadastros</span>
    </div>

    <a class="nav-link hf-menu-item <?= ($_GET['m']??'')==='produtos'?'active':'' ?>" href="/produtos.php?m=produtos" title="Produtos">
      <span class="hf-ico hf-menu-icon"><i class="bi bi-box-seam"></i></span>
      <span class="hf-menu-label">Produtos</span>
    </a>

    <a class="nav-link hf-menu-item <?= ($_GET['m']??'')==='servicos'?'active':'' ?>" href="/servicos.php?m=servicos" title="Serviços">
      <span class="hf-ico hf-menu-icon"><i class="bi bi-tools"></i></span>
      <span class="hf-menu-label">Serviços</span>
    </a>

    <div class="section hf-menu-section">
      <span class="hf-section-title">Gestão</span>
    </div>

    <?php if (isAdminLoja()): ?>
    <a class="nav-link hf-menu-item <?= ($_GET['m']??'')==='fin'?'active':'' ?>" href="/financeiro_os_lista.php?m=fin" title="Financeiro">
      <span class="hf-ico hf-menu-icon"><i class="bi bi-cash-coin"></i></span>
      <span class="hf-menu-label">Financeiro</span>
    </a>
    <?php endif; ?>

    <a class="nav-link hf-menu-item <?= ($_GET['m']??'')==='lanc'?'active':'' ?>" href="/lancamentos.php?m=lanc" title="Lançamentos">
      <span class="hf-ico hf-menu-icon"><i class="bi bi-journal-text"></i></span>
      <span class="hf-menu-label">Lançamentos</span>
    </a>

    <?php if (isAdminLoja()): ?>
    <a class="nav-link hf-menu-item <?= ($_GET['m'] ?? '') === 'hf' ? 'active' : '' ?>" href="/config_empresa.php?m=hf" title="Configurações">
      <span class="hf-ico hf-menu-icon"><i class="bi bi-gear"></i></span>
      <span class="hf-menu-label">Configurações</span>
    </a>
    <?php endif; ?>

    <div class="section hf-menu-section hf-account-section">
      <span class="hf-section-title">Conta</span>
    </div>

    <a class="nav-link hf-menu-item hf-account-item <?= ($_GET['m'] ?? '') === 'senha' ? 'active' : '' ?>" href="/change_password.php?m=senha" title="Trocar senha">
      <span class="hf-ico hf-menu-icon"><i class="bi bi-key"></i></span>
      <span class="hf-menu-label">Trocar senha</span>
    </a>

    <?php if (isAdminLoja()): ?>
    <a class="nav-link hf-menu-item hf-account-item <?= ($_GET['m'] ?? '') === 'usuarios' ? 'active' : '' ?>" href="/usuarios.php?m=usuarios" title="Usuários">
      <span class="hf-ico hf-menu-icon"><i class="bi bi-person-gear"></i></span>
      <span class="hf-menu-label">Usuários</span>
    </a>

    <a class="nav-link hf-menu-item hf-account-item <?= ($_GET['m'] ?? '') === 'reset_senha' ? 'active' : '' ?>" href="/admin_reset_password.php?m=reset_senha" title="Reset de senha">
      <span class="hf-ico hf-menu-icon"><i class="bi bi-shield-lock"></i></span>
      <span class="hf-menu-label">Reset de senha</span>
    </a>
    <?php endif; ?>
  </nav>
</aside>
