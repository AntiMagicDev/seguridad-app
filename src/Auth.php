<?php
declare(strict_types=1);

class Auth
{
    private mysqli $db;
    private SessionManager $session;

    public function __construct(SessionManager $session)
    {
        $this->db = Database::getConnection();
        $this->session = $session;
    }

    public function login(string $email, string $password): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id, nombre, password FROM usuarios WHERE correo = ? LIMIT 1"
        );

        if ($stmt === false) {
            error_log('Auth query prepare failed: ' . $this->db->error);
            return false;
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, (string) $user['password'])) {
            return false;
        }

        $this->session->regenerateId();
        $this->session->set('usuario_id', (int) $user['id']);
        $this->session->set('usuario_nombre', (string) $user['nombre']);

        return true;
    }

    public function check(): bool
    {
        return $this->session->get('usuario_id') !== null;
    }

    public function requireAuthentication(): void
    {
        if (!$this->check()) {
            Security::redirect('login.php');
        }
    }

    public function logout(): void
    {
        $this->session->destroy();
    }

    public function getUserName(): string
    {
        return (string) $this->session->get('usuario_nombre', '');
    }

    public function getUserById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT nombre FROM usuarios WHERE id = ? LIMIT 1");

        if ($stmt === false) {
            error_log('Auth getUserById prepare failed: ' . $this->db->error);
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $user ?: null;
    }
}
