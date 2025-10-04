<?php

namespace App\Controllers;

use App\Repositories\JsonDataRepository;
use App\Services\ValidationService;

class OperatorController
{
    private $dataRepository;
    private $validationService;

    public function __construct()
    {
        $this->dataRepository = new JsonDataRepository();
        $this->validationService = new ValidationService();
    }

    public function submitTravelData($data)
    {
        if ($this->validationService->validateTravelData($data)) {
            $this->dataRepository->saveTravelData($data);
            return ['status' => 'success', 'message' => 'Travel data submitted successfully.'];
        }
        return ['status' => 'error', 'message' => 'Invalid travel data.'];
    }

    public function confirmTravel($travelId)
    {
        $travelData = $this->dataRepository->getTravelDataById($travelId);
        if ($travelData) {
            $this->dataRepository->confirmTravel($travelId);
            return ['status' => 'success', 'message' => 'Travel confirmed successfully.'];
        }
        return ['status' => 'error', 'message' => 'Travel not found.'];
    }
}