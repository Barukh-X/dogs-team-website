<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8">
  <?php require_once __DIR__ . '/social-meta.php'; ?><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#100b1f"><link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="style.css?v=20261103-fotos"><title>Dogs Team — Painel</title></head>
<body class="app-page">
  <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
  <div id="app-nav"></div>
  <main id="conteudo" class="page-container">
    <section class="welcome-block"><div><span class="eyebrow">Seu espaço de estudos</span><h1>Seu painel</h1><p>Uma visão rápida das matérias, anotações e próximos compromissos.</p></div><div class="api-indicator"><span class="muted">Sessão</span><span id="api-status" class="status-pill">Verificando API…</span></div></section>
    <section><div class="section-heading"><div><span class="eyebrow">Resumo</span><h2>Visão geral</h2></div></div><div id="dashboard-stats" class="stats-grid"><p class="muted">Carregando informações…</p></div></section>
    <div class="dashboard-columns">
      <section class="panel"><div class="section-heading"><div><span class="eyebrow">Agenda</span><h2>Próximas datas</h2></div><a class="text-link" href="calendario.php">Ver todas</a></div><div id="proximas-datas" class="compact-list"></div></section>
      <section class="panel"><div class="section-heading"><div><span class="eyebrow">Anotações</span><h2>Recentes</h2></div><a class="text-link" href="anotacoes.php">Ver todas</a></div><div id="ultimas-anotacoes" class="compact-list"></div></section>
    </div>
  </main>
  <script src="api.js?v=20261103-fotos"></script><script src="app.js?v=20261103-fotos"></script><script src="dashboard.js?v=20261103-fotos"></script>
</body>
</html>
