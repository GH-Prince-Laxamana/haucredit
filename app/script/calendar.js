(function () {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) =>
    Array.from(root.querySelectorAll(selector));

  const toTs = (dateStr) => new Date(dateStr + "T00:00:00").getTime();

  const addDays = (dateStr, daysToAdd) => {
    const dt = new Date(dateStr + "T00:00:00");
    dt.setDate(dt.getDate() + daysToAdd);
    const y = dt.getFullYear();
    const m = String(dt.getMonth() + 1).padStart(2, "0");
    const d = String(dt.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
  };

  const isoToParts = (iso) => {
    if (!iso || !iso.includes("T")) return { d: "", t: "" };
    const [d, t] = iso.split("T");
    return { d: d || "", t: "" + (t || "").slice(0, 5) };
  };

  const dateOnlyFromISO = (iso) => (iso ? iso.split("T")[0] : "");
  const timeOnlyFromISO = (iso) =>
    iso && iso.includes("T") ? iso.split("T")[1].slice(0, 5) : "";

  document.addEventListener("DOMContentLoaded", () => {
    const modal = $("#calModal");
    const openBtn = $("#openAdd");
    const closeBackdrop = $("#closeAdd");
    const cancelBtn = $("#cancelAdd");

    const modalTitle = $("#modalTitle");
    const form = $(".cal-form");
    const formAction = $("#formAction");
    const entryId = $("#entryId");

    const titleInput = $("#title");
    const startDateInput = $("#start_date");
    const startTimeInput = $("#start_time");
    const endDateInput = $("#end_date");
    const endTimeInput = $("#end_time");
    const notesInput = $("#notes");

    const tabs = $$(".cal-tab[data-view]");
    const viewMonth = $("#viewMonth");
    const viewWeek = $("#viewWeek");
    const viewDay = $("#viewDay");
    const weekHead = $("#weekHead");
    const weekGrid = $("#weekGrid");
    const dayHead = $("#dayHead");
    const dayList = $("#dayList");

    if (!modal || !form || !openBtn) return;

    let currentView = "month";
    let selectedDay = (() => {
      const now = new Date();
      const y = now.getFullYear();
      const m = String(now.getMonth() + 1).padStart(2, "0");
      const d = String(now.getDate()).padStart(2, "0");
      return `${y}-${m}-${d}`;
    })();

    function openModal() {
      modal.classList.add("show");
      modal.setAttribute("aria-hidden", "false");
      document.body.classList.add("no-scroll");
      setTimeout(() => titleInput?.focus(), 0);
    }

    function closeModal() {
      modal.classList.remove("show");
      modal.setAttribute("aria-hidden", "true");
      document.body.classList.remove("no-scroll");
    }

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

    function loadEditDataFromPill(pill) {
      modalTitle.textContent = "Edit Calendar Entry";
      formAction.value = "edit_entry";
      entryId.value = pill.dataset.entryId || "";
      titleInput.value = pill.dataset.title || "";
      notesInput.value = pill.dataset.notes || "";

      const startParts = isoToParts(pill.dataset.start || "");
      startDateInput.value = startParts.d || "";
      startTimeInput.value = startParts.t || "08:00";

      if (pill.dataset.end) {
        const endParts = isoToParts(pill.dataset.end);
        endDateInput.value = endParts.d || "";
        endTimeInput.value = endParts.t || "";
      } else {
        endDateInput.value = "";
        endTimeInput.value = "";
      }
    }

    function setDateRange(startDate, endDate) {
      startDateInput.value = startDate || "";
      endDateInput.value = endDate || startDate || "";
    }

    function collectEntriesFromDOM() {
      const map = new Map();

      $$(".pill[data-entry-id]").forEach((pill) => {
        const id = pill.dataset.entryId;
        if (!id || map.has(id)) return;

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

    function overlapsDay(entry, dayStr) {
      const startDate = dateOnlyFromISO(entry.start);
      const endDate = entry.end ? dateOnlyFromISO(entry.end) : startDate;
      return toTs(dayStr) >= toTs(startDate) && toTs(dayStr) <= toTs(endDate);
    }

    function startOfWeek(dayStr) {
      const d = new Date(dayStr + "T00:00:00");
      const dayOfWeek = d.getDay();
      d.setDate(d.getDate() - dayOfWeek);
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, "0");
      const day = String(d.getDate()).padStart(2, "0");
      return `${y}-${m}-${day}`;
    }

    function setActiveTab(view) {
      tabs.forEach((tab) => {
        tab.classList.toggle("active", tab.dataset.view === view);
      });
    }

    function showView(view) {
      currentView = view;
      setActiveTab(view);

      if (viewMonth) viewMonth.hidden = view !== "month";
      if (viewWeek) viewWeek.hidden = view !== "week";
      if (viewDay) viewDay.hidden = view !== "day";

      if (view === "week") renderWeek(selectedDay);
      if (view === "day") renderDay(selectedDay);
    }

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => showView(tab.dataset.view));
    });

    openBtn.addEventListener("click", (e) => {
      e.preventDefault();
      resetToAddMode();
      setDateRange(selectedDay, selectedDay);
      openModal();
    });

    [closeBackdrop, cancelBtn].forEach((btn) => {
      if (btn) btn.addEventListener("click", closeModal);
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && modal.classList.contains("show")) {
        closeModal();
      }
    });

    const dragState = {
      dragging: false,
      start: "",
      end: "",
    };

    function clearRange() {
      $$(".cal-cell.range").forEach((cell) => cell.classList.remove("range"));
    }

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

    function handleCellClick(cell, e) {
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

    $$(".cal-cell[data-date]").forEach((cell) => {
      cell.addEventListener("click", (e) => handleCellClick(cell, e));
    });

    const monthGrid = $(".cal-grid");
    if (monthGrid) {
      monthGrid.addEventListener("pointerdown", (e) => {
        const cell = e.target.closest(".cal-cell[data-date]");
        if (!cell) return;

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
        openModal();

        setTimeout(clearRange, 200);
      });
    }

    function bindEditablePills(root = document) {
      $$(".pill[data-entry-id]", root).forEach((pill) => {
        const isLinkedEvent =
          !!pill.dataset.eventId && pill.dataset.eventId !== "0";
        if (isLinkedEvent) return;

        if (!pill.dataset.clickbound) {
          pill.dataset.clickbound = "1";
          pill.addEventListener("click", (e) => {
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

    function createWeekDayEntryElement(entry) {
      const isLinkedEvent = !!entry.eventId && entry.eventId !== "0";

      const pill = document.createElement("div");
      pill.className = "pill" + (isLinkedEvent ? " pill-event" : "");
      pill.dataset.entryId = entry.id;
      pill.dataset.title = entry.title;
      pill.dataset.start = entry.start;
      pill.dataset.end = entry.end || "";
      pill.dataset.notes = entry.notes || "";
      pill.dataset.eventId = entry.eventId || "";
      pill.dataset.eventName = entry.eventName || "";

      const title = document.createElement("div");
      title.className = "pill-title";
      title.textContent = entry.title;
      pill.appendChild(title);

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

      const actions = document.createElement("div");
      actions.className = "pill-actions";

      if (isLinkedEvent) {
        const link = document.createElement("a");
        link.href = `view_event.php?id=${entry.eventId}`;
        link.className = "pill-btn edit";
        link.title = "View Event";
        link.innerHTML =
          '<i class="fa-solid fa-arrow-up-right-from-square"></i>';
        actions.appendChild(link);
      } else {
        const editBtn = document.createElement("button");
        editBtn.type = "button";
        editBtn.className = "pill-btn edit";
        editBtn.title = "Edit";
        editBtn.innerHTML = '<i class="fa-solid fa-pen"></i>';
        actions.appendChild(editBtn);

        const deleteForm = document.createElement("form");
        deleteForm.method = "post";
        deleteForm.className = "pill-del";
        deleteForm.onsubmit = () => confirm("Delete this entry?");

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
        const label = `${weekdayNames[i]} • ${dt.toLocaleDateString(undefined, {
          month: "short",
          day: "numeric",
        })}`;

        const head = document.createElement("div");
        head.className = "wh";
        head.textContent = label;
        weekHead.appendChild(head);

        const col = document.createElement("div");
        col.className = "week-col";
        col.dataset.date = d;

        const top = document.createElement("div");
        top.className = "wk-date";
        top.textContent = d;
        col.appendChild(top);

        entries
          .filter((entry) => overlapsDay(entry, d))
          .sort((a, b) => (a.start || "").localeCompare(b.start || ""))
          .forEach((entry) => {
            col.appendChild(createWeekDayEntryElement(entry));
          });

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

      bindEditablePills(weekGrid);
    }

    function renderDay(dayStr) {
      if (!dayHead || !dayList) return;

      selectedDay = dayStr;
      dayHead.textContent = `Day View • ${dayStr}`;
      dayList.innerHTML = "";

      const entries = collectEntriesFromDOM()
        .filter((entry) => overlapsDay(entry, dayStr))
        .sort((a, b) => (a.start || "").localeCompare(b.start || ""));

      if (entries.length === 0) {
        const empty = document.createElement("div");
        empty.style.color = "rgba(0,0,0,.55)";
        empty.style.fontWeight = "800";
        empty.textContent = "No entries for this day.";
        dayList.appendChild(empty);
        return;
      }

      entries.forEach((entry) => {
        const row = document.createElement("div");
        row.className = "day-item";

        const left = document.createElement("div");
        left.className = "di-left";

        const title = document.createElement("div");
        title.className = "di-title";
        title.textContent = entry.title;

        const notes = document.createElement("div");
        notes.className = "di-notes";
        notes.textContent = entry.notes || "";

        left.append(title, notes);

        const right = document.createElement("div");
        right.className = "di-time";

        const startTime = timeOnlyFromISO(entry.start);
        const endTime = entry.end ? timeOnlyFromISO(entry.end) : "";
        right.textContent = startTime
          ? endTime
            ? `${startTime}–${endTime}`
            : startTime
          : "";

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

      bindEditablePills(dayList);
    }

    bindEditablePills(document);
    showView("month");
  });
})();
