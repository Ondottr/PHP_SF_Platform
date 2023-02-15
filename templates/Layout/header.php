<?php declare( strict_types=1 );

namespace PHP_SF\Templates\Layout;

use PHP_SF\System\Classes\Abstracts\AbstractView;
use PHP_SF\Templates\Layout\HeaderComponents\head;

// @formatter:off
final class header extends AbstractView { public function show(): void { ?>
  <!--@formatter:on-->

  <?php $this->import(head::class, htmlClassTagEnabled: false ) ?>

  <div class="header header_elements row">

  </div>

  <!--@formatter:off-->
<?php } }