if (DogsApp.initPage("ranking")) {
  const { $, $$, escapeHtml } = DogsApp;
  const user = DogsAPI.userInfo();
  const contentEl = $("#ranking-conteudo");
  const myEl = $("#meu-ranking");
  const subjectFilter = $("#filtro-ranking-materia");
  const fmt = value => Number(value ?? 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  let requestId = 0;

  function initials(name) {
    const parts = String(name || "?").trim().split(/\s+/).filter(Boolean);
    const letters = (parts[0]?.[0] || "") + (parts.length > 1 ? parts[parts.length - 1][0] : "");
    return letters.toUpperCase() || "?";
  }

  // Avatar com as iniciais; a foto de perfil (quando existe) é carregada sob demanda.
  function avatarHtml(item, large = false) {
    const photo = item.id_imagem_perfil
      ? `data-avatar-image="${escapeHtml(item.id_imagem_perfil)}" data-avatar-name="${escapeHtml(item.nome)}" role="button" tabindex="0" aria-label="Ver foto de ${escapeHtml(item.nome)}" title="Ver foto"`
      : 'aria-hidden="true"';
    return `<span class="rank-avatar${large ? " large" : ""}" ${photo}>${escapeHtml(initials(item.nome))}</span>`;
  }

  let observer = null;
  const queue = [];
  let active = 0;
  const MAX_PARALLEL = 3;

  function pump() {
    while (active < MAX_PARALLEL && queue.length) {
      const el = queue.shift();
      if (!el.isConnected) continue;
      active++;
      DogsApp.imageObjectUrl(el.dataset.avatarImage)
        .then(url => {
          el.innerHTML = `<img src="${url}" alt="" draggable="false">`;
          el.classList.add("has-photo");
        })
        .catch(() => { /* mantém as iniciais */ })
        .finally(() => { active--; pump(); });
    }
  }

  function hydrateAvatars(root) {
    if (observer) observer.disconnect();
    queue.length = 0;
    const avatars = $$("[data-avatar-image]", root);
    if (!("IntersectionObserver" in window)) {
      queue.push(...avatars);
      pump();
      return;
    }
    observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        observer.unobserve(entry.target);
        queue.push(entry.target);
      });
      pump();
    }, { rootMargin: "160px" });
    avatars.forEach(el => observer.observe(el));
  }

  async function openPhoto(el) {
    try {
      const url = await DogsApp.imageObjectUrl(el.dataset.avatarImage);
      DogsApp.openLightbox([{ src: url, caption: el.dataset.avatarName }]);
    } catch (error) {
      alert(error.message);
    }
  }

  document.addEventListener("click", event => {
    const el = event.target.closest("[data-avatar-image]");
    if (el) openPhoto(el);
  });
  document.addEventListener("keydown", event => {
    if (event.key !== "Enter" && event.key !== " ") return;
    const el = event.target.closest?.("[data-avatar-image]");
    if (!el) return;
    event.preventDefault();
    openPhoto(el);
  });

  function positionBadge(position) {
    const medal = position <= 3 ? ` rank-top-${position}` : "";
    return `<span class="rank-badge${medal}">${escapeHtml(position)}º</span>`;
  }

  function renderMine(data) {
    if (!data.usuario_participa) {
      myEl.hidden = false;
      myEl.className = "my-rank-card is-neutral";
      myEl.innerHTML = '<div><span class="eyebrow">Administrador</span><h2>Você não participa do ranking</h2><p>Administradores acompanham a classificação, mas não são listados nela.</p></div>';
      return;
    }
    myEl.hidden = false;
    const mine = data.minha_posicao;
    if (!mine) {
      myEl.className = "my-rank-card is-neutral";
      myEl.innerHTML = '<div><span class="eyebrow">Sua posição</span><h2>Ainda sem pontuação</h2><p>Conclua um simulado para entrar no ranking. Se houver questões discursivas, a nota é computada depois da correção.</p></div><a class="button button-primary" href="simulados.php">Ir para simulados</a>';
      return;
    }
    myEl.className = "my-rank-card";
    myEl.innerHTML = `<div class="my-rank-position">${positionBadge(mine.posicao)}${avatarHtml(mine, true)}</div>
      <div><span class="eyebrow">Sua posição</span><h2>${escapeHtml(mine.posicao)}º de ${escapeHtml(data.total_participantes)} estudantes</h2><p>${fmt(mine.pontuacao_total)} pontos · média ${fmt(mine.media)}% · ${escapeHtml(mine.simulados_concluidos)} simulado(s) concluído(s)</p></div>`;
  }

  function renderTable(data) {
    const list = data.ranking || [];
    if (!list.length) {
      contentEl.innerHTML = '<p class="empty-state">Ninguém pontuou ainda. O ranking aparece assim que os primeiros simulados forem concluídos e corrigidos.</p>';
      return;
    }
    contentEl.innerHTML = `<div class="table-scroll"><table class="ranking-table">
      <thead><tr><th scope="col">Posição</th><th scope="col">Estudante</th><th scope="col" class="num">Simulados</th><th scope="col" class="num">Média</th><th scope="col" class="num">Pontuação</th></tr></thead>
      <tbody>${list.map(item => {
        const me = String(item.id_usuario) === String(user.userId);
        return `<tr class="${me ? "is-me" : ""}"><td data-label="Posição"><div class="rank-position">${positionBadge(item.posicao)}${avatarHtml(item)}</div></td><td data-label="Estudante"><strong>${escapeHtml(item.nome)}</strong>${me ? ' <span class="mini-chip ok">você</span>' : ""}</td><td class="num" data-label="Simulados">${escapeHtml(item.simulados_concluidos)}</td><td class="num" data-label="Média">${fmt(item.media)}%</td><td class="num" data-label="Pontuação"><strong>${fmt(item.pontuacao_total)}</strong></td></tr>`;
      }).join("")}</tbody></table></div>`;
  }

  async function loadRanking() {
    const current = ++requestId;
    contentEl.innerHTML = '<p class="muted">Carregando ranking…</p>';
    try {
      const subject = subjectFilter.value;
      const data = await DogsAPI.request(`/api/simulados/ranking${subject ? `?id_materia=${encodeURIComponent(subject)}` : ""}`);
      if (current !== requestId) return;
      renderMine(data);
      renderTable(data);
      hydrateAvatars(document);
    } catch (error) {
      if (current !== requestId) return;
      contentEl.innerHTML = `<p class="notice error">${escapeHtml(error.message)}</p>`;
    }
  }

  async function init() {
    try {
      const data = await DogsAPI.request("/api/materias");
      subjectFilter.insertAdjacentHTML("beforeend", (data.materias || []).map(m => `<option value="${escapeHtml(m.id)}">${escapeHtml(m.nome_materia)}</option>`).join(""));
    } catch { /* o filtro é opcional */ }
    subjectFilter.addEventListener("change", loadRanking);
    await loadRanking();
  }
  init();
}
