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

namespace PHP_SF\Templates\Auth;

use PHP_SF\System\Classes\Abstracts\AbstractView;

final class login_page extends AbstractView
{

    public function show(): void
    {
        ?>
        <div>

            <form action="" method="POST">

<!--                --><?php //showErrors() ?>

                <table>
                    <tbody>

                    <tr>
                        <td><?= _t( 'Email' ) ?>:</td>
                        <td><?php formInput( 'email', [ 6, 50 ], _t( 'email' ) ) ?></td>
                    </tr>

                    <tr>
                        <td><?= _t( 'password' ) ?>:</td>
                        <td><?php formInput( 'password', [ 6, 50 ], _t( 'password' ) ) ?></td>
                    </tr>

                    <tr>
                        <td><input type="submit" value="<?= _t( 'Sign In' ) ?>"></td>
                        <td><a href="<?= routeLink( 'password_recovery' ) ?>"><?= _t( 'forgot_password' ) ?></a></td>
                    </tr>

                    </tbody>
                </table>
            </form>

            <a href="<?= routeLink( 'register_page' ) ?>">
                <span class="nav_button"><?= _t( 'register' ) ?></span>
            </a>

        </div>

    <?php }
}
