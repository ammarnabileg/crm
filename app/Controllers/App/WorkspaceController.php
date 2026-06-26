<?php

declare(strict_types=1);

namespace App\Controllers\App;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Tenancy\WorkspaceService;

/**
 * Self-service workspace management: any user can create a workspace (becoming its
 * owner), switch between the workspaces they belong to, or pick one when none is
 * active.
 */
final class WorkspaceController extends Controller
{
    public function create(): Response
    {
        return $this->view('app.workspaces.create', ['title' => 'Create workspace']);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|min:2|max:150',
        ]);

        $workspace = (new WorkspaceService())->create(auth()->user(), $data['name']);
        tenant()->setTenant($workspace);

        $this->withSuccess('Workspace "' . $workspace->name . '" created. You are the owner.');

        return $this->redirect(url('dashboard'));
    }

    public function select(): Response
    {
        $workspaces = auth()->user()->workspaces();

        if ($workspaces === []) {
            return $this->redirect(url('workspaces/create'));
        }

        return $this->view('app.workspaces.select', [
            'title'     => 'Choose a workspace',
            'workspaces' => $workspaces,
        ]);
    }

    public function switch(Request $request): Response
    {
        $data = $this->validate($request, ['workspace_id' => 'required|integer']);
        $workspaceId = (int) $data['workspace_id'];

        if (! tenant()->userBelongsTo(auth()->user(), $workspaceId)) {
            abort(403, 'You are not a member of that workspace.');
        }

        tenant()->setById($workspaceId);

        return $this->redirect(url('dashboard'));
    }
}
