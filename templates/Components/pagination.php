<?php declare( strict_types=1 );

namespace PHP_SF\Templates\Components;

use PHP_SF\System\Classes\Abstracts\AbstractView;
use function count;

/**
 * @property array<int> pages
 * @property string onclickFunction
 */
// @formatter:off
final class pagination extends AbstractView { public function show(): void { ?>
  <!--@formatter:on-->

  <?php
  if ( count( $this->pages ) === 0 )
    return
  ?>

  <ul class="page-numbers pagination pagination-sm">
    <?php if ( isset( $this->pages['first'] ) ) : ?>
      <li class="pagination-link enabled" onclick="<?= "$this->onclickFunction({$this->pages['first']})" ?>">
        <span><?= $this->pages['first'] ?></span>
      </li>

      <?php if ( isset( $this->pages['page1left'] ) ) : ?>
        <?php if ( $this->pages['page2left'] - $this->pages['first'] > 1 ) : ?>
          <li class="pagination-link disabled">
            <span>...</span>
          </li>
        <?php endif ?>

      <?php endif ?>

    <?php endif ?>


    <?php if ( isset( $this->pages['page2left'] ) ) : ?>

      <li class="pagination-link enabled" onclick="<?= "$this->onclickFunction({$this->pages['page2left']})" ?>">
        <span><?= $this->pages['page2left'] ?></span>
      </li>

    <?php endif ?>


    <?php if ( isset( $this->pages['page1left'] ) ) : ?>

      <li class="pagination-link enabled" onclick="<?= "$this->onclickFunction({$this->pages['page1left']})" ?>">
        <span><?= $this->pages['page1left'] ?></span>
      </li>

    <?php endif ?>


    <li class="pagination-link disabled current">
      <span><?= $this->pages['current'] ?></span>
    </li>

    <?php if ( isset( $this->pages['page1right'] ) ) : ?>

      <li class="pagination-link enabled" onclick="<?= "$this->onclickFunction({$this->pages['page1right']})" ?>">
        <span><?= $this->pages['page1right'] ?></span>
      </li>

    <?php endif ?>


    <?php if ( isset( $this->pages['page2right'] ) ) : ?>
      <li class="pagination-link enabled" onclick="<?= "$this->onclickFunction({$this->pages['page2right']})" ?>">
        <span><?= $this->pages['page2right'] ?></span>
      </li>


      <?php if ( isset( $this->pages['last'] ) ) : ?>
        <?php if ( $this->pages['last'] - $this->pages['page2right'] > 1 ) : ?>
          <li class="pagination-link disabled">
            <span>...</span>
          </li>
        <?php endif ?>

        <li class="pagination-link enabled" onclick="<?= "$this->onclickFunction({$this->pages['last']})" ?>">
          <span><?= $this->pages['last'] ?></span>
        </li>

      <?php endif ?>


    <?php endif ?>
  </ul>

  <script>
    paginationButtons = Object.values($('.page-numbers>.pagination-link'));
    last = paginationButtons[paginationButtons.length - 3];
    first = paginationButtons[0];

    $(last).css({"border-top-right-radius": "3px", "border-bottom-right-radius": "3px"});
    $(first).css({"border-top-left-radius": "3px", "border-bottom-left-radius": "3px"});

    delete paginationButtons;
    delete last;
    delete first;
  </script>

  <!--@formatter:off-->
<?php } }