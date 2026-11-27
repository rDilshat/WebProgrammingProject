const searchForm = document.querySelector("#movie-search-form");

if (searchForm) {
  const queryInput = document.querySelector("#search-query");
  const genreSelect = document.querySelector("#search-genre");
  const sortSelect = document.querySelector("#search-sort");
  const directionSelect = document.querySelector("#search-direction");
  const results = document.querySelector("#movie-results");
  const status = document.querySelector("#search-status");
  const pagination = document.querySelector("#movie-pagination");
  const apiUrl = "../api/movies.php";
  const perPage = 8;
  let currentPage = 1;
  let requestNumber = 0;
  let debounceTimer;

  function setStatus(message, state = "") {
    status.textContent = message;
    status.dataset.state = state;
  }

  function movieCard(movie) {
    const article = document.createElement("article");
    article.className = "movie-card";

    const posterLink = document.createElement("a");
    posterLink.href = `movie.php?id=${encodeURIComponent(movie.slug)}`;
    const poster = document.createElement("img");
    poster.src = `../photos/${encodeURIComponent(movie.poster)}`;
    poster.alt = `${movie.title} poster`;
    poster.loading = "lazy";
    posterLink.appendChild(poster);

    const title = document.createElement("h2");
    title.textContent = movie.title;
    const meta = document.createElement("p");
    const parts = [...movie.genres, `${movie.duration_min} min`];
    if (movie.release_year !== null) {
      parts.push(String(movie.release_year));
    }
    meta.textContent = parts.join(" · ");

    const detailsLink = document.createElement("a");
    detailsLink.className = "text-button";
    detailsLink.href = `movie.php?id=${encodeURIComponent(movie.slug)}`;
    detailsLink.textContent = "VIEW DETAILS";

    article.append(posterLink, title, meta, detailsLink);
    return article;
  }

  function renderPagination(data) {
    pagination.replaceChildren();
    if (data.total_pages < 2) {
      return;
    }

    const previous = document.createElement("button");
    previous.type = "button";
    previous.textContent = "Previous";
    previous.disabled = data.page <= 1;
    previous.addEventListener("click", () => loadMovies(data.page - 1));

    const pageLabel = document.createElement("span");
    pageLabel.textContent = `Page ${data.page} of ${data.total_pages}`;

    const next = document.createElement("button");
    next.type = "button";
    next.textContent = "Next";
    next.disabled = data.page >= data.total_pages;
    next.addEventListener("click", () => loadMovies(data.page + 1));
    pagination.append(previous, pageLabel, next);
  }

  async function loadMovies(page = 1) {
    const requestId = ++requestNumber;
    currentPage = page;
    const params = new URLSearchParams({
      q: queryInput.value.trim(),
      genre: genreSelect.value,
      sort: sortSelect.value,
      direction: directionSelect.value,
      page: String(page),
      per_page: String(perPage),
    });

    setStatus("Loading…", "loading");
    results.setAttribute("aria-busy", "true");
    pagination.replaceChildren();
    try {
      const response = await fetch(`${apiUrl}?${params.toString()}`, {
        headers: { Accept: "application/json" },
        cache: "no-store",
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.error || "Unable to load movies.");
      }
      if (requestId !== requestNumber) {
        return;
      }

      results.replaceChildren(...payload.data.items.map(movieCard));
      if (payload.data.total === 0) {
        setStatus("Nothing found", "empty");
      } else {
        setStatus(`${payload.data.total} movies found`, "success");
      }
      renderPagination(payload.data);
      currentPage = payload.data.page;
      const url = new URL(window.location.href);
      ["q", "genre", "sort", "direction", "page"].forEach((key) => {
        url.searchParams.delete(key);
      });
      if (queryInput.value.trim()) url.searchParams.set("q", queryInput.value.trim());
      if (genreSelect.value) url.searchParams.set("genre", genreSelect.value);
      if (sortSelect.value !== "title") url.searchParams.set("sort", sortSelect.value);
      if (directionSelect.value !== "asc") {
        url.searchParams.set("direction", directionSelect.value);
      }
      if (currentPage > 1) url.searchParams.set("page", String(currentPage));
      window.history.replaceState({}, "", url);
    } catch (error) {
      if (requestId !== requestNumber) {
        return;
      }
      results.replaceChildren();
      const message = error instanceof TypeError
        ? "The movie service is unavailable. Please try again."
        : error.message || "Unable to load movies. Please try again.";
      setStatus(message, "error");
    } finally {
      if (requestId === requestNumber) {
        results.setAttribute("aria-busy", "false");
      }
    }
  }

  searchForm.addEventListener("submit", (event) => {
    event.preventDefault();
    loadMovies(1);
  });
  genreSelect.addEventListener("change", () => loadMovies(1));
  sortSelect.addEventListener("change", () => loadMovies(1));
  directionSelect.addEventListener("change", () => loadMovies(1));
  queryInput.addEventListener("input", () => {
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(() => loadMovies(1), 300);
  });

  const params = new URLSearchParams(window.location.search);
  const initialPage = Number.parseInt(params.get("page") || "1", 10);
  if (Number.isInteger(initialPage) && initialPage > 0) {
    loadMovies(initialPage);
  } else {
    loadMovies(1);
  }
}
