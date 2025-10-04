// JS Opérateur 2 – listing + édition/clôture
const API = "api.php";
const tableBody = document.querySelector("#voyagesTable tbody");
const refreshBtn = document.getElementById("refreshBtn");
const toastEl = document.getElementById("toast");
function toast(msg, ok = true) {
  toastEl.textContent = msg;
  toastEl.style.background = ok ? "#111" : "#b91c1c";
  toastEl.hidden = false;
  setTimeout(() => (toastEl.hidden = true), 3000);
}
let prixLitre = 675;
fetch(API + "?action=configCarburant")
  .then((r) => r.json())
  .then((j) => {
    if (j.ok) prixLitre = j.prix_litre;
  });
let voyages = [];
let selectedId = null;

function load() {
  fetch(API + "?action=listVoyages&statut=SAISI&limit=300")
    .then((r) => r.json())
    .then((j) => {
      if (!j.ok) return;
      voyages = j.data;
      render();
      if (selectedId) {
        const still = voyages.find((v) => v.id == selectedId);
        if (!still) clearSelection();
      }
    });
}
function render() {
  tableBody.innerHTML = voyages
    .map(
      (v) =>
        `<tr data-id="${v.id}" class="${
          v.id == selectedId ? "selected" : ""
        }"><td>${v.id}</td><td>${v.date_voyage}</td><td>${
          v.camion_matricule
        }</td><td>${v.chauffeur}</td><td>${v.prestataire}</td><td>${Number(
          v.montant_origine
        ).toLocaleString("fr-FR")}</td><td>${Number(
          v.frais_route
        ).toLocaleString("fr-FR")}</td><td>${Number(
          v.carburant_montant
        ).toLocaleString("fr-FR")}</td><td>${Number(v.reel_recu).toLocaleString(
          "fr-FR"
        )}</td></tr>`
    )
    .join("");
}
refreshBtn.addEventListener("click", load);
setInterval(load, 15000);
load();

tableBody.addEventListener("click", (e) => {
  const tr = e.target.closest("tr");
  if (!tr) return;
  selectedId = tr.dataset.id;
  render();
  openEditor(selectedId);
});

const form = document.getElementById("formOp2");
const noSel = document.getElementById("noSelection");
const useCarb = document.getElementById("useCarb");
function clearSelection() {
  selectedId = null;
  form.hidden = true;
  noSel.style.display = "block";
}
function openEditor(id) {
  const v = voyages.find((x) => x.id == id);
  if (!v) return;
  noSel.style.display = "none";
  form.hidden = false;
  form.dataset.id = id;
  form.montant_origine.value = v.montant_origine;
  form.frais_route.value = v.frais_route || 0;
  form.carburant_litre.value = v.carburant_litre || 50;
  form.carburant_montant.value =
    v.carburant_montant || (form.carburant_litre.value || 0) * prixLitre;
  computeReel();
}

function computeReel() {
  const montantOrig = parseFloat(form.montant_origine.value) || 0;
  const fr = parseFloat(form.frais_route.value) || 0;
  const carb = parseFloat(form.carburant_montant.value) || 0;
  const reel = montantOrig - fr - carb;
  form.reel_recu.value = reel.toFixed(0);
}
form.frais_route.addEventListener("input", computeReel);
form.carburant_litre.addEventListener("input", () => {
  if (!useCarb.checked) {
    form.carburant_litre.value = 0;
    return;
  }
  const l = parseFloat(form.carburant_litre.value) || 0;
  form.carburant_montant.value = (l * prixLitre).toFixed(0);
  computeReel();
});
form.carburant_montant.addEventListener("input", () => {
  if (!useCarb.checked) {
    form.carburant_montant.value = 0;
    return;
  }
  const m = parseFloat(form.carburant_montant.value) || 0;
  form.carburant_litre.value = (m / prixLitre).toFixed(0);
  computeReel();
});
useCarb.addEventListener("change", () => {
  if (!useCarb.checked) {
    form.carburant_litre.value = 0;
    form.carburant_montant.value = 0;
  } else {
    if (!form.carburant_litre.value) form.carburant_litre.value = 50;
    form.carburant_montant.value = (
      parseFloat(form.carburant_litre.value) * prixLitre
    ).toFixed(0);
  }
  computeReel();
});

form.addEventListener("submit", (e) => {
  e.preventDefault();
  if (!selectedId) return;
  const fd = new FormData();
  fd.append("action", "updateVoyageOp2");
  fd.append("voyage_id", selectedId);
  fd.append("frais_route", form.frais_route.value || 0);
  fd.append("carburant_litre", form.carburant_litre.value || 0);
  fd.append("carburant_montant", form.carburant_montant.value || 0);
  fetch(API, { method: "POST", body: fd })
    .then((r) => r.json())
    .then((j) => {
      if (!j.ok) {
        toast(j.error || "Erreur maj", false);
        return;
      }
      toast("Voyage #" + selectedId + " clos");
      load();
      clearSelection();
    })
    .catch(() => toast("Erreur réseau", false));
});
