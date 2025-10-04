<?php

class ProjectForm {
    private $data;
    private $errors = [];
    private static $fields = ['name', 'description', 'start_date', 'end_date', 'budget'];

    public function __construct($postData = null) {
        if ($postData) {
            $this->data = $postData;
        } else {
            $this->data = [];
        }
    }

    public function validate() {
        foreach (self::$fields as $field) {
            if (empty($this->data[$field])) {
                $this->errors[$field] = "$field is required.";
            }
        }
        return empty($this->errors);
    }

    public function getData() {
        return $this->data;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function setData($data) {
        $this->data = $data;
    }
}