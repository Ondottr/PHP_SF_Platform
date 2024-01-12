<?php declare( strict_types=1 );

namespace PHP_SF\Templates\Layout\HeaderComponents;

use PHP_SF\System\Classes\Abstracts\AbstractView;
use PHP_SF\System\Router;

// @formatter:off
final class head extends AbstractView { public function show(): void { ?>
  <!--@formatter:on-->

  <!DOCTYPE html>
<html lang="<?= DEFAULT_LOCALE ?>">
  <head>
    <script type="text/javascript" src="<?= asset('js/app.js') ?>"></script>
    <script type="text/javascript" src="<?= asset('js/jquery-3.6.0.min.js') ?>"></script>
    <script type="text/javascript" src="<?= asset('js/bootstrap.min.js') ?>"></script>

    <title><?= APPLICATION_NAME ?></title>
    <!--    <link rel="shortcut icon" href="--><?php //= asset('images/favicon.gif') ?><!--">-->

    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=3.0">

  </head>

<body class="container">
<span style="position: absolute; right: 0; top: 0; color: #fff; font-weight: bolder; padding: 7px;">v. <?= env('DEVELOPMENT_STAGE') ?></span>

<script>
  app.router.setCurrentRouteName('<?= Router::$currentRoute->name ?>');
</script>

<!--@formatter:off-->
<?php } }
