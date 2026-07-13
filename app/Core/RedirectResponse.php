<?php

declare(strict_types=1);

namespace App\Core;

final class RedirectResponse extends Response
{
    public function __construct(string $location, int $status = 302)
    {
        parent::__construct('', $status, ['Location' => $location]);
    }

    /**
     * Flash a message into the session, then redirect.
     */
    public function with(string $key, mixed $value): self
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Write to the "next request" flash bucket so it survives the redirect.
            $_SESSION['_flash_new'][$key] = $value;
        }
        return $this;
    }

    /** @param array<string,string[]> $errors */
    public function withErrors(array $errors): self
    {
        return $this->with('errors', $errors);
    }

    /** @param array<string,mixed> $input */
    public function withInput(array $input): self
    {
        return $this->with('old', $input);
    }
}
