const heroSection = document.querySelector("#heroSection");

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
  const seatLabels = bookingForm.querySelectorAll(".seat-grid label[data-seat]");

  function updateSeats() {
    const booked = (sessionSelect.selectedOptions[0].dataset.booked || "")
      .split(",")
      .filter(Boolean);

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
  updateSeats();
}