document.addEventListener("DOMContentLoaded", function () {
  // ===== DOM ELEMENT REFERENCES =====
  // Get references to popup overlay and its components
  const overlay = document.getElementById("popup-overlay");
  const messageBox = document.getElementById("popup-message");
  const yesBtn = document.getElementById("popup-yes");
  const noBtn = document.getElementById("popup-no");

  // ===== STATE VARIABLES =====
  // Track the currently pending form and confirmation state
  let currentForm = null;
  let confirmed = false;

  // ===== FORM SUBMISSION INTERCEPTOR =====
  // Listen for form submissions and intercept those requiring confirmation
  document.addEventListener("submit", function (e) {
    const form = e.target;

    // Only intercept forms with data-confirm attribute
    if (!form.matches("form[data-confirm]")) return;

    // If already confirmed, allow the submission to proceed
    if (confirmed) {
      confirmed = false; // Reset for future submissions
      return;
    }

    // Prevent default submission and show confirmation popup
    e.preventDefault();

    // Store reference to the form being confirmed
    currentForm = form;

    // Set popup message from data-confirm attribute or default
    messageBox.textContent =
      form.getAttribute("data-confirm") || "Are you sure?";

    // Display the confirmation overlay
    overlay.style.display = "flex";
  });

  // ===== CONFIRMATION HANDLER =====
  // Handle user clicking "Yes" to confirm the action
  yesBtn.addEventListener("click", function () {
    console.log("Submitting:", currentForm);

    // Hide the confirmation overlay
    overlay.style.display = "none";

    // If no form is pending, do nothing
    if (!currentForm) return;

    // Mark as confirmed and submit the form
    confirmed = true;
    currentForm.requestSubmit();

    // Clear the form reference
    currentForm = null;
  });

  // ===== CANCELLATION HANDLER =====
  // Handle user clicking "No" to cancel the action
  noBtn.addEventListener("click", function () {
    // Hide the confirmation overlay
    overlay.style.display = "none";

    // Clear the pending form reference
    currentForm = null;
  });
});
