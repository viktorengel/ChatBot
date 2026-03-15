<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'header.php';

date_default_timezone_set('America/Guayaquil');
$conn = conectar();
$hoy = date('Y-m-d');

// Solo estudiantes CON representante y SIN falta hoy
if (esAdmin()) {
    $estudiantes = $conn->query("
        SELECT DISTINCT e.id, e.nombre, c.nombre as curso
        FROM estudiantes e
        JOIN cursos c ON e.curso_id = c.id
        JOIN estudiante_representante er ON er.estudiante_id = e.id
        WHERE e.id NOT IN (SELECT estudiante_id FROM faltas WHERE fecha = '$hoy')
        ORDER BY c.nombre, e.nombre
    ");
} else {
    $did = $_SESSION['docente_id'];
    $estudiantes = $conn->query("
        SELECT DISTINCT e.id, e.nombre, c.nombre as curso
        FROM estudiantes e
        JOIN cursos c ON e.curso_id = c.id
        JOIN estudiante_representante er ON er.estudiante_id = e.id
        JOIN docente_cursos dc ON dc.curso_id = e.curso_id
        WHERE dc.docente_id = $did
        AND e.id NOT IN (SELECT estudiante_id FROM faltas WHERE fecha = '$hoy')
        ORDER BY c.nombre, e.nombre
    ");
}

header_html('Registrar Falta');
?>
<div class="container">

    <?php if (isset($_GET['msg'])): ?>
        <div class="alerta exito">✅ <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['err'])): ?>
        <div class="alerta error">❌ <?= htmlspecialchars($_GET['err']) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>📝 Registrar Falta</h2>
        <form action="enviar.php" method="POST">
            <div class="grid2">
                <div class="form-group">
                    <label>Estudiante</label>
                    <select name="estudiante_id" required>
                        <option value="">-- Seleccione --</option>
                        <?php while ($e = $estudiantes->fetch_assoc()): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nombre']) ?> — <?= htmlspecialchars($e['curso']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <button type="submit" class="btn">📤 Registrar y Notificar por WhatsApp</button>
        </form>
    </div>

    <div class="card">
        <h2>📋 Últimas Faltas Registradas</h2>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Estudiante</th>
                    <th>Curso</th>
                    <th>WhatsApp</th>
                    <?php if (esAdmin()): ?><th>Acción</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="tbody-faltas">
                <?php
                if (esAdmin()) {
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
                while ($f = $faltas->fetch_assoc()):
                ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($f['fecha'])) ?></td>
                    <td><?= htmlspecialchars($f['estudiante']) ?></td>
                    <td><?= htmlspecialchars($f['curso'] ?? '—') ?></td>
                    <td><span class="badge <?= $f['mensaje_enviado'] ? 'enviado' : 'pendiente' ?>"><?= $f['mensaje_enviado'] ? '✅ Enviado' : '⏳ Pendiente' ?></span></td>
                    <?php if (esAdmin()): ?>
                    <td><a href="eliminar_falta.php?id=<?= $f['id'] ?>" class="btn-eliminar-falta">🗑</a></td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function actualizarTablaFaltas() {
    fetch('faltas_ajax.php')
        .then(r => r.json())
        .then(data => { document.getElementById('tbody-faltas').innerHTML = data.html; })
        .catch(() => {});
}
setInterval(actualizarTablaFaltas, 60000);
</script>

<?php footer_html(); $conn->close(); ?>
