<?php

class Helpers {
    public static function sanitizeInput($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }

    public static function jsonResponse($data, $status = 200) {
        header('Content-Type: application/json', true, $status);
        echo json_encode($data);
        exit;
    }

    public static function redirect($url) {
        header("Location: $url");
        exit;
    }

    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function generateRandomString($length = 10) {
        return bin2hex(random_bytes($length / 2);
    }
}