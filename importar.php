<?php
require_once 'auth.php';
soloAdmin();
require_once 'config.php';
require_once 'header.php';

$conn = conectar();
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $archivo = $_FILES['archivo'];
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

    if ($ext !== 'xlsx') {
        $resultado = ['error' => 'Solo se aceptan archivos .xlsx'];
    } elseif ($archivo['error'] !== UPLOAD_ERR_OK) {
        $resultado = ['error' => 'Error al subir el archivo'];
    } else {
        $tmp = $archivo['tmp_name'];
        $stats = procesarExcel($tmp, $conn);
        $resultado = $stats;
    }
}

function procesarExcel($archivo, $conn) {
    // Leer el Excel manualmente (sin librería externa)
    $zip = new ZipArchive();
    if ($zip->open($archivo) !== TRUE) {
        return ['error' => 'No se pudo abrir el archivo Excel'];
    }

    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $strings_xml = $zip->getFromName('xl/sharedStrings.xml');
    $zip->close();

    if (!$xml) return ['error' => 'Archivo Excel inválido o vacío'];

    // Parsear strings compartidos
    $strings = [];
    if ($strings_xml) {
        $sxml = simplexml_load_string($strings_xml);
        foreach ($sxml->si as $si) {
            $strings[] = (string)($si->t ?? implode('', (array)$si->r->t ?? []));
        }
    }

    // Parsear celdas
    $sheet = simplexml_load_string($xml);
    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $row_data = [];
        foreach ($row->c as $cell) {
            $col_letter = preg_replace('/[0-9]/', '', (string)$cell['r']);
            $col_index = col_to_index($col_letter);
            $type = (string)$cell['t'];
            $value = (string)$cell->v;
            if ($type === 's') {
                $value = $strings[(int)$value] ?? '';
            }
            $row_data[$col_index] = trim($value);
        }
        $rows[] = $row_data;
    }

    // Buscar fila de encabezados (donde está "apellido_nombre")
    $header_row = -1;
    foreach ($rows as $idx => $row) {
        if (isset($row[0]) && strtolower($row[0]) === 'apellido_nombre') {
            $header_row = $idx;
            break;
        }
    }

    if ($header_row === -1) {
        return ['error' => 'No se encontró la fila de encabezados. Asegúrate de usar la plantilla correcta.'];
    }

    // Procesar filas de datos
    $importados = 0;
    $omitidos = 0;
    $errores = [];

    for ($i = $header_row + 1; $i < count($rows); $i++) {
        $row = $rows[$i];

        $nombre     = $row[0] ?? '';
        $curso      = $row[1] ?? '';
        $rep1_nom   = $row[2] ?? '';
        $rep1_tel   = preg_replace('/\D/', '', $row[3] ?? '');
        $rep2_nom   = $row[4] ?? '';
        $rep2_tel   = preg_replace('/\D/', '', $row[5] ?? '');

        // Saltar filas vacías o de ejemplo
        if (empty($nombre) || empty($curso) || empty($rep1_nom) || empty($rep1_tel)) {
            if (!empty($nombre)) $errores[] = "Fila " . ($i+1) . ": '$nombre' omitido por datos incompletos";
            continue;
        }

        // Saltar filas de ejemplo
        if (strpos(strtolower($nombre), 'pérez garcía carlos') !== false) continue;

        // Obtener o crear curso
        $curso_id = obtenerOCrear($conn, 'cursos', 'nombre', $curso);

        // Obtener o crear representante 1
        $rep1_id = obtenerOCrearRepresentante($conn, $rep1_nom, $rep1_tel);

        // Obtener o crear representante 2
        $rep2_id = null;
        if (!empty($rep2_nom) && !empty($rep2_tel)) {
            $rep2_id = obtenerOCrearRepresentante($conn, $rep2_nom, $rep2_tel);
        }

        // Verificar si el estudiante ya existe
        $stmt = $conn->prepare("SELECT id FROM estudiantes WHERE nombre = ? AND curso_id = ?");
        $stmt->bind_param("si", $nombre, $curso_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $omitidos++;
            continue;
        }

        // Insertar estudiante
        if ($rep2_id) {
            $stmt = $conn->prepare("INSERT INTO estudiantes (nombre, curso_id, representante_id, representante2_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siii", $nombre, $curso_id, $rep1_id, $rep2_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO estudiantes (nombre, curso_id, representante_id) VALUES (?, ?, ?)");
            $stmt->bind_param("sii", $nombre, $curso_id, $rep1_id);
        }

        if ($stmt->execute()) {
            $importados++;
        } else {
            $errores[] = "Error al importar '$nombre': " . $conn->error;
        }
    }

    return [
        'importados' => $importados,
        'omitidos' => $omitidos,
        'errores' => $errores
    ];
}

function obtenerOCrear($conn, $tabla, $campo, $valor) {
    $stmt = $conn->prepare("SELECT id FROM $tabla WHERE $campo = ?");
    $stmt->bind_param("s", $valor);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) return $row['id'];
    $stmt2 = $conn->prepare("INSERT INTO $tabla ($campo) VALUES (?)");
    $stmt2->bind_param("s", $valor);
    $stmt2->execute();
    return $conn->insert_id;
}

function obtenerOCrearRepresentante($conn, $nombre, $telefono) {
    $stmt = $conn->prepare("SELECT id FROM representantes WHERE telefono = ?");
    $stmt->bind_param("s", $telefono);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) return $row['id'];
    $stmt2 = $conn->prepare("INSERT INTO representantes (nombre, telefono) VALUES (?, ?)");
    $stmt2->bind_param("ss", $nombre, $telefono);
    $stmt2->execute();
    return $conn->insert_id;
}

function col_to_index($col) {
    $col = strtoupper($col);
    $index = 0;
    for ($i = 0; $i < strlen($col); $i++) {
        $index = $index * 26 + (ord($col[$i]) - ord('A'));
    }
    return $index;
}

header_html('Importar Estudiantes');
?>
<div class="container">

    <div class="card">
        <h2>📥 Importar Estudiantes desde Excel</h2>

        <p style="margin-bottom:15px;color:#555;font-size:14px;">
            Descarga la plantilla, llénala con los datos de los estudiantes y súbela aquí.
        </p>

        <a href="plantilla_estudiantes.xlsx" class="btn btn-green" style="display:inline-block;margin-bottom:20px;text-decoration:none;">
            📄 Descargar Plantilla Excel
        </a>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Seleccionar archivo Excel (.xlsx)</label>
                <input type="file" name="archivo" accept=".xlsx" required style="padding:8px;">
            </div>
            <button type="submit" class="btn">📤 Importar Estudiantes</button>
        </form>
    </div>

    <?php if ($resultado): ?>
    <div class="card">
        <h2>📊 Resultado de la Importación</h2>

        <?php if (isset($resultado['error'])): ?>
            <div class="alerta error">❌ <?= htmlspecialchars($resultado['error']) ?></div>

        <?php else: ?>
            <div class="alerta exito">
                ✅ <strong><?= $resultado['importados'] ?> estudiantes importados correctamente</strong>
                <?php if ($resultado['omitidos'] > 0): ?>
                    <br>⏭ <?= $resultado['omitidos'] ?> estudiantes omitidos (ya existían)
                <?php endif; ?>
            </div>

            <?php if (!empty($resultado['errores'])): ?>
            <div class="alerta error">
                <strong>Advertencias:</strong><br>
                <?php foreach ($resultado['errores'] as $err): ?>
                    — <?= htmlspecialchars($err) ?><br>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <a href="estudiantes.php" class="btn" style="text-decoration:none;display:inline-block;margin-top:10px;">
                👤 Ver lista de estudiantes
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
<?php footer_html(); $conn->close(); ?>
