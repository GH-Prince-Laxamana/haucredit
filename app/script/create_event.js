function toggleBlocks() {
  const seriesBlock = document.getElementById("series-block");
  const offcampusBlock = document.getElementById("offcampus-block");

  const background = document.querySelector(
    'input[name="background"]:checked',
  )?.value;
  const activity = document.querySelector(
    'input[name="activity_type"]:checked',
  )?.value;

  if (background === "Participation") {
    seriesBlock.style.display = "flex";
    seriesBlock.querySelectorAll("input").forEach((i) => (i.required = true));
  } else {
    seriesBlock.style.display = "none";
    seriesBlock.querySelectorAll("input").forEach((i) => (i.required = false));
  }

  if (activity && activity.toLowerCase().includes("off-campus")) {
    offcampusBlock.style.display = "flex";
    offcampusBlock
      .querySelectorAll("input")
      .forEach((i) => (i.required = true));
  } else {
    offcampusBlock.style.display = "none";
    offcampusBlock
      .querySelectorAll("input")
      .forEach((i) => (i.required = false));
  }
}

window.addEventListener("pageshow", toggleBlocks);

document.addEventListener("DOMContentLoaded", () => {
  document
    .querySelectorAll('input[name="background"]')
    .forEach((r) => r.addEventListener("change", toggleBlocks));
  document
    .querySelectorAll('input[name="activity_type"]')
    .forEach((r) => r.addEventListener("change", toggleBlocks));

  const select = document.getElementById("organizing_body");
  const dropdown = document.getElementById("orgDropdown");
  const input = dropdown.querySelector(".multi-input");
  const list = dropdown.querySelector(".dropdown-list");
  const tagsContainer = document.getElementById("selectedTags");

  function populateList(filter = "") {
    list.innerHTML = "";
    Array.from(select.options).forEach((option) => {
      if (
        option.value.toLowerCase().includes(filter.toLowerCase()) &&
        !option.selected
      ) {
        const item = document.createElement("div");
        item.textContent = option.value;
        item.dataset.value = option.value;

        item.addEventListener("click", () => {
          option.selected = true;
          renderTags();
          input.value = "";
          list.style.display = "none";
        });

        list.appendChild(item);
      }
    });

    list.style.display = list.children.length ? "block" : "none";
  }

  function renderTags() {
    tagsContainer.innerHTML = "";
    const selectedOptions = Array.from(select.selectedOptions);

    selectedOptions.forEach((option) => {
      const tag = document.createElement("div");
      tag.className = "tag";
      tag.innerHTML = `${option.value}<span>&times;</span>`;

      tag.querySelector("span").addEventListener("click", () => {
        option.selected = false;
        renderTags();
        populateList(input.value);
      });

      tagsContainer.appendChild(tag);
    });
  }

  input.addEventListener("input", () => {
    populateList(input.value);
  });

  input.addEventListener("focus", () => {
    populateList(input.value);
  });

  document.addEventListener("click", (e) => {
    if (!dropdown.contains(e.target)) list.style.display = "none";
  });

  renderTags();

  // ---------- ELEMENTS ----------
  const step1 = document.querySelector(".step-1");
  const step2 = document.querySelectorAll(".step-2");

  const nextBtn = document.querySelector(".next-btn");
  const backBtn = document.querySelector(".back-btn");
  const createBtn = document.querySelector(".create-btn");

  const step1Actions = document.querySelector(".step1-actions");
  const step2Actions = document.querySelector(".step-2-actions");

  if (!step1 || !nextBtn || !createBtn) return;

  // ---------- INITIAL STATE ----------
  step2.forEach((s) => (s.style.display = "none"));
  step2Actions.style.display = "none";
  createBtn.disabled = true;

  // ---------- UTILITIES ----------

  const isVisible = (el) =>
    el.offsetParent !== null && !el.closest("[hidden]") && !el.disabled;

  const getInputs = (container) => [
    ...container.querySelectorAll("input, textarea, select"),
  ];

  function validateContainer(container) {
    const inputs = getInputs(container).filter(isVisible);
    let valid = true;

    const radioGroups = {};

    for (const input of inputs) {
      // Ignore hidden select used by tag widget
      if (input.tagName === "SELECT" && input.hidden) continue;

      // Radios
      if (input.type === "radio") {
        if (input.required) {
          (radioGroups[input.name] ??= []).push(input);
        }
        continue;
      }

      // Multi-select
      if (input.tagName === "SELECT" && input.multiple && input.required) {
        if (input.selectedOptions.length === 0) valid = false;
        continue;
      }

      // Text / textarea / datetime etc
      if (input.required && !input.value.trim()) valid = false;
    }

    // Validate radio groups
    for (const group of Object.values(radioGroups)) {
      if (!group.some((r) => r.checked)) valid = false;
    }

    const tagsContainer = container.querySelector("#selectedTags");
    if (tagsContainer && tagsContainer.querySelectorAll(".tag").length === 0) valid = false;

    return valid;
  }

  // ---------- STEP VALIDATION ----------

  function validateStep1() {
    nextBtn.disabled = !validateContainer(step1);
  }

  function validateStep2() {
    const valid = [...step2].every((step) => validateContainer(step));
    createBtn.disabled = !valid;
  }

  // ---------- LIVE INPUT WATCH ----------
  document.addEventListener("input", () => {
    validateStep1();
    validateStep2();
  });

  document.addEventListener("change", () => {
    validateStep1();
    validateStep2();
  });

  // ---------- WATCH DYNAMIC VISIBILITY ----------
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

  // Initial validation
  validateStep1();
  validateStep2();

  // ---------- NAVIGATION ----------

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
