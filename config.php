<<<<<<< HEAD
<?php
// ============================================
// CONFIGURACIÓN BASE DE DATOS
// ============================================
define('DB_HOST', 'paul.hostservercloud.com');
define('DB_NAME', 'ecuasysc_asistencias');
define('DB_USER', 'ecuasysc_user');
define('DB_PASS', 'Orktvi.5/*83e');

// ============================================
// CONFIGURACIÓN EVOLUTION API
// ============================================
define('EVOLUTION_URL', 'https://whatsapp.ecuasys.com');
define('EVOLUTION_KEY', 'colegio_pomasqui_2026');
define('EVOLUTION_INSTANCE', 'ChatBot');

// ============================================
// CONEXIÓN A BASE DE DATOS
// ============================================
function conectar() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// ============================================
// NORMALIZAR TELEFONO ECUATORIANO
// ============================================
function normalizar_telefono($telefono) {
    $tel = preg_replace('/\D/', '', $telefono);
    if (substr($tel, 0, 3) === '593') return $tel;
    if (substr($tel, 0, 1) === '0') return '593' . substr($tel, 1);
    if (strlen($tel) === 9) return '593' . $tel;
    return $tel;
=======
<?php
// ============================================
// CONFIGURACIÓN BASE DE DATOS
// ============================================
define('DB_HOST', 'paul.hostservercloud.com');
define('DB_NAME', 'ecuasysc_asistencias');
define('DB_USER', 'ecuasysc_user');
define('DB_PASS', 'Orktvi.5/*83e');

// ============================================
// CONFIGURACIÓN EVOLUTION API
// ============================================
define('EVOLUTION_URL', 'https://whatsapp.ecuasys.com');
define('EVOLUTION_KEY', 'colegio_pomasqui_2026');
define('EVOLUTION_INSTANCE', 'ChatBot');

// ============================================
// CONEXIÓN A BASE DE DATOS
// ============================================
function conectar() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// ============================================
// NORMALIZAR TELEFONO ECUATORIANO
// ============================================
function normalizar_telefono($telefono) {
    $tel = preg_replace('/\D/', '', $telefono);
    if (substr($tel, 0, 3) === '593') return $tel;
    if (substr($tel, 0, 1) === '0') return '593' . substr($tel, 1);
    if (strlen($tel) === 9) return '593' . $tel;
    return $tel;
>>>>>>> d8d42b35e9176076c87f46a03fe24cea881c0842
}