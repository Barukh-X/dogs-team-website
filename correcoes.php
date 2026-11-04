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
  <title>Dogs Team — Correções</title>
</head>
<body class="app-page">
  <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
  <div id="app-nav"></div>
  <main id="conteudo" class="page-container simulator-page">
    <section class="page-title">
      <span class="eyebrow">Administração</span>
      <h1>Correção de simulados</h1>
      <p>Corrija as respostas discursivas e as justificativas enviadas pelos estudantes. A nota final e o ranking são atualizados quando todas as respostas de uma tentativa forem corrigidas.</p>
    </section>

    <section id="correcao-lista-panel" class="panel">
      <div class="section-heading filter-heading">
        <div><span class="eyebrow">Fila</span><h2 id="correcao-lista-titulo">Aguardando correção</h2></div>
        <div class="segmented" role="tablist" aria-label="Situação das correções">
          <button type="button" class="segmented-option active" data-status="aguardando_correcao" role="tab" aria-selected="true">Pendentes</button>
          <button type="button" class="segmented-option" data-status="concluida" role="tab" aria-selected="false">Já corrigidas</button>
        </div>
      </div>
      <div id="correcao-lista" class="correction-list"><p class="muted">Carregando…</p></div>
    </section>

    <section id="correcao-detalhe-panel" class="panel" hidden>
      <div class="section-heading"><div><span class="eyebrow">Tentativa</span><h2 id="correcao-detalhe-titulo">Corrigir respostas</h2></div><button id="correcao-voltar" class="button button-secondary" type="button">← Voltar à fila</button></div>
      <div id="correcao-detalhe-resumo"></div>
      <div id="correcao-detalhe-questoes" class="correction-questions"></div>
    </section>
  </main>
  <script src="api.js?v=20261103-fotos"></script><script src="app.js?v=20261103-fotos"></script><script src="correcoes.js?v=20261103-fotos"></script>
</body>
</html>
