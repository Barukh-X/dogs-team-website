<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="style.css"><title>Dogs Team — Datas importantes</title></head>
<body class="app-page">
  <div id="app-nav"></div>
  <main class="page-container">
    <section class="page-title"><span class="eyebrow">PLANEJAMENTO</span><h1>Datas importantes</h1><p>Acompanhe provas, entregas e outros compromissos futuros.</p></section>
    <section class="panel admin-panel" id="data-admin-panel" hidden><div class="section-heading"><div><span class="eyebrow">ADMINISTRAÇÃO</span><h2>Adicionar ou editar data</h2></div></div>
      <form id="data-form" class="form-grid"><input id="data-id" type="hidden">
        <label class="form-field"><span>Título</span><input id="data-titulo" type="text" maxlength="50" placeholder="Ex.: Prova de física" required></label>
        <label class="form-field"><span>Matéria (opcional)</span><select id="data-materia"><option value="">Sem matéria vinculada</option></select></label>
        <label class="form-field"><span>Data e hora</span><input id="data-hora" type="datetime-local" required></label>
        <label class="form-field full-width"><span>Descrição (opcional)</span><textarea id="data-descricao" rows="3" placeholder="Detalhes do compromisso…"></textarea></label>
        <div class="form-buttons full-width"><button id="data-submit" class="button button-primary" type="submit">Adicionar data</button><button id="data-cancel" class="button button-secondary" type="button" hidden>Cancelar edição</button></div>
      </form><p id="data-msg" class="notice" hidden></p>
    </section>
    <p id="data-admin-note" class="subtle-note" hidden>As datas podem ser consultadas por usuários autenticados. Somente administradores podem cadastrar, editar ou excluir datas.</p>
    <section class="content-section"><div class="section-heading"><div><span class="eyebrow">AGENDA</span><h2>Próximos compromissos</h2></div></div><div id="lista-datas" class="resource-list"><p class="muted">Carregando datas…</p></div></section>
  </main>
  <script src="api.js"></script><script src="app.js"></script><script src="calendario.js"></script>
</body>
</html>
