<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Application;

/**
 * Single source of truth for a workspace's White Label identity. Every brand
 * field is stored as a `brand.*` workspace preference (no schema change); this
 * service owns the field schema, reads/writes, and the derived CSS the shell
 * themes on. The Branding Center (edit form) and the WorkspaceShell (render)
 * both go through here, so there is one — and only one — brand definition
 * (docs/WHITE_LABEL.md).
 */
final class BrandingService
{
    /**
     * Brand field schema: key => {label, type, group, options?, help?}. The order
     * here is the order the Branding Center renders. `type` drives the input
     * widget; `select` types carry an `options` map (value => label).
     *
     * @var array<string, array{label:string, type:string, group:string, options?:array<string,string>, help?:string}>
     */
    private const FIELDS = [
        // Identity
        'company_name' => ['label' => 'Company name', 'type' => 'text', 'group' => 'Identity'],
        'legal_name' => ['label' => 'Legal name', 'type' => 'text', 'group' => 'Identity'],
        'description' => ['label' => 'Company description', 'type' => 'textarea', 'group' => 'Identity'],
        // Colours
        'color' => ['label' => 'Primary colour', 'type' => 'color', 'group' => 'Colours', 'help' => 'Drives the whole app accent.'],
        'secondary_color' => ['label' => 'Secondary colour', 'type' => 'color', 'group' => 'Colours'],
        'accent_color' => ['label' => 'Accent colour', 'type' => 'color', 'group' => 'Colours'],
        // Style
        'font_family' => ['label' => 'Typography', 'type' => 'select', 'group' => 'Style', 'options' => [
            'system' => 'System default',
            'inter' => 'Inter (modern sans)',
            'rounded' => 'Rounded (Trebuchet)',
            'serif' => 'Serif (Georgia)',
            'mono' => 'Monospace',
        ]],
        'radius' => ['label' => 'Corner radius (px)', 'type' => 'number', 'group' => 'Style'],
        // Career page
        'career_hero_title' => ['label' => 'Career page hero title', 'type' => 'text', 'group' => 'Career page'],
        'career_hero_subtitle' => ['label' => 'Career page hero subtitle', 'type' => 'text', 'group' => 'Career page'],
        // Footer & social
        'footer_text' => ['label' => 'Footer text', 'type' => 'textarea', 'group' => 'Footer & social'],
        'social_website' => ['label' => 'Website URL', 'type' => 'url', 'group' => 'Footer & social'],
        'social_linkedin' => ['label' => 'LinkedIn URL', 'type' => 'url', 'group' => 'Footer & social'],
        'social_twitter' => ['label' => 'X / Twitter URL', 'type' => 'url', 'group' => 'Footer & social'],
    ];

    /** CSS font stacks per `font_family` choice. System-safe + the layout's Inter. */
    private const FONT_STACKS = [
        'inter' => "'Inter', system-ui, -apple-system, sans-serif",
        'rounded' => "'Trebuchet MS', 'Segoe UI', system-ui, sans-serif",
        'serif' => "Georgia, 'Times New Roman', serif",
        'mono' => "ui-monospace, 'Cascadia Code', 'Courier New', monospace",
    ];

    public function __construct(private readonly WorkspacePreferences $preferences)
    {
    }

    /**
     * The brand field schema (for the edit form).
     *
     * @return array<string, array{label:string, type:string, group:string, options?:array<string,string>, help?:string}>
     */
    public function fields(): array
    {
        return self::FIELDS;
    }

    /**
     * Current stored value for every brand field.
     *
     * @return array<string, string>
     */
    public function values(string $workspaceId): array
    {
        $out = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $out[$key] = (string) $this->preferences->get($workspaceId, 'brand.' . $key, '');
        }

        return $out;
    }

    /**
     * Persist every brand field. `$input` resolves a field key to its submitted
     * value (typically `fn ($k) => $request->input($k, '')`).
     */
    public function save(string $workspaceId, callable $input): void
    {
        foreach (array_keys(self::FIELDS) as $key) {
            $this->preferences->set($workspaceId, 'brand.' . $key, trim((string) $input($key)));
        }
    }

    /** A single field's raw value. */
    public function value(string $workspaceId, string $key): string
    {
        return (string) $this->preferences->get($workspaceId, 'brand.' . $key, '');
    }

    /**
     * Inline `style` for #app-shell: the full --brand-* colour scale (which the
     * app's Tailwind maps every indigo-* utility onto) plus a direct
     * `font-family` so the chosen typography cascades to the whole subtree. Empty
     * when nothing is configured, so the default theme shows through.
     */
    public function shellStyle(string $workspaceId): string
    {
        $parts = [];

        $colour = BrandPalette::styleVars($this->value($workspaceId, 'color'));
        if ($colour !== '') {
            $parts[] = rtrim($colour, '; ');
        }

        $font = $this->fontStack($this->value($workspaceId, 'font_family'));
        if ($font !== '') {
            $parts[] = 'font-family: ' . $font;
        }

        return $parts === [] ? '' : implode('; ', $parts) . ';';
    }

    /** Logo asset URL (uploaded via Settings), or null when none is set. */
    public function logoUrl(string $workspaceId): ?string
    {
        return $this->value($workspaceId, 'logo_file_id') !== '' ? '/settings/logo' : null;
    }

    /** Resolve a `font_family` choice to a CSS font stack ('' = use the default). */
    private function fontStack(string $choice): string
    {
        return self::FONT_STACKS[$choice] ?? '';
    }
}
