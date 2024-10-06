<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->set404Override('App\Controllers\Home::not_found');

$routes->get('/login', 'Login::index');
$routes->get('/logout', 'Login::logout');

$routes->get('', 'Home::index');
$routes->get('/', 'Home::index');
$routes->get('/profil', 'Home::profil');
$routes->get('/profil/(:num)', 'Home::profil/$1');

$routes->get('/points/training', 'Home::training');
$routes->get('/points/correct_point', 'Home::correct_point');
$routes->get('/points/correct_training', 'Home::correct_training');
$routes->get('/points/select_training', 'Home::select_training');
$routes->get('/points/modify_training', 'Home::modify_training');
$routes->get('/points/warning', 'Home::warning');
$routes->get('/points/blame', 'Home::blame');
$routes->get('/points/work', 'Home::work');
$routes->get('/operation_success', 'Training::operation_success');

$routes->post('/login', 'Login::api_login');
$routes->post('/points/add_training', 'Training::add_training');
$routes->post('/points/add_correct_point', 'Training::add_correct_point');
$routes->post('/points/add_blame', 'Training::add_blame');
$routes->post('/points/add_work', 'Training::add_work');
$routes->post('/points/add_warning', 'Training::add_warning');
$routes->post('/points/modify_training_presence', 'Training::modify_training_presence');