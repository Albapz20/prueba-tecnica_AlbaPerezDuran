<?php
declare(strict_types=1);

use ReservasAlba\Controller\ReservaApiController;
use ReservasAlba\Database;
use ReservasAlba\Exception\ConflictException;
use ReservasAlba\Exception\HttpException;
use ReservasAlba\Exception\NotFoundException;
use ReservasAlba\Exception\ValidationException;
use ReservasAlba\Http\JsonResponse;
use ReservasAlba\Http\Request;
use ReservasAlba\Http\Router;
use ReservasAlba\Repository\ReservaEventoRepository;
use ReservasAlba\Repository\ReservaRepository;
use ReservasAlba\Service\ReservaService;
use ReservasAlba\Validation\ReservaValidator;

// Autoloading de clases: cualquier clase que empiece por ReservasAlba\ se carga desde src/
spl_autoload_register(function (string $clase): void {
    $prefijo = 'ReservasAlba\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;   
    }
    $relativa = substr($clase, strlen($prefijo));
    $ruta = __DIR__ . '/../src/' . str_replace('\\', '/', $relativa) . '.php';
    if (is_file($ruta)) {
        require $ruta;
    }
});

// Cabeceras de seguridad HTTP.
header('X-Content-Type-Options: nosniff');   
header('X-Frame-Options: DENY');             
header('Referrer-Policy: same-origin');

// Desactivar la visualización de errores de PHP al cliente.
ini_set('display_errors', '0');

try {
    $pdo = Database::connect(require __DIR__ . '/../config/database.php');

    $controlador = new ReservaApiController(
        new ReservaService($pdo, new ReservaRepository($pdo), new ReservaEventoRepository($pdo)),
        new ReservaValidator(),
    );

    // Rutas de la API REST. Cada ruta se asocia a un método del controlador.
    $router = new Router();
    $router->agregar('GET',   '/api/reservas',                [$controlador, 'listar']);
    $router->agregar('POST',  '/api/reservas',                [$controlador, 'crear']);
    $router->agregar('GET',   '/api/reservas/{id}',           [$controlador, 'ver']);
    $router->agregar('PATCH', '/api/reservas/{id}/estado',    [$controlador, 'cambiarEstado']);

    $router->despachar(Request::desdeGlobales());

// Captura de errores y envío de respuesta JSON con el código HTTP adecuado.
} catch (ValidationException $e) {
    JsonResponse::error(400, $e->getMessage(), $e->errores());   
} catch (NotFoundException $e) {
    JsonResponse::error(404, $e->getMessage());
} catch (ConflictException $e) {
    JsonResponse::error(409, $e->getMessage());
} catch (HttpException $e) {
    JsonResponse::error($e->status(), $e->getMessage());          
} catch (Throwable $e) {
    // Registrar el error en el log del servidor para poder investigarlo, pero no mostrarlo al cliente.
    error_log(sprintf('[reservas] %s: %s en %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
    JsonResponse::error(500, 'Error interno del servidor.');
}