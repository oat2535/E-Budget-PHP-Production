# Project Rules & Workflow Instructions

## 1. Branch & Deployment Policy (PHP 8.3 & Laravel 13)
- **UAT & Development Environment (`c:\E-Budget - PHP`)**: Commit and push directly to branch **`main`** (and sync to branch **`uat`** for UAT server).
- **Production Environment (`c:\E-Budget_PRD_PHP`)**: Commit and push directly to branch **`main`** of `https://github.com/oat2535/E-Budget-PHP-Production.git`.
- **Legacy Framework Status**: Python Django framework has been officially decommissioned and sunsetted. All ongoing operations are strictly PHP 8.3 & Laravel 13.

## 2. Process Cleanup & Dev Server Lifecycle
- On Windows development environments, `php artisan serve` can leave orphaned background processes bound to port 8000 (or custom port).
- **Rule**: Prior to testing or restarting the dev server, always check and kill any lingering processes holding the target port (e.g. `Stop-Process -Id <pid> -Force`).

## 3. Laravel Caching & Optimization
- In production (`APP_ENV=production`):
  1. Run `php artisan config:cache` to optimize configuration loading.
  2. Run `php artisan route:cache` to compile route mappings.
  3. Run `php artisan view:cache` to precompile Blade templates into native PHP.
- When modifying templates or configuration in development:
  1. Run `php artisan optimize:clear` (clears cache, routes, views, config).
  2. Bump version query string on CSS/JS links (e.g. `?v=YYYYMMDD_XX`) to prevent client-side browser caching.

## 4. Best Practices & Stability
- Adhere strictly to clean architecture, performance optimization, and rock-solid stability.
- Always verify changes end-to-end via automated tests (`php artisan test`) before committing and pushing.
- Never alter or drop existing PostgreSQL tables in `imedx_tlp_for_it` (`intra_tl`) or `imedx_uat` (`public`).
