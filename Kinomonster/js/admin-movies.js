document.querySelectorAll('form[data-movie-action]').forEach((form) => {
  form.addEventListener("submit", (event) => {
    const action = form.dataset.movieAction;
    const message = action === "deactivate"
      ? "Deactivate this movie? It will disappear from the public catalog, but its booking history will be preserved."
      : "Restore this movie to the public catalog?";
    if (!window.confirm(message)) {
      event.preventDefault();
    }
  });
});

document.querySelectorAll('form[data-showtime-action="delete"]').forEach((form) => {
  form.addEventListener("submit", (event) => {
    if (!window.confirm("Remove this upcoming showtime? A session with bookings cannot be removed.")) {
      event.preventDefault();
    }
  });
});
