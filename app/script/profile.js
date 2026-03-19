document.addEventListener("DOMContentLoaded", function () {
  /* ================= TAB SWITCHING ================= */
  // Get references to tab buttons and tab content elements
  const editBtn = document.getElementById("editBtn");
  const passBtn = document.getElementById("passBtn");
  const editTab = document.getElementById("editTab");
  const passTab = document.getElementById("passTab");

  // Attach event listeners to tab buttons if they exist
  if (editBtn && passBtn) {
    // Switch to edit profile tab when edit button is clicked
    editBtn.addEventListener("click", () => {
      editTab.classList.add("active");
      passTab.classList.remove("active");
      editBtn.classList.add("active");
      passBtn.classList.remove("active");
    });

    // Switch to change password tab when password button is clicked
    passBtn.addEventListener("click", () => {
      passTab.classList.add("active");
      editTab.classList.remove("active");
      passBtn.classList.add("active");
      editBtn.classList.remove("active");
    });
  }

  /* ================= PASSWORD VISIBILITY TOGGLE ================= */
  // Attach click listeners to all password toggle buttons (eye icons)
  document.querySelectorAll(".eye-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      // Get the input ID from the button's data-toggle attribute
      const id = btn.dataset.toggle;
      const input = document.getElementById(id);

      // Toggle the input type between password and text to show/hide password
      if (input) input.type = input.type === "password" ? "text" : "password";
    });
  });

  /* ================= IMAGE CROPPER SETUP ================= */
  // Initialize cropper variable and get references to cropper elements
  let cropper;
  const photoInput = document.getElementById("photoInput");
  const cropModal = document.getElementById("cropModal");
  const cropImage = document.getElementById("cropImage");
  const cropSave = document.getElementById("cropSave");
  const cropCancel = document.getElementById("cropCancel");
  const cropClose = document.getElementById("cropClose");
  const avatar = document.querySelector(".profile-avatar");
  const photoSubmit = document.getElementById("photoSubmit");

  /* ================= PHOTO MENU MANAGEMENT ================= */
  // Get references to photo menu elements
  const editBtnPhoto = document.getElementById("editPhotoBtn");
  const photoMenu = document.getElementById("photoMenu");
  const uploadChoice = document.getElementById("uploadChoice");
  const removeChoice = document.getElementById("removeChoice");

  // Toggle photo menu visibility when edit photo button is clicked
  if (editBtnPhoto && photoMenu) {
    editBtnPhoto.addEventListener("click", () => {
      photoMenu.style.display =
        photoMenu.style.display === "block" ? "none" : "block";
    });

    // Hide photo menu when clicking outside of it
    document.addEventListener("click", (e) => {
      if (!editBtnPhoto.contains(e.target) && !photoMenu.contains(e.target)) {
        photoMenu.style.display = "none";
      }
    });
  }

  // Handle upload photo choice click
  if (uploadChoice) {
    uploadChoice.addEventListener("click", () => {
      photoInput.click(); // Trigger file input click
      photoMenu.style.display = "none"; // Hide menu after selection
    });
  }

  // Handle remove photo choice click with confirmation
  if (removeChoice) {
    removeChoice.addEventListener("click", () => {
      photoMenu.style.display = "none"; // Hide menu

      // Confirm removal before proceeding
      if (confirm("Are you sure you want to remove your profile photo?")) {
        // Create and submit a hidden form to remove the photo
        const form = document.createElement("form");
        form.method = "POST";
        form.style.display = "none";

        const input = document.createElement("input");
        input.name = "remove_photo";
        input.value = "1";

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
      }
    });
  }

  /* ================= CROPPER LOGIC ================= */
  // Handle file input change to initialize cropper
  if (photoInput) {
    photoInput.addEventListener("change", function () {
      if (this.files && this.files[0]) {
        const reader = new FileReader();

        // Load image into cropper when file is read
        reader.onload = function (e) {
          cropModal.classList.add("active"); // Show crop modal
          cropImage.src = e.target.result; // Set image source

          // Destroy existing cropper instance if present
          if (cropper) cropper.destroy();

          // Initialize new cropper with square aspect ratio
          cropper = new Cropper(cropImage, {
            aspectRatio: 1,
            viewMode: 1,
            autoCropArea: 1,
          });
        };

        reader.readAsDataURL(this.files[0]); // Read selected file
      }
    });
  }

  // Handle crop save button click
  if (cropSave) {
    cropSave.addEventListener("click", () => {
      // Get cropped canvas and convert to blob
      const canvas = cropper.getCroppedCanvas({ width: 400, height: 400 });
      canvas.toBlob(function (blob) {
        // Create file from blob and update input
        const file = new File([blob], "profile.jpg", { type: "image/jpeg" });
        const dt = new DataTransfer();
        dt.items.add(file);
        photoInput.files = dt.files;

        // Update avatar preview and hide modal
        avatar.src = canvas.toDataURL();
        cropModal.classList.remove("active");

        // Submit form after short delay to allow UI update
        setTimeout(() => photoSubmit.click(), 300);
      });
    });
  }

  // Handle crop cancel button click
  if (cropCancel) {
    cropCancel.addEventListener("click", () => {
      cropModal.classList.remove("active");
      // Reset the file input to clear any selected file and allow re-uploading
      photoInput.value = '';
      // Optional: Ensure cropper is fully cleaned up (though it should be from change event)
      if (cropper) {
        cropper.destroy();
        cropper = null;
      }
    });
  }

  // Handle crop modal close button click
  if (cropClose) {
    cropClose.addEventListener("click", () =>
      cropModal.classList.remove("active"),
    );
  }

  /* ================= DRAG & DROP UPLOAD ================= */
  // Get reference to avatar wrapper for drag and drop
  const avatarWrap = document.querySelector(".profile-avatar-wrap");

  if (avatarWrap) {
    // Prevent default behavior and add dragging class on dragover
    avatarWrap.addEventListener("dragover", (e) => {
      e.preventDefault();
      avatarWrap.classList.add("dragging");
    });

    // Remove dragging class on dragleave
    avatarWrap.addEventListener("dragleave", () => {
      avatarWrap.classList.remove("dragging");
    });

    // Handle file drop by setting files and triggering change event
    avatarWrap.addEventListener("drop", (e) => {
      e.preventDefault();
      avatarWrap.classList.remove("dragging");
      photoInput.files = e.dataTransfer.files;
      photoInput.dispatchEvent(new Event("change"));
    });
  }

  /* ================= ALERT AUTO DISMISS ================= */
  // Get reference to alert box for auto-dismissal
  const alertBox = document.querySelector(".alert");

  if (alertBox) {
    // Auto-dismiss alert after 4 seconds with fade-out animation
    setTimeout(() => {
      alertBox.style.opacity = "0";
      alertBox.style.transform = "translateY(-6px)";

      // Remove alert from DOM after animation completes
      setTimeout(() => alertBox.remove(), 300);
    }, 4000);
  }
});
