<?php
use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Dashboard
$routes->get('/', 'Dashboard::index');
$routes->get('dashboard', 'Dashboard::index');
$routes->get('dashboard/hrd', 'Dashboard::hrd', ['filter' => 'hrd']);
$routes->get('dashboard/api_stats', 'Dashboard::api_stats', ['filter' => 'hrd']);

// Auth
$routes->get('auth/login', 'Auth::login');
$routes->get('auth/register', 'Auth::register');
$routes->post('auth/doLogin', 'Auth::doLogin');
$routes->post('auth/doRegister', 'Auth::doRegister');
$routes->get('auth/logout', 'Auth::logout');
$routes->get('auth/forgot', 'Auth::forgot');
$routes->post('auth/doForgot', 'Auth::doForgot');
$routes->get('auth/resetPassword', 'Auth::resetPassword');
$routes->get('auth/changePassword', 'Auth::changePassword');
$routes->post('auth/doChangePassword', 'Auth::doChangePassword');

// Jobs
$routes->get('jobs', 'Jobs::index');
$routes->get('jobs/detail/(:num)', 'Jobs::detail/$1');
$routes->get('jobs/apply/(:num)', 'Jobs::apply/$1', ['filter' => 'auth']);
$routes->post('jobs/doApply', 'Jobs::doApply', ['filter' => 'auth']);
$routes->get('jobs/manage', 'Jobs::manage', ['filter' => 'admin']);
$routes->get('jobs/form', 'Jobs::form', ['filter' => 'admin']);
$routes->get('jobs/form/(:num)', 'Jobs::form/$1', ['filter' => 'admin']);
$routes->post('jobs/save', 'Jobs::save', ['filter' => 'admin']);
$routes->get('jobs/delete/(:num)', 'Jobs::delete/$1', ['filter' => 'admin']);
$routes->get('jobs/toggleStatus/(:num)', 'Jobs::toggleStatus/$1', ['filter' => 'admin']);
$routes->post('jobs/toggleStatus/(:num)', 'Jobs::toggleStatus/$1', ['filter' => 'admin']);

// Applications
$routes->get('applications', 'Applications::index', ['filter' => 'auth']);
$routes->get('applications/detail/(:num)', 'Applications::detail/$1', ['filter' => 'auth']);
$routes->get('applications/hrd', 'Applications::hrd', ['filter' => 'hrd']);
$routes->post('applications/updateStatus', 'Applications::updateStatus', ['filter' => 'hrd']);
$routes->post('applications/saveNotes', 'Applications::saveNotes', ['filter' => 'hrd']);
$routes->post('applications/addNote', 'Applications::addNote', ['filter' => 'hrd']);
$routes->post('applications/applyRecommendation', 'Applications::applyRecommendation', ['filter' => 'hrd']);
$routes->get('applications/downloadCv/(:num)', 'Applications::downloadCv/$1', ['filter' => 'auth']);

// Chat
$routes->get('chat', 'Chat::index', ['filter' => 'auth']);
$routes->get('chat/room/(:num)', 'Chat::room/$1', ['filter' => 'auth']);
$routes->post('chat/send', 'Chat::send', ['filter' => 'auth']);
$routes->get('chat/start/(:num)', 'Chat::start/$1', ['filter' => 'hrd']);
$routes->get('chat/userStart/(:num)', 'Chat::userStart/$1', ['filter' => 'auth']);
$routes->get('chat/getMessages/(:num)', 'Chat::getMessages/$1', ['filter' => 'auth']);
$routes->post('chat/editMessage', 'Chat::editMessage', ['filter' => 'auth']);
$routes->post('chat/deleteMessage', 'Chat::deleteMessage', ['filter' => 'auth']);

// Profile
$routes->get('profile', 'Profile::index', ['filter' => 'auth']);
$routes->get('profile/edit', 'Profile::edit', ['filter' => 'auth']);
$routes->post('profile/update', 'Profile::update', ['filter' => 'auth']);
$routes->post('profile/updateAvatar', 'Profile::updateAvatar', ['filter' => 'auth']);
$routes->get('profile/password', 'Profile::password', ['filter' => 'auth']);
$routes->post('profile/updatePassword', 'Profile::updatePassword', ['filter' => 'auth']);
