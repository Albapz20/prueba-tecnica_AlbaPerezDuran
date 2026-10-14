<?php
declare(strict_types=1);

namespace ReservasAlba\Exception;

use RuntimeException;

// Se lanza cuando los datos son correctos pero la operación choca con el estado actual.
final class ConflictException extends RuntimeException
{
}