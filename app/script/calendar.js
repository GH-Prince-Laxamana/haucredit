/**
 * Calendar Management Module
 * 
 * This module provides an interactive calendar interface with support for:
 * - Multiple view modes (month, week, day)
 * - Creating, editing, and deleting calendar entries
 * - Date range selection with drag-and-drop
 * - Integration with event management system
 * - Keyboard navigation (Escape to close modal)
 * 
 * The calendar uses a self-invoking function (IIFE) to encapsulate all variables
 * and prevent global namespace pollution.
 */

(function () {
  // ========== DOM Query Helpers ==========
  // These utility functions provide shorthand syntax for DOM element selection
  
  /**
   * Single element selector - wraps querySelector with optional root context
   * 
   * @param {string} selector - CSS selector string
   * @param {Element} root - Optional root element to search within (defaults to document)
   * @returns {Element|null} - The first matching element or null
   */
  const $ = (selector, root = document) => root.querySelector(selector);
  
  /**
   * Multiple element selector - wraps querySelectorAll and converts to array
   * Provides array methods (forEach, map, filter, etc.) on NodeList results
   * 
   * @param {string} selector - CSS selector string
   * @param {Element} root - Optional root element to search within (defaults to document)
   * @returns {Array<Element>} - Array of all matching elements
   */
  const $$ = (selector, root = document) =>
    Array.from(root.querySelectorAll(selector));

  // ========== Date/Time Utility Functions ==========
  
  /**
   * Converts a date string (YYYY-MM-DD) to Unix timestamp at midnight UTC
   * Used for date comparisons and range calculations
   * 
   * @param {string} dateStr - Date in YYYY-MM-DD format
   * @returns {number} - Unix timestamp (milliseconds)
   */
  const toTs = (dateStr) => new Date(dateStr + "T00:00:00").getTime();

  /**
   * Adds a number of days to a date string
   * Returns new date in YYYY-MM-DD format
   * 
   * @param {string} dateStr - Date in YYYY-MM-DD format
   * @param {number} daysToAdd - Number of days to add (can be negative)
   * @returns {string} - New date in YYYY-MM-DD format
   */
  const addDays = (dateStr, daysToAdd) => {
    const dt = new Date(dateStr + "T00:00:00");
    dt.setDate(dt.getDate() + daysToAdd);
    const y = dt.getFullYear();
    const m = String(dt.getMonth() + 1).padStart(2, "0");
    const d = String(dt.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
  };

  /**
   * Extracts date and time parts from ISO 8601 datetime string
   * Returns object with separate date and time components
   * 
   * @param {string} iso - ISO datetime string (e.g., "2024-03-22T14:30:00")
   * @returns {object} - Object with { d: "YYYY-MM-DD", t: "HH:MM" }
   */
  const isoToParts = (iso) => {
    if (!iso || !iso.includes("T")) return { d: "", t: "" };
    const [d, t] = iso.split("T");
    return { d: d || "", t: "" + (t || "").slice(0, 5) };
  };

  /**
   * Extracts just the date part (YYYY-MM-DD) from ISO datetime string
   * 
   * @param {string} iso - ISO datetime string
   * @returns {string} - Date in YYYY-MM-DD format, or empty string
   */
  const dateOnlyFromISO = (iso) => (iso ? iso.split("T")[0] : "");
  
  /**
   * Extracts just the time part (HH:MM) from ISO datetime string
   * 
   * @param {string} iso - ISO datetime string
   * @returns {string} - Time in HH:MM format, or empty string
   */
  const timeOnlyFromISO = (iso) =>
    iso && iso.includes("T") ? iso.split("T")[1].slice(0, 5) : "";

  // ========== Initialize Calendar on DOM Ready ==========
  // Wait for DOM to be fully loaded before attaching event listeners

  document.addEventListener("DOMContentLoaded", () => {
    // ========== DOM Elements Cache ==========
    // Store references to frequently accessed DOM elements for performance
    
    // Modal and its controls
    const modal = $("#calModal");
    const openBtn = $("#openAdd");
    const closeBackdrop = $("#closeAdd");
    const cancelBtn = $("#cancelAdd");

    // Modal form elements
    const modalTitle = $("#modalTitle");
    const form = $(".cal-form");
    const formAction = $("#formAction");
    const entryId = $("#entryId");

    // Form input fields
    const titleInput = $("#title");
    const startDateInput = $("#start_date");
    const startTimeInput = $("#start_time");
    const endDateInput = $("#end_date");
    const endTimeInput = $("#end_time");
    const notesInput = $("#notes");

    // View tabs and containers
    const tabs = $$(".cal-tab[data-view]");
    const viewMonth = $("#viewMonth");
    const viewWeek = $("#viewWeek");
    const viewDay = $("#viewDay");
    const weekHead = $("#weekHead");
    const weekGrid = $("#weekGrid");
    const dayHead = $("#dayHead");
    const dayList = $("#dayList");

    // Exit early if critical elements are missing
    if (!modal || !form || !openBtn) return;

    // ========== Calendar State ==========
    // Track the current view mode and selected date
    
    // Current view mode: "month", "week", or "day"
    let currentView = "month";
    
    // Currently selected day for view rendering
    // Initialize to today's date in YYYY-MM-DD format
    let selectedDay = (() => {
      const now = new Date();
      const y = now.getFullYear();
      const m = String(now.getMonth() + 1).padStart(2, "0");
      const d = String(now.getDate()).padStart(2, "0");
      return `${y}-${m}-${d}`;
    })();

    // ========== Modal Management Functions ==========
    
    /**
     * Opens the calendar entry modal
     * Shows modal, prevents body scroll, and focuses title input
     */
    function openModal() {
      modal.classList.add("show");
      modal.setAttribute("aria-hidden", "false");
      document.body.classList.add("no-scroll");
      // Use setTimeout to ensure focus happens after reflow
      setTimeout(() => titleInput?.focus(), 0);
    }

    /**
     * Closes the calendar entry modal
     * Hides modal and restores body scroll
     */
    function closeModal() {
      modal.classList.remove("show");
      modal.setAttribute("aria-hidden", "true");
      document.body.classList.remove("no-scroll");
    }

    /**
     * Resets form to "add new entry" mode
     * Clears all fields and sets action to "add_entry"
     */
    function resetToAddMode() {
      modalTitle.textContent = "Add Calendar Entry";
      formAction.value = "add_entry";
      entryId.value = "";
      titleInput.value = "";
      startDateInput.value = "";
      startTimeInput.value = "08:00";
      endDateInput.value = "";
      endTimeInput.value = "";
      notesInput.value = "";
    }

    /**
     * Loads entry data into form for editing
     * Extracts data from pill element's dataset and populates form fields
     * 
     * @param {Element} pill - Calendar entry pill element
     */
    function loadEditDataFromPill(pill) {
      modalTitle.textContent = "Edit Calendar Entry";
      formAction.value = "edit_entry";
      entryId.value = pill.dataset.entryId || "";
      titleInput.value = pill.dataset.title || "";
      notesInput.value = pill.dataset.notes || "";

      // Parse and load start datetime
      const startParts = isoToParts(pill.dataset.start || "");
      startDateInput.value = startParts.d || "";
      startTimeInput.value = startParts.t || "08:00";

      // Parse and load end datetime if present
      if (pill.dataset.end) {
        const endParts = isoToParts(pill.dataset.end);
        endDateInput.value = endParts.d || "";
        endTimeInput.value = endParts.t || "";
      } else {
        endDateInput.value = "";
        endTimeInput.value = "";
      }
    }

    /**
     * Sets the date range in the form inputs
     * Used when selecting a date or date range in the calendar
     * 
     * @param {string} startDate - Start date in YYYY-MM-DD format
     * @param {string} endDate - End date in YYYY-MM-DD format (defaults to startDate)
     */
    function setDateRange(startDate, endDate) {
      startDateInput.value = startDate || "";
      endDateInput.value = endDate || startDate || "";
    }

    // ========== Entry Data Collection ==========
    
    /**
     * Collects all calendar entries from DOM elements
     * Reads data from pill elements' dataset attributes
     * Deduplicates entries by ID
     * 
     * @returns {Array<Object>} - Array of entry objects with id, title, dates, etc.
     */
    function collectEntriesFromDOM() {
      const map = new Map();

      // Iterate through all pill elements and extract their data
      $$(".pill[data-entry-id]").forEach((pill) => {
        const id = pill.dataset.entryId;
        if (!id || map.has(id)) return;

        // Store entry data, using dataset attributes as source
        map.set(id, {
          id,
          title: pill.dataset.title || "",
          start: pill.dataset.start || "",
          end: pill.dataset.end || "",
          notes: pill.dataset.notes || "",
          eventId: pill.dataset.eventId || "",
          eventName: pill.dataset.eventName || "",
        });
      });

      return Array.from(map.values());
    }

    /**
     * Checks if an entry overlaps with a given day
     * Uses ISO date comparison for accuracy
     * 
     * @param {Object} entry - Entry object with start and end dates
     * @param {string} dayStr - Date in YYYY-MM-DD format to check
     * @returns {boolean} - True if entry spans the given day
     */
    function overlapsDay(entry, dayStr) {
      const startDate = dateOnlyFromISO(entry.start);
      const endDate = entry.end ? dateOnlyFromISO(entry.end) : startDate;
      return toTs(dayStr) >= toTs(startDate) && toTs(dayStr) <= toTs(endDate);
    }

    /**
     * Calculates the start of the week (Sunday) for a given date
     * Returns date in YYYY-MM-DD format
     * 
     * @param {string} dayStr - Date in YYYY-MM-DD format
     * @returns {string} - Start of week (Sunday) in YYYY-MM-DD format
     */
    function startOfWeek(dayStr) {
      const d = new Date(dayStr + "T00:00:00");
      const dayOfWeek = d.getDay();
      d.setDate(d.getDate() - dayOfWeek);
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, "0");
      const day = String(d.getDate()).padStart(2, "0");
      return `${y}-${m}-${day}`;
    }

    // ========== View Management Functions ==========
    
    /**
     * Sets the active tab styling based on current view
     * Updates CSS classes on all view tabs
     * 
     * @param {string} view - View name to set as active
     */
    function setActiveTab(view) {
      tabs.forEach((tab) => {
        tab.classList.toggle("active", tab.dataset.view === view);
      });
    }

    /**
     * Shows a specific calendar view and hides others
     * Triggers rendering for week and day views
     * 
     * @param {string} view - View to show: "month", "week", or "day"
     */
    function showView(view) {
      currentView = view;
      setActiveTab(view);

      // Toggle visibility of view containers
      if (viewMonth) viewMonth.hidden = view !== "month";
      if (viewWeek) viewWeek.hidden = view !== "week";
      if (viewDay) viewDay.hidden = view !== "day";

      // Trigger rendering for dynamic views
      if (view === "week") renderWeek(selectedDay);
      if (view === "day") renderDay(selectedDay);
    }

    // ========== View Tab Click Handlers ==========
    // Allow users to switch between calendar views
    tabs.forEach((tab) => {
      tab.addEventListener("click", () => showView(tab.dataset.view));
    });

    // ========== Modal Open/Close Event Listeners ==========
    
    // Open modal when "Add Entry" button is clicked
    openBtn.addEventListener("click", (e) => {
      e.preventDefault();
      resetToAddMode();
      setDateRange(selectedDay, selectedDay);
      openModal();
    });

    // Close modal on backdrop click or cancel button click
    [closeBackdrop, cancelBtn].forEach((btn) => {
      if (btn) btn.addEventListener("click", closeModal);
    });

    // Close modal on Escape key press
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && modal.classList.contains("show")) {
        closeModal();
      }
    });

    // ========== Date Range Selection Drag State ==========
    // Manage pointer events for drag-to-select date range functionality
    
    const dragState = {
      dragging: false,  // Is user currently dragging?
      start: "",        // Start date of drag
      end: "",          // Current end date of drag
    };

    /**
     * Clears the visual range highlight from calendar cells
     * Removes "range" CSS class from all highlighted cells
     */
    function clearRange() {
      $$(".cal-cell.range").forEach((cell) => cell.classList.remove("range"));
    }

    /**
     * Highlights a date range on the calendar
     * Adds "range" CSS class to all cells between two dates
     * 
     * @param {string} a - First date in YYYY-MM-DD format
     * @param {string} b - Second date in YYYY-MM-DD format
     */
    function highlightRange(a, b) {
      clearRange();
      if (!a || !b) return;

      const from = Math.min(toTs(a), toTs(b));
      const to = Math.max(toTs(a), toTs(b));

      $$(".cal-cell[data-date]").forEach((cell) => {
        const cellDate = cell.dataset.date;
        const ts = toTs(cellDate);
        if (ts >= from && ts <= to) {
          cell.classList.add("range");
        }
      });
    }

    /**
     * Handles click on a calendar cell in month view
     * Opens modal to create new entry for that day
     * Ignores clicks on pills, forms, buttons, or links
     * 
     * @param {Element} cell - Calendar cell element
     * @param {Event} e - Click event object
     */
    function handleCellClick(cell, e) {
      // Don't open modal if user clicked nested element
      if (
        e.target.closest(".pill") ||
        e.target.closest("form") ||
        e.target.closest("button") ||
        e.target.closest("a")
      ) {
        return;
      }

      selectedDay = cell.dataset.date;
      resetToAddMode();
      setDateRange(selectedDay, selectedDay);
      openModal();
    }

    // ========== Month View Click Events ==========
    // Allow users to click on calendar cells to create new entries
    $$(".cal-cell[data-date]").forEach((cell) => {
      cell.addEventListener("click", (e) => handleCellClick(cell, e));
    });

    // ========== Date Range Selection via Drag ==========
    // Allow users to drag across multiple days to select a date range
    
    const monthGrid = $(".cal-grid");
    if (monthGrid) {
      /**
       * Handle pointer down - start drag selection
       * Records the initial cell and prepares for drag tracking
       */
      monthGrid.addEventListener("pointerdown", (e) => {
        const cell = e.target.closest(".cal-cell[data-date]");
        if (!cell) return;

        // Don't start drag if user clicked nested element
        if (
          e.target.closest(".pill") ||
          e.target.closest("form") ||
          e.target.closest("button") ||
          e.target.closest("a")
        ) {
          return;
        }

        dragState.dragging = true;
        dragState.start = cell.dataset.date;
        dragState.end = cell.dataset.date;
        highlightRange(dragState.start, dragState.end);
      });

      /**
       * Handle pointer move - update drag selection
       * Updates the end date and visual highlight as user drags
       */
      monthGrid.addEventListener("pointermove", (e) => {
        if (!dragState.dragging) return;

        const cell = e.target.closest(".cal-cell[data-date]");
        if (!cell) return;

        const date = cell.dataset.date;
        if (date && date !== dragState.end) {
          dragState.end = date;
          highlightRange(dragState.start, dragState.end);
        }
      });

      /**
       * Handle pointer up - complete drag selection
       * Normalizes date range and opens modal with selected dates
       */
      window.addEventListener("pointerup", () => {
        if (!dragState.dragging) return;

        dragState.dragging = false;

        const a = dragState.start;
        const b = dragState.end;
        if (!a || !b) return;

        // Normalize range so from <= to
        const from = toTs(a) <= toTs(b) ? a : b;
        const to = toTs(a) <= toTs(b) ? b : a;

        selectedDay = from;
        resetToAddMode();
        setDateRange(from, to);
        openModal();

        // Clear visual highlight after brief delay
        setTimeout(clearRange, 200);
      });
    }

    // ========== Entry Pill Event Binding ==========
    
    /**
     * Binds click handlers to editable calendar entry pills
     * Skips event-linked pills (which should open event instead)
     * Uses flag to prevent duplicate event binding
     * 
     * @param {Element} root - Root element to search for pills (defaults to document)
     */
    function bindEditablePills(root = document) {
      $$(".pill[data-entry-id]", root).forEach((pill) => {
        const isLinkedEvent =
          !!pill.dataset.eventId && pill.dataset.eventId !== "0";
        if (isLinkedEvent) return;

        // Bind click handler to pill itself (if not already bound)
        if (!pill.dataset.clickbound) {
          pill.dataset.clickbound = "1";
          pill.addEventListener("click", (e) => {
            // Don't open edit if user clicked nested element
            if (
              e.target.closest(".pill-actions") ||
              e.target.closest("button") ||
              e.target.closest("form") ||
              e.target.closest("a")
            ) {
              return;
            }

            loadEditDataFromPill(pill);
            openModal();
          });
        }

        // Bind edit button click handler (if not already bound)
        const editBtn = $(".pill-btn.edit", pill);
        if (editBtn && !editBtn.dataset.bound) {
          editBtn.dataset.bound = "1";
          editBtn.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            loadEditDataFromPill(pill);
            openModal();
          });
        }
      });
    }

    // ========== Entry Element Creation ==========
    
    /**
     * Creates a DOM element for an entry in week/day view
     * Includes entry details, time, and action buttons
     * 
     * @param {Object} entry - Entry object with id, title, dates, notes, eventId
     * @returns {Element} - Pill div element with all entry information
     */
    function createWeekDayEntryElement(entry) {
      // Check if entry is linked to an event (read-only)
      const isLinkedEvent = !!entry.eventId && entry.eventId !== "0";

      // Create main pill container
      const pill = document.createElement("div");
      pill.className = "pill" + (isLinkedEvent ? " pill-event" : "");
      pill.dataset.entryId = entry.id;
      pill.dataset.title = entry.title;
      pill.dataset.start = entry.start;
      pill.dataset.end = entry.end || "";
      pill.dataset.notes = entry.notes || "";
      pill.dataset.eventId = entry.eventId || "";
      pill.dataset.eventName = entry.eventName || "";

      // Add entry title
      const title = document.createElement("div");
      title.className = "pill-title";
      title.textContent = entry.title;
      pill.appendChild(title);

      // Add time range (if available)
      const startTime = timeOnlyFromISO(entry.start);
      const endTime = entry.end ? timeOnlyFromISO(entry.end) : "";
      const timeText = startTime
        ? endTime
          ? `${startTime}–${endTime}`
          : startTime
        : "";

      if (timeText) {
        const time = document.createElement("div");
        time.className = "pill-time";
        time.textContent = timeText;
        pill.appendChild(time);
      }

      // Add action buttons (edit, delete, or view link)
      const actions = document.createElement("div");
      actions.className = "pill-actions";

      if (isLinkedEvent) {
        // Event-linked entries show a view link
        const link = document.createElement("a");
        link.href = `view_event.php?id=${entry.eventId}`;
        link.className = "pill-btn edit";
        link.title = "View Event";
        link.innerHTML =
          '<i class="fa-solid fa-arrow-up-right-from-square"></i>';
        actions.appendChild(link);
      } else {
        // Regular entries show edit and delete buttons
        const editBtn = document.createElement("button");
        editBtn.type = "button";
        editBtn.className = "pill-btn edit";
        editBtn.title = "Edit";
        editBtn.innerHTML = '<i class="fa-solid fa-pen"></i>';
        actions.appendChild(editBtn);

        // Create delete form (for CSRF protection)
        const deleteForm = document.createElement("form");
        deleteForm.method = "post";
        deleteForm.className = "pill-del";
        deleteForm.onsubmit = () => confirm("Delete this entry?");

        // Get CSRF token from page (for security)
        const csrf =
          document.querySelector('input[name="csrf_token"]')?.value || "";

        deleteForm.innerHTML = `
          <input type="hidden" name="csrf_token" value="${csrf}">
          <input type="hidden" name="action" value="delete_entry">
          <input type="hidden" name="entry_id" value="${entry.id}">
          <button type="submit" class="pill-btn del" title="Delete">
            <i class="fa-solid fa-trash-can"></i>
          </button>
        `;
        actions.appendChild(deleteForm);
      }

      pill.appendChild(actions);
      return pill;
    }

    // ========== Week View Rendering ==========
    
    /**
     * Renders the week view starting from given anchor day
     * Shows 7 days with their entries sorted chronologically
     * 
     * @param {string} anchorDay - Date in YYYY-MM-DD format to base week on
     */
    function renderWeek(anchorDay) {
      if (!weekHead || !weekGrid) return;

      // Calculate week start (Sunday)
      const start = startOfWeek(anchorDay);
      const weekdayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
      const entries = collectEntriesFromDOM();

      // Clear previous week view
      weekHead.innerHTML = "";
      weekGrid.innerHTML = "";

      // Render each day of the week
      for (let i = 0; i < 7; i++) {
        const d = addDays(start, i);
        const dt = new Date(d + "T00:00:00");
        const label = `${weekdayNames[i]} • ${dt.toLocaleDateString(undefined, {
          month: "short",
          day: "numeric",
        })}`;

        // Create day header
        const head = document.createElement("div");
        head.className = "wh";
        head.textContent = label;
        weekHead.appendChild(head);

        // Create day column
        const col = document.createElement("div");
        col.className = "week-col";
        col.dataset.date = d;

        // Add date label
        const top = document.createElement("div");
        top.className = "wk-date";
        top.textContent = d;
        col.appendChild(top);

        // Add entries for this day (sorted by start time)
        entries
          .filter((entry) => overlapsDay(entry, d))
          .sort((a, b) => (a.start || "").localeCompare(b.start || ""))
          .forEach((entry) => {
            col.appendChild(createWeekDayEntryElement(entry));
          });

        // Allow clicking column to switch to day view
        col.addEventListener("click", (e) => {
          if (
            e.target.closest(".pill") ||
            e.target.closest("form") ||
            e.target.closest("button") ||
            e.target.closest("a")
          ) {
            return;
          }

          selectedDay = d;
          showView("day");
        });

        weekGrid.appendChild(col);
      }

      // Bind edit handlers to entry pills in week view
      bindEditablePills(weekGrid);
    }

    // ========== Day View Rendering ==========
    
    /**
     * Renders the day view for the selected day
     * Shows all entries for the day in a detailed list format
     * 
     * @param {string} dayStr - Date in YYYY-MM-DD format to display
     */
    function renderDay(dayStr) {
      if (!dayHead || !dayList) return;

      // Update selected day and header
      selectedDay = dayStr;
      dayHead.textContent = `Day View • ${dayStr}`;
      dayList.innerHTML = "";

      // Collect and sort entries for this day
      const entries = collectEntriesFromDOM()
        .filter((entry) => overlapsDay(entry, dayStr))
        .sort((a, b) => (a.start || "").localeCompare(b.start || ""));

      // Show empty state if no entries
      if (entries.length === 0) {
        const empty = document.createElement("div");
        empty.style.color = "rgba(0,0,0,.55)";
        empty.style.fontWeight = "800";
        empty.textContent = "No entries for this day.";
        dayList.appendChild(empty);
        return;
      }

      // Render each entry in day view
      entries.forEach((entry) => {
        const row = document.createElement("div");
        row.className = "day-item";

        // Left section: title and notes
        const left = document.createElement("div");
        left.className = "di-left";

        const title = document.createElement("div");
        title.className = "di-title";
        title.textContent = entry.title;

        const notes = document.createElement("div");
        notes.className = "di-notes";
        notes.textContent = entry.notes || "";

        left.append(title, notes);

        // Middle section: time range
        const right = document.createElement("div");
        right.className = "di-time";

        const startTime = timeOnlyFromISO(entry.start);
        const endTime = entry.end ? timeOnlyFromISO(entry.end) : "";
        right.textContent = startTime
          ? endTime
            ? `${startTime}–${endTime}`
            : startTime
          : "";

        // Right section: action buttons
        const actionsWrap = document.createElement("div");
        actionsWrap.style.display = "flex";
        actionsWrap.style.gap = "10px";
        actionsWrap.style.alignItems = "center";

        const pill = createWeekDayEntryElement(entry);
        const actions = $(".pill-actions", pill);
        if (actions) {
          actionsWrap.appendChild(actions);
        }

        row.append(left, right, actionsWrap);
        dayList.appendChild(row);
      });

      // Bind edit handlers to entry pills in day view
      bindEditablePills(dayList);
    }

    // ========== Initialization ==========
    // Bind events to initial DOM and show default view
    bindEditablePills(document);
    showView("month");
  });
})();
