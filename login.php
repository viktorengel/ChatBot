<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['docente_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $conn  = conectar();
    $stmt  = $conn->prepare("SELECT id, nombre, password, rol FROM docentes WHERE email = ? AND activo = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result  = $stmt->get_result();
    $docente = $result->fetch_assoc();

    if ($docente && password_verify($password, $docente['password'])) {
        $_SESSION['docente_id']     = $docente['id'];
        $_SESSION['docente_nombre'] = $docente['nombre'];
        $_SESSION['docente_rol']    = $docente['rol'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Email o contraseña incorrectos';
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Asistencia</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif;
            background: #1a73e8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;          /* espacio en pantallas pequeñas */
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 36px 32px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .logo { text-align: center; margin-bottom: 24px; }
        .logo h1 { color: #1a73e8; font-size: 22px; }
        .logo p  { color: #777; font-size: 13px; margin-top: 5px; }

        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; color: #555; font-size: 14px; }

        input {
            width: 100%; padding: 12px;
            border: 1px solid #ddd; border-radius: 6px;
            font-size: 16px;        /* evita zoom automático en iOS */
            -webkit-appearance: none;
        }
        input:focus { outline: none; border-color: #1a73e8; }

        .btn {
            background: #1a73e8; color: white; border: none;
            padding: 13px; border-radius: 6px; cursor: pointer;
            font-size: 16px; width: 100%; margin-top: 6px;
            touch-action: manipulation;
        }
        .btn:hover  { background: #1557b0; }
        .btn:active { transform: scale(0.98); }

        .error {
            background: #fce8e6; color: #c5221f;
            padding: 10px; border-radius: 6px;
            margin-bottom: 15px; font-size: 14px; text-align: center;
        }

        /* Móviles muy pequeños */
        @media (max-width: 380px) {
            .card { padding: 24px 18px; }
            .logo h1 { font-size: 18px; }
        }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <h1>🏫 U.E. Pomasqui</h1>
        <p>Sistema de Control de Asistencia</p>
    </div>
    <?php if ($error): ?>
        <div class="error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="form-group">
            <label>Correo electrónico</label>
            <input type="email" name="email" placeholder="docente@ecuasys.com" required autocomplete="email">
        </div>
        <div class="form-group">
            <label>Contraseña</label>
            <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn">Ingresar</button>
    </form>
</div>
</body>
</html>