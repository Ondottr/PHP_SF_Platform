<?php declare( strict_types=1 );

namespace PHP_SF\Templates;

use PHP_SF\System\Classes\Abstracts\AbstractView;

// @formatter:off
final class base extends AbstractView { public function show(): void { ?>
  <!--@formatter:on-->

  <h1 style="text-align: center;">Hello World!</h1>

  <!--@formatter:off-->
<?php } }