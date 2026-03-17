<?php
require_once 'auth.php';
soloAdmin();
require_once 'config.php';
require_once 'header.php';

$conn = conectar();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'agregar' || $_POST['accion'] === 'editar') {
        $nombre = trim($_POST['nombre']);
        $telefono = normalizar_telefono($_POST['telefono']);
        if ($_POST['accion'] === 'agregar') {
            $stmt = $conn->prepare("INSERT INTO representantes (nombre, telefono) VALUES (?, ?)");
            $stmt->bind_param("ss", $nombre, $telefono);
            $msg = $stmt->execute() ? "Representante registrado correctamente" : "Error: " . $conn->error;
        } else {
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("UPDATE representantes SET nombre=?, telefono=? WHERE id=?");
            $stmt->bind_param("ssi", $nombre, $telefono, $id);
            $msg = $stmt->execute() ? "Representante actualizado" : "Error: " . $conn->error;
        }
    } elseif ($_POST['accion'] === 'desvincular') {
        $estudiante_id = intval($_POST['estudiante_id']);
        $representante_id = intval($_POST['representante_id']);
        $conn->query("DELETE FROM estudiante_representante WHERE estudiante_id = $estudiante_id AND representante_id = $representante_id");
        $msg = "Estudiante desvinculado correctamente";

    } elseif ($_POST['accion'] === 'eliminar') {
        $id = intval($_POST['id']);
        $check = $conn->query("SELECT COUNT(*) as t FROM estudiante_representante WHERE representante_id = $id")->fetch_assoc()['t'];
        if ($check > 0) {
            $err = "No se puede eliminar, tiene $check estudiante(s) vinculado(s). Desvincula primero.";
        } else {
            $conn->query("DELETE FROM representantes WHERE id = $id");
            $msg = "Representante eliminado";
        }
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $editar = $conn->query("SELECT * FROM representantes WHERE id = $id")->fetch_assoc();
}

// Obtener representantes ordenados por el curso de menor a mayor
$representantes = $conn->query("
    SELECT DISTINCT r.*
    FROM representantes r
    LEFT JOIN estudiante_representante er ON er.representante_id = r.id
    LEFT JOIN estudiantes e ON er.estudiante_id = e.id
    LEFT JOIN cursos c ON e.curso_id = c.id
    ORDER BY c.jornada, c.nivel, c.nombre, r.nombre
");

// Orden pedagógico para cursos
$orden_niveles = [
    'Inicial 1' => 1, 'Inicial 2' => 2,
    '1ro EGB' => 3, '2do EGB' => 4, '3ro EGB' => 5,
    '4to EGB' => 6, '5to EGB' => 7, '6to EGB' => 8, '7mo EGB' => 9,
    '8vo EGB' => 10, '9no EGB' => 11, '10mo EGB' => 12,
    '1ro BGU' => 13, '2do BGU' => 14, '3ro BGU' => 15,
    '1ro BT' => 16, '2do BT' => 17, '3ro BT' => 18,
];

function ordenCurso($nombre_curso, $orden_niveles) {
    foreach ($orden_niveles as $nivel => $pos) {
        if (strpos($nombre_curso, $nivel) !== false) return $pos;
    }
    return 999;
}

header_html('Representantes');
?>
<style>
.rep-card { background: white; border-radius: 10px; padding: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 12px; }
.rep-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.rep-info h3 { margin: 0; font-size: 15px; color: #333; }
.rep-info p { margin: 3px 0 0; font-size: 13px; color: #777; }
.estudiantes-lista { display: flex; flex-direction: column; gap: 5px; }
.estudiante-tag { background: #f8f9fa; border: 1px solid #ddd; border-radius: 6px; padding: 6px 12px; font-size: 13px; display: flex; align-items: center; gap: 8px; }
.estudiante-tag .principal-badge { background: #e6f4ea; color: #137333; padding: 2px 7px; border-radius: 10px; font-size: 11px; }
.grupo-curso-rep { margin-bottom: 8px; }
.grupo-curso-rep-titulo { font-size: 12px; font-weight: bold; color: #1a73e8; padding: 4px 0; border-bottom: 1px solid #e8f0fe; margin-bottom: 5px; }
.sin-estudiantes { color: #999; font-size: 13px; font-style: italic; }
</style>

<div class="container">
    <?php if ($msg): ?><div class="alerta exito">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alerta error">❌ <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="card">
        <h2><?= $editar ? '✏️ Editar Representante' : '➕ Registrar Representante' ?></h2>
        <form method="POST">
            <input type="hidden" name="accion" value="<?= $editar ? 'editar' : 'agregar' ?>">
            <?php if ($editar): ?><input type="hidden" name="id" value="<?= $editar['id'] ?>"><?php endif; ?>
            <div class="grid2">
                <div class="form-group">
                    <label>Nombre completo</label>
                    <input type="text" name="nombre" placeholder="Apellido Nombre" value="<?= htmlspecialchars($editar['nombre'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Teléfono WhatsApp</label>
                    <input type="text" name="telefono" placeholder="0987654321 o 593987654321" value="<?= htmlspecialchars($editar['telefono'] ?? '') ?>" required>
                    <small style="color:#777">Acepta formato 0987654321 o 593987654321</small>
                </div>
            </div>
            <button type="submit" class="btn"><?= $editar ? '💾 Guardar cambios' : '➕ Registrar' ?></button>
            <?php if ($editar): ?>
                <a href="representantes.php" class="btn" style="background:#777;text-decoration:none;margin-left:10px">Cancelar</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h2>👨‍👩‍👧 Lista de Representantes</h2>
        <?php while ($r = $representantes->fetch_assoc()):
            $estudiantes = $conn->query("
                SELECT e.id, e.nombre, c.nombre as curso, c.jornada, c.nivel, er.es_principal
                FROM estudiantes e
                JOIN estudiante_representante er ON er.estudiante_id = e.id
                LEFT JOIN cursos c ON e.curso_id = c.id
                WHERE er.representante_id = {$r['id']}
                ORDER BY c.jornada, c.nivel, c.nombre, e.nombre
            ");
            $est_arr = [];
            while ($e = $estudiantes->fetch_assoc()) $est_arr[] = $e;

            // Agrupar por curso
            $por_curso = [];
            foreach ($est_arr as $e) {
                $k = $e['curso'] ?? 'Sin curso';
                $por_curso[$k][] = $e;
            }

            // Ordenar cursos pedagógicamente
            uksort($por_curso, function($a, $b) use ($orden_niveles) {
                $oa = ordenCurso($a, $orden_niveles);
                $ob = ordenCurso($b, $orden_niveles);
                return $oa !== $ob ? $oa - $ob : strcmp($a, $b);
            });
        ?>
        <div class="rep-card">
            <div class="rep-header">
                <div class="rep-info">
                    <h3><?= htmlspecialchars($r['nombre']) ?></h3>
                    <p>📱 <?= $r['telefono'] ?> — <?= count($est_arr) ?> estudiante(s) vinculado(s)</p>
                </div>
                <div style="display:flex;gap:6px">
                    <a href="representantes.php?editar=<?= $r['id'] ?>" class="btn btn-green btn-sm" style="text-decoration:none">✏️</a>
                    <form method="POST" onsubmit="return confirm('¿Eliminar a <?= htmlspecialchars(addslashes($r['nombre'])) ?>?')">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-red btn-sm">🗑</button>
                    </form>
                </div>
            </div>

            <?php if (empty($est_arr)): ?>
                <span class="sin-estudiantes">Sin estudiantes vinculados</span>
            <?php else: ?>
                <div class="estudiantes-lista">
                    <?php foreach ($por_curso as $curso => $lista): ?>
                    <div class="grupo-curso-rep">
                        <div class="grupo-curso-rep-titulo">📚 <?= htmlspecialchars($curso) ?></div>
                        <?php foreach ($lista as $e): ?>
                        <div class="estudiante-tag" style="justify-content:space-between">
                            <span>
                                👤 <?= htmlspecialchars($e['nombre']) ?>
                                <?php if ($e['es_principal']): ?>
                                    <span class="principal-badge">⭐ Principal</span>
                                <?php endif; ?>
                            </span>
                            <form method="POST" style="margin:0" onsubmit="return confirm('¿Desvincular a <?= htmlspecialchars(addslashes($e['nombre'])) ?> de este representante?')">
                                <input type="hidden" name="accion" value="desvincular">
                                <input type="hidden" name="estudiante_id" value="<?= $e['id'] ?>">
                                <input type="hidden" name="representante_id" value="<?= $r['id'] ?>">
                                <button type="submit" style="background:none;border:none;color:#c5221f;cursor:pointer;font-size:15px;padding:0;line-height:1" title="Desvincular">✕</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
    </div>
</div>
<?php footer_html(); $conn->close(); ?>