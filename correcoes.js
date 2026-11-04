if (DogsApp.initPage("correcoes")) {
  const { $, $$, escapeHtml, message } = DogsApp;
  const user = DogsAPI.userInfo();

  if (!user.isAdmin) {
    // A fila de correção é exclusiva de administradores (o servidor também bloqueia).
    window.location.replace("dashboard.php");
  } else {
    const listPanel = $("#correcao-lista-panel");
    const detailPanel = $("#correcao-detalhe-panel");
    const listEl = $("#correcao-lista");
    const summaryEl = $("#correcao-detalhe-resumo");
    const questionsEl = $("#correcao-detalhe-questoes");
    const TYPE_LABELS = { discursiva: "Discursiva", objetiva_justificativa: "Objetiva + justificativa" };
    const fmt = value => Number(value ?? 0).toLocaleString("pt-BR", { maximumFractionDigits: 2 });
    const multiline = text => escapeHtml(text).replace(/\n/g, "<br>");
    const typeBadge = type => `<span class="type-badge type-${escapeHtml(type)}">${escapeHtml(TYPE_LABELS[type] || type)}</span>`;
    let status = "aguardando_correcao";
    let current = null; // { tentativa, aluno }
    let listRequest = 0;

    async function loadList() {
      const request = ++listRequest;
      listEl.innerHTML = '<p class="muted">Carregando…</p>';
      try {
        const data = await DogsAPI.request(`/api/simulados/correcoes?status=${encodeURIComponent(status)}`);
        if (request !== listRequest) return;
        const items = data.correcoes || [];
        if (!items.length) {
          listEl.innerHTML = `<p class="empty-state">${status === "aguardando_correcao" ? "Nenhuma resposta aguardando correção. Tudo em dia!" : "Nenhuma tentativa corrigida ainda."}</p>`;
          return;
        }
        listEl.innerHTML = items.map(item => `
          <article class="correction-item">
            <div class="correction-item-main">
              <h3>${escapeHtml(item.simulado)}</h3>
              <p>${escapeHtml(item.aluno)} <span class="muted">@${escapeHtml(item.usuario)}</span> · ${escapeHtml(item.materia)} · enviado em ${escapeHtml(DogsApp.formatDate(item.finalizada_em))}</p>
            </div>
            ${item.status === "aguardando_correcao"
              ? `<span class="mini-chip wait">${item.pendentes} pendente(s)</span>`
              : `<span class="mini-chip ok">Nota ${fmt(item.nota)}%</span>`}
            <button type="button" class="button ${item.status === "aguardando_correcao" ? "button-primary" : "button-secondary"}" data-open-correction="${escapeHtml(item.id_tentativa)}">${item.status === "aguardando_correcao" ? "Corrigir" : "Revisar"}</button>
          </article>`).join("");
      } catch (error) {
        if (request === listRequest) listEl.innerHTML = `<p class="notice error">${escapeHtml(error.message)}</p>`;
      }
    }

    function renderSummary(banner = "") {
      const t = current.tentativa;
      const aluno = current.aluno || {};
      const waiting = t.status === "aguardando_correcao";
      $("#correcao-detalhe-titulo").textContent = t.simulado_titulo || "Corrigir respostas";
      summaryEl.innerHTML = `
        <div class="correction-summary">
          <div><span class="eyebrow">Estudante</span><strong>${escapeHtml(aluno.nome || "—")}</strong><small>@${escapeHtml(aluno.usuario || "")}</small></div>
          <div><span class="eyebrow">Situação</span><strong>${waiting ? `${Number(t.pendentes || 0)} pendente(s)` : `Concluída · ${fmt(t.nota)}%`}</strong><small>${fmt(t.pontos_obtidos)} de ${fmt(t.pontos_totais)} pontos${waiting ? " confirmados" : ""}</small></div>
          <div><span class="eyebrow">Enviada em</span><strong>${escapeHtml(DogsApp.formatDate(t.finalizada_em))}</strong></div>
        </div>
        ${aluno.isadmin ? '<p class="notice info">Esta tentativa é de um administrador e não entra no ranking.</p>' : ""}
        ${banner ? `<p class="notice success" role="status">${escapeHtml(banner)}</p>` : ""}`;
    }

    function questionHtml(q, index) {
      const written = q.tipo === "discursiva" ? q.resposta_texto : q.justificativa;
      const blank = q.situacao_correcao === "automatica";
      const corrected = q.situacao_correcao === "corrigida";
      const alternative = q.tipo === "objetiva_justificativa" ? `
        <p>Alternativa do aluno: <strong>${escapeHtml(q.alternativa_selecionada || "Não respondida")}</strong>${q.alternativa_selecionada ? ` <span class="mini-chip ${q.acertou_alternativa ? "ok" : "no"}">${q.acertou_alternativa ? "correta" : "incorreta"}</span>` : ""}</p>
        <p>Alternativa correta: <strong>${escapeHtml(q.alternativa_correta || "Não definida")}</strong></p>` : "";
      const form = blank
        ? '<p class="muted">Resposta em branco: a questão recebeu nota zero automaticamente.</p>'
        : `<form class="correction-form" data-correction-form="${escapeHtml(q.id)}" data-max="${escapeHtml(q.pontos)}">
            <label class="form-field"><span>Pontos (de 0 a ${fmt(q.pontos)})</span><input type="number" name="pontos" min="0" max="${escapeHtml(q.pontos)}" step="0.01" inputmode="decimal" required value="${corrected ? escapeHtml(q.pontos_obtidos) : ""}"></label>
            <div class="quick-points" aria-label="Atalhos de pontuação"><button type="button" class="button button-quiet button-small" data-quick="0">Zero</button><button type="button" class="button button-quiet button-small" data-quick="half">Metade</button><button type="button" class="button button-quiet button-small" data-quick="full">Total</button></div>
            <label class="form-field full-width"><span>Comentário para o aluno (opcional)</span><textarea name="comentario" rows="3" maxlength="5000" placeholder="Explique a nota ou aponte melhorias">${escapeHtml(q.comentario_correcao || "")}</textarea></label>
            <div class="form-buttons full-width"><button class="button button-primary" type="submit">${corrected ? "Atualizar correção" : "Salvar correção"}</button></div>
            <p class="notice" data-correction-msg hidden aria-live="polite"></p>
          </form>`;
      return `<article class="correction-card" data-question-card="${escapeHtml(q.id)}">
        <div class="result-question-top"><span>Questão ${index + 1} ${typeBadge(q.tipo)}</span><span class="mini-chip ${corrected ? "ok" : (blank ? "no" : "wait")}" data-state-chip>${corrected ? `Corrigida · ${fmt(q.pontos_obtidos)} / ${fmt(q.pontos)}` : (blank ? "Em branco · 0" : "Pendente")}</span></div>
        <h4>${escapeHtml(q.enunciado)}</h4>
        <div class="image-manager-slot" data-question-images="${escapeHtml(q.id)}"></div>
        ${alternative}
        <div class="answer-block"><span>${q.tipo === "discursiva" ? "Resposta do aluno" : "Justificativa do aluno"}</span>${written ? `<p>${multiline(written)}</p>` : '<p class="muted">Não respondida.</p>'}</div>
        ${q.id_resposta && Number(q.quantidade_imagens) > 0
          ? `<div class="image-manager-slot answer-images" data-answer-images="${escapeHtml(q.id_resposta)}"></div>`
          : '<p class="muted answer-no-photos">O aluno não anexou fotos a esta resposta.</p>'}
        ${form}
      </article>`;
    }

    async function openCorrection(id) {
      const data = await DogsAPI.request(`/api/simulados/correcoes/tentativa?id_tentativa=${encodeURIComponent(id)}`);
      current = { tentativa: data.tentativa || {}, aluno: data.aluno || null };
      const questions = data.questoes || [];
      renderSummary();
      questionsEl.innerHTML = questions.length ? questions.map(questionHtml).join("") : '<p class="empty-state">Esta tentativa não possui questões de correção manual.</p>';
      $$("[data-answer-images]", questionsEl).forEach(slot => DogsApp.setupImageManager(slot, "resposta", Number(slot.dataset.answerImages), { canUpload: false, canDelete: false, startOpen: true, countInTitle: true, title: "Fotos da resposta do aluno", hint: "Clique em uma foto para ampliar, dar zoom e navegar entre elas." }));
      $$("[data-question-images]", questionsEl).forEach(slot => DogsApp.setupImageManager(slot, "questao", Number(slot.dataset.questionImages), { canUpload: false, canDelete: false, title: "Imagens da questão" }));
      listPanel.hidden = true;
      detailPanel.hidden = false;
      detailPanel.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    async function submitCorrection(form) {
      const card = form.closest("[data-question-card]");
      const msg = $("[data-correction-msg]", form);
      const input = $("input[name='pontos']", form);
      const max = Number(form.dataset.max);
      const points = input.valueAsNumber;
      if (!Number.isFinite(points) || points < 0 || points > max) {
        return message(msg, `Informe uma pontuação entre 0 e ${fmt(max)}.`, "error");
      }
      const button = $("button[type='submit']", form);
      button.disabled = true;
      try {
        const result = await DogsAPI.request("/api/simulados/correcoes/corrigir", {
          method: "POST",
          body: {
            id_tentativa: Number(current.tentativa.id),
            id_questao: Number(card.dataset.questionCard),
            pontos_obtidos: points,
            comentario: $("textarea[name='comentario']", form).value.trim() || null,
          },
        });
        const summary = result.resultado || {};
        Object.assign(current.tentativa, {
          status: summary.status, nota: summary.nota, pendentes: summary.pendentes,
          pontos_obtidos: summary.pontos_obtidos, pontos_totais: summary.pontos_totais,
        });
        const chip = $("[data-state-chip]", card);
        chip.className = "mini-chip ok";
        chip.textContent = `Corrigida · ${fmt(points)} / ${fmt(max)}`;
        button.textContent = "Atualizar correção";
        message(msg, "Correção salva.", "success");
        const done = summary.status === "concluida";
        const ranked = !(current.aluno && current.aluno.isadmin);
        renderSummary(done ? `Todas as respostas foram corrigidas. Nota final: ${fmt(summary.nota)}%${ranked ? " — a tentativa já conta no ranking." : "."}` : "");
      } catch (error) {
        message(msg, error.message, "error");
      } finally {
        button.disabled = false;
      }
    }

    $$(".segmented-option").forEach(option => option.addEventListener("click", () => {
      status = option.dataset.status;
      $$(".segmented-option").forEach(other => {
        other.classList.toggle("active", other === option);
        other.setAttribute("aria-selected", other === option ? "true" : "false");
      });
      $("#correcao-lista-titulo").textContent = status === "aguardando_correcao" ? "Aguardando correção" : "Tentativas já corrigidas";
      loadList();
    }));
    listEl.addEventListener("click", async event => {
      const button = event.target.closest("[data-open-correction]");
      if (!button) return;
      button.disabled = true;
      try { await openCorrection(button.dataset.openCorrection); }
      catch (error) { alert(error.message); }
      finally { button.disabled = false; }
    });
    questionsEl.addEventListener("submit", event => {
      const form = event.target.closest("[data-correction-form]");
      if (!form) return;
      event.preventDefault();
      submitCorrection(form);
    });
    questionsEl.addEventListener("click", event => {
      const button = event.target.closest("[data-quick]");
      if (!button) return;
      const form = button.closest("[data-correction-form]");
      const max = Number(form.dataset.max);
      const value = { 0: 0, half: Math.round(max * 50) / 100, full: max }[button.dataset.quick];
      $("input[name='pontos']", form).value = String(value);
    });
    $("#correcao-voltar").addEventListener("click", () => {
      detailPanel.hidden = true;
      listPanel.hidden = false;
      current = null;
      loadList();
    });

    loadList();
  }
}
