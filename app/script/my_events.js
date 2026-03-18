// ===== GLOBAL STATE VARIABLE =====
// Track the currently selected filter type (all, active, pending, completed)
let currentFilter = "all";

// ===== SET FILTER FUNCTION =====
// Update the active filter tab and apply the new filter
function setFilter(btn, filter) {
  // Remove active class from all filter tabs
  document
    .querySelectorAll(".filter-tab")
    .forEach((b) => b.classList.remove("active"));

  // Add active class to the clicked button
  btn.classList.add("active");

  // Update the current filter state
  currentFilter = filter;

  // Apply the filter to show/hide events
  filterEvents();
}

// ===== FILTER EVENTS FUNCTION =====
// Filter event cards based on current filter and search query
function filterEvents() {
  // Get and normalize the search input value
  const query = document
    .getElementById("searchInput")
    .value.toLowerCase()
    .trim();

  // Get all event card elements
  const cards = document.querySelectorAll(".event-card");

  // Counter for visible cards
  let visible = 0;

  // Process each event card for filtering
  cards.forEach((card) => {
    // Get the event status and searchable text from data attributes
    const status = card.dataset.status;
    const text = card.dataset.search || "";

    // Check if card matches the current filter (all or specific status)
    const matchFilter = currentFilter === "all" || status === currentFilter;

    // Check if card matches the search query
    const matchSearch = text.includes(query);

    // Show card if it matches both filter and search, otherwise hide
    if (matchFilter && matchSearch) {
      card.style.display = "block";
      visible++;
    } else {
      card.style.display = "none";
    }
  });

  // Show or hide the empty state message based on visible cards
  document.getElementById("emptyState").hidden = visible !== 0;
}
