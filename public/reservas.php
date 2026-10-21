<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/ReservaRepository.php';

try {
    $pdo = Database::connect();
    $repository = new ReservaRepository($pdo);

    $reservas = $repository->listar();

    $twig = new \Twig\Environment(
        new \Twig\Loader\FilesystemLoader(
            __DIR__ . '/../templates'
        )
    );

    echo $twig->render('reservas/listado.twig', [
        'reservas' => $reservas,
    ]);
} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    echo 'No se pudieron cargar las reservas.';
}
