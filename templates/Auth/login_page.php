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
          <td><?= _t( 'auth.login_form.email_label' ) ?>:</td>
          <td><?php input( 'E-mail', [ 6, 50 ], _t( 'auth.login_form.email_placeholder' ) ) ?></td>
        </tr>

        <tr>
          <td>Password:</td>
          <td><?php formInput( 'password', [ 6, 50 ], 'password' ) ?></td>
        </tr>

        <tr>
          <td><input type="submit" value="<?= _t( 'auth.login_form.submit_button' ) ?>"></td>
          <td><a href="<?= routeLink( 'password_recovery' ) ?>"><?= _t( 'auth.login_form.forgot_password' ) ?></a></td>
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
