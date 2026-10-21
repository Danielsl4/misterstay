<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ReservaRepository.php';
require_once __DIR__ . '/../src/ReservaService.php';

$twig = new \Twig\Environment(
    new \Twig\Loader\FilesystemLoader(
        __DIR__ . '/../templates'
    )
);

try {
    $pdo = Database::connect();
    $repository = new ReservaRepository($pdo);
    $service = new ReservaService($repository, $pdo);

    if (isset($_GET['id'])) {
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

        if ($id === false || $id <= 0) {
            http_response_code(404);
            echo $twig->render('error.twig', [
                'codigo' => 404,
                'titulo' => 'Identificador no válido',
                'mensaje' => 'El identificador de reserva proporcionado no tiene un formato válido.'
            ]);
            exit;
        }

        $reserva = $service->detalle($id);

        if ($reserva === null) {
            http_response_code(404);
            echo $twig->render('error.twig', [
                'codigo' => 404,
                'titulo' => 'Reserva no encontrada',
                'mensaje' => "No se ha encontrado ninguna reserva con el identificador #$id."
            ]);
            exit;
        }

        echo $twig->render('reservas/detalle.twig', [
            'reserva' => $reserva,
        ]);
        exit;
    }

    $reservas = $service->listar();

    echo $twig->render('reservas/listado.twig', [
        'reservas' => $reservas,
    ]);
} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);

    echo $twig->render('error.twig', [
        'codigo' => 500,
        'titulo' => 'Error interno',
        'mensaje' => 'Se ha producido un error al cargar la información. Por favor, inténtalo más tarde.'
    ]);
}

