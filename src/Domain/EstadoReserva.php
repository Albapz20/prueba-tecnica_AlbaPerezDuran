<?php
declare(strict_types=1);

namespace ReservasAlba\Domain;

//Los tres estados posibles de una reserva y las transiciones permitidas entre ellos.
enum EstadoReserva: string
{
    case Pendiente  = 'pendiente';
    case Confirmada = 'confirmada';
    case Cancelada  = 'cancelada';

    // Devuelve true si la reserva puede pasar del estado actual al estado $destino.
    public function puedePasarA(self $destino): bool
    {
        return match ($this) {
            self::Pendiente  => $destino === self::Confirmada || $destino === self::Cancelada,
            self::Confirmada => $destino === self::Cancelada,
            self::Cancelada  => false,   // estado final.
        };
    }
}