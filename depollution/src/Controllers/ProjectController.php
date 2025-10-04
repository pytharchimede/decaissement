<?php

namespace App\Controllers;

use App\Repositories\ProjectRepository;
use App\Services\ProjectService;

class ProjectController
{
    private $projectRepository;
    private $projectService;

    public function __construct()
    {
        $this->projectRepository = new ProjectRepository();
        $this->projectService = new ProjectService($this->projectRepository);
    }

    public function index()
    {
        $projects = $this->projectService->getAllProjects();
        require_once __DIR__ . '/../Views/admin/projects.php';
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $this->projectService->createProject($data);
            header('Location: /admin/projects.php');
            exit;
        }
        require_once __DIR__ . '/../Views/forms/project_form.php';
    }

    public function edit($id)
    {
        $project = $this->projectService->getProjectById($id);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $this->projectService->updateProject($id, $data);
            header('Location: /admin/projects.php');
            exit;
        }
        require_once __DIR__ . '/../Views/forms/project_form.php';
    }

    public function delete($id)
    {
        $this->projectService->deleteProject($id);
        header('Location: /admin/projects.php');
        exit;
    }
}