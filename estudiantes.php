<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

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
        $curso_id = !empty($_POST['curso_id']) ? intval($_POST['curso_id']) : null;
        $stmt = $conn->prepare("INSERT INTO estudiantes (nombre, curso_id) VALUES (?, ?)");
        $stmt->bind_param("si", $nombre, $curso_id);
        $msg = $stmt->execute() ? "Estudiante registrado correctamente" : "Error: " . $conn->error;

    } elseif ($_POST['accion'] === 'editar') {
        $id = intval($_POST['id']);
        $nombre = trim($_POST['nombre']);
        $curso_id = !empty($_POST['curso_id']) ? intval($_POST['curso_id']) : null;
        $stmt = $conn->prepare("UPDATE estudiantes SET nombre=?, curso_id=? WHERE id=?");
        $stmt->bind_param("sii", $nombre, $curso_id, $id);
        $msg = $stmt->execute() ? "Estudiante actualizado" : "Error: " . $conn->error;

    } elseif ($_POST['accion'] === 'eliminar') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM faltas WHERE estudiante_id = $id");
        $conn->query("DELETE FROM estudiante_representante WHERE estudiante_id = $id");
        $conn->query("DELETE FROM estudiantes WHERE id = $id");
        $msg = "Estudiante eliminado";

    } elseif ($_POST['accion'] === 'vincular_representante') {
        $estudiante_id = intval($_POST['estudiante_id']);
        $representante_id = intval($_POST['representante_id']);
        $es_principal = isset($_POST['es_principal']) ? 1 : 0;

        // Verificar si ya existe
        $check = $conn->query("SELECT id FROM estudiante_representante WHERE estudiante_id = $estudiante_id AND representante_id = $representante_id");
        if ($check->num_rows > 0) {
            $err = "Ese representante ya está vinculado a este estudiante";
        } else {
            // Si es principal, quitar principal anterior
            if ($es_principal) {
                $conn->query("UPDATE estudiante_representante SET es_principal = 0 WHERE estudiante_id = $estudiante_id");
            }
            $stmt = $conn->prepare("INSERT INTO estudiante_representante (estudiante_id, representante_id, es_principal) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $estudiante_id, $representante_id, $es_principal);
            $msg = $stmt->execute() ? "Representante vinculado correctamente" : "Error: " . $conn->error;
        }

    } elseif ($_POST['accion'] === 'desvincular_representante') {
        $estudiante_id = intval($_POST['estudiante_id']);
        $representante_id = intval($_POST['representante_id']);
        $conn->query("DELETE FROM estudiante_representante WHERE estudiante_id = $estudiante_id AND representante_id = $representante_id");
        $msg = "Representante desvinculado";
    }
}

$cursos_q = $conn->query("SELECT * FROM cursos ORDER BY jornada, nivel, nombre");
$cursos_arr = [];
while ($c = $cursos_q->fetch_assoc()) $cursos_arr[] = $c;

$representantes_todos = $conn->query("SELECT * FROM representantes ORDER BY nombre");
$reps_arr = [];
while ($r = $representantes_todos->fetch_assoc()) $reps_arr[] = $r;

$estudiantes = $conn->query("
    SELECT e.*, c.nombre as curso_nombre, c.jornada
    FROM estudiantes e
    LEFT JOIN cursos c ON e.curso_id = c.id
    ORDER BY c.nombre, e.nombre
");

$editar = null;
if (isset($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $r = $conn->query("SELECT * FROM estudiantes WHERE id = $id");
    $editar = $r->fetch_assoc();
}

header_html('Estudiantes');
?>
<style>
.estudiante-card { background: white; border-radius: 10px; padding: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 12px; }
.estudiante-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.estudiante-info h3 { margin: 0; font-size: 15px; color: #333; }
.estudiante-info p { margin: 3px 0 0; font-size: 13px; color: #777; }
.reps-lista { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
.rep-tag { background: #e8f0fe; color: #1a73e8; border-radius: 20px; padding: 4px 10px 4px 12px; font-size: 12px; display: flex; align-items: center; gap: 5px; }
.rep-tag.principal { background: #e6f4ea; color: #137333; }
.rep-tag .quitar { background: #1a73e8; color: white; border: none; border-radius: 50%; width: 16px; height: 16px; font-size: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
.rep-tag.principal .quitar { background: #137333; }
.rep-tag .quitar:hover { background: #c5221f; }
.sin-rep { color: #e65100; font-size: 12px; font-style: italic; background: #fff3e0; padding: 4px 10px; border-radius: 10px; }
.vincular-form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 8px; }
.vincular-form select { flex: 1; min-width: 180px; padding: 7px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
.vincular-form label { font-weight: normal; font-size: 13px; display: flex; align-items: center; gap: 4px; white-space: nowrap; }
.grupo-curso { margin-bottom: 20px; }
.grupo-curso-titulo { background: #1557b0; color: white; padding: 8px 15px; border-radius: 8px; font-size: 14px; font-weight: bold; margin-bottom: 10px; }
</style>

<div class="container">
    <?php if ($msg): ?><div class="alerta exito">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alerta error">❌ <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="card">
        <h2><?= $editar ? '✏️ Editar Estudiante' : '➕ Registrar Nuevo Estudiante' ?></h2>
        <form method="POST">
            <input type="hidden" name="accion" value="<?= $editar ? 'editar' : 'agregar' ?>">
            <?php if ($editar): ?><input type="hidden" name="id" value="<?= $editar['id'] ?>"><?php endif; ?>
            <div class="grid2">
                <div class="form-group">
                    <label>Nombre completo</label>
                    <input type="text" name="nombre" placeholder="Apellido Nombre" value="<?= htmlspecialchars($editar['nombre'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Curso</label>
                    <select name="curso_id">
                        <option value="">-- Sin asignar --</option>
                        <?php
                        $jornada_actual = '';
                        foreach ($cursos_arr as $c):
                            if ($c['jornada'] !== $jornada_actual) {
                                if ($jornada_actual !== '') echo '</optgroup>';
                                echo '<optgroup label="Jornada ' . htmlspecialchars($c['jornada']) . '">';
                                $jornada_actual = $c['jornada'];
                            }
                            $sel = ($editar && $editar['curso_id'] == $c['id']) ? 'selected' : '';
                            echo "<option value='{$c['id']}' $sel>" . htmlspecialchars($c['nombre']) . "</option>";
                        endforeach;
                        if ($jornada_actual !== '') echo '</optgroup>';
                        ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn"><?= $editar ? '💾 Guardar cambios' : '➕ Registrar Estudiante' ?></button>
            <?php if ($editar): ?>
                <a href="estudiantes.php" class="btn" style="background:#777;text-decoration:none;margin-left:10px">Cancelar</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h2>👤 Lista de Estudiantes</h2>
        <?php
        $estudiantes_arr = [];
        while ($e = $estudiantes->fetch_assoc()) $estudiantes_arr[] = $e;

        // Agrupar por curso
        $por_curso = [];
        foreach ($estudiantes_arr as $e) {
            $curso_key = $e['curso_nombre'] ?? 'Sin curso asignado';
            $por_curso[$curso_key][] = $e;
        }

        foreach ($por_curso as $curso_nombre => $lista):
        ?>
        <div class="grupo-curso">
            <div class="grupo-curso-titulo">📚 <?= htmlspecialchars($curso_nombre) ?> — <?= count($lista) ?> estudiante(s)</div>

            <?php foreach ($lista as $e):
                $reps = $conn->query("
                    SELECT r.id, r.nombre, r.telefono, er.es_principal
                    FROM representantes r
                    JOIN estudiante_representante er ON er.representante_id = r.id
                    WHERE er.estudiante_id = {$e['id']}
                    ORDER BY er.es_principal DESC, r.nombre
                ");
                $reps_e = [];
                while ($r = $reps->fetch_assoc()) $reps_e[] = $r;
                $ids_vinculados = array_column($reps_e, 'id');
                $reps_disponibles = array_filter($reps_arr, fn($r) => !in_array($r['id'], $ids_vinculados));
            ?>
            <div class="estudiante-card">
                <div class="estudiante-header">
                    <div class="estudiante-info">
                        <h3><?= htmlspecialchars($e['nombre']) ?></h3>
                        <p><?= htmlspecialchars($e['curso_nombre'] ?? 'Sin curso') ?></p>
                    </div>
                    <div style="display:flex;gap:6px">
                        <a href="estudiantes.php?editar=<?= $e['id'] ?>" class="btn btn-green btn-sm" style="text-decoration:none">✏️</a>
                        <form method="POST" onsubmit="return confirmarEliminar(this, '¿Eliminar a <?= htmlspecialchars($e['nombre']) ?> y todas sus faltas?')">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                            <button type="submit" class="btn btn-red btn-sm">🗑</button>
                        </form>
                    </div>
                </div>

                <!-- Representantes vinculados -->
                <div style="font-size:12px;font-weight:bold;color:#555;margin-bottom:6px">Representantes:</div>
                <div class="reps-lista">
                    <?php if (empty($reps_e)): ?>
                        <span class="sin-rep">⚠️ Sin representante — no puede recibir notificaciones</span>
                    <?php else: ?>
                        <?php foreach ($reps_e as $r): ?>
                        <div class="rep-tag <?= $r['es_principal'] ? 'principal' : '' ?>">
                            <?= $r['es_principal'] ? '⭐' : '' ?> <?= htmlspecialchars($r['nombre']) ?> — <?= $r['telefono'] ?>
                            <form method="POST" style="display:inline;margin:0">
                                <input type="hidden" name="accion" value="desvincular_representante">
                                <input type="hidden" name="estudiante_id" value="<?= $e['id'] ?>">
                                <input type="hidden" name="representante_id" value="<?= $r['id'] ?>">
                                <button type="submit" class="quitar" title="Desvincular">✕</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Vincular representante -->
                <?php if (!empty($reps_disponibles)): ?>
                <form method="POST" class="vincular-form">
                    <input type="hidden" name="accion" value="vincular_representante">
                    <input type="hidden" name="estudiante_id" value="<?= $e['id'] ?>">
                    <select name="representante_id" required>
                        <option value="">-- Vincular representante --</option>
                        <?php foreach ($reps_disponibles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?> — <?= $r['telefono'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label><input type="checkbox" name="es_principal" value="1" <?= empty($reps_e) ? 'checked' : '' ?>> Principal</label>
                    <button type="submit" class="btn btn-green btn-sm">➕ Vincular</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php footer_html(); $conn->close(); ?>
