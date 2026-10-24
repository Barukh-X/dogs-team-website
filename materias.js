if (DogsApp.initPage("materias")) {
  const { $, escapeHtml, message } = DogsApp;
  const listEl = $("#lista-materias");
  const form = $("#materia-form");
  const notice = $("#materia-msg");
  const admin = DogsAPI.userInfo().isAdmin;
  let materias = [];

  if (admin) $("#materia-admin-panel").hidden = false;
  else $("#materia-admin-note").hidden = false;

  function resetForm() {
    form.reset();
    $("#materia-id").value = "";
    $("#materia-submit").textContent = "Adicionar matéria";
    $("#materia-cancel").hidden = true;
  }

  async function carregarMaterias() {
    try {
      const data = await DogsAPI.request("/api/materias");
      materias = data.materias || [];
      if (!materias.length) {
        listEl.innerHTML = '<p class="empty-state">Ainda não há matérias cadastradas.</p>';
        return;
      }
      listEl.innerHTML = materias.map(materia => `
        <article class="resource-card">
          <div class="resource-main"><span class="resource-kicker">Matéria #${escapeHtml(materia.id)}</span><h3>${escapeHtml(materia.nome_materia)}</h3></div>
          <div class="resource-actions">
            <button class="button button-secondary" data-action="details" data-id="${escapeHtml(materia.id)}">Detalhes</button>
            ${admin ? `<button class="button button-quiet" data-action="edit" data-id="${escapeHtml(materia.id)}">Editar</button><button class="button button-danger" data-action="delete" data-id="${escapeHtml(materia.id)}">Excluir</button>` : ""}
          </div>
          <div class="details-panel" id="materia-details-${escapeHtml(materia.id)}" hidden></div>
        </article>`).join("");
    } catch (error) {
      listEl.innerHTML = `<p class="notice error">${escapeHtml(error.message)}</p>`;
    }
  }

  form.addEventListener("submit", async event => {
    event.preventDefault();
    if (!admin) return;
    const id = $("#materia-id").value;
    const nome_materia = $("#nome-materia").value.trim();
    const submit = $("#materia-submit");
    submit.disabled = true;
    try {
      await DogsAPI.request("/api/materias", {
        method: id ? "PUT" : "POST",
        body: id ? { id: Number(id), nome_materia } : { nome_materia },
      });
      message(notice, id ? "Matéria atualizada." : "Matéria criada.", "success");
      resetForm();
      await carregarMaterias();
    } catch (error) {
      message(notice, error.message, "error");
    } finally { submit.disabled = false; }
  });

  $("#materia-cancel").addEventListener("click", resetForm);
  listEl.addEventListener("click", async event => {
    const button = event.target.closest("button[data-action]");
    if (!button) return;
    const materia = materias.find(item => String(item.id) === button.dataset.id);
    if (!materia) return;

    if (button.dataset.action === "edit") {
      $("#materia-id").value = materia.id;
      $("#nome-materia").value = materia.nome_materia;
      $("#materia-submit").textContent = "Salvar alterações";
      $("#materia-cancel").hidden = false;
      form.scrollIntoView({ behavior: "smooth", block: "center" });
    }
    if (button.dataset.action === "delete") {
      if (!confirm(`Excluir a matéria “${materia.nome_materia}”?`)) return;
      try {
        await DogsAPI.request("/api/materias", { method: "DELETE", body: { id: Number(materia.id) } });
        message(notice, "Matéria excluída.", "success");
        await carregarMaterias();
      } catch (error) { message(notice, error.message, "error"); }
    }
    if (button.dataset.action === "details") {
      const panel = $(`#materia-details-${CSS.escape(String(materia.id))}`);
      if (!panel.hidden) { panel.hidden = true; return; }
      panel.hidden = false;
      panel.innerHTML = '<p class="muted">Carregando detalhes...</p>';
      try {
        const details = await DogsAPI.request(`/api/materias/detalhes?id=${encodeURIComponent(materia.id)}`);
        const dates = details.datas || [];
        const notes = details.anotacoes || [];
        panel.innerHTML = `
          <div class="detail-columns">
            <section><h4>Datas vinculadas (${dates.length})</h4>${dates.length ? dates.map(d => `<p class="detail-line"><strong>${escapeHtml(d.titulo)}</strong><span>${escapeHtml(DogsApp.formatDate(d.data_hora))}</span></p>`).join("") : '<p class="muted">Nenhuma data vinculada.</p>'}</section>
            <section><h4>Anotações visíveis (${notes.length})</h4>${notes.length ? notes.map(n => `<p class="detail-line"><strong>${escapeHtml(n.titulo)}</strong><span>${escapeHtml(n.descricao_anot || "Sem descrição")}</span></p>`).join("") : '<p class="muted">Nenhuma anotação visível.</p>'}</section>
          </div>`;
      } catch (error) { panel.innerHTML = `<p class="notice error">${escapeHtml(error.message)}</p>`; }
    }
  });
  carregarMaterias();
}
