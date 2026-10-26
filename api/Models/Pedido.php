<?php

namespace Api\Models;

use Api\Core\Database;
use PDO;

class Pedido {
    const NOME_TABELA = "pedidos";

    public function obterTodos() {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM " . self::NOME_TABELA . "";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $produtos ?: null;
    }

    public function criar(array $dados): int {
        $pdo = Database::getInstance();
        $sql = "INSERT INTO " . self::NOME_TABELA . " (valorPedido, valorFrete, dataPedido, status, idCupom) VALUES (:valorPedido, :valorFrete, CURRENT_TIMESTAMP, :status, :idCupom)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':valorPedido' => $dados["valorPedido"],
            ':valorFrete'  => $dados["valorFrete"],
            ':status'      => $dados["status"],
            ':idCupom'     => $dados["idCupom"]
        ]);
        return $pdo->lastInsertId();
    }

    public function obterComRestricoes(array $restricoes): ?array {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM " . self::NOME_TABELA . " WHERE 1 ";
        $parametros = array();

        if (!isset($restricoes['ativosSomente']) || $restricoes['ativosSomente'] === true) {
            $sql .= " AND " . self::NOME_TABELA . ".ativo = 1 ";
        }

        if (is_numeric($restricoes['id'])) {
            $sql .= " AND " . self::NOME_TABELA . ".id = :id ";
            $parametros['id'] = $restricoes['id'];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($parametros);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $pedidos ?: null;
    }

    public function atualizarStatus(int $id, string $status): int {
        $pdo = Database::getInstance();
        $sql = "UPDATE " . self::NOME_TABELA . " SET status = :status WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id'      => $id,
            ':status'  => $status
        ]);
        return $stmt->rowCount() > 0;
    }
}
