<?php

declare(strict_types=1);

namespace App\Controllers\App;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Tenancy\CompanyService;

/**
 * Self-service company management: any user can create a company (becoming its
 * owner), switch between the companies they belong to, or pick one when none is
 * active.
 */
final class CompanyController extends Controller
{
    public function create(): Response
    {
        return $this->view('app.companies.create', ['title' => 'Create company']);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|min:2|max:150',
        ]);

        $company = (new CompanyService())->create(auth()->user(), $data['name']);
        tenant()->setTenant($company);

        $this->withSuccess('Company "' . $company->name . '" created. You are the owner.');

        return $this->redirect(url('dashboard'));
    }

    public function select(): Response
    {
        $companies = auth()->user()->companies();

        if ($companies === []) {
            return $this->redirect(url('companies/create'));
        }

        return $this->view('app.companies.select', [
            'title'     => 'Choose a company',
            'companies' => $companies,
        ]);
    }

    public function switch(Request $request): Response
    {
        $data = $this->validate($request, ['company_id' => 'required|integer']);
        $companyId = (int) $data['company_id'];

        if (! tenant()->userBelongsTo(auth()->user(), $companyId)) {
            abort(403, 'You are not a member of that company.');
        }

        tenant()->setById($companyId);

        return $this->redirect(url('dashboard'));
    }
}
