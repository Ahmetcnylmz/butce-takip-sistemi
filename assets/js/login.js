const container = document.querySelector(".container");
const passwordInput = document.getElementById("password");
const passwordIcon = document.getElementById("passwordIcon");

window.addEventListener("load", function() {
  container.style.opacity = "1";
  container.style.transform = "scale(1)";
});

passwordIcon.addEventListener("click", function() {
  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    passwordIcon.classList.remove("fa-eye");
    passwordIcon.classList.add("fa-eye-slash");
  } else {
    passwordInput.type = "password";
    passwordIcon.classList.remove("fa-eye-slash");
    passwordIcon.classList.add("fa-eye");
  }
});
