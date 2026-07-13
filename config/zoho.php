<?php

declare(strict_types=1);

use App\Core\Env;

/**
 * Zoho Books integration config. Client credentials come from .env; the OAuth
 * refresh token and organization id are captured at connect time and stored
 * (encrypted) in the zoho_connections table.
 *
 * Region determines the API + accounts domains (com, eu, in, au, jp, ca).
 */
$region = (string) Env::get('ZOHO_REGION', 'com');

return [
    'client_id'     => Env::get('ZOHO_CLIENT_ID', ''),
    'client_secret' => Env::get('ZOHO_CLIENT_SECRET', ''),
    'region'        => $region,
    'accounts_url'  => 'https://accounts.zoho.' . $region,
    'api_url'       => 'https://www.zohoapis.' . $region . '/books/v3',
    'scope'         => 'ZohoBooks.fullaccess.READ',
    'redirect_uri'  => rtrim((string) Env::get('APP_URL', ''), '/') . '/admin/settings/zoho/callback',
];
