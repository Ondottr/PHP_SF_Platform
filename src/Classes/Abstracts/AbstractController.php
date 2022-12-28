<?php declare( strict_types=1 );

/*
 * Copyright © 2018-2022, Nations Original Sp. z o.o. <contact@nations-original.com>
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

use App\Kernel;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Traits\ControllerTrait;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormInterface;
use function is_array;

abstract class AbstractController
{
    use ControllerTrait;


    private string $generatedUrl;


    /**
     * @noinspection OffsetOperationsInspection
     */
    final protected function render( string $view, array $data = [] ): Response
    {
        if ( TEMPLATES_CACHE_ENABLED &&
            is_array( $arr = tc()->getCachedTemplateClass( $view ) )
        ) {
            require_once( $arr['fileName'] );
            $view = $arr['className'];
        }

        $view = new $view( $data );

        if ( $view instanceof AbstractView === false )
            throw new InvalidConfigurationException;

        return new Response( view: $view, dataFromController: $data );
    }

    final protected function submitForm( string $type, array $options = [] ): AbstractEntity
    {
        ( $form = $this
            ->createForm( $type, options: $options ) )
            ->setData( $user = new ( $form->getConfig()->getDataClass() )( false ) )
            ->submit( $this->request->request->all() );

        return $user;
    }

    /**
     * Creates and returns a Form instance from the type of the form.
     */
    final protected function createForm( string $type, mixed $data = null, array $options = [] ): FormInterface
    {
        return Kernel::getInstance()->getContainer()->get( FormFactory::class )?->create( $type, $data, $options );
    }
}
