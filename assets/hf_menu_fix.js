// assets/hf_menu_fix.js
(function(){
  const MQ='(max-width: 991.98px)'; 
  const KEY='hf_sidebar';

  function toggle(){
    const mobile = window.matchMedia(MQ).matches;
    document.body.classList.toggle(mobile ? 'sidebar-open' : 'sidebar-collapsed');
    if(!mobile){
      try{
        localStorage.setItem(KEY, document.body.classList.contains('sidebar-collapsed')?'1':'0');
      }catch(e){}
    }
  }

  function bind(){
    var btn = document.getElementById('hf-menu-btn');
    var backdrop = document.getElementById('hf-backdrop');
    if (btn && !btn.dataset.bound){
      btn.addEventListener('click', function(e){ e.preventDefault(); toggle(); });
      btn.dataset.bound = '1';
    }
    if (backdrop && !backdrop.dataset.bound){
      backdrop.addEventListener('click', function(){ document.body.classList.remove('sidebar-open'); });
      backdrop.dataset.bound = '1';
    }
    try{
      if(localStorage.getItem(KEY)==='1' && !window.matchMedia(MQ).matches){
        document.body.classList.add('sidebar-collapsed');
      }
    }catch(e){}
  }

  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }

  // fecha overlay ao sair do mobile
  window.addEventListener('resize', function(){
    if(!window.matchMedia(MQ).matches){
      document.body.classList.remove('sidebar-open');
    }
  });

  // ESC fecha overlay
  document.addEventListener('keydown', function(ev){
    if (ev.key === 'Escape') document.body.classList.remove('sidebar-open');
  });

  // expõe para onclicks existentes (se houver)
  window.hfToggleSidebar = toggle;
})();
