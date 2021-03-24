<?php

use ppil\controller\UserController;
use ppil\util\AppContainer;
use ppil\view\UserView;
use ppil\view\ViewRendering;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use \Illuminate\Database\Capsule\Manager as DB;

require __DIR__ . '/vendor/autoload.php';

$ini_file = parse_ini_file('src/conf/conf.ini');
$db = new DB();
$db->addConnection([
    'driver'    => $ini_file['driver'],
    'host'      => $ini_file['host'],
    'port'      => $ini_file['port'],
    'database'  => $ini_file['database'],
    'username'  => $ini_file['username'],
    'password'  => $ini_file['password']
]);

$db->setAsGlobal();
$db->bootEloquent();

// Creation de l'application slim
$app = AppContainer::getInstance();
$app->addRoutingMiddleware();

// Affichage des erreur (Dev Only)
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// --------------------- Creation de l'index (page pricipale) ---------------------
$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write(ViewRendering::render("Hello, world!", "Home page"));
    return $response;
})->setName('root');


// --------------------- Creation d'un compte ---------------------
// Get (obtenir la page web)
$app->get('/accounts/sin-up', function (Request $request, Response $response, $args) {
    $response->getBody()->write(UserView::creerUnCompte());
    return $response;
})->setName('sin-up');
// Post géré les donnée entré par l'utilisateur
$app->post('/accounts/sin-up', function (Request $request, Response $response, $args) {
    return $response;
})->setName('sin-up_post');


// --------------------- Connexion a un compte ---------------------
// Get (obtenir la page web)
$app->get('/accounts/sin-in', function (Request $request, Response $response, $args) {
    $response->getBody()->write(UserView::seConnecter());
    return $response;
})->setName('sin-in');
// Post géré les donnée entré par l'utilisateur
$app->post('/accounts/sin-in', function (Request $request, Response $response, $args) {
    $response->getBody()->write(UserController::seConnecter());
    return $response;
})->setName('sin-in_post');

// --------------------- Se deconnecté ---------------------
$app->post('/accounts/logout', function (Request $request, Response $response, $args) {
    UserController::seDeconnecter();
    return $response;
})->setName('logout');

// --------------------- Mot de passe oublié ---------------------
// Get (obtenir la page web)
$app->get('/accounts/password-forgotten', function (Request $request, Response $response, $args) {
    $response->getBody()->write(UserView::motDePasseOublie());
    return $response;
})->setName('password-forgotten');
// Post géré les donnée entré par l'utilisateur
$app->post('/accounts/password-forgotten', function (Request $request, Response $response, $args) {
    $response->getBody()->write(UserController::mdpOublie());
    return $response;
})->setName('password-forgotten_post');

// Get formulaire de modification de mot de passe
$app->get('/accounts/password-forgotten/{key}', function (Request $request, Response $response, $args) {
    $response->getBody()->write(UserView::motDePasseOublieForm($args['key']));
    return $response;
})->setName('password-forgotten-key');
// Post formulaire de modification de mot de passe
$app->get('/accounts/password-forgotten/{key}', function (Request $request, Response $response, $args) {
    $response->getBody()->write(UserController::recupererMdp($args['key']));
    return $response;
})->setName('password-forgotten-key_post');


// --------------------- Mofifier mon profil ---------------------
// Get (obtenir la page web)
$app->get('/accounts/edit-profile', function (Request $request, Response $response, $args) {
    $response->getBody()->write(UserView::modifierProfil());
    return $response;
})->setName('edit-profile');
// Post géré les donnée entré par l'utilisateur
$app->post('/accounts/edit-profile', function (Request $request, Response $response, $args) {
    return $response;
})->setName('edit-profile_post');

// Demarais l'appliquation web
$app->run();