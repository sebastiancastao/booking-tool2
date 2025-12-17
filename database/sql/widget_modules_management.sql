-- Widget Module Management SQL Queries for Supabase PostgreSQL
-- Run these in your Supabase SQL Editor
-- Compatible with both json and jsonb column types

-- ===========================================
-- 1. VIEW CURRENT MODULE SELECTIONS
-- ===========================================

-- View all widgets and their enabled modules
SELECT
    id,
    name,
    company_name,
    status,
    json_array_length(enabled_modules::json) as module_count,
    enabled_modules
FROM widgets
ORDER BY id;

-- View specific widget's modules in a readable format
SELECT
    id,
    name,
    json_array_elements_text(enabled_modules::json) as enabled_module
FROM widgets
WHERE id = 1;

-- ===========================================
-- 2. UPDATE MODULE SELECTIONS
-- ===========================================

-- Update widget to have ALL modules enabled
UPDATE widgets
SET enabled_modules = '["service-selection","service-type","location-type","project-scope","date-selection","time-selection","origin-location","origin-challenges","target-location","target-challenges","distance-calculation","additional-services","supply-inquiry","supply-selection","contact-info","review-quote","chat-integration"]'::json,
updated_at = NOW()
WHERE id = 1;

-- Update ALL widgets to have all modules enabled
UPDATE widgets
SET enabled_modules = '["service-selection","service-type","location-type","project-scope","date-selection","time-selection","origin-location","origin-challenges","target-location","target-challenges","distance-calculation","additional-services","supply-inquiry","supply-selection","contact-info","review-quote","chat-integration"]'::json,
updated_at = NOW();

-- Update widget with BASIC modules only (minimal setup)
UPDATE widgets
SET enabled_modules = '["service-selection","contact-info","review-quote"]'::json,
updated_at = NOW()
WHERE id = 1;

-- Update widget with CUSTOM module selection
UPDATE widgets
SET enabled_modules = '["service-selection","project-scope","date-selection","contact-info","review-quote"]'::json,
updated_at = NOW()
WHERE id = 1;

-- Add a single module to existing modules (without removing others)
-- Note: For json type, we need to parse, modify, and re-encode
UPDATE widgets
SET enabled_modules = (
    SELECT json_agg(elem)
    FROM (
        SELECT elem
        FROM json_array_elements_text(enabled_modules::json) elem
        UNION
        SELECT 'chat-integration'
    ) subq
)::json,
updated_at = NOW()
WHERE id = 1
AND NOT enabled_modules::text LIKE '%"chat-integration"%';

-- Remove a specific module from enabled modules
UPDATE widgets
SET enabled_modules = (
    SELECT json_agg(elem)
    FROM json_array_elements_text(enabled_modules::json) elem
    WHERE elem != 'chat-integration'
)::json,
updated_at = NOW()
WHERE id = 1;

-- ===========================================
-- 3. QUERY WIDGETS BY MODULES
-- ===========================================

-- Find all widgets that have a specific module enabled
SELECT id, name, company_name
FROM widgets
WHERE enabled_modules::text LIKE '%"chat-integration"%';

-- Find widgets that DON'T have a specific module
SELECT id, name, company_name
FROM widgets
WHERE NOT (enabled_modules::text LIKE '%"chat-integration"%');

-- Find widgets with more than N modules enabled
SELECT id, name, json_array_length(enabled_modules::json) as module_count
FROM widgets
WHERE json_array_length(enabled_modules::json) > 10;

-- Find widgets with less than N modules enabled
SELECT id, name, json_array_length(enabled_modules::json) as module_count
FROM widgets
WHERE json_array_length(enabled_modules::json) < 5;

-- ===========================================
-- 4. MODULE ANALYTICS
-- ===========================================

-- Count how many widgets use each module
SELECT
    module_name,
    COUNT(*) as widget_count
FROM widgets,
    json_array_elements_text(enabled_modules::json) as module_name
GROUP BY module_name
ORDER BY widget_count DESC;

-- Get most popular module combinations
SELECT
    enabled_modules::text as module_combination,
    COUNT(*) as usage_count
FROM widgets
GROUP BY enabled_modules::text
ORDER BY usage_count DESC;

-- ===========================================
-- 5. VALIDATION & DEBUGGING
-- ===========================================

-- Check for widgets with invalid/empty module arrays
SELECT id, name, enabled_modules
FROM widgets
WHERE enabled_modules IS NULL
   OR enabled_modules::text = '[]'
   OR json_array_length(enabled_modules::json) = 0;

-- Verify all widgets have required modules (service-selection, contact-info, review-quote)
SELECT
    id,
    name,
    enabled_modules::text LIKE '%"service-selection"%' as has_service_selection,
    enabled_modules::text LIKE '%"contact-info"%' as has_contact_info,
    enabled_modules::text LIKE '%"review-quote"%' as has_review_quote
FROM widgets;

-- Find widgets missing required modules
SELECT id, name, enabled_modules
FROM widgets
WHERE NOT (
    enabled_modules::text LIKE '%"service-selection"%'
    AND enabled_modules::text LIKE '%"contact-info"%'
    AND enabled_modules::text LIKE '%"review-quote"%'
);

-- ===========================================
-- 6. BULK OPERATIONS
-- ===========================================

-- Reset all draft widgets to default modules
UPDATE widgets
SET enabled_modules = '["service-selection","contact-info","review-quote"]'::json,
updated_at = NOW()
WHERE status = 'draft';

-- Clone module selection from one widget to another
UPDATE widgets
SET enabled_modules = (
    SELECT enabled_modules
    FROM widgets
    WHERE id = 1
),
updated_at = NOW()
WHERE id = 2;

-- ===========================================
-- 7. CREATE HELPER FUNCTIONS (OPTIONAL)
-- ===========================================

-- Function to add a module if not exists
CREATE OR REPLACE FUNCTION add_module_to_widget(
    widget_id INTEGER,
    module_name TEXT
)
RETURNS VOID AS $$
BEGIN
    UPDATE widgets
    SET enabled_modules = CASE
        WHEN enabled_modules::text LIKE '%"' || module_name || '"%'
        THEN enabled_modules
        ELSE (
            SELECT json_agg(elem)
            FROM (
                SELECT elem
                FROM json_array_elements_text(enabled_modules::json) elem
                UNION
                SELECT module_name
            ) subq
        )::json
    END,
    updated_at = NOW()
    WHERE id = widget_id;
END;
$$ LANGUAGE plpgsql;

-- Usage: SELECT add_module_to_widget(1, 'chat-integration');

-- Function to remove a module
CREATE OR REPLACE FUNCTION remove_module_from_widget(
    widget_id INTEGER,
    module_name TEXT
)
RETURNS VOID AS $$
BEGIN
    UPDATE widgets
    SET enabled_modules = (
        SELECT json_agg(elem)
        FROM json_array_elements_text(enabled_modules::json) elem
        WHERE elem != module_name
    )::json,
    updated_at = NOW()
    WHERE id = widget_id;
END;
$$ LANGUAGE plpgsql;

-- Usage: SELECT remove_module_from_widget(1, 'chat-integration');

-- ===========================================
-- 8. QUICK REFERENCE - COMMON OPERATIONS
-- ===========================================

-- Enable ALL modules for widget #1
-- UPDATE widgets SET enabled_modules = '["service-selection","service-type","location-type","project-scope","date-selection","time-selection","origin-location","origin-challenges","target-location","target-challenges","distance-calculation","additional-services","supply-inquiry","supply-selection","contact-info","review-quote","chat-integration"]'::json, updated_at = NOW() WHERE id = 1;

-- View widget #1 modules
-- SELECT id, name, enabled_modules FROM widgets WHERE id = 1;

-- Count modules in widget #1
-- SELECT id, name, json_array_length(enabled_modules::json) as count FROM widgets WHERE id = 1;
