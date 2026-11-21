<?php

namespace Tests\Unit;

use App\Auth\ImedxUserProvider;
use PHPUnit\Framework\TestCase;

class ImedxAuthBackendTest extends TestCase
{
    public function test_verify_django_pbkdf2_hash_valid(): void
    {
        $provider = new ImedxUserProvider();

        $password = 'SecretAdmin123!';
        $iterations = 10000;
        $salt = 'RandomSalt123';
        $binaryHash = hash_pbkdf2('sha256', $password, $salt, $iterations, 32, true);
        $encodedHash = base64_encode($binaryHash);
        $djangoHash = "pbkdf2_sha256\${$iterations}\${$salt}\${$encodedHash}";

        $this->assertTrue($provider->verifyPassword($password, $djangoHash));
        $this->assertFalse($provider->verifyPassword('WrongPassword', $djangoHash));
    }

    public function test_verify_django_pbkdf2_invalid_format(): void
    {
        $provider = new ImedxUserProvider();
        $this->assertFalse($provider->verifyPassword('pass', 'invalid_hash_string'));
        $this->assertFalse($provider->verifyPassword('pass', 'md5$123$456'));
    }

    public function test_verify_native_bcrypt_password(): void
    {
        $provider = new ImedxUserProvider();
        $hash = password_hash('bcrypt_secret', PASSWORD_BCRYPT);

        $this->assertTrue($provider->verifyPassword('bcrypt_secret', $hash));
        $this->assertFalse($provider->verifyPassword('wrong_secret', $hash));
    }
}
