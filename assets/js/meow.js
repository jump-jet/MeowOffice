document.querySelectorAll(".toast").forEach((toast) => {
  setTimeout(() => {
    toast.style.transition = "opacity 400ms ease";
    toast.style.opacity = "0";
  }, 4200);
});

// Forms with data-confirm="..." ask before submitting (deletes, rejections).
document.querySelectorAll("form[data-confirm]").forEach((form) => {
  form.addEventListener("submit", (event) => {
    if (!window.confirm(form.dataset.confirm)) {
      event.preventDefault();
    }
  });
});

// The paw badge popup floats away after a moment.
document.querySelectorAll(".paw-pop").forEach((pop) => {
  setTimeout(() => pop.classList.add("paw-pop-out"), 5200);
});
