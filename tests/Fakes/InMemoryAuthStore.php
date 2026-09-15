<?php

namespace Tests\Fakes;

use App\Auth\AuthContext;
use App\Auth\AuthStoreInterface;

final class InMemoryAuthStore implements AuthStoreInterface
{
    private ?AuthContext $context = null;

    public function start(AuthContext $context): void
    {
        $this->context = $context;
    }

    public function current(): ?AuthContext
    {
        return $this->context;
    }

    public function destroy(): void
    {
        $this->context = null;
    }
}
