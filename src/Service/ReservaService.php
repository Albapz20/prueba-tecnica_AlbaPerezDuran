<?php
declare(strict_types=1);

namespace ReservasAlba\Service;

use PDO;
use ReservasAlba\Repository\ReservaEventoRepository;
use ReservasAlba\Repository\ReservaRepository;
use Throwable;   

// Lógica de negocio. Coordina varios repositorios y decide qué operaciones deben ocurrir juntas. 
final class ReservaService
{
    public function __construct(
        private PDO $pdo,
        private ReservaRepository $reservas,
        private ReservaEventoRepository $eventos,
    ) {}

    // Crea una reserva y su evento CREACION de forma atómica. Devuelve el id de la reserva creada.
    public function crear(array $datos): int
    {
        $this->pdo->beginTransaction();

        try {
            $id = $this->reservas->crear($datos);
            $this->eventos->registrar($id, 'CREACION', 'Reserva creada en estado pendiente.');

            $this->pdo->commit();

            return $id;
        } catch (Throwable $e) {
        
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
           
            throw $e;
        }
    }

    // Devuelve una reserva con su historial dentro del mismo array, o null si no existe.
    public function obtenerDetalle(int $id): ?array
    {
        $reserva = $this->reservas->buscarPorId($id);
        
        if ($reserva === null) {
            return null;
        }
        $reserva['eventos'] = $this->eventos->listarPorReserva($id);

        return $reserva;
    }
}