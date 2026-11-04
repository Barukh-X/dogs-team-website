# Dogs Team — projeto PHP unificado

O front-end e a API agora fazem parte de uma única aplicação PHP e são servidos pelo Apache do XAMPP. Não é necessário iniciar o Live Server do VS Code nem configurar CORS para o uso local normal.

## Requisitos

- XAMPP com Apache, PHP 8+ e MySQL/MariaDB.
- Extensões PHP `pdo_mysql`, `mbstring` e `fileinfo` habilitadas.

## Instalação local no XAMPP

1. Extraia a pasta `dogs-team-php` em `C:\xampp\htdocs\`.
2. Inicie **Apache** e **MySQL** no painel do XAMPP.
3. Abra o phpMyAdmin em `http://localhost/phpmyadmin/`.
4. Importe o arquivo `create.sql`. Ele cria o banco `dogs_team_db` e as tabelas necessárias.
5. Para configurar o ambiente, copie `.env.example` para `.env` e ajuste `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` e `JWT_SECRET`. No XAMPP, os valores padrão do banco são `dogs_team_db`, usuário `root` e senha vazia.
6. Acesse `http://localhost/dogs-team-php/`.

Na hospedagem pública, configure um `JWT_SECRET` aleatório com pelo menos 32 bytes e as credenciais reais do banco no `.env` (ou nas variáveis de ambiente da hospedagem). O sistema recusa o segredo de exemplo e não usa a chave de desenvolvimento em hosts públicos. Para gerar uma chave, execute `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`. Se sua instalação do MySQL tiver senha para `root`, configure-a em `DB_PASS`.

## Criar um administrador

Por segurança, novas contas são registradas como usuários comuns. Crie uma conta pela tela de cadastro e, depois, execute no phpMyAdmin (substituindo `seu_usuario` pelo nome de usuário cadastrado):

```sql
USE dogs_team_db;
UPDATE usuarios SET isadmin = 1 WHERE usuario = 'seu_usuario';
```

Saia da aplicação e entre novamente para receber um token com as permissões atualizadas. Administradores podem cadastrar, editar e excluir matérias e datas importantes. As permissões das anotações são validadas no servidor.

## Páginas

- `index.php` — entrada da aplicação e tela de login na raiz.
- `cadastro.php` — criar conta.
- `dashboard.php` — painel geral.
- `materias.php` — listar, detalhar e administrar matérias.
- `anotacoes.php` — anotações pessoais/públicas e links.
- `calendario.php` — datas importantes e seus anexos de imagem.
- `simulados.php` — criação, **importação de arquivos `.md`** e publicação de simulados (somente administradores), resolução de questões objetivas, discursivas e objetivas com justificativa, resultados e histórico.
- `ranking.php` — ranking de pontuação dos estudantes nos simulados (administradores não participam).
- `correcoes.php` — fila de correção das questões discursivas e com justificativa (somente administradores).
- `recuperar-senha.php` — página informativa; a recuperação real de senha ainda não foi implementada na API.

## Rotas da API integradas

Todas as rotas usam o mesmo domínio/pasta do projeto:

| Método | Rota | Uso |
|---|---|---|
| GET | `/api/health` | Verificar se a API está online |
| POST | `/api/login` | Autenticar e obter token JWT |
| POST | `/api/cadastrar` | Criar conta |
| POST | `/api/logout` | Finalizar o uso do token no navegador |
| GET | `/api/materias` | Listar matérias |
| GET | `/api/materias/detalhes?id=ID` | Consultar uma matéria, suas datas futuras e anotações visíveis |
| POST / PUT / DELETE | `/api/materias` | Criar, editar e excluir matérias |
| GET / POST / PUT / DELETE | `/api/anotacoes` | Consultar e administrar anotações |
| GET / POST / PUT / DELETE | `/api/datas-importantes` | Consultar e administrar datas importantes |
| POST | `/api/simulados/importar` | Admin: envia um `.md`/`.txt` (multipart, campo `arquivo`), valida e devolve o rascunho para pré-visualização. **Não grava nada.** |
| POST | `/api/simulados/importar/confirmar` | Admin: grava o rascunho revisado como simulado em rascunho (transação única) |
| GET | `/api/simulados/ranking` | Ranking de pontuação (aceita `?id_materia=ID`) |
| GET | `/api/simulados/correcoes` | Admin: fila de correção (`?status=aguardando_correcao` ou `concluida`) |
| GET | `/api/simulados/correcoes/tentativa?id_tentativa=ID` | Admin: respostas de uma tentativa que exigem correção |
| POST | `/api/simulados/correcoes/corrigir` | Admin: registra nota e comentário de uma resposta |

As requisições que exigem autenticação devem enviar `Authorization: Bearer <token>`. As operações administrativas são verificadas no back-end, independentemente do que aparece na interface.

## Novos módulos de back-end: imagens e simulados

O back-end inclui rotas para upload autenticado de imagens (anotações, datas importantes, fotos de perfil e questões) e um módulo de simulados com questões objetivas, alternativas, tentativas, correção automática e histórico. O front-end agora integra os módulos de upload e simulados. A aplicação utiliza o tema Dark Purple (veja `REDESIGN_DARK_PURPLE.md`). O cliente não consome a rota `/api/health`; o painel mostra o estado da sessão JWT local.

- Banco novo: importe o `create.sql` atualizado.
- Banco existente (incluindo InfinityFree): faça backup e execute, no banco selecionado pelo phpMyAdmin, `migration_imagens_simulados.sql` (se ainda não aplicada), `migration_tipos_questoes_ranking.sql` (tipos de questão, correção manual e ranking; uma única vez) e `migration_importacao_markdown.sql` (coluna `questoes.explicacao`, usada pela importação de `.md`; uma única vez, **antes** de enviar os arquivos novos). Não reimporte `create.sql` sobre um banco já populado.
- Os uploads ficam em `storage/uploads/`, bloqueados para acesso HTTP direto. O PHP precisa conseguir gravar nessa pasta. Cada imagem pode ter até 5 MiB; são aceitos JPEG, PNG, WebP e GIF.
- Mantenha os arquivos ocultos `.htaccess` ao enviar o projeto: a raiz encaminha `/api/*` para `index.php`, `src/` é bloqueado para acesso HTTP direto e `storage/` protege os uploads.
- O arquivo `BACKEND_NOVAS_FUNCIONALIDADES.md` documenta rotas, payloads, regras de permissão e procedimentos de implantação.

### Simulados: quem cria, tipos de questão e ranking

- **Somente administradores criam, editam, publicam e excluem simulados e questões.** Usuários comuns apenas resolvem simulados publicados. Rascunhos criados antes desta versão por usuários comuns passam a ser gerenciáveis apenas por administradores.
- Tipos de questão: **objetiva** (1 resposta correta, correção automática), **discursiva** e **objetiva com justificativa** (ambas corrigidas manualmente por um administrador em `correcoes.php`).
- Uma tentativa com questões manuais fica **aguardando correção** (sem nota) até que todas sejam corrigidas; só então a nota final é calculada.
- **Ranking:** considera a melhor nota de cada simulado concluído (totalmente corrigido); a pontuação total é a soma dessas notas. Contas de administrador não aparecem no ranking. Cada linha mostra a posição e a foto de perfil; a foto de um estudante só é visível para os demais usuários enquanto ele estiver no ranking.
- **Detalhes e questões:** somente administradores consultam os detalhes de um simulado. Estudantes veem as questões (e suas imagens) apenas depois de iniciar a tentativa.
- **Imagens:** miniaturas abrem em um visualizador em tela cheia com zoom, arrastar, navegação, miniaturas e download.
- **Rodapé:** todas as páginas exibem o contato para sugestões e bugs (`ds.dogsteam@gmail.com`); ao clicar no e-mail, o aplicativo de e-mail abre com a mensagem preenchida.

### Importação de simulados a partir de arquivos Markdown (sem IA)

Na tela **Simulados**, o administrador usa **“Importar de arquivo .md”**, envia o arquivo, revisa a pré-visualização e confirma. O simulado é criado como **rascunho**; a publicação segue o fluxo normal.

- **Arquivo:** `.md` ou `.txt`, UTF-8, até **1 MB** e **200 questões**. O modelo para download está em `modelos/modelo-simulado.md` (botão “Baixar modelo .md”) e traz os três tipos de questão.
- **Front matter:** `materia`, `assunto`, `titulo`, `duracao_minutos` (e `descricao`, opcional). A matéria é associada pelo nome (sem diferenciar maiúsculas/acentos); se não for encontrada, o administrador escolhe uma na revisão. O `assunto` não tem coluna própria e entra no início da descrição.
- **Questões:** cada uma começa com `## Questão N`; alternativas `- a) texto` (2 a 10); `**Gabarito:** b`; `**Pontos:** 1` (padrão 1); `**Explicação:** texto` (opcional, o aluno a vê só depois de finalizar); `**Tipo:** objetiva | discursiva | objetiva_justificativa` (opcional, padrão objetiva). Blocos de código (```` ``` ```` ou `~~~`) e LaTeX (`$...$`, `$$...$$`) são aceitos no enunciado, nas alternativas e na explicação.
- **Validação e revisão obrigatória:** o servidor aponta, por questão, enunciado vazio, gabarito ausente ou inexistente, menos de 2 ou mais de 10 alternativas, mais de uma correta e pontos inválidos. O administrador corrige os campos, remove questões e só então confirma. O botão de confirmação fica bloqueado enquanto houver erro, e o servidor revalida tudo (o cliente não é fonte de verdade).
- **Gravação:** uma única transação cria o simulado, as questões e as alternativas; se algo falhar, nada é gravado. As regras são as mesmas do cadastro manual (`SimuladoService`).
- **Segurança:** somente administradores (`403` para os demais); validação de extensão, tamanho real, conteúdo textual (sem bytes nulos, MIME detectado) e UTF-8; o arquivo **não é movido nem salvo** pela aplicação, apenas lido do arquivo temporário do PHP (descartado ao fim da requisição) e processado em memória; nada do conteúdo é executado (sem `eval`); toda saída é escapada e o HTML digitado no arquivo aparece como texto.
- **Renderização:** enunciados, alternativas e explicações usam `richtext.js` (Markdown simplificado + KaTeX e highlight.js carregados do cdnjs somente quando há fórmulas ou código; se o CDN falhar, o texto continua legível). É usado na pré-visualização, na resolução e no resultado.

## Segurança e configuração

- Não coloque o arquivo `.env` em repositórios públicos; ele está listado no `.gitignore`.
- Configure uma chave `JWT_SECRET` longa e aleatória antes de publicar o projeto fora do ambiente local.
- Para uso em produção, configure HTTPS, revise as permissões de banco e não use as credenciais padrão do XAMPP.
- O token JWT atual expira após uma hora. O logout remove o token do navegador; como a API usa JWT sem estado, o token emitido não é revogado no servidor.

## Observações

- `create.sql` cria um banco novo; se já existir um banco com essas tabelas e dados, faça backup antes de importá-lo. Para bancos existentes, aplique apenas as alterações de esquema necessárias.
- A rota de detalhes dependia de métodos de modelo que não estavam implementados nos arquivos recebidos. Eles foram adicionados nesta versão unificada.

## Fotos nas respostas dos simulados

Veja `FOTOS_NAS_RESPOSTAS.md`. Em bancos existentes, execute `migration_imagens_respostas.sql` uma vez.

## Módulo Batalha Ancestral

1. Faça backup do banco e execute `migration_batalha_ancestral.sql` no banco `dogs_team_db`.
2. Envie os arquivos atualizados para a hospedagem, mantendo a pasta `storage/batalha` gravável pelo PHP.
3. Acesse `batalha.php` com uma conta autenticada. Usuários solicitam participação; administradores aprovam em **Solicitações de participação**.
4. URIHINARI exige que a solicitação escolha uma inscrição: roteiro, figurino ou encenação.

O acesso ao conteúdo e as operações de escrita são verificados no back-end. O histórico de documentos registra cada versão salva com autor, data, resumo e conteúdo integral. Imagens de figurino ficam em `storage/batalha`, servidas por rota autenticada.

## Pré-visualização ao compartilhar o site

O site inclui metadados Open Graph e Twitter para gerar cartões de pré-visualização em aplicativos como WhatsApp, Telegram e redes sociais. A imagem utilizada é `imagens/preview-estudos-computacao.jpg` (1200 × 1200), referenciada por URL absoluta pelo arquivo `social-meta.php`. Esse arquivo é incluído nas páginas públicas.

Ao publicar em outro domínio ou em uma subpasta, não é necessário editar a URL manualmente: o endereço público é montado a partir do host e do caminho da instalação. Para verificar, abra o código-fonte da página inicial e confira `og:image`; a imagem também deve responder publicamente com HTTP 200. Aplicativos de mensagens podem manter prévias antigas em cache por algum tempo.
