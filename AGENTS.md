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

## 5. Domain & Business Rules
- **Expense (GL Entry / ค่าใช้จ่ายต่างๆ / C02 Expense) GL Filtering Rule**:
  - ในหน้า Expense (ทั้งหน้าสร้าง `/add_gl_entry`, หน้าแก้ไขเอกสาร C02 / GL Entry, และ API `/api/general_ledgers` สำหรับ `GL Entry` และ `EXPENSE`) **จะต้องกรองเอา GL ที่ขึ้นต้นด้วยคำว่า `ค่าเสื่อม%` ออกทั้งหมด (Exclude)** ห้ามนำมาแสดงเป็นตัวเลือกโดยเด็ดขาด เนื่องจากค่าเสื่อมราคาจะถูกคำนวณและจัดการจากฝั่ง Asset และระบบคำนวณเฉพาะแยกต่างหาก
  - ใช้ `BudgetService::getGeneralLedgersJson(null, null, 'ค่าเสื่อม')` เสมอ ห้ามส่ง `'ค่าเสื่อม'` เป็น parameter ตัวที่สอง (`$nameStartswith`) เป็นอันขาด
