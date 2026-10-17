<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GmailSetupTest extends TestCase
{
    public function test_setup_preserves_other_environment_values_and_sends_test_email_to_sender(): void
    {
        $directory = sys_get_temp_dir().'/sdcn-mail-test-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $path = $directory.'/.env';
        file_put_contents($path, "APP_NAME=School\r\nAPP_KEY=unchanged\r\nMAIL_PASSWORD=old\r\nMAIL_MAILER=log\r\nMAIL_URL=smtp://old.example.test\r\n");
        $originalPath = $this->app->environmentPath();
        $this->app->useEnvironmentPath($directory);
        config(['mail.mailers.smtp.transport' => 'array']);

        try {
            $this->artisan('school:setup-gmail')
                ->expectsQuestion('Email Gmail / Google Workspace pengirim', 'sender@gmail.com')
                ->expectsQuestion('App Password Gmail (input disembunyikan)', 'abcd efgh ijkl mnop')
                ->assertSuccessful();

            $values = \Dotenv\Dotenv::parse(file_get_contents($path));
            $this->assertSame('unchanged', $values['APP_KEY']);
            $this->assertSame('abcdefghijklmnop', $values['MAIL_PASSWORD']);
            $this->assertSame('smtp', $values['MAIL_MAILER']);
            $this->assertSame('smtps', $values['MAIL_SCHEME']);
            $this->assertSame('465', $values['MAIL_PORT']);
            $this->assertSame('null', $values['MAIL_URL']);
            $this->assertSame('sender@gmail.com', $values['MAIL_FROM_ADDRESS']);
            $messages = Mail::mailer('smtp')->getSymfonyTransport()->messages();
            $this->assertCount(1, $messages);
            $this->assertSame('sender@gmail.com', $messages->first()->getOriginalMessage()->getTo()[0]->getAddress());
            $this->assertStringNotContainsString('abcdefghijklmnop', $messages->first()->getOriginalMessage()->getTextBody());
        } finally {
            $this->app->useEnvironmentPath($originalPath);
            unlink($path);
            rmdir($directory);
        }
    }

    public function test_normal_login_password_is_rejected_before_any_environment_write(): void
    {
        $this->artisan('school:setup-gmail')
            ->expectsQuestion('Email Gmail / Google Workspace pengirim', 'sender@gmail.com')
            ->expectsQuestion('App Password Gmail (input disembunyikan)', 'WrongPassword!')
            ->expectsOutput('App Password harus terdiri dari 16 karakter. Salin dari pengaturan Akun Google.')
            ->assertFailed();
    }
}
