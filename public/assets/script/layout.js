(() => {
  document.addEventListener("DOMContentLoaded", () => {
    const body = document.body;
    const sidebar = document.querySelector(".sidebar");
    const menuBtn = document.getElementById("menuBtn");
    const overlay = document.getElementById("sidebarOverlay");

    if (!sidebar || !menuBtn || !overlay) return;

    // Inject close button inside sidebar (✕) if not present
    if (!sidebar.querySelector(".sidebar-close")) {
      const closeBtn = document.createElement("button");
      closeBtn.type = "button";
      closeBtn.className = "sidebar-close";
      closeBtn.id = "menuCloseBtn";
      closeBtn.textContent = "✕";
      closeBtn.setAttribute("aria-label", "Close menu");

      // Put close button at very top of sidebar
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
      if (e.key === "Escape" && body.classList.contains("sidebar-open")) closeMenu();
    });

    // If user rotates / resizes to desktop, auto-close overlay
    window.addEventListener("resize", () => {
      if (window.innerWidth > 640) {
        body.classList.remove("sidebar-open");
        overlay.hidden = true;
      }
    });
  });
})();