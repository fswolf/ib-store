<?php
// Home Page
Flight::route('/', array(new HomeController(), 'home'));

// Error Pages
Flight::route('/404', array(new HomeController(), 'not_found'));
Flight::route('/500', array(new HomeController(), 'internal_error'));
Flight::route('/contact', array(new HomeController(), 'contact'));

// Product Pages
Flight::route('/items/', array(new ProductsController(), 'index'));
Flight::route('/items/*', array(new ProductsController(), 'category'));
Flight::route('/item/@uuid', array(new ProductsController(), 'product'));
Flight::route('/search/@item', array(new ProductsController(), 'search'));

// Cart & Checkout pages
Flight::route('/cart', array(new CartController(), 'cart'));
Flight::route('/cart/checkout', array(new CartController(), 'check_out'));
Flight::route('/cart/payment/@order', array(new CartController(), 'payment'));

// Signin
Flight::route('/signin', array(new AuthController(), 'signin'));
Flight::route('/signout', array(new AuthController(), 'signout'));
Flight::route('/register', array(new AuthController(), 'register'));
Flight::route('/forgot', array(new AuthController(), 'forgot'));
Flight::route('/reset/@token', array(new AuthController(), 'reset'));
Flight::route('/verify/@id:[0-9]+/@token', array(new AuthController(), 'verify'));

// Profile
Flight::route('/user/settings', array(new AccountController(), 'settings'));
Flight::route('/user/orders', array(new AccountController(), 'orders'));
Flight::route('/user/orders/@uuid', array(new AccountController(), 'order'));
Flight::route('/user/orders/@uuid/download/@item:[0-9]+', array(new AccountController(), 'download'));

// Admin (is_admin accounts only, see BaseController::requireAdmin)
Flight::route('/admin', array(new AdminController(), 'dashboard'));
Flight::route('/admin/products', array(new AdminController(), 'products'));
Flight::route('/admin/products/new', array(new AdminController(), 'product'));
Flight::route('/admin/products/@id:[0-9]+', array(new AdminController(), 'product'));
Flight::route('/admin/categories', array(new AdminController(), 'categories'));
Flight::route('/admin/orders', array(new AdminController(), 'orders'));
Flight::route('/admin/orders/@uuid', array(new AdminController(), 'order'));
Flight::route('/admin/accounts', array(new AdminController(), 'accounts'));
Flight::route('/admin/settings', array(new AdminController(), 'settings'));

// Internal API
Flight::route('GET /api/items/*', array(new ProductsApiController(), 'handle'));
Flight::route('GET /api/store/*', array(new StoreApiController(), 'handle'));
Flight::route('POST /api/auth/signin', array(new AuthApiController(), 'signin'));
Flight::route('POST /api/auth/signout', array(new AuthApiController(), 'signout'));
Flight::route('POST /api/auth/register', array(new AuthApiController(), 'register'));
Flight::route('POST /api/auth/forgot', array(new AuthApiController(), 'forgot'));
Flight::route('POST /api/auth/reset', array(new AuthApiController(), 'reset'));
Flight::route('GET /api/cart/items', array(new CartApiController(), 'handle'));
Flight::route('POST /api/cart/*', array(new CartApiController(), 'handle'));
Flight::route('GET /api/account/addresses', array(new AccountApiController(), 'handle'));
Flight::route('POST /api/account/*', array(new AccountApiController(), 'handle'));
Flight::route('POST /api/admin/*', array(new AdminApiController(), 'handle'));
Flight::route('POST /api/payment/*', array(new PaymentApiController(), 'handle'));

?>
