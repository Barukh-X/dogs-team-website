if (DogsApp.initPage("anotacoes")) {
  const { $, escapeHtml, message } = DogsApp;
  const form = $("#anotacao-form");
  const listEl = $("#lista-anotacoes");
  const notice = $("#anotacao-msg");
  const filter = $("#filtro-materia");
  const admin = DogsAPI.userInfo().isAdmin;
  let anotacoes = [];
  let materias = [];

  if (admin) $("#anotacao-publica-wrap").hidden = false;

  function resetForm() {
    form.reset();
    $("#anotacao-id").value = "";
    $("#anotacao-submit").textContent = "Salvar anotação";
    $("#anotacao-cancel").hidden = true;
    if (admin) $("#anotacao-publica").checked = true;
  }

  async function carregarMaterias() {
    const data = await DogsAPI.request("/api/materias");
    materias = data.materias || [];
    const options = materias.map(m => `<option value="${escapeHtml(m.id)}">${escapeHtml(m.nome_materia)}</option>`).join("");
    $("#anotacao-materia").innerHTML = `<option value="">Selecione uma matéria</option>${options}`;
    filter.innerHTML = `<option value="">Todas as matérias</option>${options}`;
  }

  async function carregarAnotacoes() {
    listEl.innerHTML = '<p class="muted">Carregando anotações...</p>';
    const query = filter.value ? `?id_materia=${encodeURIComponent(filter.value)}` : "";
    try {
      const data = await DogsAPI.request(`/api/anotacoes${query}`);
      anotacoes = data.anotacoes || [];
      if (!anotacoes.length) {
        listEl.innerHTML = '<p class="empty-state">Nenhuma anotação para este filtro.</p>';
        return;
      }
      listEl.innerHTML = anotacoes.map(a => {
        const materia = materias.find(m => String(m.id) === String(a.id_materia));
        const links = (a.links || []).map(link => `<li><a href="${escapeHtml(link.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(link.titulo || link.url)}</a></li>`).join("");
        const canEdit = String(a.id_usuario) === String(DogsAPI.userInfo().userId) || (admin && a.publica);
        return `<article class="resource-card">
          <div class="resource-main"><div class="resource-heading"><span class="resource-kicker">${escapeHtml(materia?.nome_materia || `Matéria #${a.id_materia}`)}</span><span class="visibility ${a.publica ? "public" : "private"}">${a.publica ? "Pública" : "Privada"}</span></div><h3>${escapeHtml(a.titulo)}</h3><p class="resource-description">${escapeHtml(a.descricao_anot || "Sem descrição.")}</p>
          ${links ? `<div class="link-list"><strong>Links</strong><ul>${links}</ul></div>` : ""}</div>
          <div class="resource-actions">${canEdit ? `<button class="button button-quiet" data-action="edit" data-id="${escapeHtml(a.id)}">Editar</button><button class="button button-danger" data-action="delete" data-id="${escapeHtml(a.id)}">Excluir</button>` : ""}</div>
          <div class="image-manager-slot" data-image-manager-for="${escapeHtml(a.id)}"></div>
        </article>`;
      }).join("");
      listEl.querySelectorAll("[data-image-manager-for]").forEach(slot => {
        const anotacao = anotacoes.find(item => String(item.id) === slot.dataset.imageManagerFor);
        const canManageImages = anotacao && (String(anotacao.id_usuario) === String(DogsAPI.userInfo().userId) || (admin && anotacao.publica));
        DogsApp.setupImageManager(slot, "anotacao", Number(slot.dataset.imageManagerFor), {
          canUpload: Boolean(canManageImages), canDelete: Boolean(canManageImages), title: "Imagens anexadas",
        });
      });
    } catch (error) { listEl.innerHTML = `<p class="notice error">${escapeHtml(error.message)}</p>`; }
  }

  form.addEventListener("submit", async event => {
    event.preventDefault();
    const id = $("#anotacao-id").value;
    const idMateria = Number($("#anotacao-materia").value);
    if (!idMateria) return message(notice, "Selecione uma matéria.", "error");
    const body = {
      id_materia: idMateria,
      titulo: $("#anotacao-titulo").value.trim(),
      descricao_anot: $("#anotacao-descricao").value.trim(),
      links: DogsApp.linkLinesToArray($("#anotacao-links").value),
    };
    if (admin) body.publica = $("#anotacao-publica").checked;
    if (id) body.id = Number(id);
    const submit = $("#anotacao-submit"); submit.disabled = true;
    try {
      await DogsAPI.request("/api/anotacoes", { method: id ? "PUT" : "POST", body });
      message(notice, id ? "Anotação atualizada." : "Anotação criada.", "success");
      resetForm();
      await carregarAnotacoes();
    } catch (error) { message(notice, error.message, "error"); }
    finally { submit.disabled = false; }
  });

  $("#anotacao-cancel").addEventListener("click", resetForm);
  filter.addEventListener("change", carregarAnotacoes);
  listEl.addEventListener("click", async event => {
    const button = event.target.closest("button[data-action]");
    if (!button) return;
    const item = anotacoes.find(a => String(a.id) === button.dataset.id);
    if (!item) return;
    if (button.dataset.action === "edit") {
      $("#anotacao-id").value = item.id;
      $("#anotacao-materia").value = item.id_materia;
      $("#anotacao-titulo").value = item.titulo || "";
      $("#anotacao-descricao").value = item.descricao_anot || "";
      $("#anotacao-links").value = DogsApp.linksToLines(item.links);
      if (admin) $("#anotacao-publica").checked = Boolean(item.publica);
      $("#anotacao-submit").textContent = "Salvar alterações";
      $("#anotacao-cancel").hidden = false;
      form.scrollIntoView({ behavior: "smooth", block: "center" });
    } else if (button.dataset.action === "delete") {
      if (!confirm(`Excluir a anotação “${item.titulo}”?`)) return;
      try {
        await DogsAPI.request("/api/anotacoes", { method: "DELETE", body: { id: Number(item.id) } });
        message(notice, "Anotação excluída.", "success");
        await carregarAnotacoes();
      } catch (error) { message(notice, error.message, "error"); }
    }
  });

  (async () => {
    try { await carregarMaterias(); await carregarAnotacoes(); }
    catch (error) { message(notice, error.message, "error"); }
  })();
}
