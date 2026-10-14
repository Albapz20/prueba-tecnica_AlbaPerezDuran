<?php

declare(strict_types=1);

namespace App\Repository;

// Importamos la clase PDO.
use PDO;

// Acceso a datos de la tabla reserva.
final class ReservaRepository   
{
    // Recibe la conexión desde fuera y la guarda como propiedad privada.
    public function __construct(private PDO $pdo) {}

    // Lista reservas con filtros opcionales y combinables.
    public function buscar(array $filtros = []): array
    {
        $where = []; 
        $params = [];   

        // Si llega un estado, añadimos la condición. "!empty" = existe y no está vacío.
        if (!empty($filtros['estado'])) {
            $where[] = 'estado = :estado';             
            $params[':estado'] = $filtros['estado'];   
        }
        // Rango de fechas por solapamiento: una reserva entra si su estancia toca el rango. Desde y hasta.
        if (!empty($filtros['desde'])) {
            $where[] = 'fecha_salida >= :desde';
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[] = 'fecha_entrada <= :hasta';
            $params[':hasta'] = $filtros['hasta'];
        }
        // Búsqueda por nombre.
        if (!empty($filtros['q'])) {
            $where[] = "nombre_huesped LIKE :q ESCAPE '!'";
            $escapado = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filtros['q']);
            $params[':q'] = '%' . $escapado . '%';   
        }

        // Base de la consulta.
        $sql = 'SELECT id, nombre_huesped, email_huesped, nombre_alojamiento,
                       fecha_entrada, fecha_salida, estado, importe, notas, fecha_creacion
                FROM reserva';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY fecha_entrada DESC, id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    // Devuelve una reserva por su id, o null si no existe.

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nombre_huesped, email_huesped, nombre_alojamiento,
                    fecha_entrada, fecha_salida, estado, importe, notas, fecha_creacion
             FROM reserva WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);

        return $stmt->fetch() ?: null;
    }

    //Inserta una reserva nueva y devuelve el id que le asignó MySQL.
    public function crear(array $d): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO reserva (nombre_huesped, email_huesped, nombre_alojamiento,
                                  fecha_entrada, fecha_salida, estado, importe, notas)
             VALUES (:nombre_huesped, :email_huesped, :nombre_alojamiento,
                     :fecha_entrada, :fecha_salida, 'pendiente', :importe, :notas)"
        );

        $stmt->execute([
            ':nombre_huesped'     => $d['nombre_huesped'],
            ':email_huesped'      => $d['email_huesped'],
            ':nombre_alojamiento' => $d['nombre_alojamiento'],
            ':fecha_entrada'      => $d['fecha_entrada'],
            ':fecha_salida'       => $d['fecha_salida'],
            ':importe'            => $d['importe'],
            ':notas'              => $d['notas'] ?? null,   
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}