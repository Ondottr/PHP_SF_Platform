<?php

namespace PHP_SF\Templates\Auth;

use PHP_SF\System\Classes\Abstracts\AbstractView;


final class register_page extends AbstractView
{

    public function show(): void
    {
        ?>

        <div>

            <form action="" method="POST">

                <?php showErrors() ?>

                <label for="login">
                    <?= _t( 'login' ) ?>: [2-35] <?php formInput( 'login', [ 2, 35 ] ) ?><br />
                    <?= _t( 'login_field_description' ) ?>
                </label>

                <div class="line"></div>

                <label for="email">
                    <?= _t( 'email' ) ?>: [6-50] <?php formInput( 'email', [ 6, 50 ], 'email' ) ?><br />
                    <?= _t( 'email_field_description' ) ?>
                </label>

                <div class="line"></div>

                <label for="password">
                    <?= _t( 'password' ) ?><span class="war">*</span>:
                    [6-50] <?php formInput( 'password', [ 6, 50 ], 'password' ) ?><br />
                    <?= _t( 'password_field_description' ) ?>

                </label>

                <div class="line"></div>

                <label for="accept">
                    <?php formCheckbox( 'accept' ) ?>
                    <?= _t( 'accept_checkbox_description' ) ?> <br /><br />
                </label>

                <input type="submit" value="<?= _t( 'register' ) ?>!">

            </form>

            <a href="<?= routeLink( 'login_page' ) ?>">
                <span class="nav_button"><?= _t( 'login' ) ?></span>
            </a>

        </div>

    <?php }
}
