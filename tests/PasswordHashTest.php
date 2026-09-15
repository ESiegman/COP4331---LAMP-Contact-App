<?php

use PHPUnit\Framework\TestCase;

final class PasswordHashTest extends TestCase
{
    public function testHashVerifiesAgainstOriginalPassword(): void
    {
        $hash = password_hash('testpassword123', PASSWORD_DEFAULT);

        $this->assertTrue(password_verify('testpassword123', $hash));
        $this->assertFalse(password_verify('wrongpassword', $hash));
    }

    public function testTwoHashesOfSamePasswordAreNotIdentical(): void
    {
        $hashOne = password_hash('testpassword123', PASSWORD_DEFAULT);
        $hashTwo = password_hash('testpassword123', PASSWORD_DEFAULT);

        $this->assertNotSame($hashOne, $hashTwo);
    }
}
