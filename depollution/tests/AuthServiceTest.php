<?php

use PHPUnit\Framework\TestCase;
use App\Services\AuthService;

class AuthServiceTest extends TestCase
{
    protected $authService;

    protected function setUp(): void
    {
        $this->authService = new AuthService();
    }

    public function testLoginWithValidCredentials()
    {
        $result = $this->authService->login('validUser', 'validPassword');
        $this->assertTrue($result);
    }

    public function testLoginWithInvalidCredentials()
    {
        $result = $this->authService->login('invalidUser', 'invalidPassword');
        $this->assertFalse($result);
    }

    public function testLogout()
    {
        $this->authService->login('validUser', 'validPassword');
        $this->authService->logout();
        $this->assertFalse($this->authService->isLoggedIn());
    }

    public function testIsLoggedIn()
    {
        $this->authService->login('validUser', 'validPassword');
        $this->assertTrue($this->authService->isLoggedIn());
    }
}