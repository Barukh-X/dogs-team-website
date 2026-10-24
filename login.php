<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#16251e">
  <link rel="stylesheet" href="style.css">
  <title>Dogs Team — Entrar</title>
</head>
<body class="auth-page">
  <main class="Login">
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
  <script src="api.js"></script>
  <script src="app.js"></script>
  <script src="script.js"></script>
</body>
</html>
