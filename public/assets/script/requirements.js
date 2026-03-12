document.addEventListener("DOMContentLoaded", function () {

    const checkboxes = document.querySelectorAll(".req-checkbox");
    const progressFill = document.querySelector(".progress-fill");
    const progressText = document.querySelector(".progress-text");

    function updateProgress() {

        const total = checkboxes.length;
        const completed = document.querySelectorAll(".req-checkbox:checked").length;

        const percent = total === 0 ? 0 : Math.round((completed / total) * 100);

        if (progressFill) {
            progressFill.style.width = percent + "%";
        }

        if (progressText) {
            progressText.textContent = `${completed} / ${total} Completed (${percent}%)`;
        }

    }

    checkboxes.forEach(box => {

        box.addEventListener("change", function () {

            const id = this.dataset.id;
            const status = this.checked ? 1 : 0;

            fetch("script/update_requirement.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: `id=${id}&status=${status}`
            });

            updateProgress();

        });

    });

    updateProgress();

});

    document.querySelectorAll(".req-checkbox").forEach(box=>{

    box.addEventListener("change",function(){

    let id=this.dataset.id;
    let status=this.checked?1:0;

    fetch("script/update_requirement.php",{

    method:"POST",

    headers:{
    "Content-Type":"application/x-www-form-urlencoded"
    },

    body:`id=${id}&status=${status}`

    });

    });

});
