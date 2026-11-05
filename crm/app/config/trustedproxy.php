<?php

/*
 * Which proxies may tell us the real client IP and scheme (X-Forwarded-For / -Proto).
 *
 * Default: none. Only REMOTE_ADDR counts, so nobody can fake their IP to dodge the login
 * and request limits (audit H2). Set TRUSTED_PROXIES to a comma list of the host's own proxy
 * IPs only (MilesWeb value and how to check it: crm/DEPLOY.md, "Trusted proxies").
 * Never use '*'.
 */
$proxies = trim((string) env('TRUSTED_PROXIES', ''));

return [
    // '*' / '**' would trust everyone again, so they are ignored.
    'proxies' => in_array($proxies, ['', '*', '**'], true) ? null : $proxies,
];
