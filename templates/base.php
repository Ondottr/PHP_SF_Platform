<?php declare( strict_types=1 );

namespace PHP_SF\Templates;

use PHP_SF\System\Classes\Abstracts\AbstractView;

final class base extends AbstractView
{

    public function show(): void
    {
        ?>

      <div class="content">

        <h1 style="text-align: center;">Hello World!</h1>

      </div>

    <?php }
}