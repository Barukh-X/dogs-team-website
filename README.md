# Dogs Team — projeto PHP unificado

O front-end e a API agora fazem parte de uma única aplicação PHP e são servidos pelo Apache do XAMPP. Não é necessário iniciar o Live Server do VS Code nem configurar CORS para o uso local normal.

## Requisitos

- XAMPP com Apache, PHP 8+ e MySQL/MariaDB.
- Extensões PHP `pdo_mysql` e `mbstring` habilitadas.

## Instalação local no XAMPP

1. Extraia a pasta `dogs-team-php` em `C:\xampp\htdocs\`.
2. Inicie **Apache** e **MySQL** no painel do XAMPP.
3. Abra o phpMyAdmin em `http://localhost/phpmyadmin/`.
4. Importe o arquivo `create.sql`. Ele cria o banco `dogs_team_db` e as tabelas necessárias.
5. Opcionalmente, copie `.env.example` para `.env` e ajuste `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` e `JWT_SECRET`. Por padrão, a configuração usa o MySQL local do XAMPP (`dogs_team_db`, usuário `root`, senha vazia).
6. Acesse `http://localhost/dogs-team-php/`.

Se sua instalação do MySQL tiver senha para `root`, configure-a em `DB_PASS` no arquivo `.env`.

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
- `calendario.php` — datas importantes.
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

As requisições que exigem autenticação devem enviar `Authorization: Bearer <token>`. As operações administrativas são verificadas no back-end, independentemente do que aparece na interface.

## Segurança e configuração

- Não coloque o arquivo `.env` em repositórios públicos; ele está listado no `.gitignore`.
- Configure uma chave `JWT_SECRET` longa e aleatória antes de publicar o projeto fora do ambiente local.
- Para uso em produção, configure HTTPS, revise as permissões de banco e não use as credenciais padrão do XAMPP.
- O token JWT atual expira após uma hora. O logout remove o token do navegador; como a API usa JWT sem estado, o token emitido não é revogado no servidor.

## Observações

- `create.sql` cria um banco novo; se já existir um banco com essas tabelas e dados, faça backup antes de importá-lo. Para bancos existentes, aplique apenas as alterações de esquema necessárias.
- A rota de detalhes dependia de métodos de modelo que não estavam implementados nos arquivos recebidos. Eles foram adicionados nesta versão unificada.
