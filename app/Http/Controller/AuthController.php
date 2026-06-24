<?php /** @noinspection MethodShouldBeFinalInspection */
declare(strict_types=1);

namespace PHP_SF\Framework\Http\Controller;

use PHP_SF\Framework\Http\Middleware\auth;
use PHP_SF\System\Attributes\Route;
use PHP_SF\System\Classes\Abstracts\AbstractController;
use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use PHP_SF\System\Classes\Abstracts\AbstractEntityRepository;
use PHP_SF\System\Core\RedirectResponse;
use PHP_SF\System\Core\Response;
use PHP_SF\System\Interface\UserInterface;
use PHP_SF\System\Kernel;
use PHP_SF\Templates\Auth\login_page;
use PHP_SF\Templates\Auth\register_page;

class AuthController extends AbstractController
{
    /**
     * @var AbstractEntityRepository<AbstractEntity&UserInterface>
     */
    protected AbstractEntityRepository $userRepository;

    public function __construct()
    {
        /**
         * @var class-string<AbstractEntity&UserInterface> $userClass
         */
        $userClass = Kernel::getApplicationUserClassName();
        $this->userRepository = $userClass::rep();
    }

    #[Route(url: 'auth/login', httpMethod: 'GET')]
    public function login_page(): Response|RedirectResponse
    {
        if (auth::isAuthenticated()) {
            return $this->redirectTo(routeLink('home_page'));
        }

        return $this->render(login_page::class);
    }

    #[Route(url: 'auth/login', httpMethod: 'POST')]
    public function login_handler(): RedirectResponse
    {
        if (auth::isAuthenticated()) {
            return $this->redirectTo(routeLink('home_page'));
        }

        $email = trim(htmlspecialchars(r()->request->get('email')));
        $password = trim(htmlspecialchars(r()->request->get('password')));

        if (empty($email) || empty($password)) {
            $errors[] = _t('auth.login_form.error.empty_credentials');
        }

        if (($user = $this->userRepository->findOneBy(['email' => $email])) === null) {
            if (($user = $this->userRepository->findOneBy(['login' => $email])) === null) {
                $errors[] = _t('auth.login_form.error.user_not_found');
            }
        }

        if (null !== $user) {
            if (false === password_verify($password, $user->getPassword())) {
                $errors[] = _t('auth.login_form.error.wrong_password');
            }
        }

        if (isset($errors)) {
            return $this->redirectBack(errors: $errors);
        }

        auth::logInUser($user);

        return $this->redirectTo('welcome_page');
    }

    #[Route(url: 'auth/register', httpMethod: 'GET')]
    public function register_page(): Response|RedirectResponse
    {
        if (auth::isAuthenticated()) {
            return $this->redirectTo(routeLink('home_page'));
        }

        return $this->render(register_page::class);
    }

    #[Route(url: 'auth/register', httpMethod: 'POST')]
    public function register_handler(): RedirectResponse
    {
        if (auth::isAuthenticated()) {
            return $this->redirectTo(routeLink('home_page'));
        }

        $login = trim(htmlspecialchars(r()->request->get('login')));
        $password = trim(htmlspecialchars(r()->request->get('password')));
        $email = trim(htmlspecialchars(r()->request->get('email')));
        $accept = trim(htmlspecialchars(r()->request->get('accept', '')));

        if (empty($password) || empty($email)) {
            $errors[] = _t('auth.login_form.error.empty_credentials');
        }

        if (($passwordLength = strlen($password)) < 6 || $passwordLength > 50) {
            $errors[] = _t('auth.login_form.error.password_length', ['min' => 6, 'max' => 50]);
        }

        if ('on' !== $accept) {
            $errors[] = _t('auth.login_form.error.terms_not_accepted');
        }

        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            $errors[] = _t('auth.login_form.error.email_already_exists');
        }

        if (null !== $this->userRepository->findOneBy(['login' => $login])) {
            $errors[] = _t('auth.login_form.error.login_already_exists');
        }

        if (isset($errors)) {
            return $this->redirectBack(errors: $errors);
        }

        $user = new (Kernel::getApplicationUserClassName())(false);
        $user->setLogin($login);
        $user->setEmail($email);
        $user->setPassword($password);

        if (true !== $user->validate()) {
            return $this->redirectBack(errors: array_values($user->getValidationErrors()));
        }

        $this->userRepository->persist($user);

        auth::logInUser($user);

        return $this->redirectTo('welcome_page');
    }
}
