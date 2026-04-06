<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Abstracts;

use PHP_SF\System\Core\Response;
use PHP_SF\System\Core\TemplatesCache;
use PHP_SF\System\Traits\RedirectTrait;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AbstractController
 *
 * @package PHP_SF\System\Classes\Abstracts
 */
abstract class AbstractController
{
    use RedirectTrait;

    private string $generatedUrl;


    public function __construct( protected Request|null $request = null ) {}


    final protected function render( string $view, array $data = [], string $pageTitle = null ): Response
    {
        s()->set( 'page_title', $pageTitle ?? APPLICATION_NAME );

        if ( TEMPLATES_CACHE_ENABLED )
            $view = TemplatesCache::getInstance()->getCachedTemplateClass( $view ) ?: $view;

        $view = new $view( $data );

        if ( $view instanceof AbstractView === false )
            throw new InvalidConfigurationException;

        return new Response( view: $view, dataFromController: $data );
    }

}
