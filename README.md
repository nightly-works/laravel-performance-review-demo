# Atelier back office

The order back office of a small online shop: staff see the orders to ship, mark them shipped (the customer gets an email), export every order as CSV for the accountant and follow revenue month by month. Laravel 13, PHP 8.3.

```
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
```
