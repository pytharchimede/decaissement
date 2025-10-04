<?php

class Task {
    private $id;
    private $projectId;
    private $description;
    private $status;
    private $createdAt;
    private $updatedAt;

    public function __construct($id, $projectId, $description, $status, $createdAt, $updatedAt) {
        $this->id = $id;
        $this->projectId = $projectId;
        $this->description = $description;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId() {
        return $this->id;
    }

    public function getProjectId() {
        return $this->projectId;
    }

    public function getDescription() {
        return $this->description;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getCreatedAt() {
        return $this->createdAt;
    }

    public function getUpdatedAt() {
        return $this->updatedAt;
    }

    public function setDescription($description) {
        $this->description = $description;
    }

    public function setStatus($status) {
        $this->status = $status;
    }

    public function updateTimestamps() {
        $this->updatedAt = date('Y-m-d H:i:s');
    }
}