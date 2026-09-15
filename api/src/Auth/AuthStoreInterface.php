<?php

namespace App\Auth;

interface AuthStoreInterface
{
    public function start(AuthContext $context): void;

    public function current(): ?AuthContext;

    public function destroy(): void;
}
