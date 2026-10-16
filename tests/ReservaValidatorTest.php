<?php
declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReservasAlba\Domain\EstadoReserva;
use ReservasAlba\Exception\ValidationException;
use ReservasAlba\Validation\ReservaValidator;

/**
 * Comprueba que el validador acepta datos correctos, rechaza los incorrectos
 * y descarta lo que no esperaba. No necesita base de datos.
 */
final class ReservaValidatorTest extends TestCase
{
    private ReservaValidator $validador;
    private DateTimeImmutable $hoy;

    /** Se ejecuta antes de cada test. Fijamos "hoy" para que los tests no dependan del día real. */
    protected function setUp(): void
    {
        $this->validador = new ReservaValidator();
        $this->hoy = new DateTimeImmutable('2026-10-14');
    }

    /** Datos válidos de partida; cada test cambia solo lo que quiere probar. */
    private function datosValidos(array $cambios = []): array
    {
        return array_merge([
            'nombre_huesped'     => '  Alba Pérez ',
            'email_huesped'      => 'alba@email.com',
            'nombre_alojamiento' => 'Apartamento Centro',
            'fecha_entrada'      => '2026-11-01',
            'fecha_salida'       => '2026-11-03',
            'importe'            => '199.9',
            'notas'              => '',
        ], $cambios);
    }

    /** Ejecuta el validador y devuelve los errores por campo (falla el test si no hubo ninguno). */
    private function erroresDe(array $datos): array
    {
        try {
            $this->validador->validarCrear($datos, $this->hoy);
        } catch (ValidationException $e) {
            return $e->errores();
        }
        $this->fail('Se esperaba una ValidationException y no se lanzó.');
    }

    public function testDatosValidosSeNormalizan(): void
    {
        $resultado = $this->validador->validarCrear($this->datosValidos(), $this->hoy);

        $this->assertSame('Alba Pérez', $resultado['nombre_huesped']);   // sin espacios sobrantes
        $this->assertSame('199.90', $resultado['importe']);              // dos decimales
        $this->assertNull($resultado['notas']);                          // '' pasa a null
    }

    public function testCamposObligatorios(): void
    {
        $errores = $this->erroresDe([]);

        foreach (['nombre_huesped', 'email_huesped', 'nombre_alojamiento', 'fecha_entrada', 'fecha_salida', 'importe'] as $campo) {
            $this->assertArrayHasKey($campo, $errores);
        }
    }

    public function testRechazaEmailInvalido(): void
    {
        $errores = $this->erroresDe($this->datosValidos(['email_huesped' => 'esto-no-es-un-email']));
        $this->assertArrayHasKey('email_huesped', $errores);
    }

    public function testSalidaDebeSerPosteriorAEntrada(): void
    {
        $errores = $this->erroresDe($this->datosValidos(['fecha_salida' => '2026-11-01']));
        $this->assertArrayHasKey('fecha_salida', $errores);
    }

    public function testRechazaFechaInexistente(): void
    {
        // El 31 de febrero no existe: sin la comprobación extra, PHP lo "corregiría" a marzo.
        $errores = $this->erroresDe($this->datosValidos(['fecha_entrada' => '2027-02-31', 'fecha_salida' => '2027-03-05']));
        $this->assertArrayHasKey('fecha_entrada', $errores);
    }

    public function testRechazaEntradaEnElPasado(): void
    {
        $errores = $this->erroresDe($this->datosValidos(['fecha_entrada' => '2026-10-01']));
        $this->assertArrayHasKey('fecha_entrada', $errores);
    }

    public function testRechazaImporteNegativoOConDemasiadosDecimales(): void
    {
        $this->assertArrayHasKey('importe', $this->erroresDe($this->datosValidos(['importe' => '-5'])));
        $this->assertArrayHasKey('importe', $this->erroresDe($this->datosValidos(['importe' => '10.123'])));
    }

    public function testDescartaCamposNoEsperados(): void
    {
        // Un cliente malintencionado intenta colar un estado: nunca debe llegar al resultado.
        $resultado = $this->validador->validarCrear($this->datosValidos(['estado' => 'confirmada', 'id' => 99]), $this->hoy);

        $this->assertArrayNotHasKey('estado', $resultado);
        $this->assertArrayNotHasKey('id', $resultado);
    }

    public function testFiltrosValidos(): void
    {
        $filtros = $this->validador->validarFiltros(['estado' => 'pendiente', 'q' => ' ana ', 'desde' => '2026-01-01']);

        $this->assertSame('pendiente', $filtros['estado']);
        $this->assertSame('ana', $filtros['q']);
        $this->assertNull($filtros['hasta']);
    }

    public function testFiltroDeEstadoInvalidoSeRechaza(): void
    {
        $this->expectException(ValidationException::class);
        $this->validador->validarFiltros(['estado' => "pendiente'; DROP TABLE reserva;--"]);
    }

    public function testCambioDeEstadoSoloAConfirmadaOCancelada(): void
    {
        $this->assertSame(EstadoReserva::Cancelada, $this->validador->validarCambioEstado(['estado' => 'cancelada']));

        $this->expectException(ValidationException::class);
        $this->validador->validarCambioEstado(['estado' => 'pendiente']);   // no se puede volver a pendiente
    }
}