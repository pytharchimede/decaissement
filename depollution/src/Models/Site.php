<?php

class Site {
    private $id;
    private $name;
    private $location;
    private $status;

    public function __construct($id, $name, $location, $status) {
        $this->id = $id;
        $this->name = $name;
        $this->location = $location;
        $this->status = $status;
    }

    public function getId() {
        return $this->id;
    }

    public function getName() {
        return $this->name;
    }

    public function getLocation() {
        return $this->location;
    }

    public function getStatus() {
        return $this->status;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function setLocation($location) {
        $this->location = $location;
    }

    public function setStatus($status) {
        $this->status = $status;
    }
}