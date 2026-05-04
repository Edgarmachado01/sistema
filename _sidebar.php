<?php /* Sidebar moderna */ ?>
<?php require_once __DIR__.'/auth.php'; ?>
<aside id="hf-sidebar" class="hf-sidebar p-2">
  <nav class="nav flex-column">
    <div class="section">Principal</div>

    <a class="nav-link <?= ($_GET['m']??'')==='dash'?'active':'' ?>" href="/dashboard.php?m=dash" title="Dashboard">
      <div class="hf-ico"><i class="bi bi-speedometer2"></i></div><span>Dashboard</span>
    </a>

    <a class="nav-link <?= ($_GET['m']??'')==='os'?'active':'' ?>" href="/os_list.php?m=os" title="Ordens de Serviço">
      <div class="hf-ico"><i class="bi bi-clipboard2-check"></i></div><span>Ordens de Serviço</span>
    </a>

    <a class="nav-link <?= ($_GET['m']??'')==='clientes'?'active':'' ?>" href="/clientes.php?m=clientes" title="Clientes">
      <div class="hf-ico"><i class="bi bi-people"></i></div><span>Clientes</span>
    </a>

    <!-- NOVO BLOCO: Cadastros -->
    <div class="section">Cadastros</div>

    <a class="nav-link <?= ($_GET['m']??'')==='produtos'?'active':'' ?>" href="/produtos.php?m=produtos" title="Produtos">
      <div class="hf-ico"><i class="bi bi-box-seam"></i></div><span>Produtos</span>
    </a>

    <a class="nav-link <?= ($_GET['m']??'')==='servicos'?'active':'' ?>" href="/servicos.php?m=servicos" title="Serviços">
      <div class="hf-ico"><i class="bi bi-tools"></i></div><span>Serviços</span>
    </a>
    <!-- /NOVO BLOCO -->

    <div class="section">Gestão</div>

    <!-- Painel financeiro geral (OS + lançamentos) - LIBERADO pra admin e atendente -->
     <?php if (isAdminLoja()): ?>
    <a class="nav-link <?= ($_GET['m']??'')==='fin'?'active':'' ?>" href="/financeiro_os_lista.php?m=fin" title="Financeiro">
      <div class="hf-ico"><i class="bi bi-cash-coin"></i></div><span>Financeiro</span>
    </a>
    <?php endif; ?>

    <!-- Lançamentos avulsos / recorrentes - LIBERADO pra admin e atendente -->
    <a class="nav-link <?= ($_GET['m']??'')==='lanc'?'active':'' ?>"
      href="/lancamentos.php?m=lanc" title="Lançamentos">
      <div class="hf-ico"><i class="bi bi-journal-text"></i></div><span>Lançamentos</span>
    </a>

    <!-- Configurações da empresa - SÓ ADMIN DA LOJA -->
    <?php if (isAdminLoja()): ?>
    <a class="nav-link <?= ($_GET['m'] ?? '') === 'hf' ? 'active' : '' ?> "
      href="/config_empresa.php?m=hf" title="Configurações">
        <div class="hf-ico"><i class="bi bi-gear"></i></div>
        <span>Configurações</span>
    </a>
    <?php endif; ?>

     <div class="section">Conta</div>

    <a class="nav-link" href="/change_password.php" title="Trocar senha">
      <div class="hf-ico"><i class="bi bi-key"></i></div><span>Trocar senha</span>
    </a>

    <?php if (isAdminLoja()): ?>
      <a class="nav-link <?= ($_GET['m'] ?? '') === 'usuarios' ? 'active' : '' ?>"
        href="/usuarios.php?m=usuarios" title="Usuários">
        <div class="hf-ico">
          <i class="bi bi bi-person"></i>
        </div>
        <span>Usuários</span>
      </a>
    

    <?php endif; ?>
  </nav>
</aside>
