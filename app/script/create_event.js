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

  if (activity && activity.includes('Off-Campus')) {
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
