<?php

declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\System\EnvFile;

/**
 * Environment Editor — edit safe runtime settings from the dashboard instead of
 * hand-editing .env over SSH (Setup & Installer Bible, "Environment Editor").
 *
 * Only a curated whitelist of non-destructive keys is editable. Secrets such as
 * APP_KEY and the DB_* credentials are never shown or accepted here: they are
 * displayed masked and read-only, and remain the installer's responsibility so
 * a stray dashboard edit can't lock the application or its database out.
 */
final class EnvironmentController extends Controller
{
    /**
     * Editable keys grouped for the form. Order here is presentation only.
     *
     * @var array<string, string[]>
     */
    private const GROUPS = [
        'app' => [
            'APP_NAME',
            'APP_URL',
            'APP_DEBUG',
            'APP_LOCALE',
            'APP_FALLBACK_LOCALE',
            'APP_TIMEZONE',
            'APP_CURRENCY',
            'ASSET_VERSION',
        ],
        'mail' => [
            'MAIL_ENABLED',
            'MAIL_FROM_ADDRESS',
            'MAIL_FROM_NAME',
        ],
        'security' => [
            'SESSION_SECURE',
        ],
    ];

    /** Keys saved as the literal strings 'true' / 'false'. */
    private const BOOLEANS = ['APP_DEBUG', 'MAIL_ENABLED', 'SESSION_SECURE'];

    /** Secret keys: shown masked + read-only, never written from here. */
    private const SECRETS = ['APP_KEY', 'DB_PASSWORD'];

    /** Additional secret-ish keys surfaced (masked) for transparency. */
    private const SECRET_DISPLAY = ['APP_KEY', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];

    public function index(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        $env = new EnvFile();
        $current = $env->all();

        $values = [];
        foreach ($this->editableKeys() as $key) {
            $values[$key] = (string) ($current[$key] ?? '');
        }

        // Build masked presence info for the read-only secrets panel.
        $secrets = [];
        foreach (self::SECRET_DISPLAY as $key) {
            $present = array_key_exists($key, $current) && $current[$key] !== '';
            $secrets[$key] = [
                'present' => $present,
                'masked'  => $present ? '•••• set' : 'not set',
            ];
        }

        return $this->view('system.environment', [
            'title'       => 'Environment Editor',
            'groups'      => self::GROUPS,
            'booleans'    => self::BOOLEANS,
            'values'      => $values,
            'secrets'     => $secrets,
            'envExists'   => $env->exists(),
            'envPath'     => $env->path(),
            'locales'     => ['en' => 'English', 'ar' => 'العربية (Arabic)'],
        ]);
    }

    public function update(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        // Validate the text/select fields. Booleans are read from the request
        // directly below because an unchecked checkbox submits nothing and the
        // validator would otherwise drop the key entirely.
        $this->validate($request, [
            'APP_NAME'            => 'required|max:190',
            'APP_URL'             => 'nullable|url|max:190',
            'APP_LOCALE'          => 'required|in:en,ar',
            'APP_FALLBACK_LOCALE' => 'required|in:en,ar',
            'APP_TIMEZONE'        => 'required|max:64',
            'APP_CURRENCY'        => 'required|alpha|min:3|max:3',
            'ASSET_VERSION'       => 'nullable|max:40',
            'MAIL_FROM_ADDRESS'   => 'nullable|email|max:190',
            'MAIL_FROM_NAME'      => 'nullable|max:190',
        ], [
            'APP_CURRENCY.alpha' => 'The currency must be a 3-letter code (e.g. SAR, USD).',
            'APP_CURRENCY.min'   => 'The currency must be a 3-letter code (e.g. SAR, USD).',
            'APP_CURRENCY.max'   => 'The currency must be a 3-letter code (e.g. SAR, USD).',
        ]);

        // Validate the timezone against PHP's IANA database rather than a regex.
        $timezone = trim((string) $request->input('APP_TIMEZONE', ''));
        if ($timezone !== '' && ! in_array($timezone, timezone_identifiers_list(), true)) {
            $this->fail('APP_TIMEZONE', 'The timezone must be a valid IANA timezone (e.g. Asia/Riyadh).');
        }

        $payload = [];

        // Non-boolean whitelisted keys: take submitted value, trim, never accept
        // a key that is not part of our whitelist.
        foreach ($this->editableKeys() as $key) {
            if (in_array($key, self::BOOLEANS, true)) {
                continue;
            }
            $payload[$key] = trim((string) $request->input($key, ''));
        }

        // Normalise a few values for safety/consistency.
        if (isset($payload['APP_URL'])) {
            $payload['APP_URL'] = rtrim($payload['APP_URL'], '/');
        }
        if (isset($payload['APP_CURRENCY'])) {
            $payload['APP_CURRENCY'] = strtoupper($payload['APP_CURRENCY']);
        }

        // Booleans -> literal 'true' / 'false'.
        foreach (self::BOOLEANS as $key) {
            $payload[$key] = $request->boolean($key) ? 'true' : 'false';
        }

        (new EnvFile())->set($payload);

        $this->withSuccess('Environment updated. Some changes take effect on the next request.');

        return $this->redirect(url('system/environment'));
    }

    /**
     * Flatten the grouped whitelist into a single ordered list of editable keys.
     *
     * @return string[]
     */
    private function editableKeys(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }
}
