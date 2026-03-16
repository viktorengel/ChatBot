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
        $resultado = procesarExcel($archivo['tmp_name'], $conn);
    }
}

function procesarExcel($archivo, $conn) {
    $zip = new ZipArchive();
    if ($zip->open($archivo) !== TRUE) return ['error' => 'No se pudo abrir el archivo Excel'];

    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $strings_xml = $zip->getFromName('xl/sharedStrings.xml');
    $zip->close();

    if (!$xml) return ['error' => 'Archivo Excel inválido o vacío'];

    // Parsear strings compartidos
    $strings = [];
    if ($strings_xml) {
        $sxml = simplexml_load_string($strings_xml);
        foreach ($sxml->si as $si) {
            $t = '';
            if (isset($si->t)) {
                $t = (string)$si->t;
            } elseif (isset($si->r)) {
                foreach ($si->r as $r) {
                    if (isset($r->t)) $t .= (string)$r->t;
                }
            }
            $strings[] = $t;
        }
    }

    // Parsear celdas (soporta sharedStrings e inlineStr)
    $sheet = simplexml_load_string($xml);
    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $row_data = [];
        foreach ($row->c as $cell) {
            $col_letter = preg_replace('/[0-9]/', '', (string)$cell['r']);
            $col_index = col_to_index($col_letter);
            $type = (string)$cell['t'];
            $value = '';
            if ($type === 's') {
                // sharedStrings
                $value = $strings[(int)(string)$cell->v] ?? '';
            } elseif ($type === 'inlineStr') {
                // inline string (formato openpyxl)
                $value = (string)$cell->is->t;
            } else {
                $value = (string)$cell->v;
            }
            $row_data[$col_index] = trim($value);
        }
        $rows[] = $row_data;
    }

    // Buscar fila de encabezados
    $header_row = -1;
    foreach ($rows as $idx => $row) {
        $first = strtolower(trim($row[0] ?? ''));
        if (in_array($first, ['jornada', '* jornada'])) {
            $header_row = $idx;
            break;
        }
    }

    if ($header_row === -1) return ['error' => 'No se encontró la fila de encabezados. Usa la plantilla correcta.'];

    $importados = 0;
    $omitidos = 0;
    $errores = [];

    for ($i = $header_row + 2; $i < count($rows); $i++) {
        $row = $rows[$i];

        $jornada    = trim($row[0] ?? '');
        $nivel      = trim($row[1] ?? '');
        $paralelo   = trim($row[2] ?? '');
        $nombre     = trim($row[3] ?? '');
        $rep1_nom   = trim($row[4] ?? '');
        $rep1_tel   = normalizar_telefono($row[5] ?? '');
        $rep2_nom   = trim($row[6] ?? '');
        $rep2_tel   = normalizar_telefono($row[7] ?? '');

        // Saltar filas vacías o de ejemplo
        if (empty($nombre) || empty($jornada) || empty($nivel) || empty($paralelo)) continue;
        if (stripos($nombre, 'Pérez García Carlos') !== false) continue;
        if (stripos($nombre, 'Mora Suárez Ana') !== false) continue;
        if (stripos($nombre, 'López Torres Diego') !== false) continue;

        // Construir búsqueda flexible del curso
        $prefijo = "$nivel \"$paralelo\"";
        $sufijo = "— $jornada";

        // Buscar curso que coincida con nivel + paralelo + jornada
        // Funciona con y sin figura profesional
        $stmt = $conn->prepare("SELECT id FROM cursos WHERE nombre LIKE ? AND nombre LIKE ?");
        $like_prefijo = $prefijo . '%';
        $like_sufijo = '%' . $sufijo;
        $stmt->bind_param("ss", $like_prefijo, $like_sufijo);
        $stmt->execute();
        $curso_row = $stmt->get_result()->fetch_assoc();

        if (!$curso_row) {
            $errores[] = "Fila " . ($i+1) . ": Curso '$nombre_curso' no existe en el sistema";
            continue;
        }
        $curso_id = $curso_row['id'];

        // Verificar si ya existe el estudiante en ese curso
        $check = $conn->prepare("SELECT id FROM estudiantes WHERE nombre = ? AND curso_id = ?");
        $check->bind_param("si", $nombre, $curso_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $omitidos++;
            continue;
        }

        // Insertar estudiante
        $stmt2 = $conn->prepare("INSERT INTO estudiantes (nombre, curso_id) VALUES (?, ?)");
        $stmt2->bind_param("si", $nombre, $curso_id);
        if (!$stmt2->execute()) {
            $errores[] = "Fila " . ($i+1) . ": Error al insertar '$nombre'";
            continue;
        }
        $estudiante_id = $conn->insert_id;
        $importados++;

        // Representante 1
        if (!empty($rep1_nom) && !empty($rep1_tel)) {
            $rep1_id = obtenerOCrearRepresentante($conn, $rep1_nom, $rep1_tel);
            $conn->query("INSERT IGNORE INTO estudiante_representante (estudiante_id, representante_id, es_principal) VALUES ($estudiante_id, $rep1_id, 1)");
        }

        // Representante 2
        if (!empty($rep2_nom) && !empty($rep2_tel)) {
            $rep2_id = obtenerOCrearRepresentante($conn, $rep2_nom, $rep2_tel);
            $conn->query("INSERT IGNORE INTO estudiante_representante (estudiante_id, representante_id, es_principal) VALUES ($estudiante_id, $rep2_id, 0)");
        }
    }

    return ['importados' => $importados, 'omitidos' => $omitidos, 'errores' => $errores];
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
        <p style="color:#555;font-size:14px;margin-bottom:20px">
            Descarga la plantilla, completa los datos y sube el archivo aquí. 
            Los estudiantes ya existentes en el mismo curso serán omitidos automáticamente.
        </p>

        <a href="plantilla_estudiantes.xlsx" class="btn btn-green" style="text-decoration:none;display:inline-block;margin-bottom:20px">
            📄 Descargar Plantilla Excel
        </a>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Seleccionar archivo Excel (.xlsx)</label>
                <input type="file" name="archivo" accept=".xlsx" required style="padding:8px">
            </div>
            <button type="submit" class="btn">📤 Importar</button>
        </form>
    </div>

    <?php if ($resultado): ?>
    <div class="card">
        <h2>📊 Resultado</h2>
        <?php if (isset($resultado['error'])): ?>
            <div class="alerta error">❌ <?= htmlspecialchars($resultado['error']) ?></div>
        <?php else: ?>
            <div class="alerta exito">
                ✅ <strong><?= $resultado['importados'] ?> estudiante(s) importado(s) correctamente</strong>
                <?php if ($resultado['omitidos'] > 0): ?>
                    <br>⏭ <?= $resultado['omitidos'] ?> omitido(s) por ya existir
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
            <a href="estudiantes.php" class="btn" style="text-decoration:none;display:inline-block;margin-top:10px">
                👤 Ver lista de estudiantes
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php footer_html(); $conn->close(); ?>
