<?php
require_once 'auth.php';
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

date_default_timezone_set('America/Guayaquil');

$estudiante_id = intval($_POST['estudiante_id']);
$fecha = $_POST['fecha'];
$motivo = 'Inasistencia registrada por el docente';

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

// Verificar acceso del docente
if (!esAdmin()) {
    $did = $_SESSION['docente_id'];
    $check_acceso = $conn->prepare("
        SELECT e.id FROM estudiantes e
        JOIN docente_cursos dc ON dc.curso_id = e.curso_id
        WHERE e.id = ? AND dc.docente_id = ?
    ");
    $check_acceso->bind_param("ii", $estudiante_id, $did);
    $check_acceso->execute();
    if ($check_acceso->get_result()->num_rows === 0) {
        header('Location: index.php?err=No tienes acceso a ese estudiante');
        exit;
    }
}

// Verificar que tiene representante
$check_rep = $conn->query("SELECT id FROM estudiante_representante WHERE estudiante_id = $estudiante_id LIMIT 1");
if ($check_rep->num_rows === 0) {
    header('Location: index.php?err=Este estudiante no tiene representante asignado');
    exit;
}

// Verificar falta duplicada
$check = $conn->prepare("SELECT id FROM faltas WHERE estudiante_id = ? AND fecha = ?");
$check->bind_param("is", $estudiante_id, $fecha);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    header('Location: index.php?err=Ya existe una falta registrada para este estudiante en esa fecha');
    exit;
}

// Guardar falta en cola
$docente_id = $_SESSION['docente_id'];
$insert = $conn->prepare("INSERT INTO faltas (estudiante_id, fecha, motivo, mensaje_enviado, docente_id) VALUES (?, ?, ?, 0, ?)");
$insert->bind_param("issi", $estudiante_id, $fecha, $motivo, $docente_id);
$insert->execute();

$conn->close();
header('Location: index.php?msg=Falta registrada. El WhatsApp se enviara en breve.');
exit;
?>
