<?php


namespace PHP_SF\Templates\ErrorPage;

use PHP_SF\System\Classes\Abstracts\AbstractView;

final class access_denied_page extends AbstractView
{

    public function show(): void
    {
        ?>

      <div class="error">

        <h3 style="text-align: center">Access Denied</h3>

      </div>

    <?php }
}