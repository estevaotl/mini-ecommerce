<?php
namespace Api\Services;

use Api\Core\Database;
use PDO;
use RuntimeException;

class Gestao {
    private static function texto(mixed $valor): string {
        return is_scalar($valor) ? trim((string) $valor) : '';
    }

    private static function tamanho(string $texto): int {
        return preg_match_all('/./us', $texto);
    }

    private static function dinheiro(mixed $valor): ?float {
        $texto = self::texto($valor);
        return preg_match('/^\d{1,8}(\.\d{1,2})?$/', $texto) ? (float) $texto : null;
    }

    public static function produtos(): array {
        $pdo = Database::getInstance();
        $produtos = $pdo->query('SELECT * FROM produtos ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare('SELECT * FROM itens_produtos WHERE idProduto = ? AND ativo = 1 ORDER BY id');
        $resumo = ['publicados' => 0, 'variacoes' => 0, 'unidades' => 0, 'baixo' => 0];
        $categorias = [];
        foreach ($produtos as &$produto) {
            $stmt->execute([$produto['id']]);
            $produto['itens'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $produto['ativo'] = (bool) $produto['ativo'];
            $produto['categoria'] = $produto['categoria'] ?: 'Peças';
            $produto['estoque_total'] = array_sum(array_column($produto['itens'], 'estoque'));
            $produto['preco_min'] = $produto['itens'] ? min(array_column($produto['itens'], 'preco')) : 0;
            $produto['preco_max'] = $produto['itens'] ? max(array_column($produto['itens'], 'preco')) : 0;
            $produto['baixo'] = (bool) array_filter($produto['itens'], fn ($item) => $item['estoque'] <= 5);
            $produto['situacao'] = !$produto['ativo'] ? 'arquivado' : (!$produto['estoque_total'] ? 'esgotado' : ($produto['baixo'] ? 'baixo' : 'disponivel'));
            $produto['imagem'] = preg_match('#^/assets/images/[a-zA-Z0-9._/-]+$#', $produto['imagem'] ?? '') ? $produto['imagem'] : '';
            $produto['imagem_posicao'] = max(0, min(3, (int) ($produto['imagem_posicao'] ?? 0)));
            $categorias[] = $produto['categoria'];
            if ($produto['ativo']) {
                $resumo['publicados']++;
                $resumo['variacoes'] += count($produto['itens']);
                $resumo['unidades'] += $produto['estoque_total'];
                $resumo['baixo'] += count(array_filter($produto['itens'], fn ($item) => $item['estoque'] <= 5));
            }
        }
        unset($produto);
        return ['produtos' => $produtos, 'resumo' => $resumo, 'categorias' => array_values(array_unique($categorias))];
    }

    public static function salvarProduto(array $dados, ?int $id = null): int {
        if ($id !== null && $id < 1) throw new RuntimeException('Produto não encontrado.');
        $pdo = Database::getInstance();
        $erros = [];
        $nome = self::texto($dados['nome'] ?? '');
        $categoria = self::texto($dados['categoria'] ?? 'Peças');
        $descricao = self::texto($dados['descricao'] ?? '');
        if ($nome === '' || self::tamanho($nome) > 500) $erros['nome'] = 'Informe um nome com até 500 caracteres.';
        if ($categoria === '' || self::tamanho($categoria) > 100) $erros['categoria'] = 'Informe uma categoria com até 100 caracteres.';
        if (self::tamanho($descricao) > 5000) $erros['descricao'] = 'Use até 5.000 caracteres na descrição.';
        $imagem = self::texto($dados['imagem'] ?? '');
        $posicao = filter_var($dados['imagem_posicao'] ?? 0, FILTER_VALIDATE_INT);
        if ($imagem !== '' && (!preg_match('#^/assets/images/[a-zA-Z0-9._/-]+$#', $imagem) || str_contains($imagem, '..') || !is_file(dirname(__DIR__, 2) . '/app/public' . $imagem))) $erros['imagem'] = 'Escolha uma imagem disponível na aplicação.';
        if ($posicao === false || $posicao < 0 || $posicao > 3) $erros['imagem'] = 'Escolha uma imagem válida.';
        $itens = $dados['itens'] ?? [];
        if (!is_array($itens) || !array_is_list($itens) || count($itens) < 1 || count($itens) > 100) $erros['itens'] = 'Adicione de 1 a 100 variações.';
        $normalizados = []; $combinacoes = []; $referencias = []; $ids = [];
        if (is_array($itens) && count($itens) <= 100) foreach ($itens as $index => $item) {
            if (!is_array($item)) { $erros['itens'] = 'Confira as variações.'; continue; }
            $row = [];
            foreach (['referencia', 'cor', 'tamanho'] as $campo) {
                $row[$campo] = self::texto($item[$campo] ?? '');
                if ($row[$campo] === '' || self::tamanho($row[$campo]) > 200) $erros["itens.$index.$campo"] = 'Preencha este campo com até 200 caracteres.';
            }
            $row['preco'] = self::dinheiro($item['preco'] ?? null);
            $row['estoque'] = filter_var($item['estoque'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
            if ($row['preco'] === null) $erros["itens.$index.preco"] = 'Informe um preço válido, com até duas casas decimais.';
            if ($row['estoque'] === false) $erros["itens.$index.estoque"] = 'Informe uma quantidade inteira a partir de zero.';
            $row['idItem'] = isset($item['idItem']) && $item['idItem'] !== '' ? filter_var($item['idItem'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
            if ($row['idItem'] === false || (!$id && $row['idItem']) || ($row['idItem'] && in_array($row['idItem'], $ids, true))) $erros['itens'] = 'Confira a identificação das variações.';
            if ($row['idItem']) $ids[] = $row['idItem'];
            $chave = strtolower($row['cor'] . '|' . $row['tamanho']);
            $referencia = strtolower($row['referencia']);
            if (in_array($chave, $combinacoes, true)) $erros["itens.$index.cor"] = 'Esta combinação de cor e tamanho já existe.';
            if (in_array($referencia, $referencias, true)) $erros["itens.$index.referencia"] = 'Use uma referência diferente para cada variação.';
            $combinacoes[] = $chave; $referencias[] = $referencia; $normalizados[] = $row;
        }
        if ($erros) throw new ValidacaoGestao($erros);
        $pdo->beginTransaction();
        try {
            $existentes = [];
            if ($id) {
                $stmt = $pdo->prepare('SELECT id FROM produtos WHERE id = ? AND ativo = 1'); $stmt->execute([$id]);
                if (!$stmt->fetchColumn()) throw new RuntimeException('Produto não encontrado. Atualize a lista e tente novamente.');
                $stmt = $pdo->prepare('SELECT id FROM itens_produtos WHERE idProduto = ? AND ativo = 1'); $stmt->execute([$id]);
                $existentes = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                foreach ($ids as $itemId) if (!in_array($itemId, $existentes, true)) throw new RuntimeException('Uma variação não pertence a este produto. Abra o cadastro novamente.');
                $stmt = $pdo->prepare('UPDATE produtos SET nome = ?, categoria = ?, descricao = ?, imagem = ?, imagem_posicao = ? WHERE id = ?');
                $stmt->execute([$nome, $categoria, $descricao, $imagem, $posicao, $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO produtos (nome, categoria, descricao, imagem, imagem_posicao) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$nome, $categoria, $descricao, $imagem, $posicao]); $id = (int) $pdo->lastInsertId();
            }
            foreach ($normalizados as $item) {
                $valores = [$item['referencia'], $item['preco'], $item['estoque'], $item['tamanho'], $item['cor']];
                if ($item['idItem']) {
                    $stmt = $pdo->prepare('UPDATE itens_produtos SET referencia = ?, preco = ?, estoque = ?, tamanho = ?, cor = ? WHERE id = ? AND idProduto = ?');
                    $stmt->execute([...$valores, $item['idItem'], $id]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO itens_produtos (referencia, preco, estoque, tamanho, cor, idProduto) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([...$valores, $id]);
                }
            }
            foreach (array_diff($existentes, $ids) as $itemId) {
                $pdo->prepare('UPDATE itens_produtos SET ativo = 0 WHERE id = ? AND idProduto = ?')->execute([$itemId, $id]);
            }
            $pdo->commit(); return $id;
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }

    public static function situacaoProduto(int $id, bool $ativo): void {
        $pdo = Database::getInstance(); $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id FROM produtos WHERE id = ?'); $stmt->execute([$id]);
            if (!$stmt->fetchColumn()) throw new RuntimeException('Produto não encontrado.');
            $pdo->prepare('UPDATE produtos SET ativo = ? WHERE id = ?')->execute([(int) $ativo, $id]);
            if ($ativo) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM itens_produtos WHERE idProduto = ? AND ativo = 1'); $stmt->execute([$id]);
                // Recupera produtos arquivados pelo fluxo antigo, que desativava todos os itens.
                if (!(int) $stmt->fetchColumn()) $pdo->prepare('UPDATE itens_produtos SET ativo = 1 WHERE idProduto = ?')->execute([$id]);
            }
            $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }

    public static function cupons(): array {
        $cupons = Database::getInstance()->query('SELECT * FROM cupons ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        $resumo = ['ativos' => 0, 'vencidos' => 0, 'sem_prazo' => 0];
        foreach ($cupons as &$cupom) {
            $cupom['situacao'] = !$cupom['ativo'] ? 'inativo' : (!empty($cupom['validade']) && strtotime($cupom['validade']) < time() ? 'vencido' : 'ativo');
            if ($cupom['situacao'] === 'ativo') { $resumo['ativos']++; if (!$cupom['validade']) $resumo['sem_prazo']++; }
            if ($cupom['situacao'] === 'vencido') $resumo['vencidos']++;
        }
        unset($cupom);
        return ['cupons' => $cupons, 'resumo' => $resumo];
    }

    public static function salvarCupom(array $dados, ?int $id = null): int {
        if ($id !== null && $id < 1) throw new RuntimeException('Cupom não encontrado.');
        $pdo = Database::getInstance(); $erros = [];
        $referencia = strtoupper(self::texto($dados['referencia'] ?? ''));
        if (!preg_match('/^[A-Z0-9_-]{1,100}$/', $referencia)) $erros['referencia'] = 'Use letras, números, hífen ou sublinhado no código.';
        $minimo = self::dinheiro($dados['valorMinimo'] ?? null); $desconto = self::dinheiro($dados['valorDesconto'] ?? null);
        if ($minimo === null) $erros['valorMinimo'] = 'Informe um valor mínimo válido, a partir de zero.';
        if ($desconto === null || $desconto <= 0) $erros['valorDesconto'] = 'Informe um desconto maior que zero.';
        $validade = self::texto($dados['validade'] ?? '');
        if ($validade) {
            $formato = strlen($validade) === 19 ? 'Y-m-d\TH:i:s' : 'Y-m-d\TH:i';
            $data = \DateTimeImmutable::createFromFormat('!' . $formato, $validade);
            if (!$data || $data->format($formato) !== $validade) $erros['validade'] = 'Informe uma data e horário válidos.';
            else $validade = $data->format('Y-m-d H:i:s');
        }
        $stmt = $pdo->prepare('SELECT id FROM cupons WHERE UPPER(referencia) = ? AND id <> ?'); $stmt->execute([$referencia, $id ?? 0]);
        if ($stmt->fetchColumn()) $erros['referencia'] = 'Este código já está cadastrado. Use outro código.';
        if ($erros) throw new ValidacaoGestao($erros);
        if ($id) {
            $stmt = $pdo->prepare('SELECT id FROM cupons WHERE id = ?'); $stmt->execute([$id]);
            if (!$stmt->fetchColumn()) throw new RuntimeException('Cupom não encontrado.');
            $stmt = $pdo->prepare('UPDATE cupons SET referencia = ?, validade = ?, valorMinimo = ?, valorDesconto = ? WHERE id = ?');
            $stmt->execute([$referencia, $validade ?: null, $minimo, $desconto, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO cupons (referencia, validade, valorMinimo, valorDesconto) VALUES (?, ?, ?, ?)');
            $stmt->execute([$referencia, $validade ?: null, $minimo, $desconto]); $id = (int) $pdo->lastInsertId();
        }
        return $id;
    }

    public static function situacaoCupom(int $id, bool $ativo): void {
        $pdo = Database::getInstance(); $stmt = $pdo->prepare('SELECT id FROM cupons WHERE id = ?'); $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Cupom não encontrado.');
        $pdo->prepare('UPDATE cupons SET ativo = ? WHERE id = ?')->execute([(int) $ativo, $id]);
    }

    public static function pedidos(): array {
        $pdo = Database::getInstance();
        $pedidos = $pdo->query('SELECT p.*, c.referencia AS codigoCupom, d.nome AS cliente, d.email, d.endereco FROM pedidos p LEFT JOIN cupons c ON c.id = p.idCupom LEFT JOIN entregas_pedidos d ON d.idPedido = p.id ORDER BY p.id DESC')->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare('SELECT ip.quantidade, i.referencia, i.cor, i.tamanho, i.ativo AS itemAtivo, p.nome AS nomeProduto, p.ativo AS produtoAtivo FROM itens_pedidos ip LEFT JOIN itens_produtos i ON i.id = ip.idItem LEFT JOIN produtos p ON p.id = i.idProduto WHERE ip.idPedido = ? ORDER BY ip.id');
        $resumo = ['pedidos' => count($pedidos), 'abertos' => 0, 'valor' => 0];
        $status = [];
        foreach ($pedidos as &$pedido) {
            $stmt->execute([$pedido['id']]); $pedido['itens'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $pedido['quantidade'] = array_sum(array_column($pedido['itens'], 'quantidade'));
            $pedido['status'] = strtoupper($pedido['status'] ?: 'ABERTO');
            $resumo['abertos'] += $pedido['status'] === 'ABERTO' ? 1 : 0;
            $resumo['valor'] += (float) $pedido['valorPedido'];
            $status[] = $pedido['status'];
        }
        unset($pedido);
        return ['pedidos' => $pedidos, 'resumo' => $resumo, 'situacoes' => array_values(array_unique($status))];
    }
}
