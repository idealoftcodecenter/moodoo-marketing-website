$(function () {
  const MOBILE_BREAKPOINT = 960;

  const $menuToggle = $("[data-menu-toggle]");
  const $mobileNav = $("[data-mobile-nav]");

  function closeMobileNav() {
    $menuToggle.attr("aria-expanded", "false");
    $mobileNav.removeClass("is-open");
  }

  if ($menuToggle.length && $mobileNav.length) {
    $menuToggle.on("click", function () {
      const isExpanded = $(this).attr("aria-expanded") === "true";
      $(this).attr("aria-expanded", String(!isExpanded));
      $mobileNav.toggleClass("is-open", !isExpanded);
    });

    $mobileNav.find("a").on("click", closeMobileNav);

    $(document).on("click", function (event) {
      const clickedInsideNav = $(event.target).closest("[data-mobile-nav], [data-menu-toggle]").length > 0;
      if (!clickedInsideNav && $mobileNav.hasClass("is-open")) {
        closeMobileNav();
      }
    });

    $(window).on("resize", function () {
      if (window.innerWidth > MOBILE_BREAKPOINT) {
        closeMobileNav();
      }
    });
  }

  $('a[href^="#"]').on("click", function (event) {
    const hash = $(this).attr("href");
    if (!hash || hash === "#") {
      return;
    }

    if (this.pathname !== window.location.pathname) {
      return;
    }

    const $target = $(hash);
    if (!$target.length) {
      return;
    }

    event.preventDefault();
    const offset = $(".site-header").outerHeight() || 0;
    const destination = Math.max(0, $target.offset().top - offset - 16);
    $("html, body").animate({ scrollTop: destination }, 280);
  });

  const currentYear = new Date().getFullYear();
  $("[data-current-year]").text(currentYear);

  $("[data-inline-waitlist]").each(function () {
    const $form = $(this);
    const $feedback = $form.parent().find("[data-inline-feedback]").first();

    $form.on("submit", function (event) {
      event.preventDefault();

      const email = String($form.find('input[name="email"]').val() || "").trim();
      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        $feedback.text("Enter a valid work email address.");
        return;
      }

      $feedback.text("Thanks. You are on the waitlist.");
      this.reset();
    });
  });

  const $modal = $("[data-enquiry-modal]");
  const $openModalButtons = $("[data-open-enquiry-modal]");
  const $closeModalButtons = $("[data-modal-close]");
  let lastFocusedElement = null;

  function getFocusableElements($scope) {
    return $scope
      .find('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])')
      .filter(":visible");
  }

  function openModal() {
    if (!$modal.length) {
      return;
    }

    lastFocusedElement = document.activeElement;
    $modal.removeAttr("hidden").addClass("is-open");
    $("body").addClass("modal-open");

    const $focusables = getFocusableElements($modal);
    if ($focusables.length) {
      $focusables.first().trigger("focus");
    }
  }

  function closeModal() {
    if (!$modal.length) {
      return;
    }

    $modal.attr("hidden", "hidden").removeClass("is-open");
    $("body").removeClass("modal-open");

    if (lastFocusedElement && typeof lastFocusedElement.focus === "function") {
      lastFocusedElement.focus();
    }
  }

  if ($modal.length) {
    $openModalButtons.on("click", openModal);
    $closeModalButtons.on("click", closeModal);

    $(document).on("keydown", function (event) {
      if (!$modal.hasClass("is-open")) {
        return;
      }

      if (event.key === "Escape") {
        closeModal();
        return;
      }

      if (event.key !== "Tab") {
        return;
      }

      const $focusables = getFocusableElements($modal);
      if (!$focusables.length) {
        return;
      }

      const first = $focusables.get(0);
      const last = $focusables.get($focusables.length - 1);

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });

    $("#enquiry-form").on("submit", function (event) {
      event.preventDefault();
      const $feedback = $("[data-modal-feedback]");
      $feedback.text("Enquiry submitted. We will contact you within 1-2 business days.");
      this.reset();
    });
  }

  const $accordion = $("[data-accordion]");
  if ($accordion.length) {
    $accordion.find(".accordion-trigger").on("click", function () {
      const $trigger = $(this);
      const panelId = $trigger.attr("aria-controls");
      const $targetPanel = $(`#${panelId}`);
      const isExpanded = $trigger.attr("aria-expanded") === "true";

      $accordion.find(".accordion-trigger").attr("aria-expanded", "false");
      $accordion.find(".accordion-panel").attr("hidden", "hidden");

      if (!isExpanded) {
        $trigger.attr("aria-expanded", "true");
        $targetPanel.removeAttr("hidden");
      }
    });
  }

  const $carousel = $("[data-carousel]");
  if ($carousel.length) {
    const $slides = $carousel.find("[data-carousel-slide]");
    const maxIndex = $slides.length - 1;
    let currentIndex = 0;

    function renderCarousel() {
      $slides.removeClass("is-active");
      $slides.eq(currentIndex).addClass("is-active");
    }

    $carousel.find("[data-carousel-prev]").on("click", function () {
      currentIndex = currentIndex <= 0 ? maxIndex : currentIndex - 1;
      renderCarousel();
    });

    $carousel.find("[data-carousel-next]").on("click", function () {
      currentIndex = currentIndex >= maxIndex ? 0 : currentIndex + 1;
      renderCarousel();
    });

    renderCarousel();
  }

  $("[data-show-features]").on("click", function () {
    $(".feature-card").removeClass("is-muted");
    $(this).attr("disabled", "disabled").text("All Features Visible");
  });
});
