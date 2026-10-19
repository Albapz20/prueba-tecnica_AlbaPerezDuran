# Gestión de reservas de alojamiento

Mini-aplicación de gestión de reservas hecha sin framework: API en PHP nativo, vistas renderizadas en servidor con Twig e interfaz con JavaScript vanilla que consume la API sin recargar la página.

- Deploy: https://reservas-albaperezduran.ifree.page
- Stack: PHP 8.1+ · MySQL/MariaDB (PDO) · Twig 3 · JavaScript vanilla · PHPUnit 10

## Qué hace

- Lista las reservas con filtros combinables: estado, rango de fechas y nombre del huésped.
- Muestra el detalle de una reserva con su historial de eventos (timeline).
- Permite crear reservas (validación en cliente y en servidor) y confirmarlas o cancelarlas con confirmación previa, sin recargar la página.
- Cada vez que se crea o cambia de estado una reserva se registra un evento en el historial.

## Puesta en marcha en local

Requisitos: PHP ≥ 8.1 con las extensiones `pdo_mysql` y `mbstring` (y `zip`, para que Composer descargue los paquetes), Composer y MySQL/MariaDB (XAMPP, MAMP o WAMP sirven).

1. Clonar e instalar dependencias (Twig y, en desarrollo, PHPUnit):
```bash
git clone https://github.com/Albapz20/prueba-tecnica-AlbaPerezDuran.git
cd prueba-tecnica-reservas
composer install
```

2. Importa el fichero `database/gestion_reservas.sql`. Puedes hacerlo desde phpMyAdmin (pestaña Importar) o con la consola:
```bash
mysql -u root -p < database/gestion_reservas.sql
```

3. Arranca el servidor:
```bash
composer start
```

Y abre http://localhost:8000

### Configuración de la base de datos

Por defecto usa los valores de XAMPP (host 127.0.0.1, puerto 3306, usuario root y sin contraseña). Si usas otra configuración (por ejemplo MAMP) puedes cambiarla de dos formas:

- Con las variables de entorno DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASS.
- Creando un fichero `config/database.local.php` a partir de `config/database.local.example.php`. Este fichero está en el .gitignore para no subir las credenciales a GitHub.

### Tests
```bash
composer test
```

Todas las respuestas son JSON: `{"data": ...}` si todo va bien y `{"error": {"message": "...", "details": {campo: mensaje}}}` si hay un error.

- `GET /api/reservas`: lista las reservas. Acepta los filtros `estado`, `desde`, `hasta` y `que`.
- `GET /api/reservas/{id}`: devuelve una reserva con su historial.
- `POST /api/reservas`: crea una reserva (siempre queda en estado pendiente).
- `PATCH /api/reservas/{id}/estado`: cambia el estado a confirmada o cancelada.


Códigos que puede devolver: **405** si la ruta existe con otro método, **404** si no existe, **413** si el cuerpo supera 64 KB, **415** si falta `Content-Type: application/json` y **500** con un mensaje genérico ante errores inesperados.

Las páginas servidas con Twig son `/` (listado con filtros) y `/reservas/{id}` (detalle con timeline).

```bash
curl "http://localhost:8000/api/reservas?estado=pendiente&q=ana&desde=2026-11-01&hasta=2026-12-31"

curl -X POST http://localhost:8000/api/reservas -H "Content-Type: application/json" \
  -d '{"nombre_huesped":"Ana","email_huesped":"ana@example.com","nombre_alojamiento":"Apartamento Centro","fecha_entrada":"2027-03-01","fecha_salida":"2027-03-04","importe":250}'

curl -X PATCH http://localhost:8000/api/reservas/2/estado -H "Content-Type: application/json" -d '{"estado":"confirmada"}'
```

### Reglas de negocio

- Una reserva nueva siempre empieza como pendiente.
- Una pendiente puede pasar a confirmada o cancelada, y una confirmada puede pasar a cancelada.
- Una reserva cancelada ya no se puede cambiar (devuelve 409).
- El filtro de fechas devuelve las reservas cuya estancia coincide con ese rango.
  
## Estructura

```
public/index.php            Punto de entrada: monta las piezas, rutas, cabeceras y manejo de errores central
public/.htaccess            Envía a index.php las rutas que no son ficheros reales
.htaccess                   (Solo para hosting con carpeta htdocs) redirige todo a public/
src/Http/                   Request (lectura segura del JSON), Router, JsonResponse
src/Controller/             ReservaApiController (JSON) y ReservaVistaController (Twig). Sin SQL ni reglas
src/Validation/             ReservaValidator: valida y normaliza la entrada
src/Service/                ReservaService: reglas de negocio y transacciones
src/Repository/             Acceso a datos con PDO: un repositorio por tabla
src/Domain/                 EstadoReserva (enum) con las transiciones permitidas
src/Exception/              Excepciones con significado HTTP (400, 404, 409...)
config/                     Configuración de la conexión a la base de datos
templates/                  Plantilla base con herencia, macro de estado y las vistas
public/js, public/css       JavaScript y CSS propios
database/                   Script SQL con tablas y datos de ejemplo
tests/                      Tests unitarios (PHPUnit)
docs/                       Script de pruebas manuales de seguridad
```

Decisiones que he tomado: 

- He separado el código en capas: el controlador recibe la petición, el validador revisa los datos, el servicio aplica las reglas y el repositorio hace las consultas. Así cada clase hace una sola cosa y es más fácil de probar.
- Uso MySQL porque los datos son relacionales (una reserva tiene muchos eventos) y necesito integridad con clave foránea y `CHECK`, índices para los filtros y transacciones.
- Crear una reserva y guardar su evento se hace en una transacción, para que se guarden las dos cosas o ninguna. Al cambiar el estado se lee la reserva con `SELECT ... FOR UPDATE` para que dos peticiones simultáneas no se pisen.
- Las reglas de los estados están en un enum para tenerlas en un único sitio, más fácil testear, y el resto del código solo las consulta.
- Una excepción por significado HTTP. `ValidationException` (400), `NotFoundException` (404) y `ConflictException` (409) se lanzan donde ocurre el problema y se traducen en un único `catch` en `index.php`. Los errores de API responden JSON y los de las páginas, una vista HTML.
- El importe lo guardo como DECIMAL y lo trato como texto en PHP para evitar errores con decimales.
- Vistas en servidor + JavaScript encima. El listado y el detalle se renderizan con Twig y funcionan sin JavaScript (los filtros son un formulario GET). El JavaScript añade el filtrado sin recargar, el alta y los cambios de estado. Está en un único fichero, sin lógica dentro del HTML.
- Maquetación. HTML semántico, CSS propio responsive (la tabla se desplaza horizontalmente en móvil), estado de carga y avisos de éxito o error en la interfaz, y una franja de color por estado en cada fila.

## Seguridad

Algunas cosas que he tenido en cuenta:
- Consultas preparadas con PDO para evitar inyección SQL, y escapo los caracteres `%` y `_` en la búsqueda.
- Twig escapa el HTML automáticamente y en JavaScript uso `textContent` en vez de `innerHTML`, para evitar XSS.
- La API solo acepta JSON, y el estado inicial lo decide el servidor (el cliente no puede enviarlo).
- Los errores internos devuelven un 500 genérico y el detalle va al log del servidor.
- Limito el tamaño del cuerpo de las peticiones y la longitud de los campos.
- Añado cabeceras de seguridad (CSP, X-Frame-Options y X-Content-Type-Options).
- Las credenciales de la base de datos no están en el repositorio.

También hice un script de pruebas manuales, `docs/pruebas-seguridad.js`, que se pega en la consola del navegador con la app en marcha. Comprueba 20 casos (inyección SQL, datos inválidos, métodos no permitidos, etc.) y en local pasan los 20.

Limitaciones conocidas: La aplicación no tiene autenticación ni límite de peticiones, porque no estaban en el alcance de la prueba. En producción la API iría detrás de un login o token.

## Despliegue

Está publicado en **InfinityFree** (hosting gratuito con PHP 8.4 y MySQL), que encaja con el stack sin necesitar contenedores. Proceso seguido:

1. Se crea una copia del proyecto sin `.git`, tests ni `docs`, y se instala `vendor` con `composer install --no-dev`.
2. Se sube por FTP a `htdocs`. Un `.htaccess` en la raíz redirige todo a `public/`, de modo que `src/`, `config/` y `vendor/` no son accesibles desde internet.
3. Se importa `database/gestion_reservas.sql` en la base de datos del hosting (sin las sentencias `CREATE DATABASE` y `USE`, porque ahí la base ya está creada con otro nombre).
4. Las credenciales se guardan en `config/database.local.php`, que existe solo en el servidor y no está en el repositorio.

Limitaciones del hosting gratuito: no permite elegir la versión de PHP ni ejecutar Composer, y no es adecuado para producción real.

## Qué haría con más tiempo

- No tiene login ni límite de peticiones.
- No hay paginación en el listado.
- El alojamiento es solo un texto. Lo ideal sería tener una tabla aparte y comprobar que no se reserve el mismo alojamiento en fechas que se solapan.
- Faltan tests de integración de la API.
- Usaría migraciones en vez de un único fichero SQL.
- Cambiaría el `window.confirm` por un diálogo propio y mejoraría los mensajes de error.

Si hubiera muchas más reservas: Con unas 1000 reservas a la hora (unas 0,3 por segundo) creo que un solo MySQL con índices lo aguantaría bien. Si creciera más, lo primero sería medir qué consultas son lentas, y después añadir paginación, cambiar la búsqueda por nombre (el `LIKE '%texto%'` no usa índice) y poner caché en las consultas más repetidas.
