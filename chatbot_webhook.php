<?php
// ============================================================
//  CHATBOT WEBHOOK — Unidad Educativa Pomasqui
//  URL a configurar en Evolution API:
//  https://as.ecuasys.com/chatbot_webhook.php
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/chatbot_sesiones.php';

date_default_timezone_set('America/Guayaquil');

$cfg  = require __DIR__ . '/chatbot_config.php';
$conn = conectar();

// ── RECIBIR PAYLOAD ─────────────────────────────────────────
$raw     = file_get_contents('php://input');
$payload = json_decode($raw, true);

// Log para depuración (desactivar en producción)
// file_put_contents(__DIR__ . '/chatbot_log.txt', date('Y-m-d H:i:s') . " " . $raw . "\n", FILE_APPEND);

// Validar que sea un mensaje de texto entrante
if (
    empty($payload['event']) ||
    $payload['event'] !== 'messages.upsert' ||
    empty($payload['data']['message']['conversation']) ||
    !empty($payload['data']['key']['fromMe'])          // ignorar mensajes propios
) {
    http_response_code(200);
    exit;
}

$numero_raw = $payload['data']['key']['remoteJid'] ?? '';
$texto      = trim($payload['data']['message']['conversation'] ?? '');
$numero     = preg_replace('/@.*$/', '', $numero_raw); // quitar @s.whatsapp.net

if (empty($numero) || empty($texto)) {
    http_response_code(200);
    exit;
}

// ── PROCESAR Y RESPONDER ────────────────────────────────────
$respuesta = procesar_mensaje($numero, $texto, $cfg, $conn);
if ($respuesta) enviar_respuesta($numero, $respuesta);

$conn->close();
http_response_code(200);
exit;


// ════════════════════════════════════════════════════════════
//  LÓGICA PRINCIPAL
// ════════════════════════════════════════════════════════════

function procesar_mensaje(string $numero, string $texto, array $cfg, $conn): string {
    $sesion = sesion_obtener($numero);
    $estado = $sesion['estado'];
    $datos  = $sesion['datos'];
    $txt    = strtolower(trim($texto));

    // Comando cancelar en cualquier momento
    if (in_array($txt, ['cancelar', 'salir', 'exit', 'menu', 'menú', '0'])) {
        sesion_limpiar($numero);
        return menu_principal();
    }

    // ── FLUJO DE CONSULTA DE FALTAS ──────────────────────────
    if ($estado === 'esperando_cedula') {
        return flujo_consultar_faltas($numero, $texto, $cfg, $conn);
    }

    // ── ESTADO INICIO: detectar intención ───────────────────
    sesion_limpiar($numero);

    // Opción numérica del menú
    if (preg_match('/^[1-9]$/', $txt)) {
        return responder_opcion_menu((int)$txt, $numero, $cfg, $conn);
    }

    // Detectar intención por palabras clave
    $intencion = detectar_intencion($txt, $cfg['intenciones']);

    // FAQ antes de intenciones generales
    $faq = buscar_faq($txt, $cfg['faq']);
    if ($faq) {
        // Si la FAQ tiene imagen, enviarla primero
        if (!empty($faq['imagen'])) {
            enviar_imagen($numero, $faq['imagen'], $faq['caption'] ?? '');
        }
        return $faq['respuesta'];
    }

    switch ($intencion) {
        case 'saludo':
            return bienvenida($cfg);
        case 'horario':
            return respuesta_horarios($cfg);
        case 'autoridades':
            return respuesta_autoridades($cfg);
        case 'niveles':
            return respuesta_niveles($cfg);
        case 'figuras':
            return respuesta_figuras($cfg);
        case 'atencion_padres':
            return respuesta_atencion_padres($cfg);
        case 'faltas':
            sesion_guardar($numero, 'esperando_cedula');
            return "🔍 *Consulta de Asistencia*\n\nPor favor escribe la *cédula* del estudiante que deseas consultar:\n\n_(Escribe *cancelar* para volver al menú)_";
        case 'ubicacion':
            return respuesta_ubicacion($cfg);
        case 'contacto':
            return respuesta_contacto($cfg);
        default:
            return no_entendido($cfg);
    }
}


// ── FLUJO CONSULTA FALTAS ────────────────────────────────────

function flujo_consultar_faltas(string $numero, string $cedula, array $cfg, $conn): string {
    $cedula = preg_replace('/\D/', '', trim($cedula));

    if (strlen($cedula) < 8 || strlen($cedula) > 12) {
        return "⚠️ La cédula ingresada no es válida. Por favor escribe solo los números (8 a 10 dígitos).\n\n_(Escribe *cancelar* para volver al menú)_";
    }

    // Buscar estudiante por cédula
    $ced_safe  = $conn->real_escape_string($cedula);
    $estudiante = $conn->query("
        SELECT e.id, e.nombre, c.nombre as curso
        FROM estudiantes e
        LEFT JOIN cursos c ON e.curso_id = c.id
        WHERE e.cedula = '$ced_safe'
        LIMIT 1
    ")->fetch_assoc();

    if (!$estudiante) {
        sesion_limpiar($numero);
        return "❌ No encontramos ningún estudiante registrado con la cédula *{$cedula}*.\n\nVerifica el número e intenta nuevamente o comunícate con Secretaría.\n\n" . pie_mensaje($cfg);
    }

    // Verificar que el número que consulta es representante de ese estudiante
    $tel_limpio = preg_replace('/\D/', '', $numero);
    // Normalizar: quitar 593 al inicio si existe para comparar con BD
    $tel_bd = $tel_limpio;
    if (substr($tel_bd, 0, 3) === '593') $tel_bd = '0' . substr($tel_bd, 3);

    $tel_safe = $conn->real_escape_string($tel_bd);
    $es_representante = $conn->query("
        SELECT r.id FROM representantes r
        JOIN estudiante_representante er ON er.representante_id = r.id
        WHERE er.estudiante_id = {$estudiante['id']}
        AND (r.telefono LIKE '%$tel_safe%' OR r.telefono LIKE '%$tel_limpio%')
        LIMIT 1
    ")->num_rows > 0;

    if (!$es_representante) {
        sesion_limpiar($numero);
        return "🔒 Tu número no está registrado como representante de *{$estudiante['nombre']}*.\n\nSi crees que es un error, comunícate con Secretaría.\n\n" . pie_mensaje($cfg);
    }

    // Contar faltas del mes actual y el año lectivo
    $mes_actual  = date('Y-m');
    $anio_actual = date('Y');

    $faltas_mes = (int)$conn->query("
        SELECT COUNT(*) as total FROM faltas
        WHERE estudiante_id = {$estudiante['id']}
        AND DATE_FORMAT(fecha, '%Y-%m') = '$mes_actual'
    ")->fetch_assoc()['total'];

    $faltas_anio = (int)$conn->query("
        SELECT COUNT(*) as total FROM faltas
        WHERE estudiante_id = {$estudiante['id']}
        AND YEAR(fecha) = $anio_actual
    ")->fetch_assoc()['total'];

    // Últimas 5 faltas
    $ultimas_rs = $conn->query("
        SELECT fecha FROM faltas
        WHERE estudiante_id = {$estudiante['id']}
        ORDER BY fecha DESC
        LIMIT 5
    ");
    $dias_es = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    $ultimas = [];
    while ($f = $ultimas_rs->fetch_assoc()) {
        $ts        = strtotime($f['fecha']);
        $ultimas[] = '• ' . $dias_es[date('w', $ts)] . ' ' . date('d/m/Y', $ts);
    }

    sesion_limpiar($numero);

    $nombre_est = $estudiante['nombre'];
    $curso      = $estudiante['curso'] ?? 'Sin curso';
    $mes_fmt    = strftime('%B %Y') ?: date('m/Y');

    $msg  = "📊 *Reporte de Asistencia*\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "👤 *{$nombre_est}*\n";
    $msg .= "📚 Curso: *{$curso}*\n\n";
    $msg .= "📅 Faltas este mes: *{$faltas_mes}*\n";
    $msg .= "📆 Faltas este año: *{$faltas_anio}*\n";

    if (!empty($ultimas)) {
        $msg .= "\n🗓️ *Últimas inasistencias:*\n";
        $msg .= implode("\n", $ultimas) . "\n";
    } else {
        $msg .= "\n✅ Sin inasistencias recientes.\n";
    }

    $msg .= "\n" . pie_mensaje($cfg);
    return $msg;
}


// ════════════════════════════════════════════════════════════
//  RESPUESTAS
// ════════════════════════════════════════════════════════════

function bienvenida(array $cfg): string {
    $inst = $cfg['institucion']['nombre'];
    return "👋 ¡Bienvenido/a al chatbot de *{$inst}*!\n\n" . menu_principal();
}

function menu_principal(): string {
    return "📋 *¿En qué puedo ayudarte?*\n\n"
         . "1️⃣  Horarios de jornadas\n"
         . "2️⃣  Atención a padres y representantes\n"
         . "3️⃣  Autoridades de la institución\n"
         . "4️⃣  Niveles educativos\n"
         . "5️⃣  Figuras del Bachillerato Técnico\n"
         . "6️⃣  Consultar faltas de mi representado/a\n"
         . "7️⃣  Ubicación y contacto\n\n"
         . "_Escribe el número de tu consulta o hazme tu pregunta directamente_ 💬";
}

function responder_opcion_menu(int $opcion, string $numero, array $cfg, $conn): string {
    switch ($opcion) {
        case 1: return respuesta_horarios($cfg);
        case 2: return respuesta_atencion_padres($cfg);
        case 3: return respuesta_autoridades($cfg);
        case 4: return respuesta_niveles($cfg);
        case 5: return respuesta_figuras($cfg);
        case 6:
            sesion_guardar($numero, 'esperando_cedula');
            return "🔍 *Consulta de Asistencia*\n\nPor favor escribe la *cédula* del estudiante:\n\n_(Escribe *cancelar* para volver al menú)_";
        case 7: return respuesta_ubicacion($cfg) . "\n\n" . respuesta_contacto($cfg);
        default: return no_entendido($cfg);
    }
}

function respuesta_horarios(array $cfg): string {
    $h   = $cfg['horarios'];
    $msg = "🕐 *Horarios de Jornadas*\n\n";
    foreach ($h['jornadas'] as $jornada => $hora) {
        $msg .= "• *{$jornada}:* {$hora}\n";
    }
    if (!empty($h['secretaria'])) $msg .= "\n🏢 *Secretaría:* {$h['secretaria']}";
    if (!empty($h['colecturia'])) $msg .= "\n💰 *Colecturía:* {$h['colecturia']}";
    $msg .= "\n\n" . pie_mensaje($cfg);
    return $msg;
}

function respuesta_atencion_padres(array $cfg): string {
    $a   = $cfg['atencion_padres'];
    $msg = "👨‍👩‍👧 *Atención a Padres y Representantes*\n\n";
    $msg .= "🕐 *Horario:* {$a['horario']}\n\n";
    $msg .= "ℹ️ {$a['nota']}\n\n";
    $msg .= "📞 *Citas:* {$a['cita_contacto']}";
    $msg .= "\n\n" . pie_mensaje($cfg);
    return $msg;
}

function respuesta_autoridades(array $cfg): string {
    $msg = "🏛️ *Autoridades de la Institución*\n\n";
    foreach ($cfg['autoridades'] as $a) {
        $msg .= "• *{$a['cargo']}*\n  {$a['nombre']}\n";
    }
    $msg .= "\n" . pie_mensaje($cfg);
    return $msg;
}

function respuesta_niveles(array $cfg): string {
    $msg = "🎓 *Oferta Educativa*\n\n";
    foreach ($cfg['niveles'] as $nivel => $info) {
        $msg .= "📌 *{$nivel}* — {$info['descripcion']}\n";
    }
    $msg .= "\nPara más información escribe *5* para ver las figuras del Bachillerato Técnico.";
    $msg .= "\n\n" . pie_mensaje($cfg);
    return $msg;
}

function respuesta_figuras(array $cfg): string {
    $msg = "🔧 *Figuras del Bachillerato Técnico*\n\n";
    foreach ($cfg['figuras_profesionales'] as $f) {
        $msg .= "✅ *{$f['figura']}*\n";
        $msg .= "   {$f['descripcion']}\n\n";
    }
    $msg .= pie_mensaje($cfg);
    return $msg;
}

function respuesta_ubicacion(array $cfg): string {
    $inst = $cfg['institucion'];
    $msg  = "📍 *Ubicación*\n\n";
    $msg .= "{$inst['nombre']}\n";
    $msg .= "{$inst['direccion']}\n\n";
    $msg .= "📌 Puedes buscarnos en Google Maps como:\n_{$inst['nombre']}_";
    return $msg;
}

function respuesta_contacto(array $cfg): string {
    $inst = $cfg['institucion'];
    $msg  = "📞 *Contacto*\n\n";
    $msg .= "☎️  Teléfono: {$inst['telefono']}\n";
    $msg .= "📱 WhatsApp: {$inst['whatsapp']}\n";
    $msg .= "✉️  Correo: {$inst['email']}\n";
    $msg .= "🌐 Web: {$inst['web']}";
    return $msg;
}

function no_entendido(array $cfg): string {
    return "🤔 No entendí tu consulta.\n\n" . menu_principal();
}

function pie_mensaje(array $cfg): string {
    return "_" . $cfg['institucion']['nombre'] . " — Chatbot informativo_";
}


// ════════════════════════════════════════════════════════════
//  DETECCIÓN DE INTENCIÓN Y FAQ
// ════════════════════════════════════════════════════════════

function detectar_intencion(string $texto, array $intenciones): ?string {
    foreach ($intenciones as $intencion => $palabras) {
        foreach ($palabras as $palabra) {
            if (str_contains($texto, $palabra)) return $intencion;
        }
    }
    return null;
}

function buscar_faq(string $texto, array $faq): ?array {
    foreach ($faq as $item) {
        foreach ($item['palabras_clave'] as $clave) {
            if (str_contains($texto, $clave)) return $item;
        }
    }
    return null;
}


// ════════════════════════════════════════════════════════════
//  ENVIAR MENSAJE POR EVOLUTION API
// ════════════════════════════════════════════════════════════

function enviar_imagen(string $numero, string $url_imagen, string $caption = ''): void {
    $payload = json_encode([
        'number'       => $numero . '@s.whatsapp.net',
        'options'      => ['delay' => 800, 'presence' => 'composing'],
        'mediaMessage' => [
            'mediatype' => 'image',
            'media'     => $url_imagen,
            'caption'   => $caption,
        ],
    ]);

    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json
apikey: " . EVOLUTION_KEY . "
",
            'content'       => $payload,
            'timeout'       => 15,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    file_get_contents(
        EVOLUTION_URL . '/message/sendMedia/' . EVOLUTION_INSTANCE,
        false,
        $context
    );
}

function enviar_respuesta(string $numero, string $mensaje): void {
    $payload = json_encode([
        'number'      => $numero . '@s.whatsapp.net',
        'options'     => ['delay' => 1200, 'presence' => 'composing'],
        'textMessage' => ['text' => $mensaje],
    ]);

    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\napikey: " . EVOLUTION_KEY . "\r\n",
            'content'       => $payload,
            'timeout'       => 15,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    file_get_contents(
        EVOLUTION_URL . '/message/sendText/' . EVOLUTION_INSTANCE,
        false,
        $context
    );
}