# Dogs Team — front-end dark mode com integração da API

## O que foi alterado

- Tema dark mode com roxo como cor principal, incluindo formulários, navegação, cartões, modais, estados de carregamento e telas de simulados.
- Autenticação, cadastro e logout conectados à API atual.
- Gestão e consulta de matérias, anotações e datas importantes.
- Upload/listagem/visualização/exclusão de imagens nas anotações, datas importantes, perfil e questões de simulados, conforme as permissões permitidas pela API.
- Catálogo, criação/edição/publicação/exclusão de simulados, gestão de questões, resolução de tentativas, gravação de respostas, finalização, resultados e histórico.
- O painel não chama `/api/health`; o cliente JavaScript não contém referência a essa rota.
- O front-end chama as 39 rotas da API diferentes de `/api/health` declaradas em `src/routes/api.php` (35 anteriores + ranking e 3 rotas de correção).

## Instalação

1. Faça backup do projeto e do banco de dados.
2. Extraia o ZIP e envie/substitua os arquivos do projeto, preservando os arquivos ocultos `.htaccess`.
3. Se ainda não aplicou as alterações do backend de imagens e simulados, execute `migration_imagens_simulados.sql` no banco existente pelo phpMyAdmin. Não reimporte `create.sql` sobre um banco de produção já populado.
4. Confira as variáveis de configuração e a capacidade de escrita na pasta `storage/uploads/`.
5. Entre na aplicação e valide os fluxos com uma conta normal e uma conta administradora.

## Validações executadas

- Sintaxe JavaScript (`node --check`) nos scripts do projeto.
- Sintaxe PHP (`php -l`) em todos os arquivos PHP.
- Verificação estática de cobertura: todas as 39 rotas diferentes de `/api/health` constam no código JavaScript do cliente.

Não foi possível executar teste ponta a ponta contra o banco de dados/hospedagem nesta sessão. A integração real deve ser validada depois de instalar e aplicar a migração.

## Atualização: simulados só para administradores, novos tipos de questão, correção e ranking

Validações desta atualização: `node --check` em todos os scripts JS; cobertura estática das 39 rotas no cliente; conferência estrutural (balanceamento de blocos/strings) dos arquivos PHP alterados; e execução, em SQLite com dados de teste, das consultas SQL de pontuação, fila de correção e ranking extraídas do código (melhor tentativa por simulado, pendentes fora do ranking, administradores excluídos, filtro por matéria).

Não foi possível executar `php -l` nem testar contra MySQL/MariaDB nesta sessão. Depois de aplicar `migration_tipos_questoes_ranking.sql`, valide com uma conta administradora e uma comum: criar simulado com os três tipos, publicar, responder, finalizar, corrigir em `correcoes.php` e conferir o ranking.

### Visualização de imagens, ranking com foto e rodapé

`app.js` ganhou o visualizador de imagens (`DogsApp.openLightbox`), a galeria com efeitos de carregamento/hover, `DogsApp.imageObjectUrl` (download único e reutilizável de imagens autenticadas) e o rodapé com `DogsApp.reportByEmail`. `ranking.js` mostra a foto de perfil junto à posição, carregada sob demanda. `simulados.js` oculta o botão "Detalhes" para quem não é administrador. Foram validados apenas a sintaxe (`node --check`) e a consulta do ranking em SQLite; o comportamento visual (gestos, zoom, layout) precisa ser conferido no navegador, em computador e celular.
