<?php
require_once 'auth.php';
require_once 'config.php';

$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
$origen = $_GET['origen'] ?? 'index';

if ($id > 0) {
    $conn = conectar();
    $conn->query("DELETE FROM faltas WHERE id = $id");
    $conn->close();
}

if ($origen === 'reportes') {
    header('Location: reportes.php?msg=Falta eliminada correctamente');
} else {
    header('Location: index.php?msg=Falta eliminada correctamente');
}
exit;
?>
