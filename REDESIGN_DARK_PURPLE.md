# Dogs Team — Redesign Dark Purple

## Decisão de arquitetura: HTML, CSS e JavaScript puros

Mantive o front-end sem framework. Motivos técnicos:

- **Compatibilidade com a hospedagem**: o projeto é PHP + Apache (XAMPP / InfinityFree), sem Node no servidor. Um framework exigiria etapa de build e deploy de artefatos, sem ganho real aqui.
- **Tamanho do projeto**: 11 páginas, módulos JS pequenos e independentes. Reescrever em React/Vue arriscaria autenticação (JWT), uploads, visualizador de imagens, importação `.md` e o fluxo de simulados, que já funcionam.
- **Desempenho**: zero dependências novas de JavaScript. As únicas adições externas são as fontes (Bricolage Grotesque e Figtree, via Google Fonts, com fallback para fontes do sistema).
- **Manutenção**: o design agora vive em um único sistema de tokens CSS (`:root` em `style.css`). Trocar a identidade visual é editar variáveis, não componentes.

API, endpoints, contratos de dados, autenticação e tokens **não foram alterados**.

## O que mudou

| Área | Alteração |
|---|---|
| `style.css` | Reescrito do zero (era um tema verde com uma camada dark sobreposta). Tokens, componentes e responsividade em um só lugar, sem sobrescritas. |
| `app.js` | Navegação com ícones SVG e menu responsivo acessível (botão, `aria-expanded`, Esc, clique fora); `DogsApp.toast()`; toasts automáticos para mensagens de sucesso; indicador de carregamento automático em textos "Carregando…"; foco movido para o modal de perfil e devolvido ao fechar. |
| `ranking.js` | `data-label` nas células: a tabela vira lista de cartões no celular. |
| Páginas `.php` | Fontes por `<link>`, link "Pular para o conteúdo", `<main id="conteudo">`, rótulos em caixa normal, `theme-color` atualizado, cache-busting novo. |
| Navegação | Item "Datas importantes" aparece como "Calendário" no menu (a página mantém o título). |

## Paleta

Fundo `#100B1F`, superfícies `#19132B` e `#211A35`, primária `#8B5CF6` (botões usam `#7C3AED` para garantir contraste com texto branco), secundária `#A78BFA`, destaque `#C4B5FD`, texto `#F8FAFC` e `#A1A1B5`.

Contrastes medidos (WCAG): texto principal 17:1; texto secundário 6,5–7:1; botão primário 5,7:1; placeholder 4,8:1. Todos acima de 4,5:1.

## Acessibilidade e movimento

- Foco visível em todos os controles, navegação completa por teclado, link para pular ao conteúdo.
- Ícones decorativos com `aria-hidden`; botões só com ícone têm `aria-label`.
- `prefers-reduced-motion` desliga animações e transições; `forced-colors` tem fallback.
- Movimento só onde responde a uma ação (menu, modal, toast) ou em uma única entrada de página.

## Como executar

Sem build. Siga o `README.md` (XAMPP: copiar para `htdocs`, importar `create.sql`, abrir `http://localhost/dogs-team-php/`). Se o navegador mostrar o visual antigo, force a atualização (Ctrl+F5); os arquivos já usam `?v=20261102-darkpurple`.

## Validação feita

- `node --check` em todos os `.js`.
- Teste automatizado em Chromium com API simulada: login, cadastro, recuperação de senha, painel, matérias, anotações, calendário, simulados (editor e importação), ranking e correções, em 1280 px e 390 px, como administrador e como estudante. Sem erros de JavaScript e sem rolagem horizontal.
- Cabeçalho verificado sem sobreposição de 820 a 1920 px; menu mobile e modal testados por teclado.

## Limites desta entrega

- Não havia PHP nem MySQL no ambiente de teste: as alterações em `.php` são só de marcação, mas **rode `php -l` e teste login/simulados no seu servidor** antes de publicar.
- Telas dependentes de dados reais (resolução de simulado, resultado, correção de uma tentativa, visualizador de imagens) usam o mesmo CSS testado, mas não foram exercitadas com dados de produção. Vale uma passada manual.
- As fontes vêm do Google Fonts; offline, o sistema usa a fonte padrão do aparelho.
