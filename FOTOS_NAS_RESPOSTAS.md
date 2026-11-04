# Fotos nas respostas dos simulados

Alunos podem anexar fotos (caderno, folha, quadro) às respostas de questões **discursivas** e **objetivas com justificativa**. O corretor vê as fotos na tela de correção e o aluno as revê no resultado.

## Banco de dados (obrigatório)

Em bancos existentes, execute **uma vez** no phpMyAdmin (com o banco selecionado e após backup):

```
migration_imagens_respostas.sql
```

Ela adiciona `imagens.id_resposta` (FK para `respostas_tentativa`, `ON DELETE CASCADE`). Instalações novas já recebem a coluna pelo `create.sql`.

## Regras

| Ação | Quem pode | Condição |
|---|---|---|
| Enviar / excluir foto | O dono da tentativa | Tentativa `em_andamento`, tempo não esgotado, questão discursiva ou objetiva com justificativa |
| Ver foto | O dono e administradores | Sempre (inclusive depois de finalizar) |
| Limite | — | 5 fotos por resposta, 5 MB cada, JPEG/PNG/WebP/GIF (mesma validação das demais imagens) |

Depois de finalizar, as fotos ficam travadas, para o corretor avaliar exatamente o que foi entregue. Outros alunos nunca acessam fotos de respostas alheias.

## Como funciona

- **API**: reaproveita `POST/GET/DELETE /api/imagens` com `tipo=resposta` e `id_recurso=<id da resposta>`. Nenhum endpoint novo.
- `POST /api/simulados/tentativas/responder` passa a devolver também `id_resposta`. O front salva a resposta atual e usa esse id para anexar a foto (por isso dá para enviar foto antes de digitar texto).
- Respostas e resultados trazem `id_resposta` e `quantidade_imagens` por questão.
- **Finalização**: uma resposta que tem só fotos (sem texto) **não** é tratada como "em branco"; ela vai para a fila de correção em vez de receber nota zero automática.

## Arquivos alterados

`src/models/Imagem.php`, `src/controllers/ImagemController.php`, `src/services/UploadImagemService.php`, `src/models/Simulado.php`, `src/services/SimuladoService.php`, `src/controllers/SimuladoController.php`, `create.sql`, `app.js` (gerenciador de imagens), `simulados.js`, `correcoes.js`, `style.css`.

## Testes

Teste automatizado com API simulada (sem PHP/MySQL no ambiente): salvar resposta → anexar fotos → finalizar → resultado → correção, incluindo ordem das chamadas, somente leitura no resultado e na correção, e abertura do visualizador. **Falta validar no seu servidor com MySQL**, principalmente a migração e o `LAST_INSERT_ID(id)` do `salvarResposta`.
