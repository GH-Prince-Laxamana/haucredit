(function () {
  // ===== UTILITY FUNCTIONS =====
  // Define shorthand functions for DOM selection to reduce code verbosity
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  // ===== DATE HELPER FUNCTIONS =====
  // Convert date string to timestamp for comparison operations
  const toTs = (d) => new Date(d + "T00:00:00").getTime();

  // Add days to a date string and return formatted date
  const addDays = (d, n) => {
    const dt = new Date(d + "T00:00:00");
    dt.setDate(dt.getDate() + n);
    const y = dt.getFullYear();
    const m = String(dt.getMonth() + 1).padStart(2, "0");
    const day = String(dt.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
  };

  // Split ISO datetime string into date and time parts
  const isoToParts = (iso) => {
    if (!iso || !iso.includes("T")) return { d: "", t: "" };
    const [d, t] = iso.split("T");
    return { d: d || "", t: t || "" };
  };

  // Extract date part from ISO datetime string
  const dateOnlyFromISO = (iso) => (iso ? iso.split("T")[0] : "");

  // Extract time part from ISO datetime string
  const timeOnlyFromISO = (iso) =>
    iso && iso.includes("T") ? iso.split("T")[1] : "";

  // ===== BUILD EDIT BUTTON WITH FA ICON =====
  // Create edit button element with Font Awesome icon for consistency with calendar.php
  const buildPillElement = () => {
    const editBtn = document.createElement("button");
    editBtn.type = "button";
    editBtn.className = "pill-btn edit";
    editBtn.title = "Edit";

    // Create Font Awesome icon element instead of using text content
    const icon = document.createElement("i");
    icon.className = "fa-solid fa-pen";
    editBtn.appendChild(icon);

    return editBtn;
  };

  // ===== BUILD DELETE FORM WITH FA ICON =====
  // Create delete form element with Font Awesome icon for consistency with calendar.php
  const buildDeleteForm = (entryId, csrfToken) => {
    const delForm = document.createElement("form");
    delForm.className = "pill-del";
    delForm.method = "post";

    // Create hidden input for CSRF token to prevent cross-site request forgery
    const csrf = document.createElement("input");
    csrf.type = "hidden";
    csrf.name = "csrf_token";
    csrf.value = csrfToken;

    // Create hidden input for action type
    const act = document.createElement("input");
    act.type = "hidden";
    act.name = "action";
    act.value = "delete_entry";

    // Create hidden input for entry ID
    const idInp = document.createElement("input");
    idInp.type = "hidden";
    idInp.name = "entry_id";
    idInp.value = entryId;

    // Create hidden input to indicate AJAX request
    const ajax = document.createElement("input");
    ajax.type = "hidden";
    ajax.name = "ajax";
    ajax.value = "1";

    // Create delete button with Font Awesome icon
    const delBtn = document.createElement("button");
    delBtn.type = "submit";
    delBtn.className = "pill-btn del";
    delBtn.title = "Delete";

    // Create Font Awesome icon element instead of using text content
    const icon = document.createElement("i");
    icon.className = "fa-solid fa-trash-can";
    delBtn.appendChild(icon);

    // Append all elements to the form
    delForm.append(csrf, act, idInp, ajax, delBtn);
    return delForm;
  };

  // ===== DOM CONTENT LOADED EVENT =====
  // Execute code after DOM is fully loaded to ensure all elements are available
  document.addEventListener("DOMContentLoaded", () => {
    // ===== DOM ELEMENT SELECTIONS =====
    // Select modal and related elements for calendar entry management
    const modal = $("#calModal");
    const openBtn = $("#openAdd");
    const closeBackdrop = $("#closeAdd");
    const closeBtn = $("#closeAddBtn");
    const cancelBtn = $("#cancelAdd");

    // Select form elements for calendar entry input
    const modalTitle = $("#modalTitle");
    const form = $(".cal-form");
    const formAction = $("#formAction");
    const entryId = $("#entryId");

    // Select input fields for calendar entry details
    const title = $("#title");
    const startDate = $("#start_date");
    const startTime = $("#start_time");
    const endDate = $("#end_date");
    const endTime = $("#end_time");
    const notes = $("#notes");

    // Get month boundaries from data attributes for rendering constraints
    const monthStart = document.body.dataset.monthStart;
    const monthEnd = document.body.dataset.monthEnd;

    // ===== TRACKER ELEMENTS =====
    // Select elements for displaying calendar statistics (if present)
    const tEntries = $("#tEntries");
    const tDays = $("#tDays");
    const tUpcoming = $("#tUpcoming");

    // ===== VIEW ELEMENTS =====
    // Select elements for different calendar view modes
    const tabs = $$(".cal-tab[data-view]");
    const viewMonth = $("#viewMonth");
    const viewWeek = $("#viewWeek");
    const viewDay = $("#viewDay");

    // Select elements for week and day view rendering
    const weekHead = $("#weekHead");
    const weekGrid = $("#weekGrid");
    const dayHead = $("#dayHead");
    const dayList = $("#dayList");

    // Early return if required elements are missing to prevent errors
    if (!modal || !openBtn || !form) return;

    // ===== STATE VARIABLES =====
    // Track current view mode and selected day for calendar navigation
    let currentView = "month";
    let selectedDay = (() => {
      const now = new Date();
      const yyyy = now.getFullYear();
      const mm = String(now.getMonth() + 1).padStart(2, "0");
      const dd = String(now.getDate()).padStart(2, "0");
      return `${yyyy}-${mm}-${dd}`;
    })();

    // ===== MODAL MANAGEMENT FUNCTIONS =====
    // Function to open the modal with proper accessibility attributes
    const open = () => {
      modal.classList.add("show");
      modal.setAttribute("aria-hidden", "false");
      document.body.classList.add("no-scroll");
      setTimeout(() => title && title.focus(), 0);
    };

    // Function to close the modal and restore page state
    const close = () => {
      modal.classList.remove("show");
      modal.setAttribute("aria-hidden", "true");
      document.body.classList.remove("no-scroll");
    };

    // Function to reset modal to add mode for new entries
    const resetToAddMode = () => {
      modalTitle.textContent = "Add Calendar Entry";
      formAction.value = "add_entry";
      entryId.value = "";
      title.value = "";
      startTime.value = "08:00";
      endDate.value = "";
      endTime.value = "";
      notes.value = "";
    };

    // Function to set date range in form inputs
    const setDateRange = (a, b) => {
      startDate.value = a || "";
      endDate.value = b || a || "";
    };

    // ===== EVENT LISTENERS FOR MODAL =====
    // Handle opening modal for adding new entries
    openBtn.addEventListener("click", (e) => {
      e.preventDefault();
      resetToAddMode();
      setDateRange(selectedDay, selectedDay);
      open();
    });

    // Handle closing modal via backdrop, close button, or cancel button
    [closeBackdrop, closeBtn, cancelBtn].forEach((btn) => {
      if (btn) btn.addEventListener("click", close);
    });

    // Handle closing modal with Escape key for accessibility
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && modal.classList.contains("show")) close();
    });

    // ===== UTILITY FUNCTIONS =====
    // Collect calendar entries from DOM elements for rendering
    function collectEntriesFromDOM() {
      const map = new Map();
      $$(".pill[data-entry-id]").forEach((p) => {
        const id = p.dataset.entryId;
        if (map.has(id)) return;
        map.set(id, {
          id,
          title: p.dataset.title || "",
          start: p.dataset.start || "",
          end: p.dataset.end || "",
          notes: p.dataset.notes || "",
        });
      });
      return Array.from(map.values());
    }

    // Check if an entry overlaps with a specific day
    function overlapsDay(entry, dayStr) {
      const s = dateOnlyFromISO(entry.start);
      const e = entry.end ? dateOnlyFromISO(entry.end) : s;
      return toTs(dayStr) >= toTs(s) && toTs(dayStr) <= toTs(e);
    }

    // Calculate start of week for a given day
    function startOfWeek(dayStr) {
      const d = new Date(dayStr + "T00:00:00");
      const dow = d.getDay();
      d.setDate(d.getDate() - dow);
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, "0");
      const dd = String(d.getDate()).padStart(2, "0");
      return `${y}-${m}-${dd}`;
    }

    // ===== VIEW SWITCHING =====
    // Set active tab based on current view
    function setActiveTab(view) {
      tabs.forEach((t) =>
        t.classList.toggle("active", t.dataset.view === view),
      );
    }

    // Switch to specified view and render accordingly
    function showView(view) {
      currentView = view;
      setActiveTab(view);
      if (viewMonth) viewMonth.hidden = view !== "month";
      if (viewWeek) viewWeek.hidden = view !== "week";
      if (viewDay) viewDay.hidden = view !== "day";

      if (view === "week") renderWeek(selectedDay);
      if (view === "day") renderDay(selectedDay);
    }

    // Attach event listeners to view tabs
    tabs.forEach((btn) => {
      btn.addEventListener("click", () => showView(btn.dataset.view));
    });

    // ===== MONTH VIEW INTERACTIONS =====
    // State for drag selection in month view
    const dragState = { dragging: false, start: "", end: "" };

    // Clear highlighted range in calendar cells
    const clearRange = () =>
      $$(".cal-cell.range").forEach((c) => c.classList.remove("range"));

    // Highlight range of dates in calendar grid
    const highlightRange = (a, b) => {
      clearRange();
      if (!a || !b) return;
      const from = Math.min(toTs(a), toTs(b));
      const to = Math.max(toTs(a), toTs(b));
      $$(".cal-cell[data-date]").forEach((cell) => {
        const d = cell.dataset.date;
        const t = toTs(d);
        if (t >= from && t <= to) cell.classList.add("range");
      });
    };

    // Handle cell click to open modal for adding entry
    const handleCellClick = (cell) => {
      if (
        event.target.closest(".pill") ||
        event.target.closest("form") ||
        event.target.closest("button")
      )
        return;
      selectedDay = cell.dataset.date;
      resetToAddMode();
      setDateRange(selectedDay, selectedDay);
      open();
    };

    // Attach click listeners to calendar cells
    $$(".cal-cell[data-date]").forEach((cell) => {
      cell.addEventListener("click", () => handleCellClick(cell));
    });

    // Handle drag selection for date range in month view
    const grid = $(".cal-grid");
    if (grid) {
      grid.addEventListener("pointerdown", (e) => {
        const cell = e.target.closest(".cal-cell[data-date]");
        if (!cell) return;
        if (
          e.target.closest(".pill") ||
          e.target.closest("form") ||
          e.target.closest("button")
        )
          return;
        dragState.dragging = true;
        dragState.start = cell.dataset.date;
        dragState.end = dragState.start;
        highlightRange(dragState.start, dragState.end);
      });

      grid.addEventListener("pointermove", (e) => {
        if (!dragState.dragging) return;
        const cell = e.target.closest(".cal-cell[data-date]");
        if (!cell) return;
        const d = cell.dataset.date;
        if (d && d !== dragState.end) {
          dragState.end = d;
          highlightRange(dragState.start, dragState.end);
        }
      });

      window.addEventListener("pointerup", () => {
        if (!dragState.dragging) return;
        dragState.dragging = false;

        const a = dragState.start;
        const b = dragState.end;
        if (!a || !b) return;

        const from = toTs(a) <= toTs(b) ? a : b;
        const to = toTs(a) <= toTs(b) ? b : a;

        selectedDay = from;
        resetToAddMode();
        setDateRange(from, to);
        open();

        setTimeout(clearRange, 250);
      });
    }

    // ===== TRACKER UPDATE =====
    // Update statistics display elements
    function updateTracker(stats) {
      if (!stats) return;
      if (tEntries) tEntries.textContent = stats.entries;
      if (tDays) tDays.textContent = stats.entry_days;
      if (tUpcoming) tUpcoming.textContent = stats.upcoming;
    }

    // ===== DOM REMOVAL =====
    // Remove entry elements from all views
    function removeEntryEverywhere(id) {
      $$('.pill[data-entry-id="' + id + '"]').forEach((p) => p.remove());
    }

    // ===== BUILD PILL ELEMENT =====
    // Create visual pill element for calendar entries
    function buildPill(e) {
      const pill = document.createElement("div");
      pill.className = "pill";
      pill.dataset.entryId = e.id;
      pill.dataset.title = e.title;
      pill.dataset.start = e.start;
      pill.dataset.end = e.end || "";
      pill.dataset.notes = e.notes || "";

      // Create title element
      const t = document.createElement("div");
      t.className = "pill-title";
      t.textContent = e.title;

      // Create time display if available
      const st = timeOnlyFromISO(e.start);
      const et = e.end ? timeOnlyFromISO(e.end) : "";
      const timeText = st ? (et ? `${st}–${et}` : st) : "";

      pill.appendChild(t);
      if (timeText) {
        const ti = document.createElement("div");
        ti.className = "pill-time";
        ti.textContent = timeText;
        pill.appendChild(ti);
      }

      // Create actions container with edit and delete buttons
      const actions = document.createElement("div");
      actions.className = "pill-actions";
      
      const csrfToken = $('input[name="csrf_token"]')?.value || "";
      actions.append(buildPillElement(), buildDeleteForm(e.id, csrfToken));
      pill.appendChild(actions);

      return pill;
    }

    // ===== BIND EVENT HANDLERS =====
    // Bind event handlers to pill elements for editing and deleting
    function bindAll() {
      const bindEditHandler = (pill) => {
        // Function to load pill data into modal for editing
        const loadPillData = () => {
          modalTitle.textContent = "Edit Calendar Entry";
          formAction.value = "edit_entry";
          entryId.value = pill.dataset.entryId || "";
          title.value = pill.dataset.title || "";
          notes.value = pill.dataset.notes || "";

          const sp = isoToParts(pill.dataset.start || "");
          startDate.value = sp.d || "";
          startTime.value = sp.t || "08:00";

          if (pill.dataset.end) {
            const ep = isoToParts(pill.dataset.end);
            endDate.value = ep.d || "";
            endTime.value = ep.t || "";
          } else {
            endDate.value = "";
            endTime.value = "";
          }
        };

        // Handle pill click to open edit modal
        const handlePillClick = (e) => {
          if (
            e.target.closest(".pill-actions") ||
            e.target.closest("button") ||
            e.target.closest("form")
          )
            return;
          loadPillData();
          open();
        };

        // Handle edit button click
        const handleEditClick = (e) => {
          e.preventDefault();
          e.stopPropagation();
          loadPillData();
          open();
        };

        // Bind click event to pill if not already bound
        if (!pill.dataset.clickbound) {
          pill.dataset.clickbound = "1";
          pill.addEventListener("click", handlePillClick);
        }

        // Bind click event to edit button if not already bound
        const editBtn = pill.querySelector(".pill-btn.edit");
        if (editBtn && !editBtn.dataset.bound) {
          editBtn.dataset.bound = "1";
          editBtn.addEventListener("click", handleEditClick);
        }
      };

      // Bind edit handlers to all pills
      $$(".pill").forEach(bindEditHandler);

      // ===== DELETE HANDLER =====
      // Bind submit handlers to delete forms
      $$(".pill-del").forEach((f) => {
        if (f.dataset.bound) return;
        f.dataset.bound = "1";

        f.addEventListener("submit", async (e) => {
          e.preventDefault();
          const id = $('input[name="entry_id"]', f)?.value;
          if (!id) return;

          if (!confirm("Delete this entry?")) return;

          const fd = new FormData(f);
          fd.set("ajax", "1");

          const res = await fetch(window.location.href, {
            method: "POST",
            body: fd,
            headers: { "X-Requested-With": "XMLHttpRequest" },
          });

          const data = await res.json();
          if (!res.ok || !data.success) {
            alert(data.error || "Delete failed");
            return;
          }

          removeEntryEverywhere(id);

          // Re-render active view after deletion
          if (currentView === "week") renderWeek(selectedDay);
          if (currentView === "day") renderDay(selectedDay);

          updateTracker(data.stats);
        });
      });
    }

    // ===== RENDER WEEK VIEW =====
    // Render week view with entries for each day
    function renderWeek(anchorDay) {
      if (!weekHead || !weekGrid) return;

      const start = startOfWeek(anchorDay);
      const weekdayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
      const entries = collectEntriesFromDOM();

      weekHead.innerHTML = "";
      weekGrid.innerHTML = "";

      for (let i = 0; i < 7; i++) {
        const d = addDays(start, i);
        const dt = new Date(d + "T00:00:00");
        const label = `${weekdayNames[i]} • ${dt.toLocaleDateString(undefined, { month: "short", day: "numeric" })}`;

        // Create header for the day
        const h = document.createElement("div");
        h.className = "wh";
        h.textContent = label;
        weekHead.appendChild(h);

        // Create column for the day
        const col = document.createElement("div");
        col.className = "week-col";
        col.dataset.date = d;

        // Add date display
        const top = document.createElement("div");
        top.className = "wk-date";
        top.textContent = d;
        col.appendChild(top);

        // Add entries for this day
        entries
          .filter((e) => overlapsDay(e, d))
          .sort((a, b) => (a.start || "").localeCompare(b.start || ""))
          .forEach((e) => {
            const pill = buildPill(e);
            col.appendChild(pill);
          });

        // Handle column click to switch to day view
        col.addEventListener("click", (ev) => {
          if (
            ev.target.closest(".pill") ||
            ev.target.closest("form") ||
            ev.target.closest("button")
          )
            return;
          selectedDay = d;
          showView("day");
        });

        weekGrid.appendChild(col);
      }

      bindAll();
    }

    // ===== RENDER DAY VIEW =====
    // Render day view with detailed entries for selected day
    function renderDay(dayStr) {
      if (!dayHead || !dayList) return;

      selectedDay = dayStr;
      dayHead.textContent = `Day View • ${dayStr}`;
      dayList.innerHTML = "";

      const entries = collectEntriesFromDOM()
        .filter((e) => overlapsDay(e, dayStr))
        .sort((a, b) => (a.start || "").localeCompare(b.start || ""));

      if (entries.length === 0) {
        const empty = document.createElement("div");
        empty.style.color = "rgba(0,0,0,.55)";
        empty.style.fontWeight = "800";
        empty.textContent = "No entries for this day.";
        dayList.appendChild(empty);
        return;
      }

      entries.forEach((e) => {
        const row = document.createElement("div");
        row.className = "day-item";

        const left = document.createElement("div");
        left.className = "di-left";

        // Title and notes
        const tt = document.createElement("div");
        tt.className = "di-title";
        tt.textContent = e.title;

        const nn = document.createElement("div");
        nn.className = "di-notes";
        nn.textContent = e.notes || "";

        left.append(tt, nn);

        // Time display
        const right = document.createElement("div");
        right.className = "di-time";
        const st = timeOnlyFromISO(e.start);
        const et = e.end ? timeOnlyFromISO(e.end) : "";
        right.textContent = st ? (et ? `${st}–${et}` : st) : "";

        // Actions
        const pill = buildPill(e);
        pill.style.marginTop = "0";
        pill.style.width = "fit-content";

        const actionsWrap = document.createElement("div");
        actionsWrap.style.display = "flex";
        actionsWrap.style.gap = "10px";
        actionsWrap.style.alignItems = "center";
        actionsWrap.appendChild(pill.querySelector(".pill-actions"));

        row.append(left, right, actionsWrap);
        dayList.appendChild(row);
      });

      bindAll();
    }

    // Initial binding of event handlers
    bindAll();

    // ===== AJAX FORM SUBMISSION =====
    // Handle form submission for adding/editing entries
    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      const fd = new FormData(form);
      fd.set("ajax", "1");

      const res = await fetch(form.getAttribute("action"), {
        method: "POST",
        body: fd,
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });

      const data = await res.json();
      if (!res.ok || !data.success) {
        alert(data.error || "Save failed");
        return;
      }

      // Remove old entry if editing
      if (data.mode === "edit")
        removeEntryEverywhere(String(data.entry.entry_id));

      // Render new entry across relevant days in month view
      const entry = data.entry;
      const startD = entry.start_date;
      const endD = entry.end_date;

      let cur = startD;
      while (toTs(cur) <= toTs(endD)) {
        // Only render if within current month
        if (toTs(cur) >= toTs(monthStart) && toTs(cur) <= toTs(monthEnd)) {
          const cell = $('.cal-cell[data-date="' + cur + '"]');
          if (cell) {
            const spanClass = (() => {
              if (startD === endD) return "";
              if (cur === startD) return " span-start";
              if (cur === endD) return " span-end";
              return " span-mid";
            })();

            const pill = document.createElement("div");
            pill.className = "pill" + spanClass;
            pill.dataset.entryId = entry.entry_id;
            pill.dataset.title = entry.title;
            pill.dataset.start = entry.start_iso;
            pill.dataset.end = entry.end_iso || "";
            pill.dataset.notes = entry.notes || "";

            const t = document.createElement("div");
            t.className = "pill-title";
            t.textContent = entry.title;
            pill.appendChild(t);

            // Show time only on start day
            if (cur === startD && entry.time_label) {
              const ti = document.createElement("div");
              ti.className = "pill-time";
              ti.textContent = entry.time_label;
              pill.appendChild(ti);
            }

            // Add actions
            const actions = document.createElement("div");
            actions.className = "pill-actions";
            const csrfToken = $('input[name="csrf_token"]')?.value || "";
            actions.append(buildPillElement(), buildDeleteForm(entry.entry_id, csrfToken));
            pill.appendChild(actions);

            cell.appendChild(pill);
          }
        }
        cur = addDays(cur, 1);
      }

      // Bind handlers to new elements
      bindAll();

      // Re-render week/day views if active
      if (currentView === "week") renderWeek(selectedDay);
      if (currentView === "day") renderDay(selectedDay);

      updateTracker(data.stats);
      close();
      form.reset();
    });

    // Set default view to month
    showView("month");
  });
})();
