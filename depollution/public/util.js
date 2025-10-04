// util.js - helpers + theme + nav + PDF chart embedding prep
(function () {
  const root = document.documentElement;
  const themeToggle = document.getElementById("themeToggle");
  const iconSun = document.getElementById("themeIconSun");
  const iconMoon = document.getElementById("themeIconMoon");
  function applyTheme(t) {
    if (t === "dark") {
      root.classList.add("dark");
      iconSun.classList.remove("hidden");
      iconMoon.classList.add("hidden");
    } else {
      root.classList.remove("dark");
      iconSun.classList.add("hidden");
      iconMoon.classList.remove("hidden");
    }
  }
  const saved = localStorage.getItem("theme");
  applyTheme(
    saved ||
      (matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light")
  );
  if (themeToggle) {
    themeToggle.addEventListener("click", () => {
      const now = root.classList.contains("dark") ? "light" : "dark";
      localStorage.setItem("theme", now);
      applyTheme(now);
    });
  }
  const hamb = document.getElementById("hamburgerBtn");
  const mobileNav = document.getElementById("mobileNav");
  if (hamb && mobileNav) {
    hamb.addEventListener("click", () => mobileNav.classList.toggle("hidden"));
  }
})();

// Format helpers
function formatNumber(n, dec = 0) {
  if (n === null || n === undefined || isNaN(n)) return "-";
  return n.toLocaleString("fr-FR", {
    minimumFractionDigits: dec,
    maximumFractionDigits: dec,
  });
}
function formatMoney(n) {
  if (n === null || n === undefined || isNaN(n)) return "-";
  return n.toLocaleString("fr-FR", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
}

// Export charts to base64 for PDF embedding: returns mapping id->dataURL
function collectChartImages() {
  if (!window.Chart) return {};
  const out = {};
  Chart.helpers.each(Chart.instances, function (inst) {
    try {
      out[inst.canvas.id] = inst.toBase64Image("image/png", 1);
    } catch (e) {}
  });
  return out;
}

// Trigger PDF export with embedded charts if endpoint supports it
async function exportPdfWithCharts(endpoint) {
  const charts = collectChartImages();
  const formData = new FormData();
  formData.append("charts_json", JSON.stringify(charts));
  // include current query params
  const params = new URLSearchParams(window.location.search);
  formData.append("filters", params.toString());
  const r = await fetch(endpoint, { method: "POST", body: formData });
  if (!r.ok) {
    alert("Echec génération PDF");
    return;
  }
  const blob = await r.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = "voyages.pdf";
  a.click();
  setTimeout(() => URL.revokeObjectURL(url), 5000);
}

window.DecapUtil = { formatNumber, formatMoney, exportPdfWithCharts };
