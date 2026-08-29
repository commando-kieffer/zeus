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

$routes->get('/points/correct_point', 'Home::correct_point');
$routes->get('/points/correct_training', 'Home::correct_training');
$routes->get('/points/select_training', 'Home::select_training');
$routes->get('/points/modify_training', 'Home::modify_training');
$routes->get('/points/warning', 'Home::warning');
$routes->get('/points/blame', 'Home::blame');
$routes->get('/points/work', 'Home::work');
$routes->get('/operation_success', 'Training::operation_success');

$routes->get('/operations', 'Operation::index');
$routes->get('/operations/create', 'Operation::create');
$routes->get('/operations/report', 'Operation::report');
$routes->get('/operations/report/(:num)', 'Operation::report_form/$1');
$routes->get('/operations/(:num)', 'Operation::show/$1');
$routes->get('/upload/galerie', 'Upload::gallery');

$routes->post('/login', 'Login::api_login');
$routes->post('/points/add_correct_point', 'Training::add_correct_point');
$routes->post('/points/add_blame', 'Training::add_blame');
$routes->post('/points/add_work', 'Training::add_work');
$routes->post('/points/add_warning', 'Training::add_warning');
$routes->post('/points/modify_training_presence', 'Training::modify_training_presence');
$routes->post('/operations/create', 'Operation::store');
$routes->post('/operations/report/(:num)', 'Operation::submit_report/$1');
$routes->post('/operations/(:num)/update_report', 'Operation::update_report/$1');
$routes->post('/upload/galerie', 'Upload::store_gallery');

