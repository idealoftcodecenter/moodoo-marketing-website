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

    $("[data-contact-form]").each(function () {
        const CONTACT_FIELDS = ["full_name", "company_name", "designation", "email", "phone", "message"];
        const $form = $(this);
        const $submitButton = $form.find("[data-contact-submit]").first();
        const $feedback = $form.find("[data-contact-feedback]").first();

        function normalizeSingleLine(value) {
            return String(value || "")
                .replace(/\s+/g, " ")
                .trim();
        }

        function normalizeMessage(value) {
            return String(value || "")
                .replace(/\r\n/g, "\n")
                .replace(/\r/g, "\n")
                .trim();
        }

        function setContactFeedback(message, type) {
            $feedback.removeClass("is-success is-error");
            if (!message) {
                $feedback.text("");
                return;
            }

            $feedback.text(message);
            if (type === "success") {
                $feedback.addClass("is-success");
            } else if (type === "error") {
                $feedback.addClass("is-error");
            }
        }

        function setFieldError(fieldName, message) {
            const $input = $form.find(`[name="${fieldName}"]`).first();
            const $error = $form.find(`[data-field-error="${fieldName}"]`).first();
            $input.attr("aria-invalid", message ? "true" : "false");
            $error.text(message || "");
        }

        function clearAllFieldErrors() {
            CONTACT_FIELDS.forEach(function (fieldName) {
                setFieldError(fieldName, "");
            });
        }

        function collectValues() {
            return {
                full_name: normalizeSingleLine($form.find('[name="full_name"]').val()),
                company_name: normalizeSingleLine($form.find('[name="company_name"]').val()),
                designation: normalizeSingleLine($form.find('[name="designation"]').val()),
                email: normalizeSingleLine($form.find('[name="email"]').val()),
                phone: normalizeSingleLine($form.find('[name="phone"]').val()),
                message: normalizeMessage($form.find('[name="message"]').val()),
            };
        }

        function validate(values) {
            const errors = {};
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const phonePattern = /^\+?[0-9()\s.-]{7,22}$/;
            const phoneDigits = values.phone.replace(/\D/g, "");

            if (values.full_name.length < 2 || values.full_name.length > 80) {
                errors.full_name = "Enter your full name (2-80 characters).";
            }

            if (values.company_name && (values.company_name.length < 2 || values.company_name.length > 120)) {
                errors.company_name = "Enter a valid company name (2-120 characters).";
            }

            if (values.designation && (values.designation.length < 2 || values.designation.length > 80)) {
                errors.designation = "Enter a valid designation/role.";
            }

            if (!emailPattern.test(values.email)) {
                errors.email = "Enter a valid email address.";
            }

            if (!phonePattern.test(values.phone) || phoneDigits.length < 7 || phoneDigits.length > 15) {
                errors.phone = "Enter a valid phone number.";
            }

            if (values.message.length < 10 || values.message.length > 1500) {
                errors.message = "Enter a message between 10 and 1500 characters.";
            }

            return errors;
        }

        CONTACT_FIELDS.forEach(function (fieldName) {
            const eventName = fieldName === "designation" ? "change" : "input";
            $form.find(`[name="${fieldName}"]`).on(`${eventName} blur`, function () {
                setFieldError(fieldName, "");
            });
        });

        $form.on("submit", function (event) {
            event.preventDefault();
            setContactFeedback("", "");
            clearAllFieldErrors();

            const values = collectValues();
            const validationErrors = validate(values);

            if (Object.keys(validationErrors).length > 0) {
                Object.entries(validationErrors).forEach(function ([fieldName, message]) {
                    setFieldError(fieldName, message);
                });
                setContactFeedback("Please fix the highlighted fields and try again.", "error");
                return;
            }

            $submitButton.addClass("is-loading").prop("disabled", true).text("Sending...");

            $.ajax({
                url: $form.attr("action") || "./contact-submit.php",
                method: "POST",
                data: $form.serialize(),
                dataType: "json",
                timeout: 15000,
            })
                .done(function (response) {
                    if (response && response.ok) {
                        setContactFeedback(response.message || "Message sent successfully.", "success");
                        $form.get(0).reset();
                        clearAllFieldErrors();
                        return;
                    }

                    if (response && response.errors) {
                        Object.entries(response.errors).forEach(function ([fieldName, message]) {
                            setFieldError(fieldName, String(message || ""));
                        });
                    }
                    setContactFeedback(
                        (response && response.message) || "We could not send your message right now.",
                        "error",
                    );
                })
                .fail(function (xhr) {
                    const response = xhr.responseJSON || {};
                    if (response.errors) {
                        Object.entries(response.errors).forEach(function ([fieldName, message]) {
                            setFieldError(fieldName, String(message || ""));
                        });
                    }

                    setContactFeedback(
                        response.message || "Something went wrong while sending your message. Please try again.",
                        "error",
                    );
                })
                .always(function () {
                    $submitButton.removeClass("is-loading").prop("disabled", false).text("Send Message");
                });
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
