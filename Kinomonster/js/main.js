const heroSection = document.querySelector("#heroSection");

const authStatusUrl = window.location.pathname.includes("/pages/")
  ? "auth-status.php"
  : "pages/auth-status.php";

function accountLinks(user, inPagesDirectory) {
  const prefix = inPagesDirectory ? "" : "pages/";
  const links = [];

  links.push({
    label: "PROFILE",
    href: `${prefix}profile.php`,
  });

  if (user.role === "admin") {
    links.push({
      label: "ADMIN",
      href: `${prefix}admin.php`,
    });
  }

  links.push({
    label: "LOG OUT",
    href: `${prefix}logout.php`,
  });

  return links;
}

function updateAuthMenus(status) {
  const inPagesDirectory = window.location.pathname.includes("/pages/");
  const desktopMenus = document.querySelectorAll("ul.second-menu");
  const mobileMenus = document.querySelectorAll("ul.second-menu-mobile");

  desktopMenus.forEach((menu) => {
    menu.classList.toggle("authenticated-menu", status.authenticated);
  });

  [desktopMenus, mobileMenus].forEach((menus) => {
    menus.forEach((menu) => {
      const items = Array.from(menu.children);

      if (status.authenticated) {
        while (items.length > 2) {
          items.pop().remove();
        }

        accountLinks(status.user, inPagesDirectory).forEach((link, index) => {
          const item = document.createElement("li");
          if (menu.classList.contains("second-menu")) {
            item.className =
              index === 0 ? "auth-menu-start auth-menu-item" : "auth-menu-item";
          }
          const anchor = document.createElement("a");
          anchor.href = link.href;
          anchor.textContent = link.label;
          item.appendChild(anchor);
          menu.appendChild(item);
        });
      } else {
        while (items.length > 2) {
          items.pop().remove();
        }

        const prefix = inPagesDirectory ? "../" : "";
        [["LOG IN", `${prefix}login.html`], ["REGISTER", `${prefix}register.html`]]
          .forEach(([label, href]) => {
            const item = document.createElement("li");
            const anchor = document.createElement("a");
            anchor.href = href;
            anchor.textContent = label;
            item.appendChild(anchor);
            menu.appendChild(item);
          });
      }
    });
  });

  document.body.classList.add("auth-status-ready");
}

fetch(authStatusUrl, {
  headers: { Accept: "application/json" },
  cache: "no-store",
})
  .then((response) => {
    if (!response.ok) {
      throw new Error("Unable to load authentication status.");
    }
    return response.json();
  })
  .then(updateAuthMenus)
  .catch(() => {
    document.body.classList.add("auth-status-ready");
  });

if (heroSection) {
  const slides = heroSection.querySelectorAll(".hero > li");
  const dots = heroSection.querySelectorAll(
    ".next-main-slider-dots > span"
  );

  const interval = 4000;
  let activeSlide = 0;

  function showSlide() {
    slides.forEach((slide, index) => {
      slide.classList.toggle("active", index === activeSlide);
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
  const sessionSelect = bookingForm.querySelector(
    "select[name='showtime_id'], select[name='session']"
  );
  const seatLabels = bookingForm.querySelectorAll(".seat-grid label[data-seat]");

  function updateSeats() {
    if (!sessionSelect) {
      return;
    }

    const booked = (sessionSelect.selectedOptions[0].dataset.booked || "")
      .split(",")
      .filter(Boolean);
    const capacity = Number(
      sessionSelect.selectedOptions[0].dataset.capacity || 24
    );

    seatLabels.forEach((label) => {
      const input = label.querySelector("input");
      const seatNumber = Number(label.dataset.seat);
      const occupied = seatNumber > capacity || booked.includes(label.dataset.seat);
      label.classList.toggle("occupied", occupied);
      input.disabled = occupied;
      if (occupied) {
        input.checked = false;
      }
    });
  }

  if (sessionSelect) {
    sessionSelect.addEventListener("change", updateSeats);
  }
  updateSeats();
}