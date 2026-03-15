<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'header.php';

date_default_timezone_set('America/Guayaquil');
$conn = conectar();
$hoy = date('Y-m-d');

// Obtener cursos para el filtro
if (esAdmin()) {
    $cursos_filtro = $conn->query("
        SELECT DISTINCT c.id, c.nombre, c.jornada
        FROM cursos c
        JOIN estudiantes e ON e.curso_id = c.id
        JOIN estudiante_representante er ON er.estudiante_id = e.id
        ORDER BY c.jornada, c.nombre
    ");
} else {
    $did = $_SESSION['docente_id'];
    $cursos_filtro = $conn->query("
        SELECT DISTINCT c.id, c.nombre, c.jornada
        FROM cursos c
        JOIN estudiantes e ON e.curso_id = c.id
        JOIN estudiante_representante er ON er.estudiante_id = e.id
        JOIN docente_cursos dc ON dc.curso_id = c.id
        WHERE dc.docente_id = $did
        ORDER BY c.jornada, c.nombre
    ");
}

$cursos_arr = [];
while ($c = $cursos_filtro->fetch_assoc()) $cursos_arr[] = $c;

header_html('Registrar Falta');
?>
<style>
.filtro-estudiante { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
.buscador-wrap { position: relative; }
.buscador-wrap input { width: 100%; padding: 10px 10px 10px 35px; }
.buscador-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #999; font-size: 16px; }
.estudiantes-lista { display: none; position: absolute; background: white; border: 1px solid #ddd; border-radius: 6px; width: 100%; max-height: 250px; overflow-y: auto; z-index: 100; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
.estudiante-opcion { padding: 10px 14px; cursor: pointer; font-size: 14px; border-bottom: 1px solid #f0f0f0; }
.estudiante-opcion:hover, .estudiante-opcion.seleccionado { background: #e8f0fe; color: #1a73e8; }
.estudiante-opcion .curso-tag { font-size: 11px; color: #777; display: block; margin-top: 2px; }
.estudiante-seleccionado { background: #e8f0fe; border: 2px solid #1a73e8; border-radius: 6px; padding: 10px 14px; font-size: 14px; color: #1a73e8; display: none; cursor: pointer; }
.estudiante-seleccionado span { font-size: 12px; color: #555; display: block; }
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
        <form action="enviar.php" method="POST" id="form-falta">
            <input type="hidden" name="estudiante_id" id="estudiante_id_hidden">

            <div class="filtro-estudiante">
                <!-- Filtro por curso -->
                <div class="form-group" style="margin:0">
                    <label>Filtrar por curso</label>
                    <select id="filtro-curso" onchange="filtrarEstudiantes()">
                        <option value="">-- Todos los cursos --</option>
                        <?php
                        $jornada_actual = '';
                        foreach ($cursos_arr as $c):
                            if ($c['jornada'] !== $jornada_actual) {
                                if ($jornada_actual !== '') echo '</optgroup>';
                                echo '<optgroup label="' . htmlspecialchars($c['jornada']) . '">';
                                $jornada_actual = $c['jornada'];
                            }
                        ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endforeach;
                        if ($jornada_actual !== '') echo '</optgroup>'; ?>
                    </select>
                </div>

                <!-- Fecha -->
                <div class="form-group" style="margin:0">
                    <label>Fecha</label>
                    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <!-- Buscador de estudiante -->
            <div class="form-group" style="position:relative">
                <label>Buscar estudiante</label>
                <div class="buscador-wrap">
                    <span class="buscador-icon">🔍</span>
                    <input type="text" id="buscador" placeholder="Escribe apellido o nombre..." oninput="buscarEstudiante()" autocomplete="off">
                </div>
                <div class="estudiantes-lista" id="lista-estudiantes"></div>
                <div class="estudiante-seleccionado" id="estudiante-seleccionado" onclick="limpiarSeleccion()">
                    <strong id="nombre-seleccionado"></strong>
                    <span id="curso-seleccionado"></span>
                    <small style="color:#1a73e8">✕ Clic para cambiar</small>
                </div>
            </div>

            <button type="submit" class="btn" id="btn-registrar" disabled style="opacity:0.5">
                📤 Registrar y Notificar por WhatsApp
            </button>
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
// Cargar todos los estudiantes con representante y sin falta hoy
var todosEstudiantes = [];
var cursoSeleccionado = '';

fetch('estudiantes_ajax.php?hoy=<?= $hoy ?>')
    .then(r => r.json())
    .then(data => { todosEstudiantes = data; });

function filtrarEstudiantes() {
    cursoSeleccionado = document.getElementById('filtro-curso').value;
    limpiarSeleccion();
    document.getElementById('buscador').value = '';
    document.getElementById('lista-estudiantes').style.display = 'none';
}

function buscarEstudiante() {
    var q = document.getElementById('buscador').value.toLowerCase().trim();
    var lista = document.getElementById('lista-estudiantes');

    if (q.length < 1) { lista.style.display = 'none'; return; }

    var filtrados = todosEstudiantes.filter(function(e) {
        var coincide_nombre = e.nombre.toLowerCase().includes(q);
        var coincide_curso = !cursoSeleccionado || e.curso_id == cursoSeleccionado;
        return coincide_nombre && coincide_curso;
    });

    lista.innerHTML = '';

    if (filtrados.length === 0) {
        var div = document.createElement('div');
        div.className = 'estudiante-opcion';
        div.style.color = '#999';
        div.textContent = 'Sin resultados';
        lista.appendChild(div);
    } else {
        filtrados.forEach(function(e) {
            var div = document.createElement('div');
            div.className = 'estudiante-opcion';
            div.innerHTML = e.nombre + '<span class="curso-tag">' + e.curso + '</span>';
            div.addEventListener('click', function() {
                seleccionarEstudiante(e.id, e.nombre, e.curso);
            });
            lista.appendChild(div);
        });
    }
    lista.style.display = 'block';
}

function seleccionarEstudiante(id, nombre, curso) {
    document.getElementById('estudiante_id_hidden').value = id;
    document.getElementById('nombre-seleccionado').textContent = nombre;
    document.getElementById('curso-seleccionado').textContent = curso;
    document.getElementById('estudiante-seleccionado').style.display = 'block';
    document.getElementById('buscador').style.display = 'none';
    document.getElementById('lista-estudiantes').style.display = 'none';
    document.getElementById('btn-registrar').disabled = false;
    document.getElementById('btn-registrar').style.opacity = '1';
}

function limpiarSeleccion() {
    document.getElementById('estudiante_id_hidden').value = '';
    document.getElementById('estudiante-seleccionado').style.display = 'none';
    document.getElementById('buscador').style.display = 'block';
    document.getElementById('buscador').value = '';
    document.getElementById('btn-registrar').disabled = true;
    document.getElementById('btn-registrar').style.opacity = '0.5';
}

// Cerrar lista al hacer clic fuera
document.addEventListener('click', function(e) {
    if (!e.target.closest('.buscador-wrap') && !e.target.closest('.estudiantes-lista')) {
        document.getElementById('lista-estudiantes').style.display = 'none';
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