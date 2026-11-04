<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8">
  <?php require_once __DIR__ . '/social-meta.php'; ?><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#100b1f"><link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="style.css?v=20261103-fotos"><title>Dogs Team — Matérias</title></head>
<body class="app-page">
  <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
  <div id="app-nav"></div>
  <main id="conteudo" class="page-container">
    <section class="page-title"><span class="eyebrow">Organização</span><h1>Matérias</h1><p>Consulte suas matérias e veja as datas e anotações associadas.</p></section>
    <section class="panel admin-panel" id="materia-admin-panel" hidden><div class="section-heading"><div><span class="eyebrow">Administração</span><h2>Adicionar ou editar matéria</h2></div></div><form id="materia-form" class="inline-form"><input type="hidden" id="materia-id"><label class="form-field grow"><span>Nome da matéria</span><input id="nome-materia" type="text" maxlength="30" placeholder="Ex.: Matemática" required></label><div class="form-buttons"><button id="materia-submit" class="button button-primary" type="submit">Adicionar matéria</button><button id="materia-cancel" class="button button-secondary" type="button" hidden>Cancelar</button></div></form><p id="materia-msg" class="notice" hidden></p></section>
    <p id="materia-admin-note" class="subtle-note" hidden>Somente administradores podem cadastrar, editar ou excluir matérias.</p>
    <section class="content-section"><div class="section-heading"><div><span class="eyebrow">Cadastro</span><h2>Matérias cadastradas</h2></div></div><div id="lista-materias" class="resource-list"><p class="muted">Carregando matérias…</p></div></section>
  </main>
  <script src="api.js?v=20261103-fotos"></script><script src="app.js?v=20261103-fotos"></script><script src="materias.js?v=20261103-fotos"></script>
</body>
</html>
