const formulario = document.getElementById("Cadastrar");
const erroEl = document.getElementById("cadastro-erro");

formulario.addEventListener("submit", async (event) => {
  event.preventDefault();
  DogsApp.message(erroEl, "");

  const nome = document.getElementById("nome").value.trim();
  const usuario = document.getElementById("user").value.trim();
  const email = document.getElementById("email").value.trim();
  const senha = document.getElementById("password").value;
  const confirmarSenha = document.getElementById("confirm_password").value;
  const botao = formulario.querySelector('button[type="submit"]');

  if (senha !== confirmarSenha) return DogsApp.message(erroEl, "As senhas não coincidem.", "error");
  if (senha.length < 6) return DogsApp.message(erroEl, "Use uma senha com pelo menos 6 caracteres.", "error");
  if (nome.length > 30 || usuario.length > 30 || email.length > 50) {
    return DogsApp.message(erroEl, "Nome e usuário devem ter até 30 caracteres; e-mail, até 50.", "error");
  }

  botao.disabled = true;
  botao.textContent = "Cadastrando...";
  try {
    await DogsAPI.request("/api/cadastrar", {
      method: "POST", auth: false, body: { nome, usuario, email, senha },
    });
    DogsApp.message(erroEl, "Cadastro realizado. Redirecionando para o login...", "success");
    setTimeout(() => window.location.replace("index.php"), 900);
  } catch (error) {
    DogsApp.message(erroEl, error.message || "Não foi possível cadastrar.", "error");
  } finally {
    botao.disabled = false;
    botao.textContent = "Criar conta";
  }
});
