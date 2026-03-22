/**
 * Form Confirmation Modal Module
 * 
 * This module intercepts form submissions and displays a confirmation dialog
 * for forms marked with a data-confirm attribute. Users can confirm or cancel
 * the submission through the modal overlay.
 * 
 * Usage:
 * Add data-confirm="Your confirmation message?" attribute to any form
 * The module will automatically intercept submission and show the modal
 * 
 * Example:
 * <form method="POST" data-confirm="Are you sure you want to delete?">
 *   <button type="submit">Delete</button>
 * </form>
 */

document.addEventListener("DOMContentLoaded", function () {
  // ========== DOM ELEMENT REFERENCES ==========
  // Cache DOM elements for the confirmation modal overlay system
  // These elements are expected to exist in the HTML document
  
  // Main overlay container that darkens the background and displays the modal
  const overlay = document.getElementById("popup-overlay");
  
  // Text element that displays the confirmation message
  const messageBox = document.getElementById("popup-message");
  
  // "Yes" button - user clicks to confirm the action
  const yesBtn = document.getElementById("popup-yes");
  
  // "No" button - user clicks to cancel the action
  const noBtn = document.getElementById("popup-no");

  // ========== STATE VARIABLES ==========
  // Track the current form submission state and pending confirmation
  
  // Reference to the form currently awaiting confirmation
  // Null when no form is pending confirmation
  let currentForm = null;
  
  // Flag indicating whether the user has confirmed the current action
  // When true, the form is allowed to submit on the next attempt
  let confirmed = false;

  // ========== FORM SUBMISSION INTERCEPTOR ==========
  /**
   * Intercepts form submissions and shows confirmation modal
   * 
   * For forms with data-confirm attribute:
   * 1. First submission: prevents default and shows confirmation modal
   * 2. After confirmation: allows the form to submit
   * 
   * Forms without data-confirm attribute: pass through unaffected
   * 
   * Uses event delegation on the document to catch all form submissions
   */
  document.addEventListener("submit", function (e) {
    // Get the form that triggered the submit event
    const form = e.target;

    // Only intercept forms that have the data-confirm attribute
    // Forms without this attribute submit normally
    if (!form.matches("form[data-confirm]")) return;

    // If the user has already confirmed this action, allow submission to proceed
    // This check prevents the confirmation modal from appearing twice
    if (confirmed) {
      // Reset the flag for the next form submission
      confirmed = false;
      // Allow the form.submit() call below to proceed unimpeded
      return;
    }

    // Prevent the default form submission behavior
    // We'll submit the form manually after confirmation
    e.preventDefault();

    // Store a reference to the form for later submission
    // This is needed because the event listener won't have access to the form
    // when the user clicks the "Yes" button
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
