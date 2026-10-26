-- Metadados opcionais da vitrine para bancos MySQL já existentes.
ALTER TABLE produtos ADD COLUMN categoria VARCHAR(100) DEFAULT 'Peças';
ALTER TABLE produtos ADD COLUMN descricao TEXT;
ALTER TABLE produtos ADD COLUMN imagem VARCHAR(500) DEFAULT '';
ALTER TABLE produtos ADD COLUMN imagem_posicao INT DEFAULT 0;
CREATE TABLE entregas_pedidos (
    idPedido INT PRIMARY KEY,
    nome VARCHAR(200),
    email VARCHAR(254),
    endereco TEXT,
    FOREIGN KEY (idPedido) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
