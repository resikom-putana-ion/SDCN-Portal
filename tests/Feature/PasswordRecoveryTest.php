<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\Support\CloudTestCase;

class PasswordRecoveryTest extends CloudTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => 'https://sekolah.example.test',
            'mail.default' => 'smtp',
            // Exercise notification rendering/delivery without any external connection.
            'mail.mailers.smtp.transport' => 'array',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.username' => 'sender@example.test',
            'mail.mailers.smtp.password' => 'test-credential',
            'mail.from.address' => 'sender@example.test',
        ]);
    }

    public function test_real_notification_renders_indonesian_email_with_trusted_url(): void
    {
        $this->withServerVariables(['HTTP_HOST' => 'untrusted.example.test'])
            ->post('/forgot-password', ['email' => strtoupper($this->admin()->email)])
            ->assertRedirect('/forgot-password')->assertSessionHas('sent', true);

        $messages = Mail::mailer('smtp')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $email = $messages->first()->getOriginalMessage();
        $this->assertSame($this->admin()->email, $email->getTo()[0]->getAddress());
        $this->assertSame('sender@example.test', $email->getFrom()[0]->getAddress());
        $this->assertStringContainsString('Atur ulang kata sandi', $email->getSubject());
        $this->assertStringContainsString('Buat kata sandi baru', $email->getHtmlBody());
        $this->assertStringContainsString('60 menit', $email->getTextBody());
        $this->assertStringContainsString('https://sekolah.example.test/reset-password/', $email->getHtmlBody());
        $this->assertStringNotContainsString('untrusted.example.test', $email->getHtmlBody());
    }

    public function test_unconfigured_mail_does_not_claim_success_or_create_a_token(): void
    {
        Notification::fake();
        config(['mail.default' => 'log']);
        $this->from('/forgot-password')->post('/forgot-password', ['email' => $this->admin()->email])
            ->assertRedirect('/forgot-password')->assertSessionHasErrors('email')->assertSessionMissing('sent');
        Notification::assertNothingSent();
        $this->assertFalse(Password::getRepository()->recentlyCreatedToken($this->admin()));
    }

    public function test_missing_gmail_credentials_are_reported_without_sending(): void
    {
        Notification::fake();
        config(['mail.mailers.smtp.password' => null]);
        $this->post('/forgot-password', ['email' => $this->admin()->email])->assertSessionHasErrors('email')->assertSessionMissing('sent');
        Notification::assertNothingSent();
    }

    public function test_delivery_failure_removes_token_and_allows_retry(): void
    {
        $dispatcher = Notification::getFacadeRoot();
        Notification::shouldReceive('send')->once()->andThrow(new TransportException('Sensitive SMTP diagnostic'));
        $this->from('/forgot-password')->post('/forgot-password', ['email' => $this->admin()->email])
            ->assertSessionHasErrors('email')->assertSessionMissing('sent');
        $this->assertFalse(Password::getRepository()->recentlyCreatedToken($this->admin()));
        $this->get('/forgot-password')->assertSee('Email pemulihan belum dapat dikirim')->assertDontSee('Sensitive SMTP diagnostic');

        Notification::swap($dispatcher);
        $this->post('/forgot-password', ['email' => $this->admin()->email])->assertSessionHas('sent', true);
        $this->assertCount(1, Mail::mailer('smtp')->getSymfonyTransport()->messages());
    }

    public function test_unknown_and_inactive_accounts_have_same_public_response_and_no_email(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $row = $this->store->get('admins', $admin->id);
        $this->store->put('admins', $admin->id, [...$row, 'active' => false]);
        foreach (['unknown@example.test', $admin->email] as $email) {
            $this->post('/forgot-password', compact('email'))->assertRedirect('/forgot-password')->assertSessionHas('sent', true)->assertSessionHasNoErrors();
        }
        Notification::assertNothingSent();
    }

    public function test_repeated_request_does_not_send_another_email_and_form_is_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', ['email' => $this->admin()->email])->assertSessionHas('sent', true);
        }
        $this->assertCount(1, Mail::mailer('smtp')->getSymfonyTransport()->messages());
        $this->post('/forgot-password', ['email' => $this->admin()->email])->assertStatus(429);
    }

    public function test_each_role_can_reset_once_and_log_in_with_new_password(): void
    {
        foreach (array_keys(User::ROLES) as $index => $role) {
            $email = $role.'@example.test';
            $id = hash('sha256', $email);
            $this->store->put('admins', $id, [
                'name' => $role, 'email' => $email, 'password' => Hash::make('BeforePassword!'),
                'role' => $role, 'active' => true, 'remember_token' => 'previous-token',
            ]);
            $user = User::find($id);
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.($index + 1)]);
            $this->post('/forgot-password', compact('email'))->assertSessionHas('sent', true);
            $message = Mail::mailer('smtp')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
            $this->assertSame($email, $message->getTo()[0]->getAddress());
            preg_match('~/reset-password/([a-zA-Z0-9]+)~', $message->getTextBody(), $matches);
            $this->assertNotEmpty($matches[1]);
            $payload = ['email' => $email, 'token' => $matches[1], 'password' => 'NewPassword2026!', 'password_confirmation' => 'NewPassword2026!'];
            $this->get('/reset-password/'.$matches[1].'?email='.urlencode($email))->assertOk()->assertSee('Buat kata sandi baru');
            $this->post('/reset-password', $payload)->assertRedirect('/login');
            $this->assertSame('', $this->store->get('admins', $id)['remember_token']);
            $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
            $this->post('/login', ['email' => $email, 'password' => 'BeforePassword!'])->assertSessionHasErrors('email');
            $this->post('/login', ['email' => $email, 'password' => 'NewPassword2026!'])->assertRedirect('/admin');
            $this->assertAuthenticatedAs($user);
            $this->post('/logout');
        }
    }

    public function test_expired_and_replaced_links_cannot_change_password(): void
    {
        $user = $this->admin();
        $oldToken = Password::createToken($user);
        $this->travel(2)->minutes();
        $newToken = Password::createToken($user);
        $payload = ['email' => $user->email, 'password' => 'NewPassword2026!', 'password_confirmation' => 'NewPassword2026!'];
        $this->post('/reset-password', [...$payload, 'token' => $oldToken])->assertSessionHasErrors('email');
        $this->travel(61)->minutes();
        $this->post('/reset-password', [...$payload, 'token' => $newToken])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('Ceria2026!', $this->admin()->password));
    }
}
