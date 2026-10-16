<?php
declare(strict_types=1);

namespace ReservasAlba;

use PDO;

// Crea la conexión a la base de datos.
final class Database
{
    public static function connect(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'], $config['port'], $config['name'], $config['charset']
        );

        return new PDO($dsn, $config['user'], $config['password'], [
            // Si una consulta falla, lanza una excepción.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Las filas llegan como arrays asociativos.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
}