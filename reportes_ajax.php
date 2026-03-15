<?php
require_once 'auth.php';
require_once 'config.php';

date_default_timezone_set('America/Guayaquil');
$conn = conectar();

$filtro_curso = $_GET['curso'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$filtro_docente = $_GET['docente'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';

$where = ["1=1"];
$params = [];
$types = "";

if (!esAdmin()) {
    $did = $_SESSION['docente_id'];
    $where[] = "dc.docente_id = $did";
}
if (!empty($filtro_curso)) { $where[] = "e.curso_id = ?"; $params[] = intval($filtro_curso); $types .= "i"; }
if (!empty($filtro_fecha_desde)) { $where[] = "f.fecha >= ?"; $params[] = $filtro_fecha_desde; $types .= "s"; }
if (!empty($filtro_fecha_hasta)) { $where[] = "f.fecha <= ?"; $params[] = $filtro_fecha_hasta; $types .= "s"; }
if (!empty($filtro_docente) && esAdmin()) { $where[] = "f.docente_id = ?"; $params[] = intval($filtro_docente); $types .= "i"; }
if ($filtro_estado === 'enviado') $where[] = "f.mensaje_enviado = 1";
elseif ($filtro_estado === 'pendiente') $where[] = "f.mensaje_enviado = 0";

$where_sql = implode(" AND ", $where);
$join_docente = esAdmin() ? "LEFT JOIN docentes d ON f.docente_id = d.id" : "JOIN docente_cursos dc ON dc.curso_id = e.curso_id";

$sql = "
    SELECT f.id, f.fecha, f.mensaje_enviado,
           e.nombre as estudiante, c.nombre as curso,
           r.nombre as representante, r.telefono,
           d.nombre as docente
    FROM faltas f
    JOIN estudiantes e ON f.estudiante_id = e.id
    JOIN cursos c ON e.curso_id = c.id
    JOIN representantes r ON e.representante_id = r.id
    $join_docente
    WHERE $where_sql
    ORDER BY f.fecha DESC, f.created_at DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$faltas = $stmt->get_result();
$filas = $faltas->fetch_all(MYSQLI_ASSOC);
$es_admin = esAdmin();

ob_start();
?>
<table>
    <thead>
        <tr>
            <th>Fecha</th><th>Estudiante</th><th>Curso</th>
            <th>Representante</th><th>Teléfono</th>
            <?php if ($es_admin): ?><th>Docente</th><?php endif; ?>
            <th>WhatsApp</th>
            <?php if ($es_admin): ?><th>Acción</th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($filas)): ?>
        <tr><td colspan="8" style="text-align:center;color:#777;padding:20px">No hay faltas registradas</td></tr>
        <?php else: ?>
        <?php foreach ($filas as $f): ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($f['fecha'])) ?></td>
            <td><?= htmlspecialchars($f['estudiante']) ?></td>
            <td><?= htmlspecialchars($f['curso']) ?></td>
            <td><?= htmlspecialchars($f['representante']) ?></td>
            <td><?= $f['telefono'] ?></td>
            <?php if ($es_admin): ?><td><?= htmlspecialchars($f['docente'] ?? '—') ?></td><?php endif; ?>
            <td><span class="badge <?= $f['mensaje_enviado'] ? 'enviado' : 'pendiente' ?>"><?= $f['mensaje_enviado'] ? '✅ Enviado' : '⏳ Pendiente' ?></span></td>
            <?php if ($es_admin): ?>
            <td>
                <a href="eliminar_falta.php?id=<?= $f['id'] ?>&origen=reportes" class="btn btn-red btn-sm">🗑</a>
            </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
<?php
$html = ob_get_clean();
$conn->close();
header('Content-Type: application/json');
echo json_encode(['html' => $html, 'hora' => date('H:i:s')]);
?>
