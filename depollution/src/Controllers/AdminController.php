<?php

namespace App\Controllers;

use App\Repositories\JsonDataRepository;
use App\Services\ProjectService;

class AdminController
{
    private $projectService;
    private $jsonDataRepository;

    public function __construct()
    {
        $this->jsonDataRepository = new JsonDataRepository();
        $this->projectService = new ProjectService($this->jsonDataRepository);
    }

    public function dashboard()
    {
        // Logic to display the admin dashboard
        $projects = $this->projectService->getAllProjects();
        include '../src/Views/admin/dashboard.php';
    }

    public function manageProjects()
    {
        // Logic to manage projects
        $projects = $this->projectService->getAllProjects();
        include '../src/Views/admin/projects.php';
    }

    public function manageUsers()
    {
        // Logic to manage users
        $users = $this->jsonDataRepository->getUsers();
        include '../src/Views/admin/users.php';
    }

    public function importExcel()
    {
        // Logic to handle Excel file import
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
            // Process the uploaded Excel file
        }
        include '../src/Views/admin/import_excel.php';
    }
}