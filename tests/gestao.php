<?php
// Verifica cadastros e recuperação em um banco temporário, sem alterar a loja.
require dirname(__DIR__) . '/vendor/autoload.php';
use Api\Core\Database;
use Api\Services\Gestao;
use Api\Services\Catalogo;
use Api\Services\ValidacaoGestao;

$file = tempnam(sys_get_temp_dir(), 'nuvix-admin-');
$_ENV['DB_DRIVER'] = 'sqlite'; $_ENV['DB_PATH'] = $file; $_ENV['APP_DEMO'] = 'true'; $_SESSION = [];
$pdo = Database::getInstance();
$pdo->exec("CREATE TABLE produtos(id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, categoria TEXT, descricao TEXT, imagem TEXT, imagem_posicao INTEGER, ativo INTEGER DEFAULT 1);
CREATE TABLE itens_produtos(id INTEGER PRIMARY KEY AUTOINCREMENT, idProduto INTEGER REFERENCES produtos(id), referencia TEXT, preco REAL, estoque INTEGER, tamanho TEXT, cor TEXT, ativo INTEGER DEFAULT 1);
CREATE TABLE cupons(id INTEGER PRIMARY KEY AUTOINCREMENT, referencia TEXT, validade TEXT, valorMinimo REAL, valorDesconto REAL, ativo INTEGER DEFAULT 1);
CREATE TABLE pedidos(id INTEGER PRIMARY KEY AUTOINCREMENT, valorPedido REAL, valorFrete REAL, dataPedido TEXT DEFAULT CURRENT_TIMESTAMP, status TEXT, idCupom INTEGER REFERENCES cupons(id));
CREATE TABLE itens_pedidos(id INTEGER PRIMARY KEY AUTOINCREMENT, idPedido INTEGER REFERENCES pedidos(id), idItem INTEGER REFERENCES itens_produtos(id), quantidade INTEGER);
CREATE TABLE entregas_pedidos(idPedido INTEGER REFERENCES pedidos(id), nome TEXT, email TEXT, endereco TEXT);");
$count = 0;
function checkAdmin(bool $condition, string $message): void { global $count; if (!$condition) throw new RuntimeException($message); $count++; }
function rejected(callable $action, ?string $field = null): void {
    try { $action(); } catch (RuntimeException $e) {
        checkAdmin($field === null || ($e instanceof ValidacaoGestao && isset($e->campos[$field])), 'A rejeição deve identificar o campo esperado.'); return;
    }
    throw new RuntimeException('Uma operação inválida foi aceita.');
}
function variants(int $id): array { global $pdo; $stmt = $pdo->prepare('SELECT * FROM itens_produtos WHERE idProduto = ? AND ativo = 1 ORDER BY id'); $stmt->execute([$id]); return $stmt->fetchAll(PDO::FETCH_ASSOC); }
function payload(array $items): array { return array_map(fn ($item) => ['idItem' => $item['id'], 'referencia' => $item['referencia'], 'cor' => $item['cor'], 'tamanho' => $item['tamanho'], 'preco' => $item['preco'], 'estoque' => $item['estoque']], $items); }
try {
    $item = ['referencia' => 'TEST-AM-P', 'cor' => 'Amarelo', 'tamanho' => 'P', 'preco' => '19.90', 'estoque' => 10];
    $data = ['nome' => 'Peça de teste', 'categoria' => 'Camisetas', 'descricao' => 'Descrição original.', 'imagem' => '/assets/images/catalogo.webp', 'imagem_posicao' => 0, 'itens' => [$item, array_merge($item, ['referencia' => 'TEST-AM-M', 'tamanho' => 'M', 'estoque' => 4])]];
    rejected(fn () => Gestao::salvarProduto(array_merge($data, ['nome' => '   '])), 'nome');
    rejected(fn () => Gestao::salvarProduto(array_merge($data, ['itens' => []])), 'itens');
    rejected(fn () => Gestao::salvarProduto(array_merge($data, ['imagem' => '/assets/images/../fonts/syne.woff2'])), 'imagem');
    rejected(fn () => Gestao::salvarProduto(array_merge($data, ['itens' => [array_merge($item, ['estoque' => 1.5])]])), 'itens.0.estoque');
    rejected(fn () => Gestao::salvarProduto(array_merge($data, ['itens' => [array_merge($item, ['preco' => '-1'])]])), 'itens.0.preco');
    rejected(fn () => Gestao::salvarProduto(array_merge($data, ['itens' => [$item, array_merge($item, ['referencia' => 'OUTRO'])]])), 'itens.1.cor');
    checkAdmin((int) $pdo->query('SELECT COUNT(*) FROM produtos')->fetchColumn() === 0, 'Dados inválidos não podem criar cadastros parciais.');
    $first = Gestao::salvarProduto($data); $firstItems = variants($first);
    checkAdmin(count($firstItems) === 2, 'Cadastro precisa persistir todas as variações.');
    checkAdmin(Catalogo::obter($first)['descricao'] === 'Descrição original.', 'Metadados devem aparecer na loja.');
    $summary = Gestao::produtos()['resumo'];
    checkAdmin($summary['publicados'] === 1 && $summary['variacoes'] === 2 && $summary['unidades'] === 14 && $summary['baixo'] === 1, 'Resumo deve refletir produtos e estoque reais.');
    $second = Gestao::salvarProduto(array_merge($data, ['nome' => 'Outra peça', 'itens' => [array_merge($item, ['referencia' => 'SECOND'])]]));
    $otherItem = variants($second)[0];
    rejected(fn () => Gestao::salvarProduto(array_merge($data, ['nome' => 'Alteração indevida', 'itens' => [array_merge($item, ['idItem' => $otherItem['id']])]]), $first));
    checkAdmin(Catalogo::obter($first)['nome'] === 'Peça de teste', 'Uma variação de outra peça não pode alterar o cadastro.');
    checkAdmin(variants($second)[0]['referencia'] === 'SECOND', 'Uma edição deve preservar as variações de outros produtos.');
    $unchanged = array_merge($data, ['itens' => payload($firstItems)]);
    checkAdmin(Gestao::salvarProduto($unchanged, $first) === $first, 'Salvar sem alterações deve funcionar.');
    rejected(fn () => Gestao::salvarProduto($data, 0));
    rejected(fn () => Gestao::salvarProduto($data, 999));
    $pdo->prepare('INSERT INTO pedidos(valorPedido,valorFrete,status) VALUES(?,?,?)')->execute([34.90,15,'ABERTO']); $order = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO itens_pedidos(idPedido,idItem,quantidade) VALUES(?,?,?)')->execute([$order,$firstItems[0]['id'],1]);
    $pdo->prepare('INSERT INTO entregas_pedidos VALUES(?,?,?,?)')->execute([$order,'Cliente de teste','teste@example.com','Endereço ilustrativo']);
    $edited = array_merge($data, ['nome' => 'Peça atualizada', 'categoria' => 'Autorais', 'descricao' => 'Nova descrição.', 'itens' => [array_merge(payload([$firstItems[1]])[0], ['preco' => '22.35']), array_merge($item, ['referencia' => 'TEST-BR-G', 'cor' => 'Branco', 'tamanho' => 'G', 'estoque' => 0])]]);
    Gestao::salvarProduto($edited, $first); $updated = variants($first);
    checkAdmin(count($updated) === 2 && (float) $updated[0]['preco'] === 22.35, 'Edição deve adicionar variações e atualizar os valores.');
    checkAdmin(Catalogo::obter($first)['categoria'] === 'Autorais', 'Categoria editada deve chegar à loja.');
    checkAdmin((int) $pdo->query('SELECT ativo FROM itens_produtos WHERE id=' . (int) $firstItems[0]['id'])->fetchColumn() === 0, 'Variação retirada deve ser desativada.');
    checkAdmin((int) $pdo->query('SELECT COUNT(*) FROM itens_pedidos')->fetchColumn() === 1, 'Retirar uma variação não pode apagar itens de pedidos.');
    $adminProducts = Gestao::produtos()['produtos'];
    $low = array_values(array_filter($adminProducts, fn ($product) => (int) $product['id'] === $first))[0];
    checkAdmin($low['situacao'] === 'baixo', 'Variações zeradas devem indicar atenção na peça.');
    Gestao::situacaoProduto($first, false);
    checkAdmin(Catalogo::obter($first) === null, 'Produto arquivado deve sair da loja.');
    checkAdmin(count(variants($first)) === 2, 'Arquivar produto deve preservar variações ativas.');
    checkAdmin(Gestao::produtos()['resumo']['publicados'] === 1, 'Resumo deve excluir produtos arquivados.');
    checkAdmin(count(Gestao::pedidos()['pedidos'][0]['itens']) === 1, 'Pedido de produto arquivado deve continuar consultável.');
    Gestao::situacaoProduto($first, true);
    checkAdmin(Catalogo::obter($first) !== null && count(variants($first)) === 2, 'Produto deve voltar à loja pela recuperação.');
    checkAdmin((int) $pdo->query('SELECT ativo FROM itens_produtos WHERE id=' . (int) $firstItems[0]['id'])->fetchColumn() === 0, 'Recuperar produto não deve ressuscitar variações retiradas na edição.');
    Gestao::situacaoProduto($first, true); checkAdmin(count(variants($first)) === 2, 'Recuperação repetida deve ser idempotente.');
    rejected(fn () => Gestao::situacaoProduto(999,false));
    $coupon = ['referencia' => 'ola15', 'valorMinimo' => '100', 'valorDesconto' => '15', 'validade' => '2099-12-31T23:59:59'];
    $couponId = Gestao::salvarCupom($coupon); $saved = Gestao::cupons()['cupons'][0];
    checkAdmin($saved['referencia'] === 'OLA15' && $saved['validade'] === '2099-12-31 23:59:59', 'Código e validade com segundos devem ser preservados.');
    rejected(fn () => Gestao::salvarCupom($coupon), 'referencia');
    rejected(fn () => Gestao::salvarCupom(array_merge($coupon,['referencia'=>'NEGATIVO','valorDesconto'=>-1])), 'valorDesconto');
    rejected(fn () => Gestao::salvarCupom(array_merge($coupon,['referencia'=>'DATA','validade'=>'2099-02-30T12:00'])), 'validade');
    rejected(fn () => Gestao::salvarCupom($coupon,0));
    Gestao::salvarCupom(array_merge($coupon,['validade'=>'']),$couponId);
    checkAdmin(Gestao::cupons()['cupons'][0]['validade'] === null, 'Remover a validade deve deixar o cupom sem prazo final.');
    Gestao::situacaoCupom($couponId,false);
    checkAdmin(Gestao::cupons()['cupons'][0]['situacao'] === 'inativo', 'Cupom desativado deve aparecer na lista correspondente.');
    rejected(fn () => Gestao::salvarCupom($coupon), 'referencia');
    Gestao::situacaoCupom($couponId,true);
    checkAdmin(Gestao::cupons()['resumo']['ativos'] === 1 && Gestao::cupons()['resumo']['sem_prazo'] === 1, 'Reativar cupom deve atualizar os resumos.');
    Gestao::salvarCupom(array_merge($coupon,['validade'=>'2000-01-01T12:00']),$couponId);
    checkAdmin(Gestao::cupons()['cupons'][0]['situacao'] === 'vencido', 'Cupom vencido não deve ser apresentado como válido.');
    $history = Gestao::pedidos();
    checkAdmin($history['resumo']['pedidos'] === 1 && $history['resumo']['abertos'] === 1 && abs($history['resumo']['valor'] - 34.90) < .001, 'Resumo de pedidos deve usar o total registrado.');
    checkAdmin($history['pedidos'][0]['cliente'] === 'Cliente de teste', 'Consulta deve incluir os dados de entrega.');
    echo "$count verificações de gestão aprovadas em banco isolado.\n";
} finally { unlink($file); }
