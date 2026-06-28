<?php
/** @var string $content */
/** @var array<string,mixed> $user */
/** @var list<array{label:string,route:string,permission:string}> $sidebar */
/** @var string|null $workspaceName */

$currentPath = '/' . trim((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'), '/');

// Active nav = the longest route that prefixes the current path.
$activeRoute = '';
foreach ($sidebar as $it) {
    $r = (string) $it['route'];
    $match = $currentPath === $r || ($r !== '/' && str_starts_with($currentPath, $r . '/'));
    if ($match && strlen($r) > strlen($activeRoute)) {
        $activeRoute = $r;
    }
}
$pageTitle = 'HaHireAI';
foreach ($sidebar as $it) {
    if ((string) $it['route'] === $activeRoute) {
        $pageTitle = (string) $it['label'];
    }
}

/** A unified line icon per nav item (Heroicons outline). */
$navIcon = static function (string $label): string {
    $paths = [
        'Dashboard' => '<path d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 8.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>',
        'My Workspaces' => '<path d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21"/>',
        'Plan' => '<path d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>',
        'Billing' => '<path d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>',
        'Jobs' => '<path d="M20.25 14.15v4.073a2.25 2.25 0 01-1.632 2.163l-1.32.377a9.04 9.04 0 01-2.496.35H6.75a2.25 2.25 0 01-2.25-2.25v-4.073M20.25 14.15A2.25 2.25 0 0021 12.265V8.706a2.25 2.25 0 00-1.591-2.153l-6-1.8a2.25 2.25 0 00-1.318 0l-6 1.8A2.25 2.25 0 003 8.706v3.559c0 .98.626 1.851 1.5 2.164m15.75-.279a48.07 48.07 0 00-7.5-.529c-2.553 0-5.06.18-7.5.529m0 0V6.75"/>',
        'Candidates' => '<path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>',
        'Members' => '<path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>',
        'Users' => '<path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>',
        'Talent Pool' => '<path d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>',
        'Pipeline' => '<path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>',
        'Tasks' => '<path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'AI Interviews' => '<path d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>',
        'Human Interviews' => '<path d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>',
        'Avatars' => '<path d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'Offers' => '<path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>',
        'Reports' => '<path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>',
        'Roles' => '<path d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>',
        'Notifications' => '<path d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>',
        'Activity' => '<path d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'Audit Logs' => '<path d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'AI' => '<path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>',
        'AI Providers' => '<path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>',
        'Workflows' => '<path d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>',
        'Developer' => '<path d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5"/>',
        'Search' => '<path d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>',
        'Files' => '<path d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>',
        'Settings' => '<path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'Platform Settings' => '<path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/>',
        'Maintenance' => '<path d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z"/>',
        'Subscriptions' => '<path d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>',
        'Payments' => '<path d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>',
        'Plans' => '<path d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 8.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>',
        'Workspaces' => '<path d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21"/>',
        'Overview' => '<path d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>',
        'Diagnostics' => '<path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>',
        'Candidate Portal' => '<path d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>',
        'Available Jobs' => '<path d="M20.25 14.15v4.073a2.25 2.25 0 01-1.632 2.163l-1.32.377a9.04 9.04 0 01-2.496.35H6.75a2.25 2.25 0 01-2.25-2.25v-4.073M20.25 14.15A2.25 2.25 0 0021 12.265V8.706a2.25 2.25 0 00-1.591-2.153l-6-1.8a2.25 2.25 0 00-1.318 0l-6 1.8A2.25 2.25 0 003 8.706v3.559c0 .98.626 1.851 1.5 2.164m15.75-.279a48.07 48.07 0 00-7.5-.529c-2.553 0-5.06.18-7.5.529m0 0V6.75"/>',
        'My Applications' => '<path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>',
        'My Profile' => '<path d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/>',
    ];
    $d = $paths[$label] ?? '<path d="M6.429 9.75L2.25 12l4.179 2.25m0-4.5l5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0l4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0l-5.571 3-5.571-3"/>';

    return '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">' . $d . '</svg>';
};

$initial = strtoupper(substr((string) ($user['name'] ?? '?'), 0, 1));

/** @var list<array<string,mixed>> $workspaces */
/** @var string|null $currentWorkspaceId */
/** @var list<array<string,mixed>> $notifications */
/** @var int $unreadCount */
$workspaces ??= [];
$currentWorkspaceId ??= null;
$notifications ??= [];
$unreadCount ??= 0;
$currentWsName = $workspaceName ?? 'Workspace';
foreach ($workspaces as $w) {
    if ((string) $w['id'] === (string) $currentWorkspaceId) {
        $currentWsName = (string) $w['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e(config('app.locale', 'en')) ?>" dir="<?= config('app.locale') === 'ar' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title><?= e($pageTitle) ?> · HaHireAI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <?php if (is_file(dirname(__DIR__, 3) . '/public/assets/tailwind.css')): ?>
        <link rel="stylesheet" href="/assets/tailwind.css">
    <?php else: ?>
        <script src="https://cdn.tailwindcss.com"></script>
    <?php endif; ?>
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased">
<div class="flex min-h-screen">
    <!-- Single dynamic sidebar — generated from permissions, not roles -->
    <aside class="hidden w-64 shrink-0 flex-col border-e border-slate-200 bg-white md:flex">
        <div class="flex h-16 items-center gap-2.5 px-5">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600 text-sm font-bold text-white shadow-sm">Ha</span>
            <span class="text-lg font-bold tracking-tight text-slate-900">HaHire<span class="text-indigo-600">AI</span></span>
        </div>
        <?php if ($workspaceName !== null): ?>
            <div class="mx-3 mb-1 truncate rounded-lg bg-slate-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-400" title="<?= e($workspaceName) ?>"><?= e($workspaceName) ?></div>
        <?php endif; ?>
        <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-2">
            <?php foreach ($sidebar as $item): ?>
                <?php $active = (string) $item['route'] === $activeRoute; ?>
                <a href="<?= e($item['route']) ?>" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium <?= $active ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
                    <span class="<?= $active ? 'text-indigo-600' : 'text-slate-400' ?>"><?= $navIcon((string) $item['label']) ?></span>
                    <?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>
            <?php if ($sidebar === []): ?>
                <p class="px-3 py-2 text-sm text-slate-400">No modules available yet.</p>
            <?php endif; ?>
        </nav>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-6">
            <?php if ($workspaces !== []): ?>
                <details class="group relative">
                    <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-xs font-semibold text-indigo-700"><?= e(strtoupper(substr($currentWsName, 0, 1))) ?></span>
                        <span class="max-w-[12rem] truncate text-sm font-semibold text-slate-900"><?= e($currentWsName) ?></span>
                        <svg class="h-4 w-4 text-slate-400 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                    </summary>
                    <div class="absolute start-0 z-30 mt-2 w-64 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg">
                        <div class="px-2 py-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Workspaces</div>
                        <?php foreach ($workspaces as $w): ?>
                            <?php $isCurrent = (string) $w['id'] === (string) $currentWorkspaceId; $wIni = strtoupper(substr((string) $w['name'], 0, 1)); ?>
                            <?php if ($isCurrent): ?>
                                <div class="flex items-center gap-2 rounded-lg bg-indigo-50 px-2 py-2">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-xs font-semibold text-indigo-700"><?= e($wIni) ?></span>
                                    <span class="truncate text-sm font-semibold text-indigo-700"><?= e($w['name']) ?></span>
                                </div>
                            <?php else: ?>
                                <form method="post" action="/workspaces/<?= e($w['id']) ?>/switch">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left hover:bg-slate-50">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-xs font-semibold text-slate-500"><?= e($wIni) ?></span>
                                        <span class="truncate text-sm font-medium text-slate-700"><?= e($w['name']) ?></span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <div class="my-1 border-t border-slate-100"></div>
                        <a href="/workspaces/create" class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm font-medium text-indigo-600 hover:bg-slate-50">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg border border-dashed border-slate-300 text-slate-400">+</span>
                            New workspace
                        </a>
                    </div>
                </details>
            <?php else: ?>
                <h1 class="text-lg font-semibold text-slate-900"><?= e($pageTitle) ?></h1>
            <?php endif; ?>
            <div class="flex items-center gap-1.5">
                <!-- Notifications bell -->
                <details class="group relative">
                    <summary class="relative flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                        <?php if ($unreadCount > 0): ?><span class="absolute right-1.5 top-1.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white"><?= e($unreadCount > 9 ? '9+' : $unreadCount) ?></span><?php endif; ?>
                    </summary>
                    <div class="absolute end-0 z-30 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                            <span class="text-sm font-semibold text-slate-900">Notifications</span>
                            <?php if ($notifications !== []): ?>
                                <form method="post" action="/notifications/read"><?= csrf_field() ?><button class="text-xs font-medium text-indigo-600 hover:text-indigo-700">Mark all read</button></form>
                            <?php endif; ?>
                        </div>
                        <?php if ($notifications === []): ?>
                            <div class="px-4 py-8 text-center text-sm text-slate-400">You're all caught up.</div>
                        <?php else: ?>
                            <ul class="max-h-80 divide-y divide-slate-100 overflow-y-auto">
                                <?php foreach ($notifications as $n): ?>
                                    <?php $unread = empty($n['read_at']); ?>
                                    <li>
                                        <a href="<?= e(! empty($n['link']) ? $n['link'] : '/notifications') ?>" class="flex gap-2 px-4 py-3 hover:bg-slate-50">
                                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full <?= $unread ? 'bg-indigo-500' : 'bg-transparent' ?>"></span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm <?= $unread ? 'font-semibold text-slate-900' : 'font-medium text-slate-600' ?>"><?= e($n['title']) ?></span>
                                                <?php if (! empty($n['body'])): ?><span class="block truncate text-xs text-slate-400"><?= e($n['body']) ?></span><?php endif; ?>
                                                <span class="text-xs text-slate-300"><?= e(time_ago($n['created_at'] ?? null)) ?></span>
                                            </span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <a href="/notifications" class="block border-t border-slate-100 px-4 py-3 text-center text-sm font-medium text-indigo-600 hover:bg-slate-50">View all</a>
                    </div>
                </details>

                <!-- User menu -->
                <details class="group relative">
                    <summary class="flex cursor-pointer list-none items-center gap-1 rounded-lg p-1 hover:bg-slate-100">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700"><?= e($initial) ?></span>
                        <svg class="h-4 w-4 text-slate-400 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                    </summary>
                    <div class="absolute end-0 z-30 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg">
                        <div class="border-b border-slate-100 px-3 py-2">
                            <div class="truncate text-sm font-semibold text-slate-900"><?= e($user['name'] ?? '') ?></div>
                            <?php if (! empty($user['email'])): ?><div class="truncate text-xs text-slate-400"><?= e($user['email']) ?></div><?php endif; ?>
                        </div>
                        <a href="/account/profile" class="mt-1 flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                            Edit profile
                        </a>
                        <form method="post" action="/logout">
                            <?= csrf_field() ?>
                            <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-rose-600 hover:bg-rose-50">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
                                Sign out
                            </button>
                        </form>
                    </div>
                </details>
            </div>
        </header>
        <main class="flex-1 p-6 lg:p-8"><?= $content ?></main>
    </div>
</div>
</body>
</html>
