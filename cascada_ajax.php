<?php
require_once 'auth.php';
require_once 'config.php';

$conn = conectar();
$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json');

if ($accion === 'niveles') {
    $jornada = $_GET['jornada'] ?? '';
    $niveles = $conn->query("
        SELECT DISTINCT c.nivel 
        FROM cursos c 
        WHERE c.jornada = '" . $conn->real_escape_string($jornada) . "'
        AND c.nivel IS NOT NULL
        ORDER BY c.nivel
    ");
    $resultado = [];
    while ($n = $niveles->fetch_assoc()) $resultado[] = $n['nivel'];
    echo json_encode($resultado);

} elseif ($accion === 'cursos') {
    $jornada = $_GET['jornada'] ?? '';
    $nivel = $_GET['nivel'] ?? '';
    $cursos = $conn->query("
        SELECT id, nombre FROM cursos 
        WHERE jornada = '" . $conn->real_escape_string($jornada) . "'
        AND nivel = '" . $conn->real_escape_string($nivel) . "'
        ORDER BY nombre
    ");
    $resultado = [];
    while ($c = $cursos->fetch_assoc()) $resultado[] = ['id' => $c['id'], 'nombre' => $c['nombre']];
    echo json_encode($resultado);
}

$conn->close();
?>
