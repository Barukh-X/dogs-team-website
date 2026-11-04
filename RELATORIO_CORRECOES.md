# Dogs Team — relatório de verificação e correções

## Correções aplicadas

1. **Respostas binárias de imagens:** removida a quebra de linha que precedia `<?php` em `src/config/database.php`. O byte extra contaminava a resposta de imagens e podia impedir a decodificação de JPEG/PNG/WebP/GIF.
2. **Cabeçalho MIME das imagens:** o endpoint agora remove o `Content-Type` JSON do middleware antes de enviar o arquivo binário. O cliente também normaliza valores MIME duplicados, como `image/jpeg, image/jpeg`.
3. **Login e cache:** o login mostra uma mensagem clara se `api.js`/`app.js` não carregar, em vez de falhar com `DogsAPI is not defined`. Os scripts têm uma versão na URL para evitar que o navegador reutilize arquivos antigos após a publicação.
4. **Segredo JWT:** a chave fraca de desenvolvimento é usada somente em ambientes locais. Em hosts públicos, é obrigatório configurar `JWT_SECRET` com pelo menos 32 bytes; o valor de exemplo é rejeitado.
5. **Configuração:** adicionado `.env.example`, e o `.htaccess` bloqueia `.env` e suas variantes contra acesso HTTP direto.
6. **Documentação:** atualizada a explicação do fluxo de imagens.

## Verificações executadas

- Todos os arquivos JavaScript passaram em `node --check`.
- Todos os arquivos PHP passaram em `php -l`.
- Referências locais de scripts e CSS nas páginas PHP foram verificadas.
- O endpoint `GET /api/health` respondeu HTTP 200 com JSON válido começando no primeiro byte, sem saída extra.
- Rotas protegidas sem token responderam HTTP 401; uma rota inexistente respondeu HTTP 404.
- Foi testada a normalização de MIME no cliente JavaScript e a geração/validação local de JWT.
- O pacote não contém declaração `const`/`let`/`var imagem` duplicada nos arquivos JavaScript.

## Limites dos testes

O ambiente de verificação não possui os módulos PHP `pdo_mysql` e `mbstring`, nem um banco MySQL/MariaDB configurado. Por isso, não foi possível executar login/cadastro e operações CRUD contra um banco real, nem simular um upload HTTP completo. A sintaxe, o roteamento inicial e os testes sem banco passaram; a verificação final dessas operações depende das credenciais, extensões PHP e esquema instalados na hospedagem.

A página `recuperar-senha.php` continua sendo informativa: o sistema original não possui fluxo de redefinição de senha nem integração de envio de e-mail. Para disponibilizar essa função é necessário implementar o fluxo de tokens temporários e configurar um serviço de e-mail.
