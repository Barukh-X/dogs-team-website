if (DogsApp.initPage("simulados")) {
  const { $, $$, escapeHtml, message } = DogsApp;
  const user = DogsAPI.userInfo();
  const editorPanel = $("#simulado-editor-panel");
  const simuladoForm = $("#simulado-form");
  const questionEditor = $("#questoes-editor");
  const questionForm = $("#questao-form");
  const listEl = $("#lista-simulados");
  const historyEl = $("#lista-historico");
  const attemptPanel = $("#tentativa-panel");
  const resultPanel = $("#resultado-panel");
  let materias = [];
  let datas = [];
  let simulados = [];
  let historico = [];
  let editingSimuladoId = null;
  let editingQuestionId = null;
  let currentSimulado = null;
  let currentQuestions = [];
  let currentAttempt = null;
  let currentQuestionIndex = 0;
  let answerSaveChain = Promise.resolve();
  let timerId = null;
  let autoFinishing = false;
  let textSaveTimer = null;

  const TYPE_LABELS = { objetiva: "Objetiva", discursiva: "Discursiva", objetiva_justificativa: "Objetiva + justificativa" };
  const TYPE_HELP = {
    objetiva: "Correção automática pelo gabarito.",
    discursiva: "Resposta escrita pelo aluno, corrigida por um administrador.",
    objetiva_justificativa: "O aluno escolhe uma alternativa e a justifica; um administrador atribui a nota.",
  };
  const hasAlternatives = type => type !== "discursiva";
  const fmtPoints = value => Number(value ?? 0).toLocaleString("pt-BR", { maximumFractionDigits: 2 });
  const typeBadge = type => `<span class="type-badge type-${escapeHtml(type || "objetiva")}">${escapeHtml(TYPE_LABELS[type] || TYPE_LABELS.objetiva)}</span>`;
  const multiline = text => escapeHtml(text).replace(/\n/g, "<br>");
  // Enunciados, alternativas e explicações: Markdown simplificado, código e LaTeX (richtext.js escapa antes de formatar).
  const rich = (text, inline = false) => (window.DogsRich ? window.DogsRich.render(text, { inline }) : (inline ? multiline(text) : `<p>${multiline(text)}</p>`));
  const enhanceRich = root => { if (window.DogsRich && root) window.DogsRich.enhance(root); };

  function optionList(items, valueKey, labelKey, placeholder) {
    return `<option value="">${escapeHtml(placeholder)}</option>` + items.map(item => `<option value="${escapeHtml(item[valueKey])}">${escapeHtml(item[labelKey])}</option>`).join("");
  }

  function fillMateriaSelects() {
    const all = optionList(materias, "id", "nome_materia", "Todas as matérias");
    $("#filtro-simulado-materia").innerHTML = all;
    $("#simulado-materia").innerHTML = optionList(materias, "id", "nome_materia", "Selecione uma matéria");
    filterDatesForSelectedSubject();
  }

  function filterDatesForSelectedSubject(selected = "") {
    const materiaId = $("#simulado-materia").value;
    const relevant = datas.filter(item => !item.id_materia || !materiaId || String(item.id_materia) === String(materiaId));
    // Preserva uma associação anterior mesmo quando a data já passou e não está mais no feed de próximas datas.
    if (selected && !relevant.some(d => String(d.id) === String(selected))
        && currentSimulado
        && String(currentSimulado.id_materia) === String(materiaId)
        && String(currentSimulado.id_data_importante) === String(selected)
        && currentSimulado.data_titulo) {
      relevant.push({ id: currentSimulado.id_data_importante, titulo: currentSimulado.data_titulo, data_hora: currentSimulado.data_prova, id_materia: materiaId });
    }
    $("#simulado-data").innerHTML = optionList(relevant.map(d => ({ id: d.id, titulo: `${d.titulo} — ${DogsApp.formatDate(d.data_hora)}` })), "id", "titulo", "Sem data associada");
    $("#simulado-data").value = relevant.some(d => String(d.id) === String(selected)) ? String(selected) : "";
  }

  async function loadInitialData() {
    const [materiaResult, dateResult] = await Promise.all([
      DogsAPI.request("/api/materias"),
      DogsAPI.request("/api/datas-importantes"),
    ]);
    materias = materiaResult.materias || [];
    datas = dateResult.datas_importantes || [];
    fillMateriaSelects();
    await Promise.all([loadSimulados(), loadHistory()]);
  }

  async function loadSimulados() {
    listEl.innerHTML = '<p class="muted">Carregando simulados…</p>';
    try {
      const materiaId = $("#filtro-simulado-materia").value;
      const query = materiaId ? `?id_materia=${encodeURIComponent(materiaId)}` : "";
      const data = await DogsAPI.request(`/api/simulados${query}`);
      simulados = data.simulados || [];
      renderSimulados();
    } catch (error) {
      listEl.innerHTML = `<p class="notice error">${escapeHtml(error.message)}</p>`;
    }
  }

  // Somente administradores criam e gerenciam simulados (o servidor também valida).
  function canManage() {
    return Boolean(user.isAdmin);
  }

  function renderSimulados() {
    if (!simulados.length) {
      listEl.innerHTML = `<p class="empty-state">${user.isAdmin ? "Nenhum simulado encontrado. Crie um rascunho para começar." : "Nenhum simulado publicado por enquanto. Volte mais tarde."}</p>`;
      return;
    }
    listEl.innerHTML = simulados.map(item => {
      const manage = canManage(item);
      const draft = item.status === "rascunho";
      const subject = item.materia || materias.find(m => String(m.id) === String(item.id_materia))?.nome_materia || "Matéria";
      const dateLabel = item.data_titulo ? `${item.data_titulo}${item.data_prova ? ` · ${DogsApp.formatDate(item.data_prova)}` : ""}` : "Sem prova associada";
      return `<article class="simulado-card" data-simulado-card="${escapeHtml(item.id)}">
        <div class="simulado-card-head"><span class="simulado-icon" aria-hidden="true">✦</span><span class="simulado-status ${draft ? "draft" : "published"}">${draft ? "Rascunho" : "Publicado"}</span></div>
        <span class="resource-kicker">${escapeHtml(subject)}</span><h3>${escapeHtml(item.titulo)}</h3>
        <p class="simulado-description">${escapeHtml(item.descricao || "Sem descrição.")}</p>
        <div class="simulado-meta"><span>▤ ${Number(item.quantidade_questoes || 0)} questões</span><span>◷ ${item.duracao_minutos ? `${Number(item.duracao_minutos)} min` : "Sem limite"}</span>${Number(item.quantidade_correcao_manual || 0) ? `<span>✎ ${Number(item.quantidade_correcao_manual)} com correção manual</span>` : ""}</div>
        <p class="simulado-date">▦ ${escapeHtml(dateLabel)}</p>
        <div class="simulado-actions">
          ${user.isAdmin ? `<button type="button" class="button button-secondary" data-sim-action="details" data-id="${escapeHtml(item.id)}">Detalhes</button>` : ""}
          ${!draft ? `<button type="button" class="button button-primary" data-sim-action="start" data-id="${escapeHtml(item.id)}">Iniciar simulado</button>` : ""}
          ${draft && manage ? `<button type="button" class="button button-quiet" data-sim-action="edit" data-id="${escapeHtml(item.id)}">Editar</button><button type="button" class="button button-secondary" data-sim-action="publish" data-id="${escapeHtml(item.id)}">Publicar</button><button type="button" class="button button-danger" data-sim-action="delete" data-id="${escapeHtml(item.id)}">Excluir</button>` : ""}
        </div>
        <div class="simulado-details" data-sim-details hidden></div>
      </article>`;
    }).join("");
  }

  async function loadDetails(id) {
    const data = await DogsAPI.request(`/api/simulados/detalhes?id=${encodeURIComponent(id)}`);
    return data.simulado;
  }

  async function showDetails(id) {
    // Somente administradores veem as questões antes de iniciar (o servidor também bloqueia).
    if (!user.isAdmin) return;
    const card = listEl.querySelector(`[data-simulado-card="${CSS.escape(String(id))}"]`);
    const panel = card?.querySelector("[data-sim-details]");
    if (!panel) return;
    if (!panel.hidden && panel.dataset.loaded === "1") { panel.hidden = true; return; }
    panel.hidden = false;
    panel.innerHTML = '<p class="muted">Carregando detalhes…</p>';
    try {
      const detail = await loadDetails(id);
      const manage = canManage(detail) && detail.status === "rascunho";
      const questions = detail.questoes || [];
      panel.innerHTML = `<div class="detail-divider"></div>
        <div class="detail-info"><p>${escapeHtml(detail.descricao || "Este simulado não tem descrição adicional.")}</p><span>${escapeHtml(detail.criador ? `Criado por ${detail.criador}` : "")}</span></div>
        ${questions.length ? `<div class="detail-question-list"><h4>Conteúdo (${questions.length} questões)</h4>${questions.map((q, i) => `<article class="detail-question"><strong class="detail-question-number">${i + 1}.</strong><div class="detail-question-text">${rich(q.enunciado)}</div><div class="question-type-line">${typeBadge(q.tipo)}<span>${fmtPoints(q.pontos)} ponto(s)</span></div>${(q.alternativas || []).length ? `<ul>${(q.alternativas || []).map(a => `<li>${rich(a.texto, true)}${manage && a.correta ? ' <span class="correct-answer-tag">Gabarito</span>' : ""}</li>`).join("")}</ul>` : ""}${manage && q.explicacao ? `<div class="answer-block explanation"><span>Explicação</span>${rich(q.explicacao)}</div>` : ""}<div class="image-manager-slot" data-question-images="${escapeHtml(q.id)}"></div></article>`).join("")}</div>` : '<p class="empty-state">Este simulado ainda não tem questões.</p>'}`;
      enhanceRich(panel);
      panel.dataset.loaded = "1";
      panel.querySelectorAll("[data-question-images]").forEach(slot => {
        DogsApp.setupImageManager(slot, "questao", Number(slot.dataset.questionImages), {
          canUpload: manage, canDelete: manage, title: "Imagens da questão",
        });
      });
    } catch (error) {
      panel.innerHTML = `<p class="notice error">${escapeHtml(error.message)}</p>`;
    }
  }

  function openEditor() {
    resetSimuladoForm();
    editorPanel.hidden = false;
    questionEditor.hidden = true;
    editorPanel.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function resetSimuladoForm() {
    simuladoForm.reset();
    $("#simulado-id").value = "";
    editingSimuladoId = null;
    currentSimulado = null;
    $("#simulado-editor-title").textContent = "Criar simulado";
    $("#simulado-salvar").textContent = "Criar rascunho";
    $("#simulado-data").value = "";
    filterDatesForSelectedSubject();
    questionEditor.hidden = true;
    resetQuestionForm();
    message($("#simulado-form-msg"), "");
  }

  async function editSimulado(id) {
    try {
      const detail = await loadDetails(id);
      if (!canManage(detail) || detail.status !== "rascunho") {
        throw new Error("Este simulado não pode mais ser editado.");
      }
      editingSimuladoId = Number(detail.id);
      currentSimulado = detail;
      $("#simulado-id").value = detail.id;
      $("#simulado-titulo").value = detail.titulo || "";
      $("#simulado-materia").value = String(detail.id_materia);
      filterDatesForSelectedSubject(detail.id_data_importante || "");
      $("#simulado-descricao").value = detail.descricao || "";
      $("#simulado-duracao").value = detail.duracao_minutos ?? "";
      $("#simulado-editor-title").textContent = `Editar: ${detail.titulo}`;
      $("#simulado-salvar").textContent = "Salvar alterações";
      editorPanel.hidden = false;
      questionEditor.hidden = false;
      renderQuestionAdminList(detail.questoes || []);
      resetQuestionForm();
      message($("#simulado-form-msg"), "Rascunho carregado. Você pode editar os dados e as questões.", "info");
      editorPanel.scrollIntoView({ behavior: "smooth", block: "start" });
    } catch (error) {
      alert(error.message);
    }
  }

  async function saveSimulado(event) {
    event.preventDefault();
    const title = $("#simulado-titulo").value.trim();
    const materiaId = Number($("#simulado-materia").value);
    if (!title || !materiaId) return message($("#simulado-form-msg"), "Informe o título e a matéria.", "error");
    const rawDuration = $("#simulado-duracao").value;
    const body = {
      titulo: title,
      id_materia: materiaId,
      id_data_importante: $("#simulado-data").value ? Number($("#simulado-data").value) : null,
      descricao: $("#simulado-descricao").value.trim() || null,
      duracao_minutos: rawDuration ? Number(rawDuration) : null,
    };
    if (editingSimuladoId) body.id = editingSimuladoId;
    const button = $("#simulado-salvar");
    button.disabled = true;
    try {
      if (editingSimuladoId) {
        await DogsAPI.request("/api/simulados", { method: "PUT", body });
        message($("#simulado-form-msg"), "Simulado atualizado.", "success");
      } else {
        const result = await DogsAPI.request("/api/simulados", { method: "POST", body });
        editingSimuladoId = Number(result.id);
        $("#simulado-id").value = String(editingSimuladoId);
        message($("#simulado-form-msg"), "Rascunho criado. Agora adicione as questões.", "success");
        $("#simulado-editor-title").textContent = `Editar: ${title}`;
        $("#simulado-salvar").textContent = "Salvar alterações";
        questionEditor.hidden = false;
      }
      currentSimulado = await loadDetails(editingSimuladoId);
      renderQuestionAdminList(currentSimulado.questoes || []);
      await loadSimulados();
    } catch (error) {
      message($("#simulado-form-msg"), error.message, "error");
    } finally {
      button.disabled = false;
    }
  }

  function renderQuestionAdminList(questions) {
    currentQuestions = questions || [];
    const target = $("#lista-questoes-editor");
    if (!currentQuestions.length) {
      target.innerHTML = '<p class="empty-state">Nenhuma questão adicionada. Use o formulário abaixo para começar.</p>';
      return;
    }
    target.innerHTML = currentQuestions.map((q, index) => `
      <article class="question-admin-card">
        <div class="question-admin-number">${index + 1}</div>
        <div class="question-admin-main"><div class="question-admin-title">${rich(q.enunciado)}</div><p>${typeBadge(q.tipo)} ${hasAlternatives(q.tipo) ? `${(q.alternativas || []).length} alternativas · ` : "Resposta escrita · "}${fmtPoints(q.pontos || 1)} ponto(s)</p><ol type="A">${(q.alternativas || []).map(a => `<li>${rich(a.texto, true)}${a.correta ? ' <span class="correct-answer-tag">Correta</span>' : ""}</li>`).join("")}</ol>
          <div class="image-manager-slot" data-question-images="${escapeHtml(q.id)}"></div>
        </div>
        <div class="resource-actions"><button class="button button-quiet" type="button" data-question-action="edit" data-id="${escapeHtml(q.id)}">Editar</button><button class="button button-danger" type="button" data-question-action="delete" data-id="${escapeHtml(q.id)}">Excluir</button></div>
      </article>`).join("");
    enhanceRich(target);
    target.querySelectorAll("[data-question-images]").forEach(slot => DogsApp.setupImageManager(slot, "questao", Number(slot.dataset.questionImages), {
      canUpload: true, canDelete: true, title: "Imagem da questão",
    }));
  }

  function renderAlternativeBuilder(values = [{ texto: "", correta: true }, { texto: "", correta: false }, { texto: "", correta: false }, { texto: "", correta: false }]) {
    const target = $("#alternativas-editor");
    target.innerHTML = values.map((value, index) => `
      <div class="alternative-builder-row" data-alt-row="${index}">
        <input type="radio" name="correct-answer" value="${index}" aria-label="Marcar alternativa ${index + 1} como correta" ${value.correta ? "checked" : ""}>
        <span class="alternative-letter">${String.fromCharCode(65 + index)}</span>
        <input type="text" data-alt-text value="${escapeHtml(value.texto)}" maxlength="5000" placeholder="Texto da alternativa ${index + 1}" required>
        <button class="button button-quiet button-small" type="button" data-alt-remove="${index}" aria-label="Remover alternativa ${index + 1}" ${values.length <= 2 ? "disabled" : ""}>×</button>
      </div>`).join("");
  }

  function applyQuestionType() {
    const type = $("#questao-tipo").value;
    const field = $("#alternativas-field");
    field.hidden = !hasAlternatives(type);
    // Campos desabilitados ficam fora da validação nativa do formulário.
    field.disabled = !hasAlternatives(type);
    $("#questao-tipo-ajuda").textContent = TYPE_HELP[type] || "";
  }

  function resetQuestionForm() {
    questionForm.reset();
    $("#questao-id").value = "";
    $("#questao-pontos").value = "1";
    $("#questao-tipo").value = "objetiva";
    applyQuestionType();
    editingQuestionId = null;
    $("#questao-form-title").textContent = "Adicionar questão";
    $("#questao-salvar").textContent = "Adicionar questão";
    $("#questao-cancelar").hidden = true;
    renderAlternativeBuilder();
    message($("#questao-form-msg"), "");
  }

  function readAlternatives() {
    const rows = $$("[data-alt-row]", $("#alternativas-editor"));
    if (rows.length < 2 || rows.length > 10) throw new Error("Cada questão deve ter de 2 a 10 alternativas.");
    const checked = $("input[name='correct-answer']:checked", $("#alternativas-editor"));
    if (!checked) throw new Error("Marque a alternativa correta.");
    const alternatives = rows.map((row, index) => ({
      texto: $("[data-alt-text]", row).value.trim(),
      correta: Number(checked.value) === index,
    }));
    if (alternatives.some(item => !item.texto)) throw new Error("Preencha o texto de todas as alternativas.");
    return alternatives;
  }

  async function saveQuestion(event) {
    event.preventDefault();
    if (!editingSimuladoId) return message($("#questao-form-msg"), "Salve o simulado antes de adicionar questões.", "error");
    const type = $("#questao-tipo").value;
    let alternatives = [];
    if (hasAlternatives(type)) {
      try { alternatives = readAlternatives(); }
      catch (error) { return message($("#questao-form-msg"), error.message, "error"); }
    }
    const body = {
      id_simulado: editingSimuladoId,
      enunciado: $("#questao-enunciado").value.trim(),
      tipo: type,
      pontos: Number($("#questao-pontos").value || 1),
    };
    if (hasAlternatives(type)) body.alternativas = alternatives;
    if (!body.enunciado) return message($("#questao-form-msg"), "Informe o enunciado da questão.", "error");
    if (editingQuestionId) body.id = editingQuestionId;
    const button = $("#questao-salvar"); button.disabled = true;
    try {
      if (editingQuestionId) await DogsAPI.request("/api/simulados/questoes", { method: "PUT", body });
      else await DogsAPI.request("/api/simulados/questoes", { method: "POST", body });
      const wasEditing = Boolean(editingQuestionId);
      resetQuestionForm();
      currentSimulado = await loadDetails(editingSimuladoId);
      renderQuestionAdminList(currentSimulado.questoes || []);
      message($("#questao-form-msg"), wasEditing ? "Questão atualizada." : "Questão adicionada. Você já pode anexar imagens a ela.", "success");
      await loadSimulados();
    } catch (error) {
      message($("#questao-form-msg"), error.message, "error");
    } finally { button.disabled = false; }
  }

  async function publishSimulado(id) {
    if (!confirm("Publicar este simulado? Depois da publicação, questões e alternativas ficam bloqueadas para preservar o histórico.")) return;
    try {
      await DogsAPI.request("/api/simulados/publicar", { method: "POST", body: { id: Number(id) } });
      message($("#simulado-form-msg"), "Simulado publicado com sucesso.", "success");
      if (Number(editingSimuladoId) === Number(id)) {
        editorPanel.hidden = true;
        resetSimuladoForm();
      }
      await Promise.all([loadSimulados(), loadHistory()]);
    } catch (error) { alert(error.message); }
  }

  async function deleteSimulado(id) {
    const item = simulados.find(s => String(s.id) === String(id));
    if (!confirm(`Excluir o rascunho “${item?.titulo || "simulado"}”? Esta operação não pode ser desfeita.`)) return;
    try {
      await DogsAPI.request("/api/simulados", { method: "DELETE", body: { id: Number(id) } });
      if (Number(editingSimuladoId) === Number(id)) resetSimuladoForm();
      editorPanel.hidden = true;
      await loadSimulados();
    } catch (error) { alert(error.message); }
  }

  async function editQuestion(id) {
    const question = currentQuestions.find(q => String(q.id) === String(id));
    if (!question) return;
    editingQuestionId = Number(question.id);
    $("#questao-id").value = String(question.id);
    $("#questao-enunciado").value = question.enunciado || "";
    $("#questao-pontos").value = question.pontos || 1;
    $("#questao-tipo").value = question.tipo || "objetiva";
    applyQuestionType();
    if ((question.alternativas || []).length) {
      renderAlternativeBuilder(question.alternativas.map(a => ({ texto: a.texto, correta: Boolean(a.correta) })));
    } else {
      renderAlternativeBuilder();
    }
    $("#questao-form-title").textContent = `Editar questão ${question.ordem || ""}`;
    $("#questao-salvar").textContent = "Salvar questão";
    $("#questao-cancelar").hidden = false;
    questionForm.scrollIntoView({ behavior: "smooth", block: "center" });
  }

  async function deleteQuestion(id) {
    const question = currentQuestions.find(q => String(q.id) === String(id));
    if (!confirm(`Excluir a questão “${(question?.enunciado || "").slice(0, 100)}”?`)) return;
    try {
      await DogsAPI.request("/api/simulados/questoes", { method: "DELETE", body: { id: Number(id) } });
      currentSimulado = await loadDetails(editingSimuladoId);
      renderQuestionAdminList(currentSimulado.questoes || []);
      await loadSimulados();
    } catch (error) { alert(error.message); }
  }

  async function loadHistory() {
    historyEl.innerHTML = '<p class="muted">Carregando histórico…</p>';
    try {
      const data = await DogsAPI.request("/api/simulados/tentativas/historico");
      historico = data.historico || [];
      if (!historico.length) {
        historyEl.innerHTML = '<p class="empty-state">Suas tentativas aparecerão aqui depois que você começar um simulado.</p>';
        return;
      }
      const statusLabel = item => item.status === "concluida"
        ? `Concluído · Nota ${Number(item.nota || 0).toFixed(2)}%`
        : (item.status === "aguardando_correcao" ? "Aguardando correção de um administrador" : "Em andamento");
      historyEl.innerHTML = historico.map(item => `
        <article class="history-row">
          <div class="history-mark ${item.status === "concluida" ? "done" : "pending"}">${item.status === "concluida" ? "✓" : "◷"}</div>
          <div class="history-main"><h3>${escapeHtml(item.simulado)}</h3><p>${escapeHtml(item.materia)} · ${escapeHtml(DogsApp.formatDate(item.iniciada_em))}</p><span class="history-status">${escapeHtml(statusLabel(item))}</span></div>
          <div class="history-actions">${item.status !== "em_andamento" ? `<button class="button button-secondary" data-history-action="result" data-id="${escapeHtml(item.id_tentativa)}">Ver resultado</button>` : `<button class="button button-primary" data-history-action="resume" data-id="${escapeHtml(item.id_tentativa)}">Continuar</button>`}</div>
        </article>`).join("");
    } catch (error) { historyEl.innerHTML = `<p class="notice error">${escapeHtml(error.message)}</p>`; }
  }

  async function startAttempt(simuladoId) {
    const simulado = simulados.find(item => String(item.id) === String(simuladoId));
    const rankingNote = user.isAdmin ? " Administradores não participam do ranking." : "";
    if (!confirm(`Iniciar “${simulado?.titulo || "este simulado"}”? A tentativa será registrada no histórico.${rankingNote}`)) return;
    try {
      const result = await DogsAPI.request("/api/simulados/tentativas/iniciar", { method: "POST", body: { id_simulado: Number(simuladoId) } });
      sessionStorage.setItem(`dogs-attempt-start-${result.id_tentativa}`, String(Date.now()));
      await openAttempt(Number(result.id_tentativa));
      await loadHistory();
    } catch (error) { alert(error.message); }
  }

  async function openAttempt(attemptId) {
    stopTimer();
    try {
      const data = await DogsAPI.request(`/api/simulados/tentativas/questoes?id_tentativa=${encodeURIComponent(attemptId)}`);
      currentAttempt = { ...data.tentativa, questions: data.questoes || [] };
      currentQuestionIndex = 0;
      answerSaveChain = Promise.resolve();
      autoFinishing = false;
      const simulado = simulados.find(item => String(item.id) === String(currentAttempt.id_simulado));
      $("#tentativa-titulo").textContent = simulado?.titulo || "Simulado em andamento";
      $("#tentativa-subtitulo").textContent = `${currentAttempt.questions.length} questões · As respostas são salvas automaticamente.`;
      editorPanel.hidden = true;
      resultPanel.hidden = true;
      attemptPanel.hidden = false;
      attemptPanel.scrollIntoView({ behavior: "smooth", block: "start" });
      renderCurrentQuestion();
      startTimer(currentAttempt);
    } catch (error) {
      // Uma tentativa expirada pode ter sido finalizada automaticamente pelo backend.
      try {
        const result = await DogsAPI.request(`/api/simulados/tentativas/resultado?id_tentativa=${encodeURIComponent(attemptId)}`);
        if (result.tentativa?.status && result.tentativa.status !== "em_andamento") {
          await showResult(attemptId);
          return;
        }
      } catch { /* apresentar o erro inicial */ }
      alert(error.message);
    }
  }

  function startTimer(attempt) {
    stopTimer();
    const timer = $("#tentativa-timer");
    const duration = Number(attempt.duracao_minutos || 0);
    if (!duration) {
      timer.textContent = "Sem limite de tempo";
      return;
    }
    const storedStart = sessionStorage.getItem(`dogs-attempt-start-${attempt.id}`);
    const startedAt = storedStart ? Number(storedStart) : Date.now();
    if (!storedStart) sessionStorage.setItem(`dogs-attempt-start-${attempt.id}`, String(startedAt));
    const endAt = startedAt + duration * 60 * 1000;
    const tick = () => {
      const left = Math.max(0, endAt - Date.now());
      const totalSeconds = Math.ceil(left / 1000);
      const mm = String(Math.floor(totalSeconds / 60)).padStart(2, "0");
      const ss = String(totalSeconds % 60).padStart(2, "0");
      timer.textContent = `Tempo restante ${mm}:${ss}`;
      timer.classList.toggle("timer-warning", left < 5 * 60 * 1000);
      if (left <= 0 && !autoFinishing) {
        autoFinishing = true;
        stopTimer();
        finalizeAttempt(true);
      }
    };
    tick();
    timerId = window.setInterval(tick, 1000);
  }

  function stopTimer() {
    if (timerId !== null) window.clearInterval(timerId);
    timerId = null;
  }

  function renderCurrentQuestion() {
    if (!currentAttempt) return;
    window.clearTimeout(textSaveTimer);
    const questions = currentAttempt.questions;
    if (!questions.length) {
      $("#tentativa-conteudo").innerHTML = '<p class="empty-state">Este simulado não possui questões.</p>';
      return;
    }
    currentQuestionIndex = Math.min(Math.max(0, currentQuestionIndex), questions.length - 1);
    const q = questions[currentQuestionIndex];
    const type = q.tipo || "objetiva";
    const selected = q.alternativa_selecionada == null ? "" : String(q.alternativa_selecionada);
    $("#tentativa-progress").style.width = `${((currentQuestionIndex + 1) / questions.length) * 100}%`;

    const optionsHtml = hasAlternatives(type) ? `
        <fieldset class="runner-options"><legend>Selecione uma alternativa</legend>
          ${(q.alternativas || []).map((a, i) => `<label class="runner-option ${selected === String(a.id) ? "selected" : ""}"><input type="radio" name="attempt-answer" value="${escapeHtml(a.id)}" ${selected === String(a.id) ? "checked" : ""}><span class="alternative-letter">${String.fromCharCode(65 + i)}</span><span class="runner-option-text">${rich(a.texto, true)}</span></label>`).join("")}
        </fieldset>` : "";
    const textHtml = type === "discursiva" ? `
        <label class="form-field runner-text"><span>Sua resposta</span><textarea id="answer-text" rows="9" maxlength="20000" placeholder="Escreva sua resposta aqui">${escapeHtml(q.resposta_texto || "")}</textarea><small>Será corrigida por um administrador.</small></label>`
      : (type === "objetiva_justificativa" ? `
        <label class="form-field runner-text"><span>Justifique sua resposta</span><textarea id="answer-text" rows="6" maxlength="20000" placeholder="Explique por que escolheu esta alternativa">${escapeHtml(q.justificativa || "")}</textarea><small>A justificativa será corrigida por um administrador.</small></label>` : "");
    const imagesHtml = type === "objetiva" ? "" : `<div class="image-manager-slot answer-images" data-answer-images="${escapeHtml(q.id_resposta ?? "")}"></div>`;
    const hasAnswer = () => (type === "discursiva" ? Boolean((q.resposta_texto || "").trim())
      : (hasAlternatives(type) && q.alternativa_selecionada != null) || Boolean((q.justificativa || "").trim()));

    $("#tentativa-conteudo").innerHTML = `
      <div class="question-runner-head"><span>QUESTÃO ${currentQuestionIndex + 1} DE ${questions.length} ${typeBadge(type)}</span><span>${fmtPoints(q.pontos || 1)} ponto(s)</span></div>
      <article class="question-runner-card"><div class="question-runner-title">${rich(q.enunciado)}</div>
        <div class="image-manager-slot" data-question-images="${escapeHtml(q.id)}"></div>
        ${optionsHtml}${textHtml}${imagesHtml}
        <p class="answer-save-state" id="answer-save-state" aria-live="polite">${hasAnswer() ? "Resposta salva." : "Nenhuma resposta registrada."}</p>
      </article>
      <div class="runner-controls"><button type="button" class="button button-secondary" id="questao-anterior" ${currentQuestionIndex === 0 ? "disabled" : ""}>← Anterior</button><button type="button" class="button button-quiet" id="limpar-resposta">Limpar resposta</button><span class="runner-spacer"></span>${currentQuestionIndex < questions.length - 1 ? `<button type="button" class="button button-primary" id="questao-proxima">Próxima →</button>` : `<button type="button" class="button button-primary" id="finalizar-tentativa">Finalizar simulado</button>`}</div>`;
    enhanceRich($("#tentativa-conteudo"));
    const imagesSlot = $("[data-question-images]", $("#tentativa-conteudo"));
    if (imagesSlot) DogsApp.setupImageManager(imagesSlot, "questao", Number(q.id), { canUpload: false, canDelete: false, title: "Imagens da questão" });

    const answerImagesSlot = $("[data-answer-images]", $("#tentativa-conteudo"));
    if (answerImagesSlot) {
      DogsApp.setupImageManager(answerImagesSlot, "resposta", q.id_resposta ?? null, {
        canUpload: true,
        canDelete: true,
        startOpen: true,
        maxImages: 5,
        countInTitle: true,
        title: "Fotos da resposta",
        uploadLabel: "Anexar fotos",
        hint: "Anexe fotos da sua resolução (caderno, folha ou quadro). JPEG, PNG, WebP ou GIF; até 5 MB cada e no máximo 5 fotos. Elas ficam travadas ao finalizar e o corretor as vê na correção.",
        // A foto pertence à resposta: salva a resposta atual (cria a linha, se preciso) e devolve o id dela.
        ensureResourceId: async () => {
          window.clearTimeout(textSaveTimer);
          await answerSaveChain.catch(() => {});
          await saveAnswer(q);
          if (!q.id_resposta) throw new Error("Não foi possível salvar a resposta para anexar a foto.");
          return q.id_resposta;
        },
      });
    }

    $$("input[name='attempt-answer']", $("#tentativa-conteudo")).forEach(input => input.addEventListener("change", () => {
      q.alternativa_selecionada = Number(input.value);
      $$(".runner-option", $("#tentativa-conteudo")).forEach(label => label.classList.toggle("selected", $("input", label).checked));
      queueSave(q);
    }));
    $("#answer-text")?.addEventListener("input", event => {
      if (type === "discursiva") q.resposta_texto = event.target.value;
      else q.justificativa = event.target.value;
      const status = $("#answer-save-state");
      if (status) status.textContent = "Salvando…";
      // Salva automaticamente depois de uma breve pausa na digitação.
      window.clearTimeout(textSaveTimer);
      textSaveTimer = window.setTimeout(() => queueSave(q), 800);
    });
    $("#questao-anterior")?.addEventListener("click", () => goToQuestion(-1));
    $("#questao-proxima")?.addEventListener("click", () => goToQuestion(1));
    $("#limpar-resposta")?.addEventListener("click", async () => {
      window.clearTimeout(textSaveTimer);
      q.alternativa_selecionada = null;
      q.resposta_texto = null;
      q.justificativa = null;
      await answerSaveChain.catch(() => {});
      await saveAnswer(q);
      renderCurrentQuestion();
    });
    $("#finalizar-tentativa")?.addEventListener("click", () => finalizeAttempt(false));
  }

  /** Salva a resposta atual e navega. Se o salvamento falhar, mostra o motivo e deixa o aluno decidir. */
  async function goToQuestion(step) {
    try {
      await persistCurrentAnswer();
    } catch (error) {
      const proceed = confirm(`Não foi possível salvar a resposta desta questão:\n${error.message}\n\nDeseja mudar de questão mesmo assim? A resposta continuará na tela e será salva quando possível.`);
      if (!proceed) return;
    }
    currentQuestionIndex += step;
    renderCurrentQuestion();
  }

  /** Enfileira o salvamento para que as respostas cheguem ao servidor na ordem em que foram dadas. */
  function queueSave(question) {
    answerSaveChain = answerSaveChain.catch(() => {}).then(() => saveAnswer(question)).catch(() => {});
  }

  /** Monta o corpo da requisição conforme o tipo da questão. */
  function answerBody(question) {
    const type = question.tipo || "objetiva";
    const body = { id_tentativa: Number(currentAttempt.id), id_questao: Number(question.id) };
    if (hasAlternatives(type)) body.id_alternativa = question.alternativa_selecionada == null ? null : Number(question.alternativa_selecionada);
    if (type === "discursiva") body.resposta_texto = (question.resposta_texto || "").trim() ? question.resposta_texto : null;
    if (type === "objetiva_justificativa") body.justificativa = (question.justificativa || "").trim() ? question.justificativa : null;
    return body;
  }

  async function saveAnswer(question) {
    const status = $("#answer-save-state");
    if (status) status.textContent = "Salvando resposta…";
    const body = answerBody(question);
    try {
      const saved = await DogsAPI.request("/api/simulados/tentativas/responder", { method: "POST", body });
      if (saved?.id_resposta) question.id_resposta = Number(saved.id_resposta);
      const visible = currentAttempt?.questions[currentQuestionIndex]?.id === Number(question.id);
      const empty = (question.tipo === "discursiva" ? body.resposta_texto === null
        : body.id_alternativa === null && (question.tipo !== "objetiva_justificativa" || body.justificativa === null));
      if (status && visible) status.textContent = empty ? "Resposta removida." : "Resposta salva.";
    } catch (error) {
      if (status) status.textContent = error.message;
      throw error;
    }
  }

  async function persistCurrentAnswer() {
    window.clearTimeout(textSaveTimer);
    await answerSaveChain;
    const question = currentAttempt?.questions[currentQuestionIndex];
    if (question) await saveAnswer(question);
  }

  async function finalizeAttempt(expired) {
    if (!currentAttempt || autoFinishing && !expired) return;
    if (!expired && !confirm("Finalizar agora? Você não poderá alterar as respostas desta tentativa depois de enviá-la.")) return;
    const finishButton = $("#finalizar-tentativa");
    if (finishButton) finishButton.disabled = true;
    try {
      await answerSaveChain;
      await persistCurrentAnswer();
      const id = currentAttempt.id;
      await DogsAPI.request("/api/simulados/tentativas/finalizar", { method: "POST", body: { id_tentativa: Number(id) } });
      stopTimer();
      sessionStorage.removeItem(`dogs-attempt-start-${id}`);
      attemptPanel.hidden = true;
      await showResult(id);
      await Promise.all([loadHistory(), loadSimulados()]);
      currentAttempt = null;
    } catch (error) {
      if (finishButton) finishButton.disabled = false;
      message($("#tentativa-msg"), error.message, "error");
      if (expired) {
        try { await showResult(currentAttempt.id); } catch { /* mantém mensagem de erro */ }
      }
    } finally {
      autoFinishing = false;
    }
  }

  function resultQuestionHtml(q, index) {
    const pending = q.situacao_correcao === "pendente";
    let cls;
    let chip;
    if (q.tipo === "objetiva") {
      cls = q.correta ? "is-correct" : "is-wrong";
      chip = q.correta ? "✓ Correta" : "× Incorreta";
    } else if (pending) {
      cls = "is-pending";
      chip = "◷ Aguardando correção";
    } else {
      const got = Number(q.pontos_obtidos || 0);
      const max = Number(q.pontos || 0);
      cls = got >= max ? "is-correct" : (got <= 0 ? "is-wrong" : "is-partial");
      chip = `${fmtPoints(got)} / ${fmtPoints(max)} pts`;
    }

    const parts = [];
    if (q.tipo !== "discursiva") {
      parts.push(`<p>Sua alternativa: <strong>${q.alternativa_selecionada ? rich(q.alternativa_selecionada, true) : "Não respondida"}</strong>${q.tipo === "objetiva_justificativa" && q.alternativa_selecionada ? ` <span class="mini-chip ${q.acertou_alternativa ? "ok" : "no"}">${q.acertou_alternativa ? "alternativa correta" : "alternativa incorreta"}</span>` : ""}</p>`);
      parts.push(`<p>Alternativa correta: <strong>${q.alternativa_correta ? rich(q.alternativa_correta, true) : "Não definida"}</strong></p>`);
    }
    const written = q.tipo === "discursiva" ? q.resposta_texto : q.justificativa;
    if (q.tipo !== "objetiva") {
      parts.push(`<div class="answer-block"><span>${q.tipo === "discursiva" ? "Sua resposta" : "Sua justificativa"}</span>${written ? `<p>${multiline(written)}</p>` : '<p class="muted">Não respondida.</p>'}</div>`);
      if (q.id_resposta && Number(q.quantidade_imagens) > 0) {
        parts.push(`<div class="image-manager-slot answer-images" data-answer-images="${escapeHtml(q.id_resposta)}"></div>`);
      }
      if (q.situacao_correcao === "corrigida") {
        parts.push(`<p>Pontuação atribuída: <strong>${fmtPoints(q.pontos_obtidos)} de ${fmtPoints(q.pontos)}</strong></p>`);
        if (q.comentario_correcao) parts.push(`<div class="answer-block feedback"><span>Comentário do corretor</span><p>${multiline(q.comentario_correcao)}</p></div>`);
      } else if (pending) {
        parts.push('<p class="muted">Um administrador ainda vai corrigir esta resposta.</p>');
      } else {
        parts.push('<p class="muted">Questão em branco: pontuação zero.</p>');
      }
    }
    return `<article class="result-question ${cls}"><div class="result-question-top"><span>Questão ${index + 1} ${typeBadge(q.tipo)}</span><strong>${escapeHtml(chip)}</strong></div><div class="result-question-title">${rich(q.enunciado)}</div>${parts.join("")}${q.explicacao ? `<div class="answer-block explanation"><span>Explicação</span>${rich(q.explicacao)}</div>` : ""}<div class="image-manager-slot" data-question-images="${escapeHtml(q.id)}"></div></article>`;
  }

  async function showResult(attemptId) {
    const data = await DogsAPI.request(`/api/simulados/tentativas/resultado?id_tentativa=${encodeURIComponent(attemptId)}`);
    const attempt = data.tentativa || {};
    const questions = data.questoes || [];
    resultPanel.hidden = false;
    attemptPanel.hidden = true;
    if (attempt.status === "em_andamento") {
      $("#resultado-conteudo").innerHTML = '<p class="empty-state">Esta tentativa ainda não foi finalizada; o gabarito ficará disponível após a conclusão.</p>';
    } else {
      const waiting = attempt.status === "aguardando_correcao";
      const note = Number(attempt.nota ?? attempt.percentual ?? 0);
      const objective = questions.filter(q => q.tipo === "objetiva");
      const hits = objective.filter(q => q.correta).length;
      const pendingCount = Number(attempt.pendentes || 0);
      const details = [`${fmtPoints(attempt.pontos_obtidos)} de ${fmtPoints(attempt.pontos_totais)} pontos${waiting ? " confirmados até agora" : ""}`];
      if (objective.length) details.push(`${hits} de ${objective.length} acertos nas objetivas`);
      const ranking = user.isAdmin
        ? "Administradores não participam do ranking."
        : (waiting ? "Sua nota final e sua posição no ranking serão atualizadas quando a correção terminar." : 'Esta nota já conta para o <a class="text-link" href="ranking.php">ranking</a>.');
      $("#resultado-conteudo").innerHTML = `
        <div class="score-summary"><div class="score-ring ${waiting ? "pending" : ""}"><strong>${waiting ? "—" : `${note.toFixed(1)}<small>%</small>`}</strong></div><div><span class="eyebrow">${waiting ? "NOTA EM APURAÇÃO" : "NOTA FINAL"}</span><h3>${escapeHtml(attempt.simulado_titulo || "Simulado concluído")}</h3><p>${escapeHtml(details.join(" · "))}</p>${waiting ? `<p><span class="mini-chip wait">${pendingCount} questão(ões) aguardando correção</span></p>` : ""}<p class="muted">${ranking}</p><span class="muted">Finalizado em ${escapeHtml(DogsApp.formatDate(attempt.finalizada_em))}</span></div></div>
        <div class="result-questions">${questions.map(resultQuestionHtml).join("")}</div>`;
      enhanceRich($("#resultado-conteudo"));
      $("#resultado-conteudo").querySelectorAll("[data-answer-images]").forEach(slot => DogsApp.setupImageManager(slot, "resposta", Number(slot.dataset.answerImages), { canUpload: false, canDelete: false, startOpen: true, countInTitle: true, title: "Suas fotos da resposta" }));
      $("#resultado-conteudo").querySelectorAll("[data-question-images]").forEach(slot => DogsApp.setupImageManager(slot, "questao", Number(slot.dataset.questionImages), { canUpload: false, canDelete: false, title: "Imagem da questão" }));
    }
    resultPanel.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function openEditorIfAdmin() {
    if (user.isAdmin) openEditor();
  }

  if (user.isAdmin) { $("#novo-simulado").hidden = false; $("#importar-simulado").hidden = false; }
  else $("#simulados-admin-note").hidden = false;

  $("#novo-simulado").addEventListener("click", openEditorIfAdmin);
  $("#questao-tipo").addEventListener("change", applyQuestionType);
  $("#fechar-editor").addEventListener("click", () => { editorPanel.hidden = true; });
  $("#simulado-cancelar").addEventListener("click", () => { editorPanel.hidden = true; resetSimuladoForm(); });
  $("#simulado-materia").addEventListener("change", () => filterDatesForSelectedSubject());
  $("#simulado-form").addEventListener("submit", saveSimulado);
  $("#questao-form").addEventListener("submit", saveQuestion);
  $("#questao-cancelar").addEventListener("click", resetQuestionForm);
  $("#adicionar-alternativa").addEventListener("click", () => {
    const values = $$("[data-alt-row]", $("#alternativas-editor")).map(row => ({
      texto: $("[data-alt-text]", row).value,
      correta: $("input[name='correct-answer']", row).checked,
    }));
    if (values.length >= 10) return message($("#questao-form-msg"), "O limite é de dez alternativas por questão.", "error");
    values.push({ texto: "", correta: false });
    renderAlternativeBuilder(values);
  });
  $("#alternativas-editor").addEventListener("click", event => {
    const button = event.target.closest("[data-alt-remove]");
    if (!button) return;
    const rows = $$("[data-alt-row]", $("#alternativas-editor"));
    if (rows.length <= 2) return;
    const values = rows.filter(row => row !== button.closest("[data-alt-row]")).map(row => ({
      texto: $("[data-alt-text]", row).value,
      correta: $("input[name='correct-answer']", row).checked,
    }));
    if (!values.some(item => item.correta)) values[0].correta = true;
    renderAlternativeBuilder(values);
  });
  $("#simulado-publicar").addEventListener("click", () => editingSimuladoId && publishSimulado(editingSimuladoId));
  $("#filtro-simulado-materia").addEventListener("change", loadSimulados);
  $("#atualizar-historico").addEventListener("click", loadHistory);
  $("#fechar-resultado").addEventListener("click", () => { resultPanel.hidden = true; });

  listEl.addEventListener("click", async event => {
    const button = event.target.closest("[data-sim-action]");
    if (!button) return;
    const { simAction, id } = button.dataset;
    button.disabled = true;
    try {
      if (simAction === "details") await showDetails(id);
      if (simAction === "edit") await editSimulado(id);
      if (simAction === "publish") await publishSimulado(id);
      if (simAction === "delete") await deleteSimulado(id);
      if (simAction === "start") await startAttempt(id);
    } finally { button.disabled = false; }
  });
  $("#lista-questoes-editor").addEventListener("click", async event => {
    const button = event.target.closest("[data-question-action]");
    if (!button) return;
    if (button.dataset.questionAction === "edit") await editQuestion(button.dataset.id);
    if (button.dataset.questionAction === "delete") await deleteQuestion(button.dataset.id);
  });
  historyEl.addEventListener("click", async event => {
    const button = event.target.closest("[data-history-action]");
    if (!button) return;
    button.disabled = true;
    try {
      if (button.dataset.historyAction === "result") await showResult(button.dataset.id);
      if (button.dataset.historyAction === "resume") await openAttempt(Number(button.dataset.id));
    } catch (error) { alert(error.message); }
    finally { button.disabled = false; }
  });

  // Disparado por importacao.js depois de gravar o rascunho: atualiza a lista e abre o rascunho no editor.
  document.addEventListener("simulados:atualizar", async event => {
    await loadSimulados();
    if (event.detail?.editar) {
      await editSimulado(event.detail.editar);
      if (event.detail.aviso) message($("#simulado-form-msg"), event.detail.aviso, "success");
    }
  });

  renderAlternativeBuilder();
  loadInitialData().catch(error => {
    listEl.innerHTML = `<p class="notice error">${escapeHtml(error.message)}</p>`;
    historyEl.innerHTML = "";
  });
}
