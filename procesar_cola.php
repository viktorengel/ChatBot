<?php
require_once 'config.php';

date_default_timezone_set('America/Guayaquil');
$conn = conectar();

// ── URL del servidor HTTP interno de Baileys ──
define('BAILEYS_URL', 'https://whatsapp.ecuasys.com/send');

$pendiente = $conn->query("
    SELECT f.id, f.fecha, e.nombre as estudiante, c.nombre as curso, f.estudiante_id
    FROM faltas f
    JOIN estudiantes e ON f.estudiante_id = e.id
    LEFT JOIN cursos c ON e.curso_id = c.id
    WHERE f.mensaje_enviado = 0
    ORDER BY f.created_at ASC
    LIMIT 1
")->fetch_assoc();

if (!$pendiente) exit;

// Verificar si ya se envió hoy para este estudiante
$ya_enviado = $conn->query("
    SELECT id FROM faltas
    WHERE estudiante_id = {$pendiente['estudiante_id']}
    AND fecha = '{$pendiente['fecha']}'
    AND mensaje_enviado = 1
    AND id != {$pendiente['id']}
")->num_rows > 0;

if ($ya_enviado) {
    $conn->query("UPDATE faltas SET mensaje_enviado = 1 WHERE id = {$pendiente['id']}");
    $conn->close();
    exit;
}

// Obtener representantes del estudiante
$representantes = $conn->query("
    SELECT r.nombre, r.telefono
    FROM representantes r
    JOIN estudiante_representante er ON er.representante_id = r.id
    WHERE er.estudiante_id = {$pendiente['estudiante_id']}
    ORDER BY er.es_principal DESC
");
$reps = [];
while ($r = $representantes->fetch_assoc()) $reps[] = $r;

if (empty($reps)) {
    $conn->query("UPDATE faltas SET mensaje_enviado = 1 WHERE id = {$pendiente['id']}");
    $conn->close();
    exit;
}

// Normalizar teléfono al formato Baileys
function norm_tel($telefono) {
    $tel = preg_replace('/\D/', '', $telefono);
    if (substr($tel, 0, 3) !== '593') {
        $tel = substr($tel, 0, 1) === '0' ? '593' . substr($tel, 1) : '593' . $tel;
    }
    return $tel . '@s.whatsapp.net';
}

// 5 plantillas rotativas (igual que antes)
function elegir_plantilla($nombre_rep, $nombre_est, $curso, $fecha) {
    $plantillas = [
        "🏫 *Unidad Educativa Pomasqui*\n\n"
        . "Estimado/a *{$nombre_rep}*,\n\n"
        . "Le informamos que su representado/a:\n"
        . "👤 *{$nombre_est}*\n"
        . "📚 Curso: *{$curso}*\n\n"
        . "No asistió a clases el día *{$fecha}*.\n\n"
        . "Le solicitamos justificar esta inasistencia con su *docente tutor* a la brevedad posible.\n\n"
        . "_Mensaje automático - U.E. Pomasqui_",

        "📢 *U.E. Pomasqui* le saluda cordialmente.\n\n"
        . "Estimado/a representante *{$nombre_rep}*:\n\n"
        . "Por medio del presente le notificamos que el/la estudiante\n"
        . "✏️ *{$nombre_est}* — *{$curso}*\n\n"
        . "registró una *ausencia* el día *{$fecha}*.\n\n"
        . "Agradecemos justificar la inasistencia con el *docente tutor*.\n\n"
        . "_Notificación automática - U.E. Pomasqui_",

        "🏫 *U.E. Pomasqui*\n\n"
        . "Hola *{$nombre_rep}*, le comunicamos que:\n\n"
        . "🔴 *{$nombre_est}* no asistió a clases el día *{$fecha}*.\n"
        . "📖 Curso: *{$curso}*\n\n"
        . "Por favor justificar la falta con el *docente tutor*.\n\n"
        . "_Aviso automático - U.E. Pomasqui_",

        "👋 *Unidad Educativa Pomasqui*\n\n"
        . "Estimado/a *{$nombre_rep}*,\n\n"
        . "Le informamos que el *{$fecha}* su representado/a\n"
        . "📌 *{$nombre_est}* del curso *{$curso}*\n\n"
        . "no asistió a la institución.\n\n"
        . "Le pedimos comunicarse y realizar la justificación correspondiente con el *docente tutor* en la brevedad posible.\n\n"
        . "_Sistema de asistencia - U.E. Pomasqui_",

        "📋 *Reporte de Asistencia*\n"
        . "*Unidad Educativa Pomasqui*\n\n"
        . "Apreciado/a *{$nombre_rep}*:\n\n"
        . "Le notificamos que *{$nombre_est}* (*{$curso}*)\n"
        . "no registró asistencia el día *{$fecha}*.\n\n"
        . "⚠️ Es importante justificar la ausencia con su *docente tutor*.\n\n"
        . "_Mensaje automático - U.E. Pomasqui_",
    ];
    return $plantillas[array_rand($plantillas)];
}

// Enviar a través de Baileys (HTTP interno puerto 3001)
function enviar_whatsapp_baileys($numero, $texto) {
    $payload = json_encode(['number' => $numero, 'text' => $texto]);
    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\napikey: " . EVOLUTION_KEY . "\r\n",
            'content'       => $payload,
            'timeout'       => 15,
            'ignore_errors' => true,
        ]
    ]);
    $resp = file_get_contents(BAILEYS_URL, false, $context);
    if ($resp === false) return false;
    $json = json_decode($resp, true);
    return isset($json['status']) && $json['status'] === 'sent';
}

$fecha_fmt = date('d/m/Y', strtotime($pendiente['fecha']));
$enviado   = false;

foreach ($reps as $i => $rep) {
    if ($i > 0) sleep(rand(3, 6));

    $numero  = norm_tel($rep['telefono']);
    $mensaje = elegir_plantilla(
        $rep['nombre'],
        $pendiente['estudiante'],
        $pendiente['curso'] ?? 'Sin curso',
        $fecha_fmt
    );

    $ok = enviar_whatsapp_baileys($numero, $mensaje);

    file_put_contents(
        '/home/ecuasysc/as.ecuasys.com/cron_log.txt',
        date('Y-m-d H:i:s') . ' - ' . ($ok ? '✅' : '❌') . " {$rep['nombre']} ({$numero})\n",
        FILE_APPEND
    );

    if ($ok) $enviado = true;
}

if ($enviado) {
    $conn->query("UPDATE faltas SET mensaje_enviado = 1 WHERE id = {$pendiente['id']}");
}

$conn->close();