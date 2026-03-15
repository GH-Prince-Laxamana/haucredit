let currentFilter = "all";

function setFilter(btn, filter) {
  document
    .querySelectorAll(".filter-tab")
    .forEach((b) => b.classList.remove("active"));

  btn.classList.add("active");

  currentFilter = filter;

  filterEvents();
}

function filterEvents() {
  const query = document
    .getElementById("searchInput")
    .value.toLowerCase()
    .trim();

  const cards = document.querySelectorAll(".event-card");

  let visible = 0;

  cards.forEach((card) => {
    const status = card.dataset.status;
    const text = card.dataset.search || "";

    const matchFilter = currentFilter === "all" || status === currentFilter;
    const matchSearch = text.includes(query);

    if (matchFilter && matchSearch) {
      card.style.display = "block";
      visible++;
    } else {
      card.style.display = "none";
    }
  });

  document.getElementById("emptyState").hidden = visible !== 0;
}
