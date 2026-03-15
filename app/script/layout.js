(() => {
  document.addEventListener("DOMContentLoaded", () => {
    const body = document.body;
    const sidebar = document.querySelector(".sidebar");
    const menuBtn = document.getElementById("menuBtn");
    const overlay = document.getElementById("sidebarOverlay");

    if (!sidebar || !menuBtn || !overlay) return;

    /* ===============================
       SIDEBAR
    =============================== */

    if (!sidebar.querySelector(".sidebar-close")) {
      const closeBtn = document.createElement("button");
      closeBtn.type = "button";
      closeBtn.className = "sidebar-close";
      closeBtn.id = "menuCloseBtn";
      closeBtn.textContent = "✕";
      closeBtn.setAttribute("aria-label", "Close menu");

      sidebar.insertBefore(closeBtn, sidebar.firstChild);

      closeBtn.addEventListener("click", () => closeMenu());
    }

    function openMenu() {
      body.classList.add("sidebar-open");
      overlay.hidden = false;
      overlay.classList.add("active");
      body.style.overflow = "hidden";
    }

    function closeMenu() {
      body.classList.remove("sidebar-open");
      overlay.classList.remove("active");
      overlay.hidden = true;
      body.style.overflow = "";
    }

    menuBtn.addEventListener("click", () => {
      if (body.classList.contains("sidebar-open")) closeMenu();
      else openMenu();
    });

    overlay.addEventListener("click", closeMenu);

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && body.classList.contains("sidebar-open"))
        closeMenu();
    });

    window.addEventListener("resize", () => {
      if (window.innerWidth > 640) {
        body.classList.remove("sidebar-open");
        overlay.hidden = true;
      }
    });

    /* ===============================
       CREATE EVENT BUTTON
    =============================== */

    const createBtn = document.querySelector(".home-primary-btn");

    if (createBtn) {
      createBtn.addEventListener("click", () => {
        window.location.href = "create_event.php";
      });
    }

    /* ===============================
       NOTIFICATION DROPDOWN
    =============================== */

    const notifBtn = document.getElementById("notifBtn");
    const notifDropdown = document.getElementById("notifDropdown");

    if (notifBtn && notifDropdown) {
      notifBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        notifDropdown.classList.toggle("show");
      });

      document.addEventListener("click", () => {
        notifDropdown.classList.remove("show");
      });
    }

    /* ===============================
       CLICKABLE EVENT ROW
    =============================== */

    const eventRows = document.querySelectorAll(".home-event-row");

    eventRows.forEach((row) => {
      row.addEventListener("click", (e) => {
        if (e.target.closest(".home-btn-view")) return;

        const viewBtn = row.querySelector(".home-btn-view");

        if (viewBtn) {
          window.location.href = viewBtn.href;
        }
      });
    });
  });
})();


