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
    inverse:{c:"#111827", rgb:"17,24,39", dark:false},
    dark:   {c:"#0d6efd", rgb:"13,110,253", dark:true}
  };

  function applyTheme(name){
    const p = PAL[name];
    if (!p) return;
    ROOT.style.setProperty("--bs-primary", p.c);
    ROOT.style.setProperty("--bs-primary-rgb", p.rgb);
    ROOT.style.setProperty("--brand", p.c);
    ROOT.setAttribute("data-bs-theme", p.dark ? "dark" : "light");
    try { localStorage.setItem(KEY, name); } catch(_e) {}
    const r = document.querySelector(`input[name="hf-theme"][value="${name}"]`);
    if (r) r.checked = true;
  }

  function escHtml(v){
    return (v ?? "").toString()
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function normalizeType(type){
    const t = (type || "").toString().toLowerCase().trim();
    if (t === "success" || t === "danger" || t === "warning" || t === "info") return t;
    if (t === "error") return "danger";
    return "info";
  }

  function addToast(type, text){
    const container = document.getElementById("hf-feedback-toast-container");
    if (!container || !text) return;

    const kind = normalizeType(type);
    const iconMap = {
      success: "bi-check-circle-fill",
      danger: "bi-x-circle-fill",
      warning: "bi-exclamation-triangle-fill",
      info: "bi-info-circle-fill"
    };

    const toast = document.createElement("div");
    toast.className = `toast align-items-center border-0 hf-toast-${kind}`;
    toast.setAttribute("role", "status");
    toast.setAttribute("aria-live", "polite");
    toast.setAttribute("aria-atomic", "true");
    toast.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">
          <i class="bi ${iconMap[kind]} me-2"></i>${escHtml(text)}
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button>
      </div>
    `;

    container.appendChild(toast);

    if (window.bootstrap && window.bootstrap.Toast) {
      const instance = new window.bootstrap.Toast(toast, { delay: 4200 });
      toast.addEventListener("hidden.bs.toast", () => toast.remove());
      instance.show();
    } else {
      setTimeout(() => toast.remove(), 4200);
    }
  }

  function decodeParamMessage(val){
    if (val == null) return "";
    const raw = val.toString().trim();
    if (!raw) return "";
    return raw.replace(/[_+]+/g, " ");
  }

  function queueMessagesFromQuery(){
    const url = new URL(window.location.href);
    const params = url.searchParams;
    const queue = [];
    const handled = [];

    const okVal = params.get("ok");
    if (okVal !== null) {
      const decoded = decodeParamMessage(okVal).toLowerCase();
      let msg = "Ação concluída com sucesso.";
      if (decoded === "deleted" || decoded === "excluido" || decoded === "removido") msg = "Registro excluído com sucesso.";
      queue.push({ type: "success", text: msg });
      handled.push("ok");
    }

    const successVal = params.get("success");
    if (successVal !== null) {
      const decoded = decodeParamMessage(successVal);
      queue.push({ type: "success", text: decoded || "Ação concluída com sucesso." });
      handled.push("success");
    }

    const errVal = params.get("err") ?? params.get("erro") ?? params.get("error");
    if (errVal !== null) {
      const code = decodeParamMessage(errVal).toLowerCase();
      let msg = "Não foi possível concluir a ação.";
      if (code === "nome") msg = "Preencha os campos obrigatórios antes de continuar.";
      if (code === "save") msg = "Não foi possível salvar. Tente novamente.";
      queue.push({ type: "danger", text: msg });
      if (params.get("err") !== null) handled.push("err");
      if (params.get("erro") !== null) handled.push("erro");
      if (params.get("error") !== null) handled.push("error");
    }

    const warningVal = params.get("warning");
    if (warningVal !== null) {
      const decoded = decodeParamMessage(warningVal);
      queue.push({ type: "warning", text: decoded || "Atenção ao validar os dados informados." });
      handled.push("warning");
    }

    const msgVal = params.get("msg");
    if (msgVal !== null) {
      const decoded = decodeParamMessage(msgVal);
      if (decoded) queue.push({ type: "info", text: decoded });
      handled.push("msg");
    }

    if (handled.length) {
      handled.forEach((k) => params.delete(k));
      const newQuery = params.toString();
      const cleanUrl = url.pathname + (newQuery ? `?${newQuery}` : "") + url.hash;
      window.history.replaceState({}, document.title, cleanUrl);
    }

    return queue;
  }

  function showInitialMessages(){
    const queued = [];
    if (Array.isArray(window.HF_GLOBAL_FEEDBACK)) {
      window.HF_GLOBAL_FEEDBACK.forEach((item) => {
        if (!item || typeof item !== "object") return;
        queued.push({ type: item.type || "info", text: item.text || "" });
      });
    }
    queueMessagesFromQuery().forEach((m) => queued.push(m));
    queued.forEach((m) => addToast(m.type, m.text));
  }

  function setGlobalLoading(isVisible){
    const box = document.getElementById("hf-global-loading");
    if (!box) return;
    box.classList.toggle("d-none", !isVisible);
    box.setAttribute("aria-hidden", isVisible ? "false" : "true");
  }

  function buttonLoadingHtml(kind){
    const txt = kind === "delete" ? "Excluindo..." : "Salvando...";
    return `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>${txt}`;
  }

  function applySubmitLock(form, submitter){
    if (form.dataset.hfSubmitting === "1") return false;
    form.dataset.hfSubmitting = "1";
    form.classList.add("hf-is-submitting");
    setGlobalLoading(true);

    const isDelete = /_delete\.php$/i.test((form.getAttribute("action") || "").toLowerCase());
    const buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
    buttons.forEach((btn) => {
      if (btn.disabled) return;
      btn.disabled = true;
      btn.classList.add("hf-btn-loading");

      if (btn.tagName === "BUTTON") {
        btn.dataset.hfOriginalHtml = btn.innerHTML;
        const custom = btn.getAttribute("data-loading-text");
        if (custom) {
          btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>${escHtml(custom)}`;
        } else {
          const kind = isDelete || btn.className.toLowerCase().indexOf("danger") >= 0 ? "delete" : "save";
          btn.innerHTML = buttonLoadingHtml(kind);
        }
      } else if (btn.tagName === "INPUT") {
        btn.dataset.hfOriginalValue = btn.value;
        btn.value = isDelete ? "Excluindo..." : "Salvando...";
      }
    });

    if (submitter && submitter.tagName === "BUTTON" && !submitter.disabled) {
      submitter.disabled = true;
    }
    return true;
  }

  function initSubmitFeedback(){
    document.addEventListener("submit", function(ev){
      const form = ev.target;
      if (!(form instanceof HTMLFormElement)) return;
      if (form.hasAttribute("data-hf-no-loading")) return;

      const method = (form.getAttribute("method") || "get").toLowerCase();
      if (method !== "post") return;
      if (ev.defaultPrevented) return;

      const locked = applySubmitLock(form, ev.submitter || document.activeElement);
      if (!locked) {
        ev.preventDefault();
      }
    });

    window.addEventListener("pageshow", function(){
      setGlobalLoading(false);
      document.querySelectorAll("form.hf-is-submitting").forEach((form) => {
        form.classList.remove("hf-is-submitting");
        form.dataset.hfSubmitting = "0";
      });
    });
  }

  window.hfToggleSwitcher = () => {
    const el = document.getElementById("hf-switcher");
    if (el) el.classList.toggle("show");
  };
  window.hfSetTheme = (v) => applyTheme(v);

  document.addEventListener("DOMContentLoaded", function(){
    showInitialMessages();
    initSubmitFeedback();
  });
})();
