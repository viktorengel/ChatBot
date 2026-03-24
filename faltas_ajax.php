<?php
require_once 'auth.php';
require_once 'config.php';

date_default_timezone_set('America/Guayaquil');
$conn     = conectar();
$es_admin = esAdmin();

// Fecha filtro: por defecto hoy
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
// Sanitizar: debe ser formato YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    $fecha = date('Y-m-d');
}

if ($es_admin) {
    $stmt = $conn->prepare("
        SELECT f.id, f.fecha, f.mensaje_enviado, e.nombre as estudiante, c.nombre as curso
        FROM faltas f
        JOIN estudiantes e ON f.estudiante_id = e.id
        LEFT JOIN cursos c ON e.curso_id = c.id
        WHERE DATE(f.fecha) = ?
        ORDER BY c.nombre, e.nombre
    ");
} else {
    $did  = $_SESSION['docente_id'];
    $stmt = $conn->prepare("
        SELECT f.id, f.fecha, f.mensaje_enviado, e.nombre as estudiante, c.nombre as curso
        FROM faltas f
        JOIN estudiantes e ON f.estudiante_id = e.id
        LEFT JOIN cursos c ON e.curso_id = c.id
        JOIN docente_cursos dc ON dc.curso_id = e.curso_id
        WHERE dc.docente_id = ? AND DATE(f.fecha) = ?
        ORDER BY c.nombre, e.nombre
    ");
    $stmt->bind_param("is", $did, $fecha);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    goto render;
}
$stmt->bind_param("s", $fecha);
$stmt->execute();
$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

render:
$cols  = 2;
$trash = "<svg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='3 6 5 6 21 6'/><path d='M19 6l-1 14H6L5 6'/><path d='M10 11v6'/><path d='M14 11v6'/><path d='M9 6V4h6v2'/></svg>";

ob_start();

if (empty($filas)) {
    echo "<p style='text-align:center;color:#aaa;padding:20px 0'>No hay faltas registradas para esta fecha</p>";
} else {
    $th_acc    = $es_admin ? "WhatsApp&nbsp;&nbsp;Eliminar" : "WhatsApp";
    $curso_act = null;
    echo "<div class='r-wrap'><table class='r-tabla'><tbody>"
       . "<tr class='r-fila-header'><td>Estudiante</td><td class='r-th-acc'>$th_acc</td></tr>";
    foreach ($filas as $f) {
        $curso_str = $f['curso'] ?? '—';
        if ($curso_str !== $curso_act) {
            $curso_act = $curso_str;
            echo "<tr class='r-fila-curso'><td colspan='$cols'>📚 " . htmlspecialchars($curso_str) . "</td></tr>";
        }
        $nom     = htmlspecialchars($f['estudiante']);
        $ico     = $f['mensaje_enviado'] ? '✅' : '❌';
        $fid     = $f['id'];
        $del_btn = $es_admin
            ? "<button class='btn-del-r' onclick=\"if(confirm('¿Eliminar?')) location.href='eliminar_falta.php?id=$fid'\">$trash</button>"
            : "";
        echo "<tr class='r-fila'>"
           . "<td>$nom</td>"
           . "<td class='r-col-acc'><span class='r-acc-inner'><span class='r-ico'>$ico</span>$del_btn</span></td>"
           . "</tr>";
    }
    echo "</tbody></table></div>";
}

$html = ob_get_clean();
$conn->close();
header('Content-Type: application/json');
echo json_encode(['html' => $html]);