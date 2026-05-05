<?php /* menu_standalone.php — teste isolado, sem depender de nenhum outro arquivo */ ?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Teste Menu Isolado</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{ --topbar-h:56px }
    .hf-topbar{ background:#0d6efd; }
    .hf-sidebar{ width:240px; min-height:100vh; background:#fff; border-right:1px solid rgba(0,0,0,.06) }
    .hf-content{ flex:1; min-width:0; padding:1.25rem 1.5rem 2rem }
    @media (max-width: 991.98px){
      .hf-sidebar{
        position:fixed; top:var(--topbar-h); left:-260px;
        height:calc(100vh - var(--topbar-h)); width:240px; z-index:1045;
        background:#fff; box-shadow:0 16px 40px rgba(0,0,0,.25); transition:left .2s ease; border-right:none;
      }
      body.sidebar-open .hf-sidebar{ left:0 }
      .hf-backdrop{ display:none }
      body.sidebar-open .hf-backdrop{
        display:block; position:fixed; inset:var(--topbar-h) 0 0 0; z-index:1040;
        background:rgba(0,0,0,.35); backdrop-filter:blur(1px)
      }
      body.sidebar-open{ overflow:hidden }
    }
  </style>
</head>
<body>
  <nav class="navbar hf-topbar">
    <div class="container-fluid">
      <button id="hf-menu-btn" class="btn btn-light me-2" type="button" aria-controls="hf-sidebar" aria-label="Abrir menu">
        <i class="bi bi-list"></i>
      </button>
      <span class="navbar-brand mb-0 h1 text-white">Teste</span>
    </div>
  </nav>

  <div id="hf-backdrop" class="hf-backdrop"></div>

  <div class="d-flex">
    <aside id="hf-sidebar" class="hf-sidebar p-2">
      <nav class="nav flex-column">
        <a class="nav-link" href="#"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="nav-link" href="#"><i class="bi bi-people"></i> Clientes</a>
      </nav>
    </aside>
    <main class="hf-content">
      <div class="alert alert-info">Se o botão acima abrir/fechar o menu, o problema é nos seus includes.</div>
    </main>
  </div>

  <script>
  (function(){
    const MQ='(max-width: 991.98px)'; const KEY='hf_sidebar';
    function toggle(){
      const mobile = window.matchMedia(MQ).matches;
      document.body.classList.toggle(mobile ? 'sidebar-open' : 'sidebar-collapsed');
      if(!mobile){
        try{ localStorage.setItem(KEY, document.body.classList.contains('sidebar-collapsed')?'1':'0'); }catch(e){}
      }
    }
    document.getElementById('hf-menu-btn').addEventListener('click', function(e){ e.preventDefault(); toggle(); });
    document.getElementById('hf-backdrop').addEventListener('click', function(){ document.body.classList.remove('sidebar-open'); });
    try{ if(localStorage.getItem(KEY)==='1' && !window.matchMedia(MQ).matches){ document.body.classList.add('sidebar-collapsed'); } }catch(e){}
    window.addEventListener('resize', function(){ if(!window.matchMedia(MQ).matches){ document.body.classList.remove('sidebar-open'); } });
  })();
  </script>
</body>
</html>
