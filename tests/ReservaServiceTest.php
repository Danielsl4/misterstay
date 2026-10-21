<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use ReservaRepository;
use ReservaService;

class ReservaServiceTest extends TestCase
{
    private ReservaRepository $repositoryMock;
    private PDO $pdoMock;
    private ReservaService $service;

    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(ReservaRepository::class);
        $this->pdoMock = $this->createMock(PDO::class);
        $this->service = new ReservaService($this->repositoryMock, $this->pdoMock);
    }

    private function getDatosValidos(): array
    {
        return [
            'nombre_huesped' => 'María Dolores',
            'email_huesped' => 'maria@example.com',
            'nombre_alojamiento' => 'Villa Sol y Mar',
            'fecha_entrada' => '2026-12-10',
            'fecha_salida' => '2026-12-15',
            'importe' => '320.00',
            'notas' => 'Cama doble si es posible'
        ];
    }

    public function testCrearReservaConDatosValidosRetornaId(): void
    {
        $datos = $this->getDatosValidos();

        $this->repositoryMock
            ->expects($this->once())
            ->method('crear')
            ->with($this->callback(function (array $argumentos) use ($datos) {
                return $argumentos['nombre_huesped'] === $datos['nombre_huesped']
                    && $argumentos['email_huesped'] === $datos['email_huesped']
                    && $argumentos['importe'] === '320.00';
            }))
            ->willReturn(42);

        $id = $this->service->crear($datos);

        $this->assertSame(42, $id);
    }

    public function testCrearReservaFallaSiFaltaCampoObligatorio(): void
    {
        $datos = $this->getDatosValidos();
        unset($datos['nombre_huesped']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El campo nombre_huesped es obligatorio.');

        $this->service->crear($datos);
    }

    public function testCrearReservaFallaSiEmailEsInvalido(): void
    {
        $datos = $this->getDatosValidos();
        $datos['email_huesped'] = 'email-invalido-sin-arroba';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El email del huésped no es válido.');

        $this->service->crear($datos);
    }

    public function testCrearReservaFallaSiFechaSalidaNoEsPosteriorAEntrada(): void
    {
        $datos = $this->getDatosValidos();
        $datos['fecha_entrada'] = '2026-12-15';
        $datos['fecha_salida'] = '2026-12-10';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La fecha de salida debe ser posterior a la de entrada.');

        $this->service->crear($datos);
    }

    public function testCrearReservaFallaSiImporteEsNegativoOInvalido(): void
    {
        $datos = $this->getDatosValidos();
        $datos['importe'] = '-50.00';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El importe debe ser un número positivo con un máximo de dos decimales.');

        $this->service->crear($datos);
    }

    public function testCambiarEstadoValidaQueSoloSeAcepteConfirmadaOCancelada(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Solo se puede confirmar o cancelar una reserva.');

        $this->service->cambiarEstado(1, 'finalizada');
    }

    public function testCambiarEstadoFallaConIdInvalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El identificador de la reserva no es válido.');

        $this->service->cambiarEstado(0, 'confirmada');
    }

    public function testListarFallaConEstadoNoValido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El estado indicado no es válido.');

        $this->service->listar(['estado' => 'desconocido']);
    }

    public function testListarFallaSiFechaDesdeEsPosteriorAFechaHasta(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La fecha inicial no puede ser posterior a la final.');

        $this->service->listar([
            'fecha_desde' => '2026-12-30',
            'fecha_hasta' => '2026-12-01'
        ]);
    }
}
