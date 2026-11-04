<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <?php require_once __DIR__ . '/social-meta.php'; ?><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#100b1f">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="style.css?v=20261103-fotos"><title>Dogs Team — Cadastro</title>
</head>
<body class="auth-page">
  <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
  <main id="conteudo" class="Login auth-register">
    <div class="auth-brand"><span class="brand-mark">DT</span><span>Dogs Team</span></div>
    <h1>Criar conta</h1><p class="auth-subtitle">Comece a organizar sua rotina de estudos.</p>
    <form id="Cadastrar" accept-charset="utf-8">
      <label class="input_box"><span class="field-label">Nome completo</span><input type="text" name="nome" id="nome" placeholder="Nome completo" maxlength="30" autocomplete="name" required><img src="imagens/user.png" alt=""></label>
      <label class="input_box"><span class="field-label">Usuário</span><input type="text" name="user" id="user" placeholder="Como quer ser chamado" maxlength="30" autocomplete="username" required><img src="imagens/user.png" alt=""></label>
      <label class="input_box"><span class="field-label">E-mail</span><input type="email" name="email" id="email" placeholder="voce@exemplo.com" maxlength="50" autocomplete="email" required><img src="imagens/email.png" alt=""></label>
      <label class="input_box"><span class="field-label">Senha</span><input type="password" name="password" id="password" placeholder="Pelo menos 6 caracteres" autocomplete="new-password" required><img src="imagens/pass.png" alt=""></label>
      <label class="input_box"><span class="field-label">Confirmar senha</span><input type="password" name="confirm_password" id="confirm_password" placeholder="Digite a senha novamente" autocomplete="new-password" required><img src="imagens/pass.png" alt=""></label>
      <p class="form-error notice" id="cadastro-erro" aria-live="polite" hidden></p>
      <button type="submit" class="btn">Criar conta</button>
      <div class="Register"><p>Já tem cadastro? <a href="index.php">Entrar</a></p></div>
    </form>
  </main>
  <script src="api.js?v=20261103-fotos"></script><script src="app.js?v=20261103-fotos"></script><script src="cadastro_script.js?v=20261103-fotos"></script>
</body>
</html>
