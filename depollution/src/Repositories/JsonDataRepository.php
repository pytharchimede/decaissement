<?php

class JsonDataRepository {
    private $dataPath;

    public function __construct($dataFile) {
        $this->dataPath = __DIR__ . '/../../data/' . $dataFile;
    }

    public function getAll() {
        return $this->readData();
    }

    public function getById($id) {
        $data = $this->readData();
        foreach ($data as $item) {
            if ($item['id'] == $id) {
                return $item;
            }
        }
        return null;
    }

    public function save($newItem) {
        $data = $this->readData();
        $data[] = $newItem;
        $this->writeData($data);
    }

    public function update($id, $updatedItem) {
        $data = $this->readData();
        foreach ($data as &$item) {
            if ($item['id'] == $id) {
                $item = array_merge($item, $updatedItem);
                break;
            }
        }
        $this->writeData($data);
    }

    public function delete($id) {
        $data = $this->readData();
        $data = array_filter($data, function($item) use ($id) {
            return $item['id'] != $id;
        });
        $this->writeData(array_values($data));
    }

    private function readData() {
        if (!file_exists($this->dataPath)) {
            return [];
        }
        $jsonData = file_get_contents($this->dataPath);
        return json_decode($jsonData, true);
    }

    private function writeData($data) {
        file_put_contents($this->dataPath, json_encode($data, JSON_PRETTY_PRINT));
    }
}