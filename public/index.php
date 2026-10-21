
<?php

require_once __DIR__ . '/../src/ReservaController.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::connect();
    $repository = new ReservaRepository($pdo);
    $service = new ReservaService($repository, $pdo);
    $controller = new ReservaController($service);

    $metodo = $_SERVER['REQUEST_METHOD'];
    $ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

    if ($base !== '' && str_starts_with($ruta, $base . '/')) {
        $ruta = substr($ruta, strlen($base));
    }

    $ruta = rtrim($ruta, '/') ?: '/';

    $id = null;
    $accion = null;

    if ($ruta === '/api/reservas') {
        // Listado o creación.
    } elseif (
        preg_match(
            '#^/api/reservas/([1-9][0-9]*)$#',
            $ruta,
            $coincidencias
        )
    ) {
        $id = filter_var(
            $coincidencias[1],
            FILTER_VALIDATE_INT
        );
    } elseif (
        preg_match(
            '#^/api/reservas/([1-9][0-9]*)/estado$#',
            $ruta,
            $coincidencias
        )
    ) {
        $id = filter_var(
            $coincidencias[1],
            FILTER_VALIDATE_INT
        );
        $accion = 'estado';
    } else {
        http_response_code(404);
        echo json_encode([
            'error' => 'Endpoint no encontrado.'
        ]);
        exit;
    }

    $datos = [];

    if (in_array($metodo, ['POST', 'PATCH'], true)) {
        $contenido = file_get_contents('php://input');
        $datosDecodificados = json_decode($contenido, true);

        if (
            !is_array($datosDecodificados)
            || json_last_error() !== JSON_ERROR_NONE
        ) {
            http_response_code(400);
            echo json_encode([
                'error' => 'El cuerpo debe contener un objeto JSON válido.'
            ]);
            exit;
        }

        $datos = $datosDecodificados;
    }

    $filtros = [];

    foreach (
        ['estado', 'fecha_desde', 'fecha_hasta', 'huesped']
        as $campo
    ) {
        if (isset($_GET[$campo])) {
            if (!is_string($_GET[$campo])) {
                http_response_code(400);
                echo json_encode([
                    'error' => "El filtro $campo no es válido."
                ]);
                exit;
            }

            $filtros[$campo] = trim($_GET[$campo]);
        }
    }

    $controller->procesar(
        $metodo,
        $id === false ? null : $id,
        $accion,
        $filtros,
        $datos
    );
} catch (Throwable $e) {
    error_log((string) $e);

    http_response_code(500);
    echo json_encode([
        'error' => 'Se ha producido un error interno.'
    ]);
}
