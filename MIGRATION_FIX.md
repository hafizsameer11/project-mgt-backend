# Migration Fix for Purchase Orders Foreign Key Error

## Problem

The migration `2025_12_07_091910_create_purchase_orders_table` was failing with:
```
SQLSTATE[HY000]: General error: 1215 Cannot add foreign key constraint
```

## Root Causes

1. **Duplicate Migration**: Two `create_purchase_orders_table` migrations existed with timestamps `091910` and `091912`
2. **Same Timestamp**: `vendors` and `purchase_orders` both had timestamp `091910`, causing potential execution order issues
3. **Foreign Key Constraint**: The foreign key was being added before ensuring the `vendors` table exists

## Fixes Applied

### 1. Removed Duplicate Migration
- Deleted: `2025_12_07_091910_create_purchase_orders_table.php`
- Kept: `2025_12_07_091912_create_purchase_orders_table.php` (runs after vendors)

### 2. Improved Migration Safety
Updated `2025_12_07_091912_create_purchase_orders_table.php` to:
- Create columns first without foreign keys
- Add foreign keys separately after checking parent tables exist
- Use conditional checks for table existence

### 3. Created Fix Migration
Created `2025_12_11_000000_fix_purchase_orders_foreign_keys.php` to:
- Fix any existing broken foreign keys in production
- Safely add foreign keys if they're missing
- Handle partial migration states

## How to Apply in Production

### Option 1: If Migration Partially Ran

1. **Check current state:**
   ```bash
   php artisan migrate:status
   ```

2. **If purchase_orders table exists but foreign keys are missing:**
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
-- Drop broken foreign keys
ALTER TABLE purchase_orders DROP FOREIGN KEY IF EXISTS purchase_orders_vendor_id_foreign;
ALTER TABLE purchase_orders DROP FOREIGN KEY IF EXISTS purchase_orders_project_id_foreign;
ALTER TABLE purchase_orders DROP FOREIGN KEY IF EXISTS purchase_orders_created_by_foreign;

-- Add foreign keys correctly
ALTER TABLE purchase_orders 
ADD CONSTRAINT purchase_orders_vendor_id_foreign 
FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE;

ALTER TABLE purchase_orders 
ADD CONSTRAINT purchase_orders_project_id_foreign 
FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;

ALTER TABLE purchase_orders 
ADD CONSTRAINT purchase_orders_created_by_foreign 
FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE;
```

Then mark the migration as run:
```bash
php artisan migrate --pretend
```

## Verification

After applying the fix, verify:

```sql
-- Check foreign keys exist
SHOW CREATE TABLE purchase_orders;

-- Or check constraints
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'purchase_orders'
AND REFERENCED_TABLE_NAME IS NOT NULL;
```

## Files Changed

1. ✅ Deleted: `2025_12_07_091910_create_purchase_orders_table.php` (duplicate)
2. ✅ Updated: `2025_12_07_091912_create_purchase_orders_table.php` (safer foreign key handling)
3. ✅ Created: `2025_12_11_000000_fix_purchase_orders_foreign_keys.php` (fix migration)

## Prevention

For future migrations:
- Always use unique timestamps
- Check parent tables exist before adding foreign keys
- Use conditional foreign key creation
- Test migrations in order

