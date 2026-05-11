<?php /* ========= _layout_end.php ========= */ ?>
  </div>

  <div id="hf-feedback-toast-container"
       class="toast-container position-fixed top-0 end-0 p-3"
       aria-live="polite"
       aria-atomic="true"></div>

  <div id="hf-global-loading" class="hf-global-loading d-none" aria-hidden="true">
    <div class="hf-global-loading-card">
      <div class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></div>
      <span>Processando...</span>
    </div>
  </div>

  <script>
    window.HF_GLOBAL_FEEDBACK = <?= json_encode($hfGlobalFeedback ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script src="assets/theme.js?v=8"></script>


  <script>
  if (typeof window.hfToggleSwitcher !== 'function') {
    window.hfToggleSwitcher = function(){
      var el = document.getElementById('hf-switcher');
      if (el) el.classList.toggle('show');
    };
  }
  if (typeof window.hfSetTheme !== 'function') {
    window.hfSetTheme = function(name){
      var ROOT=document.documentElement, map={
        primary:["#0d6efd","13,110,253",false],
        success:["#198754","25,135,84",false],
        warning:["#ffc107","255,193,7",false],
        danger:["#dc3545","220,53,69",false],
        pink:["#d63384","214,51,132",false],
        purple:["#6f42c1","111,66,193",false],
        inverse:["#111827","17,24,39",false],
        dark:["#0d6efd","13,110,253",true]
      }, p=map[name]||map.primary;
      ROOT.style.setProperty("--bs-primary",p[0]);
      ROOT.style.setProperty("--bs-primary-rgb",p[1]);
      ROOT.style.setProperty("--brand",p[0]);
      ROOT.setAttribute("data-bs-theme", p[2] ? "dark":"light");
      try{ localStorage.setItem("hf_theme",name); }catch(e){}
      var r=document.querySelector('input[name="hf-theme"][value="'+name+'"]');
      if(r) r.checked=true;
    };
  }
  </script>

  <script>
  (function(){
    const MQ   = '(max-width: 991.98px)';
    const KEY  = 'hf_sidebar';
    const BTN  = 'hf-menu-btn';
    const SID  = 'hf-sidebar';

    // Define/garante a função
    window.hfToggleSidebar = function(){
      const isMobile = window.matchMedia(MQ).matches;
      if (isMobile) {
        document.body.classList.toggle('sidebar-open');      // overlay mobile
      } else {
        document.body.classList.toggle('sidebar-collapsed'); // fino desktop
        try{
          localStorage.setItem(
            KEY, document.body.classList.contains('sidebar-collapsed') ? '1':'0'
          );
        }catch(e){}
      }
    };

    // Bind no botão + aplica estado salvo
    document.addEventListener('DOMContentLoaded', function(){
      var btn = document.getElementById(BTN);
      if (btn && !btn.dataset.bound) {
        btn.addEventListener('click', function(e){
          e.preventDefault();
          if (!document.getElementById(SID)) {
            console.warn('[HF] #hf-sidebar não encontrado nesta página.');
            return;
          }
          window.hfToggleSidebar();
        });
        btn.dataset.bound = '1';
      }
      try{
        if (localStorage.getItem(KEY)==='1' && !window.matchMedia(MQ).matches){
          document.body.classList.add('sidebar-collapsed');
        }
      }catch(e){}
    });

    // Ao sair do mobile, feche o overlay
    window.addEventListener('resize', function(){
      if (!window.matchMedia(MQ).matches) {
        document.body.classList.remove('sidebar-open');
      }
    });

    // ESC fecha overlay
    document.addEventListener('keydown', function(ev){
      if (ev.key === 'Escape') document.body.classList.remove('sidebar-open');
    });
  })();
  </script>
  
  </body>
</html>
