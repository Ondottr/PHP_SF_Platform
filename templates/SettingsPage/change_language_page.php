<?php
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

declare(strict_types=1);

namespace PHP_SF\Templates\SettingsPage;

use PHP_SF\System\Classes\Helpers\Locale;
use PHP_SF\System\Classes\Abstracts\AbstractView;
use function _t;
use function routeLink;
use function showErrors;
use function showMessages;
use const LANGUAGES_LIST;

final class change_language_page extends AbstractView
{
    public function show(): void
    { ?>

        <?php showMessages() ?>
        <?php showErrors() ?>

      <form action="<?= routeLink('change_language_handler') ?>" method="POST">

        <label for="language"> <?= _t('select_language') ?> <br /><br />
          <select name="lang" id="language">
              <?php foreach (LANGUAGES_LIST as $lang) : ?>
                <option value="<?= $lang ?>"><?= Locale::getLocaleName($lang) ?></option>
              <?php endforeach; ?>
          </select>
        </label>

        <br /><br />

        <input type="submit" value="<?= _t('change') ?>">

      </form>

    <?php }

}
