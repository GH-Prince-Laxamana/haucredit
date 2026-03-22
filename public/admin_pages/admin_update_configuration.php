<?php
session_start();
require_once __DIR__ . '/../../app/database.php';

requireAdmin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    popup_error("Invalid request.");
}

if (
    empty($_POST["csrf_token"]) ||
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
) {
    popup_error("Invalid CSRF token.");
}

// ==================== REQUEST PARAMETERS ====================
// Extract configuration type (background, org, activity_type, series, mapping, template)
$config_type = trim($_POST["config_type"] ?? "");
// Extract action (add, update, activate, deactivate, delete)
$action = trim($_POST["action"] ?? "");

// ==================== VALIDATION ====================
// Ensure both parameters are provided
if ($config_type === "" || $action === "") {
    $_SESSION["error"] = "Missing configuration action.";
    header("Location: admin_configurations.php");
    exit();
}

// ==================== CONFIGURATION CONSTANTS ====================
// Valid values for requirement due basis (when deadline calculated)
// Examples: "before_start" = days before event starts, "after_start" = days after event starts
$allowed_basis = ['before_start', 'after_start', 'before_end', 'after_end', 'manual'];
// Flag to track if action was successfully processed
$handled = false;

// ==================== REDIRECT HELPER ====================
/**
 * Redirect to Configuration Page
 * 
 * Purpose: Clean helper function for redirecting back to admin configuration page
 * after configuration updates (with success/error messages stored in session).
 * 
 * Return: void (exits after redirect)
 */
function redirect_config(): void
{
    header("Location: admin_configurations.php");
    exit();
}

// ==================== VALIDATION HELPERS ====================

/**
 * Ensure ID is Positive
 * 
 * Validates that ID is positive (> 0). Used for database record ID validation.
 * 
 * @param int $id ID to validate (from POST)
 * @param string $label Label for error message (e.g., "background", "organization")
 * @return void Throws exception if validation fails
 * @throws Exception "Invalid {label}."
 */
function ensurePositiveId(int $id, string $label): void
{
    if ($id <= 0) {
        throw new Exception("Invalid {$label}.");
    }
}

/**
 * Ensure Required Field Not Blank
 * 
 * Validates that required text field is not empty after trimming.
 * Used for names, titles, and other required text inputs.
 * 
 * @param string $value Field value from POST
 * @param string $label Label for error message (e.g., "Background name")
 * @return void Throws exception if validation fails
 * @throws Exception "{label} is required."
 */
function ensureNotBlank(string $value, string $label): void
{
    if ($value === "") {
        throw new Exception("{$label} is required.");
    }
}

/**
 * Ensure Valid Due Basis
 * 
 * Validates that due basis is one of allowed values.
 * Used for requirement deadline calculation rules.
 * 
 * @param string $basis Due basis value (before_start, after_start, etc.)
 * @param array $allowed_basis List of valid options
 * @return void Throws exception if validation fails
 * @throws Exception "Invalid due basis."
 */
function ensureValidDueBasis(string $basis, array $allowed_basis): void
{
    if (!in_array($basis, $allowed_basis, true)) {
        throw new Exception("Invalid due basis.");
    }
}

/**
 * Ensure Non-Negative Integer
 * 
 * Validates that numeric field is not negative (>= 0).
 * Used for days offsets, sort orders, and other non-negative counts.
 * 
 * @param int $value Value to validate
 * @param string $label Label for error message (e.g., "Due offset")
 * @return void Throws exception if validation fails
 * @throws Exception "{label} cannot be negative."
 */
function ensureNonNegative(int $value, string $label): void
{
    if ($value < 0) {
        throw new Exception("{$label} cannot be negative.");
    }
}

/**
 * Normalize Nullable Text Field
 * 
 * Trims whitespace and converts empty strings to null.
 * Used for optional text fields like descriptions and URLs.
 * 
 * @param string $value Raw text value from POST
 * @return ?string Trimmed value or null if empty
 */
function normalizeNullableText(string $value): ?string
{
    $value = trim($value);
    return $value === "" ? null : $value;
}

/**
 * Validate Record Not In Use Before Deletion
 * 
 * Executes query to check if record is referenced elsewhere in database.
 * Prevents deletion of records that are still actively used.
 * 
 * Examples:
 * - Cannot delete background if used in requirement mappings
 * - Cannot delete organization if assigned to any users
 * - Cannot delete requirement template if used in events
 * 
 * @param mysqli $conn Database connection
 * @param string $sql SELECT COUNT(*) query to check usage
 * @param string $types Parameter types (i/s/etc)
 * @param array $params Query parameters
 * @param string $message Error message if record in use
 * @return void Throws exception if record in use
 * @throws Exception Custom message
 */
function throwIfInUse(mysqli $conn, string $sql, string $types, array $params, string $message): void
{
    // Execute query to count references
    $row = fetchOne($conn, $sql, $types, $params);
    // Extract count from result (default 0 if missing)
    $count = (int) ($row['total'] ?? 0);

    // If count > 0, record is in use - throw exception
    if ($count > 0) {
        throw new Exception($message);
    }
}

try {
    // ==================== TRANSACTION MANAGEMENT ====================
    // Start database transaction: All configuration changes must succeed together
    // If any operation fails, entire transaction is rolled back
    $conn->begin_transaction();

    /* ================= BACKGROUND OPTIONS ================= */
    // Background options: Educational background/experience categories for users
    if ($config_type === "background") {
        // Extract POST parameters for background operation
        $background_id = (int) ($_POST["background_id"] ?? 0);          // Primary key for update/delete
        $background_name = trim($_POST["background_name"] ?? "");       // Display name for background
        $sort_order = (int) ($_POST["sort_order"] ?? 0);               // Display order in dropdown lists

        // ==================== ADD BACKGROUND ==================== 
        // Create new background option with default active status
        if ($action === "add_background") {
            // Validate required fields
            ensureNotBlank($background_name, "Background name");

            // Insert new background: active by default, customizable sort order
            execQuery(
                $conn,
                "
                INSERT INTO config_background_options (background_name, is_active, sort_order)
                VALUES (?, 1, ?)
                ",
                "si",
                [$background_name, $sort_order]
            );

            $_SESSION["success"] = "Background added successfully.";
            $handled = true;
        }

        // ==================== UPDATE BACKGROUND ====================
        // Modify existing background option
        if ($action === "update_background") {
            // Validate required fields and ID
            ensurePositiveId($background_id, "background");
            ensureNotBlank($background_name, "Background name");

            // Update background details and timestamp
            execQuery(
                $conn,
                "
                UPDATE config_background_options
                SET
                    background_name = ?,
                    sort_order = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE background_id = ?
                ",
                "sii",
                [$background_name, $sort_order, $background_id]
            );

            $_SESSION["success"] = "Background updated successfully.";
            $handled = true;
        }

        // ==================== ACTIVATE BACKGROUND ====================
        // Re-enable a previously deactivated background option
        if ($action === "activate_background") {
            // Validate background ID
            ensurePositiveId($background_id, "background");

            // Set is_active = 1 for this background
            execQuery(
                $conn,
                "
                UPDATE config_background_options
                SET
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE background_id = ?
                ",
                "i",
                [$background_id]
            );

            $_SESSION["success"] = "Background activated successfully.";
            $handled = true;
        }

        // ==================== DEACTIVATE BACKGROUND ====================
        // Disable background option (prevents selection in forms but keeps data)
        if ($action === "deactivate_background") {
            // Validate background ID
            ensurePositiveId($background_id, "background");

            // Set is_active = 0 for this background
            execQuery(
                $conn,
                "
                UPDATE config_background_options
                SET
                    is_active = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE background_id = ?
                ",
                "i",
                [$background_id]
            );

            $_SESSION["success"] = "Background deactivated successfully.";
            $handled = true;
        }

        // ==================== DELETE BACKGROUND ====================
        // Permanently remove background option from system
        if ($action === "delete_background") {
            // Validate background ID
            ensurePositiveId($background_id, "background");

            // Check if background is still used in requirement mappings
            // Will throw exception if found, preventing orphaned references
            throwIfInUse(
                $conn,
                "
                SELECT COUNT(*) AS total
                FROM config_requirements_map
                WHERE background_id = ?
                ",
                "i",
                [$background_id],
                "Cannot delete this background because it is still used in requirement mappings."
            );

            // Delete the background record
            execQuery(
                $conn,
                "
                DELETE FROM config_background_options
                WHERE background_id = ?
                ",
                "i",
                [$background_id]
            );

            $_SESSION["success"] = "Background deleted successfully.";
            $handled = true;
        }
    }

    /* ================= ORGANIZATIONS ================= */
    // Organization options: Educational institutions/bodies participating in the program
    if ($config_type === "org") {
        // Extract POST parameters for organization operation
        $org_option_id = (int) ($_POST["org_option_id"] ?? 0);    // Primary key for update/delete
        $org_name = trim($_POST["org_name"] ?? "");             // Name of organization
        $sort_order = (int) ($_POST["sort_order"] ?? 0);        // Display order in dropdown lists

        // ==================== ADD ORGANIZATION ====================
        // Create new organization option with default active status
        if ($action === "add_org") {
            // Validate required fields
            ensureNotBlank($org_name, "Organization name");

            // Insert new organization: active by default, customizable sort order
            execQuery(
                $conn,
                "
                INSERT INTO config_org_options (org_name, is_active, sort_order)
                VALUES (?, 1, ?)
                ",
                "si",
                [$org_name, $sort_order]
            );

            $_SESSION["success"] = "Organization added successfully.";
            $handled = true;
        }

        // ==================== UPDATE ORGANIZATION ====================
        // Modify existing organization option
        if ($action === "update_org") {
            // Validate required fields and ID
            ensurePositiveId($org_option_id, "organization");
            ensureNotBlank($org_name, "Organization name");

            // Update organization details and timestamp
            execQuery(
                $conn,
                "
                UPDATE config_org_options
                SET
                    org_name = ?,
                    sort_order = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE org_option_id = ?
                ",
                "sii",
                [$org_name, $sort_order, $org_option_id]
            );

            $_SESSION["success"] = "Organization updated successfully.";
            $handled = true;
        }

        // ==================== ACTIVATE ORGANIZATION ====================
        // Re-enable a previously deactivated organization option
        if ($action === "activate_org") {
            // Validate organization ID
            ensurePositiveId($org_option_id, "organization");

            // Set is_active = 1 for this organization
            execQuery(
                $conn,
                "
                UPDATE config_org_options
                SET
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE org_option_id = ?
                ",
                "i",
                [$org_option_id]
            );

            $_SESSION["success"] = "Organization activated successfully.";
            $handled = true;
        }

        // ==================== DEACTIVATE ORGANIZATION ====================
        // Disable organization option (prevents selection in forms but keeps data)
        if ($action === "deactivate_org") {
            // Validate organization ID
            ensurePositiveId($org_option_id, "organization");

            // Set is_active = 0 for this organization
            execQuery(
                $conn,
                "
                UPDATE config_org_options
                SET
                    is_active = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE org_option_id = ?
                ",
                "i",
                [$org_option_id]
            );

            $_SESSION["success"] = "Organization deactivated successfully.";
            $handled = true;
        }

        // ==================== DELETE ORGANIZATION ====================
        // Permanently remove organization option from system
        if ($action === "delete_org") {
            // Validate organization ID
            ensurePositiveId($org_option_id, "organization");

            // Check if organization is assigned to any users
            // Will throw exception if found, preventing orphaned references
            throwIfInUse(
                $conn,
                "
                SELECT COUNT(*) AS total
                FROM users
                WHERE org_body = (
                    SELECT org_name
                    FROM config_org_options
                    WHERE org_option_id = ?
                )
                ",
                "i",
                [$org_option_id],
                "Cannot delete this organization because it is still assigned to one or more users."
            );

            // Delete the organization record
            execQuery(
                $conn,
                "
                DELETE FROM config_org_options
                WHERE org_option_id = ?
                ",
                "i",
                [$org_option_id]
            );

            $_SESSION["success"] = "Organization deleted successfully.";
            $handled = true;
        }
    }

    /* ================= ACTIVITY TYPES ================= */
    // Activity types: Categories of activities/events that users can create (e.g., Workshop, Seminar, etc.)
    if ($config_type === "activity_type") {
        // Extract POST parameters for activity type operation
        $activity_type_id = (int) ($_POST["activity_type_id"] ?? 0);      // Primary key for update/delete
        $activity_type_name = trim($_POST["activity_type_name"] ?? "");  // Name of activity type
        $sort_order = (int) ($_POST["sort_order"] ?? 0);                 // Display order in dropdown lists

        // ==================== ADD ACTIVITY TYPE ====================
        // Create new activity type option with default active status
        if ($action === "add_activity_type") {
            // Validate required fields
            ensureNotBlank($activity_type_name, "Activity type name");

            // Insert new activity type: active by default, customizable sort order
            execQuery(
                $conn,
                "
                INSERT INTO config_activity_types (activity_type_name, is_active, sort_order)
                VALUES (?, 1, ?)
                ",
                "si",
                [$activity_type_name, $sort_order]
            );

            $_SESSION["success"] = "Activity type added successfully.";
            $handled = true;
        }

        // ==================== UPDATE ACTIVITY TYPE ====================
        // Modify existing activity type option
        if ($action === "update_activity_type") {
            // Validate required fields and ID
            ensurePositiveId($activity_type_id, "activity type");
            ensureNotBlank($activity_type_name, "Activity type name");

            // Update activity type details and timestamp
            execQuery(
                $conn,
                "
                UPDATE config_activity_types
                SET
                    activity_type_name = ?,
                    sort_order = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE activity_type_id = ?
                ",
                "sii",
                [$activity_type_name, $sort_order, $activity_type_id]
            );

            $_SESSION["success"] = "Activity type updated successfully.";
            $handled = true;
        }

        // ==================== ACTIVATE ACTIVITY TYPE ====================
        // Re-enable a previously deactivated activity type option
        if ($action === "activate_activity_type") {
            // Validate activity type ID
            ensurePositiveId($activity_type_id, "activity type");

            // Set is_active = 1 for this activity type
            execQuery(
                $conn,
                "
                UPDATE config_activity_types
                SET
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE activity_type_id = ?
                ",
                "i",
                [$activity_type_id]
            );

            $_SESSION["success"] = "Activity type activated successfully.";
            $handled = true;
        }

        // ==================== DEACTIVATE ACTIVITY TYPE ====================
        // Disable activity type option (prevents selection in forms but keeps data)
        if ($action === "deactivate_activity_type") {
            // Validate activity type ID
            ensurePositiveId($activity_type_id, "activity type");

            // Set is_active = 0 for this activity type
            execQuery(
                $conn,
                "
                UPDATE config_activity_types
                SET
                    is_active = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE activity_type_id = ?
                ",
                "i",
                [$activity_type_id]
            );

            $_SESSION["success"] = "Activity type deactivated successfully.";
            $handled = true;
        }

        // ==================== DELETE ACTIVITY TYPE ====================
        // Permanently remove activity type option from system
        if ($action === "delete_activity_type") {
            // Validate activity type ID
            ensurePositiveId($activity_type_id, "activity type");

            // Check if activity type is used in requirement mappings
            // Will throw exception if found, preventing orphaned references
            throwIfInUse(
                $conn,
                "
                SELECT COUNT(*) AS total
                FROM config_requirements_map
                WHERE activity_type_id = ?
                ",
                "i",
                [$activity_type_id],
                "Cannot delete this activity type because it is still used in requirement mappings."
            );

            // Delete the activity type record
            execQuery(
                $conn,
                "
                DELETE FROM config_activity_types
                WHERE activity_type_id = ?
                ",
                "i",
                [$activity_type_id]
            );

            $_SESSION["success"] = "Activity type deleted successfully.";
            $handled = true;
        }
    }

    /* ================= SERIES OPTIONS ================= */
    // Series options: Groupings of related events/activities or program tiers
    if ($config_type === "series") {
        // Extract POST parameters for series operation
        $series_option_id = (int) ($_POST["series_option_id"] ?? 0);  // Primary key for update/delete
        $series_name = trim($_POST["series_name"] ?? "");           // Name of series
        $sort_order = (int) ($_POST["sort_order"] ?? 0);            // Display order in dropdown lists

        // ==================== ADD SERIES ====================
        // Create new series option with default active status
        if ($action === "add_series") {
            // Validate required fields
            ensureNotBlank($series_name, "Series name");

            // Insert new series: active by default, customizable sort order
            execQuery(
                $conn,
                "
                INSERT INTO config_series_options (series_name, is_active, sort_order)
                VALUES (?, 1, ?)
                ",
                "si",
                [$series_name, $sort_order]
            );

            $_SESSION["success"] = "Series option added successfully.";
            $handled = true;
        }

        // ==================== UPDATE SERIES ====================
        // Modify existing series option
        if ($action === "update_series") {
            // Validate required fields and ID
            ensurePositiveId($series_option_id, "series option");
            ensureNotBlank($series_name, "Series name");

            // Update series details and timestamp
            execQuery(
                $conn,
                "
                UPDATE config_series_options
                SET
                    series_name = ?,
                    sort_order = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE series_option_id = ?
                ",
                "sii",
                [$series_name, $sort_order, $series_option_id]
            );

            $_SESSION["success"] = "Series option updated successfully.";
            $handled = true;
        }

        // ==================== ACTIVATE SERIES ====================
        // Re-enable a previously deactivated series option
        if ($action === "activate_series") {
            // Validate series ID
            ensurePositiveId($series_option_id, "series option");

            // Set is_active = 1 for this series
            execQuery(
                $conn,
                "
                UPDATE config_series_options
                SET
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE series_option_id = ?
                ",
                "i",
                [$series_option_id]
            );

            $_SESSION["success"] = "Series option activated successfully.";
            $handled = true;
        }

        // ==================== DEACTIVATE SERIES ====================
        // Disable series option (prevents selection in forms but keeps data)
        if ($action === "deactivate_series") {
            // Validate series ID
            ensurePositiveId($series_option_id, "series option");

            // Set is_active = 0 for this series
            execQuery(
                $conn,
                "
                UPDATE config_series_options
                SET
                    is_active = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE series_option_id = ?
                ",
                "i",
                [$series_option_id]
            );

            $_SESSION["success"] = "Series option deactivated successfully.";
            $handled = true;
        }

        // ==================== DELETE SERIES ====================
        // Permanently remove series option from system (no dependency check needed)
        if ($action === "delete_series") {
            // Validate series ID
            ensurePositiveId($series_option_id, "series option");

            // Delete the series record (no usage dependencies check)
            execQuery(
                $conn,
                "
                DELETE FROM config_series_options
                WHERE series_option_id = ?
                ",
                "i",
                [$series_option_id]
            );

            $_SESSION["success"] = "Series option deleted successfully.";
            $handled = true;
        }
    }

    /* ================= REQUIREMENT MAPPING ================= */
    // Requirement mapping: Links requirements to background/activity type combinations
    // Example: "Research Background + Workshop Activity requires Documentation + Report"
    if ($config_type === "mapping") {
        // Extract POST parameters for mapping operation
        $config_map_id = (int) ($_POST["config_map_id"] ?? 0);              // Primary key for update/delete
        $background_id = (int) ($_POST["background_id"] ?? 0);             // Background category (foreign key)
        $activity_type_id = (int) ($_POST["activity_type_id"] ?? 0);       // Activity type (foreign key)
        $req_template_id = (int) ($_POST["req_template_id"] ?? 0);         // Requirement template (foreign key)

        // ==================== ADD MAPPING ====================
        // Create new requirement mapping: Links a requirement to background + activity type combination
        // Uses ON DUPLICATE KEY UPDATE to activate if mapping already exists but inactive
        if ($action === "add_mapping") {
            // Validate all required foreign keys
            if ($background_id <= 0 || $activity_type_id <= 0 || $req_template_id <= 0) {
                throw new Exception("Background, activity type, and requirement template are required.");
            }

            // Insert new mapping or reactivate if duplicate exists (composite key)
            // If (background_id, activity_type_id, req_template_id) already exists, just reactivate
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

            $_SESSION["success"] = "Requirement mapping added successfully.";
            $handled = true;
        }

        // ==================== ACTIVATE MAPPING ====================
        // Re-enable a previously deactivated requirement mapping
        if ($action === "activate_mapping") {
            // Validate mapping ID
            ensurePositiveId($config_map_id, "mapping");

            // Set is_active = 1 for this mapping
            execQuery(
                $conn,
                "
                UPDATE config_requirements_map
                SET
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE config_map_id = ?
                ",
                "i",
                [$config_map_id]
            );

            $_SESSION["success"] = "Requirement mapping activated successfully.";
            $handled = true;
        }

        // ==================== DEACTIVATE MAPPING ====================
        // Disable requirement mapping (prevents automatic assignment but keeps record)
        if ($action === "deactivate_mapping") {
            // Validate mapping ID
            ensurePositiveId($config_map_id, "mapping");

            // Set is_active = 0 for this mapping
            execQuery(
                $conn,
                "
                UPDATE config_requirements_map
                SET
                    is_active = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE config_map_id = ?
                ",
                "i",
                [$config_map_id]
            );

            $_SESSION["success"] = "Requirement mapping deactivated successfully.";
            $handled = true;
        }

        // ==================== DELETE MAPPING ====================
        // Permanently remove requirement mapping from system
        if ($action === "delete_mapping") {
            // Validate mapping ID
            ensurePositiveId($config_map_id, "mapping");

            // Delete the mapping record
            execQuery(
                $conn,
                "
                DELETE FROM config_requirements_map
                WHERE config_map_id = ?
                ",
                "i",
                [$config_map_id]
            );

            $_SESSION["success"] = "Requirement mapping deleted successfully.";
            $handled = true;
        }
    }

    /* ================= REQUIREMENT TEMPLATES ================= */
    // Requirement templates: Reusable requirement definitions with default due dates and optional documents
    // Examples: "Report", "Narrative Submission", "Proof of Attendance", etc.
    if ($config_type === "template") {
        // Extract POST parameters for template operation
        $req_template_id = (int) ($_POST["req_template_id"] ?? 0);                         // Primary key for update/delete
        $req_name = trim($_POST["req_name"] ?? "");                                      // Name of requirement (e.g., "Report")
        $req_desc = normalizeNullableText($_POST["req_desc"] ?? "");                     // Optional description
        $template_url = normalizeNullableText($_POST["template_url"] ?? "");             // Optional URL to template file/document
        $default_due_offset_days = (int) ($_POST["default_due_offset_days"] ?? 0);      // Days (positive integer)
        $default_due_basis = trim($_POST["default_due_basis"] ?? "");                    // Deadline calculation rule

        // ==================== ADD TEMPLATE ====================
        // Create new requirement template with default active status
        if ($action === "add_template") {
            // Validate all required fields
            ensureNotBlank($req_name, "Requirement name");
            ensureValidDueBasis($default_due_basis, $allowed_basis);  // Must be valid basis
            ensureNonNegative($default_due_offset_days, "Due offset");  // Cannot be negative

            // Insert new requirement template: active by default
            execQuery(
                $conn,
                "
                INSERT INTO requirement_templates (
                    req_name,
                    req_desc,
                    template_url,
                    default_due_offset_days,
                    default_due_basis,
                    is_active
                )
                VALUES (?, ?, ?, ?, ?, 1)
                ",
                "sssis",
                [$req_name, $req_desc, $template_url, $default_due_offset_days, $default_due_basis]
            );

            $_SESSION["success"] = "Requirement template added successfully.";
            $handled = true;
        }

        // ==================== UPDATE TEMPLATE ====================
        // Modify existing requirement template
        if ($action === "update_template") {
            // Validate all required fields and ID
            ensurePositiveId($req_template_id, "requirement template");
            ensureNotBlank($req_name, "Requirement name");
            ensureValidDueBasis($default_due_basis, $allowed_basis);  // Must be valid basis
            ensureNonNegative($default_due_offset_days, "Due offset");  // Cannot be negative

            // Update template details and timestamp
            execQuery(
                $conn,
                "
                UPDATE requirement_templates
                SET
                    req_name = ?,
                    req_desc = ?,
                    template_url = ?,
                    default_due_offset_days = ?,
                    default_due_basis = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE req_template_id = ?
                ",
                "sssisi",
                [$req_name, $req_desc, $template_url, $default_due_offset_days, $default_due_basis, $req_template_id]
            );

            $_SESSION["success"] = "Requirement template updated successfully.";
            $handled = true;
        }

        // ==================== ACTIVATE TEMPLATE ====================
        // Re-enable a previously deactivated requirement template
        if ($action === "activate_template") {
            // Validate template ID
            ensurePositiveId($req_template_id, "requirement template");

            // Set is_active = 1 for this template
            execQuery(
                $conn,
                "
                UPDATE requirement_templates
                SET
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE req_template_id = ?
                ",
                "i",
                [$req_template_id]
            );

            $_SESSION["success"] = "Requirement template activated successfully.";
            $handled = true;
        }

        // ==================== DEACTIVATE TEMPLATE ====================
        // Disable requirement template (prevents selection in forms but keeps data)
        if ($action === "deactivate_template") {
            // Validate template ID
            ensurePositiveId($req_template_id, "requirement template");

            // Set is_active = 0 for this template
            execQuery(
                $conn,
                "
                UPDATE requirement_templates
                SET
                    is_active = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE req_template_id = ?
                ",
                "i",
                [$req_template_id]
            );

            $_SESSION["success"] = "Requirement template deactivated successfully.";
            $handled = true;
        }

        // ==================== DELETE TEMPLATE ====================
        // Permanently remove requirement template from system
        // Must check TWO places for usage: mappings and actual event requirements
        if ($action === "delete_template") {
            // Validate template ID
            ensurePositiveId($req_template_id, "requirement template");

            // Check 1: Is template used in requirement mappings?
            // If yes, cannot delete (would break automated requirement assignment)
            throwIfInUse(
                $conn,
                "
                SELECT COUNT(*) AS total
                FROM config_requirements_map
                WHERE req_template_id = ?
                ",
                "i",
                [$req_template_id],
                "Cannot delete this requirement template because it is still used in requirement mappings."
            );

            // Check 2: Are there existing event requirements using this template?
            // If yes, cannot delete (would orphan event requirement records)
            throwIfInUse(
                $conn,
                "
                SELECT COUNT(*) AS total
                FROM event_requirements
                WHERE req_template_id = ?
                ",
                "i",
                [$req_template_id],
                "Cannot delete this requirement template because it is already used by event requirements."
            );

            // Delete the template record (safe: no dependencies found)
            execQuery(
                $conn,
                "
                DELETE FROM requirement_templates
                WHERE req_template_id = ?
                ",
                "i",
                [$req_template_id]
            );

            $_SESSION["success"] = "Requirement template deleted successfully.";
            $handled = true;
        }
    }

    // ==================== ACTION VALIDATION ====================
    // Ensure requested action was recognized and handled by one of the config type branches
    if (!$handled) {
        throw new Exception("Invalid configuration action.");
    }

    // ==================== TRANSACTION COMMIT ====================
    // All operations succeeded: commit all changes to database
    $conn->commit();
} catch (Throwable $e) {
    // ==================== ERROR HANDLING ====================
    // Any exception during configuration: rollback entire transaction
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        // Silently ignore rollback errors (transaction may have already rolled back)
    }

    // Store error message in session for display on redirect
    $_SESSION["error"] = "Failed to update configuration: " . $e->getMessage();
}

// ==================== FINAL REDIRECT ====================
// Redirect back to configuration page with success/error message in session
redirect_config();