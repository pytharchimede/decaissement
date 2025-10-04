<?php

namespace App\Controllers;

use App\Services\AuthService;

class AuthController
{
    private $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function login($request)
    {
        $credentials = $request->getParsedBody();
        $user = $this->authService->authenticate($credentials['username'], $credentials['password']);

        if ($user) {
            $_SESSION['user'] = $user;
            header('Location: /public/admin.php');
            exit;
        } else {
            return $this->renderLoginError();
        }
    }

    public function logout()
    {
        session_destroy();
        header('Location: /public/index.php');
        exit;
    }

    private function renderLoginError()
    {
        // Logic to render login error view
    }
}