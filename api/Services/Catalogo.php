<?php
namespace Api\Services;

use Api\Models\Produto;
use Api\Models\ItemProduto;

class Catalogo {
    private const CORES = ['Amarelo' => '#edda83', 'Branco' => '#f6f4eb', 'Preto' => '#24303c', 'Azul' => '#3552b7', 'Lilás' => '#b7a4cc', 'Natural' => '#e0d8c5', 'Vermelho' => '#c54140'];

    public static function todos(): array {
        $produtos = (new Produto())->obterTodos() ?? [];
        foreach ($produtos as &$produto) {
            $produto = self::preparar($produto);
        }
        return $produtos;
    }

    public static function obter(int $id): ?array {
        $produto = (new Produto())->obterComRestricoes(['id' => $id]);
        return $produto ? self::preparar($produto[0]) : null;
    }

    private static function preparar(array $produto): array {
        $itens = (new ItemProduto())->obterComRestricoes(['idProduto' => $produto['id']]) ?? [];
        foreach ($itens as &$item) {
            $item['preco'] = (float) $item['preco'];
            $item['estoque'] = (int) $item['estoque'];
            $item['hex'] = self::CORES[$item['cor']] ?? '#d3d9df';
        }
        unset($item);
        $disponiveis = array_filter($itens, fn ($item) => $item['estoque'] > 0);
        $produto['itens'] = $itens;
        $produto['preco'] = $itens ? min(array_column($disponiveis ?: $itens, 'preco')) : 0;
        $produto['disponivel'] = (bool) $disponiveis;
        $produto['cores'] = array_values(array_unique(array_column($itens, 'cor')));
        $produto['cores_vitrine'] = [];
        foreach ($produto['cores'] as $cor) {
            $variantes = array_values(array_filter($itens, fn ($item) => $item['cor'] === $cor));
            $produto['cores_vitrine'][] = [
                'nome' => $cor,
                'hex' => $variantes[0]['hex'],
                'disponivel' => (bool) array_filter($variantes, fn ($item) => $item['estoque'] > 0),
            ];
        }
        $produto['categoria'] = $produto['categoria'] ?? 'Peças';
        $produto['descricao'] = $produto['descricao'] ?? 'Escolha a cor e o tamanho para encontrar a sua versão desta peça.';
        $imagem = $produto['imagem'] ?? '';
        $produto['imagem'] = preg_match('#^/assets/images/[a-zA-Z0-9._/-]+$#', $imagem) ? $imagem : '';
        $produto['imagem_posicao'] = max(0, min(3, (int) ($produto['imagem_posicao'] ?? 0)));
        $produto['galeria'] = self::galeria($produto);
        return $produto;
    }

    private static function galeria(array $produto): array {
        if (!$produto['imagem']) return [];
        $atlas = $produto['imagem'] === '/assets/images/catalogo.webp';
        $base = ['imagem' => $produto['imagem'], 'colunas' => $atlas ? 2 : 1, 'linhas' => $atlas ? 2 : 1, 'posicao' => $atlas ? $produto['imagem_posicao'] : 0];
        // As fotos complementares pertencem apenas ao catálogo ilustrativo.
        $mapa = [
            0 => ['Amarelo' => [null, 8], 'Branco' => [0, 4]],
            1 => ['Branco' => [null, 9], 'Azul' => [1, 5]],
            2 => ['Lilás' => [null, 10], 'Preto' => [2, 6]],
            3 => ['Natural' => [null, 3, 7, 11]],
        ];
        $fotos = [];
        foreach ($produto['cores'] as $cor) {
            $posicoes = $atlas ? ($mapa[$produto['imagem_posicao']][$cor] ?? [null]) : [null];
            foreach ($posicoes as $index => $posicao) {
                $foto = $posicao === null ? $base : ['imagem' => '/assets/images/catalogo-galeria.webp', 'colunas' => 4, 'linhas' => 3, 'posicao' => $posicao];
                $nome = $index === 0 ? 'Frente' : 'Costas';
                if ($produto['imagem_posicao'] === 3 && $atlas) $nome = ['Frente', 'Outro ângulo', 'Verso', 'Perfil'][$index];
                $fotos[] = array_merge($foto, ['cor' => $cor, 'nome' => $nome]);
            }
        }
        return $fotos;
    }
}
