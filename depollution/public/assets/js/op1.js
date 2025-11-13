// JS Opérateur 1 – multi-étapes + intégration API
const API = "api.php";
const stepsMeta = [
  { id: 1, label: "Prestataire" },
  { id: 2, label: "Chauffeur" },
  { id: 3, label: "Camion & Bon" },
  { id: 4, label: "Récapitulatif" },
];
const form = document.getElementById("voyageForm");
const stepsBar = document.getElementById("steps");
const fieldsets = [...form.querySelectorAll("fieldset")];
let current = 0;
const toastEl = document.getElementById("toast");

function toast(msg, ok = true) {
  toastEl.textContent = msg;
  toastEl.style.background = ok ? "#111" : "#b91c1c";
  toastEl.hidden = false;
  setTimeout(() => (toastEl.hidden = true), 3500);
}

function renderSteps() {
  stepsBar.innerHTML = stepsMeta
    .map(
      (s, i) =>
        `<div class="step ${i === current ? "active" : ""}">${s.id}. ${
          s.label
        }</div>`
    )
    .join("");
}
renderSteps();

function goto(i) {
  if (i < 0 || i >= fieldsets.length) return;
  current = i;
  fieldsets.forEach((fs, idx) =>
    fs.classList.toggle("active", idx === current)
  );
  renderSteps();
  if (current === 3) buildRecap();
  // Focus auto premier input du fieldset actif
  const first = fieldsets[current].querySelector("input,select,textarea");
  if (first) setTimeout(() => first.focus(), 50);
}

form.addEventListener("click", (e) => {
  if (e.target.classList.contains("next")) goto(current + 1);
  if (e.target.classList.contains("prev")) goto(current - 1);
});

// Charger prestataires
const prestSel = document.getElementById("prestataireSelect");
const montantBox = document.getElementById("montantOrigineBox");
fetch(API + "?action=listPrestataires")
  .then((r) => r.json())
  .then((j) => {
    if (!j.ok) {
      toast("Erreur prestataires", false);
      return;
    }
    prestSel.innerHTML =
      '<option value="">-- Choisir --</option>' +
      j.data
        .map(
          (p) =>
            `<option data-montant="${p.montant_origine}" value="${p.nom}">${p.nom}</option>`
        )
        .join("");
  });
prestSel.addEventListener("change", () => {
  const opt = prestSel.selectedOptions[0];
  if (opt && opt.dataset.montant) {
    montantBox.textContent =
      "Montant origine: " +
      Number(opt.dataset.montant).toLocaleString("fr-FR", {
        minimumFractionDigits: 0,
      }) +
      " CFA";
  }
});

// Aperçu bon
const bonInput = form.querySelector("input[name=bon_fichier]");
const bonPreviewWrap = document.getElementById("bonPreviewWrap");
bonInput.addEventListener("change", () => {
  bonPreviewWrap.innerHTML = "";
  const f = bonInput.files[0];
  if (!f) return;
  if (f.type.startsWith("image/")) {
    const url = URL.createObjectURL(f);
    bonPreviewWrap.innerHTML = `<img class="preview-bon" src="${url}" alt="bon"/>`;
  }
});

function buildRecap() {
  const data = new FormData(form);
  const entries = [
    "date_voyage",
    "prestataire_nom",
    "chauffeur_nom",
    "chauffeur_tel",
    "camion_matricule",
    "bon_numero",
  ];
  const recap = document.getElementById("recap");
  recap.innerHTML = entries
    .map(
      (k) =>
        `<div><strong>${k.replace("_", " ")}:</strong> ${
          data.get(k) || ""
        }</div>`
    )
    .join("");

  // Ajouter vignette/lien du fichier bon si présent
  try {
    const f = bonInput && bonInput.files ? bonInput.files[0] : null;
    if (f) {
      if (f.type && f.type.startsWith("image/")) {
        const url = URL.createObjectURL(f);
        recap.insertAdjacentHTML(
          "beforeend",
          `<div class="mt-2"><div><strong>Bon (aperçu):</strong></div>
            <img src="${url}" alt="aperçu bon" style="max-width:160px;max-height:120px;border-radius:6px;border:1px solid #fde68a" onload="URL.revokeObjectURL(this.src)"/>
          </div>`
        );
      } else if (
        (f.type && f.type === "application/pdf") ||
        /\.pdf$/i.test(f.name || "")
      ) {
        const url = URL.createObjectURL(f);
        recap.insertAdjacentHTML(
          "beforeend",
          `<div class="mt-2"><strong>Bon (PDF):</strong> <a href="${url}" target="_blank" rel="noopener">Ouvrir le PDF</a></div>`
        );
      } else {
        recap.insertAdjacentHTML(
          "beforeend",
          `<div class="mt-2"><strong>Bon (fichier):</strong> ${
            f.name || "fichier joint"
          }</div>`
        );
      }
    }
  } catch (e) {
    /* no-op */
  }
}

// Soumission
form.addEventListener("submit", (e) => {
  e.preventDefault();
  const fd = new FormData(form);
  fetch(API, { method: "POST", body: fd })
    .then((r) => r.json())
    .then((j) => {
      if (!j.ok) {
        toast(j.error || "Erreur création", false);
        return;
      }
      toast("Voyage créé (#" + j.voyage_id + ")");
      form.reset();
      current = 0;
      goto(0);
      prestSel.dispatchEvent(new Event("change"));
      loadRecent();
      // Fermeture modal si présente
      const modal = document.getElementById("voyageModal");
      if (modal && modal.classList.contains("open")) {
        modal.classList.remove("open");
        document.body.classList.remove("overflow-hidden");
      }
    })
    .catch(() => toast("Erreur réseau", false));
});

function loadRecent() {
  fetch(API + "?action=listVoyages&statut=SAISI&limit=10")
    .then((r) => r.json())
    .then((j) => {
      if (!j.ok) return;
      const wrap = document.getElementById("recentVoyages");
      wrap.innerHTML = j.data
        .map((v) => {
          const thumbUrl = v.bon_id
            ? API.replace("api.php", "bon_file.php") + `?id=${v.bon_id}`
            : null;
          const thumb = thumbUrl
            ? `<img src="${thumbUrl}" alt="bon" style="width:68px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #fde68a;margin-right:.5rem;" onerror="this.remove()"/>`
            : "";
          return `<div class='recent-card'>
            <div class='flex items-center justify-between'><span class='recent-badge'>#${
              v.id
            }</span><span class='font-medium text-[10px] text-yellow-800'>${
            v.date_voyage || ""
          }</span></div>
            <div class='flex items-start'>${thumb}
              <div>
                <div class='text-[11px] font-semibold text-slate-700'>${
                  v.camion_matricule || ""
                }</div>
                <div class='text-[10px] text-slate-600'>${
                  v.chauffeur || ""
                }</div>
                <div class='text-[10px] text-yellow-700'>${Number(
                  v.montant_origine || 0
                ).toLocaleString("fr-FR")} CFA</div>
              </div>
            </div>
          </div>`;
        })
        .join("");
    });
}
loadRecent();
setInterval(loadRecent, 15000);
