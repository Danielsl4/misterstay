<?php

require_once __DIR__ . '/Database.php';

class ReservaRepository
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    
public function listar(array $filtros = []): array
{
    $sql = 'SELECT * FROM reserva WHERE 1 = 1';
    $parametros = [];

    if (!empty($filtros['estado'])) {
        $sql .= ' AND estado = :estado';
        $parametros['estado'] = $filtros['estado'];
    }

    if (!empty($filtros['fecha_desde'])) {
        $sql .= ' AND fecha_entrada >= :fecha_desde';
        $parametros['fecha_desde'] = $filtros['fecha_desde'];
    }

    if (!empty($filtros['fecha_hasta'])) {
        $sql .= ' AND fecha_entrada <= :fecha_hasta';
        $parametros['fecha_hasta'] = $filtros['fecha_hasta'];
    }

    if (!empty($filtros['huesped'])) {
        $sql .= ' AND nombre_huesped LIKE :huesped';
        $parametros['huesped'] = '%' . $filtros['huesped'] . '%';
    }

    $sql .= ' ORDER BY fecha_creacion DESC';

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($parametros);

    return $stmt->fetchAll();
}

    public function buscarPorId(int $id): ?array
    {
        $sql = 'SELECT * FROM reserva WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $reserva = $stmt->fetch();

        return $reserva === false ? null : $reserva;
    }

    public function obtenerEventos(int $reservaId): array
    {
        $sql = 'SELECT * FROM reserva_evento
                WHERE reserva_id = :reserva_id
                ORDER BY fecha_creacion ASC, id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['reserva_id' => $reservaId]);

        return $stmt->fetchAll();
    }
    
public function crear(array $datos): int
{
    $this->pdo->beginTransaction();

    try {
        $sql = 'INSERT INTO reserva (
                    nombre_huesped,
                    email_huesped,
                    nombre_alojamiento,
                    fecha_entrada,
                    fecha_salida,
                    estado,
                    importe,
                    notas
                ) VALUES (
                    :nombre_huesped,
                    :email_huesped,
                    :nombre_alojamiento,
                    :fecha_entrada,
                    :fecha_salida,
                    :estado,
                    :importe,
                    :notas
                )';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'nombre_huesped' => $datos['nombre_huesped'],
            'email_huesped' => $datos['email_huesped'],
            'nombre_alojamiento' => $datos['nombre_alojamiento'],
            'fecha_entrada' => $datos['fecha_entrada'],
            'fecha_salida' => $datos['fecha_salida'],
            'estado' => 'pendiente',
            'importe' => $datos['importe'],
            'notas' => $datos['notas'] ?? null,
        ]);

        $reservaId = (int) $this->pdo->lastInsertId();

        $sqlEvento = 'INSERT INTO reserva_evento (
                          reserva_id, tipo, descripcion
                      ) VALUES (
                          :reserva_id, :tipo, :descripcion
                      )';

        $stmtEvento = $this->pdo->prepare($sqlEvento);

        $stmtEvento->execute([
            'reserva_id' => $reservaId,
            'tipo' => 'creacion',
            'descripcion' => 'Reserva creada',
        ]);

        $this->pdo->commit();

        return $reservaId;
    } catch (Throwable $e) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        throw $e;
    }
}

public function cambiarEstado(int $id, string $estado): bool
{
    $this->pdo->beginTransaction();

    try {
        $stmt = $this->pdo->prepare(
            'SELECT estado FROM reserva WHERE id = :id FOR UPDATE'
        );
        $stmt->execute(['id' => $id]);

        $reserva = $stmt->fetch();

        if ($reserva === false) {
            $this->pdo->rollBack();
            return false;
        }

        $estadoActual = $reserva['estado'];

        if ($estadoActual === $estado) {
            $this->pdo->commit();
            return true;
        }

        if ($estadoActual === 'cancelada') {
            throw new DomainException(
                'No se puede cambiar el estado de una reserva cancelada.'
            );
        }

        $stmt = $this->pdo->prepare(
            'UPDATE reserva SET estado = :estado WHERE id = :id'
        );
        $stmt->execute([
            'estado' => $estado,
            'id' => $id,
        ]);

        $stmtEvento = $this->pdo->prepare(
            'INSERT INTO reserva_evento (
                reserva_id, tipo, descripcion
            ) VALUES (
                :reserva_id, :tipo, :descripcion
            )'
        );

        $stmtEvento->execute([
            'reserva_id' => $id,
            'tipo' => 'cambio_estado',
            'descripcion' => 'Estado cambiado de '
                . $estadoActual . ' a ' . $estado,
        ]);

        $this->pdo->commit();

        return true;
    } catch (Throwable $e) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        throw $e;
    }
}

}
