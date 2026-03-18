<?php
// ============================================================
//  CHATBOT CONFIG — Unidad Educativa Pomasqui
//  Edita este archivo con la información real de la institución
// ============================================================

return [

    // ── INSTITUCIÓN ─────────────────────────────────────────
    'institucion' => [
        'nombre'    => 'Unidad Educativa Pomasqui',
        'direccion' => 'Av. Manuel Córdova Galarza y Calle Pomasqui, Parroquia Pomasqui, Quito',
        'telefono'  => '02-235-1072',
        'whatsapp'  => '+593 964 259 358',
        'email'     => '17h01988@gmail.com',
        'web'       => 'https://iepomasqui.com',
    ],

    // ── HORARIOS ────────────────────────────────────────────
    'horarios' => [
        'jornadas' => [
            'Matutina'   => '07:00 — 13:00',
            'Vespertina' => '13:00 — 18:00',
        ],
        'secretaria' => 'Lunes a Viernes de 07:00 a 16:00',
        'colecturia' => null, // No aplica — comentar o dejar null
    ],

    // ── ATENCIÓN A PADRES ───────────────────────────────────
    'atencion_padres' => [
        'horario'       => 'Lunes a Viernes de 08:00 a 12:00 y de 14:00 a 17:00',
        'nota'          => 'Se requiere cita previa para hablar con docentes.',
        'cita_contacto' => 'Solicitar cita al: 02-235-1072 o por este WhatsApp',
    ],

    // ── AUTORIDADES ─────────────────────────────────────────
    'autoridades' => [
        ['cargo' => 'Rector',               'nombre' => 'MSc. Jorge Imbaquingo'],
        ['cargo' => 'Vicerrectora',         'nombre' => 'MSc. Janeth Chipantasi'],
        ['cargo' => 'Inspector General',    'nombre' => 'Lic. Marco Loachamin'],
        ['cargo' => 'DECE Matutina',        'nombre' => 'Psic. Ana Julia Paredes'],
        ['cargo' => 'DECE Vespertina',      'nombre' => 'Psic. Eduardo Campaña'],
    ],

    // ── NIVELES EDUCATIVOS ──────────────────────────────────
    // IMPORTANTE: las claves deben ser únicas — no repetir 'BT'
    'niveles' => [
        'EGB Superior' => [
            'descripcion' => 'Educación General Básica Superior',
            'grados'      => ['8vo EGB', '9no EGB', '10mo EGB'],
        ],
        'BGU' => [
            'descripcion' => 'Bachillerato General Unificado',
            'grados'      => ['1ro BGU', '2do BGU', '3ro BGU'],
        ],
        'BT Soporte Informático' => [
            'descripcion' => 'Bachillerato Técnico — Soporte Informático',
            'grados'      => ['1ro BT'],
        ],
        'BT Informática' => [
            'descripcion' => 'Bachillerato Técnico — Informática',
            'grados'      => ['2do BT', '3ro BT'],
        ],
    ],

    // ── FIGURAS PROFESIONALES (Bachillerato Técnico) ────────
    'figuras_profesionales' => [
        [
            'figura'      => 'Soporte Informático',
            'descripcion' => 'Instalación, configuración y mantenimiento de equipos informáticos, redes y soporte técnico a usuarios.',
        ],
        [
            'figura'      => 'Informática',
            'descripcion' => 'Formación en programación, bases de datos y desarrollo de aplicaciones web y móviles.',
        ],
    ],

    // ── PREGUNTAS FRECUENTES ────────────────────────────────
    'faq' => [
        [
            'palabras_clave' => ['matrícula', 'matricula', 'inscripción', 'inscripcion'],
            'respuesta'      => "📋 *Proceso de Matrícula*\n\nPara matricularse en la U.E. Pomasqui necesita:\n• Copia de cédula del estudiante\n• Copia de cédula del representante\n• Partida de nacimiento\n• Libreta de calificaciones del año anterior\n• Foto tamaño carné\n\nFecha de matrículas: contactar secretaría al 📞 02-235-1072",
        ],
        [
            'palabras_clave' => ['uniforme', 'ropa', 'vestimenta'],
            'imagen'         => 'https://as.ecuasys.com/uniforme.jpg',  // ← sube tu imagen con este nombre
            'caption'        => '👕 Uniforme oficial — U.E. Pomasqui',
            'respuesta'      => "👕 *Uniforme Escolar*\n\nAquí puedes ver el uniforme oficial de la institución.\n\nPara consultas comuníquese con Secretaría:\n📞 02-235-1072\n🕐 Lunes a Viernes de 07:00 a 16:00",
        ],
        [
            'palabras_clave' => ['calificacion', 'calificación', 'nota', 'notas', 'rendimiento'],
            'respuesta'      => "📊 *Calificaciones*\n\nPara consultar calificaciones de su representado/a, por favor:\n• Acercarse a la institución en horario de atención\n• O comunicarse con el docente titular del curso\n\n🕐 Atención: Lunes a Viernes 08:00 a 12:00 y 14:00 a 17:00",
        ],
        [
            'palabras_clave' => ['certificado', 'documento', 'record'],
            'respuesta'      => "📄 *Certificados y Documentos*\n\nSolicitar en Secretaría:\n📞 02-235-1072\n🕐 Lunes a Viernes de 07:00 a 16:00\n\nTiempo de entrega: 3 a 5 días hábiles.",
        ],
    ],

    // ── PALABRAS CLAVE PARA DETECCIÓN DE INTENCIÓN ─────────
    'intenciones' => [
        'saludo'          => ['hola', 'buenos días', 'buenas tardes', 'buenas noches', 'buenas', 'hi', 'saludos', 'buen día'],
        'horario'         => ['horario', 'hora', 'cuando', 'cuándo', 'abierto', 'atienden', 'atención', 'atencion'],
        'autoridades'     => ['autoridad', 'autoridades', 'rector', 'director', 'directora', 'vicerrector', 'inspector', 'dece', 'secretaria', 'quien manda', 'quién manda'],
        'niveles'         => ['nivel', 'niveles', 'grado', 'grados', 'oferta', 'que ofrecen', 'qué ofrecen', 'carreras', 'carrera', 'estudiar'],
        'figuras'         => ['figura', 'figuras', 'técnico', 'tecnico', 'bachillerato técnico', 'especialidad', 'especialidades', 'bt'],
        'atencion_padres' => ['atencion padres', 'atención padres', 'hablar docente', 'cita', 'visita', 'reunion', 'reunión'],
        'faltas'          => ['falta', 'faltas', 'asistencia', 'ausencia', 'inasistencia', 'falto', 'faltó', 'ausente', 'cuantas faltas', 'cuántas faltas'],
        'ubicacion'       => ['donde', 'dónde', 'ubicacion', 'ubicación', 'dirección', 'direccion', 'como llegar', 'cómo llegar', 'mapa'],
        'contacto'        => ['contacto', 'teléfono', 'telefono', 'llamar', 'correo', 'email', 'whatsapp', 'comunicar'],
    ],

];