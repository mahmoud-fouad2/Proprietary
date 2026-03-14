<?php
declare(strict_types=1);

namespace Zaco\Controllers;

use Zaco\Core\Http;
use Zaco\Core\View;
use Zaco\Security\Auth;
use Zaco\Security\Csrf;

final class AuthController
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function loginForm(): void
    {
        if ($this->auth->usersCount() === 0) {
            Http::redirect('/setup');
        }
        if ($this->auth->check()) {
            Http::redirect('/');
        }

        View::render('auth/login', [
            'csrf' => Csrf::token(),
            'error' => null,
        ]);
    }

    public function loginSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $ok = $this->auth->login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
        if (!$ok) {
            View::render('auth/login', [
                'csrf' => Csrf::token(),
                'error' => 'البريد أو كلمة المرور غير صحيحة',
            ]);
            return;
        }

        Http::redirect('/');
    }

    public function logout(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }
        $this->auth->logout();
        Http::redirect('/login');
    }

    public function setupForm(): void
    {
        if ($this->auth->usersCount() > 0) {
            Http::redirect('/login');
        }

        View::render('auth/setup', [
            'csrf' => Csrf::token(),
            'error' => null,
        ]);
    }

    public function setupSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        try {
            $this->auth->createInitialSuperAdmin((string)($_POST['name'] ?? ''), (string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
        } catch (\Throwable $e) {
            View::render('auth/setup', [
                'csrf' => Csrf::token(),
                'error' => 'تعذر إنشاء حساب المدير: ' . $e->getMessage(),
            ]);
            return;
        }

        Http::redirect('/login');
    }

    public function forbidden(): void
    {
        http_response_code(403);
        View::render('errors/forbidden', []);
    }
}
