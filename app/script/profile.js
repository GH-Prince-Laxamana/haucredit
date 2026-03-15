document.addEventListener("DOMContentLoaded", function () {
  /* ================= TAB SWITCH ================= */
  const editBtn = document.getElementById("editBtn");
  const passBtn = document.getElementById("passBtn");
  const editTab = document.getElementById("editTab");
  const passTab = document.getElementById("passTab");

  if (editBtn && passBtn) {
    editBtn.addEventListener("click", () => {
      editTab.classList.add("active");
      passTab.classList.remove("active");
      editBtn.classList.add("active");
      passBtn.classList.remove("active");
    });

    passBtn.addEventListener("click", () => {
      passTab.classList.add("active");
      editTab.classList.remove("active");
      passBtn.classList.add("active");
      editBtn.classList.remove("active");
    });
  }

  /* ================= PASSWORD TOGGLE ================= */
  document.querySelectorAll(".eye-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      const id = btn.dataset.toggle;
      const input = document.getElementById(id);
      if (input) input.type = input.type === "password" ? "text" : "password";
    });
  });

  /* ================= IMAGE CROPPER ================= */
  let cropper;
  const photoInput = document.getElementById("photoInput");
  const cropModal = document.getElementById("cropModal");
  const cropImage = document.getElementById("cropImage");
  const cropSave = document.getElementById("cropSave");
  const cropCancel = document.getElementById("cropCancel");
  const cropClose = document.getElementById("cropClose");
  const avatar = document.querySelector(".profile-avatar");
  const photoSubmit = document.getElementById("photoSubmit");

  /* ================= PHOTO MENU ================= */
  const editBtnPhoto = document.getElementById("editPhotoBtn");
  const photoMenu = document.getElementById("photoMenu");
  const uploadChoice = document.getElementById("uploadChoice");
  const removeChoice = document.getElementById("removeChoice");

  if (editBtnPhoto && photoMenu) {
    editBtnPhoto.addEventListener("click", () => {
      photoMenu.style.display =
        photoMenu.style.display === "block" ? "none" : "block";
    });

    // Hide menu if clicked outside
    document.addEventListener("click", (e) => {
      if (!editBtnPhoto.contains(e.target) && !photoMenu.contains(e.target)) {
        photoMenu.style.display = "none";
      }
    });
  }

  // Upload Photo
  if (uploadChoice) {
    uploadChoice.addEventListener("click", () => {
      photoInput.click();
      photoMenu.style.display = "none";
    });
  }

  // Remove Photo
  if (removeChoice) {
    removeChoice.addEventListener("click", () => {
      photoMenu.style.display = "none";
      if (confirm("Are you sure you want to remove your profile photo?")) {
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

  // Cropper logic
  if (photoInput) {
    photoInput.addEventListener("change", function () {
      if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
          cropModal.classList.add("active");
          cropImage.src = e.target.result;
          if (cropper) cropper.destroy();
          cropper = new Cropper(cropImage, {
            aspectRatio: 1,
            viewMode: 1,
            autoCropArea: 1,
          });
        };
        reader.readAsDataURL(this.files[0]);
      }
    });
  }

  if (cropSave) {
    cropSave.addEventListener("click", () => {
      const canvas = cropper.getCroppedCanvas({ width: 400, height: 400 });
      canvas.toBlob(function (blob) {
        const file = new File([blob], "profile.jpg", { type: "image/jpeg" });
        const dt = new DataTransfer();
        dt.items.add(file);
        photoInput.files = dt.files;
        avatar.src = canvas.toDataURL();
        cropModal.classList.remove("active");
        setTimeout(() => photoSubmit.click(), 300);
      });
    });
  }

  if (cropCancel) {
    cropCancel.addEventListener("click", () =>
      cropModal.classList.remove("active"),
    );
  }

  if (cropClose) {
    cropClose.addEventListener("click", () =>
      cropModal.classList.remove("active"),
    );
  }
  /* ================= DRAG & DROP UPLOAD ================= */
  const avatarWrap = document.querySelector(".profile-avatar-wrap");
  if (avatarWrap) {
    avatarWrap.addEventListener("dragover", (e) => {
      e.preventDefault();
      avatarWrap.classList.add("dragging");
    });
    avatarWrap.addEventListener("dragleave", () => {
      avatarWrap.classList.remove("dragging");
    });
    avatarWrap.addEventListener("drop", (e) => {
      e.preventDefault();
      avatarWrap.classList.remove("dragging");
      photoInput.files = e.dataTransfer.files;
      photoInput.dispatchEvent(new Event("change"));
    });
  }
  /* ================= ALERT AUTO DISMISS ================= */
  const alertBox = document.querySelector(".alert");
  if (alertBox) {
    setTimeout(() => {
      alertBox.style.opacity = "0";
      alertBox.style.transform = "translateY(-6px)";
      setTimeout(() => alertBox.remove(), 300);
    }, 4000);
  }
});
