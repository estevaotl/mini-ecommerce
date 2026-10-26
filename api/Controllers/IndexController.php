<?php
namespace Api\Controllers;

use Api\Services\Catalogo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class IndexController extends BaseController {
    public function ajuda(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        return $this->render($response, 'loja/ajuda.twig', ['demo' => ($_ENV['APP_DEMO'] ?? 'false') === 'true']);
    }
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $produtos = Catalogo::todos();
        return $this->render($response, 'home.twig', [
            'produtos' => $produtos,
            'categorias' => array_values(array_unique(array_column($produtos, 'categoria'))),
            'colecao' => $request->getUri()->getPath() === '/colecao',
            'pedido' => $_SESSION['pedido'] ?? null,
        ]);
    }

    public function produto(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $produto = Catalogo::obter((int) $args['id']);
        if (!$produto) {
            return $this->render($response->withStatus(404), 'loja/produto.twig', ['produto' => null]);
        }
        return $this->render($response, 'loja/produto.twig', ['produto' => $produto]);
    }
}
