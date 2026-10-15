<?php
declare(strict_types=1);

namespace ReservasAlba\Controller;

use ReservasAlba\Exception\NotFoundException;
use ReservasAlba\Http\JsonResponse;
use ReservasAlba\Http\Request;
use ReservasAlba\Service\ReservaService;
use ReservasAlba\Validation\ReservaValidator;

// Capa HTTP de la API: recibe la petición, llama a validador y servicio, y responde.
final class ReservaApiController
{
    public function __construct(
        private ReservaService $servicio,
        private ReservaValidator $validador,
    ) {}

    public function listar(Request $request, array $params): void
    {
        $filtros  = $this->validador->validarFiltros($request->query);
        $reservas = $this->servicio->listar($filtros);

        JsonResponse::enviar(200, [
            'data' => $reservas,
            'meta' => ['total' => count($reservas)],
        ]);
    }

    public function ver(Request $request, array $params): void
    {
        $detalle = $this->servicio->obtenerDetalle($this->id($params));

        if ($detalle === null) {
            throw new NotFoundException('Reserva no encontrada.');
        }

        JsonResponse::enviar(200, ['data' => $detalle]);
    }

    public function crear(Request $request, array $params): void
    {
        $datos = $this->validador->validarCrear($request->json());

        $id      = $this->servicio->crear($datos);
        $detalle = $this->servicio->obtenerDetalle($id);

        JsonResponse::enviar(201, ['data' => $detalle], ['Location' => "/api/reservas/$id"]);
    }

    public function cambiarEstado(Request $request, array $params): void
    {
        $id     = $this->id($params);
        $nuevo  = $this->validador->validarCambioEstado($request->json());

        $detalle = $this->servicio->cambiarEstado($id, $nuevo);

        JsonResponse::enviar(200, ['data' => $detalle]);
    }

    // Convierte el parámetro {id} de la ruta en un entero, o lanza NotFoundException si no es válido.
    private function id(array $params): int
    {
        $crudo = $params['id'] ?? '';

        if (!ctype_digit($crudo) || strlen($crudo) > 10) {
            throw new NotFoundException('Reserva no encontrada.');
        }

        return (int) $crudo;
    }
}