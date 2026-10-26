<?php
namespace Api\Controllers;

use Api\Services\ValidacaoGestao;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class GestaoController extends BaseController {
    protected function contexto(string $pagina): array {
        $feedback = $_SESSION['gestao_feedback'] ?? null;
        unset($_SESSION['gestao_feedback']);
        return ['pagina' => $pagina, 'demo' => ($_ENV['APP_DEMO'] ?? 'false') === 'true', 'feedback' => $feedback];
    }
    protected function dados(ServerRequestInterface $request): array {
        $dados = $request->getParsedBody();
        if (!is_array($dados)) $dados = json_decode((string) $request->getBody(), true);
        return is_array($dados) ? $dados : [];
    }
    protected function executar(ResponseInterface $response, callable $acao, string $mensagem): ResponseInterface {
        try {
            $id = $acao(); $_SESSION['gestao_feedback'] = $mensagem;
            $dados = ['sucesso' => true, 'mensagem' => $mensagem, 'id' => $id]; $status = 200;
        } catch (\Throwable $e) {
            $esperado = $e instanceof \RuntimeException && !$e instanceof \PDOException;
            $dados = ['sucesso' => false, 'mensagem' => $esperado ? $e->getMessage() : 'Não foi possível salvar. Tente novamente.'];
            if ($e instanceof ValidacaoGestao) $dados['erros'] = $e->campos;
            $status = $esperado ? 400 : 500;
        }
        $response->getBody()->write(json_encode($dados, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
