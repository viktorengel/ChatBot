<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'header.php';

date_default_timezone_set('America/Guayaquil');
$conn = conectar();
$hoy  = date('Y-m-d');

$jornadas = $conn->query("SELECT * FROM config_jornadas WHERE activa = 1 ORDER BY orden");
$jornadas_arr = [];
while ($j = $jornadas->fetch_assoc()) $jornadas_arr[] = $j;

header_html('Registrar Falta');
?>
<style>
/* ── PESTAÑAS ── */
.tabs-nav {
    display: flex; gap: 0;
    border-bottom: 2px solid #e8eaed;
    margin-bottom: 16px;
}
.tab-btn {
    background: none; border: none; cursor: pointer;
    padding: 10px 18px; font-size: 14px; color: #777;
    border-bottom: 3px solid transparent; margin-bottom: -2px;
    transition: color 0.15s, border-color 0.15s;
    font-weight: 600; white-space: nowrap;
}
.tab-btn:hover { color: #1a73e8; }
.tab-btn.activo { color: #1a73e8; border-bottom-color: #1a73e8; }
.tab-panel { display: none; }
.tab-panel.activo { display: block; }

/* ── PESTAÑA 1: BÚSQUEDA ── */
.busqueda-grid { border: 1px solid #ddd; border-radius: 8px; overflow: hidden; margin-bottom: 15px; }
.busqueda-col { padding: 15px; }
.busqueda-col:first-child { border-bottom: 1px solid #ddd; background: #fafafa; }
.busqueda-col h4 { font-size: 13px; color: #1a73e8; font-weight: bold; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
.cascada-select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; margin-bottom: 8px; }
.buscador-wrap { position: relative; }
.buscador-wrap input { width: 100%; padding: 9px 9px 9px 32px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
.buscador-icon { position: absolute; left: 9px; top: 50%; transform: translateY(-50%); color: #999; font-size: 14px; }
.dropdown-lista { display: none; position: absolute; background: white; border: 1px solid #ddd; border-radius: 6px; width: 100%; max-height: 200px; overflow-y: auto; z-index: 100; box-shadow: 0 4px 15px rgba(0,0,0,0.12); top: 100%; left: 0; }
.dropdown-item { padding: 8px 12px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f0f0f0; }
.dropdown-item:hover { background: #e8f0fe; color: #1a73e8; }
.dropdown-item .subtexto { font-size: 11px; color: #777; display: block; }
.cascada-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 8px; }

/* ── PESTAÑA 2: LISTA POR CURSO ── */
.curso-selector { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 14px; }
.lista-curso-wrap { border: 1px solid #e8eaed; border-radius: 8px; overflow: hidden; }
.lista-curso-header {
    background: #f8f9fa; padding: 10px 14px;
    display: flex; justify-content: space-between; align-items: center;
    border-bottom: 1px solid #e8eaed;
    font-size: 13px; color: #555;
}
.lista-curso-header strong { color: #1a73e8; }
.lista-vacía { padding: 20px; text-align: center; color: #aaa; font-size: 13px; font-style: italic; }

/* Switch */
.switch-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 11px 14px; border-bottom: 1px solid #f1f3f4;
    transition: background 0.12s;
}
.switch-row:last-child { border-bottom: none; }
.switch-row:hover { background: #f8f9ff; }
.switch-nombre { font-size: 13.5px; color: #3c4043; }
.switch-nombre.ausente { color: #d93025; font-weight: 600; }

/* Toggle switch visual */
.switch {
    position: relative; width: 42px; height: 24px; flex-shrink: 0;
}
.switch input { opacity: 0; width: 0; height: 0; }
.switch-slider {
    position: absolute; inset: 0;
    background: #e0e0e0; border-radius: 24px;
    transition: background 0.2s;
    cursor: pointer;
}
.switch-slider::before {
    content: ''; position: absolute;
    width: 18px; height: 18px; border-radius: 50%;
    background: white; top: 3px; left: 3px;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.switch input:checked + .switch-slider { background: #d93025; }
.switch input:checked + .switch-slider::before { transform: translateX(18px); }

/* ── SELECCIONADOS (compartido) ── */
.seleccionados-seccion { padding: 12px 15px; border-top: 1px solid #ddd; background: white; }
.seleccionados-seccion h4 { font-size: 13px; color: #555; margin-bottom: 8px; }
.seleccionados-tags { display: flex; flex-wrap: wrap; gap: 6px; min-height: 32px; }
.sel-tag { background: #fce8e6; color: #d93025; border-radius: 20px; padding: 4px 10px 4px 12px; font-size: 12px; display: flex; align-items: center; gap: 5px; }
.sel-tag .quitar { background: #d93025; color: white; border: none; border-radius: 50%; width: 16px; height: 16px; font-size: 10px; cursor: pointer; padding: 0; display: flex; align-items: center; justify-content: center; line-height: 1; }
.sel-tag .quitar:hover { background: #b31412; }
.sin-seleccionados { color: #aaa; font-size: 13px; font-style: italic; padding: 4px 0; }
.accion-row { padding: 12px 15px; border-top: 1px solid #ddd; display: flex; gap: 10px; align-items: flex-end; background: #f8f9fa; }
.accion-row .form-group { margin: 0; flex: 0 0 180px; }

/* ── TABLA FALTAS ── */
.r-wrap { border-radius: 8px; border: 1px solid #e8eaed; overflow: hidden; width: 100%; }
.r-tabla { width: 100%; border-collapse: collapse; font-size: 12.5px; table-layout: fixed; }
.r-fila-header td { background: #f8f9fa; color: #5f6368; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 8px 12px; border-bottom: 2px solid #e8eaed; white-space: nowrap; }
.r-th-acc { text-align: right; padding-right: 14px !important; width: 90px; white-space: nowrap; }
.r-fila-fecha td { background: #1a73e8; color: white; font-size: 11.5px; font-weight: 600; padding: 5px 12px; }
.r-fila-curso td { background: #f1f3f4; color: #1557b0; font-size: 11px; font-weight: 700; padding: 4px 12px 4px 22px; border-top: 1px solid #e8eaed; border-bottom: 1px solid #e8eaed; white-space: normal !important; word-break: break-word !important; overflow: visible !important; line-height: 1.35; }
.r-fila td { padding: 8px 12px; border-bottom: 1px solid #f1f3f4; color: #3c4043; font-size: 12.5px; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.r-fila td:first-child { padding-left: 24px; }
.r-fila:last-child td { border-bottom: none; }
.r-fila:hover td { background: #f8f9ff; }
.r-col-acc { width: 58px; text-align: right; padding-right: 10px !important; white-space: nowrap; }
.r-acc-inner { display: inline-flex; align-items: center; gap: 4px; justify-content: flex-end; }
.r-ico { font-size: 15px; }
.btn-del-r { background: none; border: none; cursor: pointer; color: #999; padding: 4px; border-radius: 4px; transition: color 0.15s, background 0.15s; display: flex; align-items: center; }
.btn-del-r svg { display: block; }
.btn-del-r:hover { color: #d93025; background: #fce8e6; }

/* ── RESPONSIVE ── */
@media (max-width: 600px) {
    .tab-btn { padding: 9px 12px; font-size: 13px; }
    .cascada-grid, .curso-selector { grid-template-columns: 1fr; }
    .busqueda-col { padding: 12px; }
    .accion-row { flex-direction: column; align-items: stretch; }
    .accion-row .form-group { flex: unset !important; width: 100%; }
    .accion-row .btn { width: 100%; text-align: center; margin-bottom: 4px; }
    select[multiple] { height: 110px; font-size: 16px; }
    .cascada-select, #buscador-nombre { font-size: 16px !important; }
    .r-wrap { border-radius: 6px; }
    .r-tabla { font-size: 11px; }
    .r-fila-header td { padding: 6px 8px; font-size: 10px; }
    .r-th-acc { padding-right: 8px !important; width: 60px; }
    .r-fila-fecha td { padding: 5px 8px; font-size: 11px; }
    .r-fila-curso td { padding: 3px 8px 3px 12px; font-size: 10px; }
    .r-fila td { padding: 7px 8px; }
    .r-fila td:first-child { padding-left: 12px; white-space: normal; word-break: break-word; overflow: visible; }
    .r-col-acc { width: 56px; padding-right: 8px !important; }
    .r-ico { font-size: 14px; }
    .btn-del-r svg { width: 12px; height: 12px; }
    input[type=date], select { font-size: 16px !important; }
    .switch-nombre { font-size: 13px; }
}
@media (max-width: 380px) {
    .card h2 { font-size: 15px; }
    .tab-btn { padding: 8px 10px; font-size: 12px; }
}
</style>

<div class="container">

    <?php if (isset($_GET['msg'])): ?>
        <div class="alerta exito">✅ <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['err'])): ?>
        <div class="alerta error">❌ <?= htmlspecialchars($_GET['err']) ?></div>
    <?php endif; ?>

    <!-- FORMULARIO REGISTRO -->
    <div class="card">
        <h2>📝 Registrar Falta</h2>
        <form action="enviar_multiple.php" method="POST">
            <div id="hidden-estudiantes"></div>

            <!-- PESTAÑAS -->
            <div class="tabs-nav">
                <button type="button" class="tab-btn activo" onclick="cambiarTab('busqueda')">🔍 Buscar estudiante</button>
                <button type="button" class="tab-btn"        onclick="cambiarTab('lista')">📋 Lista por curso</button>
            </div>

            <!-- PESTAÑA 1: BÚSQUEDA INDIVIDUAL -->
            <div id="tab-busqueda" class="tab-panel activo">
                <div class="busqueda-grid">
                    <div class="busqueda-col">
                        <h4>🔍 Buscar por nombre</h4>
                        <div class="buscador-wrap" style="position:relative">
                            <span class="buscador-icon">🔍</span>
                            <input type="text" id="buscador-nombre" placeholder="Escribe apellido o nombre..." oninput="buscarPorNombre()" autocomplete="off">
                            <div class="dropdown-lista" id="lista-nombre"></div>
                        </div>
                    </div>
                    <div class="busqueda-col">
                        <h4>🗂️ Buscar por curso</h4>
                        <div class="cascada-grid">
                            <select class="cascada-select" id="sel-jornada" onchange="cargarNiveles()" style="margin:0">
                                <option value="">— Jornada —</option>
                                <?php foreach ($jornadas_arr as $j): ?>
                                <option value="<?= htmlspecialchars($j['nombre']) ?>"><?= htmlspecialchars($j['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select class="cascada-select" id="sel-nivel" onchange="cargarCursos()" disabled style="margin:0">
                                <option value="">— Nivel —</option>
                            </select>
                            <select class="cascada-select" id="sel-curso" onchange="cargarEstudiantesCurso()" disabled style="margin:0">
                                <option value="">— Curso —</option>
                            </select>
                        </div>
                        <select class="cascada-select" id="sel-estudiantes-curso" multiple style="height:90px;margin:0" onchange="agregarDesdeCascada()"></select>
                        <small style="color:#777;font-size:11px">Ctrl+clic o toca para seleccionar varios</small>
                    </div>
                </div>
            </div>

            <!-- PESTAÑA 2: LISTA POR CURSO CON SWITCHES -->
            <div id="tab-lista" class="tab-panel">
                <div class="curso-selector">
                    <select class="cascada-select" id="lista-jornada" onchange="listaCargarNiveles()" style="margin:0">
                        <option value="">— Jornada —</option>
                        <?php foreach ($jornadas_arr as $j): ?>
                        <option value="<?= htmlspecialchars($j['nombre']) ?>"><?= htmlspecialchars($j['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="cascada-select" id="lista-nivel" onchange="listaCargarCursos()" disabled style="margin:0">
                        <option value="">— Nivel —</option>
                    </select>
                    <select class="cascada-select" id="lista-curso-id" onchange="listaCargarEstudiantes()" disabled style="margin:0">
                        <option value="">— Curso —</option>
                    </select>
                </div>
                <div class="lista-curso-wrap">
                    <div id="lista-curso-contenido" class="lista-vacía">
                        Selecciona una jornada, nivel y curso para ver la lista
                    </div>
                </div>
            </div>

            <!-- SELECCIONADOS (compartido por ambas pestañas) -->
            <div class="seleccionados-seccion">
                <h4>🚫 Ausentes seleccionados (<span id="contador">0</span>)</h4>
                <div class="seleccionados-tags" id="seleccionados-tags">
                    <span class="sin-seleccionados" id="sin-seleccionados">Ningún estudiante seleccionado aún</span>
                </div>
            </div>

            <!-- FECHA Y BOTÓN -->
            <div class="accion-row">
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" name="fecha" value="<?= $hoy ?>" required>
                </div>
                <button type="submit" class="btn" id="btn-registrar" disabled style="opacity:0.5;margin-bottom:0">
                    📤 Registrar Faltas
                </button>
                <button type="button" class="btn" style="background:#777;margin-bottom:0" onclick="limpiarTodo()">
                    🗑 Limpiar
                </button>
            </div>
        </form>
    </div>

    <!-- TABLA DE TODAS LAS FALTAS -->
    <div class="card">
        <?php
        $dias_es = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        $dia_hoy = $dias_es[date('w')];
        $hoy_fmt = date('d/m/Y');
        ?>
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px">
            <h2 style="margin:0">📋 Faltas Registradas &mdash; <span id="titulo-fecha"><?= $dia_hoy ?>, <?= $hoy_fmt ?></span></h2>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <label style="font-size:13px;color:#555;font-weight:600">📅 Consultar:</label>
                <input type="date" id="filtro-fecha-tabla"
                    value="<?= $hoy ?>"
                    max="<?= $hoy ?>"
                    style="padding:5px 8px;border:1px solid #ccc;border-radius:6px;font-size:13px;cursor:pointer"
                    onchange="cambiarFechaTabla(this.value)">
                <button onclick="cambiarFechaTabla('<?= $hoy ?>')" title="Volver a hoy"
                    style="padding:5px 10px;background:#1a73e8;color:#fff;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600">Hoy</button>
            </div>
        </div>
        <div id="tabla-faltas">
        <?php
        $es_admin     = esAdmin();
        $trash        = "<svg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='3 6 5 6 21 6'/><path d='M19 6l-1 14H6L5 6'/><path d='M10 11v6'/><path d='M14 11v6'/><path d='M9 6V4h6v2'/></svg>";
        $cols         = 2;
        $fecha_filtro = $hoy;

        if ($es_admin) {
            $rs = $conn->query("
                SELECT f.id, f.fecha, f.mensaje_enviado, e.nombre as estudiante, c.nombre as curso
                FROM faltas f
                JOIN estudiantes e ON f.estudiante_id = e.id
                LEFT JOIN cursos c ON e.curso_id = c.id
                WHERE DATE(f.fecha) = '$fecha_filtro'
                ORDER BY c.nombre, e.nombre
            ");
        } else {
            $did = $_SESSION['docente_id'];
            $rs  = $conn->query("
                SELECT f.id, f.fecha, f.mensaje_enviado, e.nombre as estudiante, c.nombre as curso
                FROM faltas f
                JOIN estudiantes e ON f.estudiante_id = e.id
                LEFT JOIN cursos c ON e.curso_id = c.id
                JOIN docente_cursos dc ON dc.curso_id = e.curso_id
                WHERE dc.docente_id = $did AND DATE(f.fecha) = '$fecha_filtro'
                ORDER BY c.nombre, e.nombre
            ");
        }
        $filas = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];

        if (empty($filas)) {
            echo "<p style='text-align:center;color:#aaa;padding:20px 0'>No hay faltas registradas para esta fecha</p>";
        } else {
            $th_acc    = $es_admin ? "WhatsApp&nbsp;&nbsp;Eliminar" : "WhatsApp";
            $curso_act = null;
            echo "<div class='r-wrap'><table class='r-tabla'><tbody>"
               . "<tr class='r-fila-header'><td>Estudiante</td><td class='r-th-acc'>$th_acc</td></tr>";
            foreach ($filas as $f) {
                $curso_str = $f['curso'] ?? '—';
                if ($curso_str !== $curso_act) {
                    $curso_act = $curso_str;
                    echo "<tr class='r-fila-curso'><td colspan='$cols'>📚 " . htmlspecialchars($curso_str) . "</td></tr>";
                }
                $nom     = htmlspecialchars($f['estudiante']);
                $ico     = $f['mensaje_enviado'] ? '✅' : '❌';
                $fid     = $f['id'];
                $del_btn = $es_admin
                    ? "<button class='btn-del-r' onclick=\"if(confirm('¿Eliminar?')) location.href='eliminar_falta.php?id=$fid'\">$trash</button>"
                    : "";
                echo "<tr class='r-fila'>"
                   . "<td>$nom</td>"
                   . "<td class='r-col-acc'><span class='r-acc-inner'><span class='r-ico'>$ico</span>$del_btn</span></td>"
                   . "</tr>";
            }
            echo "</tbody></table></div>";
        }
        ?>
        </div>
    </div>
</div>

<script>
var todosEstudiantes = [];
var seleccionados    = {};

fetch('estudiantes_ajax.php?hoy=<?= $hoy ?>')
    .then(r => r.json())
    .then(data => { todosEstudiantes = data; });

// ══════════════════════════════════════
// PESTAÑAS
// ══════════════════════════════════════
function cambiarTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('activo'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('activo'));
    document.getElementById('tab-' + tab).classList.add('activo');
    event.currentTarget.classList.add('activo');
}

// ══════════════════════════════════════
// PESTAÑA 1 — CASCADA (búsqueda individual)
// ══════════════════════════════════════
function cargarNiveles() {
    var jornada = document.getElementById('sel-jornada').value;
    var sel = document.getElementById('sel-nivel');
    sel.innerHTML = '<option value="">— Nivel —</option>';
    sel.disabled = !jornada;
    document.getElementById('sel-curso').innerHTML = '<option value="">— Curso —</option>';
    document.getElementById('sel-curso').disabled = true;
    document.getElementById('sel-estudiantes-curso').innerHTML = '';
    if (!jornada) return;
    fetch('cascada_ajax.php?accion=niveles&jornada=' + encodeURIComponent(jornada))
        .then(r => r.json())
        .then(niveles => {
            niveles.forEach(n => sel.innerHTML += '<option value="' + n + '">' + n + '</option>');
            sel.disabled = false;
        });
}
function cargarCursos() {
    var jornada = document.getElementById('sel-jornada').value;
    var nivel   = document.getElementById('sel-nivel').value;
    var sel     = document.getElementById('sel-curso');
    sel.innerHTML = '<option value="">— Curso —</option>';
    sel.disabled  = !nivel;
    document.getElementById('sel-estudiantes-curso').innerHTML = '';
    if (!nivel) return;
    fetch('cascada_ajax.php?accion=cursos&jornada=' + encodeURIComponent(jornada) + '&nivel=' + encodeURIComponent(nivel))
        .then(r => r.json())
        .then(cursos => {
            cursos.forEach(c => sel.innerHTML += '<option value="' + c.id + '">' + c.nombre + '</option>');
            sel.disabled = false;
        });
}
function cargarEstudiantesCurso() {
    var curso_id = document.getElementById('sel-curso').value;
    var sel      = document.getElementById('sel-estudiantes-curso');
    sel.innerHTML = '';
    if (!curso_id) return;
    var disponibles = todosEstudiantes.filter(e => e.curso_id == curso_id && !seleccionados[e.id]);
    if (disponibles.length === 0) { sel.innerHTML = '<option disabled>Sin estudiantes disponibles</option>'; return; }
    disponibles.forEach(e => {
        sel.innerHTML += '<option value="' + e.id + '" data-nombre="' + e.nombre + '" data-curso="' + e.curso + '">' + e.nombre + '</option>';
    });
}
function agregarDesdeCascada() {
    var sel = document.getElementById('sel-estudiantes-curso');
    Array.from(sel.selectedOptions).forEach(opt => agregarEstudiante(opt.value, opt.getAttribute('data-nombre'), opt.getAttribute('data-curso')));
    Array.from(sel.options).forEach(o => o.selected = false);
    cargarEstudiantesCurso();
}

// ══════════════════════════════════════
// PESTAÑA 1 — BUSCADOR POR NOMBRE
// ══════════════════════════════════════
function buscarPorNombre() {
    var q     = document.getElementById('buscador-nombre').value.toLowerCase().trim();
    var lista = document.getElementById('lista-nombre');
    lista.innerHTML = '';
    if (q.length < 1) { lista.style.display = 'none'; return; }
    var filtrados = todosEstudiantes.filter(e => e.nombre.toLowerCase().includes(q) && !seleccionados[e.id]);
    if (filtrados.length === 0) {
        lista.innerHTML = '<div class="dropdown-item" style="color:#999">Sin resultados</div>';
    } else {
        filtrados.forEach(e => {
            var div = document.createElement('div');
            div.className = 'dropdown-item';
            div.innerHTML = e.nombre + '<span class="subtexto">' + e.curso + '</span>';
            div.addEventListener('click', function() {
                agregarEstudiante(e.id, e.nombre, e.curso);
                document.getElementById('buscador-nombre').value = '';
                lista.style.display = 'none';
            });
            lista.appendChild(div);
        });
    }
    lista.style.display = 'block';
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.buscador-wrap'))
        document.querySelectorAll('.dropdown-lista').forEach(l => l.style.display = 'none');
});

// ══════════════════════════════════════
// PESTAÑA 2 — LISTA POR CURSO CON SWITCHES
// ══════════════════════════════════════
function listaCargarNiveles() {
    var jornada = document.getElementById('lista-jornada').value;
    var sel     = document.getElementById('lista-nivel');
    sel.innerHTML = '<option value="">— Nivel —</option>';
    sel.disabled  = !jornada;
    document.getElementById('lista-curso-id').innerHTML = '<option value="">— Curso —</option>';
    document.getElementById('lista-curso-id').disabled  = true;
    document.getElementById('lista-curso-contenido').innerHTML = '<p class="lista-vacía">Selecciona nivel y curso</p>';
    if (!jornada) return;
    fetch('cascada_ajax.php?accion=niveles&jornada=' + encodeURIComponent(jornada))
        .then(r => r.json())
        .then(niveles => {
            niveles.forEach(n => sel.innerHTML += '<option value="' + n + '">' + n + '</option>');
            sel.disabled = false;
        });
}
function listaCargarCursos() {
    var jornada = document.getElementById('lista-jornada').value;
    var nivel   = document.getElementById('lista-nivel').value;
    var sel     = document.getElementById('lista-curso-id');
    sel.innerHTML = '<option value="">— Curso —</option>';
    sel.disabled  = !nivel;
    document.getElementById('lista-curso-contenido').innerHTML = '<p class="lista-vacía">Selecciona el curso</p>';
    if (!nivel) return;
    fetch('cascada_ajax.php?accion=cursos&jornada=' + encodeURIComponent(jornada) + '&nivel=' + encodeURIComponent(nivel))
        .then(r => r.json())
        .then(cursos => {
            cursos.forEach(c => sel.innerHTML += '<option value="' + c.id + '" data-nombre="' + c.nombre + '">' + c.nombre + '</option>');
            sel.disabled = false;
        });
}
function listaCargarEstudiantes() {
    var sel      = document.getElementById('lista-curso-id');
    var curso_id = sel.value;
    var contenido = document.getElementById('lista-curso-contenido');

    if (!curso_id) {
        contenido.innerHTML = '<p class="lista-vacía">Selecciona el curso</p>';
        return;
    }

    var curso_nombre = sel.options[sel.selectedIndex].getAttribute('data-nombre') || '';
    var estudiantes  = todosEstudiantes.filter(e => e.curso_id == curso_id);

    if (estudiantes.length === 0) {
        contenido.innerHTML = '<p class="lista-vacía">No hay estudiantes en este curso</p>';
        return;
    }

    var header = '<div class="lista-curso-header">'
               + '<strong>' + (curso_nombre || 'Curso') + '</strong>'
               + '<span>' + estudiantes.length + ' estudiantes</span>'
               + '</div>';

    var filas = estudiantes.map(function(e) {
        var marcado   = seleccionados[e.id] ? 'checked' : '';
        var cls_nombre = seleccionados[e.id] ? 'switch-nombre ausente' : 'switch-nombre';
        return '<div class="switch-row" id="sw-row-' + e.id + '">'
             + '<span class="' + cls_nombre + '" id="sw-label-' + e.id + '">' + e.nombre + '</span>'
             + '<label class="switch">'
             + '<input type="checkbox" ' + marcado + ' onchange="toggleAusente(' + e.id + ', \'' + e.nombre.replace(/'/g, "\\'") + '\', \'' + (e.curso || '').replace(/'/g, "\\'") + '\')">'
             + '<span class="switch-slider"></span>'
             + '</label>'
             + '</div>';
    }).join('');

    contenido.innerHTML = header + filas;
}

function toggleAusente(id, nombre, curso) {
    if (seleccionados[id]) {
        // Quitar ausente
        quitarEstudiante(id);
        var row   = document.getElementById('sw-row-'   + id);
        var label = document.getElementById('sw-label-' + id);
        if (label) { label.className = 'switch-nombre'; }
        // desmarcar el switch si aún existe
        var chk = row ? row.querySelector('input[type=checkbox]') : null;
        if (chk) chk.checked = false;
    } else {
        // Agregar ausente
        agregarEstudiante(id, nombre, curso);
        var label = document.getElementById('sw-label-' + id);
        if (label) label.className = 'switch-nombre ausente';
    }
}

// ══════════════════════════════════════
// SELECCIONADOS (compartido)
// ══════════════════════════════════════
function agregarEstudiante(id, nombre, curso) {
    if (seleccionados[id]) return;
    seleccionados[id] = { nombre: nombre, curso: curso };
    document.getElementById('sin-seleccionados').style.display = 'none';
    var tag = document.createElement('div');
    tag.className = 'sel-tag'; tag.id = 'tag-' + id;
    tag.innerHTML = nombre + ' <button type="button" class="quitar" onclick="quitarEstudiante(' + id + ')">✕</button>';
    document.getElementById('seleccionados-tags').appendChild(tag);
    var input = document.createElement('input');
    input.type = 'hidden'; input.name = 'estudiantes[]'; input.value = id; input.id = 'hi-' + id;
    document.getElementById('hidden-estudiantes').appendChild(input);
    actualizarContador();
}
function quitarEstudiante(id) {
    delete seleccionados[id];
    var t = document.getElementById('tag-' + id); if (t) t.remove();
    var h = document.getElementById('hi-'  + id); if (h) h.remove();
    // Actualizar switch en pestaña 2 si está visible
    var label = document.getElementById('sw-label-' + id);
    if (label) { label.className = 'switch-nombre'; }
    var row = document.getElementById('sw-row-' + id);
    var chk = row ? row.querySelector('input[type=checkbox]') : null;
    if (chk) chk.checked = false;
    actualizarContador();
    cargarEstudiantesCurso(); // refresca la lista de cascada tab1
}
function actualizarContador() {
    var total = Object.keys(seleccionados).length;
    document.getElementById('contador').textContent = total;
    var btn = document.getElementById('btn-registrar');
    btn.disabled      = total === 0;
    btn.style.opacity = total > 0 ? '1' : '0.5';
    document.getElementById('sin-seleccionados').style.display = total === 0 ? 'inline' : 'none';
}
function limpiarTodo() {
    seleccionados = {};
    document.getElementById('seleccionados-tags').innerHTML = '<span class="sin-seleccionados" id="sin-seleccionados">Ningún estudiante seleccionado aún</span>';
    document.getElementById('hidden-estudiantes').innerHTML = '';
    // Pestaña 1
    document.getElementById('buscador-nombre').value = '';
    document.getElementById('lista-nombre').style.display = 'none';
    document.getElementById('sel-jornada').value = '';
    document.getElementById('sel-nivel').innerHTML = '<option value="">— Nivel —</option>';
    document.getElementById('sel-nivel').disabled = true;
    document.getElementById('sel-curso').innerHTML = '<option value="">— Curso —</option>';
    document.getElementById('sel-curso').disabled = true;
    document.getElementById('sel-estudiantes-curso').innerHTML = '';
    // Pestaña 2: recargar lista para desmarcar switches
    listaCargarEstudiantes();
    actualizarContador();
}

// ── REFRESCO TABLA ──
var fechaConsultaTabla = '<?= $hoy ?>';

function cambiarFechaTabla(fecha) {
    fechaConsultaTabla = fecha;
    document.getElementById('filtro-fecha-tabla').value = fecha;
    var partes = fecha.split('-');
    var d    = new Date(partes[0], partes[1]-1, partes[2]);
    var dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    var fmt  = partes[2]+'/'+partes[1]+'/'+partes[0];
    document.getElementById('titulo-fecha').textContent = dias[d.getDay()] + ', ' + fmt;
    refrescarTabla();
}
function refrescarTabla() {
    fetch('faltas_ajax.php?fecha=' + encodeURIComponent(fechaConsultaTabla))
        .then(r => r.json())
        .then(data => { document.getElementById('tabla-faltas').innerHTML = data.html; })
        .catch(() => {});
}
setInterval(function(){
    if (fechaConsultaTabla === '<?= $hoy ?>') refrescarTabla();
}, 60000);
</script>

<?php footer_html(); $conn->close(); ?>