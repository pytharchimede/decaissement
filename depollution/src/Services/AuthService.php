<?php

class AuthService {
    private $users;

    public function __construct($userRepository) {
        $this->users = $userRepository->getAllUsers();
    }

    public function authenticate($username, $password) {
        foreach ($this->users as $user) {
            if ($user['username'] === $username && password_verify($password, $user['password'])) {
                return $user;
            }
        }
        return null;
    }

    public function isAuthenticated() {
        return isset($_SESSION['user']);
    }

    public function login($user) {
        $_SESSION['user'] = $user;
    }

    public function logout() {
        unset($_SESSION['user']);
    }
}