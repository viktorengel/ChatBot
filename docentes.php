<?php
require_once 'auth.php';
soloAdmin();
require_once 'config.php';
require_once 'header.php';

$conn = conectar();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'agregar') {
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $rol = $_POST['rol'];
        $stmt = $conn->prepare("INSERT INTO docentes (nombre, email, password, rol) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nombre, $email, $password, $rol);
        if ($stmt->execute()) {
            $msg = "Docente registrado correctamente";
        } else {
            $err = "Error: ese correo ya existe";
        }

    } elseif ($_POST['accion'] === 'eliminar') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM docente_cursos WHERE docente_id = $id");
        $conn->query("DELETE FROM docentes WHERE id = $id");
        $msg = "Docente eliminado";

    } elseif ($_POST['accion'] === 'asignar_cursos') {
        $docente_id = intval($_POST['docente_id']);
        $cursos_sel = $_POST['cursos_nuevos'] ?? [];
        foreach ($cursos_sel as $curso_id) {
            $curso_id = intval($curso_id);
            $conn->query("INSERT IGNORE INTO docente_cursos (docente_id, curso_id) VALUES ($docente_id, $curso_id)");
        }
        header('Location: docentes.php?msg=' . urlencode(count($cursos_sel) . ' curso(s) asignado(s) correctamente'));
        exit;

    } elseif ($_POST['accion'] === 'quitar_curso') {
        $docente_id = intval($_POST['docente_id']);
        $curso_id = intval($_POST['curso_id']);
        $conn->query("DELETE FROM docente_cursos WHERE docente_id = $docente_id AND curso_id = $curso_id");
        header('Location: docentes.php?msg=Curso removido');
        exit;
    }
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];

$cursos_todos = $conn->query("SELECT * FROM cursos ORDER BY jornada, nivel, nombre");
$cursos_arr = [];
while ($c = $cursos_todos->fetch_assoc()) $cursos_arr[] = $c;

// Agrupar cursos por jornada y nivel para el modal
$cursos_agrupados = [];
foreach ($cursos_arr as $c) {
    $j = $c['jornada'] ?? 'Sin jornada';
    $n = $c['nivel'] ?? 'Sin nivel';
    $cursos_agrupados[$j][$n][] = $c;
}

$docentes = $conn->query("SELECT * FROM docentes ORDER BY nombre");

header_html('Docentes');
?>
<style>
.docente-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 15px; }
.docente-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.docente-info h3 { margin: 0; font-size: 16px; color: #333; }
.docente-info p { margin: 3px 0 0; font-size: 13px; color: #777; }
.cursos-asignados { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; min-height: 35px; }
.curso-tag { background: #e8f0fe; color: #1a73e8; border-radius: 20px; padding: 5px 12px 5px 14px; font-size: 13px; display: flex; align-items: center; gap: 6px; }
.curso-tag .quitar { background: #1a73e8; color: white; border: none; border-radius: 50%; width: 18px; height: 18px; font-size: 11px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
.curso-tag .quitar:hover { background: #c5221f; }
.sin-cursos { color: #999; font-size: 13px; font-style: italic; padding: 8px 0; }

/* Modal cursos */
.modal-cursos { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1001; align-items: center; justify-content: center; }
.modal-cursos.activo { display: flex; }
.modal-cursos-box { background: white; border-radius: 12px; width: 90%; max-width: 600px; max-height: 80vh; display: flex; flex-direction: column; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
.modal-cursos-header { padding: 20px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
.modal-cursos-header h3 { margin: 0; color: #1a73e8; font-size: 18px; }
.modal-cursos-cerrar { background: none; border: none; font-size: 22px; cursor: pointer; color: #777; }
.modal-cursos-body { padding: 20px 25px; overflow-y: auto; flex: 1; }
.modal-cursos-footer { padding: 15px 25px; border-top: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
.grupo-jornada { margin-bottom: 15px; }
.grupo-jornada-titulo { background: #1557b0; color: white; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: bold; margin-bottom: 8px; }
.grupo-nivel-titulo { color: #1a73e8; font-size: 12px; font-weight: bold; margin: 8px 0 5px; padding-left: 5px; border-left: 3px solid #1a73e8; }
.cursos-check-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 6px; }
.curso-check-item { display: flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 13px; }
.curso-check-item:hover { background: #f0f4ff; }
.curso-check-item input { cursor: pointer; width: 16px; height: 16px; }
.seleccion-info { font-size: 13px; color: #555; }
.btn-seleccionar-todos { font-size: 12px; color: #1a73e8; background: none; border: none; cursor: pointer; text-decoration: underline; }
/* ── RESPONSIVE ── */
@media (max-width: 768px) {
    .cursos-check-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
    .modal-cursos-box { width: 95%; max-height: 88vh; }
}
@media (max-width: 600px) {
    .docente-header { flex-direction: column; align-items: flex-start; gap: 8px; }
    .docente-header > div:last-child { display: flex; gap: 6px; flex-wrap: wrap; }
    .cursos-check-grid { grid-template-columns: 1fr 1fr !important; }
    .modal-cursos-header { padding: 14px 16px; }
    .modal-cursos-body { padding: 14px 16px; }
    .modal-cursos-footer { padding: 12px 16px; flex-direction: column; gap: 8px; }
    .modal-cursos-footer .btn { width: 100%; text-align: center; }
    .grid2 { grid-template-columns: 1fr !important; }
    input[type=text], input[type=email], input[type=password], select { font-size: 16px !important; }
    .btn-sm { padding: 8px 12px; }
}
@media (max-width: 380px) {
    .cursos-check-grid { grid-template-columns: 1fr !important; }
    .docente-card { padding: 13px; }
    .btn { width: 100%; text-align: center; margin-bottom: 5px; }
}

</style>

<div class="container">
    <?php if ($msg): ?><div class="alerta exito">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alerta error">❌ <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="card">
        <h2>➕ Registrar Docente</h2>
        <form method="POST">
            <input type="hidden" name="accion" value="agregar">
            <div class="grid2">
                <div class="form-group">
                    <label>Nombre completo</label>
                    <input type="text" name="nombre" placeholder="Apellido Nombre" required>
                </div>
                <div class="form-group">
                    <label>Correo electrónico</label>
                    <input type="email" name="email" placeholder="docente@ecuasys.com" required>
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" name="password" placeholder="Mínimo 6 caracteres" required>
                </div>
                <div class="form-group">
                    <label>Rol</label>
                    <select name="rol">
                        <option value="docente">Docente</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn">➕ Registrar Docente</button>
        </form>
    </div>

    <h2 style="margin-bottom:15px;color:#1a73e8">🧑‍🏫 Lista de Docentes</h2>

    <?php while ($d = $docentes->fetch_assoc()):
        $cursos_docente = $conn->query("
            SELECT c.id, c.nombre FROM cursos c
            JOIN docente_cursos dc ON dc.curso_id = c.id
            WHERE dc.docente_id = {$d['id']}
            ORDER BY c.nombre
        ");
        $cursos_d = [];
        while ($cd = $cursos_docente->fetch_assoc()) $cursos_d[] = $cd;
        $ids_asignados = array_column($cursos_d, 'id');
        $disponibles = array_filter($cursos_arr, fn($c) => !in_array($c['id'], $ids_asignados));
    ?>
    <div class="docente-card">
        <div class="docente-header">
            <div class="docente-info">
                <h3><?= htmlspecialchars($d['nombre']) ?></h3>
                <p><?= htmlspecialchars($d['email']) ?> — <span class="badge <?= $d['rol'] ?>"><?= $d['rol'] ?></span></p>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
                <?php if (!empty($disponibles)): ?>
                <button class="btn btn-green btn-sm" onclick="abrirModalCursos(<?= $d['id'] ?>, '<?= htmlspecialchars(addslashes($d['nombre'])) ?>')">
                    ➕ Asignar cursos
                </button>
                <?php endif; ?>
                <?php if ($d['id'] != $_SESSION['docente_id']): ?>
                <form method="POST" onsubmit="return confirmarEliminar(this, '¿Eliminar al docente <?= htmlspecialchars($d['nombre']) ?>?')">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                    <button type="submit" class="btn btn-red btn-sm">🗑 Eliminar</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div style="font-size:13px;font-weight:bold;color:#555;margin-bottom:8px">
            Cursos asignados (<?= count($cursos_d) ?>):
        </div>
        <div class="cursos-asignados">
            <?php if (empty($cursos_d)): ?>
                <span class="sin-cursos">Sin cursos asignados</span>
            <?php else: ?>
                <?php foreach ($cursos_d as $c): ?>
                <div class="curso-tag">
                    <?= htmlspecialchars($c['nombre']) ?>
                    <form method="POST" style="display:inline;margin:0">
                        <input type="hidden" name="accion" value="quitar_curso">
                        <input type="hidden" name="docente_id" value="<?= $d['id'] ?>">
                        <input type="hidden" name="curso_id" value="<?= $c['id'] ?>">
                        <button type="submit" class="quitar" title="Quitar curso">✕</button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de cursos para este docente -->
    <div class="modal-cursos" id="modal-cursos-<?= $d['id'] ?>">
        <div class="modal-cursos-box">
            <div class="modal-cursos-header">
                <h3>📚 Asignar cursos a <?= htmlspecialchars($d['nombre']) ?></h3>
                <button class="modal-cursos-cerrar" onclick="cerrarModalCursos(<?= $d['id'] ?>)">✕</button>
            </div>
            <div class="modal-cursos-body">
                <form method="POST" id="form-cursos-<?= $d['id'] ?>">
                    <input type="hidden" name="accion" value="asignar_cursos">
                    <input type="hidden" name="docente_id" value="<?= $d['id'] ?>">
                    <?php foreach ($cursos_agrupados as $jornada => $niveles): ?>
                    <div class="grupo-jornada">
                        <div class="grupo-jornada-titulo">🕐 Jornada <?= htmlspecialchars($jornada) ?></div>
                        <?php foreach ($niveles as $nivel => $cursos): ?>
                        <div class="grupo-nivel-titulo"><?= htmlspecialchars($nivel) ?></div>
                        <div class="cursos-check-grid">
                            <?php foreach ($cursos as $c): ?>
                                <?php if (in_array($c['id'], $ids_asignados)) continue; ?>
                                <label class="curso-check-item">
                                    <input type="checkbox" name="cursos_nuevos[]" value="<?= $c['id'] ?>" class="check-docente-<?= $d['id'] ?>">
                                    <?= htmlspecialchars($c['nombre']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </form>
            </div>
            <div class="modal-cursos-footer">
                <div>
                    <span class="seleccion-info" id="info-sel-<?= $d['id'] ?>">0 cursos seleccionados</span>
                    <button class="btn-seleccionar-todos" onclick="seleccionarTodos(<?= $d['id'] ?>)">Seleccionar todos</button>
                </div>
                <div style="display:flex;gap:8px">
                    <button class="btn" style="background:#777" onclick="cerrarModalCursos(<?= $d['id'] ?>)">Cancelar</button>
                    <button class="btn btn-green" onclick="document.getElementById('form-cursos-<?= $d['id'] ?>').submit()">💾 Asignar seleccionados</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.querySelectorAll('.check-docente-<?= $d['id'] ?>').forEach(function(cb) {
        cb.addEventListener('change', function() {
            var total = document.querySelectorAll('.check-docente-<?= $d['id'] ?>:checked').length;
            document.getElementById('info-sel-<?= $d['id'] ?>').textContent = total + ' curso(s) seleccionado(s)';
        });
    });
    </script>

    <?php endwhile; ?>
</div>

<script>
function abrirModalCursos(id, nombre) {
    document.getElementById('modal-cursos-' + id).classList.add('activo');
}
function cerrarModalCursos(id) {
    document.getElementById('modal-cursos-' + id).classList.remove('activo');
}
function seleccionarTodos(id) {
    var checks = document.querySelectorAll('.check-docente-' + id);
    var todos = Array.from(checks).every(c => c.checked);
    checks.forEach(c => c.checked = !todos);
    var total = document.querySelectorAll('.check-docente-' + id + ':checked').length;
    document.getElementById('info-sel-' + id).textContent = total + ' curso(s) seleccionado(s)';
}
</script>

<?php footer_html(); $conn->close(); ?>