const formulario = document.getElementById("Logar");
const erroEl = document.getElementById("login-erro");

if (DogsAPI.userInfo().authenticated) window.location.replace("dashboard.php");

formulario.addEventListener("submit", async (event) => {
  event.preventDefault();
  DogsApp.message(erroEl, "");
  const logar = document.getElementById("user").value.trim();
  const senha = document.getElementById("password").value;
  const botao = formulario.querySelector('button[type="submit"]');
  botao.disabled = true;
  botao.textContent = "Entrando...";

  try {
    const dados = await DogsAPI.request("/api/login", {
      method: "POST", auth: false, body: { logar, senha },
    });
    if (!dados.token) throw new Error("A API não retornou um token de autenticação.");
    localStorage.setItem("token", dados.token);
    window.location.href = "dashboard.php";
  } catch (error) {
    DogsApp.message(erroEl, error.message || "Não foi possível entrar.", "error");
  } finally {
    botao.disabled = false;
    botao.textContent = "Entrar";
  }
});
