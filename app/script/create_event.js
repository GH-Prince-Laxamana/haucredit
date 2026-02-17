function toggleBlocks() {
  const seriesBlock = document.getElementById('series-block');
  const offcampusBlock = document.getElementById('offcampus-block');

  const background = document.querySelector('input[name="background"]:checked')?.value;
  const activity = document.querySelector('input[name="activity_type"]:checked')?.value;

  if (background === 'Participation') {
    seriesBlock.style.display = 'block';
    seriesBlock.querySelectorAll('input').forEach(i => i.required = true);
  } else {
    seriesBlock.style.display = 'none';
    seriesBlock.querySelectorAll('input').forEach(i => i.required = false);
  }

  if (activity && activity.toLowerCase().includes('off-campus')) {
    offcampusBlock.style.display = 'block';
    offcampusBlock.querySelectorAll('input').forEach(i => i.required = true);
  } else {
    offcampusBlock.style.display = 'none';
    offcampusBlock.querySelectorAll('input').forEach(i => i.required = false);
  }
}

window.addEventListener('pageshow', toggleBlocks);

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input[name="background"]').forEach(r => r.addEventListener('change', toggleBlocks));
  document.querySelectorAll('input[name="activity_type"]').forEach(r => r.addEventListener('change', toggleBlocks));
});

document.addEventListener("DOMContentLoaded", () => {
  const select = document.getElementById("organizing_body");
  const dropdown = document.getElementById("orgDropdown");
  const input = dropdown.querySelector(".multi-input");
  const list = dropdown.querySelector(".dropdown-list");
  const tagsContainer = document.getElementById("selectedTags");
  
  function populateList(filter = "") {
    list.innerHTML = "";
    Array.from(select.options).forEach(option => {
      if (option.value.toLowerCase().includes(filter.toLowerCase()) && !option.selected) {
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

    selectedOptions.forEach(option => {
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
});
