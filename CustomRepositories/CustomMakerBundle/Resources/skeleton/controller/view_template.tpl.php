<?= '<?php declare(strict_types=1);' . PHP_EOL ?>

namespace App\View\<?= str_replace('Page', '', ucfirst(snakeToCamel($class_name))) ?>;

use PHP_SF\System\Classes\Abstracts\AbstractView;

class <?= $class_name ?> extends AbstractView
{

public function show(): void
{ ?>

<h1><?= ucfirst(snakeToCamel($class_name)) ?></h1>

<?= '<?php }' . PHP_EOL ?>

}
