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
  <title>Dogs Team — Ranking</title>
</head>
<body class="app-page">
  <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
  <div id="app-nav"></div>
  <main id="conteudo" class="page-container ranking-page">
    <section class="page-title">
      <span class="eyebrow">Desempenho</span>
      <h1>Ranking de simulados</h1>
      <p>Classificação dos estudantes pela pontuação nos simulados. Para cada simulado vale a sua melhor nota; a pontuação total é a soma dessas notas. Administradores não participam.</p>
    </section>

    <section id="meu-ranking" class="my-rank-card" hidden></section>

    <section class="panel">
      <div class="section-heading filter-heading">
        <div><span class="eyebrow">Classificação</span><h2>Estudantes</h2></div>
        <label class="filter-field"><span>Filtrar por matéria</span><select id="filtro-ranking-materia"><option value="">Todas as matérias</option></select></label>
      </div>
      <div id="ranking-conteudo"><p class="muted">Carregando ranking…</p></div>
      <p class="ranking-footnote">Apenas simulados totalmente corrigidos entram na pontuação. Em empates (mesma pontuação e média), a posição é compartilhada.</p>
    </section>
  </main>
  <script src="api.js?v=20261103-fotos"></script><script src="app.js?v=20261103-fotos"></script><script src="ranking.js?v=20261103-fotos"></script>
</body>
</html>
