<?php

require_once __DIR__ . '/../model/Database.php';
require_once __DIR__ . '/../model/Operation.php';
require_once __DIR__ . '/../model/Designation.php';

$chantier_id = $_POST['chantier'];

$dataBaseObj = new Database();
$pdo = $dataBaseObj->getConnection();

$operationObj = new Operation($pdo);
$operations = $operationObj->getOperationByChantierId($chantier_id);

$designationObj = new Designation($pdo);

$designations = [];

// Parcourir les opérations et fusionner toutes les désignations dans un tableau unique
foreach ($operations as $operation) {
    $operation_id = $operation['id_operation'];
    $designationsPerOperation = $designationObj->getDesignationByOperationId($operation_id);

    // Fusionner avec le tableau principal
    $designations = array_merge($designations, $designationsPerOperation);
}

echo json_encode(["status" => "succes", "message" => $designations], JSON_UNESCAPED_UNICODE);
