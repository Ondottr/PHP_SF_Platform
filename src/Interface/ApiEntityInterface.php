<?php

declare( strict_types=1 );

namespace PHP_SF\System\Interface;

use Symfony\Component\HttpFoundation\JsonResponse;

interface ApiEntityInterface
{

    public function getEntityClassName(): string;

    public function apiEntityGetAll(): JsonResponse;

    public function apiEntityCreate(): JsonResponse;

    public function apiEntityGetOne(int $id): JsonResponse;

    public function apiEntityUpdate(int $id): JsonResponse;

    public function apiEntityDelete(int $id): JsonResponse;
}
