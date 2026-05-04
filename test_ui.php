<?php
// Página de teste mínima para validar menu + CSS + includes
require_once __DIR__.'/_layout_start.php';
include __DIR__.'/_sidebar.php';
?>
<main class="hf-content">
  <div class="alert alert-info d-flex align-items-center justify-content-between">
    <div>
      <strong>Teste do Menu</strong> — Clique no botão de “hambúrguer” na topbar.
      <div class="small text-muted">
        Mobile: o menu deve vir por cima (overlay). Desktop: ele deve “afinar”.
      </div>
    </div>
    <button id="dbg-toggle" class="btn btn-outline-primary btn-sm">Toggle (debug)</button>
  </div>

  <div class="hf-card p-3 mb-3">
    <div class="mb-2">Estado atual do <code>body</code>:</div>
    <div id="dbg-state" class="p-2 bg-body-tertiary rounded border"></div>
  </div>

  <div class="row g-3">
    <div class="col-md-4"><div class="hf-card p-3">Card 1</div></div>
    <div class="col-md-4"><div class="hf-card p-3">Card 2</div></div>
    <div class="col-md-4"><div class="hf-card p-3">Card 3</div></div>
  </div>
</main>
<?php include __DIR__.'/_layout_end.php'; ?>

<script>
// Diagnóstico visual rápido
(function(){
  const S = document.getElementById('dbg-state');
  function render(){
    S.textContent = document.body.className || '(sem classes no body)';
  }
  render();
  new MutationObserver(render).observe(document.body, { attributes:true, attributeFilter:['class']});
  document.getElementById('dbg-toggle').onclick = function(){
    // Chama a mesma função do botão da topbar
    if (typeof window.hfToggleSidebar === 'function') window.hfToggleSidebar();
  };
})();
</script>
