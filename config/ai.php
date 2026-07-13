<?php

declare(strict_types=1);

use App\Core\Env;

/**
 * AI provider configuration. The provider is bound in config/services.php; this
 * file is the seam where a real driver is selected. To activate a provider:
 *   1. add a driver class implementing App\Integrations\Ai\AiProviderInterface
 *   2. switch the binding in config/services.php based on this 'provider' value
 *   3. set AI_PROVIDER and AI_API_KEY in .env
 */
return [
    'provider' => Env::get('AI_PROVIDER', 'null'),   // null | anthropic | openai
    'api_key'  => Env::get('AI_API_KEY', ''),
    'model'    => Env::get('AI_MODEL', ''),
];
