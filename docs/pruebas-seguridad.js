// Pruebas manuales de seguridad de la API REST de reservas 
(async () => {
    const J = { 'Content-Type': 'application/json' };
    const base = { nombre_huesped: 'A', email_huesped: 'a@b.com', nombre_alojamiento: 'X', fecha_entrada: '2027-06-01', fecha_salida: '2027-06-03', importe: 10 };
    const post = (cuerpo) => ({ method: 'POST', headers: J, body: JSON.stringify(cuerpo) });
    const patch = (estado) => ({ method: 'PATCH', headers: J, body: JSON.stringify({ estado }) });

    const casos = [
        ['A1', 'Inyección SQL en búsqueda (texto literal)', '/api/reservas?q=' + encodeURIComponent("' OR '1'='1"), {}, 200, (d) => d.meta.total === 0],
        ['A2', 'Inyección SQL en filtro de estado', '/api/reservas?estado=' + encodeURIComponent("pendiente' OR '1'='1"), {}, 400],
        ['A3', 'Comodín % escapado en búsqueda', '/api/reservas?q=%25', {}, 200, (d) => d.meta.total === 0],
        ['A4', 'Inyección SQL en el id de la ruta', '/api/reservas/1%20OR%201=1', {}, 404],
        ['C', 'Asignación masiva (estado e id enviados por el cliente)', '/api/reservas', post({ ...base, estado: 'confirmada', id: 1 }), 201, (d) => d.data.estado === 'pendiente' && d.data.id !== 1],
        ['D1', 'Importe como array', '/api/reservas', post({ ...base, importe: [1] }), 400],
        ['D2', 'Importe no numérico', '/api/reservas', post({ ...base, importe: 'abc' }), 400],
        ['D3', 'Importe en notación científica', '/api/reservas', post({ ...base, importe: '1e5' }), 400],
        ['D4', 'Importe booleano', '/api/reservas', post({ ...base, importe: true }), 400],
        ['D5', 'Nombre como objeto', '/api/reservas', post({ ...base, nombre_huesped: { a: 1 } }), 400],
        ['D6', 'Email de más de 150 caracteres', '/api/reservas', post({ ...base, email_huesped: 'a'.repeat(200) + '@x.com' }), 400],
        ['D7', 'Fecha inexistente (31 de febrero)', '/api/reservas', post({ ...base, fecha_entrada: '2027-02-31' }), 400],
        ['E1', 'POST sin Content-Type JSON (protección CSRF)', '/api/reservas', { method: 'POST', body: 'nombre_huesped=x' }, 415],
        ['E2', 'Método no permitido (DELETE)', '/api/reservas/1', { method: 'DELETE' }, 405],
        ['E3', 'Método no permitido en /estado (POST)', '/api/reservas/1/estado', { method: 'POST', headers: J, body: '{}' }, 405],
        ['E4', 'Ruta inexistente', '/api/nada', {}, 404],
        ['F1', 'Transición no permitida (cancelada a confirmada)', '/api/reservas/3/estado', patch('confirmada'), 409],
        ['F2', 'Estado inventado', '/api/reservas/3/estado', patch('borrada'), 400],
        ['F3', 'Reserva inexistente', '/api/reservas/9999/estado', patch('cancelada'), 404],
    ];

    const filas = [];
    for (const [id, descripcion, url, opciones, esperado, comprobar] of casos) {
        let obtenido = 'sin respuesta';
        let extra = true;
        try {
            const respuesta = await fetch(url, opciones);
            obtenido = respuesta.status;
            if (comprobar) extra = comprobar(await respuesta.json());
        } catch { extra = false; }
        filas.push({ prueba: id, descripcion, esperado, obtenido, resultado: obtenido === esperado && extra ? 'OK' : 'REVISAR' });
    }


    const cab = (await fetch('/')).headers;
    const cabecerasOk = !!cab.get('content-security-policy') && cab.get('x-content-type-options') === 'nosniff' && cab.get('x-frame-options') === 'DENY';
    filas.push({ prueba: 'G', descripcion: 'Cabeceras CSP, nosniff y X-Frame-Options', esperado: 'presentes', obtenido: cabecerasOk ? 'presentes' : 'faltan', resultado: cabecerasOk ? 'OK' : 'REVISAR' });

    console.table(filas);
})();