
<?php

require_once __DIR__ . '/src/ReservaService.php';

try {
    $pdo = Database::connect();
    $repository = new ReservaRepository($pdo);
    $service = new ReservaService($repository, $pdo);

    $id = 6;

    $resultado = $service->cambiarEstado($id, 'cancelada');

    if (!$resultado) {
        echo 'La reserva no existe.';
    } else {
        $reserva = $repository->buscarPorId($id);
        $eventos = $repository->obtenerEventos($id);

        echo 'Estado actual: '
            . htmlspecialchars($reserva['estado'], ENT_QUOTES, 'UTF-8');

        echo '<p>Total de eventos: ' . count($eventos) . '</p>';
    }
} catch (InvalidArgumentException | DomainException $e) {
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Se ha producido un error al cambiar el estado.';
}
