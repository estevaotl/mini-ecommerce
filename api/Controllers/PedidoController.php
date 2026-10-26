<?php

namespace Api\Controllers;

use Api\Models\ItemProduto;
use Api\Models\Produto;
use Api\Controllers\BaseController;
use Api\Models\ItemPedido;
use Api\Models\Pedido;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PedidoController extends BaseController {
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        return $this->render($response, 'pedidos/home.twig', array_merge(\Api\Services\Gestao::pedidos(), [
            'pagina' => 'pedidos',
            'demo' => ($_ENV['APP_DEMO'] ?? 'false') === 'true',
        ]));
    }

    public function webhookAtualizarStatus(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        try {
            $data = $request->getParsedBody();
            $idPedido = $data["idPedido"];
            if (!isset($idPedido) || !is_numeric($idPedido)) {
                throw new Exception("Pedido enviado através do WebHook é inválido.");
            }

            $pedidoModel = new Pedido();
            $pedidoBanco = $pedidoModel->obterComRestricoes(array("id" => $idPedido));
            if (empty($pedidoBanco)) {
                throw new Exception("Pedido enviado através do WebHook é inválido.");
            }

            $status = $data["status"];
            if (empty($status)) {
                throw new Exception("Status enviado através do WebHook é inválido.");
            }

            $pedidoModel->atualizarStatus($idPedido, $status);

            $response->getBody()->write(json_encode([
                'sucesso' => true
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $th) {
            $response->getBody()->write(json_encode([
                'sucesso'  => false,
                'mensagem' => $th->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }
}
