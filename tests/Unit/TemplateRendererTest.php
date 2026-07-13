<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Mail\TemplateRenderer;
use PHPUnit\Framework\TestCase;

final class TemplateRendererTest extends TestCase
{
    public function test_replaces_dotted_placeholders(): void
    {
        $out = TemplateRenderer::render(
            'Hi {{customer.first_name}}, ticket {{ticket.reference}} received.',
            ['customer' => ['first_name' => 'Jane'], 'ticket' => ['reference' => 'NEXUS-1042']]
        );

        $this->assertSame('Hi Jane, ticket NEXUS-1042 received.', $out);
    }

    public function test_unknown_placeholders_render_empty(): void
    {
        $out = TemplateRenderer::render('Hello {{missing.key}}!', []);
        $this->assertSame('Hello !', $out);
    }

    public function test_tolerates_whitespace_in_braces(): void
    {
        $out = TemplateRenderer::render('{{ ticket.subject }}', ['ticket' => ['subject' => 'Login broken']]);
        $this->assertSame('Login broken', $out);
    }
}
