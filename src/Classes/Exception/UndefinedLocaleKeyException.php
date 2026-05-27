<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\Exception;

final class UndefinedLocaleKeyException extends \Exception
{
    public function __construct($localeKey)
    {
        parent::__construct(sprintf('Undefined locale key “%s”', $localeKey));
    }
}
