window.DogsApp = (() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

  function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (char) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
    })[char]);
  }

  function message(target, text = "", type = "info") {
    const element = typeof target === "string" ? $(target) : target;
    if (!element) return;
    element.textContent = text;
    element.className = `notice ${type}`;
    element.hidden = !text;
    element.setAttribute("role", type === "error" ? "alert" : "status");
  }

  function requireAuth() {
    const info = DogsAPI.userInfo();
    if (!info.authenticated) {
      DogsAPI.clearToken();
      window.location.replace("index.php");
      return false;
    }
    return true;
  }

  function renderNav(activePage) {
    const target = $("#app-nav");
    if (!target) return;
    const info = DogsAPI.userInfo();
    const links = [
      ["dashboard", "Painel", "dashboard.php"],
      ["materias", "Matérias", "materias.php"],
      ["anotacoes", "Anotações", "anotacoes.php"],
      ["calendario", "Datas importantes", "calendario.php"],
    ];
    target.innerHTML = `
      <header class="app-header">
        <a class="brand" href="dashboard.php" aria-label="Dogs Team, painel inicial"><span class="brand-mark">DT</span><span>Dogs Team</span></a>
        <nav class="main-nav" aria-label="Navegação principal">
          ${links.map(([key, label, href]) => `<a href="${href}"${key === activePage ? ' aria-current="page" class="active"' : ""}>${label}</a>`).join("")}
        </nav>
        <div class="account-area"><span class="account-role">${info.isAdmin ? "Administrador" : "Usuário"}</span><button id="logout-button" class="button button-quiet" type="button">Sair</button></div>
      </header>`;
    $("#logout-button", target)?.addEventListener("click", logout);
  }

  async function logout() {
    try {
      await DogsAPI.request("/api/logout", { method: "POST" });
    } catch (error) {
      // O token é local; mesmo se a requisição falhar, finaliza a sessão do navegador.
      console.warn("Não foi possível confirmar o logout na API:", error.message);
    } finally {
      DogsAPI.clearToken();
      window.location.replace("index.php");
    }
  }

  function formatDate(value) {
    if (!value) return "Sem data";
    const normalized = String(value).includes("T") ? value : String(value).replace(" ", "T");
    const date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat("pt-BR", {
      dateStyle: "medium", timeStyle: "short",
    }).format(date);
  }

  function toDateTimeLocal(value) {
    if (!value) return "";
    return String(value).replace(" ", "T").slice(0, 16);
  }

  function linkLinesToArray(text) {
    return String(text || "").split("\n").map(line => line.trim()).filter(Boolean).map(line => {
      const separator = line.indexOf("|");
      if (separator < 0) return { url: line, titulo: "" };
      return { titulo: line.slice(0, separator).trim(), url: line.slice(separator + 1).trim() };
    });
  }

  function linksToLines(links) {
    return (links || []).map(link => link.titulo ? `${link.titulo} | ${link.url}` : link.url).join("\n");
  }

  function initPage(page) {
    if (!requireAuth()) return false;
    renderNav(page);
    return true;
  }

  return { $, $$, escapeHtml, message, requireAuth, renderNav, logout, formatDate, toDateTimeLocal, linkLinesToArray, linksToLines, initPage };
})();
