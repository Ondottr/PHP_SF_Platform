<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\Exception;

final class UndefinedLocaleNameException extends \Exception
{
    public function __construct($localeName)
    {
        parent::__construct(sprintf('Undefined locale name “%s”', $localeName));
    }
}
