# Migration Fix for Payroll Foreign Key Error

## Problem

The migration `2025_12_07_091914_create_payroll_items_table` was failing with:
```
SQLSTATE[HY000]: General error: 1215 Cannot add foreign key constraint
```

## Root Causes

1. **Duplicate Migrations**: Two sets of payroll migrations existed:
   - `2025_12_07_091914_*` (duplicates, same timestamp)
   - `2025_12_07_091916_*` and `2025_12_07_091917_*` (correct, different timestamps)

2. **Same Timestamp**: Both `payroll` and `payroll_items` had timestamp `091914`, causing potential execution order issues

3. **Foreign Key Constraint**: The foreign key was being added before ensuring the `payroll` table exists

## Fixes Applied

### 1. Removed Duplicate Migrations
- Deleted: `2025_12_07_091914_create_payroll_table.php`
- Deleted: `2025_12_07_091914_create_payroll_items_table.php`
- Kept: `2025_12_07_091916_create_payroll_table.php` (runs first)
- Kept: `2025_12_07_091917_create_payroll_items_table.php` (runs after payroll)

### 2. Improved Migration Safety
Updated both migrations to:
- Create columns first without foreign keys
- Add foreign keys separately after checking parent tables exist
- Use conditional checks for table existence

### 3. Created Fix Migration
Created `2025_12_11_000001_fix_payroll_items_foreign_keys.php` to:
- Fix any existing broken foreign keys in production
- Safely add foreign keys if they're missing
- Handle partial migration states

## How to Apply in Production

### Option 1: If Migration Partially Ran

1. **Check current state:**
   ```bash
   php artisan migrate:status
   ```

2. **If payroll_items table exists but foreign keys are missing:**
   ```bash
   php artisan migrate
   ```
   This will run the fix migration.

### Option 2: If Migration Failed Completely

1. **Rollback if needed:**
   ```bash
   php artisan migrate:rollback --step=1
   ```

2. **Run migrations again:**
   ```bash
   php artisan migrate
   ```

### Option 3: Manual Fix (if needed)

If the table exists but foreign keys are broken:

```sql
-- Drop broken foreign key
ALTER TABLE payroll_items DROP FOREIGN KEY IF EXISTS payroll_items_payroll_id_foreign;

-- Add foreign key correctly
ALTER TABLE payroll_items 
ADD CONSTRAINT payroll_items_payroll_id_foreign 
FOREIGN KEY (payroll_id) REFERENCES payroll(id) ON DELETE CASCADE;
```

Then mark the migration as run:
```bash
php artisan migrate --pretend
```

## Verification

After applying the fix, verify:

```sql
-- Check foreign keys exist
SHOW CREATE TABLE payroll_items;

-- Or check constraints
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'payroll_items'
AND REFERENCED_TABLE_NAME IS NOT NULL;
```

## Files Changed

1. ✅ Deleted: `2025_12_07_091914_create_payroll_table.php` (duplicate)
2. ✅ Deleted: `2025_12_07_091914_create_payroll_items_table.php` (duplicate)
3. ✅ Updated: `2025_12_07_091916_create_payroll_table.php` (safer foreign key handling)
4. ✅ Updated: `2025_12_07_091917_create_payroll_items_table.php` (safer foreign key handling)
5. ✅ Created: `2025_12_11_000001_fix_payroll_items_foreign_keys.php` (fix migration)

## Migration Order (Correct)

1. `2025_12_07_091916_create_payroll_table.php` - Creates payroll table
2. `2025_12_07_091917_create_payroll_items_table.php` - Creates payroll_items table (references payroll)
3. `2025_12_11_000001_fix_payroll_items_foreign_keys.php` - Fixes any broken foreign keys

