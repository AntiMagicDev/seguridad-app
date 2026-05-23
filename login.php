<?php
require_once __DIR__ . '/src/bootstrap.php';

$session = new SessionManager();
$auth = new Auth($session);

if ($auth->check()) {
    Security::redirect('panel.php');
}

$error = '';
$csrfToken = $session->get('csrf_token');
if (empty($csrfToken)) {
    $csrfToken = Security::generateCsrfToken();
    $session->set('csrf_token', $csrfToken);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '', (string) $session->get('csrf_token', ''))) {
        $error = 'Solicitud inválida. Recarga la página e intenta de nuevo.';
    } else {
        $correo = Security::sanitizeEmail($_POST['correo'] ?? '');
        $contrasena = $_POST['contrasena'] ?? '';

        if (empty($correo) || empty($contrasena)) {
            $error = 'Todos los campos son obligatorios.';
        } elseif (!Security::validateEmail($correo)) {
            $error = 'Credenciales inválidas.';
        } elseif (!$auth->login($correo, $contrasena)) {
            $error = 'Credenciales inválidas.';
        } else {
            Security::redirect('panel.php');
        }
    }
}

$session->set('csrf_token', $csrfToken);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Iniciar sesión</title>
  <link rel="stylesheet" href="estilos.css" />
</head>
<body class="login-page" style="display: grid;">
<div class="sectionWeb">
<div class="card">
  <h1>Bienvenido</h1>
  <p class="subtitle">Ingresa tus credenciales para continuar.</p>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= Security::escape($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="login.php" novalidate>
    <input type="hidden" name="csrf_token" value="<?= Security::escape($csrfToken) ?>" />

    <div class="field">
      <label for="correo">Correo electrónico</label>
      <input type="email" id="correo" name="correo"
        placeholder="ana@correo.com" maxlength="254"
        autocomplete="email" required />
    </div>

    <div class="field">
      <label for="contrasena">Contraseña</label>
      <input type="password" id="contrasena" name="contrasena"
        placeholder="Tu contraseña" maxlength="128"
        autocomplete="current-password" required />
    </div>

    <button type="submit" class="btn-primary">Ingresar</button>
  </form>
</div>
</div>


</body>
</html>
