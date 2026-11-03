document.querySelectorAll(".toast").forEach((toast) => {
  setTimeout(() => {
    toast.style.transition = "opacity 400ms ease";
    toast.style.opacity = "0";
  }, 4200);
});
