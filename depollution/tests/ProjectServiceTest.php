<?php

use PHPUnit\Framework\TestCase;
use App\Services\ProjectService;
use App\Repositories\ProjectRepository;

class ProjectServiceTest extends TestCase
{
    private $projectService;
    private $projectRepository;

    protected function setUp(): void
    {
        $this->projectRepository = new ProjectRepository();
        $this->projectService = new ProjectService($this->projectRepository);
    }

    public function testCreateProject()
    {
        $data = [
            'name' => 'Test Project',
            'description' => 'This is a test project.',
            'site_id' => 1,
        ];

        $result = $this->projectService->createProject($data);
        $this->assertTrue($result);
    }

    public function testGetAllProjects()
    {
        $projects = $this->projectService->getAllProjects();
        $this->assertIsArray($projects);
    }

    public function testUpdateProject()
    {
        $data = [
            'id' => 1,
            'name' => 'Updated Project',
            'description' => 'This is an updated project.',
            'site_id' => 1,
        ];

        $result = $this->projectService->updateProject($data);
        $this->assertTrue($result);
    }

    public function testDeleteProject()
    {
        $result = $this->projectService->deleteProject(1);
        $this->assertTrue($result);
    }
}