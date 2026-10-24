/* Cliente da API. Front-end e back-end são servidos pela mesma aplicação PHP. */
window.DOGS_TEAM_API_ROOT = window.DOGS_TEAM_API_ROOT ?? (() => {
  const path = window.location.pathname;
  const pageSuffix = /\/(?:index|login|cadastro|dashboard|materias|anotacoes|calendario|recuperar-senha)\.php$/;
  return pageSuffix.test(path) ? path.replace(pageSuffix, "") : path.replace(/\/+$/, "");
})();

window.DogsAPI = (() => {
  const tokenKey = "token";

  function token() {
    return localStorage.getItem(tokenKey);
  }

  function decodeTokenPayload(jwt) {
    try {
      const payload = jwt.split(".")[1].replace(/-/g, "+").replace(/_/g, "/");
      const padded = payload.padEnd(Math.ceil(payload.length / 4) * 4, "=");
      const binary = atob(padded);
      const bytes = Uint8Array.from(binary, char => char.charCodeAt(0));
      return JSON.parse(new TextDecoder().decode(bytes));
    } catch {
      return null;
    }
  }

  function userInfo() {
    const jwt = token();
    const payload = jwt ? decodeTokenPayload(jwt) : null;
    return {
      authenticated: Boolean(jwt && payload && payload.exp * 1000 > Date.now()),
      isAdmin: Boolean(payload?.isadmin),
      userId: payload?.sub ?? null,
      expiresAt: payload?.exp ? payload.exp * 1000 : null,
    };
  }

  async function request(path, options = {}) {
    const method = options.method || "GET";
    const headers = new Headers(options.headers || {});
    const jwt = token();

    if (options.auth !== false && jwt) {
      headers.set("Authorization", `Bearer ${jwt}`);
      headers.set("X-Authorization", `Bearer ${jwt}`);
    }
    if (options.body !== undefined && !(options.body instanceof FormData)) {
      headers.set("Content-Type", "application/json; charset=utf-8");
    }

    const base = String(window.DOGS_TEAM_API_ROOT || "").replace(/\/$/, "");
    let response;
    try {
      response = await fetch(`${base}${path}`, {
        method,
        headers,
        body: options.body === undefined
          ? undefined
          : (options.body instanceof FormData ? options.body : JSON.stringify(options.body)),
      });
    } catch {
      throw new Error("Não foi possível conectar à aplicação PHP. Confira o Apache e o endereço local.");
    }

    const raw = await response.text();
    let data = {};
    if (raw) {
      try {
        data = JSON.parse(raw);
      } catch {
        throw new Error(`O servidor retornou uma resposta que não é JSON (HTTP ${response.status}). Confira a rota e os logs do PHP/Apache.`);
      }
    }

    if (!response.ok) {
      const message = data.erro || data.mensagem || `Falha na requisição (HTTP ${response.status}).`;
      if (response.status === 401 && options.auth !== false && path !== "/api/login") {
        localStorage.removeItem(tokenKey);
      }
      throw new Error(message);
    }

    return data;
  }

  function clearToken() {
    localStorage.removeItem(tokenKey);
  }

  return { request, token, userInfo, clearToken };
})();
