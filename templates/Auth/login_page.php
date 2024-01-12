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

namespace PHP_SF\Templates\Auth;

use PHP_SF\System\Classes\Abstracts\AbstractView;

// @formatter:off
final class login_page extends AbstractView { public function show(): void { ?>
<!--@formatter:on-->

  <div>

    <form action="" method="POST">

<!--      --><?php //showErrors() ?>

      <table>
        <tbody>

        <tr>
          <td><?= _t( 'E-mail' ) ?>:</td>
          <td><?php formInput( 'E-mail', [ 6, 50 ], _t( 'E-mail' ) ) ?></td>
        </tr>

        <tr>
          <td>Password:</td>
          <td><?php formInput( 'password', [ 6, 50 ], 'password' ) ?></td>
        </tr>

        <tr>
          <td><input type="submit" value="<?= _t( 'Sign In' ) ?>"></td>
          <td><a href="<?= routeLink( 'password_recovery' ) ?>">Forgot password?</a></td>
        </tr>

        </tbody>
      </table>
    </form>

    <a href="<?= routeLink( 'register_page' ) ?>">
      <span class="nav_button">Register</span>
    </a>

  </div>

  <!--@formatter:off-->
<?php } }