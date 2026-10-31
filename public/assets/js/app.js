(() => {
  document.querySelectorAll("[data-dismiss]").forEach((button) => {
    button.addEventListener("click", () => button.closest(".flash")?.remove());
  });

  document.querySelectorAll("[data-confirm]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      if (!window.confirm(form.dataset.confirm || "Continue?")) event.preventDefault();
    });
  });

  document.querySelectorAll("[data-toggle-password]").forEach((button) => {
    button.addEventListener("click", () => {
      const input = button.parentElement?.querySelector("input");
      if (!input) return;
      const visible = input.type === "text";
      input.type = visible ? "password" : "text";
      button.textContent = visible ? "Show" : "Hide";
    });
  });

  const breedingInput = document.querySelector("[data-breeding-date]");
  const farrowingPreview = document.querySelector("[data-farrowing-preview]");
  const updateFarrowing = () => {
    if (!breedingInput?.value || !farrowingPreview) return;
    const [year, month, day] = breedingInput.value.split("-").map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));
    date.setUTCDate(date.getUTCDate() + 114);
    farrowingPreview.textContent = new Intl.DateTimeFormat("en-PH", {
      year: "numeric",
      month: "short",
      day: "numeric",
      timeZone: "UTC",
    }).format(date);
  };
  breedingInput?.addEventListener("change", updateFarrowing);

  document.querySelectorAll("form").forEach((form) => {
    form.addEventListener("submit", () => {
      if (!form.checkValidity()) return;
      const submit = form.querySelector('button[type="submit"]');
      if (submit) {
        submit.dataset.originalText = submit.textContent;
        submit.textContent = "Please wait…";
        submit.setAttribute("aria-busy", "true");
      }
    });
  });
})();
