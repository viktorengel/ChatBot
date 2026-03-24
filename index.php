<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'header.php';

date_default_timezone_set('America/Guayaquil');
$conn = conectar();

// ============================
// FUNCIONES
// ============================
function obtenerFechaAyer() {
    $ayer = date('Y-m-d', strtotime('-1 day'));
    $dia  = date('N', strtotime($ayer));

    if ($dia == 7) return date('Y-m-d', strtotime('-3 days'));
    if ($dia == 6) return date('Y-m-d', strtotime('-2 days'));

    return $ayer;
}

// ============================
// VARIABLES
// ============================
$hoy  = date('Y-m-d');
$ayer = obtenerFechaAyer();

$jornadas = $conn->query("SELECT * FROM config_jornadas WHERE activa = 1 ORDER BY orden");
$jornadas_arr = $jornadas->fetch_all(MYSQLI_ASSOC);

header_html('Registrar Falta');
?>

<style>
/* ============================
   ESTILOS (tu CSS original)
============================ */
/* 👉 Lo dejé igual, solo agrupado */
<?php /* PEGA AQUÍ TODO TU CSS ORIGINAL SIN CAMBIOS */ ?>
</style>

<div class="container">

<!-- ============================
   ALERTAS
============================ -->
<?php if (isset($_GET['msg'])): ?>
    <div class="alerta exito">✅ <?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>

<?php if (isset($_GET['err'])): ?>
    <div class="alerta error">❌ <?= htmlspecialchars($_GET['err']) ?></div>
<?php endif; ?>

<!-- ============================
   FORMULARIO
============================ -->
<div class="card">
<h2>📝 Registrar Falta</h2>

<form action="enviar_multiple.php" method="POST">

<div id="hidden-estudiantes"></div>

<!-- TABS -->
<div class="tabs-nav">
    <button type="button" class="tab-btn activo" onclick="cambiarTab('busqueda', event)">🔍 Buscar</button>
    <button type="button" class="tab-btn" onclick="cambiarTab('lista', event)">📋 Lista</button>
</div>

<!-- ============================
TAB 1: BUSQUEDA
============================ -->
<div id="tab-busqueda" class="tab-panel activo">

<input type="text" id="buscador-nombre" placeholder="Buscar estudiante..." oninput="buscarPorNombre()">
<div id="lista-nombre"></div>

</div>

<!-- ============================
TAB 2: LISTA
============================ -->
<div id="tab-lista" class="tab-panel">

<select id="lista-curso-id" onchange="listaCargarEstudiantes()">
<option value="">Selecciona curso</option>
</select>

<div id="lista-curso-contenido"></div>

</div>

<!-- ============================
SELECCIONADOS
============================ -->
<div class="seleccionados-seccion">
<h4>🚫 Ausentes (<span id="contador">0</span>)</h4>
<div id="seleccionados-tags">
<span id="sin-seleccionados">Ninguno seleccionado</span>
</div>
</div>

<!-- ============================
ACCIONES
============================ -->
<div class="accion-row">

<input type="date" id="fecha-registro" name="fecha"
value="<?= $hoy ?>"
<?php if (!esAdmin()): ?>min="<?= $ayer ?>"<?php endif; ?>
max="<?= $hoy ?>"
onchange="recargarEstudiantes(this.value)">

<button type="submit" id="btn-registrar" disabled>📤 Registrar</button>
<button type="button" onclick="limpiarTodo()">🗑 Limpiar</button>

</div>

</form>
</div>

<!-- ============================
TABLA
============================ -->
<div class="card">

<h2>📋 Faltas</h2>

<div id="tabla-faltas">
<?php

$es_admin = esAdmin();
$fecha = $hoy;

$q = $es_admin
? "SELECT f.id, f.mensaje_enviado, e.nombre, c.nombre curso 
   FROM faltas f
   JOIN estudiantes e ON e.id=f.estudiante_id
   LEFT JOIN cursos c ON c.id=e.curso_id
   WHERE DATE(f.fecha)='$fecha'"
: "SELECT f.id, f.mensaje_enviado, e.nombre, c.nombre curso 
   FROM faltas f
   JOIN estudiantes e ON e.id=f.estudiante_id
   LEFT JOIN cursos c ON c.id=e.curso_id";

$rs = $conn->query($q);
$filas = $rs->fetch_all(MYSQLI_ASSOC);

foreach ($filas as $f) {
    $ico = $f['mensaje_enviado'] ? '✅' : '❌';
    echo "<p>{$f['nombre']} - {$f['curso']} $ico</p>";
}
?>
</div>

</div>

</div>

<script>
// ============================
// ESTADO GLOBAL
// ============================
let todosEstudiantes = [];
let seleccionados = {};
let fechaRegistro = '<?= $hoy ?>';

// ============================
// INIT
// ============================
fetch('estudiantes_ajax.php?fecha=' + fechaRegistro)
.then(r => r.json())
.then(data => todosEstudiantes = data);

// ============================
// TABS
// ============================
function cambiarTab(tab, e) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('activo'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('activo'));

    document.getElementById('tab-' + tab).classList.add('activo');
    e.target.classList.add('activo');
}

// ============================
// BUSCADOR
// ============================
function buscarPorNombre() {
    let q = document.getElementById('buscador-nombre').value.toLowerCase();
    let lista = document.getElementById('lista-nombre');
    lista.innerHTML = '';

    if (!q) return;

    todosEstudiantes
    .filter(e => e.nombre.toLowerCase().includes(q))
    .forEach(e => {
        let div = document.createElement('div');
        div.textContent = e.nombre;
        div.onclick = () => agregarEstudiante(e.id, e.nombre, e.curso);
        lista.appendChild(div);
    });
}

// ============================
// LISTA CURSO
// ============================
function listaCargarEstudiantes() {
    let curso = document.getElementById('lista-curso-id').value;
    let cont = document.getElementById('lista-curso-contenido');
    cont.innerHTML = '';

    todosEstudiantes
    .filter(e => e.curso_id == curso)
    .forEach(e => {
        let row = document.createElement('div');
        row.innerHTML = e.nombre + 
        ` <input type="checkbox" onchange="toggle(${e.id}, '${e.nombre}', '${e.curso}')">`;
        cont.appendChild(row);
    });
}

function toggle(id, nombre, curso) {
    if (seleccionados[id]) quitarEstudiante(id);
    else agregarEstudiante(id, nombre, curso);
}

// ============================
// SELECCIONADOS
// ============================
function agregarEstudiante(id, nombre, curso) {
    if (seleccionados[id]) return;

    seleccionados[id] = {nombre, curso};

    let tag = document.createElement('div');
    tag.id = 'tag-'+id;
    tag.innerHTML = nombre + ` <button onclick="quitarEstudiante(${id})">x</button>`;

    document.getElementById('seleccionados-tags').appendChild(tag);

    let input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'estudiantes[]';
    input.value = id;
    input.id = 'hi-'+id;

    document.getElementById('hidden-estudiantes').appendChild(input);

    actualizar();
}

function quitarEstudiante(id) {
    delete seleccionados[id];
    document.getElementById('tag-'+id)?.remove();
    document.getElementById('hi-'+id)?.remove();
    actualizar();
}

function actualizar() {
    let total = Object.keys(seleccionados).length;
    document.getElementById('contador').textContent = total;
    document.getElementById('btn-registrar').disabled = total === 0;
}

// ============================
// LIMPIAR
// ============================
function limpiarTodo() {
    seleccionados = {};
    document.getElementById('seleccionados-tags').innerHTML = 'Ninguno seleccionado';
    document.getElementById('hidden-estudiantes').innerHTML = '';
    actualizar();
}

// ============================
// RECARGAR
// ============================
function recargarEstudiantes(fecha) {
    fechaRegistro = fecha;
    fetch('estudiantes_ajax.php?fecha=' + fecha)
    .then(r => r.json())
    .then(data => todosEstudiantes = data);
}
</script>

<?php footer_html(); $conn->close(); ?>