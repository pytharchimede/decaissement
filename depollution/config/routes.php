<?php

$routes = [
    'GET' => [
        '/' => 'public/index.php',
        '/admin' => 'public/admin.php',
        '/operateur1' => 'public/operateur1.php',
        '/operateur2' => 'public/operateur2.php',
        '/api' => 'public/api.php',
    ],
    'POST' => [
        '/api/projects' => 'src/Controllers/ProjectController.php',
        '/api/tasks' => 'src/Controllers/TaskController.php',
        '/api/auth/login' => 'src/Controllers/AuthController.php',
    ],
];

return $routes;