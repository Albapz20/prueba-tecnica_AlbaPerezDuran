<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReservasAlba\Domain\EstadoReserva as E;

// Comprueba que las transiciones de estado de reserva son correctas.
final class EstadoReservaTest extends TestCase
{
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