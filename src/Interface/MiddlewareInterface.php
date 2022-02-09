<?php

namespace PHP_SF\System\Interface;

use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Core\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

interface MiddlewareInterface
{

    public function result(): bool|JsonResponse|RedirectResponse|Response;
}
