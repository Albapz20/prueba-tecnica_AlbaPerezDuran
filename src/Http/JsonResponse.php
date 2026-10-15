<?php
declare(strict_types=1);

namespace ReservasAlba\Http;

/**
 * Envía respuestas JSON con el código de estado HTTP adecuado.
 */
final class JsonResponse
{
    /**
     * Respuesta correcta. $cabeceras permite añadir extras (por ejemplo Location en un 201).
     */
    public static function enviar(int $codigo, array $contenido, array $cabeceras = []): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($cabeceras as $nombre => $valor) {
            header("$nombre: $valor");
        }

        // JSON_UNESCAPED_UNICODE: las tildes y la ñ salen tal cual, no como \u00e1.
        echo json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Respuesta de error con formato uniforme:
     * {"error": {"message": "...", "details": {"campo": "mensaje"}}}
     * "details" solo aparece si hay detalles (por ejemplo, errores de validación por campo).
     */
    public static function error(int $codigo, string $mensaje, array $detalles = []): void
    {
        $error = ['message' => $mensaje];
        if ($detalles) {
            $error['details'] = $detalles;
        }

        self::enviar($codigo, ['error' => $error]);
    }
}