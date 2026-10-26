<?php
namespace Api\Controllers;

use Api\Services\Gestao;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ProdutoController extends GestaoController {
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        return $this->render($response, 'produtos/home.twig', array_merge(Gestao::produtos(), $this->contexto('produtos')));
    }
    public function salvar(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        return $this->executar($response, fn () => Gestao::salvarProduto($this->dados($request)), 'Produto cadastrado.');
    }
    public function editar(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        return $this->executar($response, fn () => Gestao::salvarProduto($this->dados($request), (int) $args['id']), 'Alterações salvas.');
    }
    public function excluir(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        return $this->executar($response, fn () => Gestao::situacaoProduto((int) $args['id'], false), 'Produto arquivado. Você pode recuperá-lo em Arquivados.');
    }
    public function restaurar(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        return $this->executar($response, fn () => Gestao::situacaoProduto((int) $args['id'], true), 'Produto recuperado no catálogo. Confira o estoque.');
    }
}
