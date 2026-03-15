<?php
require_once 'auth.php';
require_once 'config.php';

$conn = conectar();
$es_admin = esAdmin();

if ($es_admin) {
    $faltas = $conn->query("
        SELECT f.id, f.fecha, f.mensaje_enviado, e.nombre as estudiante, c.nombre as curso
        FROM faltas f
        JOIN estudiantes e ON f.estudiante_id = e.id
        LEFT JOIN cursos c ON e.curso_id = c.id
        ORDER BY f.created_at DESC LIMIT 20
    ");
} else {
    $did = $_SESSION['docente_id'];
    $faltas = $conn->query("
        SELECT f.id, f.fecha, f.mensaje_enviado, e.nombre as estudiante, c.nombre as curso
        FROM faltas f
        JOIN estudiantes e ON f.estudiante_id = e.id
        LEFT JOIN cursos c ON e.curso_id = c.id
        JOIN docente_cursos dc ON dc.curso_id = e.curso_id
        WHERE dc.docente_id = $did
        ORDER BY f.created_at DESC LIMIT 20
    ");
}

ob_start();
while ($f = $faltas->fetch_assoc()):
?>
<tr>
    <td><?= date('d/m/Y', strtotime($f['fecha'])) ?></td>
    <td><?= htmlspecialchars($f['estudiante']) ?></td>
    <td><?= htmlspecialchars($f['curso'] ?? '—') ?></td>
    <td><span class="badge <?= $f['mensaje_enviado'] ? 'enviado' : 'pendiente' ?>"><?= $f['mensaje_enviado'] ? '✅ Enviado' : '⏳ Pendiente' ?></span></td>
    <?php if ($es_admin): ?>
    <td><a href="eliminar_falta.php?id=<?= $f['id'] ?>" class="btn-eliminar-falta">🗑</a></td>
    <?php endif; ?>
</tr>
<?php endwhile;
$html = ob_get_clean();
$conn->close();
header('Content-Type: application/json');
echo json_encode(['html' => $html]);
?>