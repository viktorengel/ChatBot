<?php
require_once 'auth.php';
require_once 'config.php';

$conn = conectar();
$hoy = $_GET['hoy'] ?? date('Y-m-d');
$es_admin = esAdmin();

if ($es_admin) {
    $query = "
        SELECT DISTINCT e.id, e.nombre, c.nombre as curso, c.id as curso_id
        FROM estudiantes e
        JOIN cursos c ON e.curso_id = c.id
        JOIN estudiante_representante er ON er.estudiante_id = e.id
        WHERE e.id NOT IN (SELECT estudiante_id FROM faltas WHERE fecha = '$hoy')
        ORDER BY c.nombre, e.nombre
    ";
} else {
    $did = $_SESSION['docente_id'];
    $query = "
        SELECT DISTINCT e.id, e.nombre, c.nombre as curso, c.id as curso_id
        FROM estudiantes e
        JOIN cursos c ON e.curso_id = c.id
        JOIN estudiante_representante er ON er.estudiante_id = e.id
        JOIN docente_cursos dc ON dc.curso_id = c.id
        WHERE dc.docente_id = $did
        AND e.id NOT IN (SELECT estudiante_id FROM faltas WHERE fecha = '$hoy')
        ORDER BY c.nombre, e.nombre
    ";
}

$result = $conn->query($query);
$estudiantes = [];
while ($e = $result->fetch_assoc()) {
    $estudiantes[] = [
        'id' => $e['id'],
        'nombre' => $e['nombre'],
        'curso' => $e['curso'],
        'curso_id' => $e['curso_id']
    ];
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($estudiantes);
?>
