document.addEventListener("DOMContentLoaded", function () {

  const overlay = document.getElementById("popup-overlay");
  const messageBox = document.getElementById("popup-message");
  const yesBtn = document.getElementById("popup-yes");
  const noBtn = document.getElementById("popup-no");

  let currentForm = null;
  let confirmed = false;

  document.addEventListener("submit", function (e) {

    const form = e.target;

    if (!form.matches("form[data-confirm]")) return;

    if (confirmed) {
      confirmed = false; // allow real submit
      return;
    }

    e.preventDefault();

    currentForm = form;

    messageBox.textContent = form.getAttribute("data-confirm") || "Are you sure?";
    overlay.style.display = "flex";

  });

  yesBtn.addEventListener("click", function () {

    overlay.style.display = "none";

    if (!currentForm) return;

    confirmed = true;
    currentForm.requestSubmit(); // safer than form.submit()
    currentForm = null;

  });

  noBtn.addEventListener("click", function () {

    overlay.style.display = "none";
    currentForm = null;

  });

});