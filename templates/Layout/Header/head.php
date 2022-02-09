<?php declare( strict_types=1 );

namespace PHP_SF\Templates\Layout\Header;

use PHP_SF\System\Classes\Abstracts\AbstractView;


class head extends AbstractView
{

    public function show(): void
    {
        ?>

      <html lang="ru">
      <head>
        <script type="text/javascript" src="<?= asset( 'assets/js/jquery-3.6.0.min.js' ) ?>"></script>

        <title><?= APPLICATION_NAME ?></title>
        <link rel="shortcut icon" href="<?= asset( 'assets/images/favicon.gif' ) ?>">
        <link href="<?= asset( 'assets/css/main.css' ) ?>" rel="stylesheet" type="text/css">

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=3.0">

      </head>
      <body>

    <?php }

}
