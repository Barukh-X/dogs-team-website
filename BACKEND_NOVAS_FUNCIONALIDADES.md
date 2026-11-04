# Dogs Team — Upload de imagens e simulados

Esta versão integra back-end, banco de dados e front-end. O tema visual é dark mode com roxo como cor principal. A interface consome as rotas de imagens e simulados, e não faz requisições para `/api/health`.

## 1. Aplicar a migração no InfinityFree

1. Faça backup do banco existente no phpMyAdmin.
2. Selecione o banco de dados atual do Dogs Team.
3. Abra a aba **SQL** e execute o conteúdo de `migration_imagens_simulados.sql`.
4. Envie os arquivos e pastas do ZIP para a pasta da aplicação, incluindo os arquivos ocultos `.htaccess` da raiz e de `src/`. Preserve a estrutura de diretórios e não sobrescreva o `.env` de produção.
5. Confirme que a pasta `storage/` foi enviada e que o PHP pode gravar em `storage/uploads/`. O arquivo `storage/.htaccess` bloqueia acesso HTTP direto aos uploads.
6. Antes de testar upload no InfinityFree, confira os limites PHP `upload_max_filesize` e `post_max_size`. O sistema aceita até 5 MiB por imagem e até cinco imagens por solicitação; `post_max_size` precisa ser grande o suficiente para o corpo multipart.

Para um banco novo, importe `create.sql`, que contém as tabelas antigas e as novas (já com tipos de questão, correção manual e ranking). Para um banco existente, execute **somente** as migrações, em ordem: `migration_imagens_simulados.sql` (se ainda não aplicada), `migration_tipos_questoes_ranking.sql` (uma única vez; ver seção 6) e `migration_importacao_markdown.sql` (uma única vez; ver seção 8). Não reimporte o script completo, pois ele cria as tabelas antigas sem `IF NOT EXISTS`.

## 2. API de imagens

Todas as rotas exigem `Authorization: Bearer <token>`. Os arquivos são armazenados em `storage/uploads/` e não são servidos diretamente pelo Apache. O endpoint autenticado entrega a imagem após conferir a permissão sobre o recurso.

### Enviar imagens — `POST /api/imagens`

O corpo é `multipart/form-data`:

- `tipo`: `anotacao`, `data_importante`, `perfil` ou `questao`.
- `id_recurso`: ID da anotação, data importante ou questão. Para `perfil`, omita o campo: a imagem é vinculada ao usuário autenticado.
- `imagem`: um arquivo, ou `imagens[]`: até cinco arquivos.

Formatos aceitos: JPEG, PNG, WebP e GIF. Cada imagem pode ter até 5 MiB. O back-end valida o MIME real, a estrutura da imagem, cria um nome aleatório e não confia no nome/na extensão fornecidos pelo navegador.

Permissões: a foto de perfil só pode ser enviada para a própria conta; imagens de datas importantes exigem administrador; imagens de anotação obedecem às permissões da anotação; imagens de questão só podem ser anexadas por administradores, a questões de simulados em rascunho.

Leitura: a foto de perfil pode ser vista pelo próprio dono, por administradores e por qualquer usuário autenticado quando o dono aparece no ranking (não é administrador e tem simulado concluído). Imagens de questões podem ser vistas por administradores e, nos simulados publicados, por quem já iniciou uma tentativa daquele simulado; antes disso a rota responde 404.

### Listar — `GET /api/imagens?tipo=anotacao&id_recurso=123`

Use `tipo` e `id_recurso`. Valores possíveis de `tipo`: `anotacao`, `data_importante`, `perfil` e `questao`. Para listar a foto do próprio perfil, use `GET /api/imagens?tipo=perfil`; `id_recurso` pode ser omitido. A resposta inclui metadados e uma URL da forma `/api/imagens/arquivo?id=ID`.

### Obter o arquivo — `GET /api/imagens/arquivo?id=123`

O endpoint responde com o MIME da imagem e o conteúdo binário, após verificar JWT e permissões. Como a URL exige `Authorization`, ela não deve ser usada diretamente em um elemento `<img>`. O cliente atual usa `fetch` com o token e cria URLs Blob para exibir as imagens protegidas.

### Excluir — `DELETE /api/imagens`

JSON: `{"id": 123}`. O servidor valida a permissão, remove o registro e exclui o arquivo físico. Os arquivos anexados a anotações e datas também são limpos quando esses registros são excluídos pelas rotas existentes.

## 3. API de simulados

Questões podem ser objetivas (alternativa única), discursivas ou objetivas com justificativa (seção 6). A correção objetiva e a nota são calculadas no servidor; as demais são corrigidas por administradores.

| Método | Rota | Uso |
|---|---|---|
| GET | `/api/simulados` | Lista simulados publicados (administradores também veem rascunhos); aceita `?id_materia=ID`. |
| GET | `/api/simulados/detalhes?id=ID` | Detalhes e questões. **Somente administrador** (403 para os demais); o gabarito só é incluído para rascunhos. Estudantes veem as questões apenas depois de iniciar a tentativa (`/api/simulados/tentativas/questoes`). |
| POST | `/api/simulados` | Cria rascunho. **Somente administrador.** |
| PUT | `/api/simulados` | Edita um rascunho ainda sem tentativas. |
| DELETE | `/api/simulados` | Exclui rascunho sem tentativas. JSON: `{"id": 1}`. |
| POST | `/api/simulados/publicar` | Publica após validar questões. JSON: `{"id": 1}`. |
| POST | `/api/simulados/questoes` | Adiciona questão e alternativas. |
| PUT | `/api/simulados/questoes` | Atualiza uma questão do rascunho. |
| DELETE | `/api/simulados/questoes` | Exclui questão de rascunho. JSON: `{"id": 1}`. |
| POST | `/api/simulados/tentativas/iniciar` | Inicia tentativa. JSON: `{"id_simulado": 1}`. |
| GET | `/api/simulados/tentativas/questoes?id_tentativa=ID` | Recupera questões e alternativas sem revelar o gabarito. |
| POST | `/api/simulados/tentativas/responder` | Salva resposta. |
| POST | `/api/simulados/tentativas/finalizar` | Finaliza tentativa e calcula nota. |
| GET | `/api/simulados/tentativas/resultado?id_tentativa=ID` | Consulta resultado próprio; administradores podem consultar resultados de outros usuários. |
| GET | `/api/simulados/tentativas/historico` | Histórico do usuário autenticado. |

### Criar um simulado

`POST /api/simulados` — JSON:

```json
{
  "id_materia": 1,
  "id_data_importante": 2,
  "titulo": "Funções exponenciais",
  "descricao": "Revisão para a prova",
  "duracao_minutos": 60
}
```

`id_data_importante` e `duracao_minutos` são opcionais e podem ser `null`. Se uma data importante tiver matéria associada, ela precisa pertencer à mesma matéria do simulado. Datas sem matéria podem ser associadas a qualquer matéria.

### Adicionar questão

`POST /api/simulados/questoes` — JSON (`tipo` é opcional e assume `objetiva`; veja os demais tipos na seção 6):

```json
{
  "id_simulado": 1,
  "tipo": "objetiva",
  "enunciado": "Quanto vale 2 elevado a 3?",
  "pontos": 1,
  "alternativas": [
    {"texto": "6", "correta": false},
    {"texto": "8", "correta": true},
    {"texto": "9", "correta": false},
    {"texto": "16", "correta": false}
  ]
}
```

`ordem` é opcional e, quando omitida, recebe a próxima posição. Cada questão precisa ter de 2 a 10 alternativas e exatamente uma correta. Pontos precisam ser maiores que zero e podem chegar a 100.

### Responder e finalizar

Após `POST /api/simulados/tentativas/iniciar`, a resposta retorna `id_tentativa`. Para registrar uma escolha:

```json
{
  "id_tentativa": 10,
  "id_questao": 25,
  "id_alternativa": 101
}
```

`id_alternativa` deve pertencer à questão indicada; envie `null` para deixar uma questão sem resposta. Para questões discursivas e com justificativa, veja o payload na seção 6. O cliente nunca envia `correta` nem `nota` como fonte de verdade. Ao finalizar, o serviço soma a pontuação das respostas corretas, divide pela pontuação total e salva a nota percentual de 0 a 100. O limite de tempo é validado usando o horário do banco de dados.

## 4. Permissões e integridade

- **Somente administradores** criam, editam, publicam e excluem simulados, questões e imagens de questões (as demais rotas de gestão respondem `403` para usuários comuns). Rascunhos só são visíveis para administradores.
- Simulados publicados ficam disponíveis para usuários autenticados.
- Questões de simulados publicados não podem ser modificadas pela API, preservando o histórico das tentativas.
- O usuário só pode responder e finalizar as próprias tentativas. O ID de uma tentativa não é suficiente para acessar a de outra pessoa.
- O gabarito não é enviado durante a resolução; ele fica disponível no resultado depois da finalização.
- Exclusão de simulados e questões é restringida para proteger o histórico. As respostas e tentativas não são apagadas em cascata.
- Uploads ficam protegidos contra acesso HTTP direto e são transmitidos apenas após autorização.

## 5. Front-end

- `anotacoes.php` / `anotacoes.js`: listar, enviar, visualizar e excluir imagens de anotações conforme as permissões do usuário.
- `calendario.php` / `calendario.js`: listar, enviar, visualizar e excluir imagens de datas importantes; alterações de imagens exigem administrador.
- `app.js`: foto de perfil em modal e avatar; o arquivo é enviado e lido por endpoints autenticados.
- `simulados.php` / `simulados.js`: criação e edição de rascunhos, adição/edição/exclusão de questões, anexos em questões, publicação, resolução, salvamento de respostas, finalização, resultado e histórico.
- `api.js`: cliente JSON/FormData e carregamento de imagens binárias com JWT.
- `style.css`: identidade visual dark mode, com roxo como cor principal, responsiva para dispositivos móveis.
- `dashboard.js`: resumo das funcionalidades sem chamar `/api/health`.

O front-end não envia a resposta correta nem a nota para a API durante uma tentativa. A correção permanece no servidor. O cliente também carrega imagens protegidas usando `fetch` com JWT e URLs Blob, pois o endpoint de arquivo não é público.

## 6. Tipos de questão, correção manual e ranking

### 6.1 Migração

Bancos existentes: execute `migration_tipos_questoes_ranking.sql` **uma única vez** (depois de `migration_imagens_simulados.sql`). Ela adiciona `questoes.tipo` (questões antigas viram `objetiva`), as colunas de texto/correção em `respostas_tentativa` e a situação `aguardando_correcao` em `tentativas.status`. Nada é apagado. Se o phpMyAdmin responder `Duplicate column name`, a migração já foi aplicada.

### 6.2 Tipos de questão

| `tipo` | Alternativas | Correção |
|---|---|---|
| `objetiva` | 2 a 10, exatamente 1 correta | Automática |
| `discursiva` | nenhuma (enviar `alternativas` é rejeitado) | Manual (administrador) |
| `objetiva_justificativa` | 2 a 10, exatamente 1 correta | Manual (administrador define a nota da questão; o sistema mostra se a alternativa escolhida está correta) |

Criar uma discursiva: `POST /api/simulados/questoes` com `{"id_simulado": 1, "tipo": "discursiva", "enunciado": "Explique…", "pontos": 2}`. Ao publicar, apenas questões com alternativas são validadas quanto ao gabarito.

### 6.3 Responder (`POST /api/simulados/tentativas/responder`)

- `objetiva`: `{"id_tentativa", "id_questao", "id_alternativa"}` (como antes).
- `discursiva`: `{"id_tentativa", "id_questao", "resposta_texto": "..."}` (`null` limpa a resposta; até 20.000 caracteres).
- `objetiva_justificativa`: `{"id_tentativa", "id_questao", "id_alternativa", "justificativa": "..."}`.

`GET /api/simulados/tentativas/questoes` devolve, por questão, `tipo`, `alternativa_selecionada`, `resposta_texto` e `justificativa` do próprio aluno (nunca o gabarito).

### 6.4 Finalização e situação da tentativa

Ao finalizar, respostas manuais totalmente em branco recebem zero automaticamente. Se restar alguma resposta manual preenchida, a tentativa fica `aguardando_correcao`, com `nota = null`. Quando o administrador corrige a última pendência, a tentativa vira `concluida` e a nota (0 a 100) é gravada: soma dos pontos obtidos ÷ soma dos pontos das questões. `GET /api/simulados/tentativas/resultado` passa a funcionar também para tentativas `aguardando_correcao` e traz, por questão, `situacao_correcao` (`automatica`, `pendente` ou `corrigida`), `pontos_obtidos`, `comentario_correcao`, além de `pontos_obtidos`, `pontos_totais` e `pendentes` da tentativa.

### 6.5 Correção (somente administrador; `403` para os demais)

| Método | Rota | Uso |
|---|---|---|
| GET | `/api/simulados/correcoes?status=aguardando_correcao` | Fila de tentativas com respostas pendentes. Use `status=concluida` para tentativas já corrigidas (revisão). |
| GET | `/api/simulados/correcoes/tentativa?id_tentativa=ID` | Estudante e respostas (apenas questões de correção manual) da tentativa. |
| POST | `/api/simulados/correcoes/corrigir` | Registra a correção de uma resposta. |

```json
{ "id_tentativa": 10, "id_questao": 25, "pontos_obtidos": 1.5, "comentario": "Faltou justificar o segundo passo." }
```

`pontos_obtidos` deve estar entre 0 e a pontuação da questão. Uma resposta já corrigida pode ser corrigida novamente: a nota da tentativa é recalculada. A resposta devolve `status`, `nota`, `pendentes`, `pontos_obtidos` e `pontos_totais` da tentativa.

### 6.6 Ranking — `GET /api/simulados/ranking`

Disponível para qualquer usuário autenticado; aceita `?id_materia=ID`. Regras:

- Entram apenas tentativas `concluida` (totalmente corrigidas) de contas **que não são administradoras**.
- Para cada simulado vale a **melhor nota**; refazer um simulado não soma pontos duas vezes.
- `pontuacao_total` = soma das melhores notas; desempate por maior `media`, mais simulados concluídos e ordem alfabética. Empates em pontuação e média compartilham a posição.
- Resposta: `ranking` (até 100 linhas com `posicao`, `nome`, `id_imagem_perfil` — ID da foto de perfil ou `null`, a ser baixada em `/api/imagens/arquivo?id=ID` —, `simulados_concluidos`, `pontuacao_total`, `media`), `total_participantes`, `minha_posicao` (`null` se ainda não pontuou) e `usuario_participa` (`false` para administradores).

### 6.7 Front-end

- `simulados.php` / `simulados.js`: botão e editor de simulados visíveis só para administradores; seletor de tipo no formulário de questões; resolução por tipo (alternativas, campo de resposta, campo de justificativa) com salvamento automático; resultado com situação da correção, pontos e comentário do corretor.
- `correcoes.php` / `correcoes.js` (link "Correções" no menu apenas para administradores): fila, detalhe da tentativa e formulário de nota/comentário por questão.
- `ranking.php` / `ranking.js` (link "Ranking" no menu): sua posição, tabela de classificação e filtro por matéria.

## 7. Visualizador de imagens, foto no ranking e rodapé

- **Visualizador (`app.js`, `DogsApp.openLightbox`)**: ao clicar em uma miniatura (anotações, datas, questões, perfil) abre-se um visualizador em tela cheia com navegação entre as imagens do recurso (botões, setas do teclado ou deslizar), zoom (botões, `+`/`-`/`0`, roda do mouse, pinça e duplo toque/clique), arrastar a imagem ampliada, miniaturas, legenda, download e fechamento por `Esc`, botão ou toque no fundo. As miniaturas têm efeito de carregamento e de destaque ao passar o mouse.
- **Ranking**: cada linha mostra a posição e a foto de perfil (ou as iniciais). As fotos são baixadas sob demanda (somente as linhas visíveis, no máximo três em paralelo) e clicar na foto a abre no visualizador.
- **Rodapé**: `DogsApp.renderFooter()` adiciona em todas as páginas a mensagem "Sugestões ou bugs?" com o e-mail `ds.dogsteam@gmail.com`. Ao clicar, `DogsApp.reportByEmail()` abre o aplicativo de e-mail com destinatário, assunto e um modelo de mensagem (página, endereço e navegador) preenchidos. O link `mailto:` também funciona como alternativa.

## 8. Importação de simulados a partir de Markdown

Somente **administradores** (`403` para os demais). Duas etapas: validar/pré-visualizar (não grava nada) e confirmar (grava em transação).

### 8.1 Migração e implantação (InfinityFree)

1. Backup do banco no phpMyAdmin.
2. Com o banco do Dogs Team selecionado, aba **SQL**, execute `migration_importacao_markdown.sql` **uma única vez** (adiciona `questoes.explicacao`, `MEDIUMTEXT NULL`; nada é apagado). `Duplicate column name 'explicacao'` significa que já foi aplicada. Faça isso **antes** de enviar os arquivos novos, pois as telas de simulado passam a ler essa coluna. Banco novo: o `create.sql` atualizado já a inclui.
3. Envie os arquivos novos/alterados (seção 8.6), preservando os `.htaccess` e sem sobrescrever o `.env`. A pasta `modelos/` precisa ficar pública (é o modelo para download).
4. Confira `upload_max_filesize` e `post_max_size` (≥ 1 MB). Não é necessária nenhuma extensão além das já exigidas (`mbstring`; `fileinfo` é usada se existir). Não há gravação em disco: nenhuma permissão extra em `storage/`.

### 8.2 `POST /api/simulados/importar` (validar)

`multipart/form-data`, campo **`arquivo`** (um único arquivo `.md` ou `.txt`). Verificações: extensão, tamanho real (≤ 1 MiB), conteúdo textual (sem bytes nulos; MIME detectado quando `finfo` existe), UTF-8 (BOM removido) e no máximo 200 questões. Respostas de erro: `400` (envio inválido), `413` (arquivo grande), `422` (extensão, codificação, front matter não fechado, nenhuma questão).

Resposta `200` (resumida):

```json
{
  "sucesso": true,
  "arquivo": {"nome": "modelo-simulado.md", "tamanho": 2048},
  "simulado": {
    "titulo": "Revisão de funções do 1º grau", "assunto": "Funções do 1º grau",
    "descricao": "Assunto: Funções do 1º grau", "duracao_minutos": 60,
    "materia_informada": "Matemática", "id_materia": 1, "materia_encontrada": true
  },
  "pendencias": [],
  "avisos_gerais": [],
  "questoes": [{
    "numero": 1, "linha": 14, "tipo": "objetiva",
    "enunciado": "…", "pontos": 1, "explicacao": "…",
    "alternativas": [{"texto": "5", "correta": false}, {"texto": "7", "correta": true}],
    "gabarito_informado": "b", "avisos": [], "erros": []
  }],
  "totais": {"questoes": 4, "com_erros": 0},
  "limites": {"max_bytes": 1048576, "max_questoes": 200, "min_alternativas": 2, "max_alternativas": 10}
}
```

`erros` lista **todos** os problemas da questão (gabarito ausente/inexistente, menos de 2 ou mais de 10 alternativas, mais de uma correta, alternativa sem texto, pontos inválidos, enunciado vazio). `avisos` são ressalvas da leitura (tipo não reconhecido, linhas ignoradas, letras fora de sequência…). Se a matéria não for encontrada, `id_materia` vem `null` e `pendencias` explica.

### 8.3 `POST /api/simulados/importar/confirmar` (gravar)

JSON com o rascunho **já revisado** (o servidor valida tudo de novo com as regras de `POST /api/simulados` e `POST /api/simulados/questoes`):

```json
{
  "titulo": "Revisão de funções", "id_materia": 1, "descricao": null, "duracao_minutos": 60,
  "questoes": [
    {"tipo": "objetiva", "enunciado": "…", "pontos": 1, "explicacao": null,
     "alternativas": [{"texto": "6", "correta": false}, {"texto": "8", "correta": true}]},
    {"tipo": "discursiva", "enunciado": "…", "pontos": 2, "explicacao": "Resposta esperada…", "alternativas": []}
  ]
}
```

Sucesso: `201` `{"sucesso": true, "id": 12, "total_questoes": 2}` — simulado em **rascunho**, criado com todas as questões e alternativas em uma única transação (falha = nada gravado). A ordem das questões é a ordem do array. Validação: `422` com `erro` e `detalhes: [{"numero": 3, "erros": ["Gabarito ausente…"]}]` (`numero` = posição na lista enviada). O simulado criado não tem data importante associada; ela pode ser definida no editor.

### 8.4 Explicação das questões

`questoes.explicacao` (opcional). O administrador a vê nos detalhes de rascunhos; o aluno só a recebe em `GET /api/simulados/tentativas/resultado`, depois de finalizar a tentativa (nunca durante a resolução). Editar uma questão pelo editor atual preserva a explicação existente (o editor ainda não tem campo para alterá-la).

### 8.5 Front-end

- `simulados.php`: botão “Importar de arquivo .md” e painel de importação.
- `importacao.js`: upload, pré-visualização editável (tipo, pontos, enunciado, alternativas, gabarito, explicação), remoção de questões, validação ao vivo, confirmação e exibição dos erros do servidor por questão. Ao concluir, atualiza a lista e abre o rascunho no editor.
- `richtext.js`: Markdown simplificado, código (highlight.js) e LaTeX (KaTeX) com escape prévio; usado na pré-visualização, na resolução e no resultado.
- `simulados.js`, `api.js` (erros 422 passam a expor `details`) e `style.css` (tema dark roxo) ajustados.
- `modelos/modelo-simulado.md`: modelo para download com os três tipos de questão.

### 8.6 Arquivos novos e alterados

- **Novos:** `src/services/MarkdownSimuladoParser.php`, `src/services/SimuladoImportacaoService.php`, `migration_importacao_markdown.sql`, `modelos/modelo-simulado.md`, `importacao.js`, `richtext.js`.
- **Alterados:** `src/models/Simulado.php`, `src/services/SimuladoService.php`, `src/controllers/SimuladoController.php`, `src/routes/api.php`, `create.sql`, `.htaccess` (bloqueia o download direto da nova migração), `index.php` (lista de assets do servidor embutido do PHP em desenvolvimento), `simulados.php`, `simulados.js`, `api.js`, `style.css`, `README.md`, `BACKEND_NOVAS_FUNCIONALIDADES.md`; e o parâmetro de versão do `style.css` nas demais páginas `.php` (`cadastro`, `calendario`, `anotacoes`, `correcoes`, `dashboard`, `login`, `materias`, `ranking`, `recuperar-senha`), para o navegador baixar o CSS novo.

