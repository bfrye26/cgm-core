<?php
namespace CGM\Core\Contracts;

interface BuilderAdapterInterface {
    public function id(): string;
    public function detected(): bool;
    public function capabilities(): array;
    public function register(): void;
}
