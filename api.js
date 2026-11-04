/* Cliente da API. Front-end e back-end são servidos pela mesma aplicação PHP. */
window.DOGS_TEAM_API_ROOT = window.DOGS_TEAM_API_ROOT ?? (() => {
  const path = window.location.pathname;
  const pageSuffix = /\/(?:index|login|cadastro|dashboard|materias|anotacoes|calendario|simulados|ranking|correcoes|batalha|recuperar-senha)\.php$/;
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
      const failure = new Error(message);
      failure.status = response.status;
      // Respostas 422 de validação (ex.: importação de simulados) trazem erros por item em "detalhes".
      if (Array.isArray(data.detalhes)) failure.details = data.detalhes;
      throw failure;
    }

    return data;
  }

  async function requestBlob(path) {
    const headers = new Headers();
    const jwt = token();
    if (jwt) {
      headers.set("Authorization", `Bearer ${jwt}`);
      headers.set("X-Authorization", `Bearer ${jwt}`);
    }
    const base = String(window.DOGS_TEAM_API_ROOT || "").replace(/\/$/, "");
    let response;
    try {
      response = await fetch(`${base}${path}`, { method: "GET", headers });
    } catch {
      throw new Error("Não foi possível carregar a imagem. Verifique a conexão com a aplicação.");
    }
    if (!response.ok) {
      const raw = await response.text();
      let data = {};
      try { data = raw ? JSON.parse(raw) : {}; } catch { /* resposta não JSON */ }
      if (response.status === 401) localStorage.removeItem(tokenKey);
      throw new Error(data.erro || data.mensagem || `Não foi possível carregar a imagem (HTTP ${response.status}).`);
    }
    const blob = await response.blob();
    // Alguns servidores/proxies concatenam cabeçalhos MIME repetidos (ex.:
    // 'image/jpeg, image/jpeg'). Reconstrua o Blob com o primeiro MIME válido.
    const mimeRaw = response.headers.get('content-type') || blob.type || '';
    const mimeCandidates = mimeRaw.split(',').map(value => value.trim().toLowerCase());
    const allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    const mime = mimeCandidates.find(value => allowedMimes.includes(value));
    return mime ? new Blob([blob], { type: mime }) : blob;
  }

  function clearToken() {
    localStorage.removeItem(tokenKey);
  }

  return { request, requestBlob, token, userInfo, clearToken };
})();
