/**
 * Page Layout Management Module
 * 
 * This module handles core layout interactions:
 * - Sidebar navigation (open/close/toggle with multiple triggers)
 * - Navigation button behaviors (create event, view event)
 * - Responsive layout adjustments (auto-close on desktop)
 * 
 * The module uses an IIFE (Immediately Invoked Function Expression) to encapsulate
 * all functionality in a private scope, preventing conflicts with other scripts.
 * 
 * Key Features:
 * - Dynamic close button creation for accessibility
 * - Multiple close triggers (button, overlay click, Escape key)
 * - Responsive sidebar hiding on larger screens
 * - Event row click-to-view functionality with button click preservation
 * - Prevents body scrolling when sidebar is open
 */

(() => {
  /**
   * Initialize layout functionality after DOM is fully loaded
   * Ensures all elements exist before attaching event listeners
   */
  document.addEventListener("DOMContentLoaded", () => {
    // ========== DOM ELEMENT REFERENCES ==========
    // Cache references to frequently accessed DOM elements
    // Using const prevents accidental reassignment
    const body = document.body;
    const sidebar = document.querySelector(".sidebar");  // Side navigation menu
    const menuBtn = document.getElementById("menuBtn");  // Hamburger menu toggle button
    const overlay = document.getElementById("sidebarOverlay");  // Semi-transparent overlay behind sidebar

    // ========== EARLY EXIT FOR MISSING ELEMENTS ==========
    // Prevent errors if layout elements don't exist
    // This allows the script to be included on pages with different layouts
    if (!sidebar || !menuBtn || !overlay) return;

    /* ========================================
       SIDEBAR MANAGEMENT SECTION
       ======================================== */

    // ========== DYNAMIC CLOSE BUTTON CREATION ==========
    /**
     * Creates and inserts a close button (✕) inside the sidebar
     * Only creates once using querySelector to check for existence
     * This provides an alternative way to close the sidebar besides clicking overlay
     * 
     * Button details:
     * - Type: button (not submit)
     * - Class: sidebar-close (styled in CSS)
     * - ID: menuCloseBtn (for potential CSS targeting)
     * - Label: "✕" (multiplication sign, standard close icon)
     * - aria-label: "Close menu" (screen reader accessibility)
     */
    if (!sidebar.querySelector(".sidebar-close")) {
      // Create a new button element
      const closeBtn = document.createElement("button");
      closeBtn.type = "button";
      closeBtn.className = "sidebar-close";
      closeBtn.id = "menuCloseBtn";
      closeBtn.textContent = "✕";
      closeBtn.setAttribute("aria-label", "Close menu");

      // Insert button as first child of sidebar (appears at top of menu)
      sidebar.insertBefore(closeBtn, sidebar.firstChild);

      // Attach click handler to close button
      closeBtn.addEventListener("click", () => closeMenu());
    }

    // ========== SIDEBAR STATE MANAGEMENT ==========
    
    /**
     * Opens the sidebar menu
     * 
     * Actions:
     * 1. Add "sidebar-open" class to body (triggers CSS sidebar slide-in animation)
     * 2. Make overlay visible and active (semi-transparent background)
     * 3. Prevent body scrolling (overflow: hidden)
     * 
     * Rationale:
     * - CSS class approach allows smooth transitions via CSS animations
     * - Overlay provides visual feedback and click-to-close target
     * - Preventing scroll improves UX on mobile (no background movement while menu open)
     */
    function openMenu() {
      // Add class that triggers sidebar animation (CSS transform/transition)
      body.classList.add("sidebar-open");
      // Make overlay visible to user
      overlay.hidden = false;
      // Add active state to trigger overlay transition (fade in)
      overlay.classList.add("active");
      // Prevent background content from scrolling while menu is open
      body.style.overflow = "hidden";
    }

    /**
     * Closes the sidebar menu (reverse of openMenu)
     * 
     * Actions:
     * 1. Remove "sidebar-open" class from body (triggers CSS sidebar slide-out animation)
     * 2. Hide overlay and remove active state (fade out)
     * 3. Restore body scrolling
     * 
     * Rationale:
     * - Mirrors openMenu logic so state toggles cleanly
     * - Restores overflow: "" removes the restriction (returns to default scrolling)
     */
    function closeMenu() {
      // Remove class that triggers sidebar animation (returns to closed position)
      body.classList.remove("sidebar-open");
      // Remove active state to trigger overlay transition (fade out)
      overlay.classList.remove("active");
      // Hide overlay completely from accessibility tree
      overlay.hidden = true;
      // Restore body scrolling (empty string removes inline style override)
      body.style.overflow = "";
    }

    // ========== MENU BUTTON CLICK HANDLER ==========
    /**
     * Toggles sidebar open/closed when hamburger menu button is clicked
     * 
     * Logic:
     * - Checks if body has "sidebar-open" class
     * - If open, close it; if closed, open it
     * - Provides standard hamburger menu behavior
     */
    menuBtn.addEventListener("click", () => {
      // Check current state by looking for "sidebar-open" class
      if (body.classList.contains("sidebar-open")) {
        closeMenu();  // Close if currently open
      } else {
        openMenu();  // Open if currently closed
      }
    });

    // ========== OVERLAY CLICK HANDLER ==========
    /**
     * Closes sidebar when user clicks on the dark overlay
     * 
     * Rationale:
     * - Standard UI pattern: clicking backdrop closes overlay
     * - Provides visual feedback of clickable area
     * - Common on mobile when sidebar should disappear
     */
    overlay.addEventListener("click", closeMenu);

    // ========== KEYBOARD ACCESSIBILITY HANDLER ==========
    /**
     * Closes sidebar when Escape key is pressed
     * 
     * Rationale:
     * - Escape key is standard close shortcut (modal windows, menus, dialogs)
     * - Improves keyboard navigation accessibility
     * - Only closes if sidebar is actually open (avoids unnecessary operations)
     */
    document.addEventListener("keydown", (e) => {
      // Check if Escape key was pressed AND sidebar is currently open
      if (e.key === "Escape" && body.classList.contains("sidebar-open")) {
        closeMenu();
      }
    });

    // ========== RESPONSIVE RESIZE HANDLER ==========
    /**
     * Auto-closes sidebar when window becomes larger than 640px width
     * 
     * Rationale:
     * - 640px is the breakpoint where layout switches from mobile to desktop
     * - On desktop, sidebar should be permanently visible (not overlaid)
     * - Prevents sidebar from getting stuck open if user opens on mobile then resizes
     * - Improves experience when rotating device or resizing browser
     * 
     * Note: Desktop sidebar likely shown via CSS media query, not managed by JS
     */
    window.addEventListener("resize", () => {
      // Only close if window is wider than 640px (desktop view)
      if (window.innerWidth > 640) {
        // Remove open state from body (sidebar animates closed)
        body.classList.remove("sidebar-open");
        // Hide overlay immediately (no animation needed)
        overlay.hidden = true;
      }
    });

    /* ========================================
       CREATE EVENT BUTTON SECTION
       ======================================== */

    // ========== CREATE EVENT BUTTON HANDLER ==========
    /**
     * Navigates to event creation page when primary action button is clicked
     * 
     * Purpose:
     * - Button is typically on home page or dashboard
     * - Clicking it should take user to the event creation form
     * - Uses page navigation instead of AJAX to start fresh form
     * 
     * Selector: .home-primary-btn
     * - Primary action button in home layout
     * - Purpose: create new event
     */
    const createBtn = document.querySelector(".home-primary-btn");

    // Only attach handler if button exists on this page
    if (createBtn) {
      createBtn.addEventListener("click", () => {
        // Navigate to event creation page
        window.location.href = "create_event.php";
      });
    }

    /* ========================================
       CLICKABLE EVENT ROW SECTION
       ======================================== */

    // ========== EVENT ROW CLICK HANDLERS ==========
    /**
     * Makes event rows clickable to navigate to event details page
     * 
     * Purpose:
     * - Event list displays events in rows (table-like structure)
     * - Clicking a row should show event details
     * - Row contains a "View" button with event details link
     * 
     * Implementation:
     * - Attach click listener to each row element
     * - Find the view button within the row
     * - Navigate to the event details page (button's href)
     * - Exception: if clicking button directly, don't navigate twice
     * 
     * Rationale for exception:
     * - Button itself has a click handler that would trigger
     * - If row handler also navigates, two navigations occur
     * - e.target.closest() checks if click was on view button or inside it
     * - If on button, return early to let button's own handler work
     */
    const eventRows = document.querySelectorAll(".home-event-row");

    // Attach click handler to each event row
    eventRows.forEach((row) => {
      row.addEventListener("click", (e) => {
        // ========== BUTTON CLICK EXCEPTION ==========
        // If user clicked on the view button, ignore this handler
        // The button's own click handler will handle navigation
        // closest() checks if clicked element or its ancestors include .home-btn-view
        if (e.target.closest(".home-btn-view")) {
          return;  // Exit handler, let button's handler work
        }

        // ========== ROW CLICK NAVIGATION ==========
        // Find the view button somewhere in this row
        const viewBtn = row.querySelector(".home-btn-view");

        // Navigate to the event details page using button's href
        if (viewBtn) {
          window.location.href = viewBtn.href;
        }
      });
    });
  });
})();