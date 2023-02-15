<?php declare( strict_types=1 );
/**
 * Created by PhpStorm.
 * User: ondottr
 * Date: 15/02/2023
 * Time: 8:05 am
 */

namespace PHP_SF\System\Classes\Abstracts;

use PHP_SF\System\Classes\Exception\RouteMiddlewareException;
use PHP_SF\System\Classes\MiddlewareChecks\MiddlewareAll;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Kernel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class MiddlewareType
{

    public const DEFAULT = MiddlewareAll::class;


    public function __construct(
        protected readonly string|array $middlewares,
        protected readonly Request $request,
        protected readonly Kernel $kernel,
        protected readonly AbstractController $controller,
    ) {}


    /**
     * @throws RouteMiddlewareException If any of validation fails it is recommended to throw an {@link RouteMiddlewareException}
     */
    abstract public function validate(): self;

    /**
     * Return `true` if the middleware allow the route to be executed, or a {@link RedirectResponse} or {@link JsonResponse}
     * which will be returned by the {@link Middleware::execute()} method
     *
     * @return bool|RedirectResponse|JsonResponse Result of the {@link Middleware::execute()} method
     */
    abstract public function execute(): bool|RedirectResponse|JsonResponse;

}