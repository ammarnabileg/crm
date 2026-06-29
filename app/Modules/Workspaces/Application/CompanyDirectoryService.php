<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Application;

use HaHireAI\Core\Contracts\CompanyDirectory;
use HaHireAI\Core\Database\Connection;

/**
 * Resolves a workspace's public company profile by slug for the public careers
 * page. Implements the Core CompanyDirectory contract so Recruitment can render
 * a tenant's brand without reaching into the Workspaces tables (ARCHITECTURE §4).
 */
final class CompanyDirectoryService implements CompanyDirectory
{
    public function __construct(
        private readonly Connection $connection,
        private readonly WorkspacePreferences $prefs,
    ) {
    }

    public function findBySlug(string $slug): ?array
    {
        $ws = $this->connection->selectOne(
            "SELECT id, name, slug FROM workspaces WHERE slug = ? AND status = 'active' AND deleted_at IS NULL",
            [$slug],
        );
        if ($ws === null) {
            return null;
        }

        $id = (string) $ws['id'];
        $pref = fn (string $key): string => trim((string) $this->prefs->get($id, $key, ''));
        $logoFileId = $pref('brand.logo_file_id');

        return [
            'id' => $id,
            'name' => (string) $ws['name'],
            'slug' => (string) $ws['slug'],
            'industry' => $pref('company.industry'),
            'website' => $pref('company.website'),
            'contact_email' => $pref('company.contact_email'),
            'contact_phone' => $pref('company.contact_phone'),
            'about' => $pref('company.about'),
            'tagline' => $pref('brand.tagline'),
            'logo_text' => $pref('brand.logo_text'),
            'brand_color' => $pref('brand.color'),
            'logo_file_id' => $logoFileId !== '' ? $logoFileId : null,
            'legal_name' => $pref('legal.company_legal_name'),
            'terms_url' => $pref('legal.terms_url'),
            'privacy_url' => $pref('legal.privacy_url'),
            'address' => $pref('legal.address'),
        ];
    }
}
