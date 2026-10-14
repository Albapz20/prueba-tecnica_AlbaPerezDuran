<?php
declare(strict_types=1);

namespace ReservasAlba\Exception;

use RuntimeException;

// Se lanza cuando no se encuentra un recurso (404).
final class NotFoundException extends RuntimeException
{
    public function __construct(string $mensaje = 'Recurso no encontrado.')
    {
        parent::__construct($mensaje);
    }
}