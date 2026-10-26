<?php

session_start();

require __DIR__ . '/../../vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Api\Controllers\IndexController;
use Api\Controllers\ProdutoController;
use Api\Controllers\CarrinhoController;
use Api\Controllers\CupomController;
use Api\Controllers\OpsController;
use Api\Controllers\PedidoController;
use Slim\Factory\AppFactory;

try {
    \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../api/config')->safeLoad();
    $app = AppFactory::create();
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();
    $errorMiddleware = $app->addErrorMiddleware(true, true, true);

    // Rotas
    $app->get('/', [IndexController::class, 'index']);
    $app->get('/colecao', [IndexController::class, 'index']);
    $app->get('/ajuda', [IndexController::class, 'ajuda']);
    $app->get('/peca/{id:[0-9]+}', [IndexController::class, 'produto']);

    $app->get('/produtos', [ProdutoController::class, 'index']);
    $app->post('/produtos/salvar', [ProdutoController::class, 'salvar']);
    $app->delete('/produtos/excluir/{id}', [ProdutoController::class, 'excluir']);
    $app->post('/produtos/editar/{id}', [ProdutoController::class, 'editar']);
    $app->post('/produtos/restaurar/{id}', [ProdutoController::class, 'restaurar']);


    $app->get('/carrinho', [CarrinhoController::class, 'index']);
    $app->get('/carrinho/estado', [CarrinhoController::class, 'estado']);
    $app->post('/carrinho/atualizar', [CarrinhoController::class, 'atualizar']);
    $app->post('/carrinho/cupom', [CarrinhoController::class, 'aplicarCupom']);
    $app->get('/carrinho/endereco/{cep:[0-9]{8}}', [CarrinhoController::class, 'endereco']);
    $app->post('/carrinho/adicionar', [CarrinhoController::class, 'adicionarAoCarrinho']);
    $app->post('/carrinho/frete', [CarrinhoController::class, 'calcularFrete']);
    $app->post('/carrinho/finalizar', [CarrinhoController::class, 'finalizarPedido']);
    $app->get('/carrinho/retorno', [CarrinhoController::class, 'retorno']);


    $app->get('/cupons', [CupomController::class, 'index']);
    $app->post('/cupons/salvar', [CupomController::class, 'salvar']);
    $app->delete('/cupons/excluir/{id}', [CupomController::class, 'excluir']);
    $app->post('/cupons/editar/{id}', [CupomController::class, 'editar']);
    $app->post('/cupons/restaurar/{id}', [CupomController::class, 'restaurar']);

    $app->get('/pedidos', [PedidoController::class, 'index']);
    $app->post('/pedido/webhook-atualizar-pedido-status', [PedidoController::class, 'webhookAtualizarStatus']);

    $app->get('/ops', [OpsController::class, 'index']);

    $app->run();
} catch (Throwable $e) {
    header('Location: /ops');
    exit;
}
