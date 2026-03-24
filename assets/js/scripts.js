document.addEventListener("DOMContentLoaded", function () {
  var $ = jQuery;

  // Only run if the grid exists on this page
  if (!document.getElementById("customLoopGrid")) return;

  var cfg = window.clgConfig || {};
  var ajaxUrl = cfg.ajaxUrl || "";
  var nonce = cfg.nonce || "";
  var postsPerPage = cfg.postsPerPage || 9;
  var isTaxPage = cfg.isTaxPage || 0;
  var lockedTermId = cfg.termId || 0;

  var isFiltering = false; // true when any filter/search is active
  var debounceTimer = null;

  /* -------------------------------------------------------
   * Initialize Select2 (non-location dropdowns only)
   * ------------------------------------------------------- */
  $(".taxonomy-filter:not([data-taxonomy='location'])").select2({
    width: "100%",
    placeholder: function () {
      return $(this).find("option:first").text();
    },
    allowClear: true,
    minimumResultsForSearch: 0,
  });

  /* -------------------------------------------------------
   * Lock directory_category filter on taxonomy archive pages
   * ------------------------------------------------------- */
  if (isTaxPage && lockedTermId) {
    var dirFilter = document.querySelector(
      '.taxonomy-filter[data-taxonomy="directory_category"]'
    );
    if (dirFilter) {
      dirFilter.querySelectorAll("option").forEach(function (option) {
        if (option.value == lockedTermId) {
          option.selected = true;
        }
      });
      dirFilter.disabled = true;
      // Refresh Select2 to reflect the forced value
      $(dirFilter).trigger("change.select2");
    }
  }

  /* -------------------------------------------------------
   * Collect current filter state
   * ------------------------------------------------------- */
  function getFilters() {
    var taxonomyFilters = {};
    $(".taxonomy-filter").each(function () {
      if (!this.disabled && this.value) {
        taxonomyFilters[this.getAttribute("data-taxonomy")] = this.value;
      }
    });

    return {
      titleSearch: $("#search-title").val().trim(),
      locationSearch: $("#location-search").val().trim(),
      taxonomyFilters: taxonomyFilters,
    };
  }

  function hasActiveFilters(filters) {
    return (
      filters.titleSearch !== "" ||
      filters.locationSearch !== "" ||
      Object.keys(filters.taxonomyFilters).length > 0
    );
  }

  /* -------------------------------------------------------
   * Show / hide clear button
   * ------------------------------------------------------- */
  function updateClearButton(filters) {
    if (hasActiveFilters(filters)) {
      $(".filter-clear").css("display", "inline-block");
    } else {
      $(".filter-clear").css("display", "none");
    }
  }

  /* -------------------------------------------------------
   * AJAX request to server
   * ------------------------------------------------------- */
  function doAjaxFilter(paged) {
    paged = paged || 1;
    var filters = getFilters();

    updateClearButton(filters);

    isFiltering = hasActiveFilters(filters);

    if (!isFiltering) {
      // No active filters — restore the original server-rendered grid
      // by reloading to page 1 without query params, OR just re-run
      // with paged=1 and empty filters to get the default set
    }

    var $grid = $("#customLoopGrid");
    var $noResults = $("#customNoResults");
    var $pagination = $("#customPagination");

    // Loading state
    $grid.css("opacity", "0.4");
    $noResults.hide();

    $.ajax({
      url: ajaxUrl,
      type: "POST",
      data: {
        action: "clg_filter",
        nonce: nonce,
        posts_per_page: postsPerPage,
        paged: paged,
        title_search: filters.titleSearch,
        location_search: filters.locationSearch,
        taxonomy_filters: filters.taxonomyFilters,
        locked_term_id: lockedTermId,
      },
      success: function (response) {
        $grid.css("opacity", "1");

        if (response.success) {
          var data = response.data;

          if (data.html) {
            $grid.html(data.html).show();
            $noResults.hide();
          } else {
            $grid.html("").hide();
            $noResults.show();
          }

          // Update pagination
          if (data.pagination) {
            $pagination.html(data.pagination).show();
            bindAjaxPagination();
          } else {
            $pagination.html("").hide();
          }

          // Scroll to top of grid smoothly
          if (paged > 1) {
            $("html, body").animate(
              { scrollTop: $grid.offset().top - 80 },
              400
            );
          }
        }
      },
      error: function () {
        $grid.css("opacity", "1");
      },
    });
  }

  /* -------------------------------------------------------
   * Hijack pagination links to use AJAX
   * ------------------------------------------------------- */
  function bindAjaxPagination() {
    $("#customPagination a").off("click.clgPager").on("click.clgPager", function (e) {
      e.preventDefault();
      var href = $(this).attr("href");
      // Extract page number from WP paginate_links URL
      var match = href.match(/\/page\/(\d+)/);
      var pageNum = match ? parseInt(match[1], 10) : 1;
      doAjaxFilter(pageNum);
    });
  }

  // Bind pagination on initial load too (for the server-rendered pagination)
  bindAjaxPagination();

  /* -------------------------------------------------------
   * Debounced filter trigger
   * ------------------------------------------------------- */
  function scheduleFilter() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function () {
      doAjaxFilter(1);
    }, 350);
  }

  /* -------------------------------------------------------
   * Event listeners
   * ------------------------------------------------------- */
  $(".taxonomy-filter").on("change", function () {
    doAjaxFilter(1);
  });

  $("#search-title").on("input", scheduleFilter);
  $("#location-search").on("input", scheduleFilter);

  /* -------------------------------------------------------
   * Clear button
   * ------------------------------------------------------- */
  $(".filter-clear").on("click", function () {
    $("#search-title").val("");
    $("#location-search").val("");

    $(".taxonomy-filter").each(function () {
      if (!this.disabled) {
        $(this).val(null).trigger("change"); // triggers Select2 update + our handler
      }
    });

    // If no change events fired (all were already empty), run manually
    doAjaxFilter(1);
  });
});
