(() => {
    'use strict';   // modo estricto:  evita errores silenciosos.

    // Atajos para buscar elementos en el DOM. 
    const $  = (selector, raiz = document) => raiz.querySelector(selector);             
    const $$ = (selector, raiz = document) => Array.from(raiz.querySelectorAll(selector)); 

    //  Error propio para respuestas de la API.
    class ErrorApi extends Error {
        constructor(estado, mensaje, detalles = {}) {
            super(mensaje);
            this.estado = estado;       
            this.detalles = detalles;   
        }
    }
    // Avisos de éxito / error. 
    const cajaAviso = $('#aviso');
    let temporizadorAviso;

    function mostrarAviso(texto, tipo = 'ok') {
        cajaAviso.textContent = texto;                   
        cajaAviso.className = `aviso aviso-${tipo}`;     
        cajaAviso.hidden = false;
        clearTimeout(temporizadorAviso);
        temporizadorAviso = setTimeout(() => { cajaAviso.hidden = true; }, 6000);
    }
    // Llamadas a la API 
    async function llamarApi(ruta, { metodo = 'GET', cuerpo } = {}) {
        let respuesta;
        try {
            respuesta = await fetch(ruta, {
                method: metodo,
                headers: {
                    Accept: 'application/json',
                    ...(cuerpo ? { 'Content-Type': 'application/json' } : {}),
                },
                body: cuerpo ? JSON.stringify(cuerpo) : undefined,
            });
        } catch {
            throw new ErrorApi(0, 'No se pudo conectar con el servidor. Revisa tu conexión.');
        }

        let datos = null;
        try { datos = await respuesta.json(); } catch { /* la respuesta no era JSON */ }

        if (!respuesta.ok) {   
            const porDefecto = respuesta.status >= 500
                ? 'Error interno del servidor. Inténtalo de nuevo más tarde.'
                : 'La petición no es válida.';
            throw new ErrorApi(
                respuesta.status,
                datos?.error?.message || porDefecto,   
                datos?.error?.details || {},
            );
        }
        return datos;
    }

    // Formato de datos. 
    const formatearFecha = (iso) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '');
    const formatearFechaHora = (iso) => (iso ? `${formatearFecha(iso)} ${iso.slice(11, 16)}` : '');
    const formatoEuro = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR', useGrouping: 'always' });
    const formatearImporte = (valor) => formatoEuro.format(Number(valor));
    const capitalizar = (texto) => texto.charAt(0).toUpperCase() + texto.slice(1);

    // Crear elementos HTML de forma segura. 
    function crearElemento(etiqueta, atributos = {}, ...hijos) {
        const nodo = document.createElement(etiqueta);
        for (const [nombre, valor] of Object.entries(atributos)) {
            nodo.setAttribute(nombre, valor);
        }
        nodo.append(...hijos);   
        return nodo;
    }

    const crearEtiqueta = (estado) =>
        crearElemento('span', { class: `etiqueta etiqueta-${estado}` }, capitalizar(estado));

    // Cambiar estado de una reserva.
    async function cambiarEstado(id, estado, boton) {
        if (estado === 'cancelada' && !window.confirm('¿Seguro que quieres cancelar esta reserva? No se puede deshacer.')) {
            return null;
        }

        boton.disabled = true;   
        try {
            const { data } = await llamarApi(`/api/reservas/${encodeURIComponent(id)}/estado`, {
                metodo: 'PATCH',
                cuerpo: { estado },
            });
            mostrarAviso(estado === 'cancelada' ? 'Reserva cancelada.' : 'Reserva confirmada.');
            return data;
        } catch (error) {
            mostrarAviso(error.message, 'error');   
            return null;
        } finally {
            boton.disabled = false;  
        }
    }

    // Página de listado: filtros, tabla y diálogo de nueva reserva.
    function iniciarListado() {
        const formulario = $('#filtros');
        const tabla = $('#tabla');
        const cuerpo = $('#tabla-cuerpo');
        const total = $('#total');
        const dialogo = $('#dialogo-nueva');
        const formNueva = $('#form-nueva');

        const filaMensaje = (texto) =>
            crearElemento('tr', {}, crearElemento('td', { colspan: '7', class: 'suave' }, texto));

        function crearFila(r) {
            const acciones = crearElemento('td', { class: 'acciones' },
                crearElemento('a', { href: `/reservas/${r.id}` }, 'Ver'));

            if (r.estado === 'pendiente') {
                acciones.append(crearElemento('button',
                    { type: 'button', class: 'boton boton-pequeno', 'data-accion': 'confirmada', 'data-id': r.id }, 'Confirmar'));
            }
            if (r.estado !== 'cancelada') {
                acciones.append(crearElemento('button',
                    { type: 'button', class: 'boton boton-pequeno boton-peligro', 'data-accion': 'cancelada', 'data-id': r.id }, 'Cancelar'));
            }

            return crearElemento('tr', {},
                crearElemento('td', {}, r.nombre_huesped),
                crearElemento('td', {}, r.nombre_alojamiento),
                crearElemento('td', {}, formatearFecha(r.fecha_entrada)),
                crearElemento('td', {}, formatearFecha(r.fecha_salida)),
                crearElemento('td', { class: 'num' }, formatearImporte(r.importe)),
                crearElemento('td', {}, crearEtiqueta(r.estado)),
                acciones,
            );
        }

        // Lee los filtros del formulario y devuelve un objeto URLSearchParams con los que tengan valor.
        function leerFiltros() {
            const parametros = new URLSearchParams();
            for (const [nombre, valor] of new FormData(formulario)) {
                if (String(valor).trim() !== '') parametros.set(nombre, String(valor).trim());
            }
            return parametros;
        }

        // Contador de peticiones para ignorar respuestas antiguas que lleguen tarde.
        let numeroPeticion = 0;

        // Carga el listado de reservas según los filtros, repintando la tabla y el total.
        async function cargarListado() {
            const parametros = leerFiltros();

            // Comprobación rápida en cliente.
            if (parametros.get('desde') && parametros.get('hasta') && parametros.get('hasta') < parametros.get('desde')) {
                mostrarAviso('La fecha "hasta" no puede ser anterior a "desde".', 'error');
                return;
            }

            const consulta = parametros.toString();
            // Actualiza la URL del navegador sin recargar.
            history.replaceState(null, '', consulta ? `?${consulta}` : location.pathname);

            const miPeticion = ++numeroPeticion;
            tabla.setAttribute('aria-busy', 'true');                 
            cuerpo.replaceChildren(filaMensaje('Cargando…'));        

            try {
                const { data, meta } = await llamarApi(`/api/reservas${consulta ? `?${consulta}` : ''}`);
                if (miPeticion !== numeroPeticion) return;          

                cuerpo.replaceChildren(...(data.length
                    ? data.map(crearFila)
                    : [filaMensaje('No hay reservas que coincidan con los filtros.')]));
                total.textContent = `${meta.total} reserva${meta.total === 1 ? '' : 's'}`;
            } catch (error) {
                if (miPeticion !== numeroPeticion) return;
                cuerpo.replaceChildren(filaMensaje('No se pudo cargar el listado.'));
                mostrarAviso(Object.values(error.detalles).join(' ') || error.message, 'error');
            } finally {
                if (miPeticion === numeroPeticion) tabla.removeAttribute('aria-busy');
            }
        }

        //  Eventos de los filtros 
        formulario.addEventListener('submit', (evento) => {
            evento.preventDefault();   
            cargarListado();
        });

        // Eventos de cambio en selects y fechas: recarga inmediata.
        $$('select, input[type="date"]', formulario).forEach((campo) => campo.addEventListener('change', cargarListado));
        let temporizadorBusqueda;
        $('input[name="q"]', formulario).addEventListener('input', () => {
            clearTimeout(temporizadorBusqueda);
            temporizadorBusqueda = setTimeout(cargarListado, 300);
        });

        $('#limpiar').addEventListener('click', (evento) => {
            evento.preventDefault();
            $$('input, select', formulario).forEach((campo) => { campo.value = ''; });
            cargarListado();
        });

        // Eventos de los botones de la tabla: confirmar / cancelar.
        cuerpo.addEventListener('click', async (evento) => {
            const boton = evento.target.closest('button[data-accion]');
            if (!boton) return;
            if (await cambiarEstado(boton.dataset.id, boton.dataset.accion, boton)) {
                cargarListado();
            }
        });

        //  Crear reserva 
        const limpiarErrores = () => $$('[data-error-de]', formNueva).forEach((span) => { span.textContent = ''; });

        function mostrarErrores(errores) {
            for (const [campo, mensaje] of Object.entries(errores)) {
                const span = $(`[data-error-de="${CSS.escape(campo)}"]`, formNueva);
                if (span) span.textContent = mensaje;
            }
        }

        // Validación en cliente: devuelve un objeto con los errores encontrados, o vacío si todo está bien.
        function validarEnCliente(d) {
            const errores = {};
            const hoy = new Date().toLocaleDateString('sv-SE');   

            if (!d.nombre_huesped.trim()) errores.nombre_huesped = 'Este campo es obligatorio.';
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email_huesped)) errores.email_huesped = 'El email no es válido.';
            if (!d.nombre_alojamiento.trim()) errores.nombre_alojamiento = 'Este campo es obligatorio.';

            if (!d.fecha_entrada) errores.fecha_entrada = 'Este campo es obligatorio.';
            else if (d.fecha_entrada < hoy) errores.fecha_entrada = 'La entrada no puede estar en el pasado.';

            if (!d.fecha_salida) errores.fecha_salida = 'Este campo es obligatorio.';
            else if (d.fecha_entrada && d.fecha_salida <= d.fecha_entrada) errores.fecha_salida = 'La salida debe ser posterior a la entrada.';

            if (d.importe === '' || Number.isNaN(Number(d.importe)) || Number(d.importe) < 0) errores.importe = 'Introduce un importe válido.';
            return errores;
        }

        $('#btn-nueva').addEventListener('click', () => { limpiarErrores(); dialogo.showModal(); });
        $('#btn-cerrar-dialogo').addEventListener('click', () => dialogo.close());

        formNueva.addEventListener('submit', async (evento) => {
            evento.preventDefault();
            limpiarErrores();

            const datos = Object.fromEntries(new FormData(formNueva));   
            const errores = validarEnCliente(datos);
            if (Object.keys(errores).length) {
                mostrarErrores(errores);
                return;   
            }

            const botonEnviar = $('button[type="submit"]', formNueva);
            botonEnviar.disabled = true;
            botonEnviar.textContent = 'Creando…';   

            try {
                await llamarApi('/api/reservas', { metodo: 'POST', cuerpo: datos });
                dialogo.close();
                formNueva.reset();
                mostrarAviso('Reserva creada correctamente.');
                cargarListado();
            } catch (error) {
                if (error.estado === 400 && Object.keys(error.detalles).length) {
                    mostrarErrores(error.detalles);
                } else {
                    mostrarAviso(error.message, 'error');
                }
            } finally {
                botonEnviar.disabled = false;
                botonEnviar.textContent = 'Crear reserva';
            }
        });
    }

    // Página de detalle: historial y botones de acción.
    function iniciarDetalle() {
        const etiqueta = $('#estado-etiqueta');
        const timeline = $('#timeline');
        const botonConfirmar = $('[data-accion="confirmada"]');
        const botonCancelar = $('[data-accion="cancelada"]');

        // Repinta la página con los datos de la reserva.
        function pintar(reserva) {
            etiqueta.replaceChildren(crearEtiqueta(reserva.estado));

            timeline.replaceChildren(...reserva.eventos.map((evento) =>
                crearElemento('li', {},
                    crearElemento('time', {}, formatearFechaHora(evento.fecha_creacion)),
                    crearElemento('strong', {}, evento.tipo),
                    crearElemento('span', {}, evento.descripcion),
                )));

            botonConfirmar.hidden = reserva.estado !== 'pendiente';
            botonCancelar.hidden = reserva.estado === 'cancelada';
        }

        [botonConfirmar, botonCancelar].forEach((boton) => {
            boton.addEventListener('click', async () => {
                const reserva = await cambiarEstado(boton.dataset.id, boton.dataset.accion, boton);
                if (reserva) pintar(reserva);   
            });
        });
    }

    // Inicialización según la página..
    if ($('#tabla')) iniciarListado();
    else if ($('#timeline')) iniciarDetalle();
})();