<?php

class TaskForm {
    private $data;
    private $errors = [];

    public function __construct($data = null) {
        $this->data = $data ? $data : [];
    }

    public function validate() {
        if (empty($this->data['title'])) {
            $this->errors['title'] = 'Le titre est requis.';
        }

        if (empty($this->data['description'])) {
            $this->errors['description'] = 'La description est requise.';
        }

        if (empty($this->data['due_date'])) {
            $this->errors['due_date'] = 'La date d\'échéance est requise.';
        }

        return empty($this->errors);
    }

    public function getErrors() {
        return $this->errors;
    }

    public function getData() {
        return $this->data;
    }

    public function setData($data) {
        $this->data = $data;
    }
}