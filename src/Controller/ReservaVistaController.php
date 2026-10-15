<?php
declare(strict_types=1);

namespace ReservasAlba\Controller;

use ReservasAlba\Domain\EstadoReserva;
use ReservasAlba\Exception\NotFoundException;
use ReservasAlba\Exception\ValidationException;
use ReservasAlba\Http\Request;
use ReservasAlba\Service\ReservaService;
use ReservasAlba\Validation\ReservaValidator;
use Twig\Environment;

// Controlador de la vista de reservas: recibe la petición HTTP, llama al servicio y renderiza la plantilla Twig.
final class ReservaVistaController
{
    public function __construct(
        private ReservaService $servicio,
        private ReservaValidator $validador,
        private Environment $twig,
    ) {}

    // GET /reservas -> listado de reservas, con filtros opcionales en la query string.
    public function listar(Request $request, array $params): void
    {
        // De la URL solo nos interesan estos cuatro parámetros, y solo si son texto.
        $filtros = array_intersect_key($request->query, array_flip(['estado', 'desde', 'hasta', 'q']));
        $filtros = array_filter($filtros, 'is_string');

        try {
            $reservas = $this->servicio->listar($this->validador->validarFiltros($filtros));
            $error = null;
        } catch (ValidationException $e) {
            // Filtros inválidos en la URL: no rompemos la página, mostramos un aviso.
            $reservas = [];
            $error = implode(' ', $e->errores());
        }

        $this->renderizar('reservas/listado.twig', [
            'reservas' => $reservas,
            'filtros'  => $filtros,    
            'estados'  => array_map(fn(EstadoReserva $e) => $e->value, EstadoReserva::cases()),
            'error'    => $error,
        ]);
    }

    // GET /reservas/{id} -> detalle de una reserva.
    public function ver(Request $request, array $params): void
    {
        $crudo = $params['id'] ?? '';
        if (!ctype_digit($crudo) || strlen($crudo) > 10) {
            throw new NotFoundException('Reserva no encontrada.');
        }

        $reserva = $this->servicio->obtenerDetalle((int) $crudo);
        if ($reserva === null) {
            throw new NotFoundException('Reserva no encontrada.');
        }

        $this->renderizar('reservas/detalle.twig', ['reserva' => $reserva]);
    }

    // Renderiza una plantilla Twig y envía la respuesta HTML al cliente.
    private function renderizar(string $plantilla, array $datos): void
    {
        $html = $this->twig->render($plantilla, $datos);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }
}