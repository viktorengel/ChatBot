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

        /* ══════════════════════════════════════
           CABECERA FIJA AL HACER SCROLL
        ══════════════════════════════════════ */
        .header-sticky {
            position: sticky;
            top: 0;
            z-index: 500;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        /* ── TOPBAR ── */
        .topbar {
            background: #1a73e8; color: white;
            padding: 0 20px; height: 52px;
            display: flex; justify-content: space-between; align-items: center; gap: 10px;
        }
        .topbar-left  { display: flex; align-items: center; gap: 12px; min-width: 0; }
        .topbar h1    { font-size: 16px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .topbar-right { display: flex; align-items: center; gap: 14px; flex-shrink: 0; }
        .topbar-user  { font-size: 13px; opacity: 0.9; white-space: nowrap; }
        .topbar-salir {
            color: white; text-decoration: none; font-size: 13px;
            background: rgba(255,255,255,0.2); padding: 5px 14px;
            border-radius: 20px; transition: background 0.2s;
        }
        .topbar-salir:hover { background: rgba(255,255,255,0.35); }

        /* ── BOTÓN HAMBURGUESA ── */
        .menu-toggle {
            display: none; background: none; border: none;
            color: white; cursor: pointer; padding: 6px;
            border-radius: 6px; line-height: 1; flex-shrink: 0;
            transition: background 0.2s;
        }
        .menu-toggle:hover { background: rgba(255,255,255,0.15); }

        /* ── NAV DESKTOP ── */
        .nav {
            background: #1557b0; padding: 0 20px;
            display: flex; align-items: stretch;
        }
        .nav-item { position: relative; }
        .nav-item > a, .nav-item > span {
            color: white; text-decoration: none;
            padding: 10px 16px; font-size: 14px;
            display: flex; align-items: center; gap: 6px;
            cursor: pointer; white-space: nowrap; height: 100%;
            border-bottom: 3px solid transparent;
            transition: background 0.15s, border-color 0.15s;
        }
        .nav-item > a:hover, .nav-item > span:hover,
        .nav-item:hover > a, .nav-item:hover > span {
            background: rgba(255,255,255,0.12);
            border-bottom-color: rgba(255,255,255,0.5);
        }
        .nav-item.activo > a, .nav-item.activo > span {
            border-bottom-color: white;
            background: rgba(255,255,255,0.1);
        }
        .nav-arrow { font-size: 9px; opacity: 0.7; transition: transform 0.2s; }
        .nav-item:hover .nav-arrow { transform: rotate(180deg); }
        .nav-item.nav-right { margin-left: auto; }

        /* ── DROPDOWN DESKTOP ── */
        .dropdown {
            display: none; position: absolute;
            top: 100%; left: 0;
            background: #1a73e8; min-width: 215px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.22);
            z-index: 600; overflow: hidden;
        }
        .nav-item:hover .dropdown { display: block; }
        .dropdown a {
            color: white; text-decoration: none;
            padding: 11px 18px; font-size: 13px;
            display: flex; align-items: center; gap: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            transition: background 0.15s, padding-left 0.15s;
        }
        .dropdown a:last-child { border-bottom: none; }
        .dropdown a:hover { background: rgba(255,255,255,0.15); padding-left: 22px; }

        /* ══════════════════════════════════════
           MENÚ MÓVIL — DRAWER LATERAL
        ══════════════════════════════════════ */
        .nav-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.45); z-index: 800;
            backdrop-filter: blur(2px);
        }
        .nav-overlay.activo { display: block; }

        .nav-drawer {
            position: fixed; top: 0; left: 0;
            width: 280px; height: 100%;
            background: #1557b0; z-index: 900;
            display: flex; flex-direction: column;
            transform: translateX(-100%);
            transition: transform 0.28s cubic-bezier(0.4,0,0.2,1);
            overflow-y: auto;
        }
        .nav-drawer.activo { transform: translateX(0); }

        .drawer-header {
            background: #1a73e8; padding: 16px 18px;
            display: flex; align-items: center;
            justify-content: space-between; flex-shrink: 0;
        }
        .drawer-header-info { display: flex; flex-direction: column; gap: 3px; }
        .drawer-header-titulo { color: white; font-size: 15px; font-weight: bold; }
        .drawer-header-user   { color: rgba(255,255,255,0.75); font-size: 12px; }

        .drawer-cerrar {
            background: rgba(255,255,255,0.15); border: none;
            color: white; cursor: pointer;
            width: 34px; height: 34px; border-radius: 50%;
            font-size: 18px; display: flex; align-items: center;
            justify-content: center; flex-shrink: 0; transition: background 0.2s;
        }
        .drawer-cerrar:hover { background: rgba(255,255,255,0.3); }

        .drawer-nav { flex: 1; padding: 8px 0; }

        .drawer-link {
            color: white; text-decoration: none;
            padding: 13px 20px; font-size: 15px;
            display: flex; align-items: center; gap: 10px;
            border-left: 3px solid transparent;
            transition: background 0.15s, border-color 0.15s;
        }
        .drawer-link:hover, .drawer-link.activo {
            background: rgba(255,255,255,0.12); border-left-color: white;
        }
        .drawer-divider {
            height: 1px; background: rgba(255,255,255,0.12); margin: 6px 0;
        }
        .drawer-seccion-titulo {
            color: rgba(255,255,255,0.5); font-size: 11px;
            font-weight: bold; text-transform: uppercase;
            letter-spacing: 0.8px; padding: 10px 20px 4px;
        }
        .drawer-sub-link {
            color: rgba(255,255,255,0.88); text-decoration: none;
            padding: 11px 20px 11px 42px; font-size: 14px;
            display: flex; align-items: center; gap: 8px;
            border-left: 3px solid transparent;
            transition: background 0.15s;
        }
        .drawer-sub-link:hover, .drawer-sub-link.activo {
            background: rgba(255,255,255,0.1);
            border-left-color: rgba(255,255,255,0.5);
        }
        .drawer-footer {
            padding: 14px 16px;
            border-top: 1px solid rgba(255,255,255,0.12); flex-shrink: 0;
        }
        .drawer-salir {
            display: flex; align-items: center; gap: 8px;
            color: white; text-decoration: none;
            background: rgba(255,255,255,0.12);
            padding: 11px 16px; border-radius: 8px;
            font-size: 14px; transition: background 0.2s;
        }
        .drawer-salir:hover { background: rgba(255,255,255,0.22); }

        /* ══════════════════════════════════════
           CONTENIDO
        ══════════════════════════════════════ */
        .container { max-width: 1000px; margin: 20px auto; padding: 0 16px; }
        .card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .card h2 { color: #1a73e8; margin-bottom: 18px; font-size: 17px; }

        /* ── FORMS ── */
        .form-group { margin-bottom: 14px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 14px; }
        select, input[type=text], input[type=email], input[type=password], input[type=date] {
            width: 100%; padding: 10px; border: 1px solid #ddd;
            border-radius: 6px; font-size: 14px; -webkit-appearance: none;
        }
        select:focus, input:focus { outline: none; border-color: #1a73e8; }

        /* ── BUTTONS ── */
        .btn {
            background: #1a73e8; color: white; border: none;
            padding: 10px 20px; border-radius: 6px; cursor: pointer;
            font-size: 14px; text-decoration: none; display: inline-block;
            touch-action: manipulation; transition: background 0.15s;
        }
        .btn:hover  { background: #1557b0; }
        .btn:active { transform: scale(0.97); }
        .btn-red    { background: #d93025; }
        .btn-red:hover  { background: #b31412; }
        .btn-green  { background: #137333; }
        .btn-green:hover { background: #0d5929; }
        .btn-sm { padding: 7px 12px; font-size: 13px; }

        /* ── ALERTS ── */
        .alerta { padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .alerta.exito { background: #e6f4ea; color: #137333; border: 1px solid #a8d5b5; }
        .alerta.error { background: #fce8e6; color: #c5221f; border: 1px solid #f5c6c5; }

        /* ── TABLES ── */
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 520px; }
        th { background: #1a73e8; color: white; padding: 10px; text-align: left; white-space: nowrap; }
        td { padding: 9px 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
        tr:hover { background: #f8f9fa; }

        /* ── BADGES ── */
        .badge { padding: 3px 8px; border-radius: 10px; font-size: 12px; white-space: nowrap; }
        .badge.enviado   { background: #e6f4ea; color: #137333; }
        .badge.pendiente { background: #fce8e6; color: #c5221f; }
        .badge.admin     { background: #e8f0fe; color: #1a73e8; }
        .badge.docente   { background: #f3e8fd; color: #7b1fa2; }

        /* ── GRID ── */
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }

        /* ── MODAL ── */
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
        .modal-overlay.activo { display:flex; }
        .modal-box { background:white; border-radius:12px; padding:25px; max-width:360px; width:90%; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,0.3); }
        .modal-box .icono { font-size:40px; margin-bottom:10px; }
        .modal-box h3 { color:#333; margin-bottom:8px; font-size:17px; }
        .modal-box p  { color:#666; margin-bottom:18px; font-size:14px; }
        .modal-botones { display:flex; gap:10px; justify-content:center; }
        .btn-eliminar-falta { background:#d93025; color:white; padding:6px 12px; border-radius:6px; font-size:13px; text-decoration:none; display:inline-block; }
        .btn-eliminar-falta:hover { background:#b31412; }

        /* ══════════════════════════════════════
           RESPONSIVE — TABLET (≤ 768px)
        ══════════════════════════════════════ */
        @media (max-width: 768px) {
            .grid2 { grid-template-columns: 1fr; }
            .container { margin: 12px auto; padding: 0 10px; }
            .card { padding: 15px; border-radius: 8px; }
        }

        /* ══════════════════════════════════════
           RESPONSIVE — MÓVIL (≤ 600px)
        ══════════════════════════════════════ */
        @media (max-width: 600px) {
            .nav { display: none; }
            .menu-toggle { display: flex; align-items: center; justify-content: center; }
            .topbar { padding: 0 14px; height: 50px; }
            .topbar h1 { font-size: 14px; }
            .topbar-user { display: none; }
            table { font-size: 13px; }
            th, td { padding: 8px 7px; }
            select, input[type=text], input[type=email],
            input[type=password], input[type=date] { font-size: 16px; padding: 11px; }
            .btn { padding: 11px 18px; font-size: 14px; }
            .btn-sm { padding: 8px 12px; font-size: 13px; }
        }

        /* ══════════════════════════════════════
           RESPONSIVE — PEQUEÑO (≤ 380px)
        ══════════════════════════════════════ */
        @media (max-width: 380px) {
            .topbar h1 { font-size: 12px; }
            .card { padding: 12px; }
            .btn { width: 100%; text-align: center; margin-bottom: 6px; }
            .modal-box { padding: 18px; }
            .nav-drawer { width: 100%; }
        }
    </style>
</head>
<body>

<?php
$pagina_actual = basename($_SERVER['PHP_SELF']);
function nav_activo($archivo) {
    global $pagina_actual;
    $archivos = is_array($archivo) ? $archivo : [$archivo];
    return in_array($pagina_actual, $archivos) ? 'activo' : '';
}
?>

<!-- ══ CABECERA STICKY ══ -->
<div class="header-sticky">

    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-toggle" onclick="abrirDrawer()" aria-label="Abrir menú">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <line x1="3" y1="6"  x2="21" y2="6"/>
                    <line x1="3" y1="12" x2="21" y2="12"/>
                    <line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            <h1>🏫 U.E. Pomasqui — <?= htmlspecialchars($titulo) ?></h1>
        </div>
        <div class="topbar-right">
            <span class="topbar-user">👤 <?= htmlspecialchars($_SESSION['docente_nombre']) ?></span>
            <a href="logout.php" class="topbar-salir">Salir</a>
        </div>
    </div>

    <nav class="nav">
        <div class="nav-item <?= nav_activo('index.php') ?>">
            <a href="index.php">📝 Registrar Falta</a>
        </div>
            <?php if (esAdmin()): ?>
        <div class="nav-item <?= nav_activo(['estudiantes.php','representantes.php','docentes.php']) ?>">
            <span>👥 Gestión <span class="nav-arrow">▾</span></span>
            <div class="dropdown">
                <a href="estudiantes.php">👤 Estudiantes</a>
                <a href="representantes.php">👨‍👩‍👧 Representantes</a>
                <a href="docentes.php">🧑‍🏫 Docentes</a>
            </div>
        </div>
        <div class="nav-item <?= nav_activo(['configuracion.php','importar.php']) ?>">
            <span>⚙️ Configuración <span class="nav-arrow">▾</span></span>
            <div class="dropdown">
                <a href="configuracion.php">🏫 Institución y Cursos</a>
                <a href="importar.php">📥 Importar Excel</a>
                <a href="chatbot.php">🤖 ChatBot</a>
                <a href="https://whatsapp.ecuasys.com/manager" target="_blank">📱 Conectar WhatsApp</a>
            </div>
        </div>
        <?php endif; ?>
        <div class="nav-item nav-right <?= nav_activo('contacto.php') ?>">
            <a href="contacto.php">📇 Contacto</a>
        </div>
    </nav>

</div>

<!-- ══ OVERLAY + DRAWER MÓVIL ══ -->
<div class="nav-overlay" id="nav-overlay" onclick="cerrarDrawer()"></div>

<div class="nav-drawer" id="nav-drawer" role="dialog" aria-label="Menú">

    <div class="drawer-header">
        <div class="drawer-header-info">
            <span class="drawer-header-titulo">🏫 U.E. Pomasqui</span>
            <span class="drawer-header-user">👤 <?= htmlspecialchars($_SESSION['docente_nombre']) ?></span>
        </div>
        <button class="drawer-cerrar" onclick="cerrarDrawer()" aria-label="Cerrar">✕</button>
    </div>

    <div class="drawer-nav">
        <a href="index.php"    class="drawer-link <?= nav_activo('index.php') ?>">📝 Registrar Falta</a>

        <?php if (esAdmin()): ?>
        <div class="drawer-divider"></div>
        <div class="drawer-seccion-titulo">Gestión</div>
        <a href="estudiantes.php"    class="drawer-sub-link <?= nav_activo('estudiantes.php') ?>">👤 Estudiantes</a>
        <a href="representantes.php" class="drawer-sub-link <?= nav_activo('representantes.php') ?>">👨‍👩‍👧 Representantes</a>
        <a href="docentes.php"       class="drawer-sub-link <?= nav_activo('docentes.php') ?>">🧑‍🏫 Docentes</a>

        <div class="drawer-divider"></div>
        <div class="drawer-seccion-titulo">Configuración</div>
        <a href="configuracion.php"  class="drawer-sub-link <?= nav_activo('configuracion.php') ?>">🏫 Institución y Cursos</a>
        <a href="importar.php"       class="drawer-sub-link <?= nav_activo('importar.php') ?>">📥 Importar Excel</a>
        <a href="chatbot.php"        class="drawer-sub-link <?= nav_activo('chatbot.php') ?>">🤖 ChatBot</a>
        <a href="https://whatsapp.ecuasys.com/manager" target="_blank" class="drawer-sub-link">📱 Conectar WhatsApp</a>
        <?php endif; ?>

        <div class="drawer-divider"></div>
        <a href="contacto.php" class="drawer-link <?= nav_activo('contacto.php') ?>">📇 Contacto</a>
    </div>

    <div class="drawer-footer">
        <a href="logout.php" class="drawer-salir">🚪 Cerrar sesión</a>
    </div>

</div>

<script>
function abrirDrawer() {
    document.getElementById('nav-drawer').classList.add('activo');
    document.getElementById('nav-overlay').classList.add('activo');
    document.body.style.overflow = 'hidden';
}
function cerrarDrawer() {
    document.getElementById('nav-drawer').classList.remove('activo');
    document.getElementById('nav-overlay').classList.remove('activo');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') cerrarDrawer();
});
</script>

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
    if (_formPendiente) { _formPendiente.submit(); _formPendiente = null; }
}
function cerrarModal() {
    document.getElementById('modal-confirmar').classList.remove('activo');
    _formPendiente = null;
}
</script>
</body></html>
<?php
}
