<?php declare( strict_types=1 );

namespace PHP_SF\Templates\Layout;

use PHP_SF\System\Classes\Abstracts\AbstractView;
use PHP_SF\Templates\Layout\Header\head;


final class header extends AbstractView
{

    public function show(): void
    {
        $this->import(head::class);

        ?>

      <div class="header">


      </div>

        <?php
    }

}