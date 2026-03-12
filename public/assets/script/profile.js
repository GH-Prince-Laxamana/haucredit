document.addEventListener("DOMContentLoaded", function(){

/* ================= TAB SWITCH ================= */

const editBtn = document.getElementById("editBtn");
const passBtn = document.getElementById("passBtn");
const editTab = document.getElementById("editTab");
const passTab = document.getElementById("passTab");

if(editBtn && passBtn){

editBtn.addEventListener("click", ()=>{
editTab.classList.add("active");
passTab.classList.remove("active");

editBtn.classList.add("active");
passBtn.classList.remove("active");
});

passBtn.addEventListener("click", ()=>{
passTab.classList.add("active");
editTab.classList.remove("active");

passBtn.classList.add("active");
editBtn.classList.remove("active");
});

}

/* ================= PASSWORD TOGGLE ================= */

document.querySelectorAll(".eye-btn").forEach(btn => {

btn.addEventListener("click", () => {

const id = btn.dataset.toggle;
const input = document.getElementById(id);

if(input){
input.type = input.type === "password" ? "text" : "password";
}

});

});


/* ================= IMAGE CROPPER ================= */

let cropper;

const cameraBtn = document.querySelector(".camera-btn");
const photoInput = document.getElementById("photoInput");
const cropModal = document.getElementById("cropModal");
const cropImage = document.getElementById("cropImage");
const cropSave = document.getElementById("cropSave");
const cropCancel = document.getElementById("cropCancel");
const cropClose = document.getElementById("cropClose");
const avatar = document.querySelector(".profile-avatar");
const photoSubmit = document.getElementById("photoSubmit");

if(cameraBtn){
cameraBtn.addEventListener("click", ()=> photoInput.click());
}

if(photoInput){

photoInput.addEventListener("change", function(){

if(this.files && this.files[0]){

const reader = new FileReader();

reader.onload = function(e){

cropModal.classList.add("active");
cropImage.src = e.target.result;

if(cropper) cropper.destroy();

cropper = new Cropper(cropImage,{
aspectRatio:1,
viewMode:1,
autoCropArea:1
});

};

reader.readAsDataURL(this.files[0]);

}

});

}

if(cropSave){

cropSave.addEventListener("click", ()=>{

const canvas = cropper.getCroppedCanvas({
width:400,
height:400
});

canvas.toBlob(function(blob){

const file = new File([blob],"profile.jpg",{type:"image/jpeg"});

const dt = new DataTransfer();
dt.items.add(file);

photoInput.files = dt.files;

avatar.src = canvas.toDataURL();

cropModal.classList.remove("active");

setTimeout(()=> photoSubmit.click(),300);

});

});

}

if(cropCancel){
cropCancel.addEventListener("click", ()=> cropModal.classList.remove("active"));
}

if(cropClose){
cropClose.addEventListener("click", ()=> cropModal.classList.remove("active"));
}


/* ================= DRAG & DROP UPLOAD ================= */

const avatarWrap = document.querySelector(".profile-avatar-wrap");

if(avatarWrap){

avatarWrap.addEventListener("dragover",(e)=>{
e.preventDefault();
avatarWrap.classList.add("dragging");
});

avatarWrap.addEventListener("dragleave",()=>{
avatarWrap.classList.remove("dragging");
});

avatarWrap.addEventListener("drop",(e)=>{

e.preventDefault();
avatarWrap.classList.remove("dragging");

photoInput.files = e.dataTransfer.files;

photoInput.dispatchEvent(new Event("change"));

});

}


/* ================= ALERT AUTO DISMISS ================= */

const alertBox = document.querySelector(".alert");

if(alertBox){

setTimeout(()=>{
alertBox.style.opacity="0";
alertBox.style.transform="translateY(-6px)";
setTimeout(()=>alertBox.remove(),300);
},4000);

}

});