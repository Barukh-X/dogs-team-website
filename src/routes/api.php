<?php

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/MateriaController.php';
require_once __DIR__ . '/../controllers/AnotacaoController.php';
require_once __DIR__ . '/../controllers/DataImportanteController.php';
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