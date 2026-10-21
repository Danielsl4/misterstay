<?php

require_once __DIR__ . '/ReservaService.php';

class ReservaController
{
    public function __construct(
        private ReservaService $service
    ) {
    }

    public function procesar(
        string $metodo,
        ?int $id,
        ?string $accion,
        array $filtros,
        array $datos
    ): void {
        try {
            if ($metodo === 'GET' && $id === null) {
                $this->responder(
                    200,
                    $this->service->listar($filtros)
                );
                return;
            }

            if ($metodo === 'GET' && $id !== null) {
                $reserva = $this->service->detalle($id);

                if ($reserva === null) {
                    $this->responder(404, [
                        'error' => 'Reserva no encontrada.'
                    ]);
                    return;
                }

                $this->responder(200, $reserva);
                return;
            }

            if ($metodo === 'POST' && $id === null) {
                $nuevoId = $this->service->crear($datos);

                $this->responder(201, [
                    'mensaje' => 'Reserva creada correctamente.',
                    'id' => $nuevoId
                ]);
                return;
            }

            if (
                $metodo === 'PATCH'
                && $id !== null
                && $accion === 'estado'
            ) {
                $estado = $datos['estado'] ?? null;

                if (!is_string($estado)) {
                    $this->responder(400, [
                        'error' => 'Debes indicar un estado válido.'
                    ]);
                    return;
                }

                $actualizada = $this->service->cambiarEstado(
                    $id,
                    $estado
                );

                if (!$actualizada) {
                    $this->responder(404, [
                        'error' => 'Reserva no encontrada.'
                    ]);
                    return;
                }

                $this->responder(200, [
                    'mensaje' => 'Estado actualizado correctamente.',
                    'reserva' => $this->service->detalle($id)
                ]);
                return;
            }

            $this->responder(404, [
                'error' => 'Endpoint no encontrado.'
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, [
                'error' => $e->getMessage()
            ]);
        } catch (DomainException $e) {
            $this->responder(409, [
                'error' => $e->getMessage()
            ]);
        } catch (Throwable $e) {
            error_log((string) $e);

            $this->responder(500, [
                'error' => 'Se ha producido un error interno.'
            ]);
        }
    }

    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
