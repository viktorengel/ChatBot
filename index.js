import makeWASocket, { useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion } from '@whiskeysockets/baileys'
import qrcode from 'qrcode-terminal'
import mysql from 'mysql2/promise'
import pino from 'pino'
import http from 'http'

// ── POOL DE BASE DE DATOS ─────────────────────────────────────
const db = await mysql.createPool({
  host: process.env.DB_HOST, port: process.env.DB_PORT || 3306,
  database: 'ecuasysc_asistencias',
  user: process.env.DB_USER, password: process.env.DB_PASS,
  charset: 'utf8mb4',
})

const API_KEY = process.env.API_KEY || 'colegio_pomasqui_2026'

// ══════════════════════════════════════════════════════════════
// CARGAR CONFIGURACIÓN DESDE BD (tiempo real)
// ══════════════════════════════════════════════════════════════
async function getCfg() {
  // Institución
  const [instRows] = await db.query('SELECT clave, valor FROM bot_institucion')
  const inst = {}
  for (const r of instRows) inst[r.clave] = r.valor
  if (!inst.maps) inst.maps = 'https://maps.app.goo.gl/BRgbEKRodAk1Quf79'

  // Horarios
  const [horRows] = await db.query("SELECT tipo, nombre, horario FROM bot_horarios WHERE activo=1 ORDER BY orden")
  const jornadas = {}
  const especiales = []
  for (const h of horRows) {
    if (h.tipo === 'jornada') jornadas[h.nombre] = h.horario
    else especiales.push(h)
  }

  // Autoridades
  const [autRows] = await db.query('SELECT cargo, nombre FROM bot_autoridades WHERE activo=1 ORDER BY orden')

  // FAQ
  const [faqRows] = await db.query('SELECT palabras, respuesta, imagen, caption FROM bot_faq WHERE activo=1 ORDER BY orden')
  const faq = faqRows.map(r => ({
    palabras: r.palabras.split(',').map(p => p.trim().toLowerCase()),
    respuesta: r.respuesta,
    imagen:    r.imagen  || null,
    caption:   r.caption || null,
  }))

  return { inst, jornadas, especiales, autoridades: autRows, faq }
}

// Plantilla aleatoria desde BD
async function getPlantillaAleatoria(rep, est, curso, fecha) {
  const [rows] = await db.query('SELECT contenido FROM bot_plantillas WHERE activo=1 ORDER BY RAND() LIMIT 1')
  if (!rows.length) {
    return `🏫 *Unidad Educativa Pomasqui*\n\nEstimado/a *${rep}*, su representado/a *${est}* (${curso}) no asistió el *${fecha}*.\n\n_Mensaje automático_`
  }
  return rows[0].contenido
    .replace(/\{rep\}/g, rep)
    .replace(/\{est\}/g, est)
    .replace(/\{curso\}/g, curso)
    .replace(/\{fecha\}/g, fecha)
    .replace(/\\n/g, '\n')
}

// ══════════════════════════════════════════════════════════════
// TEXTOS DINÁMICOS
// ══════════════════════════════════════════════════════════════
async function txtMenu() {
  return '📋 *¿En qué puedo ayudarte?*\n\n' +
    '1️⃣  Horarios de jornadas\n' +
    '2️⃣  Atención a padres y representantes\n' +
    '3️⃣  Autoridades de la institución\n' +
    '4️⃣  Niveles educativos\n' +
    '5️⃣  Consultar faltas de mi representado/a\n' +
    '6️⃣  Ubicación\n' +
    '7️⃣  Contacto\n\n' +
    '_Escribe el número o hazme tu pregunta_ 💬'
}

async function txtHorarios() {
  const cfg = await getCfg()
  let m = '🕐 *HORARIOS DE ATENCIÓN*\n\n'
  m += '━━━━━━━━━━━━━━━━━━\n\n'
  m += '🎒 *Jornadas Académicas*\n'
  for (const [j, h] of Object.entries(cfg.jornadas)) {
    const ico = j === 'Matutina' ? '🌅' : j === 'Vespertina' ? '🌇' : '🌙'
    m += `• ${ico} *${j}:* ${h}\n`
  }
  m += '\n━━━━━━━━━━━━━━━━━━\n\n'
  m += '🏢 *Servicios Institucionales*\n'
  for (const e of cfg.especiales) {
    if (e.nombre.toLowerCase().includes('secretar')) {
      m += `• 📋 *${e.nombre}:*\n  ${e.horario}\n`
    } else if (e.nombre.toLowerCase().includes('padre') || e.nombre.toLowerCase().includes('atencion')) {
      m += `• 👨‍👩‍👧 *${e.nombre}:*\n  ${e.horario}\n`
    }
  }
  m += '\n━━━━━━━━━━━━━━━━━━\n\n'
  m += `_↩️ Escribe *0* para volver al menú_\n_${cfg.inst.nombre} — Chatbot informativo_`
  return m
}

async function txtAtencion() {
  const cfg = await getCfg()
  let m = '👨‍👩‍👧 *Atención a Padres y Representantes*\n\n'
  m += 'ℹ️ Para hacer consultas a los docentes debe acercarse de acuerdo al horario de atención.\n\n'
  m += '🕐 *Horario:* Lunes a Viernes\n'
  for (const [j, h] of Object.entries(cfg.jornadas)) {
    const ico = j === 'Matutina' ? '🌅' : j === 'Vespertina' ? '🌇' : '🌙'
    m += `• ${ico} *${j}:* ${h}\n`
  }
  m += `\n_↩️ Escribe *0* para volver al menú_\n_${cfg.inst.nombre} — Chatbot informativo_`
  return m
}

async function txtAutoridades() {
  const cfg = await getCfg()
  let m = '🏛️ *Autoridades de la Institución*\n\n'
  for (const a of cfg.autoridades) m += `- *${a.cargo}*\n  ${a.nombre}\n`
  m += `\n_↩️ Escribe *0* para volver al menú_\n_${cfg.inst.nombre} — Chatbot informativo_`
  return m
}

async function txtNiveles() {
  const cfg = await getCfg()
  return `🎓 *Oferta Educativa*\n\n` +
    `*Jornada Vespertina*\n` +
    `📌 *EGB Superior* — Educación General Básica Superior\n   8vo EGB, 9no EGB, 10mo EGB\n\n` +
    `*Jornada Matutina*\n` +
    `📌 *BGU* — Bachillerato General Unificado\n   1ro BGU, 2do BGU, 3ro BGU\n\n` +
    `📌 *Bachillerato Técnico* - Bachillerato Técnico\n   1ro BT — Soporte Informático\n   2do BT, 3ro BT — Informática\n\n` +
    `_↩️ Escribe *0* para volver al menú_\n_${cfg.inst.nombre} — Chatbot informativo_`
}

async function txtFiguras() {
  const cfg = await getCfg()
  return `🔧 *Figuras Profesionales — Bachillerato Técnico*\n\n` +
    `💻 *Soporte Informático*\nInstalación, configuración y mantenimiento de equipos informáticos, redes y soporte técnico a usuarios.\n\n` +
    `🖥️ *Informática*\nFormación en programación, bases de datos y desarrollo de aplicaciones web y móviles.\n\n` +
    `_↩️ Escribe *0* para volver al menú_\n_${cfg.inst.nombre} — Chatbot informativo_`
}

async function txtUbicacion() {
  const cfg = await getCfg()
  const maps = cfg.inst.maps || 'https://maps.app.goo.gl/BRgbEKRodAk1Quf79'
  return `📍 *Ubicación*\n\n${cfg.inst.nombre}\n${cfg.inst.direccion}\n\n` +
    `📌 Búscanos en Google Maps como: Colegio Pomasqui\n${maps}\n_(Toca el enlace para abrir en Google Maps)_\n\n` +
    `_↩️ Escribe *0* para volver al menú_\n_${cfg.inst.nombre}_`
}

async function txtContacto() {
  const cfg = await getCfg()
  return `📞 *Contacto*\n\n☎️  Teléfono: ${cfg.inst.telefono}\n📱 WhatsApp: ${cfg.inst.whatsapp}\n✉️  Correo: ${cfg.inst.email}\n🌐 Web: ${cfg.inst.web}\n\n_↩️ Escribe *0* para volver al menú_`
}

async function buscarFaq(txt) {
  const cfg = await getCfg()
  for (const item of cfg.faq)
    for (const p of item.palabras)
      if (txt.includes(p)) return item
  return null
}

async function detectarIntencion(txt) {
  const intenciones = {
    saludo:          ['hola','buenos días','buenas tardes','buenas noches','buenas','hi','saludos','buen día'],
    horario:         ['horario','hora','cuando','cuándo','abierto','atienden','atención','atencion','jornada'],
    autoridades:     ['autoridad','autoridades','rector','director','directora','vicerrector','inspector','dece','secretaria','quien manda','quién manda'],
    niveles:         ['nivel','niveles','grado','grados','oferta','que ofrecen','qué ofrecen','carreras','carrera','estudiar','bachillerato','egb'],
    figuras:         ['figura','figuras','técnico','tecnico','bachillerato técnico','especialidad','especialidades','bt','soporte','informatica','informática'],
    atencion_padres: ['atencion padres','atención padres','hablar docente','cita','visita','reunion','reunión'],
    faltas:          ['falta','faltas','asistencia','ausencia','inasistencia','falto','faltó','ausente','cuantas faltas','cuántas faltas'],
    ubicacion:       ['donde','dónde','ubicacion','ubicación','dirección','direccion','como llegar','cómo llegar','mapa'],
    contacto:        ['contacto','teléfono','telefono','llamar','correo','email','whatsapp','comunicar'],
  }
  for (const [intencion, palabras] of Object.entries(intenciones))
    for (const p of palabras) if (txt.includes(p)) return intencion
  return null
}

// ══════════════════════════════════════════════════════════════
// SESIONES
// ══════════════════════════════════════════════════════════════
async function getSession(phone) {
  const [rows] = await db.query('SELECT step, data FROM bot_sessions WHERE phone=?', [phone])
  if (!rows.length) return { step: 'start', data: {} }
  return { step: rows[0].step, data: JSON.parse(rows[0].data || '{}') }
}
async function setSession(phone, step, data = {}) {
  await db.query(
    'INSERT INTO bot_sessions (phone,step,data) VALUES (?,?,?) ON DUPLICATE KEY UPDATE step=VALUES(step),data=VALUES(data)',
    [phone, step, JSON.stringify(data)]
  )
}
async function resetSession(phone) { await setSession(phone, 'start', {}) }
async function logMsg(phone, dir, body) {
  try { await db.query('INSERT INTO bot_message_log (phone,direction,body) VALUES (?,?,?)', [phone, dir, body.slice(0,1000)]) } catch {}
}

// ══════════════════════════════════════════════════════════════
// CONSULTA DE FALTAS
// ══════════════════════════════════════════════════════════════
async function consultarFaltas(phone, cedula) {
  cedula = cedula.replace(/\D/g, '')
  if (cedula.length < 8 || cedula.length > 13)
    return '⚠️ Cédula no válida. Ingresa solo los números.\n\n_(Escribe *cancelar* para volver al menú)_'

  const [estRows] = await db.query(
    'SELECT e.id, e.nombre, c.nombre as curso FROM estudiantes e LEFT JOIN cursos c ON e.curso_id=c.id WHERE e.cedula=? LIMIT 1',
    [cedula]
  )
  if (!estRows.length) {
    await resetSession(phone)
    const cfg = await getCfg()
    return `❌ No encontramos ningún estudiante con la cédula *${cedula}*.\n\nVerifica el número o comunícate con Secretaría.\n\n_${cfg.inst.nombre} — Chatbot informativo_`
  }
  const est = estRows[0]
  let telBd = phone.replace(/\D/g, '')
  if (telBd.startsWith('593')) telBd = '0' + telBd.slice(3)

  const [repRows] = await db.query(
    'SELECT r.id FROM representantes r JOIN estudiante_representante er ON er.representante_id=r.id WHERE er.estudiante_id=? AND (r.telefono LIKE ? OR r.telefono LIKE ?) LIMIT 1',
    [est.id, `%${telBd}%`, `%${phone}%`]
  )
  if (!repRows.length) {
    await resetSession(phone)
    const cfg = await getCfg()
    return `🔒 Tu número no está registrado como representante de *${est.nombre}*.\n\n_${cfg.inst.nombre} — Chatbot informativo_`
  }

  const mesActual  = new Date().toISOString().slice(0, 7)
  const anioActual = new Date().getFullYear()
  const [[{ total: faltasMes }]]  = await db.query(`SELECT COUNT(*) as total FROM faltas WHERE estudiante_id=? AND DATE_FORMAT(fecha,'%Y-%m')=?`, [est.id, mesActual])
  const [[{ total: faltasAnio }]] = await db.query(`SELECT COUNT(*) as total FROM faltas WHERE estudiante_id=? AND YEAR(fecha)=?`, [est.id, anioActual])
  const [ultimas] = await db.query('SELECT fecha FROM faltas WHERE estudiante_id=? ORDER BY fecha DESC LIMIT 5', [est.id])

  const dias = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb']
  const cfg  = await getCfg()
  let msg = `📊 *Reporte de Asistencia*\n━━━━━━━━━━━━━━━━━━━━\n`
  msg += `👤 *${est.nombre}*\n📚 Curso: *${est.curso || 'Sin curso'}*\n\n`
  msg += `📅 Faltas este mes: *${faltasMes}*\n📆 Faltas este año: *${faltasAnio}*\n`
  if (ultimas.length) {
    msg += `\n🗓️ *Últimas inasistencias:*\n`
    for (const f of ultimas) {
      const d = new Date(f.fecha)
      msg += `• ${dias[d.getDay()]} ${d.toLocaleDateString('es-EC')}\n`
    }
  } else { msg += `\n✅ Sin inasistencias recientes.\n` }
  msg += `\n_↩️ Escribe *0* para volver al menú_\n_${cfg.inst.nombre} — Chatbot informativo_`
  await resetSession(phone)
  return msg
}

// ══════════════════════════════════════════════════════════════
// SERVIDOR HTTP — para envíos desde PHP (procesar_cola.php)
// ══════════════════════════════════════════════════════════════
let sockGlobal = null

const httpServer = http.createServer((req, res) => {
  res.setHeader('Content-Type', 'application/json')
  if (req.method !== 'POST' || req.url !== '/send') {
    res.writeHead(404); res.end(JSON.stringify({ error: 'Not found' })); return
  }
  const apikey = req.headers['apikey'] || req.headers['authorization']
  if (apikey !== API_KEY) {
    res.writeHead(401); res.end(JSON.stringify({ error: 'Unauthorized' })); return
  }
  let body = ''
  req.on('data', chunk => { body += chunk.toString() })
  req.on('end', () => {
    let payload
    try { payload = JSON.parse(body) } catch {
      res.writeHead(400); res.end(JSON.stringify({ error: 'Invalid JSON' })); return
    }
    const { number, text } = payload
    if (!number || !text) {
      res.writeHead(400); res.end(JSON.stringify({ error: 'number y text requeridos' })); return
    }
    if (!sockGlobal) {
      res.writeHead(503); res.end(JSON.stringify({ error: 'Bot no conectado' })); return
    }
    sockGlobal.sendMessage(number, { text })
      .then(() => {
        console.log(`[API] Enviado a ${number}: ${text.slice(0,60)}`)
        res.writeHead(200); res.end(JSON.stringify({ status: 'sent', number }))
      })
      .catch(e => {
        console.error('[API] Error:', e.message)
        res.writeHead(500); res.end(JSON.stringify({ error: e.message }))
      })
  })
})

httpServer.listen(3001, '0.0.0.0', () => {
  console.log('🌐 API HTTP escuchando en puerto 3001')
})

// ══════════════════════════════════════════════════════════════
// LÓGICA PRINCIPAL DE MENSAJES
// ══════════════════════════════════════════════════════════════
async function handleMessage(sock, phone, jid, text) {
  const txt     = text.trim().toLowerCase()
  const session = await getSession(phone)
  await logMsg(phone, 'in', text)

  const send = async (msg) => {
    await logMsg(phone, 'out', msg)
    try {
      // Mostrar "escribiendo..." antes de enviar
      await sock.presenceSubscribe(jid)
      await sock.sendPresenceUpdate('composing', jid)
      const delay = Math.min(Math.max(msg.length * 15, 1000), 4000)
      await new Promise(r => setTimeout(r, delay))
      await sock.sendPresenceUpdate('paused', jid)
      await sock.sendMessage(jid, { text: msg })
    } catch(e) { console.error('[send]', e.message) }
  }

  // Cancelar en cualquier momento
  if (['cancelar','salir','exit','menu','menú','0'].includes(txt)) {
    await resetSession(phone)
    await send(await txtMenu())
    return
  }

  // Flujo activo
  if (session.step === 'esperando_metodo') {
    if (txt === '1') {
      await setSession(phone, 'esperando_cedula')
      await send('🪪 Escribe la *cédula* del estudiante:\n\n_(Escribe *0* para volver al menú)_')
    } else if (txt === '2') {
      await setSession(phone, 'esperando_nombre')
      await send('🔤 Escribe el *apellido o nombre* del estudiante:\n\n_(Escribe *0* para volver al menú)_')
    } else {
      await send('Por favor escribe *1* para cédula o *2* para nombre.\n\n_(Escribe *0* para volver al menú)_')
    }
    return
  }

  if (session.step === 'esperando_cedula') {
    await send(await consultarFaltas(phone, text))
    return
  }

  if (session.step === 'esperando_nombre') {
    await send(await consultarFaltasPorNombre(phone, text))
    return
  }

  if (session.step === 'eligiendo_estudiante') {
    const lista = session.data.lista || []
    const idx   = parseInt(txt) - 1
    if (idx >= 0 && idx < lista.length) {
      await resetSession(phone)
      await send(await generarReporte(lista[idx]))
    } else {
      await send(`Por favor escribe un número entre 1 y ${lista.length}.`)
    }
    return
  }

  // Opción numérica del menú
  if (/^[1-7]$/.test(txt)) {
    switch (txt) {
      case '1': await send(await txtHorarios()); break
      case '2': await send(await txtAtencion()); break
      case '3': await send(await txtAutoridades()); break
      case '4': await send(await txtNiveles()); break
      case '5':
        await setSession(phone, 'esperando_metodo')
        await send('🔍 *Consulta de Asistencia*\n\n¿Cómo deseas buscar al estudiante?\n\n1️⃣ Por *cédula*\n2️⃣ Por *nombre*\n\n_(Escribe *0* para volver al menú)_')
        break
      case '6': await send(await txtUbicacion()); break
      case '7': await send(await txtContacto()); break
    }
    return
  }

  // FAQ desde BD
  const faqItem = await buscarFaq(txt)
  if (faqItem) {
    // Si tiene imagen, enviarla primero
    if (faqItem.imagen && sockGlobal) {
      try {
        await sockGlobal.sendMessage(jid, {
          image: { url: faqItem.imagen },
          caption: faqItem.caption || ''
        })
        await logMsg(phone, 'out', '[imagen] ' + faqItem.imagen)
      } catch(e) {
        console.error('[imagen error]', e.message)
      }
    }
    await send(faqItem.respuesta)
    return
  }

  // Intención por palabras clave
  const intencion = await detectarIntencion(txt)
  const cfg       = await getCfg()
  switch (intencion) {
    case 'saludo':
      await send(`👋 ¡Bienvenido/a al chatbot de la *${cfg.inst.nombre}*!\n\n${await txtMenu()}`)
      return
    case 'horario':         await send(await txtHorarios()); return
    case 'autoridades':     await send(await txtAutoridades()); return
    case 'niveles':         await send(await txtNiveles()); return
    case 'figuras':         await send(await txtFiguras()); return
    case 'atencion_padres': await send(await txtAtencion()); return
    case 'faltas':
      await setSession(phone, 'esperando_metodo')
      await send('🔍 *Consulta de Asistencia*\n\n¿Cómo deseas buscar al estudiante?\n\n1️⃣ Por *cédula*\n2️⃣ Por *nombre*\n\n_(Escribe *0* para volver al menú)_')
      return
    case 'ubicacion': await send(await txtUbicacion()); return
    case 'contacto':  await send(await txtContacto()); return
  }

  await send(`🤔 No entendí tu consulta.\n\n${await txtMenu()}`)
}

// ══════════════════════════════════════════════════════════════
// BAILEYS
// ══════════════════════════════════════════════════════════════
async function startBot() {
  const { state, saveCreds } = await useMultiFileAuthState('./auth')
  const { version } = await fetchLatestBaileysVersion()
  const sock = makeWASocket({
    version, auth: state,
    logger: pino({ level: 'silent' }),
    printQRInTerminal: false,
    getMessage: async () => ({ conversation: '' }),
  })
  sock.ev.on('creds.update', saveCreds)
  sock.ev.on('connection.update', ({ connection, lastDisconnect, qr }) => {
    if (qr) { console.log('\n📱 Escanea QR:\n'); qrcode.generate(qr, { small: true }) }
    if (connection === 'close') {
      sockGlobal = null
      const reconectar = lastDisconnect?.error?.output?.statusCode !== DisconnectReason.loggedOut
      if (reconectar) setTimeout(startBot, 5000)
    }
    if (connection === 'open') { sockGlobal = sock; console.log('✅ Bot conectado a WhatsApp') }
  })
  sock.ev.on('call', async (calls) => {
    for (const call of calls) {
      if (call.status === 'offer') {
        await sock.rejectCall(call.id, call.from)
        await sock.sendMessage(call.from, { text: '📵 Este número es exclusivo para mensajes de la U.E. Pomasqui. Para llamadas comuníquese al 02-235-1072.' })
      }
    }
  })
  sock.ev.on('messages.upsert', async ({ messages, type }) => {
    if (type !== 'notify') return
    for (const msg of messages) {
      if (msg.key.fromMe) continue
      const remoteJid = msg.key.remoteJid
      if (remoteJid.endsWith('@g.us')) continue
      const text = msg.message?.conversation || msg.message?.extendedTextMessage?.text || ''
      if (!text) continue
      const phone = remoteJid.replace('@s.whatsapp.net', '').replace('@lid', '')

      // Guardar mapeo LID -> teléfono real si está disponible
      try {
        const participant = msg.key.participant || msg.pushName || ''
        const notifyName  = msg.pushName || ''
        // Intentar obtener el JID real del mensaje
        const realJid = msg.key?.remoteJid || ''
        if (realJid.includes('@lid')) {
          // Es un LID — buscar en contacts del socket
          const contact = sock.store?.contacts?.[realJid]
          if (contact?.lid) {
            await db.query(
              'INSERT INTO bot_lid_map (lid, phone) VALUES (?,?) ON DUPLICATE KEY UPDATE phone=VALUES(phone)',
              [phone, contact.lid.replace('@s.whatsapp.net','')]
            )
          }
        }
      } catch(e) {}

      console.log(`[msg] ${phone}: ${text}`)
      await handleMessage(sock, phone, remoteJid, text)
    }
  })
}

startBot()