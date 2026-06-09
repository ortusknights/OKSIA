/**
 * OKSIA Frontend JavaScript
 * Main scripts for all frontend components
 *
 * @package OKSIA
 */

(function ($) {
  "use strict";

  /**
   * Initialize all frontend components
   */
  $(document).ready(function () {
    initAjaxForms();
    initConfirmDialogs();
    initTooltips();
    initModalHandlers();
    initCopyToClipboard();
  });

  /**
   * Initialize AJAX form submissions
   */
  function initAjaxForms() {
    $(".oksia-ajax-form").on("submit", function (e) {
      e.preventDefault();

      const $form = $(this);
      const $submitBtn = $form.find('[type="submit"]');
      const $loading = $form.find(".oksia-loading");
      const $message = $form.find(".oksia-form-message");

      // Disable submit button and show loading
      $submitBtn.prop("disabled", true);
      if ($loading.length) {
        $loading.show();
      }

      // Clear previous messages
      $message.removeClass("success error").hide();

      // Get form data
      const formData = new FormData(this);
      formData.append("action", $form.data("action") || "oksia_submit_form");
      formData.append("nonce", oksia_data.nonce);

      // Submit via AJAX
      $.ajax({
        url: oksia_data.ajax_url,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
        success: function (response) {
          if (response.success) {
            // Show success message
            if ($message.length) {
              $message
                .addClass("success")
                .html(response.data.message || oksia_data.strings.success)
                .show();
            }

            // Reset form on success
            if ($form.data("reset-on-success")) {
              $form[0].reset();
            }

            // Trigger custom event
            $(document).trigger("oksia_form_success", [$form, response]);

            // Redirect if specified
            if (response.data.redirect) {
              setTimeout(function () {
                window.location.href = response.data.redirect;
              }, 1500);
            }
          } else {
            // Show error message
            if ($message.length) {
              $message
                .addClass("error")
                .html(response.data.message || oksia_data.strings.error)
                .show();
            }

            // Trigger custom event
            $(document).trigger("oksia_form_error", [$form, response]);
          }
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error:", error);
          if ($message.length) {
            $message.addClass("error").html(oksia_data.strings.error).show();
          }
        },
        complete: function () {
          $submitBtn.prop("disabled", false);
          if ($loading.length) {
            $loading.hide();
          }
        },
      });
    });
  }

  /**
   * Initialize confirmation dialogs for delete actions
   */
  function initConfirmDialogs() {
    $(document).on("click", ".oksia-confirm-delete", function (e) {
      const message =
        $(this).data("confirm-message") || oksia_data.strings.confirm_delete;
      if (!confirm(message)) {
        e.preventDefault();
        return false;
      }
    });

    $(document).on("click", ".oksia-confirm-action", function (e) {
      const message =
        $(this).data("confirm-message") ||
        "Are you sure you want to perform this action?";
      if (!confirm(message)) {
        e.preventDefault();
        return false;
      }
    });
  }

  /**
   * Initialize tooltips
   */
  function initTooltips() {
    $(document).on("mouseenter", ".oksia-tooltip", function () {
      const $this = $(this);
      const text = $this.data("tooltip");

      if (!text) return;

      const $tooltip = $('<div class="oksia-tooltip-popup">' + text + "</div>");
      $("body").append($tooltip);

      const offset = $this.offset();
      $tooltip.css({
        position: "absolute",
        top: offset.top - $tooltip.outerHeight() - 10,
        left: offset.left + $this.outerWidth() / 2 - $tooltip.outerWidth() / 2,
        zIndex: 10000,
        background: "#1e293b",
        color: "#fff",
        padding: "5px 10px",
        borderRadius: "4px",
        fontSize: "12px",
        whiteSpace: "nowrap",
      });

      $this.data("tooltip-tip", $tooltip);
    });

    $(document).on("mouseleave", ".oksia-tooltip", function () {
      const $tooltip = $(this).data("tooltip-tip");
      if ($tooltip) {
        $tooltip.remove();
      }
    });
  }

  /**
   * Initialize modal handlers
   */
  function initModalHandlers() {
    // Open modal
    $(document).on("click", "[data-modal]", function (e) {
      e.preventDefault();
      const modalId = $(this).data("modal");
      const $modal = $("#" + modalId);

      if ($modal.length) {
        $modal.addClass("active");
        $("body").css("overflow", "hidden");
      }
    });

    // Close modal
    $(document).on(
      "click",
      ".oksia-modal-close, .oksia-modal .oksia-modal-overlay",
      function () {
        const $modal = $(this).closest(".oksia-modal");
        $modal.removeClass("active");
        $("body").css("overflow", "");
      },
    );

    // Close on escape key
    $(document).on("keydown", function (e) {
      if (e.key === "Escape") {
        $(".oksia-modal.active").removeClass("active");
        $("body").css("overflow", "");
      }
    });

    // Prevent click on modal content from closing
    $(document).on("click", ".oksia-modal-content", function (e) {
      e.stopPropagation();
    });
  }

  /**
   * Initialize copy to clipboard functionality
   */
  function initCopyToClipboard() {
    $(document).on("click", ".oksia-copy", function (e) {
      e.preventDefault();

      const text = $(this).data("copy-text") || $(this).text();
      const $temp = $("<textarea>");
      $("body").append($temp);
      $temp.val(text).select();
      document.execCommand("copy");
      $temp.remove();

      // Show feedback
      const $feedback = $(this).find(".copy-feedback");
      if ($feedback.length) {
        const originalText = $feedback.text();
        $feedback.text("Copied!");
        setTimeout(function () {
          $feedback.text(originalText);
        }, 2000);
      } else {
        const $toast = $('<div class="oksia-toast">Copied!</div>');
        $("body").append($toast);
        $toast.css({
          position: "fixed",
          bottom: "20px",
          right: "20px",
          background: "#10b981",
          color: "#fff",
          padding: "10px 20px",
          borderRadius: "8px",
          zIndex: 10000,
        });
        setTimeout(function () {
          $toast.fadeOut(300, function () {
            $(this).remove();
          });
        }, 2000);
      }
    });
  }

  /**
   * Show loading overlay
   */
  window.oksiaShowLoading = function (message) {
    message = message || oksia_data.strings.loading;

    const $overlay = $(
      '<div class="oksia-overlay"><div class="oksia-loading"><div class="oksia-loading-spinner"></div><span>' +
        message +
        "</span></div></div>",
    );
    $("body").append($overlay);
    return $overlay;
  };

  /**
   * Hide loading overlay
   */
  window.oksiaHideLoading = function ($overlay) {
    if ($overlay) {
      $overlay.fadeOut(300, function () {
        $(this).remove();
      });
    } else {
      $(".oksia-overlay").fadeOut(300, function () {
        $(this).remove();
      });
    }
  };

  /**
   * Show toast notification
   */
  window.oksiaShowToast = function (message, type) {
    type = type || "success";

    const colors = {
      success: "#10b981",
      error: "#ef4444",
      warning: "#f59e0b",
      info: "#3b82f6",
    };

    const $toast = $('<div class="oksia-toast">' + message + "</div>");
    $toast.css({
      position: "fixed",
      bottom: "20px",
      right: "20px",
      background: colors[type] || colors.success,
      color: "#fff",
      padding: "12px 24px",
      borderRadius: "8px",
      zIndex: 10000,
      boxShadow: "0 4px 6px -1px rgba(0, 0, 0, 0.1)",
    });

    $("body").append($toast);

    setTimeout(function () {
      $toast.fadeOut(300, function () {
        $(this).remove();
      });
    }, 3000);
  };

  /**
   * Debounce function for search inputs
   */
  window.oksiaDebounce = function (func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  };

  /**
   * Initialize search inputs with debounce
   */
  $(document).on(
    "input",
    ".oksia-search-input",
    window.oksiaDebounce(function () {
      const $input = $(this);
      const searchValue = $input.val();
      const target = $input.data("search-target");

      if (target) {
        $(target)
          .find(".searchable-item")
          .each(function () {
            const text = $(this).text().toLowerCase();
            if (text.indexOf(searchValue.toLowerCase()) === -1) {
              $(this).hide();
            } else {
              $(this).show();
            }
          });
      }
    }, 300),
  );

  /**
   * Initialize tab navigation
   */
  $(document).on("click", ".oksia-tab", function (e) {
    e.preventDefault();

    const $tab = $(this);
    const tabId = $tab.data("tab");
    const container = $tab.closest(".oksia-tabs-container");

    if (!container.length) return;

    // Update active tab
    container.find(".oksia-tab").removeClass("active");
    $tab.addClass("active");

    // Show active content
    container.find(".oksia-tab-content").removeClass("active");
    $("#" + tabId).addClass("active");
  });

  /**
   * Initialize accordion
   */
  $(document).on("click", ".oksia-accordion-header", function () {
    const $header = $(this);
    const $content = $header.next(".oksia-accordion-content");
    const $container = $header.closest(".oksia-accordion");

    if ($container.data("multiple") !== true) {
      $container.find(".oksia-accordion-content").slideUp();
      $container.find(".oksia-accordion-header").removeClass("active");
    }

    $content.slideToggle();
    $header.toggleClass("active");
  });

  /**
   * Initialize date pickers
   */
  if (typeof $.fn.datepicker !== "undefined") {
    $(".oksia-datepicker").datepicker({
      dateFormat: "yy-mm-dd",
      changeMonth: true,
      changeYear: true,
    });
  }

  /**
   * Initialize select2 for better dropdowns
   */
  if (typeof $.fn.select2 !== "undefined") {
    $(".oksia-select2").select2({
      width: "100%",
      placeholder: "Select an option",
    });
  }
})(jQuery);
