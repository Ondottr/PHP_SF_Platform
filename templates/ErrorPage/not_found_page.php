<?php


namespace PHP_SF\Templates\ErrorPage;

use PHP_SF\System\Classes\Abstracts\AbstractView;

final class not_found_page extends AbstractView
{

    public function show(): void
    {
        ?>

      <div class="error">

        <h3 style="text-align: center">Page Not Found</h3>

      </div>

    <?php }
}