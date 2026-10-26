<?php
namespace Api\Controllers;

use Api\Core\Database;
use Api\Services\Sacola;
use Api\Models\Pedido;
use Api\Models\ItemPedido;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

class CarrinhoController extends BaseController {
    private function json(ResponseInterface $response, array $dados, int $status = 200): ResponseInterface {
        $response->getBody()->write(json_encode($dados, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    private function dados(ServerRequestInterface $request): array {
        return $request->getParsedBody() ?: (json_decode((string) $request->getBody(), true) ?: []);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        return $this->render($response, 'carrinho/home.twig', ['sacola' => Sacola::estado(), 'demo' => ($_ENV['APP_DEMO'] ?? 'false') === 'true']);
    }

    public function estado(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        return $this->json($response, Sacola::estado());
    }

    public function adicionarAoCarrinho(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        try {
            $data = $this->dados($request);
            $id = filter_var($data['idItem'] ?? null, FILTER_VALIDATE_INT);
            $q = filter_var($data['quantidade'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || !$q || $q < 1) throw new RuntimeException('Escolha uma peça e uma quantidade válida.');
            Sacola::atualizar($id, Sacola::quantidade($id) + $q);
            return $this->json($response, ['sucesso' => true, 'sacola' => Sacola::estado()]);
        } catch (RuntimeException $e) { return $this->json($response, ['sucesso' => false, 'mensagem' => $e->getMessage()], 400); }
    }

    public function atualizar(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        try {
            $data = $this->dados($request);
            $id = filter_var($data['idItem'] ?? null, FILTER_VALIDATE_INT);
            $q = filter_var($data['quantidade'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $q === false || $q === null) throw new RuntimeException('Informe uma quantidade inteira.');
            Sacola::atualizar($id, $q);
            return $this->json($response, ['sucesso' => true, 'sacola' => Sacola::estado()]);
        } catch (RuntimeException $e) { return $this->json($response, ['sucesso' => false, 'mensagem' => $e->getMessage()], 400); }
    }

    public function aplicarCupom(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        try {
            $codigo = trim((string) ($this->dados($request)['codigo'] ?? ''));
            if ($codigo !== '') Sacola::cupom($codigo, (int) round(Sacola::estado()['subtotal'] * 100));
            $_SESSION['cupom'] = $codigo;
            return $this->json($response, ['sucesso' => true, 'sacola' => Sacola::estado()]);
        } catch (RuntimeException $e) { return $this->json($response, ['sucesso' => false, 'mensagem' => $e->getMessage()], 400); }
    }

    public function calcularFrete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $sacola = Sacola::estado();
        return $this->json($response, ['subtotal' => number_format($sacola['subtotal'], 2, ',', '.'), 'frete' => number_format($sacola['frete'], 2, ',', '.')]);
    }

    public function endereco(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $cep = $args['cep'];
        $ch = curl_init('https://viacep.com.br/ws/' . $cep . '/json/');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 3]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $data = $body ? json_decode($body, true) : null;
        if ($status !== 200 || !$data || isset($data['erro'])) return $this->json($response, ['sucesso' => false, 'mensagem' => 'Não foi possível consultar este CEP. Confira o número e preencha o endereço.'], 400);
        return $this->json($response, ['sucesso' => true, 'logradouro' => $data['logradouro'] ?? '', 'bairro' => $data['bairro'] ?? '', 'cidade' => $data['localidade'] ?? '', 'uf' => $data['uf'] ?? '']);
    }

    public function finalizarPedido(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $pdo = Database::getInstance();
        try {
            $data = $this->dados($request);
            foreach (['nome', 'email', 'cep', 'endereco', 'numero', 'complemento', 'cidade', 'uf'] as $campo) {
                $data[$campo] = is_scalar($data[$campo] ?? null) ? trim((string) $data[$campo]) : '';
            }
            $data['uf'] = strtoupper($data['uf']);
            $cep = preg_replace('/\D/', '', $data['cep']);
            $erros = [];
            if (!preg_match('/^[0-9]{5}-?[0-9]{3}$/', $data['cep'])) $erros['cep'] = 'Informe um CEP com 8 números.';
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $erros['email'] = 'Informe um e-mail válido, como voce@exemplo.com.';
            foreach (['nome' => 'Informe o nome de quem vai receber.', 'endereco' => 'Informe a rua ou avenida.', 'numero' => 'Informe o número ou s/n.', 'cidade' => 'Informe a cidade.'] as $campo => $mensagem) {
                if ($data[$campo] === '') $erros[$campo] = $mensagem;
            }
            if (!in_array($data['uf'], ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'], true)) $erros['uf'] = 'Informe a sigla de um estado brasileiro, como SP.';
            if ($erros) return $this->json($response, ['sucesso' => false, 'mensagem' => 'Confira os campos indicados para continuar.', 'erros' => $erros], 400);
            $sacola = Sacola::estado();
            if (!$sacola['itens']) throw new RuntimeException('Sua sacola está vazia. Escolha uma peça para continuar.');
            $pdo->beginTransaction();
            foreach ($sacola['itens'] as $item) {
                if (!$item['disponivel']) throw new RuntimeException('Revise as peças indisponíveis na sua sacola.');
                $stmt = $pdo->prepare('UPDATE itens_produtos SET estoque = estoque - :q WHERE id = :id AND ativo = 1 AND estoque >= :q');
                $stmt->execute(['q' => $item['quantidade'], 'id' => $item['idItem']]);
                if ($stmt->rowCount() !== 1) throw new RuntimeException('O estoque mudou. Revise sua sacola antes de continuar.');
            }
            $id = (new Pedido())->criar(['valorPedido' => $sacola['total'], 'valorFrete' => $sacola['frete'], 'status' => 'ABERTO', 'idCupom' => $sacola['cupom']['id'] ?? null]);
            foreach ($sacola['itens'] as $item) (new ItemPedido())->criar(['idPedido' => $id, 'quantidade' => $item['quantidade'], 'idItem' => $item['idItem']]);
            $endereco = trim($data['endereco']) . ', ' . trim($data['numero']) . (!empty($data['complemento']) ? ', ' . trim($data['complemento']) : '') . ' — ' . trim($data['cidade']) . '/' . strtoupper($data['uf']) . ' — CEP ' . $cep;
            // Os dados de entrega acompanham o pedido; sem dependência de envio de e-mail.
            $pdo->prepare('INSERT INTO entregas_pedidos (idPedido, nome, email, endereco) VALUES (?, ?, ?, ?)')->execute([$id, trim($data['nome']), trim($data['email']), $endereco]);
            $pdo->commit();
            $_SESSION['resumoPedido'] = array_merge($sacola, ['idPedido' => $id, 'nome' => trim($data['nome']), 'email' => trim($data['email']), 'enderecoFormatado' => $endereco, 'demo' => ($_ENV['APP_DEMO'] ?? 'false') === 'true']);
            $_SESSION['resumoPedido']['emailEnviado'] = \Api\Services\EmailPedido::enviar($_SESSION['resumoPedido']);
            unset($_SESSION['pedido'], $_SESSION['cupom']);
            return $this->json($response, ['sucesso' => true, 'redirect' => '/carrinho/retorno']);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $mensagem = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível registrar o pedido. Tente novamente.';
            return $this->json($response, ['sucesso' => false, 'mensagem' => $mensagem], 400);
        }
    }

    public function retorno(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        return $this->render($response, 'carrinho/retorno.twig', ['resumo' => $_SESSION['resumoPedido'] ?? null]);
    }
}
