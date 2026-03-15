<?php
require_once 'auth.php';
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

date_default_timezone_set('America/Guayaquil');

$estudiantes_ids = $_POST['estudiantes'] ?? [];
$fecha = $_POST['fecha'];
$motivo = 'Inasistencia registrada por el docente';

if (empty($estudiantes_ids)) {
    header('Location: index.php?err=No seleccionaste ningún estudiante');
    exit;
}

// Validar fecha
$hoy = date('Y-m-d');
$ayer = date('Y-m-d', strtotime('-1 day'));
$dia_semana_ayer = date('N', strtotime($ayer));
if ($dia_semana_ayer == 7) $ayer = date('Y-m-d', strtotime('-3 days'));
if ($dia_semana_ayer == 6) $ayer = date('Y-m-d', strtotime('-2 days'));

if ($fecha > $hoy) {
    header('Location: index.php?err=No se puede registrar una falta en fecha futura');
    exit;
}
if ($fecha < $ayer) {
    header('Location: index.php?err=Solo se puede registrar faltas de hoy o del ultimo dia laborable');
    exit;
}

$conn = conectar();
$docente_id = $_SESSION['docente_id'];
$registrados = 0;
$omitidos = 0;
$errores = [];

foreach ($estudiantes_ids as $estudiante_id) {
    $estudiante_id = intval($estudiante_id);

    // Verificar acceso del docente
    if (!esAdmin()) {
        $did = $_SESSION['docente_id'];
        $check = $conn->query("
            SELECT e.id FROM estudiantes e
            JOIN docente_cursos dc ON dc.curso_id = e.curso_id
            WHERE e.id = $estudiante_id AND dc.docente_id = $did
        ");
        if ($check->num_rows === 0) continue;
    }

    // Verificar representante
    $check_rep = $conn->query("SELECT id FROM estudiante_representante WHERE estudiante_id = $estudiante_id LIMIT 1");
    if ($check_rep->num_rows === 0) {
        $errores[] = "Estudiante ID $estudiante_id sin representante";
        continue;
    }

    // Verificar duplicado
    $check_dup = $conn->prepare("SELECT id FROM faltas WHERE estudiante_id = ? AND fecha = ?");
    $check_dup->bind_param("is", $estudiante_id, $fecha);
    $check_dup->execute();
    if ($check_dup->get_result()->num_rows > 0) {
        $omitidos++;
        continue;
    }

    // Registrar falta
    $insert = $conn->prepare("INSERT INTO faltas (estudiante_id, fecha, motivo, mensaje_enviado, docente_id) VALUES (?, ?, ?, 0, ?)");
    $insert->bind_param("issi", $estudiante_id, $fecha, $motivo, $docente_id);
    if ($insert->execute()) {
        $registrados++;
    }
}

$conn->close();

$msg = "$registrados falta(s) registrada(s). El WhatsApp se enviara en breve.";
if ($omitidos > 0) $msg .= " $omitidos omitida(s) por duplicado.";

header('Location: index.php?msg=' . urlencode($msg));
exit;
?>