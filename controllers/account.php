<?php
/** Customer account controllers: register, login, logout, reset, profile, wishlist. */

declare(strict_types=1);

function login_page(): void
{
    if (current_user()) redirect('/account');
    view('auth/login', ['title' => 'Login — ' . setting('store_name')]);
}

function do_login(): void
{
    verify_csrf();
    if (!rate_limit('login:' . ($_SERVER['REMOTE_ADDR'] ?? 'na'), 8, 300)) {
        flash('Too many login attempts. Please wait 5 minutes.', 'error');
        redirect('/login');
    }
    $u = User::attempt((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
    if (!$u) {
        flash('Invalid email or password.', 'error');
        redirect('/login');
    }
    SecureCookie::set('auth', ['uid' => (int)$u['id'], 'exp' => time() + 30 * 86400], 30 * 86400, IS_PRODUCTION);
    merge_guest_cart((int)$u['id']);
    flash('Welcome back, ' . $u['name'] . '!');
    $next = $_GET['next'] ?? '/account';
    // Only allow internal redirects (open-redirect protection)
    if (!preg_match('#^/[a-z0-9/_?=&.-]*$#i', (string)$next) || str_starts_with((string)$next, '//')) $next = '/account';
    redirect($next);
}

function logout(): void
{
    SecureCookie::delete('auth', IS_PRODUCTION);
    flash('You have been logged out.');
    redirect('/');
}

function register_page(): void
{
    if (current_user()) redirect('/account');
    view('auth/register', ['title' => 'Create Account — ' . setting('store_name')]);
}

function do_register(): void
{
    verify_csrf();
    if (!rate_limit('register:' . ($_SERVER['REMOTE_ADDR'] ?? 'na'), 5, 3600)) {
        flash('Too many registrations from this network. Try later.', 'error');
        redirect('/register');
    }
    [$ok, $msg, $uid] = array_pad(
        User::register((string)($_POST['name'] ?? ''), (string)($_POST['email'] ?? ''),
                       (string)($_POST['phone'] ?? ''), (string)($_POST['password'] ?? '')),
        3, null);
    if (!$ok) { flash((string)$msg, 'error'); redirect('/register'); }

    Mailer::make()->send((string)$_POST['email'], 'Welcome to ' . setting('store_name'),
        mail_template('Welcome!', '<p>Your account is ready. Start shopping with Cash on Delivery across Pakistan.</p>'));

    SecureCookie::set('auth', ['uid' => (int)$uid, 'exp' => time() + 30 * 86400], 30 * 86400, IS_PRODUCTION);
    merge_guest_cart((int)$uid);
    flash('Account created. Welcome!');
    redirect('/account');
}

function forgot_page(): void { view('auth/forgot', ['title' => 'Reset Password']); }

function do_forgot(): void
{
    verify_csrf();
    if (!rate_limit('forgot:' . ($_SERVER['REMOTE_ADDR'] ?? 'na'), 5, 3600)) {
        flash('Too many requests. Try again later.', 'error'); redirect('/forgot-password');
    }
    $u = User::findByEmail((string)($_POST['email'] ?? ''));
    // Always show the same message (no user enumeration)
    if ($u) {
        $tok = User::setResetToken((int)$u['id']);
        $link = url('/reset-password?token=' . $tok);
        $sent = Mailer::make()->send((string)$u['email'], 'Password Reset — ' . setting('store_name'),
            mail_template('Reset your password',
                "<p>Click to reset your password (valid 1 hour):</p><p><a href=\"$link\">$link</a></p>"));
        if (!$sent && !IS_PRODUCTION) flash('Dev mode: reset link — ' . $link);
    }
    flash('If that email exists, a reset link has been sent.');
    redirect('/forgot-password');
}

function reset_page(): void
{
    $tok = (string)($_GET['token'] ?? '');
    $u = $tok ? User::byResetToken($tok) : null;
    if (!$u) { flash('Reset link is invalid or expired.', 'error'); redirect('/forgot-password'); }
    view('auth/reset', ['token' => $tok, 'user' => $u, 'title' => 'Choose New Password']);
}

function do_reset(): void
{
    verify_csrf();
    $tok = (string)($_POST['token'] ?? '');
    $u = User::byResetToken($tok);
    if (!$u) { flash('Reset link is invalid or expired.', 'error'); redirect('/forgot-password'); }
    [$ok, $msg] = User::setPassword((int)$u['id'], (string)($_POST['password'] ?? ''));
    flash((string)$msg, $ok ? 'success' : 'error');
    redirect($ok ? '/login' : '/reset-password?token=' . urlencode($tok));
}

function account_page(): void
{
    $u = require_login();
    view('auth/account', [
        'u' => $u,
        'orders' => Order::forUser((int)$u['id']),
        'title' => 'My Account — ' . setting('store_name'),
    ]);
}

function do_profile_update(): void
{
    $u = require_login();
    verify_csrf();
    [$ok, $msg] = User::updateProfile((int)$u['id'], (string)($_POST['name'] ?? ''), (string)($_POST['phone'] ?? ''));
    flash((string)$msg, $ok ? 'success' : 'error');
    redirect('/account');
}

function addresses_page(): void
{
    $u = require_login();
    view('auth/addresses', [
        'u' => $u, 'addresses' => User::addresses((int)$u['id']),
        'provinces' => pk_provinces(), 'title' => 'My Addresses',
    ]);
}

function do_address_add(): void
{
    $u = require_login();
    verify_csrf();
    [$ok, $msg] = User::addAddress((int)$u['id'], $_POST);
    flash((string)$msg, $ok ? 'success' : 'error');
    redirect('/account/addresses');
}

function do_address_delete(): void
{
    $u = require_login();
    verify_csrf();
    User::deleteAddress((int)($_POST['id'] ?? 0), (int)$u['id']);
    flash('Address removed.');
    redirect('/account/addresses');
}

function order_detail_page(string $num): void
{
    $u = require_login();
    $o = Order::findByNumber($num);
    if (!$o || (int)$o['user_id'] !== (int)$u['id']) {
        render_error_page(404, 'Order not found.'); return;
    }
    $o['history'] = Order::history((int)$o['id']);
    view('auth/order_detail', ['order' => $o, 'title' => 'Order ' . $o['order_number']]);
}

function wishlist_page(): void
{
    $u = require_login();
    $ids = User::wishlistIds((int)$u['id']);
    view('auth/wishlist', ['items' => Product::byIds($ids), 'title' => 'My Wishlist']);
}

function do_wishlist_toggle(): void
{
    $u = require_login();
    verify_csrf();
    $state = User::wishlistToggle((int)$u['id'], (int)($_POST['product_id'] ?? 0));
    if (is_api_request()) json_out(['ok' => true, 'state' => $state]);
    flash($state === 'added' ? 'Added to wishlist.' : 'Removed from wishlist.');
    redirect($_SERVER['HTTP_REFERER'] ?? '/wishlist');
}
