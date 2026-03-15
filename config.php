<?php
// ============================================
// CONFIGURACIÓN BASE DE DATOS
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecuasysc_asistencias');
define('DB_USER', 'ecuasysc_user');
define('DB_PASS', 'Orktvi.5/*83e');

// ============================================
// CONFIGURACIÓN EVOLUTION API
// ============================================
define('EVOLUTION_URL', 'https://whatsapp.ecuasys.com');
define('EVOLUTION_KEY', 'colegio_pomasqui_2026');
define('EVOLUTION_INSTANCE', 'colegio-pomasqui');

// ============================================
// CONEXIÓN A BASE DE DATOS
// ============================================
function conectar() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");
    return $conn;
}
