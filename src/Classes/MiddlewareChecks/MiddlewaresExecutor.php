<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\MiddlewareChecks;

use PHP_SF\System\Classes\Abstracts\MiddlewareType;
use PHP_SF\System\Classes\Exception\RouteMiddlewareException;
use PHP_SF\System\Core\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

final class MiddlewaresExecutor
{
    /**
     * @param string|array<array-key, mixed> $middlewares
     */
    public function __construct(
        private string|array $middlewares,
    ) {}


    final public function execute(): bool|RedirectResponse|JsonResponse
    {
        if (is_string($this->getMiddlewares()) && empty($this->getMiddlewares())) {
            throw new RouteMiddlewareException('Middleware must be a non-empty string');
        }

        if ([] === $this->getMiddlewares()) {
            return true;
        }

        // If the middleware is a string, convert it to an array
        if (is_string($this->getMiddlewares())) {
            $this->setMiddlewares([MiddlewareType::DEFAULT => [$this->getMiddlewares()]]);
        }

        // If the first key in the middleware array is numeric, assume it is an array of middlewares for all route matches
        if (is_numeric(array_key_first($this->getMiddlewares()))) {
            $this->setMiddlewares([MiddlewareType::DEFAULT => array_values($this->getMiddlewares())]);
        }

        // First level of an array must contain only one key
        if (1 !== count($this->getMiddlewares())) {
            throw new RouteMiddlewareException('First level of an array must contain only one key!');
        }

        // Check if that first key is a valid class which extends MiddlewareCheck
        $middlewareType = array_key_first($this->getMiddlewares());
        if (false === is_string($middlewareType) || false === class_exists($middlewareType) || false === is_subclass_of($middlewareType, MiddlewareType::class)) {
            throw new RouteMiddlewareException(
                'Middleware array keys must be a valid class which extends MiddlewareCheck!',
            );
        }

        try {
            $result =
                (new $middlewareType($this->getMiddlewares()))
                    ->validate()
                    ->execute();
        } catch (Throwable $e) {
            throw new RouteMiddlewareException($e->getMessage(), $e->getCode(), $e);
        }

        return $result;
    }

    /**
     * @return array<array-key, mixed>|string
     */
    private function getMiddlewares(): array|string
    {
        return $this->middlewares;
    }

    /**
     * @param array<array-key, mixed>|string $middleware
     */
    private function setMiddlewares(array|string $middleware): void
    {
        $this->middlewares = $middleware;
    }
}
