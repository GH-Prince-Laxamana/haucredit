/**
 * Event Creation Form Module
 * 
 * This module manages the multi-step event creation form with:
 * - Conditional form block visibility based on background and activity type
 * - Multi-select dropdown for organizing bodies
 * - Real-time form validation with progress control
 * - Step-by-step navigation with back/next buttons
 * - Special validation rules (metric format, time gaps, etc.)
 * 
 * The form uses a two-step process:
 * Step 1: Basic event information (background, activity, dates, etc.)
 * Step 2: Detailed information (requirements, metrics, logistics, etc.)
 */

// ========== CONDITIONAL FORM BLOCK MANAGEMENT ==========

/**
 * Toggles visibility of conditional form sections based on selected background and activity
 * 
 * Logic:
 * - Series block: shown for "Participation" background
 * - Off-campus block: shown for activities containing "off-campus"
 * - Visitors block: shown for activities containing "on-campus"
 * 
 * Each block's inputs are marked required when visible, optional when hidden
 * Clears values when hidden to prevent accidental submission of hidden fields
 */
function toggleBlocks() {
  // Get the currently selected background and activity type radio buttons
  const seriesBlock = document.getElementById("series-block");
  const offcampusBlock = document.getElementById("offcampus-block");
  const visitorsBlock = document.getElementById("visitors-block");

  // Retrieve selected background radio button and extract its name
  const selectedBackground = document.querySelector(
    'input[name="background_id"]:checked',
  );
  const selectedActivity = document.querySelector(
    'input[name="activity_type_id"]:checked',
  );

  // Extract display names from dataset attributes of selected inputs
  const backgroundName =
    selectedBackground?.dataset.backgroundName?.trim() || "";
  const activityName = selectedActivity?.dataset.activityTypeName?.trim() || "";

  // ========== SERIES BLOCK ==========
  // Series block is shown only for "Participation" type events
  // These events can be grouped into series (e.g., monthly meetings, recurring workshops)
  if (backgroundName === "Participation") {
    // Show the series selection block
    seriesBlock.style.display = "flex";
    // Mark series input as required since block is visible
    seriesBlock
      .querySelectorAll('input[name="series_option_id"]')
      .forEach((i) => (i.required = true));
  } else {
    // Hide the series block
    seriesBlock.style.display = "none";
    // Clear series selection and make it optional
    seriesBlock
      .querySelectorAll('input[name="series_option_id"]')
      .forEach((i) => {
        i.required = false;
        i.checked = false;
      });
  }

  // ========== OFF-CAMPUS BLOCK ==========
  // Off-campus block appears for activities that are held outside campus
  // Contains fields for distance, platform, etc.
  if (activityName.toLowerCase().includes("off-campus")) {
    // Show the off-campus information block
    offcampusBlock.style.display = "block";
    // Mark all inputs in block as required
    offcampusBlock
      .querySelectorAll("input")
      .forEach((i) => (i.required = true));
  } else {
    // Hide the off-campus block
    offcampusBlock.style.display = "none";
    // Clear inputs and make them optional
    offcampusBlock.querySelectorAll("input").forEach((i) => {
      i.required = false;
      // Also uncheck any radio buttons
      if (i.type === "radio") i.checked = false;
    });
  }

  // ========== VISITORS BLOCK ==========
  // Visitors block appears for on-campus activities
  // Allows specifying whether external visitors attended the event
  if (activityName.toLowerCase().includes("on-campus")) {
    // Show the visitors selection block
    visitorsBlock.style.display = "flex";
    // Mark visitor inputs as required
    visitorsBlock
      .querySelectorAll('input[name="has_visitors"]')
      .forEach((r) => (r.required = true));
  } else {
    // Hide the visitors block
    visitorsBlock.style.display = "none";
    // Clear visitor selection and make it optional
    visitorsBlock
      .querySelectorAll('input[name="has_visitors"]')
      .forEach((r) => {
        r.required = false;
        r.checked = false;
      });
  }
}

// ========== VALIDATION HELPER FUNCTIONS ==========

/**
 * Validates the target metric field format
 * Format required: "XX% description" where XX is 0-100
 * Example: "95% of students approved the event"
 * 
 * @param {string} value - The target metric input value
 * @returns {boolean} - True if format is valid, false otherwise
 */
function isValidTargetMetric(value) {
  const v = value.trim();
  // Empty values are considered invalid if field is required
  if (!v) return false;
  // Regex: percentage (0-100) followed by space and description
  // Pattern: (100|[1-9]?\d)\% .+
  // Matches: 100%, 99%, 50%, 5%, 0% followed by at least one character
  return /^(100|[1-9]?\d)\%\s+.+$/.test(v);
}

/**
 * Validates that the end datetime is at least 2 hours after start datetime
 * Events must have minimum 2-hour duration
 * 
 * @param {string} startValue - Start datetime in ISO format (e.g., "2024-03-22T09:00")
 * @param {string} endValue - End datetime in ISO format
 * @returns {boolean} - True if gap is at least 2 hours, false otherwise
 */
function hasMinimumTwoHoursGap(startValue, endValue) {
  // Both datetimes must be present to validate
  if (!startValue || !endValue) return false;

  // Parse ISO datetime strings into Date objects
  const start = new Date(startValue);
  const end = new Date(endValue);

  // Validate that both dates parsed successfully (getTime() returns NaN if invalid)
  if (isNaN(start.getTime()) || isNaN(end.getTime())) return false;

  // Calculate difference in milliseconds
  const diffMs = end - start;
  
  // Check if difference is at least 2 hours (2 * 60 * 60 * 1000 milliseconds)
  return diffMs >= 2 * 60 * 60 * 1000;
}

// ========== INITIALIZE TOGGLE ON PAGE SHOW ==========
// pageshow event fires when user navigates back to page using browser back button
// This ensures form blocks are correctly displayed after navigation
window.addEventListener("pageshow", toggleBlocks);

// ========== DOM CONTENT LOADED INITIALIZATION ==========
// Wait for DOM to fully load before attaching event listeners and setting up form

document.addEventListener("DOMContentLoaded", () => {
  // ========== RADIO BUTTON CHANGE LISTENERS ==========
  // Listen for changes to background and activity type selections
  // Trigger conditional block visibility updates
  
  // When user changes background selection, update visible form blocks
  document
    .querySelectorAll('input[name="background_id"]')
    .forEach((r) => r.addEventListener("change", toggleBlocks));

  // When user changes activity type selection, update visible form blocks
  document
    .querySelectorAll('input[name="activity_type_id"]')
    .forEach((r) => r.addEventListener("change", toggleBlocks));

  // ========== MULTI-SELECT DROPDOWN SETUP ==========
  // Creates a searchable, taggable dropdown for selecting multiple organizations/bodies
  // Replaces standard multi-select with better UX
  
  // Get the hidden select element that holds the actual selected values
  const select = document.getElementById("organizing_body");
  // Get the custom dropdown container
  const dropdown = document.getElementById("orgDropdown");
  // Get the search input field
  const input = dropdown.querySelector(".multi-input");
  // Get the dropdown list that shows available options
  const list = dropdown.querySelector(".dropdown-list");
  // Get the container where selected tags are displayed
  const tagsContainer = document.getElementById("selectedTags");

  /**
   * Populates the dropdown list with unselected options
   * Filters options based on search input
   * 
   * @param {string} filter - Search string to filter options (default empty)
   */
  function populateList(filter = "") {
    // Clear previous list items
    list.innerHTML = "";

    // Iterate through all options in the hidden select element
    Array.from(select.options).forEach((option) => {
      // Show option only if:
      // 1. It matches the search filter (case-insensitive)
      // 2. It's not already selected
      if (
        option.value.toLowerCase().includes(filter.toLowerCase()) &&
        !option.selected
      ) {
        // Create a clickable item for this option
        const item = document.createElement("div");
        item.textContent = option.value;
        item.dataset.value = option.value;

        // When user clicks an option, mark it as selected
        item.addEventListener("click", () => {
          // Mark the option as selected in the hidden select
          option.selected = true;
          // Update the displayed tags
          renderTags();
          // Clear the search input
          input.value = "";
          // Hide the dropdown list
          list.style.display = "none";
          // Re-validate the form
          validateStep1();
          validateStep2();
        });

        // Add the item to the dropdown list
        list.appendChild(item);
      }
    });

    // Show or hide the dropdown based on whether there are filtered options
    list.style.display = list.children.length ? "block" : "none";
  }

  /**
   * Renders the selected organizations as visual tags
   * Each tag has an X button to deselect it
   */
  function renderTags() {
    // Clear previous tags
    tagsContainer.innerHTML = "";
    // Get all currently selected options
    const selectedOptions = Array.from(select.selectedOptions);

    // Create a tag for each selected option
    selectedOptions.forEach((option) => {
      const tag = document.createElement("div");
      tag.className = "tag";
      // Tag displays the text with an X close button
      tag.innerHTML = `${option.value}<span>&times;</span>`;

      // When user clicks the X, deselect the option
      tag.querySelector("span").addEventListener("click", () => {
        // Unselect the option in the hidden select
        option.selected = false;
        // Update tags display
        renderTags();
        // Refresh the dropdown list
        populateList(input.value);
        // Re-validate the form
        validateStep1();
        validateStep2();
      });

      // Add tag to display area
      tagsContainer.appendChild(tag);
    });
  }

  // ========== DROPDOWN INPUT HANDLING ==========
  // Show matching options as user types in the search input
  input.addEventListener("input", () => {
    populateList(input.value);
  });

  // Show all available options when user focuses the input
  input.addEventListener("focus", () => {
    populateList(input.value);
  });

  // Close dropdown when user clicks outside the dropdown area
  document.addEventListener("click", (e) => {
    if (!dropdown.contains(e.target)) list.style.display = "none";
  });

  // Initial render of any pre-selected tags
  renderTags();

  // ========== STEP NAVIGATION ELEMENTS ==========
  // Get references to step containers and navigation buttons
  
  // Container for step 1 (basic event info)
  const step1 = document.querySelector(".step-1");
  // Collection of step 2 containers (detailed event info - may be multiple)
  const step2 = document.querySelectorAll(".step-2");

  // Navigation buttons
  const nextBtn = document.querySelector(".next-btn");  // Move from step 1 to step 2
  const backBtn = document.querySelector(".back-btn");  // Move from step 2 to step 1
  const createBtn = document.querySelector(".create-btn");  // Submit the form

  // Action button containers
  const step1Actions = document.querySelector(".step1-actions");  // Step 1 buttons
  const step2Actions = document.querySelector(".step-2-actions");  // Step 2 buttons

  // Exit if required elements don't exist
  if (!step1 || !nextBtn || !createBtn) return;

  // ========== INITIAL STATE SETUP ==========
  // Hide step 2 and its actions initially (show only step 1)
  step2.forEach((s) => (s.style.display = "none"));
  step2Actions.style.display = "none";
  // Disable create button until form is valid
  createBtn.disabled = true;

  /**
   * Checks if an element is currently visible to the user
   * Used to validate only visible form fields
   * 
   * @param {Element} el - Element to check
   * @returns {boolean} - True if element is visible
   */
  const isVisible = (el) =>
    el.offsetParent !== null && !el.closest("[hidden]") && !el.disabled;

  /**
   * Gets all input, textarea, and select elements within a container
   * 
   * @param {Element} container - Container to search
   * @returns {Array<Element>} - Array of form inputs
   */
  const getInputs = (container) => [
    ...container.querySelectorAll("input, textarea, select"),
  ];

  /**
   * Validates all required fields in a container
   * Checks:
   * - Required text inputs have values
   * - Required checkboxes/radio buttons are selected
   * - Multi-select dropdowns have selections
   * - Target metric has valid format
   * - Event duration is at least 2 hours
   * - At least one organization is selected
   * 
   * @param {Element} container - Container with form fields to validate
   * @returns {boolean} - True if all required fields are valid
   */
  function validateContainer(container) {
    const inputs = getInputs(container).filter(isVisible);
    let valid = true;

    // Object to track radio button groups and their required status
    const radioGroups = {};

    // Validate each visible input
    for (const input of inputs) {
      // Skip hidden selects (conditional fields that aren't shown)
      if (input.tagName === "SELECT" && input.hidden) continue;

      // For radio buttons, track which groups are required
      if (input.type === "radio") {
        if (input.required) {
          (radioGroups[input.name] ??= []).push(input);
        }
        continue;
      }

      // For multi-select dropdowns, ensure at least one option is selected
      if (input.tagName === "SELECT" && input.multiple && input.required) {
        if (input.selectedOptions.length === 0) valid = false;
        continue;
      }

      // For required fields, ensure they have a non-empty value
      if (input.required && !input.value.trim()) {
        valid = false;
        continue;
      }

      // Special validation: target metric must match format if provided
      if (input.id === "target_metric" && input.value.trim()) {
        if (!isValidTargetMetric(input.value)) valid = false;
      }
    }

    // For each radio group, ensure at least one option is checked
    for (const group of Object.values(radioGroups)) {
      if (!group.some((r) => r.checked)) valid = false;
    }

    // Ensure at least one organization is selected
    const tagsContainer = container.querySelector("#selectedTags");
    if (tagsContainer && tagsContainer.querySelectorAll(".tag").length === 0) {
      valid = false;
    }

    // Validate event duration (2-hour minimum)
    const startInput = document.getElementById("start_datetime");
    const endInput = document.getElementById("end_datetime");

    if (
      startInput &&
      endInput &&
      isVisible(startInput) &&
      isVisible(endInput)
    ) {
      // Both datetime fields must have valid values and meet 2-hour requirement
      if (!hasMinimumTwoHoursGap(startInput.value, endInput.value)) {
        valid = false;
      }
    }

    return valid;
  }

  /**
   * Validates step 1 and enables/disables next button
   * Called whenever form fields change
   */
  function validateStep1() {
    nextBtn.disabled = !validateContainer(step1);
  }

  /**
   * Validates step 2 and enables/disables create button
   * Called whenever form fields change
   */
  function validateStep2() {
    // All step 2 containers must be valid (might be multiple)
    const valid = [...step2].every((step) => validateContainer(step));
    createBtn.disabled = !valid;
  }

  // ========== REAL-TIME VALIDATION ==========
  // Re-validate form whenever user makes changes
  
  // Validate on text input (typing in fields)
  document.addEventListener("input", () => {
    validateStep1();
    validateStep2();
  });

  // Validate on value changes (radio buttons, checkboxes, selects)
  document.addEventListener("change", () => {
    validateStep1();
    validateStep2();
  });

  // ========== MUTATION OBSERVER FOR CONDITIONAL CHANGES ==========
  // Watches for DOM changes (form blocks being shown/hidden)
  // Re-validates when conditional visibility changes
  
  const observer = new MutationObserver(() => {
    validateStep1();
    validateStep2();
  });

  // Observe changes to style, class, and open attributes
  // These indicate when form blocks are shown/hidden
  observer.observe(step1, {
    subtree: true,
    childList: true,
    attributes: true,
    attributeFilter: ["style", "class", "open"],
  });

  // ========== INITIAL VALIDATION ==========
  // Set initial state: show conditional blocks, validate form
  toggleBlocks();
  validateStep1();
  validateStep2();

  // ========== STEP NAVIGATION EVENT HANDLERS ==========
  
  /**
   * Next button: Move from step 1 to step 2
   * Only enabled if step 1 is valid
   */
  nextBtn.addEventListener("click", () => {
    // Double-check button is enabled (sanity check)
    if (nextBtn.disabled) return;

    // Hide step 1 and its actions
    step1.style.display = "none";
    step1Actions.style.display = "none";

    // Show all step 2 sections and open them if they're details elements
    step2.forEach((s) => {
      s.style.display = "block";
      s.open = true;  // Open details elements
    });

    // Show step 2 actions
    step2Actions.style.display = "flex";
    // Validate step 2 (enables/disables create button)
    validateStep2();

    // Scroll to top to show step 2 content
    window.scrollTo({ top: 0, behavior: "smooth" });
  });

  /**
   * Back button: Move from step 2 back to step 1
   */
  backBtn.addEventListener("click", () => {
    // Show step 1 and its actions
    step1.style.display = "block";
    step1Actions.style.display = "flex";

    // Hide all step 2 sections and close them if they're details elements
    step2.forEach((s) => {
      s.style.display = "none";
      s.open = false;  // Close details elements
    });

    // Hide step 2 actions
    step2Actions.style.display = "none";
    // Disable create button (only shown for step 2)
    createBtn.disabled = true;

    // Re-validate step 1 (might enable/disable next button)
    validateStep1();

    // Scroll to top to show step 1 content
    window.scrollTo({ top: 0, behavior: "smooth" });
  });
});
