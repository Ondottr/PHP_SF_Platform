<?php declare(strict_types=1);

namespace PHP_SF\System\Interface;

interface UserInterface
{
    /**
     * @return int|string Auto-increment integer for the default {@see \PHP_SF\System\Classes\Abstracts\AbstractEntity}
     *                    PK; RFC 4122 string (UUID/ULID) for custom PK User entities. The auth
     *                    middleware round-trips the id through the session as-is and resolves it
     *                    via `Entity::find(int|string $id)` on the next request.
     */
    public function getId(): int|string;

    public function setEmail(?string $email): self;

    public function getEmail(): ?string;

    public function setPassword(?string $password): self;

    public function getPassword(): ?string;
}
