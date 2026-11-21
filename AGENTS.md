# Project Rules & Workflow Instructions

## 1. Branch & Deployment Policy
- **UAT Environment (`c:\E-Budget`)**: Commit and push directly to branch **`uat`**.
- **Production Environment (`c:\E-Budget_PRD`)**: Commit and push directly to branch **`main`**.
- **Migration Branch (`migration/laravel13`)**: Dedicated development and verification branch for PHP 8.3 & Laravel 13 migration.

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
