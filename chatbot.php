<?php
require_once 'auth.php';
soloAdmin();
require_once 'config.php';

$conn = conectar();

// ══════════════════════════════════════════════════════════════
// CREAR TABLAS SI NO EXISTEN
// ══════════════════════════════════════════════════════════════
$conn->query("CREATE TABLE IF NOT EXISTS bot_institucion (
    id    INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50)  NOT NULL UNIQUE,
    valor TEXT         NOT NULL,
    label VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS bot_horarios (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    tipo    ENUM('jornada','especial') NOT NULL DEFAULT 'jornada',
    nombre  VARCHAR(100) NOT NULL,
    horario VARCHAR(100) NOT NULL,
    activo  TINYINT(1)   NOT NULL DEFAULT 1,
    orden   INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS bot_autoridades (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    cargo  VARCHAR(100) NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    activo TINYINT(1)   NOT NULL DEFAULT 1,
    orden  INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS bot_faq (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    palabras  TEXT         NOT NULL,
    respuesta TEXT         NOT NULL,
    imagen    VARCHAR(500) DEFAULT NULL,
    caption   VARCHAR(200) DEFAULT NULL,
    activo    TINYINT(1)   NOT NULL DEFAULT 1,
    orden     INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Agregar columnas imagen/caption si ya existía la tabla sin ellas
$conn->query("ALTER TABLE bot_faq ADD COLUMN IF NOT EXISTS imagen  VARCHAR(500) DEFAULT NULL");
$conn->query("ALTER TABLE bot_faq ADD COLUMN IF NOT EXISTS caption VARCHAR(200) DEFAULT NULL");

$conn->query("CREATE TABLE IF NOT EXISTS bot_plantillas (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nombre    VARCHAR(100) NOT NULL,
    contenido TEXT         NOT NULL,
    activo    TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ══════════════════════════════════════════════════════════════
// POBLAR DATOS INICIALES (solo si la tabla está vacía)
// ══════════════════════════════════════════════════════════════

// ── Institución ──
$inst_rows = [
    ['nombre',    'Unidad Educativa Pomasqui',                                          'Nombre de la institución'],
    ['direccion', 'Av. Manuel Córdova Galarza N1-189 y Manuela Sáenz, Parroquia Pomasqui, Quito', 'Dirección'],
    ['telefono',  '02-235-1072',                                                        'Teléfono fijo'],
    ['whatsapp',  '+593 964 259 358',                                                   'Número WhatsApp'],
    ['email',     '17h01988@gmail.com',                                                 'Correo electrónico'],
    ['web',       'https://iepomasqui.com',                                             'Sitio web'],
    ['maps',      'https://maps.app.goo.gl/BRgbEKRodAk1Quf79',                    'Enlace Google Maps'],
];
$stmt = $conn->prepare("INSERT INTO bot_institucion (clave,valor,label) VALUES (?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label)");
foreach ($inst_rows as $r) { $stmt->bind_param("sss", $r[0], $r[1], $r[2]); $stmt->execute(); }

// ── Horarios (solo si vacío) ──
if ($conn->query("SELECT COUNT(*) as n FROM bot_horarios")->fetch_assoc()['n'] == 0) {
    $stmt = $conn->prepare("INSERT INTO bot_horarios (tipo,nombre,horario,orden) VALUES (?,?,?,?)");
    $horarios_init = [
        ['jornada',  'Matutina',          '07:00 — 13:00',                                    1],
        ['jornada',  'Vespertina',         '13:00 — 18:00',                                    2],
        ['jornada',  'Nocturna',           '15:00 — 22:00',                                    3],
        ['especial', 'Secretaría',         'Lunes a Viernes, 08:00 – 16:00',                   4],
        ['especial', 'Atención a padres',  'Matutina: 07:00–13:00 / Vespertina: 13:00–18:00',  5],
    ];
    foreach ($horarios_init as $h) { $stmt->bind_param("sssi", $h[0], $h[1], $h[2], $h[3]); $stmt->execute(); }
}

// ── Autoridades: insertar las que falten por cargo ──
$cargos_db = [];
$rs = $conn->query("SELECT cargo FROM bot_autoridades");
while ($r = $rs->fetch_assoc()) $cargos_db[] = $r['cargo'];

$autoridades_init = [
    ['Rector',            'MSc. Jorge Imbaquingo',  1],
    ['Vicerrectora',      'MSc. Janeth Chipantasi',  2],
    ['Inspector General', 'Lic. Marco Loachamin',    3],
    ['ViceInspector',     'MSc. Willi Calahorrano',  4],
    ['DECE Matutina',     'Psic. Ana Julia Paredes', 5],
    ['DECE Vespertina',   'Psic. Eduardo Campaña',   6],
];
$stmt = $conn->prepare("INSERT INTO bot_autoridades (cargo,nombre,orden) VALUES (?,?,?)");
foreach ($autoridades_init as $a) {
    if (!in_array($a[0], $cargos_db)) { $stmt->bind_param("ssi", $a[0], $a[1], $a[2]); $stmt->execute(); }
}

// ── FAQ (solo si vacío) ──
if ($conn->query("SELECT COUNT(*) as n FROM bot_faq")->fetch_assoc()['n'] == 0) {
    $stmt = $conn->prepare("INSERT INTO bot_faq (palabras,respuesta,imagen,caption,orden) VALUES (?,?,?,?,?)");
    $faqs_init = [
        ['matrícula,matricula,inscripción,inscripcion',
         "📋 *Proceso de Matrícula*\n\nPara matricularse en la U.E. Pomasqui necesita:\n• Copia de cédula del estudiante\n• Copia de cédula del representante\n• Partida de nacimiento\n• Libreta de calificaciones del año anterior\n• Foto tamaño carné\n\nFecha de matrículas: contactar secretaría al 📞 02-235-1072",
         null, null, 1],
        ['uniforme,ropa,vestimenta',
         "👕 *Uniforme Escolar*\n\nAquí puedes ver el uniforme oficial de la institución.\n\nPara consultas comuníquese con Secretaría:\n📞 02-235-1072\n🕐 Lunes a Viernes de 07:00 a 16:00",
         'https://as.ecuasys.com/uniforme.jpg', '👕 Uniforme oficial — U.E. Pomasqui', 2],
        ['pension,pensión,pago,mensualidad',
         "💰 *Pagos*\n\nPara información sobre pensiones y pagos acercarse a colecturía:\n🕐 Lunes a Viernes de 07:00 a 16:00\n📞 02-235-1072",
         null, null, 3],
        ['calificacion,calificación,nota,notas,rendimiento',
         "📊 *Calificaciones*\n\nPara consultar calificaciones de su representado/a:\n• Acercarse a la institución en horario de atención\n• O comunicarse con el docente titular del curso\n\n🕐 Atención: Lunes a Viernes 08:00 a 12:00 y 14:00 a 17:00",
         null, null, 4],
        ['certificado,documento,record',
         "📄 *Certificados y Documentos*\n\nAcercarse a Secretaría con la solicitud:\n🕐 Lunes a Viernes de 07:00 a 16:00\n\nTiempo de entrega: 3 a 5 días hábiles.",
         null, null, 5],
    ];
    foreach ($faqs_init as $f) { $stmt->bind_param("ssssi", $f[0], $f[1], $f[2], $f[3], $f[4]); $stmt->execute(); }
} else {
    // Actualizar FAQ de uniforme con imagen si aún no la tiene
    $conn->query("UPDATE bot_faq SET
        palabras='uniforme,ropa,vestimenta',
        imagen='https://as.ecuasys.com/uniforme.jpg',
        caption='👕 Uniforme oficial — U.E. Pomasqui'
        WHERE palabras LIKE '%uniforme%' AND (imagen IS NULL OR imagen='') LIMIT 1");
    // Insertar FAQ de calificaciones si no existe
    $stmt_chk = $conn->prepare("SELECT id FROM bot_faq WHERE palabras LIKE '%calificacion%'");
    $stmt_chk->execute();
    if ($stmt_chk->get_result()->num_rows === 0) {
        $pal = 'calificacion,calificación,nota,notas,rendimiento';
        $res = "📊 *Calificaciones*\n\nPara consultar calificaciones de su representado/a:\n• Acercarse a la institución en horario de atención\n• O comunicarse con el docente titular del curso\n\n🕐 Atención: Lunes a Viernes 08:00 a 12:00 y 14:00 a 17:00";
        $stmt = $conn->prepare("INSERT INTO bot_faq (palabras,respuesta,orden) VALUES (?,?,5)");
        $stmt->bind_param("ss", $pal, $res); $stmt->execute();
    }
    $stmt_chk = $conn->prepare("SELECT id FROM bot_faq WHERE palabras LIKE '%certificado%'");
    $stmt_chk->execute();
    if ($stmt_chk->get_result()->num_rows === 0) {
        $pal = 'certificado,documento,record';
        $res = "📄 *Certificados y Documentos*\n\nSolicitar en Secretaría:\n📞 02-235-1072\n🕐 Lunes a Viernes de 07:00 a 16:00\n\nTiempo de entrega: 3 a 5 días hábiles.";
        $stmt = $conn->prepare("INSERT INTO bot_faq (palabras,respuesta,orden) VALUES (?,?,6)");
        $stmt->bind_param("ss", $pal, $res); $stmt->execute();
    }
}

// ── Plantillas (solo si vacío) ──
if ($conn->query("SELECT COUNT(*) as n FROM bot_plantillas")->fetch_assoc()['n'] == 0) {
    $stmt = $conn->prepare("INSERT INTO bot_plantillas (nombre,contenido) VALUES (?,?)");
    $plantillas_init = [
        ['Formal directa',
         "🏫 *Unidad Educativa Pomasqui*\n\nEstimado/a *{rep}*,\n\nLe informamos que su representado/a:\n👤 *{est}*\n📚 Curso: *{curso}*\n\nNo asistió a clases el día *{fecha}*.\n\nLe solicitamos justificar esta inasistencia con su *docente tutor* a la brevedad posible.\n\n_Mensaje automático - U.E. Pomasqui_"],
        ['Cordial',
         "📢 *U.E. Pomasqui* le saluda cordialmente.\n\nEstimado/a representante *{rep}*:\n\nPor medio del presente le notificamos que el/la estudiante\n✏️ *{est}* — *{curso}*\n\nregistró una *ausencia* el día *{fecha}*.\n\nAgradecemos justificar la inasistencia con el *docente tutor*.\n\n_Notificación automática - U.E. Pomasqui_"],
        ['Breve y directa',
         "🏫 *U.E. Pomasqui*\n\nHola *{rep}*, le comunicamos que:\n\n🔴 *{est}* no asistió a clases el día *{fecha}*.\n📖 Curso: *{curso}*\n\nPor favor justificar la falta con el *docente tutor*.\n\n_Aviso automático - U.E. Pomasqui_"],
        ['Con detalle',
         "👋 *Unidad Educativa Pomasqui*\n\nEstimado/a *{rep}*,\n\nLe informamos que el *{fecha}* su representado/a\n📌 *{est}* del curso *{curso}*\n\nno asistió a la institución.\n\nLe pedimos comunicarse y realizar la justificación con el *docente tutor* a la brevedad.\n\n_Sistema de asistencia - U.E. Pomasqui_"],
        ['Reporte formal',
         "📋 *Reporte de Asistencia*\n*Unidad Educativa Pomasqui*\n\nApreciado/a *{rep}*:\n\nLe notificamos que *{est}* (*{curso}*)\nno registró asistencia el día *{fecha}*.\n\n⚠️ Es importante justificar la ausencia con su *docente tutor*.\n\n_Mensaje automático - U.E. Pomasqui_"],
    ];
    foreach ($plantillas_init as $p) { $stmt->bind_param("ss", $p[0], $p[1]); $stmt->execute(); }
}


// ══════════════════════════════════════════════════════════════
// ACCIONES POST
// ══════════════════════════════════════════════════════════════
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // ── Institución ──
    if ($accion === 'guardar_institucion') {
        $stmt = $conn->prepare("UPDATE bot_institucion SET valor=? WHERE clave=?");
        foreach (['nombre','direccion','telefono','whatsapp','email','web'] as $k) {
            $v = trim($_POST[$k] ?? '');
            $stmt->bind_param("ss", $v, $k);
            $stmt->execute();
        }
        $msg = 'Datos institucionales actualizados';

    // ── Horarios: guardar ──
    } elseif ($accion === 'guardar_horarios') {
        $ids = $_POST['hor_id'] ?? [];
        $stmt = $conn->prepare("UPDATE bot_horarios SET nombre=?,horario=?,activo=? WHERE id=?");
        foreach ($ids as $i => $id) {
            $id  = intval($id);
            $nom = trim($_POST['hor_nombre'][$i]  ?? '');
            $hor = trim($_POST['hor_horario'][$i] ?? '');
            $act = isset($_POST['hor_activo'][$id]) ? 1 : 0;
            $stmt->bind_param("ssii", $nom, $hor, $act, $id);
            $stmt->execute();
        }
        $msg = 'Horarios actualizados';

    // ── Horario: agregar ──
    } elseif ($accion === 'agregar_horario') {
        $tipo = $_POST['tipo'] === 'especial' ? 'especial' : 'jornada';
        $nom  = trim($_POST['nombre']  ?? '');
        $hor  = trim($_POST['horario'] ?? '');
        if ($nom && $hor) {
            $conn->query("INSERT INTO bot_horarios (tipo,nombre,horario,orden)
                SELECT '$tipo','".addslashes($nom)."','".addslashes($hor)."', IFNULL(MAX(orden),0)+1 FROM bot_horarios");
            $msg = 'Horario agregado';
        }

    // ── Horario: eliminar ──
    } elseif ($accion === 'eliminar_horario') {
        $conn->query("DELETE FROM bot_horarios WHERE id=".intval($_POST['id']));
        $msg = 'Horario eliminado';

    // ── Autoridades: guardar ──
    } elseif ($accion === 'guardar_autoridades') {
        $ids  = $_POST['aut_id'] ?? [];
        $stmt = $conn->prepare("UPDATE bot_autoridades SET cargo=?,nombre=?,activo=? WHERE id=?");
        foreach ($ids as $i => $id) {
            $id  = intval($id);
            $car = trim($_POST['aut_cargo'][$i]  ?? '');
            $nom = trim($_POST['aut_nombre'][$i] ?? '');
            $act = isset($_POST['aut_activo'][$id]) ? 1 : 0;
            $stmt->bind_param("ssii", $car, $nom, $act, $id);
            $stmt->execute();
        }
        $msg = 'Autoridades actualizadas';

    // ── Autoridad: agregar ──
    } elseif ($accion === 'agregar_autoridad') {
        $car = trim($_POST['cargo']  ?? '');
        $nom = trim($_POST['nombre'] ?? '');
        if ($car && $nom) {
            $conn->query("INSERT INTO bot_autoridades (cargo,nombre,orden)
                SELECT '".addslashes($car)."','".addslashes($nom)."', IFNULL(MAX(orden),0)+1 FROM bot_autoridades");
            $msg = 'Autoridad agregada';
        }

    // ── Autoridad: eliminar ──
    } elseif ($accion === 'eliminar_autoridad') {
        $conn->query("DELETE FROM bot_autoridades WHERE id=".intval($_POST['id']));
        $msg = 'Autoridad eliminada';

    // ── FAQ: guardar ──
    } elseif ($accion === 'guardar_faq') {
        $ids  = $_POST['faq_id'] ?? [];
        $stmt = $conn->prepare("UPDATE bot_faq SET palabras=?,respuesta=?,imagen=?,caption=?,activo=? WHERE id=?");
        foreach ($ids as $i => $id) {
            $id  = intval($id);
            $pal = trim($_POST['faq_palabras'][$i]  ?? '');
            $res = trim($_POST['faq_respuesta'][$i] ?? '');
            $img = trim($_POST['faq_imagen'][$i]    ?? '') ?: null;
            $cap = trim($_POST['faq_caption'][$i]   ?? '') ?: null;
            $act = isset($_POST['faq_activo'][$id]) ? 1 : 0;
            $stmt->bind_param("ssssis", $pal, $res, $img, $cap, $act, $id);
            $stmt->execute();
        }
        $msg = 'FAQ actualizado';

    // ── FAQ: agregar ──
    } elseif ($accion === 'agregar_faq') {
        $pal = trim($_POST['palabras']  ?? '');
        $res = trim($_POST['respuesta'] ?? '');
        $img = trim($_POST['imagen']    ?? '') ?: null;
        $cap = trim($_POST['caption']   ?? '') ?: null;
        if ($pal && $res) {
            $stmt = $conn->prepare("INSERT INTO bot_faq (palabras,respuesta,imagen,caption,orden)
                SELECT ?,?,?,?, IFNULL(MAX(orden),0)+1 FROM bot_faq");
            $stmt->bind_param("ssss", $pal, $res, $img, $cap);
            $stmt->execute();
            $msg = 'Pregunta agregada';
        }

    // ── FAQ: eliminar ──
    } elseif ($accion === 'eliminar_faq') {
        $conn->query("DELETE FROM bot_faq WHERE id=".intval($_POST['id']));
        $msg = 'Pregunta eliminada';

    // ── Plantillas: guardar ──
    } elseif ($accion === 'guardar_plantillas') {
        $ids  = $_POST['pla_id'] ?? [];
        $stmt = $conn->prepare("UPDATE bot_plantillas SET nombre=?,contenido=?,activo=? WHERE id=?");
        foreach ($ids as $i => $id) {
            $id  = intval($id);
            $nom = trim($_POST['pla_nombre'][$i]    ?? '');
            $con = trim($_POST['pla_contenido'][$i] ?? '');
            $act = isset($_POST['pla_activo'][$id]) ? 1 : 0;
            $stmt->bind_param("ssii", $nom, $con, $act, $id);
            $stmt->execute();
        }
        $msg = 'Plantillas actualizadas';

    // ── Plantilla: agregar ──
    } elseif ($accion === 'agregar_plantilla') {
        $nom = trim($_POST['nombre']    ?? '');
        $con = trim($_POST['contenido'] ?? '');
        if ($nom && $con) {
            $stmt = $conn->prepare("INSERT INTO bot_plantillas (nombre,contenido) VALUES (?,?)");
            $stmt->bind_param("ss", $nom, $con);
            $stmt->execute();
            $msg = 'Plantilla agregada';
        }

    // ── Plantilla: eliminar ──
    } elseif ($accion === 'eliminar_plantilla') {
        $conn->query("DELETE FROM bot_plantillas WHERE id=".intval($_POST['id']));
        $msg = 'Plantilla eliminada';
    }

    // Determinar pestaña activa para redirigir
    $tab_map = [
        'guardar_institucion' => 'inst', 'guardar_horarios' => 'hor', 'agregar_horario' => 'hor', 'eliminar_horario' => 'hor',
        'guardar_autoridades' => 'aut', 'agregar_autoridad' => 'aut', 'eliminar_autoridad' => 'aut',
        'guardar_faq' => 'faq', 'agregar_faq' => 'faq', 'eliminar_faq' => 'faq',
        'guardar_plantillas' => 'pla', 'agregar_plantilla' => 'pla', 'eliminar_plantilla' => 'pla',
    ];
    $tab = $tab_map[$accion] ?? 'inst';
    $tipo = str_contains($accion, 'guardar') ? 'guardado' : (str_contains($accion, 'agregar') ? 'agregado' : 'eliminado');
    header("Location: chatbot.php?tab=$tab&ok=$tipo");
    exit;
}

// ── Leer tab activo desde URL ──
$tab_activo = in_array($_GET['tab'] ?? '', ['inst','hor','aut','faq','pla']) ? $_GET['tab'] : 'inst';
$ok_msg = $_GET['ok'] ?? '';

// ══════════════════════════════════════════════════════════════
// CARGAR DATOS PARA MOSTRAR
// ══════════════════════════════════════════════════════════════
$inst        = [];
$rs = $conn->query("SELECT clave,valor,label FROM bot_institucion ORDER BY FIELD(clave,'nombre','direccion','telefono','whatsapp','email','web','maps')");
while ($r = $rs->fetch_assoc()) $inst[$r['clave']] = $r;

$horarios    = $conn->query("SELECT * FROM bot_horarios ORDER BY orden,id")->fetch_all(MYSQLI_ASSOC);
$autoridades = $conn->query("SELECT * FROM bot_autoridades ORDER BY orden,id")->fetch_all(MYSQLI_ASSOC);
$faqs        = $conn->query("SELECT * FROM bot_faq ORDER BY orden,id")->fetch_all(MYSQLI_ASSOC);
$plantillas  = $conn->query("SELECT * FROM bot_plantillas ORDER BY id")->fetch_all(MYSQLI_ASSOC);
?>
<?php require_once 'header.php'; header_html('ChatBot — Configuración'); ?>
<style>
.tabs-nav { display:flex; gap:0; border-bottom:2px solid #e8eaed; margin-bottom:20px; flex-wrap:wrap; }
.tab-btn { background:none; border:none; cursor:pointer; padding:10px 18px; font-size:14px; color:#777; border-bottom:3px solid transparent; margin-bottom:-2px; font-weight:600; white-space:nowrap; transition:color .15s,border-color .15s; }
.tab-btn:hover { color:#1a73e8; }
.tab-btn.activo { color:#1a73e8; border-bottom-color:#1a73e8; }
.tab-panel { display:none; }
.tab-panel.activo { display:block; }
.fila-edit { display:grid; gap:10px; align-items:center; padding:10px 0; border-bottom:1px solid #f0f0f0; }
.fila-edit:last-child { border-bottom:none; }
.fila-3col { grid-template-columns: 1fr 1fr 70px; }
.fila-check { display:flex; align-items:center; gap:6px; font-size:13px; color:#555; }
textarea.bot-area { width:100%; padding:8px; border:1px solid #ddd; border-radius:6px; font-size:13px; font-family:monospace; resize:vertical; min-height:80px; }
textarea.bot-area:focus { outline:none; border-color:#1a73e8; }
.vars-hint { background:#e8f0fe; border-radius:6px; padding:10px 14px; font-size:12px; color:#1557b0; margin-bottom:14px; }
.vars-hint code { background:#c5d8fb; padding:2px 5px; border-radius:3px; font-family:monospace; }
.agregar-form { background:#f8f9fa; border:1px dashed #ccc; border-radius:8px; padding:14px; margin-top:16px; }
.agregar-form h4 { font-size:13px; color:#1a73e8; margin-bottom:10px; }
.faq-card { border:1px solid #e8eaed; border-radius:8px; padding:14px; margin-bottom:12px; }
@media(max-width:600px) {
    .tab-btn { padding:8px 10px; font-size:12px; }
    .fila-3col { grid-template-columns:1fr; }
}
</style>

<div class="container">
    <?php if ($ok_msg): ?><div class="alerta exito">✅ Cambios guardados correctamente</div><?php endif; ?>
    <?php if ($err): ?><div class="alerta error">❌ <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="card">
        <h2>🤖 Configuración del ChatBot WhatsApp</h2>
        <p style="font-size:13px;color:#777;margin-bottom:16px">Los cambios se aplican en tiempo real — el bot lee esta información en cada mensaje recibido.</p>

        <div class="tabs-nav">
            <button type="button" class="tab-btn activo" onclick="cambiarTab('inst',this)">🏫 Institución</button>
            <button type="button" class="tab-btn"        onclick="cambiarTab('hor',this)">🕐 Horarios</button>
            <button type="button" class="tab-btn"        onclick="cambiarTab('aut',this)">🏛️ Autoridades</button>
            <button type="button" class="tab-btn"        onclick="cambiarTab('faq',this)">❓ FAQ</button>
            <button type="button" class="tab-btn"        onclick="cambiarTab('pla',this)">📨 Plantillas</button>
        </div>

        <!-- ══ INSTITUCIÓN ══ -->
        <div id="tab-inst" class="tab-panel activo">
            <form method="POST">
                <input type="hidden" name="accion" value="guardar_institucion">
                <?php foreach ($inst as $clave => $row): ?>
                <div class="form-group">
                    <label><?= htmlspecialchars($row['label']) ?></label>
                    <input type="text" name="<?= $clave ?>" value="<?= htmlspecialchars($row['valor']) ?>">
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn">💾 Guardar datos institucionales</button>
            </form>
        </div>

        <!-- ══ HORARIOS ══ -->
        <div id="tab-hor" class="tab-panel">
            <form method="POST">
                <input type="hidden" name="accion" value="guardar_horarios">
                <?php foreach ($horarios as $h): ?>
                <div class="fila-edit fila-3col">
                    <input type="hidden" name="hor_id[]" value="<?= $h['id'] ?>">
                    <div>
                        <label style="font-size:11px;color:#777"><?= $h['tipo']==='jornada' ? '🎒 Jornada' : '📋 Especial' ?></label>
                        <input type="text" name="hor_nombre[]" value="<?= htmlspecialchars($h['nombre']) ?>" style="margin-top:3px">
                    </div>
                    <div>
                        <label style="font-size:11px;color:#777">Horario</label>
                        <input type="text" name="hor_horario[]" value="<?= htmlspecialchars($h['horario']) ?>" style="margin-top:3px">
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:center;gap:6px;padding-top:18px">
                        <label class="fila-check"><input type="checkbox" name="hor_activo[<?= $h['id'] ?>]" <?= $h['activo']?'checked':'' ?>> Activo</label>
                        <button type="button" class="btn btn-red btn-sm"
                            onclick="if(confirm('¿Eliminar?')) eliminarItem('horario', <?= $h['id'] ?>)">✕</button>
                    </div>
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn" style="margin-top:14px">💾 Guardar horarios</button>
            </form>
            <div class="agregar-form">
                <h4>➕ Agregar horario</h4>
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar_horario">
                    <div class="grid2">
                        <div class="form-group">
                            <label>Tipo</label>
                            <select name="tipo"><option value="jornada">🎒 Jornada</option><option value="especial">📋 Especial</option></select>
                        </div>
                        <div class="form-group"><label>Nombre</label><input type="text" name="nombre" placeholder="ej. Nocturna"></div>
                        <div class="form-group"><label>Horario</label><input type="text" name="horario" placeholder="ej. 18:00 — 22:00"></div>
                    </div>
                    <button type="submit" class="btn btn-green">➕ Agregar</button>
                </form>
            </div>
        </div>

        <!-- ══ AUTORIDADES ══ -->
        <div id="tab-aut" class="tab-panel">
            <form method="POST">
                <input type="hidden" name="accion" value="guardar_autoridades">
                <?php foreach ($autoridades as $a): ?>
                <div class="fila-edit fila-3col">
                    <input type="hidden" name="aut_id[]" value="<?= $a['id'] ?>">
                    <div>
                        <label style="font-size:11px;color:#777">Cargo</label>
                        <input type="text" name="aut_cargo[]" value="<?= htmlspecialchars($a['cargo']) ?>" style="margin-top:3px">
                    </div>
                    <div>
                        <label style="font-size:11px;color:#777">Nombre completo</label>
                        <input type="text" name="aut_nombre[]" value="<?= htmlspecialchars($a['nombre']) ?>" style="margin-top:3px">
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:center;gap:6px;padding-top:18px">
                        <label class="fila-check"><input type="checkbox" name="aut_activo[<?= $a['id'] ?>]" <?= $a['activo']?'checked':'' ?>> Activo</label>
                        <button type="button" class="btn btn-red btn-sm"
                            onclick="if(confirm('¿Eliminar?')) eliminarItem('autoridad', <?= $a['id'] ?>)">✕</button>
                    </div>
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn" style="margin-top:14px">💾 Guardar autoridades</button>
            </form>
            <div class="agregar-form">
                <h4>➕ Agregar autoridad</h4>
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar_autoridad">
                    <div class="grid2">
                        <div class="form-group"><label>Cargo</label><input type="text" name="cargo" placeholder="ej. Subdirector"></div>
                        <div class="form-group"><label>Nombre completo</label><input type="text" name="nombre" placeholder="ej. Lic. Juan Pérez"></div>
                    </div>
                    <button type="submit" class="btn btn-green">➕ Agregar</button>
                </form>
            </div>
        </div>

        <!-- ══ FAQ ══ -->
        <div id="tab-faq" class="tab-panel">
            <p style="font-size:13px;color:#777;margin-bottom:6px">Las <strong>palabras clave</strong> se separan por coma. Si el padre escribe alguna, el bot responde automáticamente.</p>
            <p style="font-size:12px;color:#1557b0;background:#e8f0fe;border-radius:6px;padding:8px 12px;margin-bottom:14px">
                💡 Si subes una imagen del uniforme al hosting con el nombre <code>uniforme.jpg</code>, el bot la enviará automáticamente cuando alguien pregunte por uniformes.
            </p>
            <form method="POST">
                <input type="hidden" name="accion" value="guardar_faq">
                <?php foreach ($faqs as $f): ?>
                <div class="faq-card">
                    <input type="hidden" name="faq_id[]" value="<?= $f['id'] ?>">
                    <div class="form-group">
                        <label>Palabras clave <small style="font-weight:normal;color:#999">(separadas por coma)</small></label>
                        <input type="text" name="faq_palabras[]" value="<?= htmlspecialchars($f['palabras']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Respuesta del bot</label>
                        <textarea class="bot-area" name="faq_respuesta[]"><?= htmlspecialchars($f['respuesta']) ?></textarea>
                    </div>
                    <div class="grid2">
                        <div class="form-group">
                            <label>🖼 URL de imagen <small style="font-weight:normal;color:#999">(opcional)</small></label>
                            <input type="text" name="faq_imagen[]" value="<?= htmlspecialchars($f['imagen'] ?? '') ?>" placeholder="https://as.ecuasys.com/imagen.jpg">
                        </div>
                        <div class="form-group">
                            <label>Pie de imagen <small style="font-weight:normal;color:#999">(caption)</small></label>
                            <input type="text" name="faq_caption[]" value="<?= htmlspecialchars($f['caption'] ?? '') ?>" placeholder="Descripción de la imagen">
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px">
                        <label class="fila-check"><input type="checkbox" name="faq_activo[<?= $f['id'] ?>]" <?= $f['activo']?'checked':'' ?>> Activa</label>
                        <button type="button" class="btn btn-red btn-sm"
                            onclick="if(confirm('¿Eliminar esta pregunta?')) eliminarItem('faq', <?= $f['id'] ?>)">🗑 Eliminar</button>
                    </div>
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn">💾 Guardar FAQ</button>
            </form>
            <div class="agregar-form">
                <h4>➕ Agregar pregunta frecuente</h4>
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar_faq">
                    <div class="form-group"><label>Palabras clave</label><input type="text" name="palabras" placeholder="ej. beca,becas,ayuda economica"></div>
                    <div class="form-group"><label>Respuesta</label><textarea class="bot-area" name="respuesta" placeholder="Escribe la respuesta que dará el bot..."></textarea></div>
                    <div class="grid2">
                        <div class="form-group"><label>🖼 URL de imagen <small style="font-weight:normal;color:#999">(opcional)</small></label><input type="text" name="imagen" placeholder="https://..."></div>
                        <div class="form-group"><label>Pie de imagen</label><input type="text" name="caption" placeholder="Caption opcional"></div>
                    </div>
                    <button type="submit" class="btn btn-green">➕ Agregar</button>
                </form>
            </div>
        </div>

        <!-- ══ PLANTILLAS ══ -->
        <div id="tab-pla" class="tab-panel">
            <div class="vars-hint">
                <strong>Variables disponibles:</strong>
                <code>{rep}</code> Nombre del representante &nbsp;
                <code>{est}</code> Nombre del estudiante &nbsp;
                <code>{curso}</code> Nombre del curso &nbsp;
                <code>{fecha}</code> Fecha de la falta &nbsp;
                <code>\n</code> Salto de línea
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="guardar_plantillas">
                <?php foreach ($plantillas as $p): ?>
                <div style="border:1px solid #e8eaed;border-radius:8px;padding:14px;margin-bottom:12px">
                    <input type="hidden" name="pla_id[]" value="<?= $p['id'] ?>">
                    <div class="form-group"><label>Nombre de la plantilla</label><input type="text" name="pla_nombre[]" value="<?= htmlspecialchars($p['nombre']) ?>"></div>
                    <div class="form-group"><label>Contenido del mensaje</label><textarea class="bot-area" name="pla_contenido[]" style="min-height:140px"><?= htmlspecialchars($p['contenido']) ?></textarea></div>
                    <div style="display:flex;align-items:center;justify-content:space-between">
                        <label class="fila-check"><input type="checkbox" name="pla_activo[<?= $p['id'] ?>]" <?= $p['activo']?'checked':'' ?>> Activa</label>
                        <button type="button" class="btn btn-red btn-sm"
                            onclick="if(confirm('¿Eliminar esta plantilla?')) eliminarItem('plantilla', <?= $p['id'] ?>)">🗑 Eliminar</button>
                    </div>
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn">💾 Guardar plantillas</button>
            </form>
            <div class="agregar-form">
                <h4>➕ Agregar plantilla</h4>
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar_plantilla">
                    <div class="form-group"><label>Nombre</label><input type="text" name="nombre" placeholder="ej. Mensaje urgente"></div>
                    <div class="form-group"><label>Contenido</label><textarea class="bot-area" name="contenido" style="min-height:120px" placeholder="🏫 *U.E. Pomasqui*&#10;&#10;Hola *{rep}*..."></textarea></div>
                    <button type="submit" class="btn btn-green">➕ Agregar</button>
                </form>
            </div>
        </div>

    </div>
</div>

<!-- Formulario oculto para eliminar sin anidar forms -->
<form id="form-eliminar" method="POST" style="display:none">
    <input type="hidden" id="eliminar-accion" name="accion" value="">
    <input type="hidden" id="eliminar-id" name="id" value="">
</form>

<script>
// Formulario oculto para eliminar
function eliminarItem(tipo, id) {
    var form = document.getElementById('form-eliminar');
    document.getElementById('eliminar-accion').value = 'eliminar_' + tipo;
    document.getElementById('eliminar-id').value = id;
    form.submit();
}

function cambiarTab(tab, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('activo'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('activo'));
    document.getElementById('tab-' + tab).classList.add('activo');
    if (btn) btn.classList.add('activo');
}
// Activar pestaña desde URL
document.addEventListener('DOMContentLoaded', function() {
    var tab = '<?= $tab_activo ?>';
    var btn = document.querySelector('.tab-btn[onclick*="' + tab + '"]');
    cambiarTab(tab, btn);
});
</script>

<?php footer_html(); $conn->close(); ?>