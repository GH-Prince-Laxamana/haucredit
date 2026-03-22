/**
 * My Events List Filtering Module
 * 
 * This module manages event list filtering and searching functionality:
 * - Tabbed filter system (all, active, pending, completed)
 * - Real-time search query filtering
 * - Combined filter + search logic
 * - Empty state display management
 * 
 * The filtering system uses a two-stage approach:
 * 1. Filter by status (what filter tab is selected)
 * 2. Filter by search query (text matching)
 * 
 * Cards are shown only if they match BOTH criteria.
 * Empty state message is displayed when no cards are visible.
 * 
 * Data Attributes Expected:
 * - data-status: Event status (all, active, pending, completed, etc.)
 * - data-search: Searchable text content (event title, description, etc.)
 */

// ========== GLOBAL STATE VARIABLE ==========
/**
 * Tracks the currently active filter type
 * 
 * Possible values:
 * - "all": Show all events regardless of status
 * - "active": Show events with active status
 * - "pending": Show events awaiting action/review
 * - "completed": Show finished/completed events
 * 
 * Updated by: setFilter() function
 * Used by: filterEvents() to determine which cards to show
 * 
 * Global scope:
 * - Made global so filter state persists across function calls
 * - Allows multiple functions to reference current filter state
 */
let currentFilter = "all";

// ========== FILTER TAB SWITCHING FUNCTION ==========

/**
 * Updates the active filter tab and applies the filtering
 * 
 * Called when user clicks a filter tab button (All, Active, Pending, Completed, etc.)
 * 
 * @param {HTMLElement} btn - The filter button/tab that was clicked
 *   Receives the button element so we can mark it as active
 *   Example: <button class="filter-tab" onclick="setFilter(this, 'active')">Active</button>
 * 
 * @param {string} filter - The filter value to apply
 *   Corresponds to event card data-status values
 *   Examples: "all", "active", "pending", "completed"
 * 
 * Process Flow:
 * 1. Clear active state from all filter tabs (remove "active" class)
 * 2. Mark the clicked button as active (add "active" class)
 * 3. Update global currentFilter variable with new filter value
 * 4. Call filterEvents() to re-evaluate all cards
 * 
 * Design Pattern:
 * - Only one tab should be active at a time (radio button pattern)
 * - Visual indication helps users know which filter is selected
 * - Defer filtering to filterEvents() function (separation of concerns)
 */
function setFilter(btn, filter) {
  // ========== REMOVE ACTIVE CLASS FROM ALL TABS ==========
  // Query all filter tab elements and clear their active state
  // This ensures only the newly clicked tab appears selected
  document
    .querySelectorAll(".filter-tab")
    .forEach((b) => b.classList.remove("active"));

  // ========== MARK CLICKED BUTTON AS ACTIVE ==========
  // Add the "active" class to the button that was clicked
  // This highlights the selected filter tab visually
  btn.classList.add("active");

  // ========== UPDATE FILTER STATE ==========
  // Store the new filter value in global variable
  // This is the filter criterion used by filterEvents()
  currentFilter = filter;

  // ========== APPLY THE NEW FILTER ==========
  // Call the filtering function to show/hide cards based on:
  // - The new filter (currentFilter)
  // - The current search query (value of searchInput)
  // This ensures search and filter work together
  filterEvents();
}

// ========== EVENT CARD FILTERING FUNCTION ==========

/**
 * Filters and displays/hides event cards based on filter and search
 * 
 * Called by:
 * - setFilter() when user changes the filter tab
 * - Search input handler when user types (typically via HTML oninput event)
 * 
 * Filtering Logic:
 * Each card is shown only if it meets BOTH criteria:
 * 1. Matches the current filter (status check)
 * 2. Matches the search query (text search)
 * 
 * If a card fails either check, it's hidden.
 * 
 * Empty State:
 * - If no cards are visible, show the empty state message
 * - If at least one card is visible, hide the empty state message
 * - Provides user feedback when filter/search returns no results
 */
function filterEvents() {
  // ========== GET SEARCH QUERY ==========
  // Retrieve the user's search input from the search field
  // Normalize the query for consistent matching:
  // - toLowerCase(): Convert to lowercase for case-insensitive search
  // - trim(): Remove leading/trailing whitespace for cleaner matching
  const query = document
    .getElementById("searchInput")
    .value.toLowerCase()
    .trim();

  // ========== GET ALL EVENT CARDS ==========
  // Query all event card elements that will be filtered
  // Each card represents one event in the user's list
  // Cards have data-status and data-search attributes
  const cards = document.querySelectorAll(".event-card");

  // ========== COUNTER FOR VISIBLE CARDS ==========
  // Track how many cards pass the filter+search criteria
  // Used to determine if empty state message should be shown
  // Reset to 0 at start of filter operation
  let visible = 0;

  // ========== PROCESS EACH EVENT CARD ==========
  // Iterate through all cards and apply filter+search logic
  cards.forEach((card) => {
    // ========== GET CARD DATA ATTRIBUTES ==========
    // Extract filtering information from HTML data attributes
    // These are set by the server when rendering the event list
    
    // status: The card's event status (active, pending, completed, etc.)
    // Used for tab filtering (only show cards matching currentFilter)
    const status = card.dataset.status;
    
    // search: Full searchable text for this card
    // Usually contains: event title, description, date, organizer, etc.
    // Concatenated as single string for fast searching
    // Fallback to empty string if attribute not present
    const text = card.dataset.search || "";

    // ========== FILTER CRITERION ==========
    // Check if card's status matches the selected filter
    // Logic:
    // - If filter is "all": always pass (show all statuses)
    // - Otherwise: only pass if card's status matches current filter
    // Uses short-circuit OR: "all" is always truthy, so other condition not evaluated
    const matchFilter = currentFilter === "all" || status === currentFilter;

    // ========== SEARCH CRITERION ==========
    // Check if card matches the search query
    // Uses includes() for substring matching (not exact match)
    // Example: Searching "Bob" would match "Bob Smith", "Bobby's Event", etc.
    // If query is empty string, includes() always returns true (match all)
    const matchSearch = text.includes(query);

    // ========== SHOW OR HIDE CARD ==========
    // Card is shown and counted only if both criteria are met
    if (matchFilter && matchSearch) {
      // Card passes both filter and search criteria
      card.style.display = "block";  // Make card visible
      visible++;  // Increment counter of visible cards
    } else {
      // Card fails at least one criterion
      card.style.display = "none";  // Hide the card
      // Note: Do not increment visible counter
    }
  });

  // ========== MANAGE EMPTY STATE MESSAGE ==========
  // Show or hide the empty state (no results) message
  
  // Get the empty state message element
  const emptyStateEl = document.getElementById("emptyState");
  
  // Logic:
  // - visible === 0: No cards passed filter+search → show empty state
  // - visible > 0: At least one card visible → hide empty state
  // 
  // Using hidden property (boolean):
  // - hidden = true: Element not displayed and removed from tab order
  // - hidden = false: Element displayed normally
  // 
  // Expression: visible !== 0 returns:
  // - false when visible = 0 (set hidden = false, show message)
  // - true when visible > 0 (set hidden = true, hide message)
  document.getElementById("emptyState").hidden = visible !== 0;
}
