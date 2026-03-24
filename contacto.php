<?php
define('DB_HOST', 'paul.hostservercloud.com');
define('DB_NAME', 'ecuasysc_asistencias');
define('DB_USER', 'ecuasysc_user');
define('DB_PASS', 'Orktvi.5/*83e');

function conectarBd() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
    return $conn;
}

// ── DESCARGAR vCard ──────────────────────────────────────────
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

// ── VERIFICAR SI YA ACEPTÓ ───────────────────────────────────
if (isset($_GET['verificar'])) {
    header('Content-Type: application/json');
    $tel = preg_replace('/\D/', '', $_GET['tel'] ?? '');
    if (substr($tel, 0, 1) === '0') $tel = '593' . substr($tel, 1);
    if (strlen($tel) < 11) { echo json_encode(['acepto' => false]); exit; }
    $conn = conectarBd();
    $stmt = $conn->prepare('SELECT fecha FROM bot_consentimientos WHERE telefono = ? LIMIT 1');
    $stmt->bind_param('s', $tel);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $conn->close();
    echo json_encode(['acepto' => (bool)$row, 'fecha' => $row['fecha'] ?? null]);
    exit;
}

// ── REGISTRAR NÚMERO ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['telefono'])) {
    header('Content-Type: application/json');

    $tel = preg_replace('/\D/', '', $_POST['telefono']);
    if (substr($tel, 0, 1) === '0') $tel = '593' . substr($tel, 1);
    if (strlen($tel) < 11 || strlen($tel) > 13) {
        echo json_encode(['ok' => false, 'error' => 'Número no válido']);
        exit;
    }

    $forzar = isset($_POST['forzar']) && $_POST['forzar'] === '1';
    $ip     = $_SERVER['REMOTE_ADDR'] ?? '';
    $conn   = conectarBd();

    $stmt = $conn->prepare('SELECT fecha FROM bot_consentimientos WHERE telefono = ? LIMIT 1');
    $stmt->bind_param('s', $tel);
    $stmt->execute();
    $yaAcepto = $stmt->get_result()->fetch_assoc();

    // Si ya existe y no se fuerza el cambio, devolver estado
    if ($yaAcepto && !$forzar) {
        $conn->close();
        echo json_encode(['ok' => true, 'ya_registrado' => true, 'fecha' => $yaAcepto['fecha']]);
        exit;
    }

    // Guardar o actualizar consentimiento
    $stmt2 = $conn->prepare('INSERT INTO bot_consentimientos (telefono, ip) VALUES (?, ?) ON DUPLICATE KEY UPDATE ip=VALUES(ip), fecha=NOW()');
    $stmt2->bind_param('ss', $tel, $ip);
    $stmt2->execute();

    $logLine = date('Y-m-d H:i:s') . " | $tel | IP: $ip | " . ($forzar ? 'Cambio de número' : 'Primer registro') . "\n";
    @file_put_contents(__DIR__ . '/consentimiento_log.txt', $logLine, FILE_APPEND);
    $conn->close();

    // Enviar mensaje de bienvenida
    $texto = $forzar
        ? "🔄 *Número actualizado — U.E. Pomasqui*\n\nTu número ha sido actualizado correctamente en nuestro sistema.\n\nEscribe *hola* para ver el menú de opciones.\n\n_U.E. Pomasqui — Sistema Automático_"
        : "👋 *Bienvenido/a al sistema de notificaciones de la U.E. Pomasqui*\n\n✅ Tu número ha sido registrado correctamente.\n\nA partir de ahora recibirás notificaciones de asistencia de tus representados/as.\n\nEscribe *hola* para ver el menú de opciones.\n\n_U.E. Pomasqui — Sistema Automático_";

    $payload = json_encode(['number' => $tel . '@s.whatsapp.net', 'text' => $texto]);
    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\napikey: colegio_pomasqui_2026\r\n",
            'content'       => $payload,
            'timeout'       => 10,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    @file_get_contents('https://whatsapp.ecuasys.com/send', false, $context);

    echo json_encode(['ok' => true, 'ya_registrado' => false]);
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
            display: flex; align-items: center; justify-content: center;
            padding: 20px;
        }
        .card {
            background: white; border-radius: 20px;
            padding: 35px 30px; max-width: 400px; width: 100%;
            text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo {
            width: 80px; height: 80px; background: #1a73e8;
            border-radius: 50%; display: flex;
            align-items: center; justify-content: center;
            margin: 0 auto 16px; font-size: 36px;
        }
        h1 { color: #1a73e8; font-size: 22px; margin-bottom: 6px; }
        .subtitulo { color: #777; font-size: 14px; margin-bottom: 28px; }
        .btn-guardar {
            background: #25D366; color: white; border: none;
            padding: 16px 30px; border-radius: 12px;
            font-size: 17px; font-weight: bold; cursor: pointer;
            width: 100%; display: block; margin-bottom: 12px;
        }
        .btn-guardar:hover { background: #1ea952; }
        .btn-whatsapp {
            background: white; color: #25D366; border: 2px solid #25D366;
            padding: 14px 30px; border-radius: 12px; font-size: 15px;
            font-weight: bold; width: 100%; text-decoration: none; display: block;
        }
        .badge-registrado {
            background: #e8f5e9; border: 1px solid #a5d6a7;
            border-radius: 10px; padding: 12px 16px;
            color: #2e7d32; font-size: 13px; margin-bottom: 16px; display: none;
        }

        /* OVERLAY */
        .overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.55); z-index: 100;
            align-items: center; justify-content: center; padding: 20px;
        }
        .overlay.active { display: flex; }

        /* MODAL TÉRMINOS */
        .modal-terminos {
            background: white; border-radius: 16px;
            max-width: 420px; width: 100%;
            max-height: 90vh; display: flex; flex-direction: column;
        }
        .modal-header {
            background: #1a73e8; color: white;
            padding: 18px 20px; border-radius: 16px 16px 0 0; flex-shrink: 0;
        }
        .modal-header h2 { font-size: 16px; margin-bottom: 4px; }
        .modal-header p  { font-size: 12px; opacity: 0.85; }
        .modal-body {
            flex: 1; overflow-y: auto; padding: 20px;
            font-size: 13px; color: #444; line-height: 1.7;
        }
        .modal-body h3 { color: #1a73e8; font-size: 14px; margin: 14px 0 8px; }
        .modal-body ul  { padding-left: 18px; margin: 8px 0; }
        .modal-body ul li { margin-bottom: 6px; }
        .modal-body .destacado {
            background: #f0f4ff; border-left: 3px solid #1a73e8;
            padding: 10px 14px; border-radius: 4px; margin: 14px 0; font-size: 12px;
        }
        .modal-footer { padding: 16px 20px; border-top: 1px solid #eee; flex-shrink: 0; }
        .checkbox-row { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 14px; }
        .checkbox-row input[type=checkbox] {
            width: 20px; height: 20px; margin-top: 2px;
            accent-color: #1a73e8; cursor: pointer; flex-shrink: 0;
        }
        .checkbox-row label { font-size: 13px; color: #333; cursor: pointer; line-height: 1.5; }

        /* MODAL GENÉRICO */
        .modal-box {
            background: white; border-radius: 16px;
            padding: 28px 24px; max-width: 340px; width: 100%;
            text-align: center;
        }
        .modal-box h2 { font-size: 18px; margin-bottom: 8px; }
        .modal-box p  { color: #666; font-size: 13px; margin-bottom: 18px; line-height: 1.5; }
        .modal-box input {
            width: 100%; padding: 14px; border: 2px solid #ddd;
            border-radius: 10px; font-size: 20px; text-align: center;
            letter-spacing: 3px; margin-bottom: 16px;
        }
        .modal-box input:focus { outline: none; border-color: #1a73e8; }

        /* Número grande de confirmación */
        .numero-grande {
            background: #f0f4ff; border: 2px solid #1a73e8;
            border-radius: 12px; padding: 16px;
            font-size: 24px; font-weight: bold; color: #1a73e8;
            letter-spacing: 3px; margin-bottom: 8px;
        }
        .numero-desc { font-size: 12px; color: #888; margin-bottom: 20px; }

        .btn-primario {
            background: #25D366; color: white; border: none;
            padding: 14px; border-radius: 10px; font-size: 16px;
            font-weight: bold; cursor: pointer; width: 100%; margin-bottom: 10px;
        }
        .btn-primario:disabled { background: #aaa; cursor: not-allowed; }
        .btn-secundario {
            background: none; border: none; color: #1a73e8;
            font-size: 14px; cursor: pointer; width: 100%; padding: 8px; font-weight: bold;
        }
        .btn-cancelar {
            background: none; border: none; color: #aaa;
            font-size: 13px; cursor: pointer; width: 100%; padding: 6px;
        }
        .btn-peligro {
            background: none; border: 2px solid #e53935; color: #e53935;
            padding: 12px; border-radius: 10px; font-size: 14px;
            font-weight: bold; cursor: pointer; width: 100%; margin-bottom: 10px;
        }
        .msg-error   { color: #e53935; font-size: 13px; margin-bottom: 10px; }
        .msg-success { color: #2e7d32; font-size: 14px; margin-bottom: 10px; font-weight: bold; }

        .divider { border: none; border-top: 1px solid #eee; margin: 12px 0; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">🏫</div>
    <h1>U.E. Pomasqui</h1>
    <p class="subtitulo">Sistema de Notificaciones de Asistencia</p>

    <div class="badge-registrado" id="badge-registrado">
        ✅ Ya estás registrado/a. Puedes descargar el contacto directamente.
    </div>

    <button class="btn-guardar" id="btn-principal" onclick="iniciar()">
        📲 Registrarme y Guardar Contacto
    </button>

    <a href="https://wa.me/593964259358?text=Hola,%20soy%20representante%20de%20la%20U.E.%20Pomasqui"
       class="btn-whatsapp" target="_blank">
        💬 Escribir al Colegio por WhatsApp
    </a>
</div>

<!-- OVERLAY TÉRMINOS -->
<div class="overlay" id="overlay-terminos">
    <div class="modal-terminos">
        <div class="modal-header">
            <h2>📋 Autorización de Datos Personales</h2>
            <p>Ley Orgánica de Protección de Datos Personales del Ecuador</p>
        </div>
        <div class="modal-body">
            <h3>Finalidades del tratamiento</h3>
            <p>De conformidad con la Ley Orgánica de Protección de Datos Personales del Ecuador, autorizo de manera libre, informada y expresa a la institución educativa a recopilar, almacenar y utilizar los datos personales proporcionados en este formulario, con las siguientes finalidades:</p>
            <ul>
                <li>Gestionar procesos académicos, administrativos y de registro estudiantil.</li>
                <li>Elaborar reportes de calificaciones, asistencia y seguimiento académico.</li>
                <li>Enviar información institucional, comunicados, actividades y eventos escolares.</li>
                <li>Mantener contacto con representantes legales para temas educativos.</li>
            </ul>
            <div class="destacado">
                La institución garantiza que la información será tratada con confidencialidad y no será compartida con terceros, salvo obligación legal o requerimiento de autoridad competente.
            </div>
            <h3>Derechos del titular</h3>
            <p>El titular de los datos (o su representante legal) podrá ejercer sus derechos de <strong>acceso, rectificación, actualización o eliminación</strong> de sus datos personales mediante solicitud dirigida a la institución.</p>
        </div>
        <div class="modal-footer">
            <div class="checkbox-row">
                <input type="checkbox" id="chk-acepto" onchange="toggleAceptar()">
                <label for="chk-acepto">
                    <strong>ACEPTO</strong> el tratamiento de mis datos personales conforme a lo descrito anteriormente para fines académicos y administrativos del colegio.
                </label>
            </div>
            <button class="btn-primario" id="btn-aceptar" disabled onclick="abrirTelefono()">
                ✅ Aceptar y Continuar
            </button>
            <button class="btn-cancelar" onclick="cerrarTerminos()">Cancelar</button>
        </div>
    </div>
</div>

<!-- OVERLAY TELÉFONO -->
<div class="overlay" id="overlay-tel">
    <div class="modal-box">
        <h2 style="color:#1a73e8">📱 Tu número de celular</h2>
        <p>Ingresa el número registrado en la secretaría del colegio para activar las notificaciones.</p>
        <input type="tel" id="tel-input" placeholder="0998 000 000"
               maxlength="12" inputmode="numeric"
               oninput="this.value=this.value.replace(/\D/g,'')">
        <div class="msg-error" id="tel-error" style="display:none"></div>
        <button class="btn-primario" onclick="confirmarNumero()">Continuar →</button>
        <button class="btn-secundario" onclick="volverTerminos()">← Volver</button>
        <button class="btn-cancelar" onclick="cerrarTodo()">Cancelar</button>
    </div>
</div>

<!-- OVERLAY CONFIRMAR NÚMERO -->
<div class="overlay" id="overlay-confirmar">
    <div class="modal-box">
        <h2 style="color:#1a73e8">✅ Confirma tu número</h2>
        <p>¿Este es tu número de celular correcto?</p>
        <div class="numero-grande" id="numero-display"></div>
        <p class="numero-desc">Asegúrate de que el número sea el correcto antes de continuar.</p>
        <div class="msg-error"   id="confirmar-error"   style="display:none"></div>
        <div class="msg-success" id="confirmar-success" style="display:none"></div>
        <button class="btn-primario" id="btn-si-correcto" onclick="registrar(false)">
            ✅ Sí, es correcto
        </button>
        <button class="btn-secundario" onclick="corregirNumero()">✏️ Corregir número</button>
        <button class="btn-cancelar" onclick="cerrarTodo()">Cancelar</button>
    </div>
</div>

<!-- OVERLAY YA REGISTRADO -->
<div class="overlay" id="overlay-ya-registrado">
    <div class="modal-box">
        <h2 style="color:#2e7d32">✅ Ya estás registrado/a</h2>
        <p>El número</p>
        <div class="numero-grande" id="ya-numero-display"></div>
        <p style="margin-bottom:20px">ya se encuentra registrado en nuestro sistema desde <strong id="ya-fecha"></strong>.</p>
        <button class="btn-primario" onclick="descargarVcard()">
            ⬇️ Descargar Contacto del Colegio
        </button>
        <hr class="divider">
        <p style="font-size:12px;color:#888;margin-bottom:12px">¿Quieres usar un número diferente?</p>
        <button class="btn-peligro" onclick="cambiarNumero()">
            🔄 Cambiar mi número
        </button>
        <button class="btn-cancelar" onclick="cerrarTodo()">Cerrar</button>
    </div>
</div>

<script>
let telActual = ''
let forzarCambio = false

window.addEventListener('load', () => {
    const telGuardado = localStorage.getItem('uep_tel')
    if (telGuardado) verificarRegistro(telGuardado)
})

async function verificarRegistro(tel) {
    try {
        const res  = await fetch('?verificar=1&tel=' + encodeURIComponent(tel))
        const data = await res.json()
        if (data.acepto) {
            document.getElementById('badge-registrado').style.display = 'block'
            const btn = document.getElementById('btn-principal')
            btn.textContent = '⬇️ Descargar Contacto del Colegio'
            btn.onclick = descargarVcard
        }
    } catch {}
}

function iniciar() {
    forzarCambio = false
    document.getElementById('overlay-terminos').classList.add('active')
}

function cerrarTerminos() {
    document.getElementById('overlay-terminos').classList.remove('active')
    document.getElementById('chk-acepto').checked = false
    document.getElementById('btn-aceptar').disabled = true
}

function toggleAceptar() {
    document.getElementById('btn-aceptar').disabled = !document.getElementById('chk-acepto').checked
}

function abrirTelefono() {
    document.getElementById('overlay-terminos').classList.remove('active')
    document.getElementById('overlay-tel').classList.add('active')
    document.getElementById('tel-input').value = ''
    document.getElementById('tel-error').style.display = 'none'
    document.getElementById('tel-input').focus()
}

function volverTerminos() {
    document.getElementById('overlay-tel').classList.remove('active')
    document.getElementById('overlay-terminos').classList.add('active')
}

function confirmarNumero() {
    const tel = document.getElementById('tel-input').value.trim()
    const err = document.getElementById('tel-error')
    err.style.display = 'none'
    if (tel.length < 9 || tel.length > 12) {
        err.textContent = 'Ingresa un número válido (ej: 0998368685)'
        err.style.display = 'block'
        return
    }
    telActual = tel
    // Formatear para mostrar: 0998 368 685
    const fmt = tel.replace(/(\d{4})(\d{3})(\d{3,4})/, '$1 $2 $3')
    document.getElementById('numero-display').textContent = fmt
    document.getElementById('confirmar-error').style.display = 'none'
    document.getElementById('confirmar-success').style.display = 'none'
    document.getElementById('btn-si-correcto').disabled = false
    document.getElementById('btn-si-correcto').textContent = '✅ Sí, es correcto'
    document.getElementById('overlay-tel').classList.remove('active')
    document.getElementById('overlay-confirmar').classList.add('active')
}

function corregirNumero() {
    document.getElementById('overlay-confirmar').classList.remove('active')
    document.getElementById('overlay-tel').classList.add('active')
    document.getElementById('tel-input').focus()
}

async function registrar(forzar) {
    const err = document.getElementById('confirmar-error')
    const suc = document.getElementById('confirmar-success')
    const btn = document.getElementById('btn-si-correcto')
    err.style.display = 'none'
    suc.style.display = 'none'
    btn.disabled = true
    btn.textContent = '⏳ Registrando...'

    try {
        const body = 'telefono=' + encodeURIComponent(telActual) + (forzar ? '&forzar=1' : '')
        const res  = await fetch('', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body })
        const data = await res.json()

        if (data.ok) {
            if (data.ya_registrado && !forzar) {
                // Mostrar modal de ya registrado
                document.getElementById('overlay-confirmar').classList.remove('active')
                const fmt = telActual.replace(/(\d{4})(\d{3})(\d{3,4})/, '$1 $2 $3')
                document.getElementById('ya-numero-display').textContent = fmt
                document.getElementById('ya-fecha').textContent = data.fecha ? data.fecha.split(' ')[0] : 'fecha anterior'
                document.getElementById('overlay-ya-registrado').classList.add('active')
            } else {
                localStorage.setItem('uep_tel', telActual)
                suc.textContent = forzar ? '✅ Número actualizado. Revisa tu WhatsApp!' : '✅ Registro exitoso. Revisa tu WhatsApp!'
                suc.style.display = 'block'
                btn.textContent = '⬇️ Descargando contacto...'
                setTimeout(() => {
                    window.location.href = '?descargar=1'
                    setTimeout(cerrarTodo, 2000)
                }, 1500)
            }
        } else {
            err.textContent = data.error || 'Error al registrar. Intenta de nuevo.'
            err.style.display = 'block'
            btn.disabled = false
            btn.textContent = '✅ Sí, es correcto'
        }
    } catch(e) {
        err.textContent = 'Error de conexión. Intenta de nuevo.'
        err.style.display = 'block'
        btn.disabled = false
        btn.textContent = '✅ Sí, es correcto'
    }
}

function cambiarNumero() {
    document.getElementById('overlay-ya-registrado').classList.remove('active')
    forzarCambio = true
    // Ir directo a ingresar número (sin términos, ya los aceptó)
    document.getElementById('overlay-tel').classList.add('active')
    document.getElementById('tel-input').value = ''
    document.getElementById('tel-error').style.display = 'none'
    document.getElementById('tel-input').focus()
    // Sobreescribir confirmarNumero para forzar
    window._modoForzar = true
}

// Sobreescribir confirmarNumero cuando es cambio
const _confirmarOriginal = confirmarNumero
window.confirmarNumero = function() {
    _confirmarOriginal()
    if (window._modoForzar) {
        // El botón "Sí es correcto" debe usar forzar=true
        document.getElementById('btn-si-correcto').onclick = () => registrar(true)
        window._modoForzar = false
    }
}

function descargarVcard() {
    window.location.href = '?descargar=1'
    cerrarTodo()
}

function cerrarTodo() {
    ['overlay-terminos','overlay-tel','overlay-confirmar','overlay-ya-registrado'].forEach(id => {
        document.getElementById(id).classList.remove('active')
    })
    document.getElementById('chk-acepto').checked = false
    document.getElementById('btn-aceptar').disabled = true
    document.getElementById('tel-input').value = ''
    document.getElementById('tel-error').style.display = 'none'
    document.getElementById('confirmar-error').style.display = 'none'
    document.getElementById('confirmar-success').style.display = 'none'
    document.getElementById('btn-si-correcto').disabled = false
    document.getElementById('btn-si-correcto').textContent = '✅ Sí, es correcto'
    document.getElementById('btn-si-correcto').onclick = () => registrar(false)
    window._modoForzar = false
    forzarCambio = false
}

document.getElementById('tel-input').addEventListener('keypress', e => { if (e.key === 'Enter') confirmarNumero() })
document.getElementById('overlay-terminos').addEventListener('click', function(e) { if (e.target === this) cerrarTerminos() })
document.getElementById('overlay-tel').addEventListener('click', function(e) { if (e.target === this) cerrarTodo() })
document.getElementById('overlay-confirmar').addEventListener('click', function(e) { if (e.target === this) cerrarTodo() })
document.getElementById('overlay-ya-registrado').addEventListener('click', function(e) { if (e.target === this) cerrarTodo() })
</script>
</body>
</html>