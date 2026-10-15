<?php
declare(strict_types=1);

namespace ReservasAlba\Service;

use PDO;
use ReservasAlba\Domain\EstadoReserva;
use ReservasAlba\Exception\ConflictException;
use ReservasAlba\Exception\NotFoundException;
use ReservasAlba\Repository\ReservaEventoRepository;
use ReservasAlba\Repository\ReservaRepository;
use Throwable;

// Lógica de negocio de reservas.
final class ReservaService
{
    public function __construct(
        private PDO $pdo,
        private ReservaRepository $reservas,
        private ReservaEventoRepository $eventos,
    ) {}

    // Crea una reserva nueva en estado pendiente y devuelve su id. Registra un evento de creación.
    public function crear(array $datos): int
    {
        return $this->enTransaccion(function () use ($datos): int {
            $id = $this->reservas->crear($datos);
            $this->eventos->registrar($id, 'CREACION', 'Reserva creada en estado pendiente.');

            return $id;
        });
    }

    /**
     * Cambia el estado de una reserva respetando las transiciones permitidas.
     * Lanza NotFoundException si no existe y ConflictException si el cambio no es válido.
     * Devuelve la reserva ya actualizada, con su historial.
     */
    public function cambiarEstado(int $id, EstadoReserva $nuevo): array
    {
        $this->enTransaccion(function () use ($id, $nuevo): void {
            $reserva = $this->reservas->buscarPorId($id, true);
            if ($reserva === null) {
                throw new NotFoundException('Reserva no encontrada.');
            }
            $actual = EstadoReserva::from($reserva['estado']);

            if (!$actual->puedePasarA($nuevo)) {
                throw new ConflictException(
                    "No se puede pasar de {$actual->value} a {$nuevo->value}."
                );
            }

            $this->reservas->actualizarEstado($id, $nuevo->value);
            $this->eventos->registrar($id, 'CAMBIO_ESTADO', "Estado actualizado a {$nuevo->value}.");
        });

        return $this->obtenerDetalle($id);
    }

    // Lista reservas aplicando filtros ya validados. 
    public function listar(array $filtros): array
    {
        return $this->reservas->buscar($filtros);
    }
    
    // Devuelve la reserva con su historial de eventos, o null si no existe.
    public function obtenerDetalle(int $id): ?array
    {
        $reserva = $this->reservas->buscarPorId($id);
        if ($reserva === null) {
            return null;
        }
        $reserva['eventos'] = $this->eventos->listarPorReserva($id);

        return $reserva;
    }

    /**
     * Ejecuta una función dentro de una transacción: si termina bien, commit;
     * si lanza cualquier error, rollback y se vuelve a lanzar hacia arriba.
     * Se extrae para no repetir el mismo patrón (try/catch) en cada operación.
     */
    private function enTransaccion(callable $operacion): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $resultado = $operacion();
            $this->pdo->commit();

            return $resultado;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}