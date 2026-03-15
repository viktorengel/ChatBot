<?php
require_once 'config.php';

date_default_timezone_set('America/Guayaquil');
$conn = conectar();

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

// Verificar si ya se envio hoy
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

// Obtener todos los representantes del estudiante
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

// Normalizar telefono
function norm_tel($telefono) {
    $tel = preg_replace('/\D/', '', $telefono);
    if (substr($tel, 0, 3) !== '593') {
        $tel = substr($tel, 0, 1) === '0' ? '593' . substr($tel, 1) : '593' . $tel;
    }
    return $tel;
}

$fecha_formato = date('d/m/Y', strtotime($pendiente['fecha']));

function enviar_whatsapp($telefono, $nombre_rep, $nombre_est, $curso, $fecha_formato) {
    $mensaje  = "\xF0\x9F\x8F\xAB *Unidad Educativa Pomasqui*\n\n";
    $mensaje .= "Estimado/a *{$nombre_rep}*,\n\n";
    $mensaje .= "Le informamos que su representado/a:\n";
    $mensaje .= "\xF0\x9F\x91\xA4 *{$nombre_est}*\n";
    $mensaje .= "\xF0\x9F\x93\x9A Curso: *{$curso}*\n\n";
    $mensaje .= "No asisti\xC3\xB3 a clases el d\xC3\xADa *{$fecha_formato}*.\n\n";
    $mensaje .= "Por favor com\xC3\xBAn\xC3\xADquese con la instituci\xC3\xB3n.\n\n";
    $mensaje .= "_Mensaje autom\xC3\xA1tico - U.E. Pomasqui_";

    $payload = json_encode([
        "number" => $telefono,
        "options" => ["delay" => 1000, "presence" => "composing"],
        "textMessage" => ["text" => $mensaje]
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\napikey: " . EVOLUTION_KEY . "\r\n",
            'content' => $payload,
            'timeout' => 15,
            'ignore_errors' => true
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);

    return file_get_contents(
        EVOLUTION_URL . "/message/sendText/" . EVOLUTION_INSTANCE,
        false,
        $context
    );
}

// Enviar a todos los representantes
$enviado = false;
foreach ($reps as $i => $rep) {
    if ($i > 0) sleep(rand(3, 6));

    $tel = norm_tel($rep['telefono']) . "@s.whatsapp.net";
    $response = enviar_whatsapp(
        $tel,
        $rep['nombre'],
        $pendiente['estudiante'],
        $pendiente['curso'] ?? 'Sin curso',
        $fecha_formato
    );

    if ($response !== false) {
        $enviado = true;
        file_put_contents('/home/ecuasysc/as.ecuasys.com/cron_log.txt',
            date('Y-m-d H:i:s') . " - Enviado a {$rep['nombre']} ({$tel})\n",
            FILE_APPEND
        );
    }
}

if ($enviado) {
    $conn->query("UPDATE faltas SET mensaje_enviado = 1 WHERE id = {$pendiente['id']}");
}

$conn->close();
?>
