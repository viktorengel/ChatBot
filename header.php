<?php
function header_html($titulo) {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?> - U.E. Pomasqui</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; }
        .topbar { background: #1a73e8; color: white; padding: 12px 25px; display: flex; justify-content: space-between; align-items: center; }
        .topbar h1 { font-size: 18px; }
        .topbar .user { font-size: 13px; opacity: 0.9; }
        .topbar a { color: white; text-decoration: none; margin-left: 15px; font-size: 13px; }
        .topbar a:hover { text-decoration: underline; }

        /* NAV */
        .nav { background: #1557b0; padding: 0 25px; display: flex; align-items: stretch; position: relative; z-index: 100; }
        .nav-item { position: relative; }
        .nav-item > a, .nav-item > span {
            color: white; text-decoration: none; padding: 12px 16px; font-size: 14px;
            display: flex; align-items: center; gap: 5px; cursor: pointer;
            white-space: nowrap; height: 100%;
        }
        .nav-item > a:hover, .nav-item > span:hover,
        .nav-item:hover > a, .nav-item:hover > span {
            background: rgba(255,255,255,0.15);
        }
        .nav-arrow { font-size: 10px; opacity: 0.8; }

        /* DROPDOWN */
        .dropdown { display: none; position: absolute; top: 100%; left: 0; background: #1a73e8; min-width: 200px; border-radius: 0 0 8px 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 200; }
        .nav-item:hover .dropdown { display: block; }
        .dropdown a { color: white; text-decoration: none; padding: 10px 18px; font-size: 13px; display: block; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .dropdown a:last-child { border-bottom: none; }
        .dropdown a:hover { background: rgba(255,255,255,0.15); }

        /* CONTENT */
        .container { max-width: 1000px; margin: 25px auto; padding: 0 20px; }
        .card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .card h2 { color: #1a73e8; margin-bottom: 20px; font-size: 18px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 14px; }
        select, input[type=text], input[type=email], input[type=password], input[type=date] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        select:focus, input:focus { outline: none; border-color: #1a73e8; }
        .btn { background: #1a73e8; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #1557b0; }
        .btn-red { background: #d93025; }
        .btn-red:hover { background: #b31412; }
        .btn-green { background: #137333; }
        .btn-green:hover { background: #0d5929; }
        .btn-sm { padding: 5px 12px; font-size: 13px; }
        .alerta { padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .alerta.exito { background: #e6f4ea; color: #137333; border: 1px solid #a8d5b5; }
        .alerta.error { background: #fce8e6; color: #c5221f; border: 1px solid #f5c6c5; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #1a73e8; color: white; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
        tr:hover { background: #f8f9fa; }
        .badge { padding: 3px 8px; border-radius: 10px; font-size: 12px; }
        .badge.enviado { background: #e6f4ea; color: #137333; }
        .badge.pendiente { background: #fce8e6; color: #c5221f; }
        .badge.admin { background: #e8f0fe; color: #1a73e8; }
        .badge.docente { background: #f3e8fd; color: #7b1fa2; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        @media(max-width:600px) { .grid2 { grid-template-columns: 1fr; } }
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
        .modal-overlay.activo { display:flex; }
        .modal-box { background:white; border-radius:12px; padding:30px; max-width:380px; width:90%; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,0.3); }
        .modal-box .icono { font-size:45px; margin-bottom:10px; }
        .modal-box h3 { color:#333; margin-bottom:8px; font-size:18px; }
        .modal-box p { color:#666; margin-bottom:20px; font-size:14px; }
        .modal-botones { display:flex; gap:10px; justify-content:center; }
        .btn-eliminar-falta { background:#d93025; color:white; padding:5px 12px; border-radius:6px; font-size:13px; text-decoration:none; display:inline-block; }
        .btn-eliminar-falta:hover { background:#b31412; }

        /* RESPONSIVE NAV */
        @media(max-width:600px) {
            .nav { flex-wrap: wrap; }
            .dropdown { position: static; box-shadow: none; border-radius: 0; }
        }
    </style>
</head>
<body>
<div class="topbar">
    <h1>🏫 U.E. Pomasqui — <?= htmlspecialchars($titulo) ?></h1>
    <div class="user">
        👤 <?= htmlspecialchars($_SESSION['docente_nombre']) ?>
        <a href="logout.php">Salir</a>
    </div>
</div>
<div class="nav">
    <div class="nav-item">
        <a href="index.php">📝 Registrar Falta</a>
    </div>
    <div class="nav-item">
        <a href="reportes.php">📊 Reportes</a>
    </div>
    <?php if (esAdmin()): ?>
    <div class="nav-item">
        <span>👥 Gestión <span class="nav-arrow">▾</span></span>
        <div class="dropdown">
            <a href="estudiantes.php">👤 Estudiantes</a>
            <a href="representantes.php">👨‍👩‍👧 Representantes</a>
            <a href="docentes.php">🧑‍🏫 Docentes</a>
        </div>
    </div>
    <div class="nav-item">
        <span>⚙️ Configuración <span class="nav-arrow">▾</span></span>
        <div class="dropdown">
            <a href="configuracion.php">🏫 Institución y Cursos</a>
            <a href="importar.php">📥 Importar Excel</a>
            <a href="https://whatsapp.ecuasys.com/manager" target="_blank">📱 Conectar WhatsApp</a>
        </div>
    </div>
    <?php endif; ?>
    <div class="nav-item" style="margin-left: auto;">
        <a href="contacto.php">📇 Contacto</a>
    </div>
</div>
<?php
}

function footer_html() {
?>
<div class="modal-overlay" id="modal-confirmar">
    <div class="modal-box">
        <div class="icono">⚠️</div>
        <h3>Confirmar accion</h3>
        <p id="modal-mensaje">¿Deseas eliminar este registro?</p>
        <div class="modal-botones">
            <button onclick="cerrarModal()" class="btn" style="background:#777">Cancelar</button>
            <button onclick="confirmarModal()" class="btn btn-red">Eliminar</button>
        </div>
    </div>
</div>
<script>
var _formPendiente = null;
function confirmarEliminar(form, mensaje) {
    _formPendiente = form;
    document.getElementById('modal-mensaje').textContent = mensaje || '¿Deseas eliminar este registro?';
    document.getElementById('modal-confirmar').classList.add('activo');
    return false;
}
function confirmarModal() {
    cerrarModal();
    if (_formPendiente) {
        _formPendiente.submit();
        _formPendiente = null;
    }
}
function cerrarModal() {
    document.getElementById('modal-confirmar').classList.remove('activo');
    _formPendiente = null;
}
</script>
</body></html>
<?php
}