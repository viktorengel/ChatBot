<?php
require_once 'auth.php';
require_once 'config.php';

date_default_timezone_set('America/Guayaquil');
$conn = conectar();

$filtro_curso       = $_GET['curso']       ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$filtro_estado      = $_GET['estado']      ?? '';

$where  = ["1=1"];
$params = [];
$types  = "";

if (!esAdmin()) {
    $did     = $_SESSION['docente_id'];
    $where[] = "dc.docente_id = $did";
}
if (!empty($filtro_curso))       { $where[] = "e.curso_id = ?"; $params[] = intval($filtro_curso);   $types .= "i"; }
if (!empty($filtro_fecha_desde)) { $where[] = "f.fecha >= ?";   $params[] = $filtro_fecha_desde;     $types .= "s"; }
if (!empty($filtro_fecha_hasta)) { $where[] = "f.fecha <= ?";   $params[] = $filtro_fecha_hasta;     $types .= "s"; }
if ($filtro_estado === 'enviado')      $where[] = "f.mensaje_enviado = 1";
elseif ($filtro_estado === 'pendiente') $where[] = "f.mensaje_enviado = 0";

$where_sql    = implode(" AND ", $where);
$join_docente = esAdmin()
    ? "LEFT JOIN docentes d ON f.docente_id = d.id"
    : "JOIN docente_cursos dc ON dc.curso_id = e.curso_id";

$sql = "
    SELECT f.id, f.fecha, f.mensaje_enviado,
           e.nombre as estudiante, c.nombre as curso
    FROM faltas f
    JOIN estudiantes e ON f.estudiante_id = e.id
    JOIN cursos c ON e.curso_id = c.id
    $join_docente
    WHERE $where_sql
    ORDER BY f.fecha DESC, c.nombre, e.nombre
";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$es_admin = esAdmin();
$cols     = $es_admin ? 3 : 2;
$dias_es  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
$trash    = "<svg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='3 6 5 6 21 6'/><path d='M19 6l-1 14H6L5 6'/><path d='M10 11v6'/><path d='M14 11v6'/><path d='M9 6V4h6v2'/></svg>";

ob_start();

if (empty($filas)) {
    echo "<p style='text-align:center;color:#aaa;padding:20px 0'>No hay faltas registradas</p>";
} else {
    $th_acc = $es_admin ? "WhatsApp&nbsp;&nbsp;Eliminar" : "WhatsApp";
    echo "<div class='r-wrap'><table class='r-tabla'><tbody>"
       . "<tr class='r-fila-header'><td>Estudiante</td><td class='r-th-acc'>$th_acc</td></tr>";

    $fecha_act = null;
    $curso_act = null;
    foreach ($filas as $f) {
        $fecha_fmt = date('d/m/Y', strtotime($f['fecha']));
        $curso_str = $f['curso'] ?? '—';

        if ($fecha_fmt !== $fecha_act) {
            $fecha_act = $fecha_fmt; $curso_act = null;
            $dia_sem   = $dias_es[date('w', strtotime($f['fecha']))];
            echo "<tr class='r-fila-fecha'><td colspan='$cols'>📅 $dia_sem, $fecha_fmt</td></tr>";
        }
        if ($curso_str !== $curso_act) {
            $curso_act = $curso_str;
            echo "<tr class='r-fila-curso'><td colspan='$cols'>📚 " . htmlspecialchars($curso_str) . "</td></tr>";
        }

        $nom     = htmlspecialchars($f['estudiante']);
        $ico     = $f['mensaje_enviado'] ? '✅' : '❌';
        $fid     = $f['id'];
        $del_btn = $es_admin
            ? "<button class='btn-del-r' onclick=\"if(confirm('¿Eliminar?')) location.href='eliminar_falta.php?id=$fid&origen=reportes'\">$trash</button>"
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
echo json_encode(['html' => $html, 'hora' => date('H:i:s')]);