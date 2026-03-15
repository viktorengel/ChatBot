<?php
require_once 'auth.php';
soloAdmin();
require_once 'config.php';
require_once 'header.php';

$conn = conectar();
$msg = '';
$err = '';

// Crear tablas de configuración si no existen
$conn->query("CREATE TABLE IF NOT EXISTS config_institucion (clave VARCHAR(100) PRIMARY KEY, valor TEXT)");
$conn->query("CREATE TABLE IF NOT EXISTS config_jornadas (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(50) NOT NULL UNIQUE, activa TINYINT(1) DEFAULT 1, orden INT DEFAULT 0)");
$conn->query("CREATE TABLE IF NOT EXISTS config_niveles (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL, codigo VARCHAR(20) NOT NULL, jornada_id INT NOT NULL, activo TINYINT(1) DEFAULT 1, orden INT DEFAULT 0, FOREIGN KEY (jornada_id) REFERENCES config_jornadas(id))");
$conn->query("CREATE TABLE IF NOT EXISTS config_paralelos (id INT AUTO_INCREMENT PRIMARY KEY, nivel_id INT NOT NULL, letra VARCHAR(5) NOT NULL, UNIQUE KEY unico (nivel_id, letra), FOREIGN KEY (nivel_id) REFERENCES config_niveles(id))");
$conn->query("CREATE TABLE IF NOT EXISTS config_figuras (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL UNIQUE)");

$niveles_sistema = [
    'Inicial' => [
        ['nombre' => 'Inicial 1', 'codigo' => 'INI1'],
        ['nombre' => 'Inicial 2', 'codigo' => 'INI2'],
    ],
    'Básica Elemental' => [
        ['nombre' => '1ro EGB', 'codigo' => '1BE'],
        ['nombre' => '2do EGB', 'codigo' => '2BE'],
        ['nombre' => '3ro EGB', 'codigo' => '3BE'],
    ],
    'Básica Media' => [
        ['nombre' => '4to EGB', 'codigo' => '4BM'],
        ['nombre' => '5to EGB', 'codigo' => '5BM'],
        ['nombre' => '6to EGB', 'codigo' => '6BM'],
        ['nombre' => '7mo EGB', 'codigo' => '7BM'],
    ],
    'Básica Superior' => [
        ['nombre' => '8vo EGB', 'codigo' => '8BS'],
        ['nombre' => '9no EGB', 'codigo' => '9BS'],
        ['nombre' => '10mo EGB', 'codigo' => '10BS'],
    ],
    'Bachillerato BGU' => [
        ['nombre' => '1ro BGU', 'codigo' => '1BGU'],
        ['nombre' => '2do BGU', 'codigo' => '2BGU'],
        ['nombre' => '3ro BGU', 'codigo' => '3BGU'],
    ],
    'Bachillerato Técnico' => [
        ['nombre' => '1ro BT', 'codigo' => '1BT'],
        ['nombre' => '2do BT', 'codigo' => '2BT'],
        ['nombre' => '3ro BT', 'codigo' => '3BT'],
    ],
];

$tab_activo = $_POST['tab_actual'] ?? $_GET['tab'] ?? 'inst';
$jornada_activa = $_POST['jornada_activa'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    if ($_POST['accion'] === 'guardar_institucion') {
        foreach (['nombre_institucion','ciudad','provincia','director','telefono_inst'] as $campo) {
            $valor = trim($_POST[$campo] ?? '');
            $stmt = $conn->prepare("INSERT INTO config_institucion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
            $stmt->bind_param("sss", $campo, $valor, $valor);
            $stmt->execute();
        }
        $msg = "Datos de la institución guardados";

    } elseif ($_POST['accion'] === 'guardar_jornadas') {
        $jornadas_sel = $_POST['jornadas'] ?? [];
        foreach (['Matutina','Vespertina','Nocturna'] as $i => $j) {
            $activa = in_array($j, $jornadas_sel) ? 1 : 0;
            $conn->query("INSERT INTO config_jornadas (nombre, activa, orden) VALUES ('$j', $activa, $i) ON DUPLICATE KEY UPDATE activa = $activa");
        }
        $msg = "Jornadas guardadas correctamente";

    } elseif ($_POST['accion'] === 'guardar_niveles') {
        $jornada_id = intval($_POST['jornada_id']);
        $niveles_sel = $_POST['niveles'] ?? [];

        $niveles_actuales = $conn->query("SELECT id, codigo FROM config_niveles WHERE jornada_id = $jornada_id");
        $codigos_existentes = [];
        while ($n = $niveles_actuales->fetch_assoc()) {
            $codigos_existentes[$n['codigo']] = $n['id'];
        }

        $conn->query("UPDATE config_niveles SET activo = 0 WHERE jornada_id = $jornada_id");

        foreach ($niveles_sel as $codigo) {
            $nombre_nivel = '';
            foreach ($niveles_sistema as $niveles) {
                foreach ($niveles as $n) {
                    if ($n['codigo'] === $codigo) { $nombre_nivel = $n['nombre']; break 2; }
                }
            }
            if ($nombre_nivel) {
                if (isset($codigos_existentes[$codigo])) {
                    $id_existente = $codigos_existentes[$codigo];
                    $conn->query("UPDATE config_niveles SET activo = 1 WHERE id = $id_existente");
                } else {
                    $conn->query("INSERT INTO config_niveles (nombre, codigo, jornada_id, activo) VALUES ('$nombre_nivel', '$codigo', $jornada_id, 1)");
                }
            }
        }
        $msg = "Niveles guardados correctamente";

    } elseif ($_POST['accion'] === 'guardar_todos_paralelos') {
        $jornada_id = intval($_POST['jornada_id']);
        $paralelos_post = $_POST['paralelos'] ?? [];
        $niveles = $conn->query("SELECT id FROM config_niveles WHERE jornada_id = $jornada_id AND activo = 1");
        while ($n = $niveles->fetch_assoc()) {
            $nivel_id = $n['id'];
            $conn->query("DELETE FROM config_paralelos WHERE nivel_id = $nivel_id");
            foreach ($paralelos_post[$nivel_id] ?? [] as $letra) {
                $conn->query("INSERT IGNORE INTO config_paralelos (nivel_id, letra) VALUES ($nivel_id, '$letra')");
            }
        }
        $msg = "Paralelos guardados correctamente";

    } elseif ($_POST['accion'] === 'eliminar_curso') {
        $id_curso = intval($_POST['id_curso']);
        $tiene_estudiantes = $conn->query("SELECT id FROM estudiantes WHERE curso_id = $id_curso LIMIT 1")->num_rows;
        if ($tiene_estudiantes > 0) {
            $err = "No se puede eliminar, tiene estudiantes asignados";
        } else {
            $conn->query("DELETE FROM docente_cursos WHERE curso_id = $id_curso");
            $conn->query("DELETE FROM cursos WHERE id = $id_curso");
            $msg = "Curso eliminado correctamente";
        }

    } elseif ($_POST['accion'] === 'agregar_figura') {
        $nombre = trim($_POST['nombre_figura']);
        if (!empty($nombre)) {
            $stmt = $conn->prepare("INSERT IGNORE INTO config_figuras (nombre) VALUES (?)");
            $stmt->bind_param("s", $nombre);
            $msg = $stmt->execute() ? "Figura agregada" : "Error: ya existe";
        }

    } elseif ($_POST['accion'] === 'eliminar_figura') {
        $conn->query("DELETE FROM config_figuras WHERE id = " . intval($_POST['id']));
        $msg = "Figura eliminada";

    } elseif ($_POST['accion'] === 'generar_cursos') {
        $generados = 0; $omitidos = 0;
        $jornadas = $conn->query("SELECT * FROM config_jornadas WHERE activa = 1 ORDER BY orden");
        while ($jornada = $jornadas->fetch_assoc()) {
            $niveles = $conn->query("SELECT * FROM config_niveles WHERE jornada_id = {$jornada['id']} AND activo = 1 ORDER BY id");
            while ($nivel = $niveles->fetch_assoc()) {
                $paralelos = $conn->query("SELECT letra FROM config_paralelos WHERE nivel_id = {$nivel['id']} ORDER BY letra");
                $letras = [];
                while ($p = $paralelos->fetch_assoc()) $letras[] = $p['letra'];
                if (empty($letras)) continue;

                if (strpos($nivel['codigo'], 'BT') !== false) {
                    $figuras = $conn->query("SELECT nombre FROM config_figuras ORDER BY nombre");
                    while ($fig = $figuras->fetch_assoc()) {
                        foreach ($letras as $letra) {
                            $nombre = "{$nivel['nombre']} \"{$letra}\" {$fig['nombre']} — {$jornada['nombre']}";
                            $check = $conn->query("SELECT id FROM cursos WHERE nombre = '" . $conn->real_escape_string($nombre) . "'");
                            if ($check->num_rows === 0) {
                                $conn->query("INSERT INTO cursos (nombre, nivel, jornada) VALUES ('" . $conn->real_escape_string($nombre) . "', '{$nivel['nombre']}', '{$jornada['nombre']}')");
                                $generados++;
                            } else $omitidos++;
                        }
                    }
                } else {
                    foreach ($letras as $letra) {
                        $nombre = "{$nivel['nombre']} \"{$letra}\" — {$jornada['nombre']}";
                        $check = $conn->query("SELECT id FROM cursos WHERE nombre = '" . $conn->real_escape_string($nombre) . "'");
                        if ($check->num_rows === 0) {
                            $conn->query("INSERT INTO cursos (nombre, nivel, jornada) VALUES ('" . $conn->real_escape_string($nombre) . "', '{$nivel['nombre']}', '{$jornada['nombre']}')");
                            $generados++;
                        } else $omitidos++;
                    }
                }
            }
        }
        $msg = "$generados curso(s) generados. $omitidos ya existían y fueron omitidos.";
    }
}

// Cargar datos
$inst = [];
$r = $conn->query("SELECT clave, valor FROM config_institucion");
while ($row = $r->fetch_assoc()) $inst[$row['clave']] = $row['valor'];

$jornadas_config = $conn->query("SELECT * FROM config_jornadas ORDER BY orden");
$jornadas_arr = [];
while ($j = $jornadas_config->fetch_assoc()) $jornadas_arr[] = $j;

$figuras = $conn->query("SELECT * FROM config_figuras ORDER BY nombre");
$figuras_arr = [];
while ($f = $figuras->fetch_assoc()) $figuras_arr[] = $f;

$total_cursos = $conn->query("SELECT COUNT(*) as t FROM cursos")->fetch_assoc()['t'];

header_html('Configuración');
?>
<style>
.config-layout { display: grid; grid-template-columns: 220px 1fr; gap: 20px; align-items: start; }
.config-tabs { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); overflow: hidden; position: sticky; top: 20px; }
.config-tab { display: flex; align-items: center; gap: 10px; padding: 13px 16px; cursor: pointer; font-size: 14px; color: #555; border-left: 3px solid transparent; }
.config-tab:hover { background: #f0f4ff; color: #1a73e8; }
.config-tab.activo { background: #e8f0fe; color: #1a73e8; font-weight: bold; border-left-color: #1a73e8; }
.config-panel { display: none; }
.config-panel.activo { display: block; }
.config-section { background: white; border-radius: 10px; padding: 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 15px; }
.config-section h3 { color: #1a73e8; margin-bottom: 18px; font-size: 16px; display: flex; align-items: center; gap: 8px; padding-bottom: 12px; border-bottom: 2px solid #e8f0fe; }
.jornada-tabs { display: flex; gap: 5px; margin-bottom: 15px; flex-wrap: wrap; }
.jornada-tab-btn { padding: 8px 16px; border-radius: 20px; cursor: pointer; font-size: 13px; border: 2px solid #1a73e8; color: #1a73e8; background: white; font-weight: bold; }
.jornada-tab-btn.activo { background: #1a73e8; color: white; }
.jornada-content { display: none; }
.jornada-content.activo { display: block; }
.niveles-columnas { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px; }
.nivel-grupo-card { background: #f8f9fa; border-radius: 8px; padding: 12px; border: 1px solid #eee; }
.nivel-grupo-titulo { font-size: 11px; font-weight: bold; color: #1a73e8; text-transform: uppercase; margin-bottom: 8px; padding-bottom: 5px; border-bottom: 1px solid #ddd; }
.nivel-check-item { display: flex; align-items: center; gap: 6px; padding: 4px 0; font-size: 13px; cursor: pointer; font-weight: normal; }
.nivel-check-item:hover { color: #1a73e8; }
.paralelos-tabla { width: 100%; border-collapse: collapse; font-size: 13px; }
.paralelos-tabla th { background: #e8f0fe; color: #1a73e8; padding: 8px 12px; text-align: left; font-size: 12px; }
.paralelos-tabla td { padding: 7px 12px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.paralelos-checks { display: flex; gap: 5px; flex-wrap: wrap; }
.par-btn { width: 30px; height: 30px; border-radius: 6px; border: 2px solid #ddd; background: white; cursor: pointer; font-size: 12px; font-weight: bold; color: #777; display: inline-flex; align-items: center; justify-content: center; padding: 0; transition: all 0.15s; }
.par-btn.sel { border-color: #1a73e8; background: #1a73e8; color: white; }
.par-btn:hover { border-color: #1a73e8; color: #1a73e8; }
.grupo-paralelos { margin-bottom: 15px; }
.grupo-paralelos-titulo { background: #e8f0fe; color: #1a73e8; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
.figuras-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; margin-bottom: 15px; }
.figura-card { background: #e8f0fe; border-radius: 8px; padding: 10px 12px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #1a73e8; }
.figura-card button { background: none; border: none; color: #c5221f; cursor: pointer; font-size: 16px; padding: 0; line-height: 1; }
.generar-box { background: linear-gradient(135deg, #1a73e8, #0d47a1); color: white; border-radius: 12px; padding: 25px; text-align: center; margin-bottom: 15px; }
.generar-box h3 { color: white; margin-bottom: 8px; }
.generar-box p { opacity: 0.85; font-size: 13px; margin-bottom: 15px; }
.stat-cursos { font-size: 48px; font-weight: bold; line-height: 1; }
.btn-generar { background: white; color: #1a73e8; border: none; padding: 12px 30px; border-radius: 8px; font-size: 15px; font-weight: bold; cursor: pointer; }
.btn-generar:hover { background: #e8f0fe; }
.cursos-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 5px; }
.curso-preview-item { background: #f8f9fa; border: 1px solid #eee; border-radius: 6px; padding: 5px 10px; font-size: 12px; color: #444; }
@media(max-width:700px) {
    .config-layout { grid-template-columns: 1fr; }
    .niveles-columnas { grid-template-columns: 1fr 1fr; }
}
</style>

<div class="container" style="max-width:1100px">
    <?php if ($msg): ?><div class="alerta exito">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alerta error">❌ <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="config-layout">

        <!-- TABS LATERALES -->
        <div class="config-tabs">
            <div class="config-tab <?= $tab_activo==='inst'?'activo':'' ?>" onclick="mostrarPanel('inst')">🏫 Institución</div>
            <div class="config-tab <?= $tab_activo==='jornadas'?'activo':'' ?>" onclick="mostrarPanel('jornadas')">🕐 Jornadas</div>
            <div class="config-tab <?= $tab_activo==='niveles'?'activo':'' ?>" onclick="mostrarPanel('niveles')">📚 Niveles y Paralelos</div>
            <div class="config-tab <?= $tab_activo==='figuras'?'activo':'' ?>" onclick="mostrarPanel('figuras')">🔧 Figuras Técnicas</div>
            <div class="config-tab <?= $tab_activo==='generar'?'activo':'' ?>" onclick="mostrarPanel('generar')">⚡ Generar Cursos</div>
        </div>

        <div>

            <!-- INSTITUCIÓN -->
            <div class="config-panel <?= $tab_activo==='inst'?'activo':'' ?>" id="panel-inst">
                <div class="config-section">
                    <h3>🏫 Datos de la Institución</h3>
                    <form method="POST">
                        <input type="hidden" name="accion" value="guardar_institucion">
                        <input type="hidden" name="tab_actual" value="inst">
                        <div class="grid2">
                            <div class="form-group">
                                <label>Nombre de la institución</label>
                                <input type="text" name="nombre_institucion" value="<?= htmlspecialchars($inst['nombre_institucion'] ?? '') ?>" placeholder="Unidad Educativa...">
                            </div>
                            <div class="form-group">
                                <label>Ciudad</label>
                                <input type="text" name="ciudad" value="<?= htmlspecialchars($inst['ciudad'] ?? '') ?>" placeholder="Quito">
                            </div>
                            <div class="form-group">
                                <label>Provincia</label>
                                <input type="text" name="provincia" value="<?= htmlspecialchars($inst['provincia'] ?? '') ?>" placeholder="Pichincha">
                            </div>
                            <div class="form-group">
                                <label>Director/Rector</label>
                                <input type="text" name="director" value="<?= htmlspecialchars($inst['director'] ?? '') ?>" placeholder="Nombre del director">
                            </div>
                        </div>
                        <button type="submit" class="btn">💾 Guardar datos</button>
                    </form>
                </div>
            </div>

            <!-- JORNADAS -->
            <div class="config-panel <?= $tab_activo==='jornadas'?'activo':'' ?>" id="panel-jornadas">
                <div class="config-section">
                    <h3>🕐 Jornadas Activas</h3>
                    <form method="POST">
                        <input type="hidden" name="accion" value="guardar_jornadas">
                        <input type="hidden" name="tab_actual" value="jornadas">
                        <div style="display:flex;gap:15px;flex-wrap:wrap;margin-bottom:20px">
                            <?php foreach (['Matutina','Vespertina','Nocturna'] as $j):
                                $activa = false;
                                foreach ($jornadas_arr as $ja) {
                                    if ($ja['nombre'] === $j && $ja['activa']) { $activa = true; break; }
                                }
                            ?>
                            <label style="display:flex;align-items:center;gap:8px;font-size:15px;cursor:pointer;font-weight:normal;background:<?= $activa?'#e8f0fe':'#f8f9fa' ?>;padding:12px 20px;border-radius:8px;border:2px solid <?= $activa?'#1a73e8':'#ddd' ?>">
                                <input type="checkbox" name="jornadas[]" value="<?= $j ?>" <?= $activa?'checked':'' ?> style="width:18px;height:18px">
                                <?= $j ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn">💾 Guardar jornadas</button>
                    </form>
                </div>
            </div>

            <!-- NIVELES Y PARALELOS -->
            <div class="config-panel <?= $tab_activo==='niveles'?'activo':'' ?>" id="panel-niveles">
                <div class="config-section">
                    <h3>📚 Niveles y Paralelos por Jornada</h3>
                    <?php
                    $jornadas_activas = array_filter($jornadas_arr, fn($j) => $j['activa']);
                    if (empty($jornadas_activas)):
                    ?>
                    <p style="color:#e65100">⚠️ Primero activa las jornadas en la pestaña anterior.</p>
                    <?php else: ?>

                    <div class="jornada-tabs">
                        <?php $primera = true; foreach ($jornadas_activas as $j): ?>
                        <button type="button" class="jornada-tab-btn <?= $primera?'activo':'' ?>" onclick="mostrarJornada('j<?= $j['id'] ?>', this)" id="btn-j<?= $j['id'] ?>">
                            <?= $j['nombre'] ?>
                        </button>
                        <?php $primera = false; endforeach; ?>
                    </div>

                    <?php $primera = true; foreach ($jornadas_activas as $jornada):
                        $niv_activos_q = $conn->query("SELECT * FROM config_niveles WHERE jornada_id = {$jornada['id']} AND activo = 1");
                        $niveles_ids_activos = [];
                        $paralelos_por_nivel = [];
                        while ($n = $niv_activos_q->fetch_assoc()) {
                            $niveles_ids_activos[] = $n['codigo'];
                            $pars = $conn->query("SELECT letra FROM config_paralelos WHERE nivel_id = {$n['id']} ORDER BY letra");
                            $letras = [];
                            while ($p = $pars->fetch_assoc()) $letras[] = $p['letra'];
                            $paralelos_por_nivel[$n['id']] = $letras;
                        }
                    ?>
                    <div class="jornada-content <?= $primera?'activo':'' ?>" id="j<?= $jornada['id'] ?>">

                        <!-- Selección niveles -->
                        <form method="POST" style="margin-bottom:20px">
                            <input type="hidden" name="accion" value="guardar_niveles">
                            <input type="hidden" name="jornada_id" value="<?= $jornada['id'] ?>">
                            <input type="hidden" name="tab_actual" value="niveles">
                            <input type="hidden" name="jornada_activa" value="j<?= $jornada['id'] ?>">
                            <p style="font-size:13px;color:#555;margin-bottom:10px;font-weight:bold">Selecciona los niveles activos:</p>
                            <div class="niveles-columnas">
                                <?php foreach ($niveles_sistema as $grupo => $niveles): ?>
                                <div class="nivel-grupo-card">
                                    <div class="nivel-grupo-titulo"><?= $grupo ?></div>
                                    <?php foreach ($niveles as $nivel):
                                        $activo = in_array($nivel['codigo'], $niveles_ids_activos);
                                    ?>
                                    <label class="nivel-check-item">
                                        <input type="checkbox" name="niveles[]" value="<?= $nivel['codigo'] ?>" <?= $activo?'checked':'' ?>>
                                        <?= htmlspecialchars($nivel['nombre']) ?>
                                    </label>
                                    <?php endforeach; // cursos ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="btn btn-sm">💾 Guardar niveles</button>
                        </form>

                        <!-- Paralelos agrupados por categoría -->
                        <?php
                        $niveles_guardados = $conn->query("SELECT * FROM config_niveles WHERE jornada_id = {$jornada['id']} AND activo = 1 ORDER BY id");
                        $niveles_g = [];
                        while ($n = $niveles_guardados->fetch_assoc()) $niveles_g[] = $n;

                        if (!empty($niveles_g)):
                            // Agrupar por categoría y ordenar según sistema educativo
                            $niveles_por_grupo = [];
                            $orden_codigos = [];
                            foreach ($niveles_sistema as $nombre_grupo => $lista) {
                                foreach ($lista as $n) $orden_codigos[$n['codigo']] = $nombre_grupo;
                            }
                            foreach ($niveles_g as $nivel) {
                                $grupo = $orden_codigos[$nivel['codigo']] ?? 'Otros';
                                $niveles_por_grupo[$grupo][] = $nivel;
                            }
                            // Ordenar grupos según orden del sistema educativo
                            $orden_grupos = array_keys($niveles_sistema);
                            uksort($niveles_por_grupo, function($a, $b) use ($orden_grupos) {
                                $pa = array_search($a, $orden_grupos);
                                $pb = array_search($b, $orden_grupos);
                                return ($pa === false ? 999 : $pa) - ($pb === false ? 999 : $pb);
                            });
                            // Ordenar niveles dentro de cada grupo
                            foreach ($niveles_por_grupo as $grp => &$nivs) {
                                $orden_niveles = array_column($niveles_sistema[$grp] ?? [], 'codigo');
                                usort($nivs, function($a, $b) use ($orden_niveles) {
                                    $pa = array_search($a['codigo'], $orden_niveles);
                                    $pb = array_search($b['codigo'], $orden_niveles);
                                    return ($pa === false ? 999 : $pa) - ($pb === false ? 999 : $pb);
                                });
                            }
                            unset($nivs);
                        ?>
                        <p style="font-size:13px;color:#555;margin-bottom:8px;font-weight:bold">Configura los paralelos:</p>
                        <form method="POST">
                            <input type="hidden" name="accion" value="guardar_todos_paralelos">
                            <input type="hidden" name="jornada_id" value="<?= $jornada['id'] ?>">
                            <input type="hidden" name="tab_actual" value="niveles">
                            <input type="hidden" name="jornada_activa" value="j<?= $jornada['id'] ?>">

                            <?php foreach ($niveles_por_grupo as $grupo => $niveles_grupo): ?>
                            <div class="grupo-paralelos">
                                <div class="grupo-paralelos-titulo">📖 <?= htmlspecialchars($grupo) ?></div>
                                <table class="paralelos-tabla">
                                    <tbody>
                                    <?php foreach ($niveles_grupo as $nivel):
                                        $letras_activas = $paralelos_por_nivel[$nivel['id']] ?? [];
                                    ?>
                                    <tr>
                                        <td style="font-weight:bold;white-space:nowrap;width:150px"><?= htmlspecialchars($nivel['nombre']) ?></td>
                                        <td>
                                            <div class="paralelos-checks">
                                                <?php foreach (['A','B','C','D','E','F','G','H'] as $letra): ?>
                                                <button type="button"
                                                    class="par-btn <?= in_array($letra, $letras_activas)?'sel':'' ?>"
                                                    onclick="toggleParalelo(this, '<?= $letra ?>', '<?= $nivel['id'] ?>')">
                                                    <?= $letra ?>
                                                </button>
                                                <?php endforeach; ?>
                                                <div id="hidden-par-<?= $nivel['id'] ?>">
                                                    <?php foreach ($letras_activas as $la): ?>
                                                    <input type="hidden" name="paralelos[<?= $nivel['id'] ?>][]" value="<?= $la ?>">
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-green" style="margin-top:5px">💾 Guardar todos los paralelos</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php $primera = false; endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- FIGURAS -->
            <div class="config-panel <?= $tab_activo==='figuras'?'activo':'' ?>" id="panel-figuras">
                <div class="config-section">
                    <h3>🔧 Figuras del Bachillerato Técnico</h3>
                    <p style="font-size:13px;color:#777;margin-bottom:15px">Se usarán al generar los cursos del Bachillerato Técnico.</p>
                    <div class="figuras-grid">
                        <?php foreach ($figuras_arr as $f): ?>
                        <div class="figura-card">
                            <?= htmlspecialchars($f['nombre']) ?>
                            <form method="POST" style="display:inline;margin:0">
                                <input type="hidden" name="accion" value="eliminar_figura">
                                <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                <input type="hidden" name="tab_actual" value="figuras">
                                <button type="submit" title="Eliminar">✕</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($figuras_arr)): ?>
                            <p style="color:#999;font-size:13px;font-style:italic;grid-column:1/-1">Sin figuras registradas</p>
                        <?php endif; ?>
                    </div>
                    <form method="POST" style="display:flex;gap:10px;align-items:flex-end;margin-top:10px">
                        <input type="hidden" name="accion" value="agregar_figura">
                        <input type="hidden" name="tab_actual" value="figuras">
                        <div class="form-group" style="flex:1;margin:0">
                            <label>Nueva figura profesional</label>
                            <input type="text" name="nombre_figura" placeholder="Ej: Informática, Contabilidad...">
                        </div>
                        <button type="submit" class="btn btn-green">➕ Agregar</button>
                    </form>
                </div>
            </div>

            <!-- GENERAR -->
            <div class="config-panel <?= $tab_activo==='generar'?'activo':'' ?>" id="panel-generar">
                <div class="generar-box">
                    <div class="stat-cursos"><?= $total_cursos ?></div>
                    <p style="margin-top:5px">cursos generados actualmente</p>
                    <h3 style="margin-top:15px">⚡ Generar todos los cursos</h3>
                    <p>Genera automáticamente todos los cursos. Los ya existentes no se duplican.</p>
                    <form method="POST">
                        <input type="hidden" name="accion" value="generar_cursos">
                        <input type="hidden" name="tab_actual" value="generar">
                        <button type="submit" class="btn-generar">⚡ Generar Cursos Ahora</button>
                    </form>
                </div>

                <?php if ($total_cursos > 0):
                    $cursos_lista = $conn->query("SELECT nombre, nivel, jornada FROM cursos ORDER BY jornada, nivel, nombre");
                    $por_jornada = [];
                    while ($c = $cursos_lista->fetch_assoc()) {
                        $cat = 'Otros';
                        foreach ($niveles_sistema as $nombre_cat => $lista) {
                            foreach ($lista as $n) {
                                if ($n['nombre'] === $c['nivel']) { $cat = $nombre_cat; break 2; }
                            }
                        }
                        $por_jornada[$c['jornada']][$cat][$c['nivel']][] = ['nombre' => $c['nombre'], 'id' => $c['id'] ?? null];
                    }
                    // Ordenar categorías según orden del sistema educativo
                    $orden_categorias = array_keys($niveles_sistema);
                    foreach ($por_jornada as $jornada => &$categorias) {
                        uksort($categorias, function($a, $b) use ($orden_categorias) {
                            $pos_a = array_search($a, $orden_categorias);
                            $pos_b = array_search($b, $orden_categorias);
                            $pos_a = $pos_a === false ? 999 : $pos_a;
                            $pos_b = $pos_b === false ? 999 : $pos_b;
                            return $pos_a - $pos_b;
                        });
                        // Ordenar niveles dentro de cada categoría
                        foreach ($categorias as $cat => &$niveles_cat) {
                            $orden_niveles = [];
                            if (isset($niveles_sistema[$cat])) {
                                $orden_niveles = array_column($niveles_sistema[$cat], 'nombre');
                            }
                            uksort($niveles_cat, function($a, $b) use ($orden_niveles) {
                                $pos_a = array_search($a, $orden_niveles);
                                $pos_b = array_search($b, $orden_niveles);
                                $pos_a = $pos_a === false ? 999 : $pos_a;
                                $pos_b = $pos_b === false ? 999 : $pos_b;
                                return $pos_a - $pos_b;
                            });
                        }
                        unset($niveles_cat);
                    }
                    unset($categorias);
                    // Obtener IDs
                    $cursos_ids = [];
                    $q_ids = $conn->query("SELECT id, nombre FROM cursos");
                    while ($ci = $q_ids->fetch_assoc()) $cursos_ids[$ci['nombre']] = $ci['id'];
                ?>
                <div class="config-section">
                    <h3>📋 Cursos generados</h3>
                    <?php foreach ($por_jornada as $jornada => $categorias): ?>
                    <div style="margin-bottom:18px">
                        <div style="background:#1557b0;color:white;padding:7px 14px;border-radius:6px;font-size:13px;font-weight:bold;margin-bottom:10px">
                            🕐 Jornada <?= htmlspecialchars($jornada) ?>
                        </div>
                        <?php foreach ($categorias as $categoria => $niveles): ?>
                        <div style="margin-bottom:12px">
                            <div style="background:#e8f0fe;color:#1a73e8;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:bold;text-transform:uppercase;margin-bottom:8px">
                                📖 <?= htmlspecialchars($categoria) ?>
                            </div>
                            <?php foreach ($niveles as $nivel => $cursos): ?>
                            <div style="margin-bottom:8px;padding-left:10px">
                                <div style="font-size:12px;color:#555;font-weight:bold;margin-bottom:4px">
                                    <?= htmlspecialchars($nivel) ?>
                                </div>
                                <div class="cursos-preview-grid">
                                <?php foreach ($cursos as $c_item):
                                    $nombre = $c_item['nombre'];
                                ?>
                                <div class="curso-preview-item" style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                                <span><?= htmlspecialchars($nombre) ?></span>
                                <?php
                                $curso_q = $conn->query("SELECT id FROM cursos WHERE nombre = '" . $conn->real_escape_string($nombre) . "' LIMIT 1");
                                $curso_row = $curso_q->fetch_assoc();
                                if ($curso_row):
                                ?>
                                <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('¿Eliminar este curso?')">
                                    <input type="hidden" name="accion" value="eliminar_curso">
                                    <input type="hidden" name="id_curso" value="<?= $curso_row['id'] ?>">
                                    <input type="hidden" name="tab_actual" value="generar">
                                    <button type="submit" style="background:none;border:none;color:#c5221f;cursor:pointer;font-size:16px;padding:0;line-height:1" title="Eliminar curso">✕</button>
                                </form>
                                <?php endif; ?>
                            </div>
                                <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
function mostrarPanel(id) {
    document.querySelectorAll('.config-panel').forEach(p => p.classList.remove('activo'));
    document.querySelectorAll('.config-tab').forEach(t => t.classList.remove('activo'));
    document.getElementById('panel-' + id).classList.add('activo');
    event.currentTarget.classList.add('activo');
}

function mostrarJornada(id, btn) {
    document.querySelectorAll('.jornada-content').forEach(j => j.classList.remove('activo'));
    document.querySelectorAll('.jornada-tab-btn').forEach(b => b.classList.remove('activo'));
    document.getElementById(id).classList.add('activo');
    btn.classList.add('activo');
}

function toggleParalelo(btn, letra, nivelId) {
    btn.classList.toggle('sel');
    var container = document.getElementById('hidden-par-' + nivelId);
    var existing = container.querySelector('input[value="' + letra + '"]');
    if (existing) {
        existing.remove();
    } else {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'paralelos[' + nivelId + '][]';
        input.value = letra;
        container.appendChild(input);
    }
}

// Restaurar jornada activa después de guardar
var jornadaActiva = '<?= htmlspecialchars($jornada_activa) ?>';
if (jornadaActiva) {
    window.addEventListener('DOMContentLoaded', function() {
        var btn = document.getElementById('btn-' + jornadaActiva);
        if (btn) mostrarJornada(jornadaActiva, btn);
    });
}
</script>

<?php footer_html(); $conn->close(); ?>