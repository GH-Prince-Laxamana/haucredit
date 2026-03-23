<?php
/**
 * NOTE: THIS IS AN OPTIONAL FILE
 * This file is included by database.php when seeding is required.
 * It can also be called manually by developers for testing purposes.
 * 
 * Database Configuration Seeding Script
 * 
 * This script initializes the database with default configuration data including:
 * - Requirement templates for different event types
 * - Organization options (bodies/clubs)
 * - Event background classifications
 * - Event activity type categories
 * - Event series grouping options
 * - Requirements mapping (linking requirements to event background/activity combinations)
 * 
 * The script uses ON DUPLICATE KEY UPDATE for idempotent operations, allowing
 * it to be run multiple times safely without creating duplicate data.
 */

// Import required database and utility functions
// Only require if not already loaded (to avoid conflicts when called from database.php)
if (!function_exists('fetchOne')) {
    require_once 'error.php';
    require_once 'query_builder_functions.php';
}

if (!isset($conn)) {
    require_once 'database.php';
}

// ========== Load Configuration Data ==========
// Import configuration arrays from external config files
// These arrays define the base data to seed into the database
$requirements_map = require_once "config/requirements_map.php";
$org_options = require_once "config/org_options.php";
$activity_types = require_once "config/activity_types.php";
$series_options = require_once "config/series_options.php";
$requirement_list = require_once "config/requirement_list.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ========== Begin Database Transaction ==========
// Wrap all seeding operations in a transaction for atomicity
// If any operation fails, all changes will be rolled back
try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("Database connection is not available.");
    }

    $conn->begin_transaction();

    // ========== SEED: DEFAULT REQUIREMENT TEMPLATES ==========
    // Requirement templates define what documents/submissions are needed for events
    // These are the master templates that get associated with specific event types
    
    // Extract requirement data from the loaded configuration
    $default_requirements = $requirement_list['default_requirements'] ?? [];
    $default_requirements_descs = $requirement_list['default_requirements_descs'] ?? [];
    $requirements_templates = $requirement_list['requirements_templates'] ?? [];

    // SQL template for upserting requirement templates
    // Uses ON DUPLICATE KEY UPDATE to update existing records instead of failing
    // This allows the script to be run multiple times idempotently
    $templateUpsertSql = "
        INSERT INTO requirement_templates (
            req_name,
            req_desc,
            template_url,
            default_due_offset_days,
            default_due_basis,
            is_active
        )
        VALUES (?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            req_desc = VALUES(req_desc),
            template_url = VALUES(template_url),
            default_due_offset_days = VALUES(default_due_offset_days),
            default_due_basis = VALUES(default_due_basis),
            is_active = VALUES(is_active),
            updated_at = CURRENT_TIMESTAMP
    ";

    // Iterate through each default requirement and insert into database
    foreach ($default_requirements as $req_name) {
        // Get the description for this requirement, default to null if not defined
        $req_desc = $default_requirements_descs[$req_name] ?? null;
        
        // Get the template URL for this requirement, default to null if not defined
        $template_url = $requirements_templates[$req_name] ?? null;
        
        // Set default offset days for deadline calculation
        $offset_days = 7;
        
        // Set default basis for deadline calculation (before event start)
        $basis = 'before_start';

        // Special case: Narrative Report is due after the event ends
        if ($req_name === 'Narrative Report') {
            $basis = 'after_end';
        }

        // Execute the upsert query to insert or update the requirement template
        execQuery(
            $conn,
            $templateUpsertSql,
            "sssis",
            [$req_name, $req_desc, $template_url, $offset_days, $basis]
        );
    }

    // ========== DERIVE BACKGROUND OPTIONS FROM REQUIREMENTS MAP ==========
    // Extract all unique background names from the requirements mapping structure
    // This ensures backgrounds are created for all mapped background/activity/requirement combinations
    $background_options = array_keys($requirements_map);

    // ========== SEED CONFIG: ORGANIZATIONS ==========
    // Organizations represent user organizational bodies/clubs
    // These are the entities that users belong to
    foreach ($org_options as $index => $org_name) {
        execQuery(
            $conn,
            "
            INSERT INTO config_org_options (
                org_name,
                is_active,
                sort_order
            )
            VALUES (?, 1, ?)
            ON DUPLICATE KEY UPDATE
                sort_order = VALUES(sort_order),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
            ",
            "si",
            [$org_name, $index + 1]  // $index + 1 provides 1-based ordering
        );
    }

    // ========== SEED CONFIG: BACKGROUNDS ==========
    // Backgrounds are classifications for events (e.g., "Seminar", "Workshop", "Conference")
    // They determine which requirements apply to which events
    foreach ($background_options as $index => $background_name) {
        execQuery(
            $conn,
            "
            INSERT INTO config_background_options (
                background_name,
                is_active,
                sort_order
            )
            VALUES (?, 1, ?)
            ON DUPLICATE KEY UPDATE
                sort_order = VALUES(sort_order),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
            ",
            "si",
            [$background_name, $index + 1]  // $index + 1 provides 1-based ordering
        );
    }

    // ========== SEED CONFIG: ACTIVITY TYPES ==========
    // Activity types categorize what kind of activities occur within events
    // (e.g., "Lecture", "Discussion", "Hands-on Workshop", "Panel")
    foreach ($activity_types as $index => $activity_type_name) {
        execQuery(
            $conn,
            "
            INSERT INTO config_activity_types (
                activity_type_name,
                is_active,
                sort_order
            )
            VALUES (?, 1, ?)
            ON DUPLICATE KEY UPDATE
                sort_order = VALUES(sort_order),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
            ",
            "si",
            [$activity_type_name, $index + 1]  // $index + 1 provides 1-based ordering
        );
    }

    // ========== SEED CONFIG: SERIES OPTIONS ==========
    // Series options group events into related series or recurring event categories
    // (e.g., "Monthly Talks", "Annual Conference", "Weekly Meetings")
    foreach ($series_options as $index => $series_name) {
        execQuery(
            $conn,
            "
            INSERT INTO config_series_options (
                series_name,
                is_active,
                sort_order
            )
            VALUES (?, 1, ?)
            ON DUPLICATE KEY UPDATE
                sort_order = VALUES(sort_order),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
            ",
            "si",
            [$series_name, $index + 1]  // $index + 1 provides 1-based ordering
        );
    }

    // ========== SEED CONFIG: REQUIREMENTS MAP ==========
    // The requirements map defines which requirements apply to which event types
    // Structure: Background -> Activity Type -> Array of Requirement Names
    // This ensures specific event types have the right requirements
    
    foreach ($requirements_map as $background_name => $activityMap) {
        // Fetch the database ID for this background from the current seeded data
        $backgroundRow = fetchOne(
            $conn,
            "
            SELECT background_id
            FROM config_background_options
            WHERE background_name = ?
            LIMIT 1
            ",
            "s",
            [$background_name]
        );

        // Skip this background if it doesn't exist in the database
        if (!$backgroundRow) {
            continue;
        }

        // Extract the background ID from the query result
        $background_id = (int) $backgroundRow['background_id'];

        // Iterate through all activity types for this background
        foreach ($activityMap as $activity_type_name => $req_names) {
            // Fetch the database ID for this activity type
            $activityTypeRow = fetchOne(
                $conn,
                "
                SELECT activity_type_id
                FROM config_activity_types
                WHERE activity_type_name = ?
                LIMIT 1
                ",
                "s",
                [$activity_type_name]
            );

            // Skip this activity type if it doesn't exist in the database
            if (!$activityTypeRow) {
                continue;
            }

            // Extract the activity type ID from the query result
            $activity_type_id = (int) $activityTypeRow['activity_type_id'];

            // Iterate through all requirement names for this background/activity combination
            foreach ($req_names as $req_name) {
                // Fetch the database ID for this requirement template
                $templateRow = fetchOne(
                    $conn,
                    "
                    SELECT req_template_id
                    FROM requirement_templates
                    WHERE req_name = ?
                    LIMIT 1
                    ",
                    "s",
                    [$req_name]
                );

                // Skip this requirement if it doesn't exist in the database
                if (!$templateRow) {
                    continue;
                }

                // Extract the requirement template ID from the query result
                $req_template_id = (int) $templateRow['req_template_id'];

                // Create the mapping between background, activity type, and requirement
                // This determines what requirements apply to specific event types
                execQuery(
                    $conn,
                    "
                    INSERT INTO config_requirements_map (
                        background_id,
                        activity_type_id,
                        req_template_id,
                        is_active
                    )
                    VALUES (?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE
                        is_active = 1,
                        updated_at = CURRENT_TIMESTAMP
                    ",
                    "iii",
                    [$background_id, $activity_type_id, $req_template_id]
                );
            }
        }
    }

    // Commit all database changes atomically
    // All insert/update operations are now permanently stored
    $conn->commit();

} catch (Exception $e) {
    // Attempt to rollback if an error occurred during seeding
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            // Rollback all pending changes to maintain database integrity
            $conn->rollback();
        } catch (Exception $rollbackError) {
            // Suppress rollback errors - the main error will be reported
        }
    }

    // Display error message to user with details about what failed
    popup_error("Configuration Seeding Error: " . $e->getMessage());
}
?>