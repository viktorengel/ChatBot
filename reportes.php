<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'header.php';

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
    SELECT f.id, f.fecha, f.mensaje_enviado, f.created_at,
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

$total_enviados = 0;
$total_pendientes = 0;
$filas = [];
while ($f = $faltas->fetch_assoc()) {
    $filas[] = $f;
    if ($f['mensaje_enviado']) $total_enviados++;
    else $total_pendientes++;
}

$cursos = $conn->query("SELECT * FROM cursos ORDER BY nombre");
$docentes = esAdmin() ? $conn->query("SELECT * FROM docentes ORDER BY nombre") : null;

header_html('Reportes');
?>
<style>
.filtros { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; margin-bottom: 15px; }
.stat-box { background: white; border-radius: 8px; padding: 15px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.stat-num { font-size: 32px; font-weight: bold; color: #1a73e8; }
.stat-label { font-size: 13px; color: #777; margin-top: 5px; }
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
#ultima-actualizacion { font-size: 12px; color: #777; text-align: right; margin-bottom: 8px; }
</style>

<div class="container">

    <?php if (isset($_GET['msg'])): ?>
        <div class="alerta exito">✅ <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>🔍 Filtros</h2>
        <form method="GET" id="form-filtros">
            <div class="filtros">
                <div class="form-group">
                    <label>Desde</label>
                    <input type="date" name="fecha_desde" value="<?= $filtro_fecha_desde ?>">
                </div>
                <div class="form-group">
                    <label>Hasta</label>
                    <input type="date" name="fecha_hasta" value="<?= $filtro_fecha_hasta ?>">
                </div>
                <div class="form-group">
                    <label>Curso</label>
                    <select name="curso">
                        <option value="">Todos</option>
                        <?php while ($c = $cursos->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" <?= $filtro_curso == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php if (esAdmin()): ?>
                <div class="form-group">
                    <label>Docente</label>
                    <select name="docente">
                        <option value="">Todos</option>
                        <?php while ($d = $docentes->fetch_assoc()): ?>
                            <option value="<?= $d['id'] ?>" <?= $filtro_docente == $d['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['nombre']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label>Estado WhatsApp</label>
                    <select name="estado">
                        <option value="">Todos</option>
                        <option value="enviado" <?= $filtro_estado === 'enviado' ? 'selected' : '' ?>>Enviados</option>
                        <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                    </select>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn" style="width:100%">🔍 Filtrar</button>
                </div>
            </div>
        </form>
    </div>

    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-num"><?= count($filas) ?></div>
            <div class="stat-label">Total Faltas</div>
        </div>
        <div class="stat-box">
            <div class="stat-num" style="color:#137333"><?= $total_enviados ?></div>
            <div class="stat-label">WhatsApp Enviados</div>
        </div>
        <div class="stat-box">
            <div class="stat-num" style="color:#c5221f"><?= $total_pendientes ?></div>
            <div class="stat-label">Pendientes</div>
        </div>
    </div>

    <div class="card">
        <h2>📋 Registro de Faltas</h2>
        <div id="ultima-actualizacion">Actualizado: <span id="hora-actualizacion"><?= date('H:i:s') ?></span></div>
        <div id="tabla-faltas">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Estudiante</th>
                        <th>Curso</th>
                        <th>Representante</th>
                        <th>Teléfono</th>
                        <?php if (esAdmin()): ?><th>Docente</th><?php endif; ?>
                        <th>WhatsApp</th>
                        <?php if (esAdmin()): ?><th>Acción</th><?php endif; ?>
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
                        <?php if (esAdmin()): ?>
                        <td><?= htmlspecialchars($f['docente'] ?? '—') ?></td>
                        <?php endif; ?>
                        <td>
                            <span class="badge <?= $f['mensaje_enviado'] ? 'enviado' : 'pendiente' ?>">
                                <?= $f['mensaje_enviado'] ? '✅ Enviado' : '⏳ Pendiente' ?>
                            </span>
                        </td>
                        <?php if (esAdmin()): ?>
                        <td>
                            <a href="eliminar_falta.php?id=<?= $f['id'] ?>&origen=reportes" class="btn-eliminar-falta">🗑</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function actualizarTabla() {
    var params = new URLSearchParams(window.location.search);
    fetch('reportes_ajax.php?' + params.toString())
        .then(r => r.json())
        .then(data => {
            document.getElementById('tabla-faltas').innerHTML = data.html;
            document.getElementById('hora-actualizacion').textContent = data.hora;
        })
        .catch(() => {});
}
setInterval(actualizarTabla, 30000);
</script>

<?php footer_html(); $conn->close(); ?>
