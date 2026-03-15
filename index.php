<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'header.php';

date_default_timezone_set('America/Guayaquil');
$conn = conectar();
$hoy = date('Y-m-d');

$jornadas = $conn->query("SELECT * FROM config_jornadas WHERE activa = 1 ORDER BY orden");
$jornadas_arr = [];
while ($j = $jornadas->fetch_assoc()) $jornadas_arr[] = $j;

header_html('Registrar Falta');
?>
<style>
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
.seleccionados-seccion { padding: 12px 15px; border-top: 1px solid #ddd; background: white; }
.seleccionados-seccion h4 { font-size: 13px; color: #555; margin-bottom: 8px; }
.seleccionados-tags { display: flex; flex-wrap: wrap; gap: 6px; min-height: 32px; }
.sel-tag { background: #e8f0fe; color: #1a73e8; border-radius: 20px; padding: 4px 10px 4px 12px; font-size: 12px; display: flex; align-items: center; gap: 5px; }
.sel-tag .quitar { background: #1a73e8; color: white; border: none; border-radius: 50%; width: 16px; height: 16px; font-size: 10px; cursor: pointer; padding: 0; display: flex; align-items: center; justify-content: center; line-height: 1; }
.sel-tag .quitar:hover { background: #c5221f; }
.sin-seleccionados { color: #aaa; font-size: 13px; font-style: italic; padding: 4px 0; }
.accion-row { padding: 12px 15px; border-top: 1px solid #ddd; display: flex; gap: 10px; align-items: flex-end; background: #f8f9fa; }
.accion-row .form-group { margin: 0; flex: 0 0 180px; }

</style>

<div class="container">

    <?php if (isset($_GET['msg'])): ?>
        <div class="alerta exito">✅ <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['err'])): ?>
        <div class="alerta error">❌ <?= htmlspecialchars($_GET['err']) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>📝 Registrar Falta</h2>
        <form action="enviar_multiple.php" method="POST">
            <div id="hidden-estudiantes"></div>

            <!-- DOS MÉTODOS DE BÚSQUEDA -->
            <div class="busqueda-grid">

                <!-- MÉTODO 1: BUSCADOR POR NOMBRE -->
                <div class="busqueda-col">
                    <h4>🔍 Buscar por nombre</h4>
                    <div class="buscador-wrap" style="position:relative">
                        <span class="buscador-icon">🔍</span>
                        <input type="text" id="buscador-nombre" placeholder="Escribe apellido o nombre..." oninput="buscarPorNombre()" autocomplete="off">
                        <div class="dropdown-lista" id="lista-nombre"></div>
                    </div>
                </div>

                <!-- MÉTODO 2: FILTROS EN CASCADA -->
                <div class="busqueda-col">
                    <h4>🗂️ Buscar por curso</h4>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:8px">
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
                    <select class="cascada-select" id="sel-estudiantes-curso" multiple style="height:90px;margin:0" onchange="agregarDesdeCascada()">
                    </select>
                    <small style="color:#777;font-size:11px">Ctrl+clic para seleccionar varios a la vez</small>
                </div>
            </div>

            <!-- ESTUDIANTES SELECCIONADOS -->
            <div class="seleccionados-seccion">
                <h4>👥 Estudiantes seleccionados (<span id="contador">0</span>)</h4>
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
var todosEstudiantes = [];
var seleccionados = {};

fetch('estudiantes_ajax.php?hoy=<?= $hoy ?>')
    .then(r => r.json())
    .then(data => { todosEstudiantes = data; });

// ===== CASCADA =====
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
    var nivel = document.getElementById('sel-nivel').value;
    var sel = document.getElementById('sel-curso');
    sel.innerHTML = '<option value="">— Curso —</option>';
    sel.disabled = !nivel;
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
    var sel = document.getElementById('sel-estudiantes-curso');
    sel.innerHTML = '';
    if (!curso_id) return;
    var disponibles = todosEstudiantes.filter(e => e.curso_id == curso_id && !seleccionados[e.id]);
    if (disponibles.length === 0) {
        sel.innerHTML = '<option disabled>Sin estudiantes disponibles</option>';
        return;
    }
    disponibles.forEach(e => {
        sel.innerHTML += '<option value="' + e.id + '" data-nombre="' + e.nombre + '" data-curso="' + e.curso + '">' + e.nombre + '</option>';
    });
}

function agregarDesdeCascada() {
    var sel = document.getElementById('sel-estudiantes-curso');
    Array.from(sel.selectedOptions).forEach(opt => {
        agregarEstudiante(opt.value, opt.getAttribute('data-nombre'), opt.getAttribute('data-curso'));
    });
    Array.from(sel.options).forEach(o => o.selected = false);
    cargarEstudiantesCurso();
}

// ===== BUSCADOR =====
function buscarPorNombre() {
    var q = document.getElementById('buscador-nombre').value.toLowerCase().trim();
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

// ===== COMÚN =====
function agregarEstudiante(id, nombre, curso) {
    if (seleccionados[id]) return;
    seleccionados[id] = {nombre: nombre, curso: curso};

    // Quitar mensaje vacío
    document.getElementById('sin-seleccionados').style.display = 'none';

    // Agregar tag
    var tag = document.createElement('div');
    tag.className = 'sel-tag';
    tag.id = 'tag-' + id;
    tag.innerHTML = nombre + ' <button type="button" class="quitar" onclick="quitarEstudiante(' + id + ')">✕</button>';
    document.getElementById('seleccionados-tags').appendChild(tag);

    // Agregar hidden input
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'estudiantes[]';
    input.value = id;
    input.id = 'hi-' + id;
    document.getElementById('hidden-estudiantes').appendChild(input);

    actualizarContador();
}

function quitarEstudiante(id) {
    delete seleccionados[id];
    var tag = document.getElementById('tag-' + id);
    if (tag) tag.remove();
    var hi = document.getElementById('hi-' + id);
    if (hi) hi.remove();
    actualizarContador();
    cargarEstudiantesCurso();
}

function actualizarContador() {
    var total = Object.keys(seleccionados).length;
    document.getElementById('contador').textContent = total;
    var btn = document.getElementById('btn-registrar');
    btn.disabled = total === 0;
    btn.style.opacity = total > 0 ? '1' : '0.5';
    document.getElementById('sin-seleccionados').style.display = total === 0 ? 'inline' : 'none';
}

function limpiarTodo() {
    seleccionados = {};
    document.getElementById('seleccionados-tags').innerHTML = '<span class="sin-seleccionados" id="sin-seleccionados">Ningún estudiante seleccionado aún</span>';
    document.getElementById('hidden-estudiantes').innerHTML = '';
    document.getElementById('buscador-nombre').value = '';
    document.getElementById('lista-nombre').style.display = 'none';
    document.getElementById('sel-jornada').value = '';
    document.getElementById('sel-nivel').innerHTML = '<option value="">— Nivel —</option>';
    document.getElementById('sel-nivel').disabled = true;
    document.getElementById('sel-curso').innerHTML = '<option value="">— Curso —</option>';
    document.getElementById('sel-curso').disabled = true;
    document.getElementById('sel-estudiantes-curso').innerHTML = '';
    actualizarContador();
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.buscador-wrap')) {
        document.querySelectorAll('.dropdown-lista').forEach(l => l.style.display = 'none');
    }
});

function actualizarTablaFaltas() {
    fetch('faltas_ajax.php')
        .then(r => r.json())
        .then(data => { document.getElementById('tbody-faltas').innerHTML = data.html; })
        .catch(() => {});
}
setInterval(actualizarTablaFaltas, 60000);
</script>

<?php footer_html(); $conn->close(); ?>