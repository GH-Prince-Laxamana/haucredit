// ===== TOGGLE CONDITIONAL FORM BLOCKS =====
function toggleBlocks() {
  const seriesBlock = document.getElementById("series-block");
  const offcampusBlock = document.getElementById("offcampus-block");
  const visitorsBlock = document.getElementById("visitors-block");

  const selectedBackground = document.querySelector(
    'input[name="background_id"]:checked',
  );
  const selectedActivity = document.querySelector(
    'input[name="activity_type_id"]:checked',
  );

  const backgroundName =
    selectedBackground?.dataset.backgroundName?.trim() || "";
  const activityName = selectedActivity?.dataset.activityTypeName?.trim() || "";

  // ===== SERIES BLOCK =====
  if (backgroundName === "Participation") {
    seriesBlock.style.display = "flex";
    seriesBlock
      .querySelectorAll('input[name="series_option_id"]')
      .forEach((i) => (i.required = true));
  } else {
    seriesBlock.style.display = "none";
    seriesBlock
      .querySelectorAll('input[name="series_option_id"]')
      .forEach((i) => {
        i.required = false;
        i.checked = false;
      });
  }

  // ===== OFF-CAMPUS BLOCK =====
  if (activityName.toLowerCase().includes("off-campus")) {
    offcampusBlock.style.display = "block";
    offcampusBlock
      .querySelectorAll("input")
      .forEach((i) => (i.required = true));
  } else {
    offcampusBlock.style.display = "none";
    offcampusBlock.querySelectorAll("input").forEach((i) => {
      i.required = false;
      if (i.type === "radio") i.checked = false;
    });
  }

  // ===== VISITORS BLOCK =====
  if (activityName.toLowerCase().includes("on-campus")) {
    visitorsBlock.style.display = "flex";
    visitorsBlock
      .querySelectorAll('input[name="has_visitors"]')
      .forEach((r) => (r.required = true));
  } else {
    visitorsBlock.style.display = "none";
    visitorsBlock
      .querySelectorAll('input[name="has_visitors"]')
      .forEach((r) => {
        r.required = false;
        r.checked = false;
      });
  }
}

function isValidTargetMetric(value) {
  const v = value.trim();
  if (!v) return false;
  return /^(100|[1-9]?\d)\%\s+.+$/.test(v);
}

function hasMinimumTwoHoursGap(startValue, endValue) {
  if (!startValue || !endValue) return false;

  const start = new Date(startValue);
  const end = new Date(endValue);

  if (isNaN(start.getTime()) || isNaN(end.getTime())) return false;

  const diffMs = end - start;
  return diffMs >= 2 * 60 * 60 * 1000;
}

// ===== INITIALIZE TOGGLE ON PAGE LOAD =====
window.addEventListener("pageshow", toggleBlocks);

// ===== DOM CONTENT LOADED EVENT =====
document.addEventListener("DOMContentLoaded", () => {
  // ===== RADIO BUTTON EVENT LISTENERS =====
  document
    .querySelectorAll('input[name="background_id"]')
    .forEach((r) => r.addEventListener("change", toggleBlocks));

  document
    .querySelectorAll('input[name="activity_type_id"]')
    .forEach((r) => r.addEventListener("change", toggleBlocks));

  // ===== MULTI-SELECT DROPDOWN SETUP =====
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
          validateStep1();
          validateStep2();
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
        validateStep1();
        validateStep2();
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

  // ===== STEP NAVIGATION ELEMENTS =====
  const step1 = document.querySelector(".step-1");
  const step2 = document.querySelectorAll(".step-2");

  const nextBtn = document.querySelector(".next-btn");
  const backBtn = document.querySelector(".back-btn");
  const createBtn = document.querySelector(".create-btn");

  const step1Actions = document.querySelector(".step1-actions");
  const step2Actions = document.querySelector(".step-2-actions");

  if (!step1 || !nextBtn || !createBtn) return;

  // ===== INITIAL STATE =====
  step2.forEach((s) => (s.style.display = "none"));
  step2Actions.style.display = "none";
  createBtn.disabled = true;

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
      if (input.tagName === "SELECT" && input.hidden) continue;

      if (input.type === "radio") {
        if (input.required) {
          (radioGroups[input.name] ??= []).push(input);
        }
        continue;
      }

      if (input.tagName === "SELECT" && input.multiple && input.required) {
        if (input.selectedOptions.length === 0) valid = false;
        continue;
      }

      if (input.required && !input.value.trim()) {
        valid = false;
        continue;
      }

      if (input.id === "target_metric" && input.value.trim()) {
        if (!isValidTargetMetric(input.value)) valid = false;
      }
    }

    for (const group of Object.values(radioGroups)) {
      if (!group.some((r) => r.checked)) valid = false;
    }

    const tagsContainer = container.querySelector("#selectedTags");
    if (tagsContainer && tagsContainer.querySelectorAll(".tag").length === 0) {
      valid = false;
    }

    const startInput = document.getElementById("start_datetime");
    const endInput = document.getElementById("end_datetime");

    if (
      startInput &&
      endInput &&
      isVisible(startInput) &&
      isVisible(endInput)
    ) {
      if (!hasMinimumTwoHoursGap(startInput.value, endInput.value)) {
        valid = false;
      }
    }

    return valid;
  }

  function validateStep1() {
    nextBtn.disabled = !validateContainer(step1);
  }

  function validateStep2() {
    const valid = [...step2].every((step) => validateContainer(step));
    createBtn.disabled = !valid;
  }

  document.addEventListener("input", () => {
    validateStep1();
    validateStep2();
  });

  document.addEventListener("change", () => {
    validateStep1();
    validateStep2();
  });

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

  toggleBlocks();
  validateStep1();
  validateStep2();

  nextBtn.addEventListener("click", () => {
    if (nextBtn.disabled) return;

    step1.style.display = "none";
    step1Actions.style.display = "none";

    step2.forEach((s) => {
      s.style.display = "block";
      s.open = true;
    });

    step2Actions.style.display = "flex";
    validateStep2();

    window.scrollTo({ top: 0, behavior: "smooth" });
  });

  backBtn.addEventListener("click", () => {
    step1.style.display = "block";
    step1Actions.style.display = "flex";

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
