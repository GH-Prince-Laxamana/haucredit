(function () {
  console.log("calendar.js loaded ✅");

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  const toTs = (d) => new Date(d + "T00:00:00").getTime();
  const addDays = (d, n) => {
    const dt = new Date(d + "T00:00:00");
    dt.setDate(dt.getDate() + n);
    const y = dt.getFullYear();
    const m = String(dt.getMonth() + 1).padStart(2, "0");
    const day = String(dt.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
  };
  const isoToParts = (iso) => {
    if (!iso || !iso.includes("T")) return { d: "", t: "" };
    const [d, t] = iso.split("T");
    return { d: d || "", t: t || "" };
  };
  const dateOnlyFromISO = (iso) => (iso ? iso.split("T")[0] : "");
  const timeOnlyFromISO = (iso) => (iso && iso.includes("T") ? iso.split("T")[1] : "");

  document.addEventListener("DOMContentLoaded", () => {
    // ===== DOM
    const modal = $("#calModal");
    const openBtn = $("#openAdd");
    const closeBackdrop = $("#closeAdd");
    const closeBtn = $("#closeAddBtn");
    const cancelBtn = $("#cancelAdd");

    const modalTitle = $("#modalTitle");
    const form = $(".cal-form");
    const formAction = $("#formAction");
    const entryId = $("#entryId");

    const title = $("#title");
    const startDate = $("#start_date");
    const startTime = $("#start_time");
    const endDate = $("#end_date");
    const endTime = $("#end_time");
    const notes = $("#notes");

    const monthStart = document.body.dataset.monthStart;
    const monthEnd = document.body.dataset.monthEnd;

    // tracker
    const tEntries = $("#tEntries");
    const tDays = $("#tDays");
    const tUpcoming = $("#tUpcoming");

    // views
    const tabs = $$(".cal-tab[data-view]");
    const viewMonth = $("#viewMonth");
    const viewWeek = $("#viewWeek");
    const viewDay = $("#viewDay");

    const weekHead = $("#weekHead");
    const weekGrid = $("#weekGrid");
    const dayHead = $("#dayHead");
    const dayList = $("#dayList");

    if (!modal || !openBtn || !form) return;

    let currentView = "month";
    let selectedDay = (() => {
      const now = new Date();
      const yyyy = now.getFullYear();
      const mm = String(now.getMonth() + 1).padStart(2, "0");
      const dd = String(now.getDate()).padStart(2, "0");
      return `${yyyy}-${mm}-${dd}`;
    })();

    // ===== modal open/close
    const open = () => {
      modal.classList.add("show");
      modal.setAttribute("aria-hidden", "false");
      document.body.classList.add("no-scroll");
      setTimeout(() => title && title.focus(), 0);
    };
    const close = () => {
      modal.classList.remove("show");
      modal.setAttribute("aria-hidden", "true");
      document.body.classList.remove("no-scroll");
    };

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

    const setDateRange = (a, b) => {
      startDate.value = a || "";
      endDate.value = b || a || "";
    };

    openBtn.addEventListener("click", (e) => {
      e.preventDefault();
      resetToAddMode();
      setDateRange(selectedDay, selectedDay);
      open();
    });

    closeBackdrop && closeBackdrop.addEventListener("click", close);
    closeBtn && closeBtn.addEventListener("click", close);
    cancelBtn && cancelBtn.addEventListener("click", close);
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && modal.classList.contains("show")) close();
    });

    // ===== Collect unique entries from DOM pills (works after AJAX too)
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
          notes: p.dataset.notes || ""
        });
      });
      return Array.from(map.values());
    }

    function overlapsDay(entry, dayStr) {
      const s = dateOnlyFromISO(entry.start);
      const e = entry.end ? dateOnlyFromISO(entry.end) : s;
      return toTs(dayStr) >= toTs(s) && toTs(dayStr) <= toTs(e);
    }

    function startOfWeek(dayStr) {
      const d = new Date(dayStr + "T00:00:00");
      const dow = d.getDay(); // 0 Sun
      d.setDate(d.getDate() - dow);
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, "0");
      const dd = String(d.getDate()).padStart(2, "0");
      return `${y}-${m}-${dd}`;
    }

    // ===== View switching
    function setActiveTab(view) {
      tabs.forEach((t) => t.classList.toggle("active", t.dataset.view === view));
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

    tabs.forEach((btn) => {
      btn.addEventListener("click", () => showView(btn.dataset.view));
    });

    // ===== Month: click cell + drag-select
    const dragState = { dragging: false, start: "", end: "" };

    const clearRange = () => $$(".cal-cell.range").forEach((c) => c.classList.remove("range"));
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

    $$(".cal-cell[data-date]").forEach((cell) => {
      cell.addEventListener("click", (e) => {
        if (dragState.dragging) return;
        if (e.target.closest(".pill") || e.target.closest("form") || e.target.closest("button")) return;
        selectedDay = cell.dataset.date;
        resetToAddMode();
        setDateRange(selectedDay, selectedDay);
        open();
      });
    });

    const grid = $(".cal-grid");
    if (grid) {
      grid.addEventListener("pointerdown", (e) => {
        const cell = e.target.closest(".cal-cell[data-date]");
        if (!cell) return;
        if (e.target.closest(".pill") || e.target.closest("form") || e.target.closest("button")) return;
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

    // ===== Tracker update
    function updateTracker(stats) {
      if (!stats) return;
      if (tEntries) tEntries.textContent = stats.entries;
      if (tDays) tDays.textContent = stats.entry_days;
      if (tUpcoming) tUpcoming.textContent = stats.upcoming;
    }

    // ===== DOM removal of entry pills
    function removeEntryEverywhere(id) {
      $$('.pill[data-entry-id="' + id + '"]').forEach((p) => p.remove());
    }

    // ===== Render Week
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

        const h = document.createElement("div");
        h.className = "wh";
        h.textContent = label;
        weekHead.appendChild(h);

        const col = document.createElement("div");
        col.className = "week-col";
        col.dataset.date = d;

        const top = document.createElement("div");
        top.className = "wk-date";
        top.textContent = d;
        col.appendChild(top);

        entries
          .filter((e) => overlapsDay(e, d))
          .sort((a, b) => (a.start || "").localeCompare(b.start || ""))
          .forEach((e) => {
            const pill = buildPill(e);
            col.appendChild(pill);
          });

        col.addEventListener("click", (ev) => {
          if (ev.target.closest(".pill") || ev.target.closest("form") || ev.target.closest("button")) return;
          selectedDay = d;
          showView("day");
        });

        weekGrid.appendChild(col);
      }

      bindAll();
    }

    // ===== Render Day
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

        const tt = document.createElement("div");
        tt.className = "di-title";
        tt.textContent = e.title;

        const nn = document.createElement("div");
        nn.className = "di-notes";
        nn.textContent = e.notes || "";

        left.append(tt, nn);

        const right = document.createElement("div");
        right.className = "di-time";
        const st = timeOnlyFromISO(e.start);
        const et = e.end ? timeOnlyFromISO(e.end) : "";
        right.textContent = st ? (et ? `${st}–${et}` : st) : "";

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

    // ===== Build a pill (reusable for Month/Week/Day view rendering)
    function buildPill(e) {
      const pill = document.createElement("div");
      pill.className = "pill";
      pill.dataset.entryId = e.id;
      pill.dataset.title = e.title;
      pill.dataset.start = e.start;
      pill.dataset.end = e.end || "";
      pill.dataset.notes = e.notes || "";

      const t = document.createElement("div");
      t.className = "pill-title";
      t.textContent = e.title;

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

      const actions = document.createElement("div");
      actions.className = "pill-actions";

      const editBtn = document.createElement("button");
      editBtn.type = "button";
      editBtn.className = "pill-btn edit";
      editBtn.title = "Edit";
      editBtn.textContent = "✎";

      const delForm = document.createElement("form");
      delForm.className = "pill-del";
      delForm.method = "post";

      const csrf = document.createElement("input");
      csrf.type = "hidden";
      csrf.name = "csrf_token";
      csrf.value = $('input[name="csrf_token"]')?.value || "";

      const act = document.createElement("input");
      act.type = "hidden";
      act.name = "action";
      act.value = "delete_entry";

      const idInp = document.createElement("input");
      idInp.type = "hidden";
      idInp.name = "entry_id";
      idInp.value = e.id;

      const ajax = document.createElement("input");
      ajax.type = "hidden";
      ajax.name = "ajax";
      ajax.value = "1";

      const delBtn = document.createElement("button");
      delBtn.type = "submit";
      delBtn.className = "pill-btn del";
      delBtn.title = "Delete";
      delBtn.textContent = "🗑";

      delForm.append(csrf, act, idInp, ajax, delBtn);
      actions.append(editBtn, delForm);
      pill.appendChild(actions);

      return pill;
    }

    // ===== Bind Edit/Delete on ALL pills (Month/Week/Day)
    function bindAll() {
      // EDIT
      $$(".pill-btn.edit").forEach((btn) => {
        if (btn.dataset.bound) return;
        btn.dataset.bound = "1";

        btn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();

          const pill = btn.closest(".pill");
          if (!pill) return;

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

          open();
        });
      });

      // DELETE (AJAX)
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
            headers: { "X-Requested-With": "XMLHttpRequest" }
          });

          const data = await res.json();
          if (!res.ok || !data.success) {
            alert(data.error || "Delete failed");
            return;
          }

          removeEntryEverywhere(id);

          // If you are on week/day, re-render from Month DOM (updated)
          if (currentView === "week") renderWeek(selectedDay);
          if (currentView === "day") renderDay(selectedDay);

          updateTracker(data.stats);
        });
      });
    }

    // initial bind
    bindAll();

    // ===== AJAX save (Add/Edit)
    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      const fd = new FormData(form);
      fd.set("ajax", "1");

      const res = await fetch(form.getAttribute("action"), {
        method: "POST",
        body: fd,
        headers: { "X-Requested-With": "XMLHttpRequest" }
      });

      const data = await res.json();
      if (!res.ok || !data.success) {
        alert(data.error || "Save failed");
        return;
      }

      // easiest: refresh month pills by reloading page? NO (you want AJAX)
      // We will insert/update ONLY Month DOM so Week/Day can rebuild from it.

      // remove old entry pills if edit
      if (data.mode === "edit") removeEntryEverywhere(String(data.entry.entry_id));

      // render entry across days in month (Month grid only)
      const entry = data.entry;

      const startD = entry.start_date;
      const endD = entry.end_date;

      let cur = startD;
      while (toTs(cur) <= toTs(endD)) {
        // only if this day is in month grid
        if (toTs(cur) >= toTs(monthStart) && toTs(cur) <= toTs(monthEnd)) {
          const cell = $('.cal-cell[data-date="' + cur + '"]');
          if (cell) {
            // span class similar look
            let spanClass = "";
            if (startD !== endD) {
              if (cur === startD) spanClass = " span-start";
              else if (cur === endD) spanClass = " span-end";
              else spanClass = " span-mid";
            }

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

            // show time only on start day
            if (cur === startD && entry.time_label) {
              const ti = document.createElement("div");
              ti.className = "pill-time";
              ti.textContent = entry.time_label;
              pill.appendChild(ti);
            }

            // actions
            const actions = document.createElement("div");
            actions.className = "pill-actions";

            const editBtn = document.createElement("button");
            editBtn.type = "button";
            editBtn.className = "pill-btn edit";
            editBtn.title = "Edit";
            editBtn.textContent = "✎";

            const delForm = document.createElement("form");
            delForm.className = "pill-del";
            delForm.method = "post";

            const csrf = document.createElement("input");
            csrf.type = "hidden";
            csrf.name = "csrf_token";
            csrf.value = entry.csrf_token;

            const act = document.createElement("input");
            act.type = "hidden";
            act.name = "action";
            act.value = "delete_entry";

            const idInp = document.createElement("input");
            idInp.type = "hidden";
            idInp.name = "entry_id";
            idInp.value = entry.entry_id;

            const ajax = document.createElement("input");
            ajax.type = "hidden";
            ajax.name = "ajax";
            ajax.value = "1";

            const delBtn = document.createElement("button");
            delBtn.type = "submit";
            delBtn.className = "pill-btn del";
            delBtn.title = "Delete";
            delBtn.textContent = "🗑";

            delForm.append(csrf, act, idInp, ajax, delBtn);
            actions.append(editBtn, delForm);
            pill.appendChild(actions);

            cell.appendChild(pill);
          }
        }
        cur = addDays(cur, 1);
      }

      // bind new DOM
      bindAll();

      // re-render week/day if active
      if (currentView === "week") renderWeek(selectedDay);
      if (currentView === "day") renderDay(selectedDay);

      updateTracker(data.stats);
      close();
      form.reset();
    });

    // Default view
    showView("month");
  });
})();