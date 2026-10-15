<?php
declare(strict_types=1);

use ReservasAlba\Controller\ReservaApiController;
use ReservasAlba\Controller\ReservaVistaController;
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
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require __DIR__ . '/../vendor/autoload.php';

// Cabeceras de seguridad.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

// Nunca mostrar errores de PHP al cliente.
ini_set('display_errors', '0');

$request = Request::desdeGlobales();
$esApi   = str_starts_with($request->ruta, '/api/');

// Twig: motor de plantillas para las páginas HTML.
$twig = new Environment(new FilesystemLoader(__DIR__ . '/../templates'), [
    'autoescape' => 'html',
]);

// Función para responder errores, ya sea en JSON (API) o HTML (vistas).
$responderError = function (int $codigo, string $mensaje, array $detalles = []) use ($esApi, $twig): void {
    if ($esApi) {
        JsonResponse::error($codigo, $mensaje, $detalles);
        return;
    }
    http_response_code($codigo);
    header('Content-Type: text/html; charset=utf-8');
    echo $twig->render('error.twig', ['codigo' => $codigo, 'mensaje' => $mensaje]);
};

try {
    // Montaje de dependencias: repositorios, servicios y controladores.
    $pdo       = Database::connect(require __DIR__ . '/../config/database.php');
    $servicio  = new ReservaService($pdo, new ReservaRepository($pdo), new ReservaEventoRepository($pdo));
    $validador = new ReservaValidator();

    $api    = new ReservaApiController($servicio, $validador);
    $vistas = new ReservaVistaController($servicio, $validador, $twig);

    // Rutas
    $router = new Router();
    // Páginas (HTML renderizado con Twig)
    $router->agregar('GET', '/',                 [$vistas, 'listar']);
    $router->agregar('GET', '/reservas/{id}',    [$vistas, 'ver']);
    // API (JSON)
    $router->agregar('GET',   '/api/reservas',             [$api, 'listar']);
    $router->agregar('POST',  '/api/reservas',             [$api, 'crear']);
    $router->agregar('GET',   '/api/reservas/{id}',        [$api, 'ver']);
    $router->agregar('PATCH', '/api/reservas/{id}/estado', [$api, 'cambiarEstado']);

    $router->despachar($request);

// Manejo de errores.
} catch (ValidationException $e) {
    $responderError(400, $e->getMessage(), $e->errores());
} catch (NotFoundException $e) {
    $responderError(404, $e->getMessage());
} catch (ConflictException $e) {
    $responderError(409, $e->getMessage());
} catch (HttpException $e) {
    $responderError($e->status(), $e->getMessage());
} catch (Throwable $e) {
    // Error inesperado: el detalle va al log del servidor, al cliente un mensaje genérico.
    error_log(sprintf('[reservas] %s: %s en %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
    $responderError(500, 'Error interno del servidor.');
}