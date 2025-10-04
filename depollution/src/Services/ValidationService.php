<?php

namespace App\Services;

class ValidationService
{
    public function validateProjectData(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'Le nom du projet est requis.';
        }

        if (empty($data['description'])) {
            $errors['description'] = 'La description du projet est requise.';
        }

        if (empty($data['start_date'])) {
            $errors['start_date'] = 'La date de début est requise.';
        }

        if (empty($data['end_date'])) {
            $errors['end_date'] = 'La date de fin est requise.';
        }

        return $errors;
    }

    public function validateTaskData(array $data): array
    {
        $errors = [];

        if (empty($data['title'])) {
            $errors['title'] = 'Le titre de la tâche est requis.';
        }

        if (empty($data['assigned_to'])) {
            $errors['assigned_to'] = 'L\'assignation de la tâche est requise.';
        }

        if (empty($data['due_date'])) {
            $errors['due_date'] = 'La date d\'échéance est requise.';
        }

        return $errors;
    }
}