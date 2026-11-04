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


// "More" nav menu: close on outside click, Escape, or when focus leaves it.
document.querySelectorAll(".nav-more").forEach((menu) => {
  document.addEventListener("click", (event) => {
    if (menu.open && !menu.contains(event.target)) menu.open = false;
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && menu.open) {
      menu.open = false;
      menu.querySelector("summary").focus();
    }
  });
  menu.addEventListener("focusout", (event) => {
    if (menu.open && event.relatedTarget && !menu.contains(event.relatedTarget)) menu.open = false;
  });
});
