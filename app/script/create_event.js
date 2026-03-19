// ===== TOGGLE CONDITIONAL FORM BLOCKS =====
// Function to show/hide form sections based on user selections and set required attributes
function toggleBlocks() {
  // Get references to conditional form blocks
  const seriesBlock = document.getElementById("series-block");
  const offcampusBlock = document.getElementById("offcampus-block");
  const visitorsBlock = document.getElementById("visitors-block");

  // Get selected background value from radio buttons
  const background = document.querySelector(
    'input[name="background"]:checked',
  )?.value;

  // Get selected activity type value from radio buttons
  const activity = document.querySelector(
    'input[name="activity_type"]:checked',
  )?.value;

  // ===== SERIES BLOCK LOGIC =====
  // Show series block if background is "Participation", otherwise hide
  if (background === "Participation") {
    seriesBlock.style.display = "flex";
    // Make all inputs in series block required
    seriesBlock.querySelectorAll("input").forEach((i) => (i.required = true));
  } else {
    seriesBlock.style.display = "none";
    // Remove required attribute from series block inputs
    seriesBlock.querySelectorAll("input").forEach((i) => (i.required = false));
  }

  // ===== OFF-CAMPUS BLOCK LOGIC =====
  // Show off-campus block if activity type includes "off-campus", otherwise hide
  if (activity && activity.toLowerCase().includes("off-campus")) {
    offcampusBlock.style.display = "flex";
    // Make all inputs in off-campus block required
    offcampusBlock
      .querySelectorAll("input")
      .forEach((i) => (i.required = true));
  } else {
    offcampusBlock.style.display = "none";
    // Remove required attribute from off-campus block inputs
    offcampusBlock
      .querySelectorAll("input")
      .forEach((i) => (i.required = false));
  }

  // ===== VISITORS BLOCK LOGIC =====
  // Show visitors block if activity type includes "on-campus", otherwise hide
  if (activity && activity.toLowerCase().includes("on-campus")) {
    visitorsBlock.style.display = "flex";
    // Make visitor radio buttons required
    visitorsBlock
      .querySelectorAll('input[name="has_visitors"]')
      .forEach((r) => (r.required = true));
  } else {
    visitorsBlock.style.display = "none";

    // Remove required attribute and clear selection from visitor radio buttons
    visitorsBlock
      .querySelectorAll('input[name="has_visitors"]')
      .forEach((r) => {
        r.required = false; // remove required
        r.checked = false; // clear selection
      });
  }
}

// ===== INITIALIZE TOGGLE ON PAGE LOAD =====
// Call toggleBlocks when page is shown (handles back/forward navigation)
window.addEventListener("pageshow", toggleBlocks);

// ===== DOM CONTENT LOADED EVENT =====
// Set up event listeners and initialize form components after DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  // ===== RADIO BUTTON EVENT LISTENERS =====
  // Attach change listeners to background and activity type radio buttons
  document
    .querySelectorAll('input[name="background"]')
    .forEach((r) => r.addEventListener("change", toggleBlocks));
  document
    .querySelectorAll('input[name="activity_type"]')
    .forEach((r) => r.addEventListener("change", toggleBlocks));

  // ===== MULTI-SELECT DROPDOWN SETUP =====
  // Get references to multi-select components
  const select = document.getElementById("organizing_body");
  const dropdown = document.getElementById("orgDropdown");
  const input = dropdown.querySelector(".multi-input");
  const list = dropdown.querySelector(".dropdown-list");
  const tagsContainer = document.getElementById("selectedTags");

  // ===== POPULATE DROPDOWN LIST =====
  // Function to populate dropdown list with filtered options
  function populateList(filter = "") {
    list.innerHTML = "";
    // Iterate through select options and create list items for matching unselected options
    Array.from(select.options).forEach((option) => {
      if (
        option.value.toLowerCase().includes(filter.toLowerCase()) &&
        !option.selected
      ) {
        const item = document.createElement("div");
        item.textContent = option.value;
        item.dataset.value = option.value;

        // Handle item click to select option and update UI
        item.addEventListener("click", () => {
          option.selected = true;
          renderTags();
          input.value = "";
          list.style.display = "none";
        });

        list.appendChild(item);
      }
    });

    // Show or hide list based on whether there are items
    list.style.display = list.children.length ? "block" : "none";
  }

  // ===== RENDER SELECTED TAGS =====
  // Function to render selected options as removable tags
  function renderTags() {
    tagsContainer.innerHTML = "";
    const selectedOptions = Array.from(select.selectedOptions);

    // Create tag for each selected option
    selectedOptions.forEach((option) => {
      const tag = document.createElement("div");
      tag.className = "tag";
      tag.innerHTML = `${option.value}<span>&times;</span>`;

      // Handle tag removal click
      tag.querySelector("span").addEventListener("click", () => {
        option.selected = false;
        renderTags();
        populateList(input.value);
      });

      tagsContainer.appendChild(tag);
    });
  }

  // ===== INPUT EVENT LISTENERS =====
  // Filter dropdown list as user types in input
  input.addEventListener("input", () => {
    populateList(input.value);
  });

  // Show dropdown list when input gains focus
  input.addEventListener("focus", () => {
    populateList(input.value);
  });

  // ===== CLICK OUTSIDE HANDLER =====
  // Hide dropdown list when clicking outside the dropdown component
  document.addEventListener("click", (e) => {
    if (!dropdown.contains(e.target)) list.style.display = "none";
  });

  // Initial render of tags
  renderTags();

  // ===== STEP NAVIGATION ELEMENTS =====
  // Get references to step containers and navigation buttons
  const step1 = document.querySelector(".step-1");
  const step2 = document.querySelectorAll(".step-2");

  const nextBtn = document.querySelector(".next-btn");
  const backBtn = document.querySelector(".back-btn");
  const createBtn = document.querySelector(".create-btn");

  const step1Actions = document.querySelector(".step1-actions");
  const step2Actions = document.querySelector(".step-2-actions");

  // Early return if required elements are missing
  if (!step1 || !nextBtn || !createBtn) return;

  // ===== INITIAL STATE SETUP =====
  // Hide step 2 and disable create button initially
  step2.forEach((s) => (s.style.display = "none"));
  step2Actions.style.display = "none";
  createBtn.disabled = true;

  // ===== UTILITY FUNCTIONS =====
  // Check if an element is visible (not hidden, disabled, or in hidden container)
  const isVisible = (el) =>
    el.offsetParent !== null && !el.closest("[hidden]") && !el.disabled;

  // Get all input, textarea, and select elements within a container
  const getInputs = (container) => [
    ...container.querySelectorAll("input, textarea, select"),
  ];

  // ===== VALIDATION FUNCTION =====
  // Validate all required fields in a container
  function validateContainer(container) {
    const inputs = getInputs(container).filter(isVisible);
    let valid = true;

    const radioGroups = {};

    // Process each input for validation
    for (const input of inputs) {
      // Ignore hidden select used by tag widget
      if (input.tagName === "SELECT" && input.hidden) continue;

      // Handle radio buttons by grouping them
      if (input.type === "radio") {
        if (input.required) {
          (radioGroups[input.name] ??= []).push(input);
        }
        continue;
      }

      // Handle multi-select elements
      if (input.tagName === "SELECT" && input.multiple && input.required) {
        if (input.selectedOptions.length === 0) valid = false;
        continue;
      }

      // Handle text inputs, textareas, etc.
      if (input.required && !input.value.trim()) valid = false;
    }

    // Validate that at least one radio in each required group is checked
    for (const group of Object.values(radioGroups)) {
      if (!group.some((r) => r.checked)) valid = false;
    }

    // Check if tags container has at least one tag (for organizing body)
    const tagsContainer = container.querySelector("#selectedTags");
    if (tagsContainer && tagsContainer.querySelectorAll(".tag").length === 0)
      valid = false;

    return valid;
  }

  // ===== STEP VALIDATION FUNCTIONS =====
  // Validate step 1 and update next button state
  function validateStep1() {
    nextBtn.disabled = !validateContainer(step1);
  }

  // Validate all step 2 sections and update create button state
  function validateStep2() {
    const valid = [...step2].every((step) => validateContainer(step));
    createBtn.disabled = !valid;
  }

  // ===== LIVE VALIDATION EVENT LISTENERS =====
  // Re-validate on input and change events
  document.addEventListener("input", () => {
    validateStep1();
    validateStep2();
  });

  document.addEventListener("change", () => {
    validateStep1();
    validateStep2();
  });

  // ===== MUTATION OBSERVER =====
  // Watch for DOM changes that might affect visibility and re-validate
  const observer = new MutationObserver(() => {
    validateStep1();
    validateStep2();
  });

  observer.observe(step1, {
    subtree: true,
    childList: true,
    attributes: true,
    attributeFilter: ["style", "class", "open"],
  });

  // Initial validation run
  validateStep1();
  validateStep2();

  // ===== NAVIGATION EVENT HANDLERS =====
  // Handle next button click to proceed to step 2
  nextBtn.addEventListener("click", () => {
    if (nextBtn.disabled) return;

    // Hide Step 1 completely
    step1.style.display = "none";
    step1Actions.style.display = "none";

    // Show Step 2
    step2.forEach((s) => {
      s.style.display = "block";
      s.open = true;
    });

    step2Actions.style.display = "flex";

    validateStep2();

    window.scrollTo({ top: 0, behavior: "smooth" });
  });

  // Handle back button click to return to step 1
  backBtn.addEventListener("click", () => {
    // Show Step 1
    step1.style.display = "block";
    step1Actions.style.display = "flex";

    // Hide Step 2
    step2.forEach((s) => {
      s.style.display = "none";
      s.open = false;
    });

    step2Actions.style.display = "none";

    createBtn.disabled = true;

    validateStep1();

    window.scrollTo({ top: 0, behavior: "smooth" });
  });
});
