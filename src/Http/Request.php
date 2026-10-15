<?php
declare(strict_types=1);

// Carpeta src/Http/ -> ReservasAlba\Http
namespace ReservasAlba\Http;

use ReservasAlba\Exception\HttpException;

/**
 * Representa la petición HTTP entrante: método, ruta, parámetros de la URL y cuerpo JSON.
 * Encapsula las variables globales de PHP ($_SERVER, $_GET...) en un solo sitio.
 */
final class Request
{
    // Tamaño máximo del cuerpo: 64 KB. Una reserva ocupa unos cientos de bytes,
    // así que cualquier cosa mayor es un abuso.
    private const MAX_CUERPO = 65536;

    // "readonly": se asigna en el constructor y no se puede modificar después.
    public function __construct(
        public readonly string $metodo,   // GET, POST, PATCH...
        public readonly string $ruta,     // /api/reservas/3
        public readonly array $query,     // parámetros de la URL: ?estado=pendiente
    ) {}

    /** Construye la petición a partir de las variables globales de PHP. */
    public static function desdeGlobales(): self
    {
        // REQUEST_URI incluye la ruta y el ?query; parse_url se queda solo con la ruta.
        $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $ruta = rawurldecode($ruta);

        // Quitamos la barra final para que /api/reservas/ sea igual que /api/reservas.
        if ($ruta !== '/') {
            $ruta = rtrim($ruta, '/');
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $ruta,
            $_GET,
        );
    }

    /**
     * Devuelve el cuerpo de la petición como array asociativo.
     * Lanza HttpException si no es JSON, es demasiado grande o está mal formado.
     */
    public function json(): array
    {
        // 1) Exigimos Content-Type: application/json. Además de ser lo correcto, impide
        //    que un formulario HTML de otra web nos envíe datos (protege de CSRF).
        $tipo = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        if (!str_starts_with($tipo, 'application/json')) {
            throw new HttpException(415, 'El Content-Type debe ser application/json.');
        }

        // 2) Leemos como máximo MAX_CUERPO + 1 bytes: si llega ese byte de más, nos pasamos.
        //    "php://input" es el cuerpo crudo de la petición.
        $crudo = file_get_contents('php://input', false, null, 0, self::MAX_CUERPO + 1);
        if ($crudo === false || strlen($crudo) > self::MAX_CUERPO) {
            throw new HttpException(413, 'El cuerpo de la petición es demasiado grande.');
        }

        // 3) Decodificamos. JSON_THROW_ON_ERROR lanza excepción si el JSON es inválido
        //    (si no, json_decode devolvería null sin avisar). Profundidad máxima 8.
        try {
            $datos = json_decode($crudo, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new HttpException(400, 'JSON malformado.');
        }

        // 4) Esperamos un objeto {...}, no un array [...] ni un valor suelto.
        if (!is_array($datos) || array_is_list($datos)) {
            throw new HttpException(400, 'Se esperaba un objeto JSON.');
        }

        return $datos;
    }
}