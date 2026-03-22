<?php

namespace PHP_SF\System\Interface;

interface UserInterface
{

    public function getId(): int;

    public function setEmail(?string $email): self;

    public function getEmail(): ?string;

    public function setPassword(?string $password): self;

    public function getPassword(): ?string;
}
