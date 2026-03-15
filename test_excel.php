<?php
require_once 'config.php';

$archivo = '/home/ecuasysc/as.ecuasys.com/plantilla_estudiantes.xlsx';

$zip = new ZipArchive();
$zip->open($archivo);
$xml = $zip->getFromName('xl/worksheets/sheet1.xml');
$strings_xml = $zip->getFromName('xl/sharedStrings.xml');
$zip->close();

$strings = [];
if ($strings_xml) {
    $sxml = simplexml_load_string($strings_xml);
    foreach ($sxml->si as $si) {
        $t = '';
        if (isset($si->t)) $t = (string)$si->t;
        elseif (isset($si->r)) foreach ($si->r as $r) if (isset($r->t)) $t .= (string)$r->t;
        $strings[] = $t;
    }
}

$sheet = simplexml_load_string($xml);
$count = 0;
foreach ($sheet->sheetData->row as $row) {
    if ($count >= 6) break;
    echo "<strong>Fila " . ($count+1) . ":</strong> ";
    foreach ($row->c as $cell) {
        $type = (string)$cell['t'];
        $value = (string)$cell->v;
        if ($type === 's') $value = $strings[(int)$value] ?? '';
        echo "[" . htmlspecialchars($value) . "] ";
    }
    echo "<br>";
    $count++;
}
?>