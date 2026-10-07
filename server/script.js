(function () {
  // --- 1. Mobile Sidebar Navigation Drawer & Backdrop ---
  function initSidebar() {
    const menuToggle = document.querySelector(".menu-toggle");
    const sidebar = document.querySelector("#sidebar");
    if (!menuToggle || !sidebar) return;

    // Ensure backdrop element exists in the DOM for mobile screens
    let backdrop = document.querySelector(".sidebar-backdrop");
    if (!backdrop) {
      backdrop = document.createElement("div");
      backdrop.className = "sidebar-backdrop";
      backdrop.setAttribute("aria-hidden", "true");
      document.body.appendChild(backdrop);
    }

    function setSidebarOpen(open) {
      const willBeOpen = typeof open === "boolean" ? open : !sidebar.classList.contains("is-open");
      sidebar.classList.toggle("is-open", willBeOpen);
      backdrop.classList.toggle("is-active", willBeOpen);
      menuToggle.setAttribute("aria-expanded", String(willBeOpen));

      // Lock body scroll on mobile phones when sidebar drawer is open
      if (willBeOpen && window.innerWidth <= 768) {
        document.body.style.overflow = "hidden";
      } else {
        document.body.style.overflow = "";
      }
    }

    menuToggle.addEventListener("click", (e) => {
      e.stopPropagation();
      setSidebarOpen();
    });

    backdrop.addEventListener("click", () => setSidebarOpen(false));

    // Close when tapping Escape
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && sidebar.classList.contains("is-open")) {
        setSidebarOpen(false);
      }
    });

    // Close drawer when clicking any link inside sidebar on mobile
    sidebar.querySelectorAll(".nav-link, .logout-link").forEach((link) => {
      link.addEventListener("click", () => {
        if (window.innerWidth <= 768) {
          setSidebarOpen(false);
        }
      });
    });
  }

  // --- 2. Responsive Card Tables (Auto data-label injector) ---
  function initResponsiveTables() {
    const tables = document.querySelectorAll("table.records-table");
    tables.forEach((table) => {
      const headerCells = table.querySelectorAll("thead th");
      const headers = Array.from(headerCells).map((th) => th.textContent.trim());
      if (!headers.length) return;

      const rows = table.querySelectorAll("tbody tr");
      rows.forEach((row) => {
        const cells = row.querySelectorAll("td");
        // Skip empty state row spanning entire table
        if (cells.length === 1 && (cells[0].hasAttribute("colspan") || cells[0].classList.contains("empty-state"))) {
          return;
        }

        cells.forEach((td, idx) => {
          if (!td.getAttribute("data-label") && headers[idx]) {
            td.setAttribute("data-label", headers[idx]);
          }
        });
      });
    });
  }
  window.initResponsiveTables = initResponsiveTables;

  // --- 2. Fullscreen Circular Loader ---
  let loaderEl = null;
  let loaderTextEl = null;
  let activeProcesses = 0;
  let safetyTimeout = null;

  function ensureLoaderElement() {
    loaderEl = document.getElementById("page-loader");
    if (!loaderEl) {
      loaderEl = document.createElement("div");
      loaderEl.id = "page-loader";
      loaderEl.className = "page-loader is-ready";
      loaderEl.setAttribute("role", "status");
      loaderEl.setAttribute("aria-live", "polite");
      loaderEl.innerHTML = `
        <div class="page-loader-card">
          <div class="page-loader-spinner" aria-hidden="true"></div>
          <span class="page-loader-text" id="page-loader-text">Loading...</span>
        </div>
      `;
      if (document.body) {
        document.body.appendChild(loaderEl);
      }
    }

    loaderTextEl = document.getElementById("page-loader-text");
    if (!loaderTextEl && loaderEl) {
      loaderTextEl = loaderEl.querySelector(".page-loader-text") || loaderEl.querySelector("span:not(.page-loader-spinner)");
    }
  }

  function showGlobalLoader(message) {
    if (!loaderEl) {
      ensureLoaderElement();
    }
    if (!loaderEl) return;

    if (loaderTextEl && message) {
      loaderTextEl.textContent = message;
    }
    loaderEl.classList.remove("is-ready");

    // Safety timeout: auto-hide after 12 seconds so user is never stuck on broken requests
    if (safetyTimeout) {
      clearTimeout(safetyTimeout);
    }
    safetyTimeout = setTimeout(() => {
      hideGlobalLoader(true);
    }, 12000);
  }

  function hideGlobalLoader(force) {
    if (force) {
      activeProcesses = 0;
    } else {
      activeProcesses = Math.max(0, activeProcesses - 1);
    }

    if (activeProcesses === 0 && loaderEl) {
      loaderEl.classList.add("is-ready");
      if (safetyTimeout) {
        clearTimeout(safetyTimeout);
        safetyTimeout = null;
      }
    }
  }

  // Expose global helpers
  window.showGlobalLoader = function (msg = "Processing...") {
    activeProcesses++;
    showGlobalLoader(msg);
  };
  window.hideGlobalLoader = function (force = false) {
    hideGlobalLoader(force);
  };

  // --- 3. Intercept Fetch for All Background API Processes ---
  if (typeof window.fetch === "function") {
    const originalFetch = window.fetch;
    window.fetch = async function (...args) {
      const options = args[1] || {};
      const isSilent = Boolean(options.silent || (options.headers && options.headers["X-Silent-Loader"]));

      if (!isSilent) {
        window.showGlobalLoader("Processing request...");
      }

      try {
        const response = await originalFetch.apply(this, args);
        return response;
      } finally {
        if (!isSilent) {
          window.hideGlobalLoader();
        }
      }
    };
  }

  // --- 4. Intercept Form Submissions ---
  document.addEventListener("submit", function (e) {
    const form = e.target;
    if (!form || form.tagName !== "FORM") return;

    // Check HTML5 validity if available
    if (typeof form.checkValidity === "function" && !form.checkValidity()) {
      return;
    }

    const customText = form.getAttribute("data-loading-text") || "Saving changes...";
    window.showGlobalLoader(customText);
  }, true);

  // --- 5. Intercept Navigation Link Clicks ---
  document.addEventListener("click", function (e) {
    // Only primary left clicks without modifier keys
    if (e.button !== 0 || e.ctrlKey || e.shiftKey || e.altKey || e.metaKey) return;

    const link = e.target.closest("a");
    if (!link) return;

    const href = link.getAttribute("href");
    if (!href) return;

    // Ignore anchors, javascript, mailto, tel
    if (
      href === "#" ||
      href.startsWith("#") ||
      href.startsWith("javascript:") ||
      href.startsWith("mailto:") ||
      href.startsWith("tel:")
    ) {
      return;
    }

    // Ignore new tabs or download files
    if (link.target === "_blank" || link.hasAttribute("download")) {
      return;
    }

    // Ignore links marked explicitly as no-loader or buttons
    if (
      link.dataset.noLoader === "true" ||
      link.getAttribute("role") === "button" ||
      link.classList.contains("no-loader")
    ) {
      return;
    }

    // Show navigation loader
    window.showGlobalLoader("Loading page...");
  }, true);

  // --- 5. Modal Backdrop & Accessibility Handler ---
  function initModals() {
    // Backdrop click-to-close on all modals
    document.querySelectorAll(".record-modal").forEach((modal) => {
      modal.addEventListener("click", (e) => {
        if (e.target === modal) {
          modal.hidden = true;
          document.body.style.overflow = "";
        }
      });
    });

    // Close open modal on Escape key
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        document.querySelectorAll(".record-modal:not([hidden])").forEach((modal) => {
          modal.hidden = true;
          document.body.style.overflow = "";
        });
      }
    });
  }

  // --- 6. Lifecycle & Safety Event Listeners ---
  function init() {
    initSidebar();
    initResponsiveTables();
    initModals();
    ensureLoaderElement();
    window.hideGlobalLoader(true);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  // Ensure loader is hidden when full page finishes loading
  window.addEventListener("load", () => {
    window.hideGlobalLoader(true);
  });

  // Handle browser back/forward cache (bfcache)
  window.addEventListener("pageshow", () => {
    window.hideGlobalLoader(true);
  });

  // Allow Escape key to dismiss if user feels stuck
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && loaderEl && !loaderEl.classList.contains("is-ready")) {
      window.hideGlobalLoader(true);
    }
  });
})();
