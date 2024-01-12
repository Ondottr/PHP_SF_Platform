<?php declare( strict_types=1 );

namespace PHP_SF\Templates\Layout;

use App\Entity\User;
use App\Kernel;
use PHP_SF\System\Classes\Abstracts\AbstractEventsDispatcher;
use PHP_SF\System\Classes\Abstracts\AbstractView;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Router;
use PHP_SF\Templates\Layout\FooterComponents\CKEditor_activator;

// @formatter:off
final class footer extends AbstractView { public function show(): void { ?>
  <!--@formatter:on-->

  </div>
  <div class="footer">
    <div class="container">
      <div class="row">
        <div class="offset-6 col-6" style="text-align: right;">
          <?php
          $key = sprintf('%s:average_page_load_time', env( 'SERVER_PREFIX' ));
          rp()->rpush($key, [($currentPageLoadTime = round((microtime(true) - start_time), 5))]);
          if (!rc()->exists( $key) )
            rp()->expire($key, 86400);

          $sum = $currentPageLoadTime;
          foreach (($arr = rc()->lrange($key, 0, -1)) as $value)
            $sum += $value;

          $averagePageLoadTime = round($sum / (count($arr) + 1), 5);

          ?>
          <p>
            <?= round($currentPageLoadTime, 3) . ' ' . 'sec' ?>
            / <?= round($averagePageLoadTime, 3) . ' ' . 'sec' ?>
          </p>
        </div>
      </div>

      <?php if (User::isAdmin()) : ?>
        <div class="row">
          <div class="col-6">
            <?php dump(Router::$currentRoute, AbstractEventsDispatcher::getDispatchedListeners()) ?>
          </div>
          <div class="col-6">
            <?php dump(Response::$activeTemplates) ?>
          </div>
        </div>
      <?php endif ?>

    </div>
  </div>


  </body>
  </html>

  <?php
  if (Kernel::isEditorActivated())
    $this->import(CKEditor_activator::class)
  ?>

  <!--@formatter:off-->
<?php } }
