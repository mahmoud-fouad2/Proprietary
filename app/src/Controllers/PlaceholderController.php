<?php
declare(strict_types=1);

namespace Zaco\Controllers;

use Zaco\Core\View;
use Zaco\Security\Auth;

final class PlaceholderController
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function show(string $title, string $subtitle): void
    {
        View::render('modules/placeholder', [
            'title' => $title,
            'subtitle' => $subtitle,
            'user' => $this->auth->user(),
        ]);
    }
}
