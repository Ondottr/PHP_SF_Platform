<?php declare( strict_types=1 );

namespace PHP_SF\Templates\SettingsPage;

use PHP_SF\System\Classes\Abstracts\AbstractView;
use PHP_SF\System\Classes\Helpers\Locale;

// @formatter:off
final class change_language_page extends AbstractView { public function show(): void { ?>
  <!--@formatter:on-->

<!--  --><?php //showErrors() ?>
<!--  --><?php //showMessages() ?>

  <form method="POST">

    <label for="language"> <?= _t( 'Select Language' ) ?> <br /><br />
      <select name="lang" id="language">
        <?php foreach ( LANGUAGES_LIST as $lang ) : ?>
          <option value="<?= $lang ?>"><?= Locale::getLocaleName( $lang ) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <br /><br />

    <input type="submit" value="<?= _t( 'Change' ) ?>">

  </form>

  <!--@formatter:off-->
<?php } }
