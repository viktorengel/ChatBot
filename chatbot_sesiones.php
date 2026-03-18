<?php
// ============================================================
//  CHATBOT SESIONES — Manejo de estado por número de teléfono
//  Guarda el estado de cada conversación en /tmp (archivo JSON)
// ============================================================

define('SESIONES_FILE', sys_get_temp_dir() . '/chatbot_sesiones.json');
define('SESION_TTL', 300); // 5 minutos de inactividad resetea la sesión

function sesion_cargar_todas(): array {
    if (!file_exists(SESIONES_FILE)) return [];
    $data = json_decode(file_get_contents(SESIONES_FILE), true);
    return is_array($data) ? $data : [];
}

function sesion_guardar_todas(array $sesiones): void {
    file_put_contents(SESIONES_FILE, json_encode($sesiones), LOCK_EX);
}

function sesion_obtener(string $numero): array {
    $todas = sesion_cargar_todas();
    $s     = $todas[$numero] ?? null;

    // Expirada o no existe → sesión limpia
    if (!$s || (time() - ($s['ts'] ?? 0)) > SESION_TTL) {
        return ['estado' => 'inicio', 'datos' => [], 'ts' => time()];
    }
    return $s;
}

function sesion_guardar(string $numero, string $estado, array $datos = []): void {
    $todas           = sesion_cargar_todas();
    $todas[$numero]  = ['estado' => $estado, 'datos' => $datos, 'ts' => time()];

    // Limpiar sesiones expiradas al guardar
    $ahora = time();
    foreach ($todas as $num => $s) {
        if (($ahora - ($s['ts'] ?? 0)) > SESION_TTL) unset($todas[$num]);
    }
    sesion_guardar_todas($todas);
}

function sesion_limpiar(string $numero): void {
    $todas = sesion_cargar_todas();
    unset($todas[$numero]);
    sesion_guardar_todas($todas);
}
