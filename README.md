# Laravel performance review (demo)

A proof piece for performance reviews of Laravel apps. A fictional shop back office written for this demo (Laravel 13, PHP 8.3) with five slow spots, each measured on a seeded copy with 20,000 orders and written as a budget test (queries, index use, models loaded, streaming, queueing) that the app as delivered exceeds and the fixed app keeps.

| # | Slow spot | Before | After |
|---|---|---|---|
| 1 | The order list runs two queries per order (N+1) | 102 queries, 83 ms | 4 queries, 6 ms |
| 2 | The order list scans the whole orders table | full scan and temporary sort | index search |
| 3 | The revenue report adds up the year in PHP | 30,225 models, 414 ms, 51 MB | 1 query, 13 ms |
| 4 | The CSV export builds the whole file in memory | 119 MB; fails from about 15,000 orders at a 128 MB limit | 3.7 MB up to 100,000 orders |
| 5 | Marking an order shipped waits on the mail server | email sent in the request | email queued after commit |

Full write-up, as a client receives it: [docs/performance-review.md](docs/performance-review.md).

## History

1. `as-delivered` (tag): the app as written for the demo, slow spots included.
2. The five findings as budget tests, and the measuring script: all five tests fail on that code.
3. One commit per fix, each turning one test green.

## Run

PHP 8.3 and Composer:

```
composer install
cp .env.example .env && php artisan key:generate
php artisan test               # fixed app: every budget kept, pages and figures right
./scripts/prove-flaws.sh       # app as delivered: every budget exceeded
./scripts/compare.sh           # before and after on a 20,000-order shop
```

CI runs all three, plus `composer audit`.

[![checks](https://github.com/nightly-works/laravel-performance-review-demo/actions/workflows/checks.yml/badge.svg)](https://github.com/nightly-works/laravel-performance-review-demo/actions/workflows/checks.yml)

Built by an AI agent (Claude) at [Nightly Works](https://github.com/nightly-works). MIT licence.
