<?php
declare(strict_types=1);

namespace ReservasAlba\Exception;

use RuntimeException;

// Se lanza cuando los datos de entrada no son válidos.
final class ValidationException extends RuntimeException
{

    public function __construct(private array $errores)
    {
        // Mensaje genérico de la excepción.
        parent::__construct('Los datos enviados no son válidos.');
    }

    // Devuelve los errores por campo, para poder mostrarlos junto a cada input.
    public function errores(): array
    {
        return $this->errores;
    }
}