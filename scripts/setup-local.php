<?php
// Cria somente um banco SQLite de demonstração; não altera um banco MySQL existente.
if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__);
$env = $root . '/api/config/.env';
if (is_file($env) && !str_contains(file_get_contents($env), 'DB_DRIVER=sqlite')) {
    fwrite(STDERR, "Já existe uma configuração de banco. Ela foi preservada.\n");
    exit(1);
}
$path = $root . '/api/database/loja.sqlite';
$pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("PRAGMA foreign_keys = ON;
CREATE TABLE IF NOT EXISTS produtos (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, ativo INTEGER DEFAULT 1, categoria TEXT DEFAULT 'Peças', descricao TEXT, imagem TEXT DEFAULT '', imagem_posicao INTEGER DEFAULT 0);
CREATE TABLE IF NOT EXISTS itens_produtos (id INTEGER PRIMARY KEY AUTOINCREMENT, idProduto INTEGER NOT NULL REFERENCES produtos(id), preco DECIMAL(10,2) DEFAULT 0, estoque INTEGER DEFAULT 0, cor TEXT NOT NULL, tamanho TEXT NOT NULL, referencia TEXT NOT NULL, ativo INTEGER DEFAULT 1);
CREATE TABLE IF NOT EXISTS cupons (id INTEGER PRIMARY KEY AUTOINCREMENT, referencia TEXT NOT NULL, validade TEXT, valorMinimo DECIMAL(10,2) DEFAULT 0, valorDesconto DECIMAL(10,2) DEFAULT 0, ativo INTEGER DEFAULT 1);
CREATE TABLE IF NOT EXISTS pedidos (id INTEGER PRIMARY KEY AUTOINCREMENT, valorPedido DECIMAL(10,2) DEFAULT 0, valorFrete DECIMAL(10,2) DEFAULT 0, dataPedido TEXT DEFAULT CURRENT_TIMESTAMP, status TEXT, idCupom INTEGER REFERENCES cupons(id));
CREATE TABLE IF NOT EXISTS itens_pedidos (id INTEGER PRIMARY KEY AUTOINCREMENT, idPedido INTEGER NOT NULL REFERENCES pedidos(id), quantidade INTEGER DEFAULT 0, idItem INTEGER NOT NULL REFERENCES itens_produtos(id));
CREATE TABLE IF NOT EXISTS entregas_pedidos (idPedido INTEGER PRIMARY KEY REFERENCES pedidos(id), nome VARCHAR(200), email VARCHAR(254), endereco TEXT);");
if ((int) $pdo->query('SELECT COUNT(*) FROM produtos')->fetchColumn() === 0) {
    $dados = [
        ['Camiseta Sol', 'Camisetas', 'Um pequeno sol para acompanhar o dia. Camiseta de algodão com modelagem ampla e estampa autoral.', 89.90, ['Amarelo', 'Branco'], ['P', 'M', 'G', 'GG']],
        ['Camiseta Forma', 'Camisetas', 'Geometria que sai do papel. Uma estampa de formas livres em uma camiseta de algodão com caimento descontraído.', 99.90, ['Branco', 'Azul'], ['P', 'M', 'G', 'GG']],
        ['Moletom Pausa', 'Moletons', 'Para os dias que pedem mais calma. Moletom macio com capuz, bolso frontal e modelagem confortável.', 189.90, ['Lilás', 'Preto'], ['P', 'M', 'G', 'GG']],
        ['Ecobag Maré', 'Acessórios', 'Leve suas ideias com você. Bolsa de lona com alças longas e uma estampa inspirada no movimento da água.', 59.90, ['Natural'], ['Único']],
    ];
    $pdo->beginTransaction();
    foreach ($dados as $pos => [$nome, $categoria, $descricao, $preco, $cores, $tamanhos]) {
        $stmt = $pdo->prepare('INSERT INTO produtos (nome, categoria, descricao, imagem, imagem_posicao) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$nome, $categoria, $descricao, '/assets/images/catalogo.webp', $pos]);
        $id = $pdo->lastInsertId();
        foreach ($cores as $cor) foreach ($tamanhos as $tamanho) {
            $stmt = $pdo->prepare('INSERT INTO itens_produtos (idProduto, preco, estoque, cor, tamanho, referencia) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$id, $preco, 12, $cor, $tamanho, 'NUV-' . $id . '-' . $cor . '-' . $tamanho]);
        }
    }
    $pdo->prepare('INSERT INTO cupons (referencia, validade, valorMinimo, valorDesconto) VALUES (?, ?, ?, ?)')->execute(['OLA15', '2099-12-31 23:59:59', 100, 15]);
    $pdo->commit();
}
if (!is_file($env)) file_put_contents($env, "DB_DRIVER=sqlite\nDB_PATH=api/database/loja.sqlite\nAPP_DEMO=true\nMAIL_ENABLED=false\n");
echo "Banco local pronto. Produtos ilustrativos e cupom OLA15 (R$15 para compras a partir de R$100).\n";
