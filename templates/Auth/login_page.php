<?php declare( strict_types=1 );

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