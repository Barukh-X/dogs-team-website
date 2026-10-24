<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="style.css"><title>Dogs Team — Anotações</title></head>
<body class="app-page">
  <div id="app-nav"></div>
  <main class="page-container">
    <section class="page-title"><span class="eyebrow">CONHECIMENTO</span><h1>Anotações</h1><p>Guarde explicações, lembretes e links úteis para cada matéria.</p></section>
    <section class="panel"><div class="section-heading"><div><span class="eyebrow">NOVA ANOTAÇÃO</span><h2>Escrever anotação</h2></div></div>
      <form id="anotacao-form" class="form-grid">
        <input type="hidden" id="anotacao-id">
        <label class="form-field"><span>Matéria</span><select id="anotacao-materia" required><option value="">Carregando matérias…</option></select></label>
        <label class="form-field"><span>Título</span><input id="anotacao-titulo" type="text" maxlength="100" placeholder="Título da anotação" required></label>
        <label class="form-field full-width"><span>Descrição</span><textarea id="anotacao-descricao" rows="4" placeholder="Escreva o conteúdo da anotação…"></textarea></label>
        <label class="form-field full-width"><span>Links (opcional)</span><textarea id="anotacao-links" rows="3" placeholder="Um link por linha. Opcionalmente use: Título | https://exemplo.com"></textarea><small>São aceitos até 20 links por anotação; use URLs http ou https.</small></label>
        <label id="anotacao-publica-wrap" class="check-field" hidden><input id="anotacao-publica" type="checkbox" checked><span>Disponibilizar como anotação pública</span></label>
        <div class="form-buttons full-width"><button id="anotacao-submit" class="button button-primary" type="submit">Salvar anotação</button><button id="anotacao-cancel" class="button button-secondary" type="button" hidden>Cancelar edição</button></div>
      </form><p id="anotacao-msg" class="notice" hidden></p>
    </section>
    <section class="content-section"><div class="section-heading filter-heading"><div><span class="eyebrow">SUA BIBLIOTECA</span><h2>Anotações disponíveis</h2></div><label class="filter-field"><span>Filtrar por matéria</span><select id="filtro-materia"><option value="">Todas as matérias</option></select></label></div><div id="lista-anotacoes" class="resource-list"><p class="muted">Carregando anotações…</p></div></section>
  </main>
  <script src="api.js"></script><script src="app.js"></script><script src="anotacoes.js"></script>
</body>
</html>
