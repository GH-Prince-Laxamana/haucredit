/**
 * User Profile Management Module
 * 
 * This module handles all profile page interactions:
 * - Tab switching between edit profile and change password sections
 * - Password visibility toggles (show/hide password input)
 * - Profile photo upload with image cropping functionality
 * - Photo menu management (upload vs. remove options)
 * - Drag-and-drop file upload to avatar area
 * - Auto-dismissing alert notifications
 * 
 * Key Features:
 * - Image Cropper library integration for square profile photo editing
 * - File drag-and-drop support with visual feedback
 * - Modal dialog for image cropping before upload
 * - Confirmation dialog before removing profile photo
 * - Auto-hiding notification alerts after 4 seconds with animation
 * 
 * Dependencies:
 * - Cropper.js library (for image cropping functionality)
 * - HTML elements with specific IDs and classes (see below)
 * 
 * Expected HTML Structure:
 * - Tab buttons: #editBtn, #passBtn
 * - Tab content: #editTab, #passTab
 * - Password inputs: Various password fields with eye toggle buttons
 * - Photo elements: #photoInput, #cropModal, #cropImage, etc.
 * - Photo menu: #editPhotoBtn, #photoMenu, #uploadChoice, #removeChoice
 * - Avatar: .profile-avatar, .profile-avatar-wrap
 * - Alerts: .alert (for auto-dismiss)
 */

document.addEventListener("DOMContentLoaded", function () {
  /* ========================================
     TAB SWITCHING SECTION
     ======================================== */

  /**
   * Tab switching functionality for profile and password sections
   * Only one tab should be active at a time (radio button pattern)
   * Visual styling determines which tab content and button appear active
   */
  
  // Get references to tab switching buttons
  const editBtn = document.getElementById("editBtn");
  const passBtn = document.getElementById("passBtn");
  
  // Get references to tab content containers
  const editTab = document.getElementById("editTab");
  const passTab = document.getElementById("passTab");

  // Attach event listeners to tab buttons if they exist
  // (Elements may not exist on all pages, so conditional check prevents errors)
  if (editBtn && passBtn) {
    /**
     * Switch to edit profile tab when edit button is clicked
     * 
     * Actions:
     * 1. Add "active" class to edit tab (shows tab content)
     * 2. Remove "active" class from password tab (hides it)
     * 3. Add "active" class to edit button (highlights button)
     * 4. Remove "active" class from password button (unhighlights it)
     */
    editBtn.addEventListener("click", () => {
      // Show edit tab content and highlight edit button
      editTab.classList.add("active");
      passTab.classList.remove("active");
      editBtn.classList.add("active");
      passBtn.classList.remove("active");
    });

    /**
     * Switch to change password tab when password button is clicked
     * 
     * Actions:
     * 1. Add "active" class to password tab (shows tab content)
     * 2. Remove "active" class from edit tab (hides it)
     * 3. Add "active" class to password button (highlights button)
     * 4. Remove "active" class from edit button (unhighlights it)
     */
    passBtn.addEventListener("click", () => {
      // Show password tab content and highlight password button
      passTab.classList.add("active");
      editTab.classList.remove("active");
      passBtn.classList.add("active");
      editBtn.classList.remove("active");
    });
  }

  /* ========================================
     PASSWORD VISIBILITY TOGGLE SECTION
     ======================================== */

  /**
   * Password visibility toggle functionality
   * 
   * Implementation:
   * - Each password input has an associated eye button
   * - Eye buttons store the target input ID in data-toggle attribute
   * - Clicking eye button toggles input type between "password" and "text"
   * - CSS can style button differently based on input type
   * 
   * Accessibility:
   * - Users can view password content if they choose
   * - Helpful for catching typos during password entry
   * - Toggle button typically shows eye icon (open/closed)
   */
  
  // Attach click listeners to all password visibility toggle buttons
  document.querySelectorAll(".eye-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      // Get the target password input ID from button's data-toggle attribute
      // Example HTML: <button class="eye-btn" data-toggle="password-field">👁️</button>
      const id = btn.dataset.toggle;
      const input = document.getElementById(id);

      // Toggle password input visibility by switching input type
      // - type="password": Hidden dots/bullets (default)
      // - type="text": Visible plain text characters
      // This simple toggle allows users to see what they typed
      if (input) input.type = input.type === "password" ? "text" : "password";
    });
  });

  /* ========================================
     IMAGE CROPPER SETUP SECTION
     ======================================== */

  /**
   * Initialize cropper library client variable
   * Stores the active Cropper instance created from Cropper.js library
   * Set to null/undefined when no crop session is active
   * Destroyed and recreated for each new image selection
   */
  let cropper;
  
  // Get references to photo upload and cropper UI elements
  const photoInput = document.getElementById("photoInput");  // Hidden file input
  const cropModal = document.getElementById("cropModal");  // Modal dialog containing cropper
  const cropImage = document.getElementById("cropImage");  // Image element inside modal
  const cropSave = document.getElementById("cropSave");  // Save/confirm button
  const cropCancel = document.getElementById("cropCancel");  // Cancel button
  const cropClose = document.getElementById("cropClose");  // Close (X) button
  const avatar = document.querySelector(".profile-avatar");  // Avatar image element
  const photoSubmit = document.getElementById("photoSubmit");  // Form submit button for photo upload

  /* ========================================
     PHOTO MENU MANAGEMENT SECTION
     ======================================== */

  /**
   * Photo menu provides options for managing profile photo:
   * - Upload: Choose new photo from file system
   * - Remove: Delete current photo with confirmation
   * 
   * Menu is hidden by default and shown/hidden on demand
   * Clicking outside menu closes it (standard menu pattern)
   */
  
  // Get references to photo menu UI elements
  const editBtnPhoto = document.getElementById("editPhotoBtn");  // Button to open photo menu
  const photoMenu = document.getElementById("photoMenu");  // Menu container
  const uploadChoice = document.getElementById("uploadChoice");  // Upload option
  const removeChoice = document.getElementById("removeChoice");  // Remove option

  // Photo menu toggle: open/close on edit button click
  if (editBtnPhoto && photoMenu) {
    /**
     * Toggle photo menu visibility when edit photo button is clicked
     * 
     * Logic:
     * - If menu is visible (display: "block"), hide it
     * - If menu is hidden (display: "none"), show it
     * 
     * Uses ternary operator for concise toggle:
     * photoMenu.style.display === "block" ? "none" : "block"
     */
    editBtnPhoto.addEventListener("click", () => {
      // Toggle menu visibility using display CSS property
      photoMenu.style.display =
        photoMenu.style.display === "block" ? "none" : "block";
    });

    /**
     * Auto-hide photo menu when user clicks outside of it
     * 
     * Pattern:
     * - Detects clicks anywhere on document
     * - Checks if click target is inside edit button or menu
     * - If outside both, hides the menu
     * 
     * This prevents menu from staying open after user clicks elsewhere
     * Common UX pattern for dropdown menus
     * 
     * Implementation Details:
     * - e.target.contains(): Returns true if element contains click target
     * - Negation operator (!): Inverts the contains check
     * - Condition: click is NOT on button AND click is NOT on menu
     */
    document.addEventListener("click", (e) => {
      // Hide menu if click is outside both the button and menu
      if (!editBtnPhoto.contains(e.target) && !photoMenu.contains(e.target)) {
        photoMenu.style.display = "none";
      }
    });
  }

  /**
   * Handle upload photo option click
   * 
   * Process:
   * 1. Click upload option in menu
   * 2. Trigger the hidden file input to open file picker
   * 3. Hide the menu after initiating selection
   * 4. User selects file → photoInput change event fires
   * 5. Change handler initializes cropper
   */
  if (uploadChoice) {
    uploadChoice.addEventListener("click", () => {
      // Trigger the file input (opens system file picker)
      // Hidden file input has accept="image/*" to filter for image files
      photoInput.click();
      
      // Hide menu immediately after user has started file selection
      photoMenu.style.display = "none";
    });
  }

  /**
   * Handle remove photo option click
   * 
   * Process:
   * 1. Click remove option in menu
   * 2. Hide menu
   * 3. Show confirmation dialog to prevent accidental deletion
   * 4. If user confirms, create form and submit to delete photo
   * 
   * Implementation Pattern:
   * - Creates a hidden form with remove_photo parameter
   * - Appends form to body
   * - Submits form to trigger PHP photo deletion handler
   * - This approach works even without JavaScript form references
   */
  if (removeChoice) {
    removeChoice.addEventListener("click", () => {
      // Hide menu immediately
      photoMenu.style.display = "none";

      // Ask user for confirmation before removing photo
      // Prevents accidental deletion via double-click or muscle memory
      if (confirm("Are you sure you want to remove your profile photo?")) {
        // Create a hidden form to submit photo removal request
        // Using form POST ensures proper CSRF token handling (if backend checks it)
        const form = document.createElement("form");
        form.method = "POST";
        form.style.display = "none";  // Hide form from user view

        // Create input field with removal flag
        const input = document.createElement("input");
        input.name = "remove_photo";  // Backend looks for this parameter
        input.value = "1";  // Flag value indicating removal request

        // Add input to form, then form to page, then submit
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();  // Submit form to trigger backend photo removal
      }
    });
  }

  /* ========================================
     IMAGE CROPPER LOGIC SECTION
     ======================================== */

  /**
   * ImageCropper initialization and management
   * 
   * Cropper.js is a library that provides image cropping UI
   * Used to ensure profile photos are square and properly framed
   * 
   * Process Flow:
   * 1. User selects file → photoInput change event
   * 2. Read file as data URL using FileReader
   * 3. Display crop modal with Cropper UI
   * 4. User adjusts crop area in modal
   * 5. User clicks save → crop canvas exported as blob
   * 6. Blob converted to File object and set on photoInput
   * 7. Avatar preview updated immediately
   * 8. Form submitted to upload photo
   */

  /**
   * Handle photo file input change event
   * 
   * Triggered by:
   * - User selecting file from file picker (upload option click)
   * - User dropping file on drag-drop area
   * 
   * Process:
   * 1. Read the selected file using FileReader
   * 2. Once loaded, set image source to data URL
   * 3. Initialize Cropper.js on the image
   * 4. Show the cropper modal to user
   */
  if (photoInput) {
    photoInput.addEventListener("change", function () {
      // Check that files exist and at least one file is selected
      if (this.files && this.files[0]) {
        // Create FileReader to convert file to data URL
        const reader = new FileReader();

        /**
         * FileReader onload handler
         * Fires when file has been read into memory
         * 
         * e.target.result contains the data URL
         * Format: "data:image/jpeg;base64,/9j/4AAQSkZJRg..."
         */
        reader.onload = function (e) {
          // Show the crop modal dialog to user
          cropModal.classList.add("active");
          
          // Set the image source to the loaded file data
          cropImage.src = e.target.result;

          // Destroy existing cropper if one is already running
          // This prevents memory leaks and cropper conflicts
          // Happening when user selects multiple files in sequence
          if (cropper) cropper.destroy();

          /**
           * Initialize new Cropper instance with options
           * 
           * Cropper.js configuration:
           * - aspectRatio: 1 (locks aspect to square, prevents distortion)
           * - viewMode: 1 (shows entire image, not cropped outside canvas)
           * - autoCropArea: 1 (auto-size crop area to 100% of image)
           * 
           * These settings ensure users crop to a square profile photo
           */
          cropper = new Cropper(cropImage, {
            aspectRatio: 1,  // Square photo requirement
            viewMode: 1,  // Show entire image within container
            autoCropArea: 1,  // Start with full image selected
          });
        };

        /**
         * Read file as data URL
         * Converts the File object to a base64-encoded string
         * Allows image to be displayed in browser without server
         */
        reader.readAsDataURL(this.files[0]);
      }
    });
  }

  /**
   * Handle crop save button click
   * 
   * Process:
   * 1. Get the cropped image as canvas element
   * 2. Export canvas as JPEG blob (compressed image binary)
   * 3. Convert blob to File object with image/jpeg type
   * 4. Add File to photoInput using DataTransfer API
   * 5. Update avatar preview immediately for user feedback
   * 6. Hide crop modal
   * 7. Auto-submit form after brief delay (allows UI update first)
   * 
   * Rationale for DataTransfer API:
   * - Normal way to set files on input is read-only (.files is readonly)
   * - DataTransfer provides the only standard way to set file input files
   * - Simulates user selecting a file through file picker
   */
  if (cropSave) {
    cropSave.addEventListener("click", () => {
      /**
       * Get cropped canvas with specific dimensions
       * 
       * getCroppedCanvas returns a canvas element with cropped image
       * Options specify output size: 400x400 pixels for profile photo
       * This standardizes all profile photos to same dimensions
       */
      const canvas = cropper.getCroppedCanvas({ width: 400, height: 400 });
      
      /**
       * Convert canvas to blob (binary image data)
       * 
       * toBlob(callback) is async - provides blob when compression complete
       * Blob can then be converted to File for form submission
       */
      canvas.toBlob(function (blob) {
        /**
         * Create File object from blob
         * 
         * File constructor signature:
         * new File([blob], filename, {type: mimeType})
         * 
         * Parameters:
         * - [blob]: Array of data sources (here, single blob)
         * - "profile.jpg": Filename hint for backend
         * - {type: "image/jpeg"}: MIME type for form submission
         */
        const file = new File([blob], "profile.jpg", { type: "image/jpeg" });
        
        /**
         * Use DataTransfer API to set file on input
         * 
         * Why DataTransfer:
         * - input.files is read-only property
         * - Cannot directly set: input.files = someFile
         * - DataTransfer is the standard workaround
         */
        const dt = new DataTransfer();
        dt.items.add(file);  // Add file to DataTransfer
        photoInput.files = dt.files;  // Assign to input.files

        /**
         * Update avatar preview immediately
         * 
         * Show user the cropped image before upload completes
         * Provides instant visual feedback
         * Uses canvas.toDataURL() to get preview image
         */
        avatar.src = canvas.toDataURL();
        
        // Hide the crop modal
        cropModal.classList.remove("active");

        /**
         * Submit form after brief delay
         * 
         * Rationale for setTimeout:
         * - Allows browser to render the avatar change first
         * - Ensures smooth visual feedback before page reload
         * - Delay of 300ms is imperceptible to user but enough for paint
         * - After submit, page typically reloads with new photo
         */
        setTimeout(() => photoSubmit.click(), 300);
      });
    });
  }

  /**
   * Handle crop cancel button click
   * 
   * Process:
   * 1. Hide the crop modal dialog
   * 2. Reset file input (clear selected file)
   * 3. Destroy cropper instance
   * 
   * Rationale:
   * - Clearing file allows user to re-select same file again
   * - Destroying cropper frees memory and resources
   * - Without destroy, previous cropper instance would persist
   */
  if (cropCancel) {
    cropCancel.addEventListener("click", () => {
      // Hide the crop modal
      cropModal.classList.remove("active");
      
      // Reset the file input to empty
      // This clears the selected file and allows re-selection
      // Important: Resetting prevents "change event won't fire for same file" issue
      photoInput.value = '';
      
      // Clean up the cropper instance to free resources
      // Without this, multiple crops could cause memory leaks
      if (cropper) {
        cropper.destroy();  // Destroy Cropper.js instance
        cropper = null;  // Clear reference
      }
    });
  }

  /**
   * Handle crop modal close button (X button) click
   * 
   * Simpler than cancel: just hide modal, don't clean up
   * User might still be viewing the crop before deciding
   */
  if (cropClose) {
    cropClose.addEventListener("click", () => {
      // Hide the crop modal
      cropModal.classList.remove("active");
    });
  }

  /* ========================================
     DRAG & DROP UPLOAD SECTION
     ======================================== */

  /**
   * Drag and drop file upload functionality
   * 
   * Allows users to drag image files onto avatar area to upload
   * Visual feedback shows when zone is "hot" (file over zone)
   * 
   * Process:
   * 1. User drags file over avatar area
   * 2. dragover handler prevents default (allows drop) and adds "dragging" class
   * 3. Visual feedback shows via CSS styling on dragging class
   * 4. User drops file → drop handler extracts files from dataTransfer
   * 5. Trigger photoInput change event to start cropping process
   * 6. Same cropping flow runs as if user selected from file picker
   * 
   * Implementation Details:
   * - e.preventDefault() on dragover/drop allows drop behavior
   * - event.dataTransfer.files contains dropped files
   * - CSS class changes provide visual feedback during drag
   */
  
  // Get avatar container element for drag/drop listeners
  const avatarWrap = document.querySelector(".profile-avatar-wrap");

  if (avatarWrap) {
    /**
     * dragover handler: Prepare zone for drop
     * 
     * Purpose:
     * - Prevent browser default behavior (which would navigate to file)
     * - Add visual "hot zone" styling to show drop is possible
     * - Called repeatedly while file is dragged over zone
     */
    avatarWrap.addEventListener("dragover", (e) => {
      // Prevent default browser behavior
      // Without this, browser would navigate to the dragged file
      e.preventDefault();
      
      // Add visual feedback class
      // CSS will highlight zone to show it's a drop target
      avatarWrap.classList.add("dragging");
    });

    /**
     * dragleave handler: Remove drop zone styling
     * 
     * Fires when file is dragged away from zone
     * Removes the "hot zone" visual styling
     */
    avatarWrap.addEventListener("dragleave", () => {
      // Remove visual feedback class
      avatarWrap.classList.remove("dragging");
    });

    /**
     * drop handler: Process dropped file
     * 
     * Process:
     * 1. Prevent default (prevents file navigation)
     * 2. Remove dragging class (reset styling)
     * 3. Extract files from drop event
     * 4. Set files on photoInput (as if user chose file)
     * 5. Dispatch change event to trigger cropper
     */
    avatarWrap.addEventListener("drop", (e) => {
      // Prevent browser default file open behavior
      e.preventDefault();
      
      // Remove drop zone highlighting
      avatarWrap.classList.remove("dragging");
      
      // Get files from drag & drop event
      // e.dataTransfer.files is a FileList like normal file input
      photoInput.files = e.dataTransfer.files;
      
      /**
       * Manually dispatch change event
       * 
       * Rationale:
       * - photoInput.files is set, but change event doesn't fire automatically
       * - change event listener handles cropper initialization
       * - Manually dispatch ensures cropper runs for drag-drop files
       * - Same behavior as user selecting file from picker
       */
      photoInput.dispatchEvent(new Event("change"));
    });
  }

  /* ========================================
     ALERT AUTO-DISMISS SECTION
     ======================================== */

  /**
   * Auto-dismissing alert notifications
   * 
   * Alerts are shown after form submission (photo removed, profile updated, etc.)
   * They automatically disappear after 4 seconds with fade-out animation
   * Improves UX by not leaving persistent notifications on screen
   * 
   * Process:
   * 1. Alert is initially visible (added to HTML by server)
   * 2. setTimeout waits 4000 milliseconds (4 seconds)
   * 3. Fade-out animation applied via CSS properties
   * 4. Second setTimeout waits for animation to complete (300ms)
   * 5. Alert element removed from DOM
   * 
   * Animation Details:
   * - opacity: "0" makes alert transparent
   * - transform: "translateY(-6px)" moves alert up slightly
   * - These changes trigger CSS transition animation
   * - 300ms delay allows animation to finish before removal
   */
  
  // Get reference to the alert element
  const alertBox = document.querySelector(".alert");

  if (alertBox) {
    /**
     * Auto-dismiss alert after 4 seconds with fade-out animation
     * 
     * Timeline:
     * - 0ms: Alert is visible (initial state)
     * - 4000ms: Start fade-out animation
     * - 4300ms: Remove alert from DOM
     * - Total visible: ~4 seconds
     */
    setTimeout(() => {
      /**
       * Apply fade-out animation
       * 
       * These CSS-in-JS changes trigger transition:
       * - browser detects opacity change
       * - CSS transition animates opacity over time
       * - Same for transform (slide up)
       */
      alertBox.style.opacity = "0";  // Fade to transparent
      alertBox.style.transform = "translateY(-6px)";  // Slide up slightly

      /**
       * Remove alert after animation completes
       * 
       * Waits 300ms for CSS animation to finish
       * Then removes element from DOM entirely
       * Prevents element from taking space and improves performance
       */
      setTimeout(() => alertBox.remove(), 300);
    }, 4000);  // Wait 4 seconds before starting animation
  }
});
