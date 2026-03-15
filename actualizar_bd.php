<?php
require_once 'config.php';
$conn = conectar();

// Ejecutar sentencias una por una
$sentencias = [
    // Tabla cursos
    "CREATE TABLE IF NOT EXISTS cursos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        nivel VARCHAR(50),
        jornada VARCHAR(20) DEFAULT 'Matutina',
        activo TINYINT(1) DEFAULT 1
    )",

    // Tabla representantes
    "CREATE TABLE IF NOT EXISTS representantes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        telefono VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // Tabla estudiantes SIN representante obligatorio
    "CREATE TABLE IF NOT EXISTS estudiantes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        curso_id INT NULL,
        curso VARCHAR(50) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (curso_id) REFERENCES cursos(id)
    )",

    // Tabla de relación estudiante-representante
    "CREATE TABLE IF NOT EXISTS estudiante_representante (
        id INT AUTO_INCREMENT PRIMARY KEY,
        estudiante_id INT NOT NULL,
        representante_id INT NOT NULL,
        es_principal TINYINT(1) DEFAULT 1,
        UNIQUE KEY unico (estudiante_id, representante_id),
        FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id),
        FOREIGN KEY (representante_id) REFERENCES representantes(id)
    )",

    // Tabla docentes
    "CREATE TABLE IF NOT EXISTS docentes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        rol ENUM('admin','docente') DEFAULT 'docente',
        activo TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // Tabla docente_cursos
    "CREATE TABLE IF NOT EXISTS docente_cursos (
        docente_id INT NOT NULL,
        curso_id INT NOT NULL,
        PRIMARY KEY (docente_id, curso_id),
        FOREIGN KEY (docente_id) REFERENCES docentes(id),
        FOREIGN KEY (curso_id) REFERENCES cursos(id)
    )",

    // Tabla faltas
    "CREATE TABLE IF NOT EXISTS faltas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        estudiante_id INT NOT NULL,
        fecha DATE NOT NULL,
        motivo VARCHAR(200) DEFAULT 'Inasistencia registrada por el docente',
        mensaje_enviado TINYINT(1) DEFAULT 0,
        docente_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id)
    )",

    // Columnas adicionales por si ya existe la tabla
    "ALTER TABLE cursos ADD COLUMN IF NOT EXISTS jornada VARCHAR(20) DEFAULT 'Matutina'",
    "ALTER TABLE faltas ADD COLUMN IF NOT EXISTS docente_id INT NULL",
    "ALTER TABLE estudiantes ADD COLUMN IF NOT EXISTS curso_id INT NULL",
    "ALTER TABLE estudiantes MODIFY COLUMN curso VARCHAR(50) NULL DEFAULT NULL",
];

$errores = [];
foreach ($sentencias as $sql) {
    if (!$conn->query($sql)) {
        if ($conn->errno != 1060 && $conn->errno != 1061 && $conn->errno != 1050) {
            $errores[] = $conn->error . " → " . substr($sql, 0, 60);
        }
    }
}

// Insertar admin por defecto
$pass = password_hash('admin123', PASSWORD_DEFAULT);
$conn->query("INSERT IGNORE INTO docentes (nombre, email, password, rol) VALUES ('Administrador', 'admin@ecuasys.com', '$pass', 'admin')");

echo "<h2 style='color:green;font-family:Arial'>✅ Base de datos actualizada correctamente</h2>";
if (!empty($errores)) {
    echo "<p style='color:orange;font-family:Arial'><strong>Advertencias:</strong></p><ul>";
    foreach ($errores as $e) echo "<li style='font-family:Arial;font-size:13px'>$e</li>";
    echo "</ul>";
}
echo "<p style='font-family:Arial'><strong>Usuario admin:</strong> admin@ecuasys.com</p>";
echo "<p style='font-family:Arial'><strong>Contraseña:</strong> admin123</p>";
echo "<p style='font-family:Arial'><a href='login.php'>Ir al login</a></p>";

$conn->close();
?>
