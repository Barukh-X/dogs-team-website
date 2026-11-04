/*
 * Renderização segura de enunciados, alternativas e explicações.
 *
 * Segurança: todo o texto é escapado ANTES de qualquer formatação. O HTML de saída é montado apenas
 * com um conjunto fixo de tags (p, br, ul, li, strong, em, code, pre, span); HTML digitado no
 * arquivo aparece como texto. Não há links, imagens, atributos vindos do texto nem eval.
 *
 * Formatação aceita: blocos de código (``` ou ~~~, com linguagem opcional), `código`, **negrito**,
 * *itálico*, listas com "- ", e fórmulas LaTeX: $x^2$, $$...$$, \(...\) e \[...\].
 *
 * KaTeX (fórmulas) e highlight.js (cores do código) são carregados do cdnjs somente quando a página
 * tem fórmulas ou código. Se o CDN estiver indisponível, o texto continua legível (fórmulas aparecem
 * com seus delimitadores e o código, sem cores).
 */
window.DogsRich = (() => {
  const KATEX_VERSION = "0.16.9";
  const HLJS_VERSION = "11.9.0";
  const CDN = "https://cdnjs.cloudflare.com/ajax/libs";
  const KATEX_CSS = `${CDN}/KaTeX/${KATEX_VERSION}/katex.min.css`;
  const KATEX_JS = `${CDN}/KaTeX/${KATEX_VERSION}/katex.min.js`;
  const HLJS_CSS = `${CDN}/highlight.js/${HLJS_VERSION}/styles/atom-one-dark.min.css`;
  const HLJS_JS = `${CDN}/highlight.js/${HLJS_VERSION}/highlight.min.js`;

  const PH_OPEN = "\uE000";
  const PH_CLOSE = "\uE001";
  const MAX_TEX_LENGTH = 2000;

  function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, char => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
    })[char]);
  }

  function safeLanguage(value) {
    return /^[A-Za-z0-9_+#.-]{1,20}$/.test(value || "") ? value.toLowerCase() : "";
  }

  function codeBlockHtml(code, language, inline) {
    const lang = safeLanguage(language);
    const cls = `language-${lang || "plaintext"}`;
    const attr = lang ? ` data-lang="${escapeHtml(lang)}"` : "";
    if (inline) return `<code class="inline-code code-multiline ${cls}"${attr}>${escapeHtml(code)}</code>`;
    return `<pre class="rich-code"><code class="${cls}"${attr}>${escapeHtml(code)}</code></pre>`;
  }

  /** Fórmula: o conteúdo inicial é o texto original (fallback); enhance() troca pelo HTML do KaTeX. */
  function mathHtml(tex, original, display) {
    return `<span class="math-tex${display ? " math-display" : ""}" data-display="${display ? 1 : 0}" data-tex="${escapeHtml(tex)}">${escapeHtml(original)}</span>`;
  }

  /** Formatação de linha/parágrafo (inline): código, fórmulas, negrito e itálico. */
  function inlineHtml(text) {
    const stash = [];
    const keep = html => {
      stash.push(html);
      return `${PH_OPEN}${stash.length - 1}${PH_CLOSE}`;
    };

    // 1) código inline (protege o conteúdo de qualquer outra formatação)
    let work = text
      .replace(/``([^\n]+?)``/g, (_, code) => keep(`<code class="inline-code">${escapeHtml(code.trim())}</code>`))
      .replace(/`([^`\n]+)`/g, (_, code) => keep(`<code class="inline-code">${escapeHtml(code)}</code>`));

    // 2) fórmulas
    const math = (match, tex, display) => {
      const trimmed = tex.trim();
      if (!trimmed || trimmed.length > MAX_TEX_LENGTH || trimmed.includes(PH_OPEN)) return match;
      return keep(mathHtml(trimmed, match, display));
    };
    work = work
      .replace(/\$\$([\s\S]+?)\$\$/g, (m, tex) => math(m, tex, true))
      .replace(/\\\[([\s\S]+?)\\\]/g, (m, tex) => math(m, tex, true))
      .replace(/\\\(([\s\S]+?)\\\)/g, (m, tex) => math(m, tex, false))
      // "$5 e $10" não é fórmula: sem espaço junto aos cifrões e sem dígito logo após o fechamento.
      .replace(/\$((?:\\.|[^$\\\n])+?)\$(?!\d)/g, (m, tex) => (/^\s|\s$/.test(tex) ? m : math(m, tex, false)));

    // 3) escapa tudo o que sobrou e aplica negrito/itálico
    let html = escapeHtml(work)
      .replace(/\*\*([^\n]+?)\*\*/g, "<strong>$1</strong>")
      .replace(/(^|[^*\w])\*([^*\n]+?)\*(?![*\w])/g, "$1<em>$2</em>")
      .replace(/(^|[^\w])_([^_\n]+?)_(?!\w)/g, "$1<em>$2</em>");

    return html.replace(new RegExp(`${PH_OPEN}(\\d+)${PH_CLOSE}`, "g"), (_, index) => stash[Number(index)] ?? "");
  }

  /** Parágrafos e listas de um trecho que não é bloco de código. */
  function textBlocksHtml(text, inline) {
    // Fórmulas $$...$$ podem ocupar várias linhas: troca-as por marcadores antes de dividir em linhas.
    const multiline = [];
    const protectedText = text.replace(/\$\$([\s\S]+?)\$\$/g, match => {
      multiline.push(match);
      return `${PH_OPEN}M${multiline.length - 1}${PH_CLOSE}`;
    });
    const restoreMath = value => value.replace(new RegExp(`${PH_OPEN}M(\\d+)${PH_CLOSE}`, "g"), (_, index) => multiline[Number(index)] ?? "");

    const lines = protectedText.split("\n");
    if (inline) {
      return lines.map(line => inlineHtml(restoreMath(line))).join("<br>").replace(/(?:<br>)+$/, "");
    }

    const out = [];
    let paragraph = [];
    let list = [];
    const flushParagraph = () => {
      if (paragraph.length) out.push(`<p>${paragraph.map(line => inlineHtml(restoreMath(line))).join("<br>")}</p>`);
      paragraph = [];
    };
    const flushList = () => {
      if (list.length) out.push(`<ul>${list.map(item => `<li>${inlineHtml(restoreMath(item))}</li>`).join("")}</ul>`);
      list = [];
    };
    for (const line of lines) {
      const bullet = /^\s{0,3}[-*+]\s+(.*)$/.exec(line);
      if (line.trim() === "") {
        flushParagraph();
        flushList();
      } else if (bullet) {
        flushParagraph();
        list.push(bullet[1]);
      } else {
        flushList();
        paragraph.push(line);
      }
    }
    flushParagraph();
    flushList();
    return out.join("");
  }

  /**
   * Converte texto (Markdown simplificado) em HTML seguro.
   * @param {string} text
   * @param {{inline?: boolean}} [options] inline=true devolve apenas HTML em linha (para alternativas e listas).
   */
  function render(text, options = {}) {
    const inline = Boolean(options.inline);
    const source = String(text ?? "").replace(/\r\n?/g, "\n").replace(/[\uE000\uE001]/g, "");
    if (!source.trim()) return "";

    // Separa blocos de código cercados; o restante é texto comum.
    const parts = [];
    let fence = null;
    let language = "";
    let buffer = [];
    const pushText = () => {
      if (buffer.length) parts.push({ type: "text", value: buffer.join("\n") });
      buffer = [];
    };
    for (const line of source.split("\n")) {
      const match = /^\s{0,3}(`{3,}|~{3,})\s*([^\s`]*)[^`]*$/.exec(line);
      if (fence) {
        const closing = /^\s{0,3}(`{3,}|~{3,})\s*$/.exec(line);
        if (closing && closing[1][0] === fence[0] && closing[1].length >= fence.length) {
          parts.push({ type: "code", value: buffer.join("\n"), language });
          buffer = [];
          fence = null;
        } else {
          buffer.push(line);
        }
      } else if (match) {
        pushText();
        fence = match[1];
        language = match[2] || "";
      } else {
        buffer.push(line);
      }
    }
    if (fence) parts.push({ type: "code", value: buffer.join("\n"), language }); // bloco sem fechamento
    else pushText();

    const html = parts.map(part => (part.type === "code"
      ? codeBlockHtml(part.value, part.language, inline)
      : textBlocksHtml(part.value, inline))).join(inline ? "<br>" : "");
    return inline
      ? `<span class="rich-text rich-inline">${html}</span>`
      : `<div class="rich-text">${html}</div>`;
  }

  // ------------------------------------------------------------------
  // Bibliotecas externas (carregadas sob demanda, uma única vez)
  // ------------------------------------------------------------------

  const loaders = {};

  function loadStyle(href) {
    if (document.querySelector(`link[data-rich-lib="${href}"]`)) return;
    const link = document.createElement("link");
    link.rel = "stylesheet";
    link.href = href;
    link.crossOrigin = "anonymous";
    link.referrerPolicy = "no-referrer";
    link.dataset.richLib = href;
    document.head.appendChild(link);
  }

  function loadScript(src) {
    return new Promise((resolve, reject) => {
      const script = document.createElement("script");
      const timer = window.setTimeout(() => reject(new Error("Tempo esgotado ao carregar " + src)), 10000);
      script.src = src;
      script.async = true;
      script.crossOrigin = "anonymous";
      script.referrerPolicy = "no-referrer";
      script.onload = () => { window.clearTimeout(timer); resolve(); };
      script.onerror = () => { window.clearTimeout(timer); reject(new Error("Falha ao carregar " + src)); };
      document.head.appendChild(script);
    });
  }

  function loadKatex() {
    if (!loaders.katex) {
      loaders.katex = (async () => {
        if (window.katex) return window.katex;
        loadStyle(KATEX_CSS);
        await loadScript(KATEX_JS);
        return window.katex || null;
      })().catch(() => null);
    }
    return loaders.katex;
  }

  function loadHighlighter() {
    if (!loaders.hljs) {
      loaders.hljs = (async () => {
        if (window.hljs) return window.hljs;
        loadStyle(HLJS_CSS);
        await loadScript(HLJS_JS);
        return window.hljs || null;
      })().catch(() => null);
    }
    return loaders.hljs;
  }

  /**
   * Renderiza as fórmulas e colore os blocos de código dentro de `root`.
   * Nunca rejeita: se uma biblioteca não carregar, o texto original permanece.
   */
  async function enhance(root = document) {
    const maths = [...root.querySelectorAll(".math-tex:not([data-rendered])")];
    const codes = [...root.querySelectorAll("pre.rich-code code[data-lang]:not([data-highlighted])")];
    const tasks = [];

    if (maths.length) {
      tasks.push(loadKatex().then(katex => {
        if (!katex) return;
        maths.forEach(element => {
          if (!element.isConnected || element.dataset.rendered) return;
          try {
            katex.render(element.dataset.tex || "", element, {
              displayMode: element.dataset.display === "1",
              throwOnError: false,
              trust: false,
              strict: "ignore",
              maxSize: 30,
              maxExpand: 200,
            });
            element.dataset.rendered = "1";
          } catch {
            /* mantém o texto original */
          }
        });
      }));
    }
    if (codes.length) {
      tasks.push(loadHighlighter().then(hljs => {
        if (!hljs) return;
        codes.forEach(element => {
          if (!element.isConnected) return;
          try { hljs.highlightElement(element); } catch { /* sem cores */ }
        });
      }));
    }
    await Promise.all(tasks);
  }

  return { render, enhance, escapeHtml };
})();
