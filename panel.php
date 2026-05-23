<?php
require_once __DIR__ . '/src/bootstrap.php';

$session = new SessionManager();
$auth = new Auth($session);
$auth->requireAuthentication();

if (isset($_GET['logout']) && Security::validateCsrfToken($_GET['csrf'] ?? '', (string) $session->get('csrf_token', ''))) {
    $auth->logout();
    Security::redirect('login.php');
}

$csrfToken = $session->get('csrf_token');
if (empty($csrfToken)) {
    $csrfToken = Security::generateCsrfToken();
    $session->set('csrf_token', $csrfToken);
}

$productRepository = new ProductRepository();
$productRepository->createTableIfNeeded();

$nombre_usuario = Security::escape($auth->getUserName());
$inicial = mb_strtoupper(mb_substr($auth->getUserName(), 0, 1));

$mensaje = '';
$tipo_mensaje = '';
$producto_editar = null;

function getProductPayload(array $data): array
{
    return [
        'nombre' => Security::sanitizeString($data['nombre'] ?? '', 255),
        'descripcion' => Security::sanitizeString($data['descripcion'] ?? '', 1000),
        'precio' => max(0.0, floatval($data['precio'] ?? 0)),
        'stock' => max(0, intval($data['stock'] ?? 0)),
        'categoria' => Security::sanitizeString($data['categoria'] ?? '', 100),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '', (string) $session->get('csrf_token', ''))) {
        $mensaje = 'Solicitud inválida. Recarga la página.';
        $tipo_mensaje = 'error';
    } else {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'crear' || $accion === 'actualizar') {
            $payload = getProductPayload($_POST);

            if ($payload['nombre'] === '' || $payload['precio'] <= 0 || $payload['stock'] < 0) {
                $mensaje = 'Por favor completa los campos obligatorios correctamente.';
                $tipo_mensaje = 'error';
            } else {
                if ($accion === 'crear') {
                    if ($productRepository->create(
                        $payload['nombre'],
                        $payload['descripcion'],
                        $payload['precio'],
                        $payload['stock'],
                        $payload['categoria']
                    )) {
                        $mensaje = '✓ Producto registrado exitosamente.';
                        $tipo_mensaje = 'success';
                    } else {
                        $mensaje = 'Error al registrar el producto.';
                        $tipo_mensaje = 'error';
                    }
                } else {
                    $id_producto = intval($_POST['id_producto'] ?? 0);

                    if ($id_producto <= 0) {
                        $mensaje = 'ID de producto inválido.';
                        $tipo_mensaje = 'error';
                    } elseif ($productRepository->update(
                        $id_producto,
                        $payload['nombre'],
                        $payload['descripcion'],
                        $payload['precio'],
                        $payload['stock'],
                        $payload['categoria']
                    )) {
                        $mensaje = '✓ Producto actualizado exitosamente.';
                        $tipo_mensaje = 'success';
                        $producto_editar = null;
                    } else {
                        $mensaje = 'Error al actualizar el producto.';
                        $tipo_mensaje = 'error';
                    }
                }
            }
        } elseif ($accion === 'eliminar') {
            $id_producto = intval($_POST['id_producto'] ?? 0);

            if ($id_producto <= 0) {
                $mensaje = 'ID de producto inválido.';
                $tipo_mensaje = 'error';
            } elseif ($productRepository->delete($id_producto)) {
                $mensaje = '✓ Producto eliminado exitosamente.';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al eliminar el producto.';
                $tipo_mensaje = 'error';
            }
        }
    }
}

if (isset($_GET['editar'])) {
    $id_editar = intval($_GET['editar']);
    $producto_editar = $productRepository->findById($id_editar);
}

$productos = $productRepository->findAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Panel de Inventario</title>
  <link rel="stylesheet" href="estilos.css" />
  <style>
    /* ── Layout panel ── */
    body { display: block; padding: 0; background: var(--bg); }

    .topbar {
      position: sticky; top: 0; z-index: 10;
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center;
      justify-content: space-between;
      padding: 1rem 2rem;
    }

    .topbar-brand {
      font-family: 'DM Serif Display', serif;
      font-size: 1.25rem;
      color: var(--accent);
    }

    .topbar-user {
      display: flex; align-items: center; gap: .75rem;
    }

    .avatar {
      width: 38px; height: 38px;
      background: var(--accent);
      color: #0e0e10;
      border-radius: 50%;
      display: grid; place-items: center;
      font-weight: 700; font-size: .95rem;
      flex-shrink: 0;
    }

    .topbar-name {
      font-size: .9rem;
      color: var(--text);
    }

    .btn-logout {
      font-size: .8rem;
      padding: .4rem .9rem;
      background: transparent;
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--muted);
      cursor: pointer;
      text-decoration: none;
      transition: border-color .2s, color .2s;
    }

    .btn-logout:hover { border-color: var(--error); color: var(--error); }

    /* ── Main content ── */
    .main { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }

    .welcome {
      margin-bottom: 2rem;
    }

    .welcome h2 {
      font-family: 'DM Serif Display', serif;
      font-size: 1.75rem;
      margin-bottom: .3rem;
    }

    .welcome p { color: var(--muted); font-size: .9rem; }

    /* ── Secciones ── */
    .section {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 2rem;
      margin-bottom: 2rem;
    }

    .section h3 {
      font-size: 1.25rem;
      margin-bottom: 1.5rem;
      color: var(--text);
    }

    /* ── Form layout ── */
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .field { display: flex; flex-direction: column; }
    .field label { font-size: .85rem; font-weight: 500; margin-bottom: .5rem; color: var(--text); }
    .field input,
    .field textarea,
    .field select {
      padding: .7rem;
      border: 1px solid var(--border);
      border-radius: 8px;
      background: var(--bg);
      color: var(--text);
      font-size: .9rem;
    }

    .field textarea { resize: vertical; min-height: 80px; }

    .form-actions {
      display: flex; gap: .75rem;
    }

    .btn {
      padding: .7rem 1.5rem;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: .9rem;
      font-weight: 500;
      transition: all .2s;
    }

    .btn-primary {
      background: var(--accent);
      color: #0e0e10;
    }

    .btn-primary:hover { opacity: 0.9; transform: translateY(-2px); }

    .btn-secondary {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text);
      text-decoration: none;
    }

    .btn-secondary:hover { border-color: var(--accent); color: var(--accent); }

    .btn-sm {
      padding: .4rem .8rem;
      font-size: .8rem;
    }

    .btn-danger {
      background: var(--error);
      color: white;
    }

    .btn-danger:hover { opacity: 0.9; }

    .btn-success {
      background: var(--success);
      color: white;
    }

    /* ── Mensajes ── */
    .alert {
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: .75rem;
    }

    .alert-success {
      background: rgba(76,175,125,.12);
      border: 1px solid var(--success);
      color: var(--success);
    }

    .alert-error {
      background: rgba(244,67,54,.12);
      border: 1px solid var(--error);
      color: var(--error);
    }

    /* ── Tabla de productos ── */
    .table-container {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    thead {
      background: var(--bg);
      border-bottom: 2px solid var(--border);
    }

    th {
      padding: 1rem;
      text-align: left;
      font-weight: 600;
      font-size: .85rem;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: var(--muted);
    }

    td {
      padding: 1rem;
      border-bottom: 1px solid var(--border);
      color: var(--text);
    }

    tr:hover { background: var(--bg); }

    .table-actions {
      display: flex; gap: .5rem;
    }

    .modal-mode {
      background: rgba(0, 0, 0, .5);
      padding: 1.5rem;
      border-radius: 12px;
      margin-bottom: 1.5rem;
      border: 1px solid var(--accent);
    }

    .modal-mode p {
      color: var(--muted);
      margin-bottom: 1rem;
    }

    /* ── Responsive ── */
    @media (max-width: 768px) {
      .topbar { flex-direction: column; gap: 1rem; }
      .form-grid { grid-template-columns: 1fr; }
      .table-actions { flex-direction: column; }
    }
  </style>
</head>
<body>

<!-- Topbar -->
<header class="topbar">
  <span class="topbar-brand">Panel de Inventario</span>
  <div class="topbar-user">
    <div class="avatar"><?= Security::escape($inicial) ?></div>
    <span class="topbar-name"><?= $nombre_usuario ?></span>
    <a href="panel.php?logout=1&csrf=<?= urlencode($csrfToken) ?>"
       class="btn-logout"
       onclick="return confirm('¿Cerrar sesión?')">
      Salir
    </a>
  </div>
</header>

<!-- Contenido -->
<main class="main">

  <div class="welcome">
    <h2>¡Hola, <?= $nombre_usuario ?>! </h2>
    <p>Gestiona tu inventario de productos desde aquí.</p>
  </div>

  <!-- Mensajes -->
  <?php if ($mensaje): ?>
    <div class="alert alert-<?= Security::escape($tipo_mensaje) ?>">
      <span><?= Security::escape($mensaje) ?></span>
    </div>
  <?php endif; ?>

  <!-- Formulario de Producto -->
  <section class="section">
    <?php if ($producto_editar): ?>
      <div class="modal-mode">
        <p> <strong>Modo edición:</strong> Estás actualizando el producto "<?= Security::escape($producto_editar['nombre']) ?>"</p>
        <a href="panel.php" class="btn btn-secondary btn-sm">Cancelar edición</a>
      </div>
    <?php endif; ?>

    <h3><?= $producto_editar ? 'Actualizar producto' : 'Registrar nuevo producto' ?></h3>
    
    <form method="POST" action="panel.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= Security::escape($csrfToken) ?>" />
      <input type="hidden" name="accion" value="<?= $producto_editar ? 'actualizar' : 'crear' ?>" />
      
      <?php if ($producto_editar): ?>
        <input type="hidden" name="id_producto" value="<?= Security::escape((string) $producto_editar['id']) ?>" />
      <?php endif; ?>

      <div class="form-grid">
        <div class="field">
          <label for="nombre">Nombre del producto *</label>
          <input type="text" id="nombre" name="nombre"
            placeholder="Ej: Laptop Dell"
            value="<?= $producto_editar ? Security::escape($producto_editar['nombre']) : '' ?>"
            maxlength="255" required />
        </div>

        <div class="field">
          <label for="categoria">Categoría</label>
          <input type="text" id="categoria" name="categoria"
            placeholder="Ej: Electrónica"
            value="<?= $producto_editar ? Security::escape($producto_editar['categoria']) : '' ?>"
            maxlength="100" />
        </div>

        <div class="field">
          <label for="precio">Precio *</label>
          <input type="number" id="precio" name="precio"
            placeholder="0.00"
            value="<?= $producto_editar ? Security::escape((string) $producto_editar['precio']) : '' ?>"
            step="0.01" min="0" required />
        </div>

        <div class="field">
          <label for="stock">Stock *</label>
          <input type="number" id="stock" name="stock"
            placeholder="0"
            value="<?= $producto_editar ? Security::escape((string) $producto_editar['stock']) : '' ?>"
            min="0" required />
        </div>
      </div>

      <div class="field">
        <label for="descripcion">Descripción</label>
        <textarea id="descripcion" name="descripcion"
          placeholder="Descripción detallada del producto..."
          maxlength="1000"><?= $producto_editar ? Security::escape($producto_editar['descripcion']) : '' ?></textarea>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">
          <?= $producto_editar ? 'Actualizar' : '➕ Registrar' ?>
        </button>
        
        <?php if ($producto_editar): ?>
          <a href="panel.php" class="btn btn-secondary">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <!-- Tabla de Productos -->
  <section class="section">
    <h3>Productos registrados (<?= count($productos) ?>)</h3>

    <?php if (empty($productos)): ?>
      <p style="color: var(--muted); text-align: center; padding: 2rem;">
        📭 No hay productos registrados aún. ¡Crea uno para empezar!
      </p>
    <?php else: ?>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Categoría</th>
              <th>Precio</th>
              <th>Stock</th>
              <th>Descripción</th>
              <th>Creado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($productos as $producto): ?>
              <tr>
                <td><strong><?= Security::escape($producto['nombre']) ?></strong></td>
                <td><?= Security::escape($producto['categoria'] ?: '—') ?></td>
                <td>$<?= Security::escape(number_format((float) $producto['precio'], 2)) ?></td>
                <td>
                  <span style="
                    background: <?= $producto['stock'] > 0 ? 'rgba(76,175,125,.2)' : 'rgba(244,67,54,.2)' ?>;
                    color: <?= $producto['stock'] > 0 ? 'var(--success)' : 'var(--error)' ?>;
                    padding: .3rem .75rem;
                    border-radius: 100px;
                    font-size: .85rem;
                    font-weight: 600;
                  ">
                    <?= Security::escape((string) $producto['stock']) ?> un.
                  </span>
                </td>
                <td>
                  <span style="color: var(--muted); font-size: .85rem;">
                    <?= Security::escape(mb_substr($producto['descripcion'] ?: '—', 0, 40)) ?>...
                  </span>
                </td>
                <td>
                  <span style="color: var(--muted); font-size: .8rem;">
                    <?= Security::escape(date('d/m/y', strtotime($producto['created_at']))) ?>
                  </span>
                </td>
                <td>
                  <div class="table-actions">
                    <a href="panel.php?editar=<?= Security::escape((string) $producto['id']) ?>" class="btn btn-secondary btn-sm">
                      Editar
                    </a>
                    <form method="POST" action="panel.php" style="display: inline;"
                      onsubmit="return confirm('¿Eliminar producto?');">
                      <input type="hidden" name="csrf_token" value="<?= Security::escape($csrfToken) ?>" />
                      <input type="hidden" name="accion" value="eliminar" />
                      <input type="hidden" name="id_producto" value="<?= Security::escape((string) $producto['id']) ?>" />
                      <button type="submit" class="btn btn-danger btn-sm">🗑️ Eliminar</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

</main>

</body>
</html>
