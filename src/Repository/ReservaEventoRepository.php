<?php
declare(strict_types=1);

namespace ReservasAlba\Repository;

use PDO;

// Acceso a datos de la tabla `reserva_evento`..
final class ReservaEventoRepository
{
    // Recibe la conexión desde fuera y la guarda como propiedad privada.
    public function __construct(private PDO $pdo) {}

    public function registrar(int $reservaId, string $tipo, string $descripcion): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reserva_evento (reserva_id, tipo, descripcion)
             VALUES (:reserva_id, :tipo, :descripcion)'
        );
        $stmt->execute([
            ':reserva_id'  => $reservaId,
            ':tipo'        => $tipo,
            ':descripcion' => $descripcion,
        ]);
    }

    // Devuelve el historial de una reserva, del más antiguo al más reciente,
    public function listarPorReserva(int $reservaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tipo, descripcion, fecha_creacion
             FROM reserva_evento
             WHERE reserva_id = :reserva_id
             ORDER BY fecha_creacion ASC, id ASC'   // el id desempata si coinciden en fecha
        );
        $stmt->execute([':reserva_id' => $reservaId]);

        // Si la reserva no tiene eventos devuelve un array vacío, no un error.
        return $stmt->fetchAll();
    }
}