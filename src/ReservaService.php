<?php

require_once __DIR__ . '/ReservaRepository.php';

class ReservaService
{
    private const ESTADOS = [
        'pendiente',
        'confirmada',
        'cancelada'
    ];

    public function __construct(
        private ReservaRepository $repository,
        private PDO $pdo
    ) {
    }

    public function listar(array $filtros = []): array
    {
        if (
            isset($filtros['estado'])
            && !in_array($filtros['estado'], self::ESTADOS, true)
        ) {
            throw new InvalidArgumentException(
                'El estado indicado no es válido.'
            );
        }

        foreach (['fecha_desde', 'fecha_hasta'] as $campo) {
            if (
                !empty($filtros[$campo])
                && !$this->fechaValida($filtros[$campo])
            ) {
                throw new InvalidArgumentException(
                    "La fecha del filtro $campo no es válida."
                );
            }
        }

        if (
            !empty($filtros['fecha_desde'])
            && !empty($filtros['fecha_hasta'])
            && $filtros['fecha_desde'] > $filtros['fecha_hasta']
        ) {
            throw new InvalidArgumentException(
                'La fecha inicial no puede ser posterior a la final.'
            );
        }

        return $this->repository->listar($filtros);
    }

    private function fechaValida(string $fecha): bool
    {
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fecha
        );

        return $date !== false
            && $date->format('Y-m-d') === $fecha;
    }
    
public function crear(array $datos): int
{
    $camposObligatorios = [
        'nombre_huesped',
        'email_huesped',
        'nombre_alojamiento',
        'fecha_entrada',
        'fecha_salida',
        'importe',
    ];

    foreach ($camposObligatorios as $campo) {
        if (
            !isset($datos[$campo])
            || !is_string($datos[$campo])
               && !is_numeric($datos[$campo])
            || trim((string) $datos[$campo]) === ''
        ) {
            throw new InvalidArgumentException(
                "El campo $campo es obligatorio."
            );
        }
    }

    foreach (
        ['nombre_huesped' => 120, 'nombre_alojamiento' => 150]
        as $campo => $maximo
    ) {
        if (
            mb_strlen(trim((string) $datos[$campo])) > $maximo
        ) {
            throw new InvalidArgumentException(
                "El campo $campo supera la longitud permitida."
            );
        }
    }

    if (
        mb_strlen(trim((string) $datos['email_huesped'])) > 254
        || !filter_var(
            $datos['email_huesped'],
            FILTER_VALIDATE_EMAIL
        )
    ) {
        throw new InvalidArgumentException(
            'El email del huésped no es válido.'
        );
    }

    foreach (['fecha_entrada', 'fecha_salida'] as $campo) {
        if (!$this->fechaValida((string) $datos[$campo])) {
            throw new InvalidArgumentException(
                "El campo $campo debe contener una fecha válida."
            );
        }
    }

    if ($datos['fecha_salida'] <= $datos['fecha_entrada']) {
        throw new InvalidArgumentException(
            'La fecha de salida debe ser posterior a la de entrada.'
        );
    }

    if (
        !is_numeric($datos['importe'])
        || !is_finite((float) $datos['importe'])
        || (float) $datos['importe'] < 0
        || (float) $datos['importe'] > 99999999.99
        || !preg_match(
            '/^\d+(?:\.\d{1,2})?$/',
            (string) $datos['importe']
        )
    ) {
        throw new InvalidArgumentException(
            'El importe debe ser un número positivo con un máximo de dos decimales.'
        );
    }

    if (
        isset($datos['notas'])
        && (
            !is_string($datos['notas'])
            || mb_strlen($datos['notas']) > 5000
        )
    ) {
        throw new InvalidArgumentException(
            'Las notas deben ser texto de hasta 5000 caracteres.'
        );
    }

    $datos['nombre_huesped'] = trim($datos['nombre_huesped']);
    $datos['email_huesped'] = trim($datos['email_huesped']);
    $datos['nombre_alojamiento'] = trim(
        $datos['nombre_alojamiento']
    );
    $datos['notas'] = isset($datos['notas'])
        ? trim($datos['notas'])
        : null;

    return $this->repository->crear($datos);
}

public function cambiarEstado(int $id, string $estado): bool
{
    if ($id <= 0) {
        throw new InvalidArgumentException(
            'El identificador de la reserva no es válido.'
        );
    }

    if (!in_array($estado, ['confirmada', 'cancelada'], true)) {
        throw new InvalidArgumentException(
            'Solo se puede confirmar o cancelar una reserva.'
        );
    }

    return $this->repository->cambiarEstado($id, $estado);
}

public function detalle(int $id): ?array
{
    if ($id <= 0) {
        throw new InvalidArgumentException(
            'El identificador no es válido.'
        );
    }

    $reserva = $this->repository->buscarPorId($id);

    if ($reserva === null) {
        return null;
    }

    $reserva['eventos'] = $this->repository->obtenerEventos($id);

    return $reserva;
}

}
