<?php

declare(strict_types=1);

namespace App\Controllers\App;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Design System catalog (docs/30) — the living style guide. Renders every Design
 * System component with its variants/states so the team has one canonical reference
 * and can eyeball light/dark, RTL/LTR and accessibility in one place. Read-only; no
 * tenant data, gated by dashboard.view.
 */
final class DesignSystemController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('app.design', ['title' => 'Design System']);
    }
}
