<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="style.css"><title>Dogs Team — Matérias</title></head>
<body class="app-page">
  <div id="app-nav"></div>
  <main class="page-container">
    <section class="page-title"><span class="eyebrow">ORGANIZAÇÃO</span><h1>Matérias</h1><p>Consulte suas matérias e veja as datas e anotações associadas.</p></section>
    <section class="panel admin-panel" id="materia-admin-panel" hidden><div class="section-heading"><div><span class="eyebrow">ADMINISTRAÇÃO</span><h2>Adicionar ou editar matéria</h2></div></div><form id="materia-form" class="inline-form"><input type="hidden" id="materia-id"><label class="form-field grow"><span>Nome da matéria</span><input id="nome-materia" type="text" maxlength="30" placeholder="Ex.: Matemática" required></label><div class="form-buttons"><button id="materia-submit" class="button button-primary" type="submit">Adicionar matéria</button><button id="materia-cancel" class="button button-secondary" type="button" hidden>Cancelar</button></div></form><p id="materia-msg" class="notice" hidden></p></section>
    <p id="materia-admin-note" class="subtle-note" hidden>Somente administradores podem cadastrar, editar ou excluir matérias.</p>
    <section class="content-section"><div class="section-heading"><div><span class="eyebrow">CADASTRO</span><h2>Matérias cadastradas</h2></div></div><div id="lista-materias" class="resource-list"><p class="muted">Carregando matérias…</p></div></section>
  </main>
  <script src="api.js"></script><script src="app.js"></script><script src="materias.js"></script>
</body>
</html>
