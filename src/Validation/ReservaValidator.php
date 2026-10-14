<?php
declare(strict_types=1);

namespace ReservasAlba\Validation;

use DateTimeImmutable;
use ReservasAlba\Exception\ValidationException;
use ReservasAlba\Domain\EstadoReserva;

final class ReservaValidator
{
    /** Valida los datos para crear una reserva.
     * Devuelve solo los campos esperados, ya limpios (lista blanca).
     * Si hay algún error, lanza ValidationException con todos los errores a la vez.*/

    public function validarCrear(array $entrada, ?DateTimeImmutable $hoy = null): array
    {
        $hoy ??= new DateTimeImmutable('today');
        $errores = [];

        // Campos de texto obligatorios.
        $nombre      = $this->texto($entrada['nombre_huesped'] ?? null, 150, 'nombre_huesped', $errores);
        $email       = $this->texto($entrada['email_huesped'] ?? null, 150, 'email_huesped', $errores);
        $alojamiento = $this->texto($entrada['nombre_alojamiento'] ?? null, 150, 'nombre_alojamiento', $errores);

        // Si el email es texto válido, se comprueba que tenga formato de email.
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errores['email_huesped'] = 'El email no es válido.';
        }

        // Fechas.
        $entrada_f = $this->fecha($entrada['fecha_entrada'] ?? null, 'fecha_entrada', $errores);
        $salida_f  = $this->fecha($entrada['fecha_salida'] ?? null, 'fecha_salida', $errores);

        // Coherencia entre fechas.
        if ($entrada_f !== null && $entrada_f < $hoy) {
            $errores['fecha_entrada'] = 'La fecha de entrada no puede estar en el pasado.';
        }
        if ($entrada_f !== null && $salida_f !== null && $salida_f <= $entrada_f) {
            $errores['fecha_salida'] = 'La fecha de salida debe ser posterior a la de entrada.';
        }

        // Importe: obligatorio, positivo, hasta 2 decimales. Se acepta como número o como string.
        $importe = $entrada['importe'] ?? null;
        $esNumeroSimple = is_int($importe) || is_float($importe) || is_string($importe);
        if ($importe === null || $importe === '') {
            $errores['importe'] = 'Este campo es obligatorio.';
        } elseif (!$esNumeroSimple || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', (string) $importe)) {
            $errores['importe'] = 'El importe debe ser un número positivo con hasta 2 decimales.';
        }

        // Notas: opcionales. Si vienen, deben ser texto de hasta 1000 caracteres.
        $notas = $entrada['notas'] ?? null;
        if ($notas === null || $notas === '') {
            $notas = null;
        } elseif (!is_string($notas) || mb_strlen($notas) > 1000) {
            $errores['notas'] = 'Las notas deben ser texto de hasta 1000 caracteres.';
        } else {
            $notas = trim($notas);
        }

        // Si hay errores, se lanza la excepción con todos ellos a la vez.
        if ($errores) {
            throw new ValidationException($errores);
        }

        // Si todo es válido, se devuelve un array con solo los campos esperados, ya limpios.
        return [
            'nombre_huesped'     => $nombre,
            'email_huesped'      => $email,
            'nombre_alojamiento' => $alojamiento,
            'fecha_entrada'      => $entrada_f->format('Y-m-d'),
            'fecha_salida'       => $salida_f->format('Y-m-d'),
            'importe'            => number_format((float) $importe, 2, '.', ''),  
            'notas'              => $notas,
        ];
    }

    // Valida el estado que pide el cliente en un cambio de estado.
    public function validarCambioEstado(array $entrada): EstadoReserva
    {
        $estado = is_string($entrada['estado'] ?? null)
            ? EstadoReserva::tryFrom($entrada['estado'])
            : null;

        if ($estado === null || $estado === EstadoReserva::Pendiente) {
            throw new ValidationException([
                'estado' => 'El estado debe ser "confirmada" o "cancelada".',
            ]);
        }

        return $estado;
    }

    // Valida un campo de texto obligatorio. Devuelve el texto sin espacios sobrantes, o null si no es válido.
    private function texto(mixed $valor, int $max, string $campo, array &$errores): ?string
    {
        // Debe ser un string y no estar vacío..
        if (!is_string($valor) || trim($valor) === '') {
            $errores[$campo] = 'Este campo es obligatorio.';
            return null;
        }

        $valor = trim($valor);

        if (mb_strlen($valor) > $max) {
            $errores[$campo] = "Máximo $max caracteres.";
            return null;
        }

        return $valor;
    }

    // Valida un campo de fecha obligatorio en formato AAAA-MM-DD. Devuelve un DateTimeImmutable o null si no es válido.
    private function fecha(mixed $valor, string $campo, array &$errores): ?DateTimeImmutable
    {
        if (!is_string($valor) || $valor === '') {
            $errores[$campo] = 'Este campo es obligatorio.';
            return null;
        }

        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        // Se comprueba que la fecha sea válida y que el formato coincida exactamente con AAAA-MM-DD.
        if ($fecha === false || $fecha->format('Y-m-d') !== $valor) {
            $errores[$campo] = 'Fecha no válida (formato AAAA-MM-DD).';
            return null;
        }

        return $fecha;
    }
}