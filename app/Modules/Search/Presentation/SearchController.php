<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Search\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Search\Application\SearchService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/** Unified workspace search results page. */
final class SearchController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly SearchService $search,
    ) {
    }

    public function index(Request $request): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can('search.use')) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }

        $query = (string) $request->query('q', '');

        return $this->shell->render($this->context, 'search.index', [
            'query' => $query,
            'results' => $this->search->search((string) $this->context->workspaceId(), $query),
        ]);
    }
}
