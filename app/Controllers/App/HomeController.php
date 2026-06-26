<?php

declare(strict_types=1);

namespace App\Controllers\App;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Plan;

/**
 * Public landing page. Authenticated visitors are sent straight to their
 * dashboard.
 */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        if (auth()->check()) {
            return $this->redirect(url('dashboard'));
        }

        return $this->view('welcome', [
            'title' => config('app.name'),
            'plans' => Plan::active(),
        ]);
    }
}
