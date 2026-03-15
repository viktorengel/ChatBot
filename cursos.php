<?php
require_once 'auth.php';
soloAdmin();
require_once 'config.php';
require_once 'header.php';

$conn = conectar();
$msg = '';
$err = '';

// Estructura completa del colegio
$estructura = [
    'Inicial' => [
        'subniveles' => ['Inicial 1', 'Inicial 2'],
        'paralelos' => true,
        'figura' => false
    ],
    'Básica Elemental' => [
        'grados' => ['1ro', '2do', '3ro'],
        'paralelos' => true,
        'figura' => false
    ],
    'Básica Media' => [
        'grados' => ['4to', '5to', '6to', '7mo'],
        'paralelos' => true,
        'figura' => false
    ],
    'Básica Superior' => [
        'grados' => ['8vo', '9no', '10mo'],
        'paralelos' => true,
        'figura' => false
    ],
    'Bachillerato BGU' => [
        'grados' => ['1ro', '2do', '3ro'],
        'paralelos' => true,
        'figura' => false
    ],
    'Bachillerato Técnico' => [
        'grados' => ['1ro', '2do', '3ro'],
        'paralelos' => true,
        'figura' => true
    ],
];

$jornadas = ['Matutina', 'Vespertina', 'Nocturna'];
$paralelos = ['A', 'B', 'C', 'D', 'E', 'F'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    if ($_POST['accion'] === 'generar') {
        // Generar cursos automáticamente
        $jornada = $_POST['jornada'];
        $nivel = $_POST['nivel'];
        $paralelos_sel = $_POST['paralelos_sel'] ?? [];
        $figura = trim($_POST['figura'] ?? '');
        $generados = 0;

        $info = $estructura[$nivel];

        if ($nivel === 'Inicial') {
            foreach ($info['subniveles'] as $subnivel) {
                foreach ($paralelos_sel as $p) {
                    $nombre = "$subnivel $p — $jornada";
                    $stmt = $conn->prepare("INSERT IGNORE INTO cursos (nombre, nivel, jornada) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $nombre, $nivel, $jornada);
                    if ($stmt->execute() && $conn->affected_rows > 0) $generados++;
                }
            }
        } else {
            foreach ($info['grados'] as $grado) {
                foreach ($paralelos_sel as $p) {
                    if (!empty($figura)) {
                        $nombre = "$grado BGT $p $figura — $jornada";
                    } else {
                        $abrev = str_replace('Bachillerato ', 'Bach. ', $nivel);
                        if (strpos($nivel, 'BGU') !== false) {
                            $nombre = "$grado BGU $p — $jornada";
                        } else {
                            $nombre = "$grado $p — $jornada";
                        }
                    }
                    $stmt = $conn->prepare("INSERT IGNORE INTO cursos (nombre, nivel, jornada) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $nombre, $nivel, $jornada);
                    if ($stmt->execute() && $conn->affected_rows > 0) $generados++;
                }
            }
        }
        $msg = "$generados curso(s) generados correctamente";

    } elseif ($_POST['accion'] === 'agregar') {
        $nombre = trim($_POST['nombre']);
        $nivel = $_POST['nivel'];
        $jornada = $_POST['jornada'];
        $stmt = $conn->prepare("INSERT INTO cursos (nombre, nivel, jornada) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nombre, $nivel, $jornada);
        $msg = $stmt->execute() ? "Curso creado correctamente" : "Error: " . $conn->error;

    } elseif ($_POST['accion'] === 'editar') {
        $id = intval($_POST['id']);
        $nombre = trim($_POST['nombre']);
        $nivel = $_POST['nivel'];
        $jornada = $_POST['jornada'];
        $stmt = $conn->prepare("UPDATE cursos SET nombre=?, nivel=?, jornada=? WHERE id=?");
        $stmt->bind_param("sssi", $nombre, $nivel, $jornada, $id);
        $msg = $stmt->execute() ? "Curso actualizado" : "Error: " . $conn->error;

    } elseif ($_POST['accion'] === 'eliminar') {
        $id = intval($_POST['id']);
        $check = $conn->query("SELECT COUNT(*) as t FROM estudiantes WHERE curso_id = $id");
        $total = $check->fetch_assoc()['t'];
        if ($total > 0) {
            $err = "No se puede eliminar, tiene $total estudiante(s) asignado(s)";
        } else {
            $conn->query("DELETE FROM docente_cursos WHERE curso_id = $id");
            $conn->query("DELETE FROM cursos WHERE id = $id");
            $msg = "Curso eliminado";
        }
    } elseif ($_POST['accion'] === 'eliminar_todos') {
        $jornada = $_POST['jornada_filtro'];
        $nivel = $_POST['nivel_filtro'];
        $conn->query("DELETE dc FROM docente_cursos dc JOIN cursos c ON dc.curso_id = c.id WHERE c.jornada = '$jornada' AND c.nivel = '$nivel' AND c.id NOT IN (SELECT DISTINCT curso_id FROM estudiantes WHERE curso_id IS NOT NULL)");
        $conn->query("DELETE FROM cursos WHERE jornada = '$jornada' AND nivel = '$nivel' AND id NOT IN (SELECT DISTINCT curso_id FROM estudiantes WHERE curso_id IS NOT NULL)");
        $msg = "Cursos sin estudiantes eliminados";
    }
}

// Obtener cursos agrupados
$cursos_raw = $conn->query("
    SELECT c.*, COUNT(e.id) as total_estudiantes 
    FROM cursos c 
    LEFT JOIN estudiantes e ON e.curso_id = c.id 
    GROUP BY c.id 
    ORDER BY c.jornada, c.nivel, c.nombre
");
$cursos_agrupados = [];
while ($c = $cursos_raw->fetch_assoc()) {
    $j = $c['jornada'] ?? 'Sin jornada';
    $n = $c['nivel'] ?? 'Sin nivel';
    $cursos_agrupados[$j][$n][] = $c;
}

$editar = null;
if (isset($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $r = $conn->query("SELECT * FROM cursos WHERE id = $id");
    $editar = $r->fetch_assoc();
}

header_html('Cursos');
?>
<style>
.seccion-jornada { margin-bottom: 25px; }
.titulo-jornada { background: #1557b0; color: white; padding: 10px 15px; border-radius: 8px 8px 0 0; font-weight: bold; font-size: 16px; }
.titulo-nivel { background: #e8f0fe; color: #1a73e8; padding: 8px 15px; font-weight: bold; font-size: 14px; border-left: 4px solid #1a73e8; margin: 5px 0; }
.cursos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 8px; padding: 10px 0; }
.curso-item { background: #f8f9fa; border: 1px solid #ddd; border-radius: 6px; padding: 10px 12px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; }
.curso-item:hover { background: #e8f0fe; }
.curso-nombre { font-weight: bold; color: #333; }
.curso-count { color: #777; font-size: 12px; }
.curso-acciones { display: flex; gap: 5px; }
.tabs { display: flex; gap: 5px; margin-bottom: 20px; flex-wrap: wrap; }
.tab { padding: 8px 16px; border-radius: 20px; cursor: pointer; font-size: 13px; border: 2px solid #1a73e8; color: #1a73e8; background: white; }
.tab.active { background: #1a73e8; color: white; }
.paralelos-check { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 5px; }
.paralelos-check label { display: flex; align-items: center; gap: 5px; font-weight: normal; font-size: 14px; cursor: pointer; }
</style>

<div class="container">
    <?php if ($msg): ?><div class="alerta exito">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alerta error">❌ <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <!-- TABS -->
    <div class="tabs">
        <button class="tab active" onclick="mostrarTab('generar')">⚡ Generar Cursos</button>
        <button class="tab" onclick="mostrarTab('manual')">➕ Agregar Manual</button>
        <?php if ($editar): ?>
        <button class="tab active" onclick="mostrarTab('editar_tab')">✏️ Editando curso</button>
        <?php endif; ?>
    </div>

    <!-- TAB GENERAR -->
    <div id="tab-generar" class="card">
        <h2>⚡ Generar Cursos Automáticamente</h2>
        <p style="color:#555;font-size:13px;margin-bottom:15px;">Selecciona el nivel, jornada y paralelos para generar todos los cursos de una vez.</p>
        <form method="POST">
            <input type="hidden" name="accion" value="generar">
            <div class="grid2">
                <div class="form-group">
                    <label>Jornada</label>
                    <select name="jornada" required>
                        <?php foreach ($jornadas as $j): ?>
                            <option value="<?= $j ?>"><?= $j ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nivel</label>
                    <select name="nivel" id="nivel-select" onchange="mostrarFigura(this.value)" required>
                        <?php foreach (array_keys($estructura) as $n): ?>
                            <option value="<?= $n ?>"><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group" id="figura-group" style="display:none">
                <label>Figura Profesional</label>
                <input type="text" name="figura" placeholder="Ej: Informática, Contabilidad, Electrónica...">
                <small style="color:#777">Dejar vacío si no aplica</small>
            </div>
            <div class="form-group">
                <label>Paralelos a generar</label>
                <div class="paralelos-check">
                    <?php foreach ($paralelos as $p): ?>
                    <label>
                        <input type="checkbox" name="paralelos_sel[]" value="<?= $p ?>"> <?= $p ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="btn">⚡ Generar Cursos</button>
        </form>
    </div>

    <!-- TAB MANUAL -->
    <div id="tab-manual" class="card" style="display:none">
        <h2>➕ Agregar Curso Manual</h2>
        <form method="POST">
            <input type="hidden" name="accion" value="agregar">
            <div class="grid2">
                <div class="form-group">
                    <label>Nombre del curso</label>
                    <input type="text" name="nombre" placeholder="Ej: 3ro BGU A — Matutina" required>
                </div>
                <div class="form-group">
                    <label>Nivel</label>
                    <select name="nivel">
                        <?php foreach (array_keys($estructura) as $n): ?>
                            <option value="<?= $n ?>"><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Jornada</label>
                    <select name="jornada">
                        <?php foreach ($jornadas as $j): ?>
                            <option value="<?= $j ?>"><?= $j ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn">➕ Agregar Curso</button>
        </form>
    </div>

    <!-- TAB EDITAR -->
    <?php if ($editar): ?>
    <div id="tab-editar_tab" class="card">
        <h2>✏️ Editar Curso</h2>
        <form method="POST">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" value="<?= $editar['id'] ?>">
            <div class="grid2">
                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="nombre" value="<?= htmlspecialchars($editar['nombre']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Nivel</label>
                    <select name="nivel">
                        <?php foreach (array_keys($estructura) as $n): ?>
                            <option value="<?= $n ?>" <?= $editar['nivel']==$n?'selected':'' ?>><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Jornada</label>
                    <select name="jornada">
                        <?php foreach ($jornadas as $j): ?>
                            <option value="<?= $j ?>" <?= $editar['jornada']==$j?'selected':'' ?>><?= $j ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn">💾 Guardar cambios</button>
            <a href="cursos.php" class="btn" style="background:#777;text-decoration:none;margin-left:10px;">Cancelar</a>
        </form>
    </div>
    <?php endif; ?>

    <!-- LISTA DE CURSOS AGRUPADOS -->
    <div class="card">
        <h2>📚 Cursos Registrados</h2>
        <?php if (empty($cursos_agrupados)): ?>
            <p style="color:#777">No hay cursos registrados aún. Usa el generador para crear los cursos.</p>
        <?php else: ?>
            <?php foreach ($cursos_agrupados as $jornada => $niveles): ?>
            <div class="seccion-jornada">
                <div class="titulo-jornada">🕐 Jornada <?= htmlspecialchars($jornada) ?></div>
                <?php foreach ($niveles as $nivel => $cursos): ?>
                <div class="titulo-nivel">📖 <?= htmlspecialchars($nivel) ?> — <?= count($cursos) ?> curso(s)</div>
                <div class="cursos-grid">
                    <?php foreach ($cursos as $c): ?>
                    <div class="curso-item">
                        <div>
                            <div class="curso-nombre"><?= htmlspecialchars($c['nombre']) ?></div>
                            <div class="curso-count">👥 <?= $c['total_estudiantes'] ?> estudiante(s)</div>
                        </div>
                        <div class="curso-acciones">
                            <a href="cursos.php?editar=<?= $c['id'] ?>" class="btn btn-green btn-sm" style="text-decoration:none">✏️</a>
                            <?php if ($c['total_estudiantes'] == 0): ?>
                            <form method="POST" onsubmit="return confirm('¿Eliminar?')" style="display:inline">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-red btn-sm">🗑</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function mostrarTab(nombre) {
    document.querySelectorAll('[id^="tab-"]').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + nombre).style.display = 'block';
    event.target.classList.add('active');
}
function mostrarFigura(nivel) {
    document.getElementById('figura-group').style.display = 
        nivel === 'Bachillerato Técnico' ? 'block' : 'none';
}
</script>

<?php footer_html(); $conn->close(); ?>
