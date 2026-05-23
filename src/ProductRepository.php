<?php
declare(strict_types=1);

class ProductRepository
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function createTableIfNeeded(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS productos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            descripcion TEXT,
            precio DECIMAL(10,2) NOT NULL,
            stock INT NOT NULL DEFAULT 0,
            categoria VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";

        if (!$this->db->query($sql)) {
            error_log('Producto table creation failed: ' . $this->db->error);
        }
    }

    public function findAll(): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, nombre, descripcion, precio, stock, categoria, created_at
             FROM productos
             ORDER BY created_at DESC"
        );

        if ($stmt === false) {
            error_log('Product findAll prepare failed: ' . $this->db->error);
            return [];
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $products = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $products;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, nombre, descripcion, precio, stock, categoria, created_at
             FROM productos WHERE id = ? LIMIT 1"
        );

        if ($stmt === false) {
            error_log('Product findById prepare failed: ' . $this->db->error);
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $product ?: null;
    }

    public function create(
        string $nombre,
        string $descripcion,
        float $precio,
        int $stock,
        string $categoria
    ): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO productos (nombre, descripcion, precio, stock, categoria)
             VALUES (?, ?, ?, ?, ?)"
        );

        if ($stmt === false) {
            error_log('Product create prepare failed: ' . $this->db->error);
            return false;
        }

        $stmt->bind_param('ssdis', $nombre, $descripcion, $precio, $stock, $categoria);
        $success = $stmt->execute();

        if (!$success) {
            error_log('Product create execute failed: ' . $stmt->error);
        }

        $stmt->close();
        return $success;
    }

    public function update(
        int $id,
        string $nombre,
        string $descripcion,
        float $precio,
        int $stock,
        string $categoria
    ): bool {
        $stmt = $this->db->prepare(
            "UPDATE productos
             SET nombre = ?, descripcion = ?, precio = ?, stock = ?, categoria = ?
             WHERE id = ?"
        );

        if ($stmt === false) {
            error_log('Product update prepare failed: ' . $this->db->error);
            return false;
        }

        $stmt->bind_param('ssdisi', $nombre, $descripcion, $precio, $stock, $categoria, $id);
        $success = $stmt->execute();

        if (!$success) {
            error_log('Product update execute failed: ' . $stmt->error);
        }

        $stmt->close();
        return $success;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM productos WHERE id = ?');

        if ($stmt === false) {
            error_log('Product delete prepare failed: ' . $this->db->error);
            return false;
        }

        $stmt->bind_param('i', $id);
        $success = $stmt->execute();

        if (!$success) {
            error_log('Product delete execute failed: ' . $stmt->error);
        }

        $stmt->close();
        return $success;
    }
}
