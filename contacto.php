<?php
// Si viene desde un celular y hace clic en descargar, servir la vCard
if (isset($_GET['descargar'])) {
    header('Content-Type: text/vcard; charset=utf-8');
    header('Content-Disposition: attachment; filename="UEPomasqui.vcf"');
    echo "BEGIN:VCARD\r\n";
    echo "VERSION:3.0\r\n";
    echo "FN:UE Pomasqui\r\n";
    echo "ORG:Unidad Educativa Pomasqui\r\n";
    echo "TITLE:Contacto Institucional\r\n";
    echo "TEL;TYPE=CELL:+593964259358\r\n";
    echo "EMAIL:17h01988@gmail.com\r\n";
    echo "ADR:;;Quito;;;Ecuador\r\n";
    echo "URL:https://www.iepomasqui.com\r\n";
    echo "END:VCARD\r\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>U.E. Pomasqui — Guardar Contacto</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 20px;
            padding: 35px 30px;
            max-width: 380px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo {
            width: 80px;
            height: 80px;
            background: #1a73e8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
        }
        h1 {
            color: #1a73e8;
            font-size: 22px;
            margin-bottom: 6px;
        }
        .subtitulo {
            color: #777;
            font-size: 14px;
            margin-bottom: 25px;
        }
        .info-box {
            background: #f0f4ff;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 25px;
            text-align: left;
        }
        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #e0e7ff;
            font-size: 14px;
            color: #333;
        }
        .info-item:last-child { border-bottom: none; }
        .info-icon { font-size: 20px; width: 28px; text-align: center; }
        .info-texto { flex: 1; }
        .info-label { font-size: 11px; color: #888; display: block; }
        .btn-guardar {
            background: #25D366;
            color: white;
            border: none;
            padding: 16px 30px;
            border-radius: 12px;
            font-size: 17px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            text-decoration: none;
            display: block;
            margin-bottom: 12px;
        }
        .btn-guardar:hover { background: #1ea952; }
        .btn-whatsapp {
            background: white;
            color: #25D366;
            border: 2px solid #25D366;
            padding: 14px 30px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            text-decoration: none;
            display: block;
            margin-bottom: 20px;
        }
        .instruccion {
            font-size: 12px;
            color: #aaa;
            line-height: 1.5;
        }
        .paso {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-top: 15px;
            text-align: left;
            font-size: 12px;
            color: #555;
        }
        .paso strong { color: #1a73e8; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">🏫</div>
    <h1>U.E. Pomasqui</h1>
    <p class="subtitulo">Sistema de Notificaciones de Asistencia</p>

    <div class="info-box">
        <div class="info-item">
            <span class="info-icon">📱</span>
            <div class="info-texto">
                <span class="info-label">WhatsApp Institucional</span>
                +593 964 259 358
            </div>
        </div>
        <div class="info-item">
            <span class="info-icon">🌐</span>
            <div class="info-texto">
                <span class="info-label">Sitio web</span>
                www.iepomasqui.com
            </div>
        </div>
        <div class="info-item">
            <span class="info-icon">📍</span>
            <div class="info-texto">
                <span class="info-label">Ubicación</span>
                Quito, Ecuador
            </div>
        </div>
    </div>

    <a href="?descargar=1" class="btn-guardar">
        📲 Guardar Contacto en mi Celular
    </a>

    <a href="https://wa.me/593964259358?text=Hola,%20soy%20representante%20de%20la%20U.E.%20Pomasqui" class="btn-whatsapp" target="_blank">
        💬 Abrir en WhatsApp
    </a>

    <p class="instruccion">
        Al guardar el contacto recibirás las notificaciones de asistencia correctamente en tu celular.
    </p>

    <div class="paso">
        <strong>¿Por qué guardar el contacto?</strong><br>
        Si el número del colegio está en tu agenda, WhatsApp mostrará las notificaciones con sonido y vibración normalmente.
    </div>
</div>
</body>
</html>
