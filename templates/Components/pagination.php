<?php declare( strict_types=1 );

namespace PHP_SF\Templates\Components;

use PHP_SF\System\Classes\Abstracts\AbstractView;
use function count;

/**
 * @property array<int> pages
 * @property string     onclickFunction
 * @property bool       link
 */
final class pagination extends AbstractView
{
    public function show(): void
    {
        if ( count( $this->pages ) === 0 )
            return ?>

      <ul class="pagination">
          <?php if ( isset( $this->pages['first'] ) ) : ?>
            <li class="pagination-link enabled"
                onclick="<?= isset( $this->link ) ? "window.location.replace({$this->pages['first']})"
                    : "$this->onclickFunction({$this->pages['first']})" ?>">
              <a>
                <span><?= $this->pages['first'] ?></span>
              </a>
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
            <li class="pagination-link enabled"
                onclick="<?= isset( $this->link ) ? "window.location.replace({$this->pages['page2left']})"
                    : "$this->onclickFunction({$this->pages['page2left']})" ?>">
              <a>
                <span><?= $this->pages['page2left'] ?></span>
              </a>
            </li>

          <?php endif ?>


          <?php if ( isset( $this->pages['page1left'] ) ) : ?>
            <li class="pagination-link enabled"
                onclick="<?= isset( $this->link ) ? "window.location.replace({$this->pages['page1left']})"
                    : "$this->onclickFunction({$this->pages['page1left']})" ?>">
              <a>
                <span><?= $this->pages['page1left'] ?></span>
              </a>
            </li>

          <?php endif ?>


        <li class="pagination-link disabled current">
          <span><?= $this->pages['current'] ?></span>
        </li>

          <?php if ( isset( $this->pages['page1right'] ) ) : ?>
            <li class="pagination-link enabled"
                onclick="<?= isset( $this->link ) ? "window.location.replace({$this->pages['page1right']})"
                    : "$this->onclickFunction({$this->pages['page1right']})" ?>">
              <a>
                <span><?= $this->pages['page1right'] ?></span>
              </a>
            </li>

          <?php endif ?>


          <?php if ( isset( $this->pages['page2right'] ) ) : ?>
            <li class="pagination-link enabled"
                onclick="<?= isset( $this->link ) ? "window.location.replace({$this->pages['page2right']})"
                    : "$this->onclickFunction({$this->pages['page2right']})" ?>">
              <a>
                <span><?= $this->pages['page2right'] ?></span>
              </a>
            </li>


              <?php if ( isset( $this->pages['last'] ) ) : ?>
                  <?php if ( $this->pages['last'] - $this->pages['page2right'] > 1 ) : ?>
                <li class="pagination-link disabled">
                  <span>...</span>
                </li>
                  <?php endif ?>

              <li class="pagination-link enabled"
                  onclick="<?= isset( $this->link ) ? "window.location.replace({$this->pages['last']})"
                      : "$this->onclickFunction({$this->pages['last']})" ?>">
                <a>
                  <span><?= $this->pages['last'] ?></span>
                </a>
              </li>

              <?php endif ?>


          <?php endif ?>
      </ul>

    <?php }
}
