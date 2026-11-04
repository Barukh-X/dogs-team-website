const formulario = document.getElementById("Logar");
const erroEl = document.getElementById("login-erro");

function mostrarErroDeInicializacao(mensagem) {
  if (!erroEl) return;
  erroEl.textContent = mensagem;
  erroEl.className = "notice error";
  erroEl.hidden = false;
  erroEl.setAttribute("role", "alert");
}

if (!formulario) {
  console.error("Dogs Team: formulário de login não encontrado.");
} else if (!window.DogsAPI || typeof window.DogsAPI.request !== "function" || !window.DogsApp) {
  mostrarErroDeInicializacao("Não foi possível carregar os arquivos da aplicação. Atualize a página; se o problema continuar, confira se api.js e app.js foram enviados para a hospedagem.");
  console.error("Dogs Team: cliente da API ou utilitários não foram carregados.");
  const botao = formulario.querySelector('button[type="submit"]');
  if (botao) botao.disabled = true;
} else {
  if (window.DogsAPI.userInfo().authenticated) window.location.replace("dashboard.php");

  formulario.addEventListener("submit", async (event) => {
    event.preventDefault();
    window.DogsApp.message(erroEl, "");
    const logar = document.getElementById("user").value.trim();
    const senha = document.getElementById("password").value;
    const botao = formulario.querySelector('button[type="submit"]');
    botao.disabled = true;
    botao.textContent = "Entrando...";

    try {
      const dados = await window.DogsAPI.request("/api/login", {
        method: "POST", auth: false, body: { logar, senha },
      });
      if (!dados.token) throw new Error("A API não retornou um token de autenticação.");
      localStorage.setItem("token", dados.token);
      window.location.href = "dashboard.php";
    } catch (error) {
      window.DogsApp.message(erroEl, error.message || "Não foi possível entrar.", "error");
    } finally {
      botao.disabled = false;
      botao.textContent = "Entrar";
    }
  });
}
