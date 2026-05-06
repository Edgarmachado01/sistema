(function(){
  const KEY = "hf_theme";
  const ROOT = document.documentElement;

  const PAL = {
    primary:{c:"#0d6efd", rgb:"13,110,253", dark:false},
    success:{c:"#198754", rgb:"25,135,84", dark:false},
    warning:{c:"#ffc107", rgb:"255,193,7", dark:false},
    danger: {c:"#dc3545", rgb:"220,53,69", dark:false},
    pink:   {c:"#d63384", rgb:"214,51,132", dark:false},
    purple: {c:"#6f42c1", rgb:"111,66,193", dark:false},
    inverse:{c:"#111827", rgb:"17,24,39",  dark:false},
    dark:   {c:"#0d6efd", rgb:"13,110,253", dark:true}
  };

  function applyTheme(name){
    const p = PAL[name];
    if (!p) return;
    ROOT.style.setProperty("--bs-primary", p.c);
    ROOT.style.setProperty("--bs-primary-rgb", p.rgb);
    ROOT.style.setProperty("--brand", p.c);
    ROOT.setAttribute("data-bs-theme", p.dark ? "dark" : "light");
    try{ localStorage.setItem(KEY, name); }catch(e){}
    const r = document.querySelector(`input[name="hf-theme"][value="${name}"]`);
    if (r) r.checked = true;
  }

  // Expor funções globais do switcher
  window.hfToggleSwitcher = () => {
    const el = document.getElementById("hf-switcher");
    if (el) el.classList.toggle("show");
  };
  window.hfSetTheme = (v) => applyTheme(v);

  // Não força nenhum tema — só aplica se houver salvo

})();
