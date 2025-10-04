<?php

namespace App\Repositories;

use App\Models\Project;
use App\Repositories\JsonDataRepository;

class ProjectRepository extends JsonDataRepository
{
    protected $filePath = __DIR__ . '/../../data/projects.json';

    public function getAllProjects()
    {
        return $this->getData();
    }

    public function getProjectById($id)
    {
        $projects = $this->getData();
        foreach ($projects as $project) {
            if ($project['id'] == $id) {
                return $project;
            }
        }
        return null;
    }

    public function createProject(array $data)
    {
        $projects = $this->getData();
        $data['id'] = $this->generateId($projects);
        $projects[] = $data;
        $this->saveData($projects);
        return $data;
    }

    public function updateProject($id, array $data)
    {
        $projects = $this->getData();
        foreach ($projects as &$project) {
            if ($project['id'] == $id) {
                $project = array_merge($project, $data);
                $this->saveData($projects);
                return $project;
            }
        }
        return null;
    }

    public function deleteProject($id)
    {
        $projects = $this->getData();
        foreach ($projects as $key => $project) {
            if ($project['id'] == $id) {
                unset($projects[$key]);
                $this->saveData(array_values($projects));
                return true;
            }
        }
        return false;
    }

    private function generateId(array $projects)
    {
        return count($projects) > 0 ? max(array_column($projects, 'id')) + 1 : 1;
    }
}