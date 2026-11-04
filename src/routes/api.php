<?php

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/MateriaController.php';
require_once __DIR__ . '/../controllers/AnotacaoController.php';
require_once __DIR__ . '/../controllers/DataImportanteController.php';
require_once __DIR__ . '/../controllers/ImagemController.php';
require_once __DIR__ . '/../controllers/SimuladoController.php';
require_once __DIR__ . '/Router.php';

$router = new Router();

$router->get('/api/health', function () {
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'API Dogs Team online'
    ]);
});

$router->post('/api/login', [AuthController::class, 'login']);
$router->post('/api/cadastrar', [AuthController::class, 'cadastrar']);
$router->post('/api/logout', [AuthController::class, 'logout']);

$router->get('/api/materias', [MateriaController::class, 'listar']);
$router->get('/api/materias/detalhes', [MateriaController::class, 'detalhes']);
$router->post('/api/materias', [MateriaController::class, 'criar']);
$router->put('/api/materias', [MateriaController::class, 'editar']);
$router->delete('/api/materias', [MateriaController::class, 'excluir']);

$router->get('/api/anotacoes', [AnotacaoController::class, 'listar']);
$router->post('/api/anotacoes', [AnotacaoController::class, 'criar']);
$router->put('/api/anotacoes', [AnotacaoController::class, 'editar']);
$router->delete('/api/anotacoes', [AnotacaoController::class, 'excluir']);

$router->get('/api/datas-importantes', [DataImportanteController::class, 'listar']);
$router->post('/api/datas-importantes', [DataImportanteController::class, 'criar']);
$router->put('/api/datas-importantes', [DataImportanteController::class, 'editar']);
$router->delete('/api/datas-importantes', [DataImportanteController::class, 'excluir']);

// Upload de imagens: os arquivos ficam em storage/uploads, fora do acesso HTTP direto.
$router->get('/api/imagens', [ImagemController::class, 'listar']);
$router->get('/api/imagens/arquivo', [ImagemController::class, 'arquivo']);
$router->post('/api/imagens', [ImagemController::class, 'enviar']);
$router->delete('/api/imagens', [ImagemController::class, 'excluir']);

// CRUD de simulados, questões, tentativas, correção e histórico.
$router->get('/api/simulados', [SimuladoController::class, 'listar']);
$router->get('/api/simulados/detalhes', [SimuladoController::class, 'detalhes']);
$router->post('/api/simulados', [SimuladoController::class, 'criar']);
$router->put('/api/simulados', [SimuladoController::class, 'editar']);
$router->delete('/api/simulados', [SimuladoController::class, 'excluir']);
$router->post('/api/simulados/publicar', [SimuladoController::class, 'publicar']);
$router->post('/api/simulados/questoes', [SimuladoController::class, 'adicionarQuestao']);
$router->put('/api/simulados/questoes', [SimuladoController::class, 'editarQuestao']);
$router->delete('/api/simulados/questoes', [SimuladoController::class, 'excluirQuestao']);
$router->post('/api/simulados/tentativas/iniciar', [SimuladoController::class, 'iniciar']);
$router->get('/api/simulados/tentativas/questoes', [SimuladoController::class, 'questoesTentativa']);
$router->post('/api/simulados/tentativas/responder', [SimuladoController::class, 'responder']);
$router->post('/api/simulados/tentativas/finalizar', [SimuladoController::class, 'finalizar']);
$router->get('/api/simulados/tentativas/resultado', [SimuladoController::class, 'resultado']);
$router->get('/api/simulados/tentativas/historico', [SimuladoController::class, 'historico']);

// Importação de simulados a partir de arquivos Markdown (administradores): validar/pré-visualizar e confirmar.
$router->post('/api/simulados/importar', [SimuladoController::class, 'importar']);
$router->post('/api/simulados/importar/confirmar', [SimuladoController::class, 'confirmarImportacao']);

// Correção manual (administradores) e ranking de pontuação.
$router->get('/api/simulados/correcoes', [SimuladoController::class, 'listarCorrecoes']);
$router->get('/api/simulados/correcoes/tentativa', [SimuladoController::class, 'detalheCorrecao']);
$router->post('/api/simulados/correcoes/corrigir', [SimuladoController::class, 'corrigir']);
$router->get('/api/simulados/ranking', [SimuladoController::class, 'ranking']);

// Batalha Ancestral: participação, permissões por projeto e histórico de documentos.
require_once __DIR__ . '/../controllers/BatalhaController.php';
$router->get('/api/batalha', [BatalhaController::class, 'listar']);
$router->post('/api/batalha/solicitar', [BatalhaController::class, 'solicitar']);
$router->get('/api/batalha/solicitacoes', [BatalhaController::class, 'solicitacoes']);
$router->post('/api/batalha/solicitacoes/analisar', [BatalhaController::class, 'analisar']);
$router->get('/api/batalha/conteudo', [BatalhaController::class, 'conteudo']);
$router->put('/api/batalha/documentos', [BatalhaController::class, 'salvarDocumento']);
$router->get('/api/batalha/historico', [BatalhaController::class, 'historico']);
$router->post('/api/batalha/figurinos', [BatalhaController::class, 'figurinoUpload']);
$router->get('/api/batalha/figurinos/arquivo', [BatalhaController::class, 'arquivoFigurino']);
$router->post('/api/batalha/figurinos/comentarios', [BatalhaController::class, 'comentarioFigurino']);
$router->get('/api/batalha/figurinos/comentarios', [BatalhaController::class, 'comentarios']);
$router->put('/api/batalha/encenacao', [BatalhaController::class, 'salvarEncenacao']);
