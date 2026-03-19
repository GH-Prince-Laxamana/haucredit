(() => {
  // ===== DOM CONTENT LOADED EVENT =====
  // Execute code after DOM is fully loaded to ensure all elements are available
  document.addEventListener("DOMContentLoaded", () => {
    // ===== DOM ELEMENT REFERENCES =====
    // Get references to key layout elements
    const body = document.body;
    const sidebar = document.querySelector(".sidebar");
    const menuBtn = document.getElementById("menuBtn");
    const overlay = document.getElementById("sidebarOverlay");

    // Early return if required elements are missing to prevent errors
    if (!sidebar || !menuBtn || !overlay) return;

    /* ===============================
       SIDEBAR MANAGEMENT
    =============================== */

    // ===== DYNAMIC CLOSE BUTTON CREATION =====
    // Create and insert a close button for the sidebar if it doesn't already exist
    if (!sidebar.querySelector(".sidebar-close")) {
      const closeBtn = document.createElement("button");
      closeBtn.type = "button";
      closeBtn.className = "sidebar-close";
      closeBtn.id = "menuCloseBtn";
      closeBtn.textContent = "✕";
      closeBtn.setAttribute("aria-label", "Close menu");

      // Insert close button at the beginning of the sidebar
      sidebar.insertBefore(closeBtn, sidebar.firstChild);

      // Attach click event to close the menu
      closeBtn.addEventListener("click", () => closeMenu());
    }

    // ===== SIDEBAR OPEN FUNCTION =====
    // Function to open the sidebar menu
    function openMenu() {
      body.classList.add("sidebar-open");
      overlay.hidden = false;
      overlay.classList.add("active");
      body.style.overflow = "hidden"; // Prevent background scrolling
    }

    // ===== SIDEBAR CLOSE FUNCTION =====
    // Function to close the sidebar menu
    function closeMenu() {
      body.classList.remove("sidebar-open");
      overlay.classList.remove("active");
      overlay.hidden = true;
      body.style.overflow = ""; // Restore background scrolling
    }

    // ===== MENU BUTTON EVENT LISTENER =====
    // Toggle sidebar open/close when menu button is clicked
    menuBtn.addEventListener("click", () => {
      if (body.classList.contains("sidebar-open")) closeMenu();
      else openMenu();
    });

    // ===== OVERLAY CLICK EVENT LISTENER =====
    // Close sidebar when clicking on the overlay
    overlay.addEventListener("click", closeMenu);

    // ===== ESCAPE KEY EVENT LISTENER =====
    // Close sidebar when Escape key is pressed (accessibility feature)
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && body.classList.contains("sidebar-open"))
        closeMenu();
    });

    // ===== WINDOW RESIZE EVENT LISTENER =====
    // Close sidebar automatically on larger screens (desktop view)
    window.addEventListener("resize", () => {
      if (window.innerWidth > 640) {
        body.classList.remove("sidebar-open");
        overlay.hidden = true;
      }
    });

    /* ===============================
       CREATE EVENT BUTTON
    =============================== */

    // ===== CREATE EVENT BUTTON EVENT LISTENER =====
    // Redirect to create event page when create button is clicked
    const createBtn = document.querySelector(".home-primary-btn");

    if (createBtn) {
      createBtn.addEventListener("click", () => {
        window.location.href = "create_event.php";
      });
    }

    /* ===============================
       CLICKABLE EVENT ROW
    =============================== */

    // ===== EVENT ROW CLICK HANDLERS =====
    // Make event rows clickable to navigate to event view page
    const eventRows = document.querySelectorAll(".home-event-row");

    eventRows.forEach((row) => {
      row.addEventListener("click", (e) => {
        // Ignore clicks on view buttons to prevent double navigation
        if (e.target.closest(".home-btn-view")) return;

        // Find the view button and navigate to its href
        const viewBtn = row.querySelector(".home-btn-view");

        if (viewBtn) {
          window.location.href = viewBtn.href;
        }
      });
    });
  });
})();