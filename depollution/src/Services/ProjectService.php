<?php

namespace App\Services;

use App\Repositories\ProjectRepository;

class ProjectService
{
    private $projectRepository;

    public function __construct(ProjectRepository $projectRepository)
    {
        $this->projectRepository = $projectRepository;
    }

    public function createProject(array $data)
    {
        // Validate and create a new project
        return $this->projectRepository->create($data);
    }

    public function updateProject(int $id, array $data)
    {
        // Validate and update the existing project
        return $this->projectRepository->update($id, $data);
    }

    public function deleteProject(int $id)
    {
        // Delete the project by ID
        return $this->projectRepository->delete($id);
    }

    public function getAllProjects()
    {
        // Retrieve all projects
        return $this->projectRepository->findAll();
    }

    public function getProjectById(int $id)
    {
        // Retrieve a project by ID
        return $this->projectRepository->findById($id);
    }
}