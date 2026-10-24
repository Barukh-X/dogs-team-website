<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="style.css"><title>Dogs Team — Painel</title></head>
<body class="app-page">
  <div id="app-nav"></div>
  <main class="page-container">
    <section class="welcome-block"><div><span class="eyebrow">SEU ESPAÇO DE ESTUDOS</span><h1>Seu painel</h1><p>Uma visão rápida das matérias, anotações e próximos compromissos.</p></div><div class="api-indicator"><span class="muted">Conexão</span><span id="api-status" class="status-pill">Verificando API…</span></div></section>
    <section><div class="section-heading"><div><span class="eyebrow">RESUMO</span><h2>Visão geral</h2></div></div><div id="dashboard-stats" class="stats-grid"><p class="muted">Carregando informações…</p></div></section>
    <div class="dashboard-columns">
      <section class="panel"><div class="section-heading"><div><span class="eyebrow">AGENDA</span><h2>Próximas datas</h2></div><a class="text-link" href="calendario.php">Ver todas →</a></div><div id="proximas-datas" class="compact-list"></div></section>
      <section class="panel"><div class="section-heading"><div><span class="eyebrow">ANOTAÇÕES</span><h2>Recentes</h2></div><a class="text-link" href="anotacoes.php">Ver todas →</a></div><div id="ultimas-anotacoes" class="compact-list"></div></section>
    </div>
  </main>
  <script src="api.js"></script><script src="app.js"></script><script src="dashboard.js"></script>
</body>
</html>
