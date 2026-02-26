(function () {
  var STORAGE_KEY = "tabs-theme";
  var html = document.documentElement;

  function getPreferred() {
    var stored = localStorage.getItem(STORAGE_KEY);
    if (stored === "dark" || stored === "light") {
      return stored;
    }
    return window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light";
  }

  function apply(theme) {
    html.setAttribute("data-theme", theme);
    localStorage.setItem(STORAGE_KEY, theme);
  }

  apply(getPreferred());

  document.addEventListener("DOMContentLoaded", function () {
    var toggleButtons = document.querySelectorAll(".theme-toggle");
    toggleButtons.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var current = html.getAttribute("data-theme") || "light";
        apply(current === "dark" ? "light" : "dark");
      });
    });
  });
})();
