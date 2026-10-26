<?php
// Verifica a compra em banco isolado, sem enviar e-mail ou acessar serviços externos.
require dirname(__DIR__) . '/vendor/autoload.php';
use Api\Core\Database;
use Api\Controllers\CarrinhoController;
use Api\Services\Sacola;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

$file = tempnam(sys_get_temp_dir(), 'nuvix-test-');
$_ENV['DB_DRIVER'] = 'sqlite'; $_ENV['DB_PATH'] = $file; $_ENV['APP_DEMO'] = 'true';
$_SESSION = [];
$pdo = Database::getInstance();
$pdo->exec("CREATE TABLE produtos(id INTEGER PRIMARY KEY, nome TEXT, ativo INTEGER, categoria TEXT);
CREATE TABLE itens_produtos(id INTEGER PRIMARY KEY, idProduto INTEGER, preco REAL, estoque INTEGER, cor TEXT, tamanho TEXT, referencia TEXT, ativo INTEGER);
CREATE TABLE cupons(id INTEGER PRIMARY KEY, referencia TEXT, validade TEXT, valorMinimo REAL, valorDesconto REAL, ativo INTEGER);
CREATE TABLE pedidos(id INTEGER PRIMARY KEY AUTOINCREMENT, valorPedido REAL, valorFrete REAL, dataPedido TEXT, status TEXT, idCupom INTEGER);
CREATE TABLE itens_pedidos(id INTEGER PRIMARY KEY AUTOINCREMENT, idPedido INTEGER, quantidade INTEGER, idItem INTEGER);
CREATE TABLE entregas_pedidos(idPedido INTEGER PRIMARY KEY, nome TEXT, email TEXT, endereco TEXT);
INSERT INTO produtos VALUES(1,'Camiseta teste',1,'Camisetas');
INSERT INTO itens_produtos VALUES(1,1,89.90,3,'Amarelo','M','TEST-M',1),(2,1,49.90,1,'Branco','G','TEST-G',1),(3,1,12,4,'Branco','P','INATIVO',0);
INSERT INTO cupons VALUES(1,'OLA15','2099-12-31',100,15,1),(2,'EXPIRADO','2000-01-01',0,10,1),(3,'INATIVO','2099-12-31',0,10,0);");
$controller = new CarrinhoController();
$count = 0;
function check(bool $condition, string $message): void { global $count; if (!$condition) throw new RuntimeException($message); $count++; }
function callAction(string $method, array $data = []): array { global $controller; $request = (new ServerRequestFactory())->createServerRequest('POST', '/')->withParsedBody($data); $response = $controller->$method($request, new Response()); return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)]; }
try {
    [$status] = callAction('adicionarAoCarrinho', ['idItem' => 99, 'quantidade' => 1]); check($status === 400, 'Variante inexistente precisa ser recusada');
    [$status] = callAction('adicionarAoCarrinho', ['idItem' => 3, 'quantidade' => 1]); check($status === 400, 'Variante desativada precisa ser recusada');
    foreach ([0, -1, 1.5] as $q) { [$status] = callAction('adicionarAoCarrinho', ['idItem' => 1, 'quantidade' => $q]); check($status === 400, 'Quantidade inválida precisa ser recusada'); }
    [$status, $body] = callAction('adicionarAoCarrinho', ['idItem' => 1, 'quantidade' => 1, 'preco' => .01]);
    check($status === 200, 'Adição válida precisa funcionar'); check($body['sacola']['subtotal'] === 89.9, 'Preço adulterado deve ser ignorado'); check($body['sacola']['frete'] === 15, 'Frete da primeira faixa');
    [$status] = callAction('aplicarCupom', ['codigo' => 'OLA15']); check($status === 400, 'Cupom precisa respeitar o mínimo');
    [$status, $body] = callAction('adicionarAoCarrinho', ['idItem' => 1, 'quantidade' => 1]); check($body['sacola']['quantidade'] === 2, 'Mesma variante deve somar quantidades'); check(count($body['sacola']['itens']) === 1, 'Mesma variante não deve duplicar linhas'); check($body['sacola']['frete'] === 20, 'Frete da faixa intermediária');
    [$status] = callAction('adicionarAoCarrinho', ['idItem' => 1, 'quantidade' => 2]); check($status === 400, 'Quantidade acumulada precisa respeitar estoque'); check(Sacola::quantidade(1) === 2, 'Rejeição deve preservar sacola');
    foreach (['EXPIRADO', 'INATIVO', 'INEXISTENTE'] as $code) { [$status] = callAction('aplicarCupom', ['codigo' => $code]); check($status === 400, 'Cupom inválido precisa ser recusado'); }
    [$status, $body] = callAction('aplicarCupom', ['codigo' => 'ola15']); check($status === 200, 'Código do cupom deve ignorar caixa'); check($body['sacola']['desconto'] === 15, 'Desconto do cupom'); check(abs($body['sacola']['total'] - 184.80) < .001, 'Total precisa somar frete e subtrair desconto');
    [$status, $body] = callAction('atualizar', ['idItem' => 1, 'quantidade' => 3]); check($body['sacola']['frete'] === 0, 'Frete grátis acima de R$200');
    [$status, $body] = callAction('atualizar', ['idItem' => 1, 'quantidade' => 1]); check($body['sacola']['cupom'] === null, 'Cupom deve sair ao perder o mínimo');
    [$status] = callAction('atualizar', ['idItem' => 1, 'quantidade' => 1.2]); check($status === 400, 'Atualização deve exigir quantidade inteira');
    [$status, $body] = callAction('atualizar', ['idItem' => 1, 'quantidade' => 0]); check($body['sacola']['quantidade'] === 0, 'Quantidade zero remove item'); check($body['sacola']['total'] === 0, 'Sacola vazia não deve cobrar frete');
    $delivery = ['cep' => '01001000', 'email' => 'teste@example.com', 'nome' => 'Teste local', 'endereco' => 'Praça da Sé', 'numero' => '1', 'cidade' => 'São Paulo', 'uf' => 'SP'];
    [$status] = callAction('finalizarPedido', $delivery); check($status === 400, 'Pedido vazio deve ser recusado');
    callAction('adicionarAoCarrinho', ['idItem' => 1, 'quantidade' => 1]);
    [$status, $body] = callAction('finalizarPedido', array_merge($delivery, ['email' => 'invalido'])); check($status === 400, 'E-mail inválido precisa ser recusado'); check(isset($body['erros']['email']), 'Erro de e-mail precisa indicar o campo');
    [$status, $body] = callAction('finalizarPedido', array_merge($delivery, ['cep' => '123'])); check($status === 400, 'CEP inválido precisa ser recusado'); check(isset($body['erros']['cep']), 'Erro de CEP precisa indicar o campo');
    [$status, $body] = callAction('finalizarPedido', array_merge($delivery, ['nome' => '   ', 'uf' => 'ZZ', 'cidade' => ''])); check($status === 400 && isset($body['erros']['nome'], $body['erros']['uf'], $body['erros']['cidade']), 'Validação precisa devolver todos os campos inválidos');
    check((int) $pdo->query('SELECT COUNT(*) FROM pedidos')->fetchColumn() === 0, 'Dados inválidos não podem registrar pedidos');
    check((int) $pdo->query('SELECT estoque FROM itens_produtos WHERE id=1')->fetchColumn() === 3, 'Dados inválidos não podem reduzir estoque');
    $pdo->exec("ALTER TABLE produtos ADD COLUMN imagem TEXT; ALTER TABLE produtos ADD COLUMN imagem_posicao INTEGER; UPDATE produtos SET imagem='/assets/images/catalogo.webp', imagem_posicao=0 WHERE id=1");
    $gallery = \Api\Services\Catalogo::obter(1)['galeria'];
    check(count(array_filter($gallery, fn ($photo) => $photo['cor'] === 'Branco')) === 2, 'Cada cor de demonstração deve ter frente e costas');
    callAction('adicionarAoCarrinho', ['idItem' => 2, 'quantidade' => 1]);
    $white = array_values(array_filter(Sacola::estado()['itens'], fn ($item) => $item['cor'] === 'Branco'))[0];
    check($white['foto']['cor'] === 'Branco' && $white['foto']['imagem'] === '/assets/images/catalogo-galeria.webp', 'A foto da sacola deve corresponder à cor escolhida');
    callAction('atualizar', ['idItem' => 2, 'quantidade' => 0]);
    callAction('adicionarAoCarrinho', ['idItem' => 2, 'quantidade' => 1]);
    $pdo->exec('UPDATE itens_produtos SET estoque = 0 WHERE id = 2');
    [$status] = callAction('finalizarPedido', $delivery); check($status === 400, 'Mudança de estoque precisa impedir pedido'); check((int) $pdo->query('SELECT estoque FROM itens_produtos WHERE id=1')->fetchColumn() === 3, 'Falha precisa desfazer redução de estoque de outros itens'); check((int) $pdo->query('SELECT COUNT(*) FROM pedidos')->fetchColumn() === 0, 'Falha não pode deixar pedido parcial');
    callAction('atualizar', ['idItem' => 2, 'quantidade' => 0]);
    $delivery['uf'] = 'sp';
    [$status, $body] = callAction('finalizarPedido', $delivery); check($status === 200 && $body['sucesso'], 'Pedido válido precisa ser registrado'); check((int) $pdo->query('SELECT estoque FROM itens_produtos WHERE id=1')->fetchColumn() === 2, 'Pedido precisa reduzir estoque'); check((int) $pdo->query('SELECT COUNT(*) FROM pedidos')->fetchColumn() === 1, 'Pedido deve ser persistido uma única vez'); check((int) $pdo->query('SELECT quantidade FROM itens_pedidos')->fetchColumn() === 1, 'Itens do pedido precisam ser persistidos'); check($pdo->query('SELECT email FROM entregas_pedidos')->fetchColumn() === 'teste@example.com', 'Dados de entrega precisam ser persistidos'); check(Sacola::estado()['quantidade'] === 0, 'Sacola deve ser limpa após pedido'); check($_SESSION['resumoPedido']['idPedido'] === 1, 'Confirmação precisa guardar o pedido da sessão');
    echo "$count verificações aprovadas em banco isolado.\n";
} finally { unlink($file); }
