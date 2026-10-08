const heroSection = document.querySelector("#heroSection");

const mainScript = document.querySelector('script[src$="js/main.js"]');

if (mainScript) {
  const authStatusUrl = new URL("../pages/auth-status.php", mainScript.src);

  fetch(authStatusUrl, { credentials: "same-origin" })
    .then((response) => response.ok ? response.json() : Promise.reject())
    .then((auth) => {
      document.body.dataset.authenticated = String(auth.authenticated);

      if (!auth.authenticated) {
        return;
      }

      document.querySelectorAll('a[href$="login.html"], a[href$="register.html"]')
        .forEach((link) => link.closest("li")?.remove());

      document.querySelectorAll(".second-menu, .second-menu-mobile")
        .forEach((menu) => {
          addAccountLinks(menu, auth.user);

          const item = document.createElement("li");
          const logoutLink = document.createElement("a");
          menu.classList.add("authenticated-menu");
          item.classList.add("auth-menu-item");
          logoutLink.href = new URL("../pages/logout.php", mainScript.src);
          logoutLink.textContent = `LOG OUT (${auth.user.firstName})`;
          item.append(logoutLink);
          menu.append(item);
        });

      document.querySelectorAll('.footer a[href$="login.html"]').forEach((link) => {
        link.href = new URL("../pages/profile.php", mainScript.src);
        link.textContent = "Profile";
      });

      document.querySelectorAll('.footer a[href$="register.html"]').forEach((link) => {
        link.href = new URL("../pages/logout.php", mainScript.src);
        link.textContent = "Log Out";
      });
    })
    .catch(() => {
      document.body.dataset.authenticated = "false";
    });

  // PROFILE для всех вошедших и ADMIN только для администратора
  function addAccountLinks(menu, user) {
    const links = [["PROFILE", "../pages/profile.php"]];

    if (user.role === "admin") {
      links.push(["ADMIN", "../pages/admin.php"]);
    }

    links.forEach(([label, path], index) => {
      const item = document.createElement("li");
      const link = document.createElement("a");
      link.href = new URL(path, mainScript.src);
      link.textContent = label;
      item.classList.add("auth-menu-link");

      if (index === 0) {
        item.classList.add("auth-menu-start");
      }

      // Подсвечиваем текущую страницу, как остальные пункты меню
      if (link.pathname === window.location.pathname) {
        link.classList.add("active");
        link.setAttribute("aria-current", "page");
      }

      item.append(link);
      menu.append(item);
    });
  }
}

if (heroSection) {
  const slides = heroSection.querySelectorAll(".hero > li");
  const dots = heroSection.querySelectorAll(
    ".next-main-slider-dots > span"
  );

  const interval = 4000;
  let activeSlide = 0;

  function showSlide() {
    slides.forEach((slide, index) => {
      slide.style.display =
        index === activeSlide ? "block" : "none";
    });

    dots.forEach((dot, index) => {
      dot.classList.toggle("active", index === activeSlide);
    });
  }

  // Показываем первый слайд
  showSlide();

  // Автоматическое переключение
  setInterval(() => {
    activeSlide = (activeSlide + 1) % slides.length;
    showSlide();
  }, interval);

  // Переключение по точкам
  dots.forEach((dot, index) => {
    dot.addEventListener("click", () => {
      activeSlide = index;
      showSlide();
    });
  });

  // Затемнение изображения и шапки при прокрутке
  const checkpoint = 600;

  window.addEventListener("scroll", () => {
    const currentScroll = window.scrollY;
    const header = document.querySelector(".header-nav");

    let background = "transparent";
    let opacity = 1;

    if (currentScroll <= checkpoint) {
      opacity = 1 - currentScroll / checkpoint;
    } else {
      background = "#000";
      opacity = 0;
    }

    if (header) {
      header.style.background = background;
    }

    slides.forEach((slide) => {
      const image = slide.querySelector("img");

      if (image) {
        image.style.opacity = String(opacity);
      }
    });
  });
}

// Мобильное меню
const menuButton = document.querySelector(".mobile-btn");
const submenu = document.querySelector(".second-menu-mobile");

if (menuButton && submenu) {
  menuButton.addEventListener("click", () => {
    const isOpen = submenu.style.display === "block";
    submenu.style.display = isOpen ? "none" : "block";
  });
}

const bookingForm = document.querySelector(".booking-panel form");

if (bookingForm) {
  const sessionSelect = bookingForm.querySelector("select[name='session']");
  const dateInput = bookingForm.querySelector("input[name='date']");
  const dbBookedSeats = JSON.parse(bookingForm.dataset.bookedDb || "{}");
  const seatLabels = bookingForm.querySelectorAll(".seat-grid label[data-seat]");
  const loginLink = document.querySelector("a[href$='login.html']");
  const registerLink = document.querySelector("a[href$='register.html']");

  function isVisible(element) {
    return element && element.offsetParent !== null;
  }

  function updateSeats() {
    const dbKey = `${dateInput.value}|${sessionSelect.value}`;
    const booked = (sessionSelect.selectedOptions[0].dataset.booked || "")
      .split(",")
      .filter(Boolean)
      .concat((dbBookedSeats[dbKey] || []).map(String));

    seatLabels.forEach((label) => {
      const input = label.querySelector("input");
      const occupied = booked.includes(label.dataset.seat);
      label.classList.toggle("occupied", occupied);
      input.disabled = occupied;
      if (occupied) {
        input.checked = false;
      }
    });
  }

  sessionSelect.addEventListener("change", updateSeats);
  dateInput.addEventListener("change", updateSeats);
  updateSeats();

  bookingForm.addEventListener("submit", (event) => {
    if (document.body.dataset.authenticated !== "true" || isVisible(loginLink) || isVisible(registerLink)) {
      event.preventDefault();
      alert("Please log in or register before booking seats.");
    }
  });
}
