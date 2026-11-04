<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <?php require_once __DIR__ . '/social-meta.php'; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#100b1f">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="style.css?v=20261103-fotos">
  <title>Dogs Team — Simulados</title>
</head>
<body class="app-page">
  <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
  <div id="app-nav"></div>
  <main id="conteudo" class="page-container simulator-page">
    <section class="page-title simulator-title-row">
      <div><span class="eyebrow">Prática e desempenho</span><h1>Simulados</h1><p>Pratique os conteúdos, acompanhe suas notas e prepare-se para as próximas provas.</p></div>
      <div class="title-actions"><button id="importar-simulado" class="button button-secondary" type="button" hidden>⇪ Importar de arquivo .md</button><button id="novo-simulado" class="button button-primary" type="button" hidden>＋ Criar simulado</button></div>
    </section>

    <section id="importacao-panel" class="panel import-panel" hidden aria-labelledby="import-titulo-painel">
      <div class="section-heading"><div><span class="eyebrow">Importação</span><h2 id="import-titulo-painel">Importar simulado de arquivo .md</h2></div><button id="import-fechar" class="button button-quiet" type="button">Fechar</button></div>

      <div id="import-step-upload">
        <p class="muted">Envie um arquivo Markdown pronto (<code class="inline-code">.md</code> ou <code class="inline-code">.txt</code>, UTF-8, até 1 MB e 200 questões). O arquivo é lido em memória e nada é gravado antes da sua confirmação.</p>
        <form id="import-form" class="import-upload-form">
          <label class="form-field"><span>Arquivo</span><input id="import-file" type="file" accept=".md,.txt,text/markdown,text/plain" required></label>
          <div class="form-buttons"><button id="import-analisar" class="button button-primary" type="submit">Analisar arquivo</button><a class="button button-secondary" href="modelos/modelo-simulado.md" download="modelo-simulado.md">Baixar modelo .md</a></div>
        </form>
        <p id="import-upload-msg" class="notice" hidden aria-live="polite"></p>
        <details class="import-format-help"><summary>Como escrever o arquivo</summary>
<pre class="rich-code"><code>---
materia: Matemática
assunto: Funções do 1º grau
titulo: Revisão de funções
duracao_minutos: 60
---

## Questão 1
Enunciado (aceita código e LaTeX, como $f(x) = 2x + 1$)

- a) texto
- b) texto

**Gabarito:** b
**Pontos:** 1
**Explicação:** texto</code></pre>
          <p class="muted small-help">Tipos: <strong>objetiva</strong> (padrão), <strong>discursiva</strong> (sem alternativas) e <strong>objetiva_justificativa</strong>. Informe-os com <code class="inline-code">**Tipo:** discursiva</code>. A matéria é associada pelo nome; se não for encontrada, você escolhe na revisão.</p>
        </details>
      </div>

      <div id="import-step-review" hidden>
        <div class="import-file-line"><span id="import-arquivo-info" class="muted"></span><button id="import-trocar-arquivo" class="button button-quiet button-small" type="button">Trocar arquivo</button></div>
        <div id="import-avisos-gerais" class="import-warnings" hidden></div>
        <div class="form-grid import-meta">
          <label class="form-field"><span>Título</span><input id="import-titulo" type="text" maxlength="150" placeholder="Título do simulado"></label>
          <label class="form-field"><span>Matéria</span><select id="import-materia"><option value="">Carregando matérias…</option></select><small id="import-materia-aviso" class="import-field-alert" hidden></small></label>
          <label class="form-field"><span>Duração em minutos (opcional)</span><input id="import-duracao" type="number" min="1" max="600" placeholder="Ex.: 60"></label>
          <label class="form-field full-width"><span>Descrição (opcional)</span><textarea id="import-descricao" rows="2" maxlength="10000"></textarea></label>
        </div>
        <div class="import-summary-bar"><span id="import-resumo"></span></div>
        <div id="import-questoes" class="import-question-list"></div>
        <div class="import-confirm-bar">
          <p id="import-confirmar-ajuda" class="muted"></p>
          <div class="form-buttons"><button id="import-confirmar" class="button button-primary" type="button" disabled>Confirmar e criar rascunho</button><button id="import-cancelar" class="button button-secondary" type="button">Cancelar importação</button></div>
        </div>
        <p id="import-review-msg" class="notice" hidden aria-live="polite"></p>
      </div>
    </section>

    <section id="simulado-editor-panel" class="panel simulator-editor" hidden>
      <div class="section-heading"><div><span class="eyebrow">Editor</span><h2 id="simulado-editor-title">Criar simulado</h2></div><button id="fechar-editor" class="button button-quiet" type="button">Fechar</button></div>
      <form id="simulado-form" class="form-grid">
        <input id="simulado-id" type="hidden">
        <label class="form-field"><span>Título</span><input id="simulado-titulo" type="text" maxlength="150" placeholder="Ex.: Funções exponenciais" required></label>
        <label class="form-field"><span>Matéria</span><select id="simulado-materia" required><option value="">Carregando matérias…</option></select></label>
        <label class="form-field"><span>Data importante / prova (opcional)</span><select id="simulado-data"><option value="">Sem data associada</option></select><small>Datas de outra matéria não serão disponibilizadas.</small></label>
        <label class="form-field"><span>Duração em minutos (opcional)</span><input id="simulado-duracao" type="number" min="1" max="600" placeholder="Ex.: 60"><small>O servidor controla a expiração da tentativa.</small></label>
        <label class="form-field full-width"><span>Descrição (opcional)</span><textarea id="simulado-descricao" rows="3" maxlength="10000" placeholder="Conteúdos, objetivos ou orientações"></textarea></label>
        <div class="form-buttons full-width"><button id="simulado-salvar" class="button button-primary" type="submit">Criar rascunho</button><button id="simulado-cancelar" class="button button-secondary" type="button">Cancelar</button></div>
      </form>
      <p id="simulado-form-msg" class="notice" hidden aria-live="polite"></p>

      <section id="questoes-editor" class="question-editor-section" hidden>
        <div class="section-heading"><div><span class="eyebrow">Conteúdo</span><h3>Questões do simulado</h3><p class="muted">Escolha o tipo de cada questão. Objetivas precisam de 2 a 10 alternativas e exatamente uma correta; discursivas e com justificativa são corrigidas manualmente em <a class="text-link" href="correcoes.php">Correções</a>.</p></div><button id="simulado-publicar" class="button button-primary" type="button">Publicar simulado</button></div>
        <div id="lista-questoes-editor" class="question-admin-list"><p class="muted">Carregando questões…</p></div>
        <div class="question-form-shell">
          <div class="section-heading"><div><span class="eyebrow">Nova questão</span><h3 id="questao-form-title">Adicionar questão</h3></div></div>
          <form id="questao-form" class="form-grid">
            <input id="questao-id" type="hidden">
            <label class="form-field full-width"><span>Enunciado</span><textarea id="questao-enunciado" rows="4" maxlength="20000" placeholder="Digite o enunciado da questão" required></textarea></label>
            <label class="form-field"><span>Tipo de questão</span><select id="questao-tipo"><option value="objetiva">Objetiva (1 resposta correta)</option><option value="discursiva">Discursiva</option><option value="objetiva_justificativa">Objetiva com justificativa</option></select><small id="questao-tipo-ajuda">Correção automática pelo gabarito.</small></label>
            <label class="form-field"><span>Pontuação</span><input id="questao-pontos" type="number" min="0.01" max="100" step="0.01" value="1" required></label>
            <fieldset id="alternativas-field" class="form-field full-width alternatives-field"><span>Alternativas</span><p class="muted small-help">Marque o círculo da alternativa correta. O gabarito não será exibido aos alunos enquanto resolvem o simulado.</p><div id="alternativas-editor" class="alternative-builder"></div><button id="adicionar-alternativa" class="button button-secondary" type="button">＋ Adicionar alternativa</button></fieldset>
            <div class="form-buttons full-width"><button id="questao-salvar" class="button button-primary" type="submit">Adicionar questão</button><button id="questao-cancelar" class="button button-secondary" type="button" hidden>Cancelar edição</button></div>
          </form>
          <p id="questao-form-msg" class="notice" hidden aria-live="polite"></p>
        </div>
      </section>
    </section>

    <p id="simulados-admin-note" class="subtle-note" hidden>Somente administradores criam e publicam simulados, e as questões só aparecem depois que você inicia a tentativa. Sua pontuação entra no <a class="text-link" href="ranking.php">ranking</a> assim que todas as respostas forem corrigidas.</p>

    <section id="simulados-list-panel" class="panel">
      <div class="section-heading filter-heading"><div><span class="eyebrow">Biblioteca</span><h2>Simulados disponíveis</h2></div><label class="filter-field"><span>Filtrar por matéria</span><select id="filtro-simulado-materia"><option value="">Todas as matérias</option></select></label></div>
      <div id="lista-simulados" class="simulado-grid"><p class="muted">Carregando simulados…</p></div>
    </section>

    <section id="tentativa-panel" class="panel attempt-panel" hidden>
      <div class="attempt-topline"><div><span class="eyebrow">Em andamento</span><h2 id="tentativa-titulo">Resolvendo simulado</h2><p id="tentativa-subtitulo" class="muted"></p></div><div class="timer-chip" id="tentativa-timer">Sem limite de tempo</div></div>
      <div class="progress-track"><div id="tentativa-progress" class="progress-fill"></div></div>
      <div id="tentativa-conteudo"></div>
      <p id="tentativa-msg" class="notice" hidden aria-live="polite"></p>
    </section>

    <section id="resultado-panel" class="panel result-panel" hidden>
      <div class="section-heading"><div><span class="eyebrow">Desempenho</span><h2>Resultado do simulado</h2></div><button id="fechar-resultado" type="button" class="button button-secondary">Fechar resultado</button></div>
      <div id="resultado-conteudo"></div>
    </section>

    <section id="historico-panel" class="panel history-panel">
      <div class="section-heading"><div><span class="eyebrow">Evolução</span><h2>Meu histórico</h2></div><button id="atualizar-historico" class="button button-quiet" type="button">Atualizar</button></div>
      <div id="lista-historico" class="history-list"><p class="muted">Carregando histórico…</p></div>
    </section>
  </main>
  <script src="api.js?v=20261102-importacao"></script><script src="app.js?v=20261102-importacao"></script><script src="richtext.js?v=20261102-importacao"></script><script src="simulados.js?v=20261102-importacao"></script><script src="importacao.js?v=20261102-importacao"></script>
</body>
</html>
