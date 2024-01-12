<?php declare( strict_types=1 );
/*
 * Copyright © 2018-2024, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 * Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 * granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE
 * INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE
 * LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER
 * RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 * TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

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
 * @author  Dmytro Dyvulskyi <dmytro.dyvulskyi@nations-original.com>
 */
abstract class AbstractController
{
    use RedirectTrait;

    private string $generatedUrl;


    public function __construct( protected Request|null $request ) {}


    final protected function render( string $view, array $data = [], string $pageTitle = null ): Response
    {
        s()->set( 'page_title', $pageTitle ?? APPLICATION_NAME );

        if ( TEMPLATES_CACHE_ENABLED ) {
            $result = TemplatesCache::getInstance()->getCachedTemplateClass( $view );
            if ( $result !== false ) {
                eval( $result['fileContent'] );
                $view = $result['className'];
            }
        }

        $view = new $view( $data );

        if ( $view instanceof AbstractView === false )
            throw new InvalidConfigurationException;

        return new Response( view: $view, dataFromController: $data );
    }

}
