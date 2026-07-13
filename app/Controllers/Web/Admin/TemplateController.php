<?php

declare(strict_types=1);

namespace App\Controllers\Web\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\EmailTemplateRepositoryInterface;
use App\Services\Audit\AuditService;
use App\Support\Validation\Validator;

/**
 * Email template management. Templates use {{dotted.key}} placeholders rendered
 * at send time (see MailService / TemplateRenderer).
 */
final class TemplateController extends Controller
{
    public function __construct(
        private readonly EmailTemplateRepositoryInterface $templates,
        private readonly AuditService $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->view('admin.templates_index', [
            'title'     => 'Email templates',
            'active'    => 'settings',
            'templates' => $this->templates->all(),
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $template = $this->templates->find($id);
        if ($template === null) {
            throw HttpException::notFound('Template not found.');
        }
        return $this->view('admin.template_edit', [
            'title'    => 'Edit template',
            'active'   => 'settings',
            'template' => $template,
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $template = $this->templates->find($id);
        if ($template === null) {
            throw HttpException::notFound('Template not found.');
        }
        $validator = Validator::make(
            $request->only(['subject', 'body_html']),
            ['subject' => 'required|max:255', 'body_html' => 'required|min:10']
        );
        if ($validator->fails()) {
            return (new RedirectResponse('/admin/settings/templates/' . $id))->withErrors($validator->errors());
        }

        $this->templates->update($id, [
            'subject'   => (string) $request->input('subject'),
            'body_html' => (string) $request->input('body_html'),
            'body_text' => $request->input('body_text') !== '' ? (string) $request->input('body_text') : null,
            'is_active' => (string) $request->input('is_active', '1') === '1' ? 1 : 0,
        ]);
        $this->audit->log('settings.email_template_updated', 'EmailTemplate', $id);

        return (new RedirectResponse('/admin/settings/templates'))->with('status', 'Template saved.');
    }
}
