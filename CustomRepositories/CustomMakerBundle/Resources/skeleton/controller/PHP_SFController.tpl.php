<?php
$template_name = str_replace(['.php', '/'], ['', '\\'], $template_name);
$route_path = substr($route_path, 1);
?>
<?= '<?php declare(strict_types=1);' . PHP_EOL ?>

namespace <?= $namespace ?>;

use App\View\<?= ($template_name) ?>;
use PHP_SF\Framework\Http\Middleware\auth;
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Core\Response;

class <?= $class_name ?> extends AbstractController
{

#[Route(url: '<?= $route_path ?>', httpMethod: 'GET', middleware: [auth::class])]
public function fucking_fuck_page(): Response
{
return $this->render(<?= explode('\\', $template_name)[1] ?>::class);
}


#[Route(url: '<?= $route_path ?>', httpMethod: 'POST', middleware: [auth::class])]
public function fucking_fuck_handler(): RedirectResponse
{
// TODO:: Do something

return $this->redirectToRoute('<?= explode('\\', $template_name)[1] ?>', errors: [], messages: []);
}

}
