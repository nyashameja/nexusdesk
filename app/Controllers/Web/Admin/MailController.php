<?php

declare(strict_types=1);

namespace App\Controllers\Web\Admin;

use App\Core\Controller;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\Auth\AuthContext;
use App\Services\Mail\MailService;
use App\Services\Settings\Settings;
use App\Support\Validation\Validator;

/**
 * Mail settings: the From identity used by templated emails, plus a test-send.
 * SMTP connection credentials live in .env (set by the installer) so secrets
 * stay out of the database.
 */
final class MailController extends Controller
{
    public function __construct(
        private readonly SettingRepositoryInterface $settings,
        private readonly MailService $mail,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->view('admin.settings_mail', [
            'title'  => 'Email settings',
            'active' => 'settings',
        ]);
    }

    public function update(Request $request): Response
    {
        $validator = Validator::make(
            $request->only(['from_name', 'from_email']),
            ['from_name' => 'required|max:120', 'from_email' => 'required|email']
        );
        if ($validator->fails()) {
            return (new RedirectResponse('/admin/settings/mail'))->withErrors($validator->errors());
        }

        $this->settings->setMany([
            'mail.from_name'  => (string) $request->input('from_name'),
            'mail.from_email' => (string) $request->input('from_email'),
        ]);
        Settings::flush();

        return (new RedirectResponse('/admin/settings/mail'))->with('status', 'Mail settings saved.');
    }

    public function sendTest(Request $request): Response
    {
        $user = AuthContext::user();
        if ($user === null) {
            return new RedirectResponse('/admin/settings/mail');
        }

        $ok = $this->mail->send(
            $user->email,
            $user->fullName(),
            'NexusDesk test email',
            '<p>This is a test email from NexusDesk. If you received it, your SMTP settings work. ✅</p>'
        );

        $message = $ok
            ? 'Test email sent to ' . $user->email . '.'
            : 'Could not send the test email — check SMTP credentials in .env and the mail log.';

        return (new RedirectResponse('/admin/settings/mail'))->with('status', $message);
    }
}
