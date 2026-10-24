if (DogsApp.initPage("dashboard")) {
  const statusEl = document.getElementById("api-status");
  const statsEl = document.getElementById("dashboard-stats");
  const datasEl = document.getElementById("proximas-datas");
  const anotacoesEl = document.getElementById("ultimas-anotacoes");

  async function carregarPainel() {
    try {
      const health = await DogsAPI.request("/api/health", { auth: false });
      statusEl.textContent = health.mensagem || "API online";
      statusEl.className = "status-pill online";
    } catch (error) {
      statusEl.textContent = error.message;
      statusEl.className = "status-pill offline";
    }

    try {
      const [materiasData, anotacoesData, datasData] = await Promise.all([
        DogsAPI.request("/api/materias"),
        DogsAPI.request("/api/anotacoes"),
        DogsAPI.request("/api/datas-importantes"),
      ]);
      const materias = materiasData.materias || [];
      const anotacoes = anotacoesData.anotacoes || [];
      const datas = datasData.datas_importantes || [];
      statsEl.innerHTML = `
        <article class="stat-card"><span class="stat-label">Matérias</span><strong>${materias.length}</strong><a href="materias.php">Ver matérias →</a></article>
        <article class="stat-card"><span class="stat-label">Anotações visíveis</span><strong>${anotacoes.length}</strong><a href="anotacoes.php">Ver anotações →</a></article>
        <article class="stat-card"><span class="stat-label">Próximas datas</span><strong>${datas.length}</strong><a href="calendario.php">Ver calendário →</a></article>`;

      const materiasPorId = new Map(materias.map(m => [String(m.id), m.nome_materia]));
      datasEl.innerHTML = datas.length ? datas.slice(0, 4).map(data => `
        <article class="compact-item"><div><strong>${DogsApp.escapeHtml(data.titulo)}</strong><span>${DogsApp.escapeHtml(data.materia || materiasPorId.get(String(data.id_materia)) || "Sem matéria")}</span></div><time>${DogsApp.escapeHtml(DogsApp.formatDate(data.data_hora))}</time></article>`).join("") : '<p class="empty-state">Nenhuma data importante futura cadastrada.</p>';
      anotacoesEl.innerHTML = anotacoes.length ? anotacoes.slice(0, 4).map(anotacao => `
        <article class="compact-item"><div><strong>${DogsApp.escapeHtml(anotacao.titulo)}</strong><span>${anotacao.publica ? "Pública" : "Privada"}</span></div><p>${DogsApp.escapeHtml(anotacao.descricao_anot || "Sem descrição")}</p></article>`).join("") : '<p class="empty-state">Nenhuma anotação encontrada.</p>';
    } catch (error) {
      statsEl.innerHTML = `<p class="notice error">${DogsApp.escapeHtml(error.message)}</p>`;
      datasEl.innerHTML = "";
      anotacoesEl.innerHTML = "";
    }
  }
  carregarPainel();
}
