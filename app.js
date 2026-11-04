window.DogsApp = (() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

  function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (char) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
    })[char]);
  }

  function message(target, text = "", type = "info", options = {}) {
    const element = typeof target === "string" ? $(target) : target;
    if (!element) return;
    element.textContent = text;
    element.className = `notice ${type}`;
    element.hidden = !text;
    element.setAttribute("role", type === "error" ? "alert" : "status");
    if (text && type === "success" && options.toast !== false) toast(text, "success");
  }

  /** Notificação temporária no canto da tela (feedback de ações bem-sucedidas). */
  function toast(text, type = "info", duration = 4200) {
    if (!text) return;
    let region = document.getElementById("toast-region");
    if (!region) {
      region = document.createElement("div");
      region.id = "toast-region";
      region.setAttribute("aria-live", "polite");
      region.setAttribute("aria-atomic", "false");
      document.body.appendChild(region);
    }
    const item = document.createElement("div");
    item.className = `toast ${type}`;
    const icon = type === "success" ? "\u2713" : type === "error" ? "!" : "i";
    item.innerHTML = `<span class="toast-icon" aria-hidden="true">${icon}</span><span class="toast-text"></span><button type="button" class="toast-close" aria-label="Fechar notificação">\u00d7</button>`;
    item.querySelector(".toast-text").textContent = text;
    const remove = () => {
      if (!item.isConnected) return;
      item.classList.add("is-leaving");
      setTimeout(() => item.remove(), 220);
    };
    item.querySelector(".toast-close").addEventListener("click", remove);
    region.appendChild(item);
    while (region.children.length > 3) region.firstElementChild.remove();
    setTimeout(remove, duration);
  }

  function requireAuth() {
    const info = DogsAPI.userInfo();
    if (!info.authenticated) {
      DogsAPI.clearToken();
      window.location.replace("index.php");
      return false;
    }
    return true;
  }

  const NAV_ICONS = {
    dashboard: '<rect x="3" y="3" width="7" height="9" rx="1.8"/><rect x="14" y="3" width="7" height="5" rx="1.8"/><rect x="14" y="12" width="7" height="9" rx="1.8"/><rect x="3" y="16" width="7" height="5" rx="1.8"/>',
    materias: '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M4 19.5V21h16v-4"/>',
    anotacoes: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
    calendario: '<rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M8 3v4M16 3v4M3 10h18"/>',
    simulados: '<rect x="5" y="4" width="14" height="17" rx="2.5"/><path d="M9 4V3h6v1M9 13l2 2 4-4"/>',
    ranking: '<path d="M7 21v-7M12 21V4M17 21v-11"/>',
    correcoes: '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
    batalha: '<path d="M4 20V7l8-4 8 4v13"/><path d="M4 12h16M8 20v-5h8v5"/>',
  };
  const svgIcon = (paths, cls = "") => `<svg class="${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${paths}</svg>`;

  function renderNav(activePage) {
    const target = $("#app-nav");
    if (!target) return;
    const info = DogsAPI.userInfo();
    const links = [
      ["dashboard", "Painel", "dashboard.php"],
      ["materias", "Matérias", "materias.php"],
      ["anotacoes", "Anotações", "anotacoes.php"],
      ["calendario", "Calendário", "calendario.php"],
      ["simulados", "Simulados", "simulados.php"],
      ["ranking", "Ranking", "ranking.php"],
      ["batalha", "Batalha Ancestral", "batalha.php"],
    ];
    // A fila de correção só existe para administradores.
    if (info.isAdmin) links.push(["correcoes", "Correções", "correcoes.php"]);
    target.innerHTML = `
      <header class="app-header">
        <a class="brand" href="dashboard.php" aria-label="Dogs Team, painel inicial"><span class="brand-mark" aria-hidden="true">DT</span><span>Dogs Team</span></a>
        <nav class="main-nav" id="main-nav" aria-label="Navegação principal">
          ${links.map(([key, label, href]) => `<a href="${href}"${key === activePage ? ' aria-current="page" class="active"' : ""}>${key === "batalha" ? '<img class="nav-batalha-icon" src="imagens/batalha-ancestral.jpg" alt="" />' : svgIcon(NAV_ICONS[key])}<span>${label}</span></a>`).join("")}
        </nav>
        <div class="account-area">
          <span class="account-role">${info.isAdmin ? "Administrador" : "Estudante"}</span>
          <button id="profile-button" class="button button-profile" type="button" aria-label="Abrir foto de perfil" title="Foto de perfil"><span class="profile-avatar">${info.isAdmin ? "A" : "U"}</span><span class="profile-button-label">Perfil</span></button>
          <button id="logout-button" class="button button-quiet" type="button">Sair</button>
          <button id="nav-toggle" class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-nav" aria-label="Abrir menu de navegação">
            ${svgIcon('<path d="M4 7h16M4 12h16M4 17h16"/>', "icon-open")}${svgIcon('<path d="M6 6l12 12M18 6 6 18"/>', "icon-close")}
          </button>
        </div>
      </header>
      <div id="profile-modal" class="modal-backdrop" hidden>
        <section class="modal-card profile-modal" role="dialog" aria-modal="true" aria-labelledby="profile-modal-title">
          <button class="modal-close" type="button" aria-label="Fechar">×</button>
          <span class="eyebrow">Sua conta</span><h2 id="profile-modal-title">Foto de perfil</h2>
          <p class="muted">Envie uma imagem para personalizar sua conta. O sistema permite uma foto de perfil por usuário.</p>
          <div id="profile-image-manager"></div>
        </section>
      </div>`;
    $("#logout-button", target)?.addEventListener("click", logout);
    const avatar = $(".profile-avatar", target);
    if (avatar) avatar.dataset.fallback = avatar.textContent;
    loadProfileAvatar(avatar);
    $("#profile-button", target)?.addEventListener("click", () => {
      const modal = $("#profile-modal", target);
      modal.hidden = false;
      document.body.classList.add("modal-open");
      if (!modal.dataset.managerReady) {
        setupImageManager($("#profile-image-manager", modal), "perfil", null, { canUpload: true, canDelete: true, single: true, title: "Sua imagem" });
        modal.dataset.managerReady = "1";
      }
      $(".modal-close", modal)?.focus();
    });
    $(".modal-close", target)?.addEventListener("click", closeProfileModal);
    $("#profile-modal", target)?.addEventListener("click", event => {
      if (event.target.id === "profile-modal") closeProfileModal();
    });
    document.addEventListener("keydown", profileEscapeHandler);
    setupMobileMenu(target);
  }

  /** Menu responsivo: abre/fecha com botão, Esc, clique fora e ao escolher um link. */
  function setupMobileMenu(root) {
    const toggle = $("#nav-toggle", root);
    const nav = $("#main-nav", root);
    if (!toggle || !nav) return;
    const set = (open, returnFocus = false) => {
      nav.classList.toggle("is-open", open);
      toggle.setAttribute("aria-expanded", String(open));
      toggle.setAttribute("aria-label", open ? "Fechar menu de navegação" : "Abrir menu de navegação");
      if (!open && returnFocus) toggle.focus();
    };
    toggle.addEventListener("click", () => set(!nav.classList.contains("is-open")));
    nav.addEventListener("click", event => { if (event.target.closest("a")) set(false); });
    document.addEventListener("click", event => {
      if (nav.classList.contains("is-open") && !event.target.closest(".app-header")) set(false);
    });
    document.addEventListener("keydown", event => {
      if (event.key === "Escape" && nav.classList.contains("is-open")) set(false, true);
    });
    window.matchMedia("(min-width: 1241px)").addEventListener?.("change", event => { if (event.matches) set(false); });
  }

  async function loadProfileAvatar(avatar) {
    if (!avatar) return;
    try {
      const data = await DogsAPI.request("/api/imagens?tipo=perfil");
      const item = (data.imagens || [])[0];
      if (avatar._dogsAvatarUrl) {
        URL.revokeObjectURL(avatar._dogsAvatarUrl);
        avatar._dogsAvatarUrl = null;
      }
      avatar.classList.remove("has-profile-image");
      if (!item) {
        avatar.textContent = avatar.dataset.fallback || "U";
        return;
      }
      const blob = await DogsAPI.requestBlob(`/api/imagens/arquivo?id=${encodeURIComponent(item.id)}`);
      const url = URL.createObjectURL(blob);
      avatar._dogsAvatarUrl = url;
      avatar.innerHTML = `<img src="${url}" alt="Foto de perfil">`;
      avatar.classList.add("has-profile-image");
    } catch (error) {
      // A foto é opcional; a navegação continua funcional sem ela.
      console.debug("Foto de perfil indisponível:", error.message);
    }
  }

  function profileEscapeHandler(event) {
    if (event.key === "Escape") closeProfileModal();
  }

  function closeProfileModal() {
    const modal = $("#profile-modal");
    const wasOpen = modal && !modal.hidden;
    if (modal) modal.hidden = true;
    document.body.classList.remove("modal-open");
    if (wasOpen) $("#profile-button")?.focus();
  }

  async function logout() {
    try {
      await DogsAPI.request("/api/logout", { method: "POST" });
    } catch (error) {
      console.warn("Não foi possível confirmar o logout na API:", error.message);
    } finally {
      DogsAPI.clearToken();
      window.location.replace("index.php");
    }
  }

  function formatDate(value) {
    if (!value) return "Sem data";
    const normalized = String(value).includes("T") ? value : String(value).replace(" ", "T");
    const date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat("pt-BR", {
      dateStyle: "medium", timeStyle: "short",
    }).format(date);
  }

  function toDateTimeLocal(value) {
    if (!value) return "";
    return String(value).replace(" ", "T").slice(0, 16);
  }

  function linkLinesToArray(text) {
    return String(text || "").split("\n").map(line => line.trim()).filter(Boolean).map(line => {
      const separator = line.indexOf("|");
      if (separator < 0) return { url: line, titulo: "" };
      return { titulo: line.slice(0, separator).trim(), url: line.slice(separator + 1).trim() };
    });
  }

  function linksToLines(links) {
    return (links || []).map(link => link.titulo ? `${link.titulo} | ${link.url}` : link.url).join("\n");
  }

  function imageUrl(id) {
    return `/api/imagens/arquivo?id=${encodeURIComponent(id)}`;
  }

  const imageUrlCache = new Map();

  /** Baixa uma imagem autenticada uma única vez e devolve uma URL de objeto reutilizável. */
  function imageObjectUrl(id) {
    const key = String(id);
    if (!imageUrlCache.has(key)) {
      const promise = DogsAPI.requestBlob(imageUrl(id)).then(blob => URL.createObjectURL(blob));
      promise.catch(() => imageUrlCache.delete(key));
      imageUrlCache.set(key, promise);
    }
    return imageUrlCache.get(key);
  }

  // ------------------------------------------------------------------
  // Visualizador de imagens (lightbox)
  // ------------------------------------------------------------------
  let lightboxCleanup = null;

  function closeLightbox() {
    if (lightboxCleanup) lightboxCleanup();
  }

  /**
   * Abre o visualizador em tela cheia. items: [{ src, caption }].
   * Recursos: navegação (botões, setas, deslizar), zoom (botões, roda do mouse, duplo toque, pinça),
   * arrastar a imagem ampliada, miniaturas, legenda, download e fechamento por Esc/fundo.
   */
  function openLightbox(items, startIndex = 0) {
    items = (items || []).filter(item => item && item.src);
    if (!items.length) return;
    closeLightbox();

    const previousFocus = document.activeElement;
    const total = items.length;
    const MAX_ZOOM = 5;
    let index = Math.min(Math.max(0, startIndex), total - 1);
    let zoom = 1;
    let panX = 0;
    let panY = 0;

    const root = document.createElement("div");
    root.className = "lightbox";
    root.setAttribute("role", "dialog");
    root.setAttribute("aria-modal", "true");
    root.setAttribute("aria-label", "Visualizador de imagens");
    root.innerHTML = `
      <div class="lightbox-topbar">
        <span class="lightbox-counter" aria-live="polite"></span>
        <div class="lightbox-tools">
          <button type="button" class="lightbox-btn" data-lb="zoom-out" aria-label="Diminuir zoom">−</button>
          <button type="button" class="lightbox-btn lightbox-zoom-level" data-lb="zoom-reset" aria-label="Restaurar tamanho">100%</button>
          <button type="button" class="lightbox-btn" data-lb="zoom-in" aria-label="Aumentar zoom">＋</button>
          <a class="lightbox-btn lightbox-download" data-lb="download" download aria-label="Baixar imagem">⤓</a>
          <button type="button" class="lightbox-btn lightbox-close" data-lb="close" aria-label="Fechar visualizador">×</button>
        </div>
      </div>
      <button type="button" class="lightbox-nav lightbox-prev" data-lb="prev" aria-label="Imagem anterior">‹</button>
      <div class="lightbox-stage"><img class="lightbox-image" alt="" draggable="false"></div>
      <button type="button" class="lightbox-nav lightbox-next" data-lb="next" aria-label="Próxima imagem">›</button>
      <p class="lightbox-caption"></p>
      <div class="lightbox-thumbs" role="tablist" aria-label="Miniaturas"></div>`;
    document.body.appendChild(root);
    document.body.classList.add("lightbox-open");

    const stage = $(".lightbox-stage", root);
    const img = $(".lightbox-image", root);
    const counter = $(".lightbox-counter", root);
    const caption = $(".lightbox-caption", root);
    const zoomLevel = $(".lightbox-zoom-level", root);
    const download = $(".lightbox-download", root);
    const thumbs = $(".lightbox-thumbs", root);
    const prevButton = $(".lightbox-prev", root);
    const nextButton = $(".lightbox-next", root);

    if (total > 1) {
      thumbs.innerHTML = items.map((item, i) => `<button type="button" class="lightbox-thumb" data-lb-thumb="${i}" role="tab" aria-label="Imagem ${i + 1} de ${total}"><img src="${escapeHtml(item.src)}" alt="" draggable="false"></button>`).join("");
    } else {
      thumbs.hidden = true;
      prevButton.hidden = true;
      nextButton.hidden = true;
    }

    function limits() {
      return {
        x: Math.max(0, (img.clientWidth * zoom - stage.clientWidth) / 2),
        y: Math.max(0, (img.clientHeight * zoom - stage.clientHeight) / 2),
      };
    }

    function render() {
      const max = limits();
      panX = Math.min(max.x, Math.max(-max.x, panX));
      panY = Math.min(max.y, Math.max(-max.y, panY));
      if (zoom <= 1) { panX = 0; panY = 0; }
      img.style.transform = `translate(${panX}px, ${panY}px) scale(${zoom})`;
      stage.classList.toggle("is-zoomed", zoom > 1);
      zoomLevel.textContent = `${Math.round(zoom * 100)}%`;
    }

    // Altera o zoom mantendo fixo o ponto sob o cursor/dedo (ou o centro, quando não informado).
    function setZoom(next, clientX, clientY) {
      next = Math.min(MAX_ZOOM, Math.max(1, next));
      const rect = stage.getBoundingClientRect();
      const dx = (clientX ?? rect.left + rect.width / 2) - (rect.left + rect.width / 2);
      const dy = (clientY ?? rect.top + rect.height / 2) - (rect.top + rect.height / 2);
      const ratio = next / zoom;
      panX = dx - ratio * (dx - panX);
      panY = dy - ratio * (dy - panY);
      zoom = next;
      render();
    }

    function show(target) {
      index = (target + total) % total;
      zoom = 1; panX = 0; panY = 0;
      const item = items[index];
      img.classList.remove("lb-enter");
      void img.offsetWidth; // reinicia a animação de entrada
      img.src = item.src;
      img.alt = item.caption || `Imagem ${index + 1}`;
      img.classList.add("lb-enter");
      caption.textContent = item.caption || "";
      caption.hidden = !item.caption;
      counter.textContent = total > 1 ? `${index + 1} / ${total}` : "";
      download.href = item.src;
      download.setAttribute("download", item.caption || `imagem-${index + 1}`);
      $$(".lightbox-thumb", root).forEach((thumb, i) => {
        const active = i === index;
        thumb.classList.toggle("active", active);
        thumb.setAttribute("aria-selected", active ? "true" : "false");
        if (active) thumb.scrollIntoView({ block: "nearest", inline: "center", behavior: "smooth" });
      });
      render();
    }

    const step = delta => { if (total > 1) show(index + delta); };

    root.addEventListener("click", event => {
      const control = event.target.closest("[data-lb]");
      const thumb = event.target.closest("[data-lb-thumb]");
      if (thumb) return show(Number(thumb.dataset.lbThumb));
      if (!control) return;
      const action = control.dataset.lb;
      if (action === "close") closeLightbox();
      else if (action === "prev") step(-1);
      else if (action === "next") step(1);
      else if (action === "zoom-in") setZoom(zoom * 1.4);
      else if (action === "zoom-out") setZoom(zoom / 1.4);
      else if (action === "zoom-reset") setZoom(1);
    });

    // Gestos: arrastar (quando ampliada), deslizar para trocar, pinça e duplo toque/clique.
    const pointers = new Map();
    let pinchStart = null;
    let gesture = null;
    let lastTap = null;
    let moved = false;

    stage.addEventListener("pointerdown", event => {
      stage.setPointerCapture?.(event.pointerId);
      pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
      stage.classList.add("is-dragging");
      if (pointers.size === 1) {
        moved = false;
        gesture = { startX: event.clientX, startY: event.clientY, panX, panY, time: Date.now(), onImage: event.target === img };
      } else if (pointers.size === 2) {
        const [a, b] = [...pointers.values()];
        pinchStart = { distance: Math.hypot(a.x - b.x, a.y - b.y) || 1, zoom };
        gesture = null; // evita saltos ao arrastar depois da pinça
      }
    });

    stage.addEventListener("pointermove", event => {
      if (!pointers.has(event.pointerId)) return;
      pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
      if (pointers.size >= 2 && pinchStart) {
        const [a, b] = [...pointers.values()];
        moved = true;
        setZoom(pinchStart.zoom * (Math.hypot(a.x - b.x, a.y - b.y) / pinchStart.distance), (a.x + b.x) / 2, (a.y + b.y) / 2);
        return;
      }
      if (!gesture) return;
      const dx = event.clientX - gesture.startX;
      const dy = event.clientY - gesture.startY;
      if (Math.hypot(dx, dy) > 6) moved = true;
      if (zoom > 1) {
        panX = gesture.panX + dx;
        panY = gesture.panY + dy;
        render();
      }
    });

    function endPointer(event) {
      if (!pointers.has(event.pointerId)) return;
      pointers.delete(event.pointerId);
      if (pointers.size < 2) pinchStart = null;
      if (pointers.size === 0) stage.classList.remove("is-dragging");
      if (pointers.size > 0 || !gesture) return;
      const dx = event.clientX - gesture.startX;
      const dy = event.clientY - gesture.startY;
      const elapsed = Date.now() - gesture.time;
      const startedOnImage = gesture.onImage;
      gesture = null;
      if (event.type === "pointercancel") return;
      if (zoom <= 1 && Math.abs(dx) > 55 && Math.abs(dx) > Math.abs(dy) * 1.5) {
        step(dx < 0 ? 1 : -1); // deslizar para a esquerda avança
        lastTap = null;
        return;
      }
      if (!moved && elapsed < 350 && startedOnImage) {
        const now = Date.now();
        if (lastTap && now - lastTap.time < 320 && Math.hypot(event.clientX - lastTap.x, event.clientY - lastTap.y) < 30) {
          setZoom(zoom > 1 ? 1 : 2.5, event.clientX, event.clientY); // duplo toque/clique
          lastTap = null;
        } else {
          lastTap = { time: now, x: event.clientX, y: event.clientY };
        }
      } else if (!moved && !startedOnImage) {
        closeLightbox(); // toque no fundo fecha
      }
    }
    stage.addEventListener("pointerup", endPointer);
    stage.addEventListener("pointercancel", endPointer);
    stage.addEventListener("wheel", event => {
      event.preventDefault();
      setZoom(zoom * (event.deltaY < 0 ? 1.18 : 1 / 1.18), event.clientX, event.clientY);
    }, { passive: false });

    // Captura o teclado antes de qualquer outro tratador (por exemplo, o Esc do modal de perfil).
    function onKey(event) {
      const handled = {
        Escape: closeLightbox,
        ArrowLeft: () => step(-1),
        ArrowRight: () => step(1),
        "+": () => setZoom(zoom * 1.4),
        "=": () => setZoom(zoom * 1.4),
        "-": () => setZoom(zoom / 1.4),
        "0": () => setZoom(1),
      }[event.key];
      if (handled) {
        event.preventDefault();
        event.stopImmediatePropagation();
        handled();
        return;
      }
      if (event.key === "Tab") {
        const focusable = $$("button:not([hidden]), a[href]", root).filter(el => !el.closest("[hidden]"));
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
      }
    }
    const onResize = () => render();
    document.addEventListener("keydown", onKey, true);
    window.addEventListener("resize", onResize);

    lightboxCleanup = () => {
      lightboxCleanup = null;
      document.removeEventListener("keydown", onKey, true);
      window.removeEventListener("resize", onResize);
      root.classList.add("closing");
      window.setTimeout(() => root.remove(), 160);
      document.body.classList.remove("lightbox-open");
      if (previousFocus && typeof previousFocus.focus === "function") previousFocus.focus();
    };

    show(index);
    $(".lightbox-close", root).focus();
  }

  // ------------------------------------------------------------------
  // Rodapé: sugestões e bugs
  // ------------------------------------------------------------------
  const REPORT_EMAIL = "ds.dogsteam@gmail.com";

  /** Abre o aplicativo de e-mail com destinatário, assunto e corpo preenchidos. */
  function reportByEmail(event) {
    if (event) event.preventDefault();
    const subject = "Dogs Team — Sugestão ou bug";
    const body = [
      "Olá, equipe Dogs Team!",
      "",
      "Descreva sua sugestão ou o problema (o que você fez, o que esperava e o que aconteceu):",
      "",
      "",
      "—",
      `Página: ${document.title}`,
      `Endereço: ${window.location.origin}${window.location.pathname}`,
      `Navegador: ${navigator.userAgent}`,
    ].join("\n");
    window.location.href = `mailto:${REPORT_EMAIL}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  }

  function renderFooter() {
    if ($(".site-footer")) return;
    const footer = document.createElement("footer");
    footer.className = "site-footer";
    footer.innerHTML = `<p>Sugestões ou bugs? Reporte enviando um e-mail para <a href="mailto:${REPORT_EMAIL}" data-report-email>${REPORT_EMAIL}</a></p>`;
    $("[data-report-email]", footer).addEventListener("click", reportByEmail);
    document.body.appendChild(footer);
  }

  function releaseImageUrls(root) {
    if (!root?._dogsImageUrls) return;
    root._dogsImageUrls.forEach(url => URL.revokeObjectURL(url));
    root._dogsImageUrls = [];
  }

  /** Monta a interface de listagem, upload e exclusão das imagens de um recurso. */
  function setupImageManager(root, type, resourceId, options = {}) {
    if (!root) return;
    const canUpload = Boolean(options.canUpload);
    const canDelete = Boolean(options.canDelete);
    const single = Boolean(options.single || type === "perfil");
    const title = options.title || "Imagens anexadas";
    const maxImages = Number(options.maxImages) || 0; // 0 = sem limite no cliente (o servidor sempre valida)
    // Para respostas de simulado o id só existe depois que a resposta é salva: ensureResourceId() o cria.
    let currentResourceId = resourceId;
    let imageCount = 0;
    root.classList.add("image-manager");
    root.innerHTML = `
      <div class="image-manager-toolbar">
        <button type="button" class="button button-secondary" data-image-toggle><span aria-hidden="true">▧</span> ${escapeHtml(title)}</button>
        ${canUpload ? `<label class="button button-primary image-upload-label">${options.uploadLabel || (single ? "Enviar foto" : "Adicionar imagens")}<input class="image-upload-input" type="file" accept="image/jpeg,image/png,image/webp,image/gif" ${single ? "" : "multiple"} hidden></label>` : ""}
      </div>
      <div class="image-manager-body" data-image-body hidden>
        <p class="image-manager-hint">${escapeHtml(options.hint || `JPEG, PNG, WebP ou GIF; até 5 MB por imagem${single ? "." : " e até cinco arquivos por envio."}`)}</p>
        <p class="notice" data-image-notice hidden aria-live="polite"></p>
        <div class="image-gallery" data-image-gallery><p class="muted">Carregando imagens…</p></div>
      </div>`;
    const toggle = $("[data-image-toggle]", root);
    const body = $("[data-image-body]", root);
    const gallery = $("[data-image-gallery]", root);
    const notice = $("[data-image-notice]", root);
    const input = $(".image-upload-input", root);
    let loaded = false;
    let loading = false;

    function updateCounter() {
      if (typeof options.onChange === "function") options.onChange(imageCount);
      toggle.dataset.count = String(imageCount);
      if (options.countInTitle) toggle.lastChild.textContent = ` ${title}${imageCount ? ` (${imageCount})` : ""}`;
    }

    async function loadImages() {
      if (loading) return;
      loading = true;
      gallery.innerHTML = '<p class="muted">Carregando imagens…</p>';
      try {
        // Resposta ainda não salva: não há o que listar (evita uma requisição que retornaria 400).
        if (type !== "perfil" && (currentResourceId === null || currentResourceId === undefined)) {
          loaded = true;
          imageCount = 0;
          gallery.innerHTML = '<p class="empty-state image-empty">Nenhuma imagem anexada.</p>';
          return;
        }
        const query = new URLSearchParams({ tipo: type });
        if (currentResourceId !== null && currentResourceId !== undefined) query.set("id_recurso", String(currentResourceId));
        const data = await DogsAPI.request(`/api/imagens?${query.toString()}`);
        const images = data.imagens || [];
        releaseImageUrls(root);
        loaded = true;
        imageCount = images.length;
        updateCounter();
        if (!images.length) {
          gallery.innerHTML = '<p class="empty-state image-empty">Nenhuma imagem anexada.</p>';
          return;
        }
        root._dogsGallery = images.map(item => ({ id: item.id, src: null, caption: item.nome_original || "Imagem" }));
        gallery.innerHTML = images.map(item => `
          <figure class="image-tile is-loading" data-image-item="${escapeHtml(item.id)}">
            <button type="button" class="image-open" data-image-open="${escapeHtml(item.id)}" aria-label="Ampliar ${escapeHtml(item.nome_original || "imagem")}">
              <img class="image-preview" alt="${escapeHtml(item.nome_original || "Imagem anexada")}" data-image-id="${escapeHtml(item.id)}" draggable="false">
              <span class="image-open-hint" aria-hidden="true">⤢ Ampliar</span>
            </button>
            <figcaption><span title="${escapeHtml(item.nome_original || "Imagem")}">${escapeHtml(item.nome_original || "Imagem")}</span>
              ${canDelete ? `<button type="button" class="button button-danger button-small" data-image-delete="${escapeHtml(item.id)}" aria-label="Excluir imagem">Excluir</button>` : ""}
            </figcaption>
          </figure>`).join("");
        await Promise.all(images.map(async (item, position) => {
          const img = $(`img[data-image-id="${CSS.escape(String(item.id))}"]`, gallery);
          const tile = img?.closest(".image-tile");
          if (!img) return;
          try {
            const blob = await DogsAPI.requestBlob(imageUrl(item.id));
            const url = URL.createObjectURL(blob);
            if (!root._dogsImageUrls) root._dogsImageUrls = [];
            root._dogsImageUrls.push(url);
            img.addEventListener("load", () => tile?.classList.remove("is-loading"), { once: true });
            img.src = url;
            if (root._dogsGallery?.[position]) root._dogsGallery[position].src = url;
          } catch (error) {
            img.alt = "Não foi possível carregar esta imagem";
            img.classList.add("image-unavailable");
            tile?.classList.remove("is-loading");
            tile?.classList.add("is-unavailable");
          }
        }));
      } catch (error) {
        gallery.innerHTML = `<p class="notice error">${escapeHtml(error.message)}</p>`;
      } finally {
        loading = false;
      }
    }

    if (options.startOpen) {
      body.hidden = false;
      loadImages();
    }
    toggle.addEventListener("click", async () => {
      body.hidden = !body.hidden;
      if (!body.hidden && !loaded) await loadImages();
    });
    input?.addEventListener("change", async () => {
      const files = [...(input.files || [])];
      if (!files.length) return;
      if (single && files.length !== 1) {
        message(notice, "Selecione apenas uma imagem para o perfil.", "error");
        input.value = "";
        return;
      }
      if (files.some(file => file.size > 5 * 1024 * 1024)) {
        message(notice, "Cada imagem deve ter no máximo 5 MB.", "error");
        input.value = "";
        return;
      }
      if (files.length > 5) {
        message(notice, "Envie no máximo cinco imagens por vez.", "error");
        input.value = "";
        return;
      }
      if (maxImages && imageCount + files.length > maxImages) {
        message(notice, `Esta resposta aceita no máximo ${maxImages} fotos (já há ${imageCount}).`, "error");
        input.value = "";
        return;
      }
      const uploadButton = $(".image-upload-label", root);
      if (uploadButton) uploadButton.classList.add("is-busy");
      if (body.hidden) body.hidden = false;
      try {
        if (type !== "perfil" && (currentResourceId === null || currentResourceId === undefined) && options.ensureResourceId) {
          currentResourceId = await options.ensureResourceId();
        }
        if (type !== "perfil" && !currentResourceId) throw new Error("Salve a resposta antes de anexar fotos.");
        const formData = new FormData();
        formData.append("tipo", type);
        if (type !== "perfil") formData.append("id_recurso", String(currentResourceId));
        files.forEach(file => formData.append(single ? "imagem" : "imagens[]", file));
        await DogsAPI.request("/api/imagens", { method: "POST", body: formData });
        message(notice, files.length === 1 ? "Imagem enviada com sucesso." : `${files.length} imagens enviadas com sucesso.`, "success", { toast: false });
        loaded = false;
        await loadImages();
        if (type === "perfil") await loadProfileAvatar($(".profile-avatar"));
      } catch (error) {
        message(notice, error.message, "error");
      } finally {
        input.value = "";
        if (uploadButton) uploadButton.classList.remove("is-busy");
      }
    });
    root.addEventListener("click", event => {
      const opener = event.target.closest("[data-image-open]");
      if (!opener || !root.contains(opener)) return;
      const ready = (root._dogsGallery || []).filter(item => item.src);
      const start = ready.findIndex(item => String(item.id) === opener.dataset.imageOpen);
      if (start >= 0) openLightbox(ready, start);
    });
    root.addEventListener("click", async event => {
      const button = event.target.closest("[data-image-delete]");
      if (!button || !root.contains(button)) return;
      const id = Number(button.dataset.imageDelete);
      if (!id || !confirm("Excluir esta imagem permanentemente?")) return;
      button.disabled = true;
      try {
        await DogsAPI.request("/api/imagens", { method: "DELETE", body: { id } });
        message(notice, "Imagem excluída.", "success", { toast: false });
        loaded = false;
        await loadImages();
        if (type === "perfil") await loadProfileAvatar($(".profile-avatar"));
      } catch (error) {
        message(notice, error.message, "error");
        button.disabled = false;
      }
    });
    root._dogsReloadImages = () => { loaded = false; return loadImages(); };
  }

  function initPage(page) {
    if (!requireAuth()) return false;
    renderNav(page);
    return true;
  }

  return {
    $, $$, escapeHtml, message, toast, requireAuth, renderNav, logout, formatDate,
    toDateTimeLocal, linkLinesToArray, linksToLines, initPage,
    setupImageManager, closeProfileModal,
    openLightbox, closeLightbox, imageObjectUrl, reportByEmail, renderFooter,
  };
})();

// Rodapé com o contato para sugestões e bugs em todas as páginas que carregam este script.
if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", () => DogsApp.renderFooter());
else DogsApp.renderFooter();

// Indicador de carregamento: textos "Carregando…" / "Verificando…" ganham um spinner,
// e perdem quando o conteúdo é substituído (sem alterar o JS de cada página).
(() => {
  const PATTERN = /^\s*(Carregando|Verificando|Analisando|Enviando)/i;
  const mark = node => {
    if (!(node instanceof HTMLElement)) return;
    const candidates = node.matches("p.muted, .status-pill") ? [node] : [...node.querySelectorAll("p.muted, .status-pill")];
    candidates.forEach(el => el.classList.toggle("is-loading-text", PATTERN.test(el.textContent)));
  };
  const start = () => {
    mark(document.body);
    new MutationObserver(records => {
      records.forEach(record => {
        if (record.type === "characterData") mark(record.target.parentElement);
        record.addedNodes.forEach(node => (node.nodeType === 3 ? mark(node.parentElement) : mark(node)));
      });
    }).observe(document.body, { childList: true, subtree: true, characterData: true });
  };
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
