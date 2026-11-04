/*
 * Importação de simulados a partir de arquivos Markdown (.md/.txt) — somente administradores.
 *
 * Fluxo:
 *  1. O arquivo é enviado para POST /api/simulados/importar, que valida e devolve um rascunho.
 *     Nada é gravado nesta etapa.
 *  2. O administrador revisa e corrige o rascunho nesta tela (editar, remover questões, escolher a matéria).
 *  3. "Confirmar" envia o rascunho revisado para POST /api/simulados/importar/confirmar, que valida tudo de
 *     novo no servidor e grava um simulado em rascunho (transação única). A publicação continua no fluxo atual.
 *
 * Segurança: todo texto vindo do arquivo ou do servidor é escapado antes de entrar no HTML; as
 * pré-visualizações usam DogsRich (escapa antes de formatar; sem HTML cru, sem eval).
 */
(() => {
  const panel = document.getElementById("importacao-panel");
  if (!panel || !window.DogsAPI || !window.DogsApp || !DogsAPI.userInfo().isAdmin) return;

  const { $, $$, escapeHtml, message } = DogsApp;
  const MAX_BYTES = 1048576;
  const MAX_QUESTOES = 200;
  const MIN_ALT = 2;
  const MAX_ALT = 10;

  const TYPE_LABELS = { objetiva: "Objetiva", discursiva: "Discursiva", objetiva_justificativa: "Objetiva + justificativa" };
  const letter = index => String.fromCharCode(65 + (index % 26));
  const hasAlternatives = type => type !== "discursiva";
  const fmt = value => Number(value ?? 0).toLocaleString("pt-BR", { maximumFractionDigits: 2 });

  const uploadStep = $("#import-step-upload", panel);
  const reviewStep = $("#import-step-review", panel);
  const fileInput = $("#import-file", panel);
  const analyzeButton = $("#import-analisar", panel);
  const questionsEl = $("#import-questoes", panel);
  const confirmButton = $("#import-confirmar", panel);

  let draft = null;
  let materias = [];
  let materiasLoaded = false;
  let nextKey = 1;
  let busy = false;
  const previewTimers = new Map();

  // ------------------------------------------------------------------
  // Abrir / fechar
  // ------------------------------------------------------------------

  function openPanel() {
    panel.hidden = false;
    showUpload();
    panel.scrollIntoView({ behavior: "smooth", block: "start" });
    loadMaterias();
  }

  function closePanel(askBeforeDiscard = true) {
    if (askBeforeDiscard && draft && !window.confirm("Descartar a importação em andamento? Nada foi gravado ainda.")) return;
    discardDraft();
    panel.hidden = true;
  }

  function discardDraft() {
    draft = null;
    questionsEl.innerHTML = "";
    previewTimers.forEach(timer => window.clearTimeout(timer));
    previewTimers.clear();
    fileInput.value = "";
    message($("#import-upload-msg", panel), "");
    message($("#import-review-msg", panel), "");
    showUpload();
  }

  function showUpload() {
    uploadStep.hidden = false;
    reviewStep.hidden = true;
  }

  function showReview() {
    uploadStep.hidden = true;
    reviewStep.hidden = false;
  }

  async function loadMaterias() {
    if (materiasLoaded) return;
    try {
      const data = await DogsAPI.request("/api/materias");
      materias = data.materias || [];
      materiasLoaded = true;
      if (draft) fillMateriaSelect();
    } catch (error) {
      message($("#import-upload-msg", panel), error.message, "error");
    }
  }

  // ------------------------------------------------------------------
  // Etapa 1: enviar e analisar
  // ------------------------------------------------------------------

  function checkFileBeforeUpload(file) {
    if (!file) return "Escolha um arquivo .md ou .txt.";
    if (!/\.(md|txt)$/i.test(file.name)) return "Extensão não permitida. Envie um arquivo .md ou .txt.";
    if (file.size === 0) return "O arquivo está vazio.";
    if (file.size > MAX_BYTES) return "O arquivo excede o limite de 1 MB.";
    return "";
  }

  async function analyze(event) {
    event.preventDefault();
    if (busy) return;
    const msg = $("#import-upload-msg", panel);
    const file = fileInput.files && fileInput.files[0];
    const problem = checkFileBeforeUpload(file);
    if (problem) return message(msg, problem, "error");

    const body = new FormData();
    body.append("arquivo", file);
    busy = true;
    analyzeButton.disabled = true;
    analyzeButton.textContent = "Analisando…";
    message(msg, "");
    try {
      await loadMaterias();
      const data = await DogsAPI.request("/api/simulados/importar", { method: "POST", body });
      startDraft(data);
    } catch (error) {
      message(msg, error.message, "error");
    } finally {
      busy = false;
      analyzeButton.disabled = false;
      analyzeButton.textContent = "Analisar arquivo";
    }
  }

  function startDraft(data) {
    const sim = data.simulado || {};
    draft = {
      arquivo: data.arquivo || {},
      meta: {
        titulo: sim.titulo || "",
        id_materia: sim.id_materia ? String(sim.id_materia) : "",
        materia_informada: sim.materia_informada || "",
        materia_encontrada: Boolean(sim.materia_encontrada),
        duracao_minutos: sim.duracao_minutos == null ? "" : String(sim.duracao_minutos),
        descricao: sim.descricao || "",
      },
      avisosGerais: data.avisos_gerais || [],
      questoes: (data.questoes || []).map(toDraftQuestion),
    };
    renderReview();
    showReview();
    panel.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function toDraftQuestion(item) {
    const tipo = TYPE_LABELS[item.tipo] ? item.tipo : "objetiva";
    return {
      key: nextKey++,
      linha: item.linha || null,
      tipo,
      enunciado: String(item.enunciado || ""),
      pontos: item.pontos == null ? "" : String(item.pontos),
      explicacao: String(item.explicacao || ""),
      alternativas: (item.alternativas || []).map(alt => ({ texto: String(alt.texto || ""), correta: alt.correta === true })),
      avisos: Array.isArray(item.avisos) ? item.avisos.map(String) : [],
      serverErrors: [],
    };
  }

  // ------------------------------------------------------------------
  // Validação no cliente (espelha o servidor, que continua sendo a autoridade)
  // ------------------------------------------------------------------

  function parsePoints(value) {
    const raw = String(value ?? "").trim().replace(",", ".");
    if (raw === "") return NaN;
    return Number(raw);
  }

  function validateQuestion(q) {
    const errors = [];
    const statement = q.enunciado.trim();
    if (!statement) errors.push("Enunciado vazio.");
    else if (statement.length > 20000) errors.push("O enunciado excede 20.000 caracteres.");

    const points = parsePoints(q.pontos);
    if (!Number.isFinite(points) || points <= 0 || points > 100) {
      errors.push("Pontos inválidos: informe um valor maior que zero e no máximo 100.");
    }
    if (q.explicacao.trim().length > 20000) errors.push("A explicação excede 20.000 caracteres.");

    if (!hasAlternatives(q.tipo)) return errors;

    const count = q.alternativas.length;
    if (count === 0) errors.push("Sem alternativas: questões objetivas precisam de 2 a 10 alternativas.");
    else if (count < MIN_ALT) errors.push("Menos de 2 alternativas (mínimo: 2).");
    else if (count > MAX_ALT) errors.push("Mais de 10 alternativas (máximo: 10).");

    const correct = [];
    q.alternativas.forEach((alt, index) => {
      const text = alt.texto.trim();
      if (!text) errors.push(`Alternativa ${letter(index)} sem texto.`);
      else if (text.length > 4000) errors.push(`A alternativa ${letter(index)} excede 4.000 caracteres.`);
      if (alt.correta) correct.push(letter(index));
    });
    if (correct.length > 1) errors.push(`Mais de uma alternativa correta (${correct.join(", ")}); deve haver exatamente uma.`);
    else if (!correct.length && count > 0) errors.push("Gabarito ausente: marque a alternativa correta.");
    return errors;
  }

  function validateMeta() {
    const meta = draft.meta;
    const problems = [];
    const title = meta.titulo.trim();
    if (!title) problems.push("Informe o título do simulado.");
    else if (title.length > 150) problems.push("O título deve ter no máximo 150 caracteres.");
    if (!meta.id_materia) problems.push("Escolha a matéria do simulado.");
    if (meta.duracao_minutos !== "") {
      const minutes = Number(meta.duracao_minutos);
      if (!Number.isInteger(minutes) || minutes < 1 || minutes > 600) problems.push("A duração deve ser um número inteiro entre 1 e 600 minutos.");
    }
    if (meta.descricao.length > 10000) problems.push("A descrição excede 10.000 caracteres.");
    if (!draft.questoes.length) problems.push("Inclua pelo menos uma questão.");
    return problems;
  }

  // ------------------------------------------------------------------
  // Renderização da revisão
  // ------------------------------------------------------------------

  function fillMateriaSelect() {
    const select = $("#import-materia", panel);
    const options = ['<option value="">Selecione uma matéria</option>']
      .concat(materias.map(m => `<option value="${escapeHtml(m.id)}">${escapeHtml(m.nome_materia)}</option>`));
    select.innerHTML = options.join("");
    select.value = draft.meta.id_materia;
  }

  function renderReview() {
    const meta = draft.meta;
    $("#import-arquivo-info", panel).textContent = `${draft.arquivo.nome || "arquivo"} · ${Math.max(1, Math.round((draft.arquivo.tamanho || 0) / 1024))} KB`;
    $("#import-titulo", panel).value = meta.titulo;
    $("#import-duracao", panel).value = meta.duracao_minutos;
    $("#import-descricao", panel).value = meta.descricao;
    fillMateriaSelect();

    const subjectNote = $("#import-materia-aviso", panel);
    if (meta.materia_encontrada) {
      subjectNote.hidden = true;
    } else {
      subjectNote.hidden = false;
      subjectNote.textContent = meta.materia_informada
        ? `A matéria “${meta.materia_informada}” do arquivo não foi encontrada. Escolha uma matéria existente.`
        : "O arquivo não informa a matéria. Escolha uma matéria existente.";
    }

    const warnings = $("#import-avisos-gerais", panel);
    warnings.hidden = !draft.avisosGerais.length;
    warnings.innerHTML = draft.avisosGerais.length
      ? `<strong>Avisos</strong><ul>${draft.avisosGerais.map(text => `<li>${escapeHtml(text)}</li>`).join("")}</ul>`
      : "";

    renderQuestions();
    refreshSummary();
  }

  function renderQuestions() {
    questionsEl.innerHTML = draft.questoes.map((q, index) => questionHtml(q, index)).join("");
    $$("[data-iq]", questionsEl).forEach(card => refreshCard(card));
  }

  function questionHtml(q, index) {
    const withAlternatives = hasAlternatives(q.tipo);
    const typeOptions = Object.entries(TYPE_LABELS)
      .map(([value, label]) => `<option value="${value}" ${q.tipo === value ? "selected" : ""}>${escapeHtml(label)}</option>`).join("");
    return `<article class="import-question" data-iq="${q.key}">
      <header class="import-question-head">
        <div><span class="import-question-number" data-iq-number>Questão ${index + 1}</span>${q.linha ? `<span class="muted"> · linha ${escapeHtml(q.linha)} do arquivo</span>` : ""}</div>
        <button type="button" class="button button-danger button-small" data-iq-remove aria-label="Remover questão ${index + 1}">Remover questão</button>
      </header>
      <div class="import-question-feedback" data-iq-feedback aria-live="polite"></div>
      <div class="form-grid">
        <label class="form-field"><span>Tipo de questão</span><select data-iq-field="tipo">${typeOptions}</select></label>
        <label class="form-field"><span>Pontuação</span><input type="number" min="0.01" max="100" step="0.01" inputmode="decimal" data-iq-field="pontos" value="${escapeHtml(q.pontos)}"></label>
        <label class="form-field full-width"><span>Enunciado (aceita Markdown, código e LaTeX)</span><textarea rows="5" maxlength="20000" data-iq-field="enunciado">${escapeHtml(q.enunciado)}</textarea></label>
        ${withAlternatives ? alternativesHtml(q) : ""}
        <label class="form-field full-width"><span>Explicação (opcional)</span><textarea rows="3" maxlength="20000" data-iq-field="explicacao">${escapeHtml(q.explicacao)}</textarea><small>O aluno só vê a explicação depois de finalizar o simulado.</small></label>
      </div>
      <details class="import-preview" data-iq-preview><summary>Pré-visualização formatada</summary><div class="import-preview-body" data-iq-preview-body></div></details>
    </article>`;
  }

  function alternativesHtml(q) {
    const rows = q.alternativas.map((alt, index) => `
      <div class="import-alt-row" data-iq-alt="${index}">
        <input type="checkbox" class="import-alt-correct" data-iq-correct="${index}" ${alt.correta ? "checked" : ""} aria-label="Marcar a alternativa ${letter(index)} como correta" title="Alternativa correta">
        <span class="alternative-letter">${letter(index)}</span>
        <textarea rows="1" maxlength="4000" data-iq-alt-text="${index}" placeholder="Texto da alternativa ${letter(index)}">${escapeHtml(alt.texto)}</textarea>
        <button type="button" class="button button-quiet button-small" data-iq-alt-remove="${index}" aria-label="Remover alternativa ${letter(index)}">×</button>
      </div>`).join("");
    return `<fieldset class="form-field full-width alternatives-field" data-iq-alts>
      <span>Alternativas</span>
      <p class="muted small-help">Marque a caixa da alternativa correta (exatamente uma).</p>
      <div class="import-alt-list">${rows}</div>
      <button type="button" class="button button-secondary button-small" data-iq-alt-add ${q.alternativas.length >= MAX_ALT ? "disabled" : ""}>＋ Adicionar alternativa</button>
    </fieldset>`;
  }

  /** Atualiza erros/avisos do cartão (sem recriar os campos, para não perder o foco). */
  function refreshCard(card) {
    const q = findQuestion(card.dataset.iq);
    if (!q) return;
    const errors = validateQuestion(q).concat(q.serverErrors.filter(text => !validateQuestion(q).includes(text)));
    const feedback = $("[data-iq-feedback]", card);
    const parts = [];
    if (errors.length) parts.push(`<div class="import-errors" role="alert"><strong>Corrija antes de confirmar</strong><ul>${errors.map(text => `<li>${escapeHtml(text)}</li>`).join("")}</ul></div>`);
    if (q.avisos.length) parts.push(`<div class="import-warnings"><strong>Avisos da leitura do arquivo</strong><ul>${q.avisos.map(text => `<li>${escapeHtml(text)}</li>`).join("")}</ul></div>`);
    feedback.innerHTML = parts.join("");
    card.classList.toggle("has-errors", errors.length > 0);
    $$(".import-alt-correct", card).forEach(box => box.closest(".import-alt-row").classList.toggle("is-correct", box.checked));
    refreshSummary();
    schedulePreview(card);
  }

  function refreshSummary() {
    if (!draft) return;
    const withErrors = draft.questoes.filter(q => validateQuestion(q).length > 0 || q.serverErrors.length > 0).length;
    const problems = validateMeta();
    const total = draft.questoes.length;
    $("#import-resumo", panel).innerHTML = `<strong>${total}</strong> questão(ões) · <strong class="${withErrors ? "text-danger" : "text-ok"}">${withErrors}</strong> com erro`;
    confirmButton.disabled = busy || withErrors > 0 || problems.length > 0;
    const hint = $("#import-confirmar-ajuda", panel);
    hint.textContent = problems.length ? problems[0] : (withErrors ? "Corrija as questões marcadas em vermelho para continuar." : "Tudo certo. O simulado será criado como rascunho.");
  }

  // ------------------------------------------------------------------
  // Pré-visualização formatada (código e LaTeX), sob demanda
  // ------------------------------------------------------------------

  function schedulePreview(card) {
    const details = $("[data-iq-preview]", card);
    if (!details || !details.open) return;
    const key = card.dataset.iq;
    window.clearTimeout(previewTimers.get(key));
    previewTimers.set(key, window.setTimeout(() => renderPreview(card), 250));
  }

  function renderPreview(card) {
    const q = findQuestion(card.dataset.iq);
    const body = $("[data-iq-preview-body]", card);
    if (!q || !body) return;
    const rich = window.DogsRich;
    if (!rich) {
      body.innerHTML = `<p class="muted">O visualizador de Markdown não foi carregado.</p>`;
      return;
    }
    const alternatives = hasAlternatives(q.tipo)
      ? `<ol class="import-preview-alts" type="A">${q.alternativas.map(alt => `<li class="${alt.correta ? "is-correct" : ""}">${rich.render(alt.texto, { inline: true })}${alt.correta ? ' <span class="correct-answer-tag">Gabarito</span>' : ""}</li>`).join("")}</ol>`
      : '<p class="muted">Questão discursiva: o aluno escreve a resposta e um administrador a corrige.</p>';
    body.innerHTML = `<div class="import-preview-statement">${rich.render(q.enunciado)}</div>${alternatives}${q.explicacao.trim() ? `<div class="answer-block explanation"><span>Explicação</span>${rich.render(q.explicacao)}</div>` : ""}`;
    rich.enhance(body);
  }

  // ------------------------------------------------------------------
  // Edição
  // ------------------------------------------------------------------

  function findQuestion(key) {
    return draft ? draft.questoes.find(q => String(q.key) === String(key)) : null;
  }

  function clearServerErrors(q) {
    q.serverErrors = [];
  }

  function autoGrow(textarea) {
    textarea.style.height = "auto";
    textarea.style.height = `${Math.min(textarea.scrollHeight + 2, 260)}px`;
  }

  function rerenderCard(card) {
    const q = findQuestion(card.dataset.iq);
    const index = draft.questoes.indexOf(q);
    const wasOpen = $("[data-iq-preview]", card)?.open;
    const wrapper = document.createElement("div");
    wrapper.innerHTML = questionHtml(q, index);
    const fresh = wrapper.firstElementChild;
    card.replaceWith(fresh);
    if (wasOpen) $("[data-iq-preview]", fresh).open = true;
    $$("[data-iq-alt-text]", fresh).forEach(autoGrow);
    refreshCard(fresh);
    return fresh;
  }

  function renumber() {
    $$("[data-iq]", questionsEl).forEach((card, index) => {
      $("[data-iq-number]", card).textContent = `Questão ${index + 1}`;
      $("[data-iq-remove]", card).setAttribute("aria-label", `Remover questão ${index + 1}`);
    });
  }

  function onInput(event) {
    const card = event.target.closest("[data-iq]");
    if (!card) return;
    const q = findQuestion(card.dataset.iq);
    if (!q) return;
    const field = event.target.dataset.iqField;
    if (field && field !== "tipo") {
      q[field] = event.target.value;
    } else if (event.target.dataset.iqAltText !== undefined) {
      q.alternativas[Number(event.target.dataset.iqAltText)].texto = event.target.value;
      autoGrow(event.target);
    } else {
      return;
    }
    clearServerErrors(q);
    refreshCard(card);
  }

  function onChange(event) {
    const card = event.target.closest("[data-iq]");
    if (!card) return;
    const q = findQuestion(card.dataset.iq);
    if (!q) return;

    if (event.target.dataset.iqField === "tipo") {
      q.tipo = event.target.value;
      if (hasAlternatives(q.tipo) && !q.alternativas.length) {
        q.alternativas = [0, 1, 2, 3].map(() => ({ texto: "", correta: false }));
      }
      clearServerErrors(q);
      rerenderCard(card);
      return;
    }
    if (event.target.dataset.iqCorrect !== undefined) {
      const chosen = Number(event.target.dataset.iqCorrect);
      // Comportamento de "rádio": marcar uma desmarca as outras (desmarcar deixa o gabarito vazio).
      q.alternativas.forEach((alt, index) => { alt.correta = event.target.checked && index === chosen; });
      $$(".import-alt-correct", card).forEach((box, index) => { box.checked = q.alternativas[index].correta; });
      clearServerErrors(q);
      refreshCard(card);
    }
  }

  function onClick(event) {
    const card = event.target.closest("[data-iq]");
    if (!card) return;
    const q = findQuestion(card.dataset.iq);
    if (!q) return;

    if (event.target.closest("[data-iq-remove]")) {
      const position = draft.questoes.indexOf(q) + 1;
      if (!window.confirm(`Remover a questão ${position} da importação?`)) return;
      draft.questoes = draft.questoes.filter(item => item !== q);
      card.remove();
      renumber();
      refreshSummary();
      return;
    }
    if (event.target.closest("[data-iq-alt-add]")) {
      if (q.alternativas.length >= MAX_ALT) return;
      q.alternativas.push({ texto: "", correta: false });
      clearServerErrors(q);
      rerenderCard(card);
      return;
    }
    const removeAlt = event.target.closest("[data-iq-alt-remove]");
    if (removeAlt) {
      q.alternativas.splice(Number(removeAlt.dataset.iqAltRemove), 1);
      clearServerErrors(q);
      rerenderCard(card);
    }
  }

  function onToggle(event) {
    const details = event.target;
    if (!(details instanceof HTMLDetailsElement) || !details.matches("[data-iq-preview]") || !details.open) return;
    renderPreview(details.closest("[data-iq]"));
  }

  function onMetaInput(event) {
    if (!draft) return;
    const map = { "import-titulo": "titulo", "import-materia": "id_materia", "import-duracao": "duracao_minutos", "import-descricao": "descricao" };
    const field = map[event.target.id];
    if (!field) return;
    draft.meta[field] = event.target.value;
    if (field === "id_materia" && event.target.value) $("#import-materia-aviso", panel).hidden = true;
    refreshSummary();
  }

  // ------------------------------------------------------------------
  // Etapa 2: confirmar e gravar
  // ------------------------------------------------------------------

  function buildPayload() {
    const meta = draft.meta;
    return {
      titulo: meta.titulo.trim(),
      id_materia: Number(meta.id_materia),
      descricao: meta.descricao.trim() || null,
      duracao_minutos: meta.duracao_minutos === "" ? null : Number(meta.duracao_minutos),
      questoes: draft.questoes.map(q => ({
        tipo: q.tipo,
        enunciado: q.enunciado.trim(),
        pontos: parsePoints(q.pontos),
        explicacao: q.explicacao.trim() || null,
        // Discursiva não tem alternativas. O gabarito é sempre o que está marcado nesta tela.
        alternativas: hasAlternatives(q.tipo) ? q.alternativas.map(alt => ({ texto: alt.texto.trim(), correta: alt.correta === true })) : [],
      })),
    };
  }

  function applyServerDetails(details) {
    let first = null;
    (details || []).forEach(item => {
      const q = draft.questoes[Number(item.numero) - 1];
      if (!q) return;
      q.serverErrors = Array.isArray(item.erros) ? item.erros.map(String) : [];
      const card = questionsEl.querySelector(`[data-iq="${q.key}"]`);
      if (card) {
        refreshCard(card);
        first = first || card;
      }
    });
    if (first) first.scrollIntoView({ behavior: "smooth", block: "center" });
  }

  async function confirmImport() {
    if (busy || !draft) return;
    const msg = $("#import-review-msg", panel);
    const localProblems = validateMeta();
    if (localProblems.length) return message(msg, localProblems[0], "error");
    if (draft.questoes.some(q => validateQuestion(q).length)) return message(msg, "Corrija as questões marcadas em vermelho antes de confirmar.", "error");

    busy = true;
    confirmButton.disabled = true;
    confirmButton.textContent = "Gravando…";
    message(msg, "");
    try {
      const data = await DogsAPI.request("/api/simulados/importar/confirmar", { method: "POST", body: buildPayload() });
      const id = data.id;
      const total = data.total_questoes;
      busy = false;
      discardDraft();
      panel.hidden = true;
      document.dispatchEvent(new CustomEvent("simulados:atualizar", { detail: { editar: id, aviso: `Simulado importado como rascunho com ${total} questão(ões). Revise e publique quando quiser.` } }));
    } catch (error) {
      message(msg, error.message, "error");
      // Resposta 422 do servidor traz os erros por questão (a posição é a da lista enviada).
      if (error.details) applyServerDetails(error.details);
    } finally {
      busy = false;
      confirmButton.textContent = "Confirmar e criar rascunho";
      if (draft) refreshSummary();
    }
  }

  // ------------------------------------------------------------------
  // Eventos
  // ------------------------------------------------------------------

  $("#importar-simulado")?.addEventListener("click", openPanel);
  $("#import-fechar", panel).addEventListener("click", () => closePanel(true));
  $("#import-cancelar", panel).addEventListener("click", () => closePanel(true));
  $("#import-trocar-arquivo", panel).addEventListener("click", () => {
    if (draft && !window.confirm("Trocar de arquivo descarta a revisão atual. Continuar?")) return;
    discardDraft();
  });
  $("#import-form", panel).addEventListener("submit", analyze);
  confirmButton.addEventListener("click", confirmImport);
  questionsEl.addEventListener("input", onInput);
  questionsEl.addEventListener("change", onChange);
  questionsEl.addEventListener("click", onClick);
  questionsEl.addEventListener("toggle", onToggle, true);
  reviewStep.addEventListener("input", onMetaInput);
  reviewStep.addEventListener("change", onMetaInput);
  window.addEventListener("beforeunload", event => {
    if (draft) { event.preventDefault(); event.returnValue = ""; }
  });
})();
