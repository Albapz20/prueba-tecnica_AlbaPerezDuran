<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReservasAlba\Domain\EstadoReserva as E;

/**
 * Comprueba la tabla de transiciones de estado: es la regla de negocio central.
 */
final class EstadoReservaTest extends TestCase
{
    /**
     * Un "data provider" devuelve varios casos [estado actual, destino, ¿permitido?]
     * y PHPUnit ejecuta el mismo test una vez por cada uno.
     */
    public static function transiciones(): array
    {
        return [
            'pendiente a confirmada'  => [E::Pendiente,  E::Confirmada, true],
            'pendiente a cancelada'   => [E::Pendiente,  E::Cancelada,  true],
            'confirmada a cancelada'  => [E::Confirmada, E::Cancelada,  true],
            'confirmada a pendiente'  => [E::Confirmada, E::Pendiente,  false],
            'cancelada a confirmada'  => [E::Cancelada,  E::Confirmada, false],
            'cancelada a pendiente'   => [E::Cancelada,  E::Pendiente,  false],
            'cancelada a cancelada'   => [E::Cancelada,  E::Cancelada,  false],
        ];
    }

    #[DataProvider('transiciones')]
    public function testTransiciones(E $actual, E $destino, bool $esperado): void
    {
        $this->assertSame($esperado, $actual->puedePasarA($destino));
    }
}