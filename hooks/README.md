# hooks/

PHP-filer i denne mappe (`*.php`) indlæses af `index.php` lige efter et vellykket login,
og kan bruge `add_action('user_login_sync', function ($user) { ... });`.

Moduler bør lægge deres egne hooks i modulets `init.php` i stedet for her.

PHP files in this folder are loaded right after a successful login. Modules should register
their hooks in their own `init.php` instead.
