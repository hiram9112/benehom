document.addEventListener("click", (event) => {
  const button = event.target.closest("[data-bh-password-toggle]");

  if (!button) return;

  const input = document.getElementById(button.dataset.bhPasswordToggle);
  const icon = button.querySelector("i");

  if (!input) return;

  const isHidden = input.type === "password";
  input.type = isHidden ? "text" : "password";
  button.setAttribute("aria-pressed", isHidden ? "true" : "false");
  button.setAttribute(
    "aria-label",
    isHidden ? "Ocultar contraseña" : "Mostrar contraseña",
  );

  if (icon) {
    icon.classList.toggle("bi-eye", !isHidden);
    icon.classList.toggle("bi-eye-slash", isHidden);
  }
});
