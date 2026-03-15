<?php
session_start();
if (!isset($_SESSION['docente_id'])) {
    header('Location: login.php');
    exit;
}

function esAdmin() {
    return $_SESSION['docente_rol'] === 'admin';
}

function soloAdmin() {
    if (!esAdmin()) {
        header('Location: index.php');
        exit;
    }
}
