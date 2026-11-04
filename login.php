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
  <title>Dogs Team — Entrar</title>
</head>
<body class="auth-page">
  <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
  <main id="conteudo" class="Login">
    <div class="auth-brand"><span class="brand-mark">DT</span><span>Dogs Team</span></div>
    <h1>Bem-vindo de volta</h1>
    <p class="auth-subtitle">Entre para organizar suas matérias, anotações e datas importantes.</p>
    <form id="Logar" accept-charset="utf-8">
      <label class="input_box"><span class="field-label">Usuário ou e-mail</span><input type="text" name="user" id="user" placeholder="Seu usuário ou e-mail" autocomplete="username" required><img src="imagens/user.png" class="user-png" alt=""></label>
      <label class="input_box"><span class="field-label">Senha</span><input type="password" name="password" id="password" placeholder="Sua senha" autocomplete="current-password" required><img src="imagens/pass.png" class="pass-png" alt=""></label>
      <div class="Forgot_password"><a href="recuperar-senha.php">Esqueceu a senha?</a></div>
      <p class="form-error notice" id="login-erro" aria-live="polite" hidden></p>
      <button type="submit" class="btn">Entrar</button>
      <div class="Register"><p>Não tem uma conta? <a href="cadastro.php">Criar cadastro</a></p></div>
    </form>
  </main>
  <script src="api.js?v=20261103-fotos"></script>
  <script src="app.js?v=20261103-fotos"></script>
  <script src="script.js?v=20261103-fotos"></script>
</body>
</html>
