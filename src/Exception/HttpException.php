<?php
declare(strict_types=1);

namespace ReservasAlba\Exception;

use RuntimeException;

// Excepción que representa un error HTTP, con código de estado y mensaje.
class HttpException extends RuntimeException
{
    public function __construct(private int $status, string $mensaje)
    {
        parent::__construct($mensaje);
    }

    public function status(): int
    {
        return $this->status;
    }
}