if (DogsApp.initPage("calendario")) {
  const { $, escapeHtml, message } = DogsApp;
  const form = $("#data-form");
  const listEl = $("#lista-datas");
  const notice = $("#data-msg");
  const admin = DogsAPI.userInfo().isAdmin;
  let datas = [];
  let materias = [];

  if (admin) $("#data-admin-panel").hidden = false;
  else $("#data-admin-note").hidden = false;

  function resetForm() {
    form.reset();
    $("#data-id").value = "";
    $("#data-submit").textContent = "Adicionar data";
    $("#data-cancel").hidden = true;
  }

  async function carregarMaterias() {
    const data = await DogsAPI.request("/api/materias");
    materias = data.materias || [];
    $("#data-materia").innerHTML = `<option value="">Sem matéria vinculada</option>${materias.map(m => `<option value="${escapeHtml(m.id)}">${escapeHtml(m.nome_materia)}</option>`).join("")}`;
  }

  async function carregarDatas() {
    listEl.innerHTML = '<p class="muted">Carregando datas...</p>';
    try {
      const data = await DogsAPI.request("/api/datas-importantes");
      datas = data.datas_importantes || [];
      if (!datas.length) { listEl.innerHTML = '<p class="empty-state">Nenhuma data futura cadastrada.</p>'; return; }
      listEl.innerHTML = datas.map(d => {
        const materia = d.materia || materias.find(m => String(m.id) === String(d.id_materia))?.nome_materia || "Sem matéria";
        return `<article class="resource-card calendar-card"><div class="date-badge"><strong>${escapeHtml(new Date(String(d.data_hora).replace(" ", "T")).getDate() || "—")}</strong><span>${escapeHtml(new Intl.DateTimeFormat("pt-BR", { month: "short" }).format(new Date(String(d.data_hora).replace(" ", "T"))).replace(".", ""))}</span></div><div class="resource-main"><span class="resource-kicker">${escapeHtml(materia)}</span><h3>${escapeHtml(d.titulo)}</h3><time>${escapeHtml(DogsApp.formatDate(d.data_hora))}</time>${d.descricao ? `<p class="resource-description">${escapeHtml(d.descricao)}</p>` : ""}</div>${admin ? `<div class="resource-actions"><button class="button button-quiet" data-action="edit" data-id="${escapeHtml(d.id)}">Editar</button><button class="button button-danger" data-action="delete" data-id="${escapeHtml(d.id)}">Excluir</button></div>` : ""}</article>`;
      }).join("");
    } catch (error) { listEl.innerHTML = `<p class="notice error">${escapeHtml(error.message)}</p>`; }
  }

  form.addEventListener("submit", async event => {
    event.preventDefault();
    if (!admin) return;
    const id = $("#data-id").value;
    const idMateria = $("#data-materia").value;
    const body = {
      titulo: $("#data-titulo").value.trim(),
      descricao: $("#data-descricao").value.trim(),
      data_hora: $("#data-hora").value,
      id_materia: idMateria || "",
    };
    if (id) body.id = Number(id);
    const button = $("#data-submit"); button.disabled = true;
    try {
      await DogsAPI.request("/api/datas-importantes", { method: id ? "PUT" : "POST", body });
      message(notice, id ? "Data importante atualizada." : "Data importante criada.", "success");
      resetForm(); await carregarDatas();
    } catch (error) { message(notice, error.message, "error"); }
    finally { button.disabled = false; }
  });

  $("#data-cancel").addEventListener("click", resetForm);
  listEl.addEventListener("click", async event => {
    const button = event.target.closest("button[data-action]");
    if (!button) return;
    const item = datas.find(d => String(d.id) === button.dataset.id);
    if (!item) return;
    if (button.dataset.action === "edit") {
      $("#data-id").value = item.id;
      $("#data-titulo").value = item.titulo || "";
      $("#data-descricao").value = item.descricao || "";
      $("#data-hora").value = DogsApp.toDateTimeLocal(item.data_hora);
      $("#data-materia").value = item.id_materia || "";
      $("#data-submit").textContent = "Salvar alterações";
      $("#data-cancel").hidden = false;
      form.scrollIntoView({ behavior: "smooth", block: "center" });
    } else if (button.dataset.action === "delete") {
      if (!confirm(`Excluir a data “${item.titulo}”?`)) return;
      try {
        await DogsAPI.request("/api/datas-importantes", { method: "DELETE", body: { id: Number(item.id) } });
        message(notice, "Data importante excluída.", "success");
        await carregarDatas();
      } catch (error) { message(notice, error.message, "error"); }
    }
  });

  (async () => {
    try { await carregarMaterias(); await carregarDatas(); }
    catch (error) { message(notice, error.message, "error"); }
  })();
}
