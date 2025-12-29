document.addEventListener("DOMContentLoaded", function () {
  var $ = jQuery;

  /* -------------------------------------------------------
   * Detect taxonomy page and lock directory_category filter
   * ------------------------------------------------------- */
  if (window.location.pathname.includes("directory-category")) {
    var urlSegments = window.location.pathname.split("/").filter(Boolean);
    var termSlug = urlSegments[urlSegments.length - 1];

    var dirFilter = document.querySelector(
      '.taxonomy-filter[data-taxonomy="directory_category"]'
    );

    if (dirFilter) {
      // Find matching option by slug (stored in data attribute)
      dirFilter.querySelectorAll("option").forEach(function (option) {
        if (
          option.textContent.toLowerCase().replace(/\s+/g, "-") === termSlug
        ) {
          option.selected = true;
        }
      });

      dirFilter.disabled = true;
    }
  }

  /* -------------------------------------------------------
   * Initialize Select2 (AFTER locking logic)
   * ------------------------------------------------------- */
  $(".taxonomy-filter").select2({
    width: "100%",
    placeholder: function () {
      return $(this).find("option:first").text();
    },
    allowClear: true,
    minimumResultsForSearch: 0,
  });

  /* -------------------------------------------------------
   * Main filter application logic
   * ------------------------------------------------------- */
  function applyFilters() {
    var filters = {};

    $(".taxonomy-filter").each(function () {
      if (!this.disabled && this.value) {
        filters[this.getAttribute("data-taxonomy")] = this.value;
      }
    });

    var searchTerm = $("#search-title").val().toLowerCase();
    var visibleCount = 0;

    $(".custom-loop-grid .grid-item").each(function () {
      var match = true;

      for (var taxonomy in filters) {
        var selectedTermId = filters[taxonomy];
        var itemTermIds = ($(this).data(taxonomy) + "").split(",");

        if (!itemTermIds.includes(selectedTermId)) {
          match = false;
        }
      }

      var title = $(this).find("h3").text().toLowerCase();
      if (searchTerm && !title.includes(searchTerm)) {
        match = false;
      }

      $(this).toggle(match);
      if (match) visibleCount++;
    });

    $("#customNoResults").toggle(visibleCount === 0);
  }

  /* -------------------------------------------------------
   * Event listeners
   * ------------------------------------------------------- */
  $(".taxonomy-filter").on("change", applyFilters);
  $("#search-title").on("input", applyFilters);

  /* -------------------------------------------------------
   * CLEAR BUTTON
   * ------------------------------------------------------- */
  $(".filter-clear").on("click", function () {
    $("#search-title").val("");

    $(".taxonomy-filter").each(function () {
      if (!this.disabled) {
        $(this).val(null).trigger("change");
      }
    });

    $(".custom-loop-grid .grid-item").show();
    $("#customNoResults").hide();
  });

  /* -------------------------------------------------------
   * Initial state
   * ------------------------------------------------------- */
  applyFilters();
});
