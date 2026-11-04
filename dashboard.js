if (DogsApp.initPage("dashboard")) {
  const statusEl = document.getElementById("api-status");
  const statsEl = document.getElementById("dashboard-stats");
  const datasEl = document.getElementById("proximas-datas");
  const anotacoesEl = document.getElementById("ultimas-anotacoes");

  async function carregarPainel() {
    statusEl.textContent = "Sessão autenticada";
    statusEl.className = "status-pill online";

    try {
      const [materiasData, anotacoesData, datasData, simuladosData] = await Promise.all([
        DogsAPI.request("/api/materias"),
        DogsAPI.request("/api/anotacoes"),
        DogsAPI.request("/api/datas-importantes"),
        DogsAPI.request("/api/simulados"),
      ]);
      const materias = materiasData.materias || [];
      const anotacoes = anotacoesData.anotacoes || [];
      const datas = datasData.datas_importantes || [];
      const simulados = simuladosData.simulados || [];
      statsEl.innerHTML = `
        <article class="stat-card"><span class="stat-label">Matérias</span><strong>${materias.length}</strong><a href="materias.php">Ver matérias</a></article>
        <article class="stat-card"><span class="stat-label">Anotações visíveis</span><strong>${anotacoes.length}</strong><a href="anotacoes.php">Ver anotações</a></article>
        <article class="stat-card"><span class="stat-label">Próximas datas</span><strong>${datas.length}</strong><a href="calendario.php">Ver calendário</a></article>
        <article class="stat-card"><span class="stat-label">Simulados publicados</span><strong>${simulados.filter(s => s.status === "publicado").length}</strong><a href="simulados.php">Acessar simulados</a></article>`;

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
