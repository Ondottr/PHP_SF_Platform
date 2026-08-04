<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\Exception;

use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Core\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

final class InvalidRouteReturnTypeException extends RouteParameterException
{
    public function __construct(string $type, object $data)
    {
        parent::__construct(
            sprintf(
                'Invalid return type “%s” for API route %s::%s — API routes must return a %s or %s and must never return a %s!',
                $type,
                $data->class,
                $data->method,
                Response::class,
                JsonResponse::class,
                RedirectResponse::class,
            ),
        );
    }
}
