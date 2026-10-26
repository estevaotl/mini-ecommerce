<?php
namespace Api\Services;

use Api\Core\Database;
use RuntimeException;

class Sacola {
    public static function variante(int $id): ?array {
        $stmt = Database::getInstance()->prepare('SELECT i.*, p.nome, p.ativo AS produtoAtivo FROM itens_produtos i JOIN produtos p ON p.id = i.idProduto WHERE i.id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function quantidade(int $id): int {
        foreach ($_SESSION['pedido']['itensPedido'] ?? [] as $item) {
            if ((int) $item['idItem'] === $id) return (int) $item['quantidade'];
        }
        return 0;
    }

    public static function atualizar(int $id, int $quantidade): void {
        if ($id < 1 || $quantidade < 0) throw new RuntimeException('Escolha uma quantidade válida.');
        $variante = self::variante($id);
        if ($quantidade > 0 && (!$variante || !$variante['ativo'] || !$variante['produtoAtivo'])) {
            throw new RuntimeException('Esta peça não está mais disponível.');
        }
        if ($quantidade > 0 && $quantidade > (int) $variante['estoque']) {
            throw new RuntimeException('Há apenas ' . $variante['estoque'] . ' unidade(s) desta combinação em estoque.');
        }
        $itens = array_values(array_filter($_SESSION['pedido']['itensPedido'] ?? [], fn ($item) => (int) $item['idItem'] !== $id));
        if ($quantidade > 0) $itens[] = ['idItem' => $id, 'quantidade' => $quantidade];
        $_SESSION['pedido'] = ['itensPedido' => $itens];
    }

    public static function cupom(string $codigo, int $subtotal): array {
        $stmt = Database::getInstance()->prepare('SELECT * FROM cupons WHERE UPPER(referencia) = :codigo AND ativo = 1');
        $stmt->execute(['codigo' => strtoupper(trim($codigo))]);
        $cupom = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$cupom) throw new RuntimeException('Cupom não encontrado. Confira o código.');
        if (!empty($cupom['validade']) && strtotime($cupom['validade']) < time()) throw new RuntimeException('Este cupom já venceu.');
        $minimo = (int) round((float) $cupom['valorMinimo'] * 100);
        if ($subtotal < $minimo) throw new RuntimeException('Este cupom vale para compras a partir de R$ ' . number_format($minimo / 100, 2, ',', '.') . '.');
        return $cupom;
    }

    public static function estado(): array {
        $itens = [];
        $subtotal = 0;
        $quantidade = 0;
        foreach ($_SESSION['pedido']['itensPedido'] ?? [] as $item) {
            $v = self::variante((int) $item['idItem']);
            $q = (int) $item['quantidade'];
            $produto = $v ? Catalogo::obter((int) $v['idProduto']) : null;
            $preco = (int) round((float) ($v['preco'] ?? 0) * 100);
            $disponivel = $v && $v['ativo'] && $v['produtoAtivo'] && $q <= (int) $v['estoque'];
            $itens[] = [
                'idItem' => (int) $item['idItem'], 'idProduto' => $v['idProduto'] ?? 0,
                'nome' => $v['nome'] ?? 'Peça indisponível', 'referencia' => $v['referencia'] ?? '',
                'cor' => $v['cor'] ?? '', 'tamanho' => $v['tamanho'] ?? '', 'quantidade' => $q,
                'preco' => $preco / 100, 'total' => $preco * $q / 100,
                'estoque' => (int) ($v['estoque'] ?? 0), 'disponivel' => (bool) $disponivel,
                'imagem' => $produto['imagem'] ?? '', 'imagem_posicao' => $produto['imagem_posicao'] ?? 0,
                'foto' => $produto ? (array_values(array_filter($produto['galeria'], fn ($foto) => $foto['cor'] === ($v['cor'] ?? '')))[0] ?? null) : null,
            ];
            $subtotal += $preco * $q;
            $quantidade += $q;
        }
        $frete = !$itens || $subtotal > 20000 ? 0 : ($subtotal >= 5200 && $subtotal <= 16659 ? 1500 : 2000);
        $cupom = null;
        $desconto = 0;
        if (!empty($_SESSION['cupom'])) {
            try {
                $cupom = self::cupom($_SESSION['cupom'], $subtotal);
                $desconto = min($subtotal, max(0, (int) round((float) $cupom['valorDesconto'] * 100)));
            } catch (RuntimeException $e) { unset($_SESSION['cupom']); }
        }
        return ['itens' => $itens, 'quantidade' => $quantidade, 'subtotal' => $subtotal / 100,
            'frete' => $frete / 100, 'desconto' => $desconto / 100, 'total' => ($subtotal + $frete - $desconto) / 100,
            'cupom' => $cupom ? ['id' => $cupom['id'], 'codigo' => $cupom['referencia']] : null,
            'faltaFreteGratis' => max(0, 20001 - $subtotal) / 100];
    }
}
