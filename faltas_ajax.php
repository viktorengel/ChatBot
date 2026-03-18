<?php
require_once 'auth.php';
require_once 'config.php';

date_default_timezone_set('America/Guayaquil');
$conn     = conectar();
$es_admin = esAdmin();
$hoy      = date('Y-m-d');

// Fecha solicitada: no puede ser futura
$fecha_raw = isset($_GET['fecha']) ? trim($_GET['fecha']) : $hoy;
// Validar formato Y-m-d y que no sea futura
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_raw) || $fecha_raw > $hoy) {
    $fecha_raw = $hoy;
}
$fecha_filtro = $conn->real_escape_string($fecha_raw);

if ($es_admin) {
    $rs = $conn->query("
        SELECT f.id, f.fecha, f.mensaje_enviado, e.nombre as estudiante, c.nombre as curso
        FROM faltas f
        JOIN estudiantes e ON f.estudiante_id = e.id
        LEFT JOIN cursos c ON e.curso_id = c.id
        WHERE DATE(f.fecha) = '$fecha_filtro'
        ORDER BY c.nombre, e.nombre
    ");
} else {
    $did = $_SESSION['docente_id'];
    $rs = $conn->query("
        SELECT f.id, f.fecha, f.mensaje_enviado, e.nombre as estudiante, c.nombre as curso
        FROM faltas f
        JOIN estudiantes e ON f.estudiante_id = e.id
        LEFT JOIN cursos c ON e.curso_id = c.id
        JOIN docente_cursos dc ON dc.curso_id = e.curso_id
        WHERE dc.docente_id = $did AND DATE(f.fecha) = '$fecha_filtro'
        ORDER BY c.nombre, e.nombre
    ");
}

$filas = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
$cols  = 2;

ob_start();
if (empty($filas)) {
    echo "<p style='text-align:center;color:#aaa;padding:20px 0'>No hay faltas registradas para esta fecha</p>";
} else {
    $th_acc    = $es_admin ? "WhatsApp&nbsp;&nbsp;Eliminar" : "WhatsApp";
    $trash_svg = "<svg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='3 6 5 6 21 6'/><path d='M19 6l-1 14H6L5 6'/><path d='M10 11v6'/><path d='M14 11v6'/><path d='M9 6V4h6v2'/></svg>";
    echo "<div class='r-wrap'><table class='r-tabla'><tbody>"
       . "<tr class='r-fila-header'><td>Estudiante</td><td class='r-th-acc'>$th_acc</td></tr>";
    $curso_act = null;
    foreach ($filas as $f):
        $curso_str = $f['curso'] ?? '—';
        if ($curso_str !== $curso_act):
            $curso_act = $curso_str;
            $curso_esc = htmlspecialchars($curso_str);
            echo "<tr class='r-fila-curso'><td colspan='$cols'>📚 $curso_esc</td></tr>";
        endif;
        $nombre  = htmlspecialchars($f['estudiante']);
        $ico     = $f['mensaje_enviado'] ? '✅' : '❌';
        $fila    = "<tr class='r-fila'><td>$nombre</td><td class='r-col-acc'><span class='r-acc-inner'><span class='r-ico'>$ico</span>";
        if ($es_admin):
            $id   = $f['id'];
            $fila .= "<button class='btn-del-r' onclick=\"if(confirm('¿Eliminar?')) location.href='eliminar_falta.php?id=$id'\">$trash_svg</button>";
        endif;
        $fila .= "</span></td></tr>";
        echo $fila;
    endforeach;
    echo "</tbody></table></div>";
}

$html = ob_get_clean();
$conn->close();
header('Content-Type: application/json');
echo json_encode(['html' => $html]);