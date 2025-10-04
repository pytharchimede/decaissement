<?php

class Response
{
    public static function json($data, $status = 200)
    {
        header('Content-Type: application/json', true, $status);
        echo json_encode($data);
        exit;
    }

    public static function error($message, $status = 400)
    {
        self::json(['error' => $message], $status);
    }

    public static function success($data, $status = 200)
    {
        self::json(['data' => $data], $status);
    }
}