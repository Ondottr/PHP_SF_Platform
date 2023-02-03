<?php declare( strict_types=1 );
/*
 * Copyright © 2018-2023, Nations Original Sp. z o.o. <contact@nations-original.com>
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
final class register_page extends AbstractView { public function show(): void { ?>
<!--@formatter:on-->

  <div>

    <form action="" method="POST">

<!--      --><?php //showErrors() ?>

      <label for="login">
        Login: [2-35] <?php formInput( 'login', [ 2, 35 ] ) ?><br />
        Enter your login
      </label>

      <div class="line"></div>

      <label for="email">
        <?= _t( 'E-mail' ) ?>: [6-50] <?php formInput( 'email', [ 6, 50 ], 'email' ) ?><br />
        Enter your email address
      </label>

      <div class="line"></div>

      <label for="password">
        Password<span class="war">*</span>:
        [6-50] <?php formInput( 'password', [ 6, 50 ], 'password' ) ?><br />
        Enter your password

      </label>

      <div class="line"></div>

      <label for="accept">
        <?php formCheckbox( 'accept' ) ?>
        I hereby certify that I am over the age of 13 or other minimum age of consent as required by the laws of my
        country. Please ask your legal representative to consent for you by ticking the appropriate box if you are below
        the minimum age of consent under the laws of your country. <br /><br />
      </label>

      <input type="submit" value="Register!">

    </form>

    <a href="<?= routeLink( 'login_page' ) ?>">
      <span class="nav_button">Login</span>
    </a>

  </div>

  <!--@formatter:off-->
<?php } }
