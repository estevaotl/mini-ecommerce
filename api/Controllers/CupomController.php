<?php
namespace Api\Controllers;

use Api\Services\Gestao;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CupomController extends GestaoController {
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        return $this->render($response, 'cupons/home.twig', array_merge(Gestao::cupons(), $this->contexto('cupons')));
    }
    public function salvar(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        return $this->executar($response, fn () => Gestao::salvarCupom($this->dados($request)), 'Cupom cadastrado.');
    }
    public function editar(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        return $this->executar($response, fn () => Gestao::salvarCupom($this->dados($request), (int) $args['id']), 'Alterações salvas.');
    }
    public function excluir(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        return $this->executar($response, fn () => Gestao::situacaoCupom((int) $args['id'], false), 'Cupom desativado. Você pode reativá-lo pela lista.');
    }
    public function restaurar(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        return $this->executar($response, fn () => Gestao::situacaoCupom((int) $args['id'], true), 'Cupom reativado. Confira a validade antes de divulgá-lo.');
    }
}
