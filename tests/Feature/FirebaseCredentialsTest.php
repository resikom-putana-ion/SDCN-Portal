<?php

namespace Tests\Feature;

use App\Services\FirebaseCredentials;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FirebaseCredentialsTest extends TestCase
{
    private string $path;
    private array $credentials;
    protected function setUp(): void
    {
        parent::setUp();
        $this->path=tempnam(sys_get_temp_dir(),'credential-test-');
        $this->credentials=['type'=>'service_account','project_id'=>'demo-school','client_email'=>'test@demo-school.iam.gserviceaccount.com',
            'private_key'=>"-----BEGIN PRIVATE KEY-----\nTEST-ONLY\n-----END PRIVATE KEY-----\n",'private_key_id'=>uniqid()];
        file_put_contents($this->path,json_encode($this->credentials));
        config(['school.firebase_project'=>'demo-school','school.firebase_credentials'=>$this->path,
            'school.firebase_credentials_json_base64'=>null,'school.firebase_ca_bundle'=>null]);
    }
    protected function tearDown(): void { unlink($this->path);parent::tearDown(); }

    public function test_file_and_base64_credentials_return_validated_project_without_writing_json(): void
    {
        $provider=new FirebaseCredentials;
        $this->assertSame('demo-school',$provider->data()['project_id']);
        $encoded=base64_encode(json_encode($this->credentials));
        config(['school.firebase_credentials_json_base64'=>$encoded,'school.firebase_credentials'=>'nonexistent-file']);
        $this->assertSame($this->credentials+['token_uri'=>'https://oauth2.googleapis.com/token'],$provider->data());
        $this->assertTrue($provider->httpOptions()['verify']);
    }

    public function test_incorrect_project_is_rejected_without_echoing_credential_contents(): void
    {
        config(['school.firebase_project'=>'another-project']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Project ID, email, atau private key');
        (new FirebaseCredentials)->data();
    }

    public function test_malformed_base64_is_rejected(): void
    {
        config(['school.firebase_credentials_json_base64'=>'not base64!!!']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FIREBASE_CREDENTIALS_JSON_BASE64');
        (new FirebaseCredentials)->data();
    }

    public function test_token_cache_is_memory_only_and_is_separated_by_scope_and_key_rotation(): void
    {
        Cache::shouldReceive('remember')->never();
        $provider=new class extends FirebaseCredentials {
            public int $calls=0;
            protected function fetchToken(array $credentials,string $scope,array $options): array
            {
                return ['access_token'=>'test-token-'.++$this->calls,'expires_in'=>3600];
            }
        };
        $this->assertSame('test-token-1',$provider->accessToken('scope-a'));
        $this->assertSame('test-token-1',$provider->accessToken('scope-a'));
        $this->assertSame('test-token-2',$provider->accessToken('scope-b'));
        $changed=$this->credentials;$changed['private_key_id']='rotated';
        config(['school.firebase_credentials_json_base64'=>base64_encode(json_encode($changed))]);
        $this->assertSame('test-token-3',$provider->accessToken('scope-a'));
        $this->assertSame(3,$provider->calls);
    }

    public function test_ca_cannot_be_a_directory_or_boolean_false(): void
    {
        config(['school.firebase_ca_bundle'=>false]);
        try { (new FirebaseCredentials)->httpOptions(); $this->fail('False must not disable TLS.'); }
        catch (\RuntimeException $error) { $this->assertStringContainsString('FIREBASE_CA_BUNDLE',$error->getMessage()); }
        config(['school.firebase_ca_bundle'=>sys_get_temp_dir()]);
        $this->expectException(\RuntimeException::class);
        (new FirebaseCredentials)->httpOptions();
    }
}
