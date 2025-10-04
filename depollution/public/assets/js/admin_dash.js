// Admin dashboard JS modern
const API = "api.php";
const toastEl = document.getElementById("toast");
function toast(msg, ok = true) {
  toastEl.textContent = msg;
  toastEl.style.background = ok ? "#111" : "#b91c1c";
  toastEl.hidden = false;
  setTimeout(() => (toastEl.hidden = true), 3000);
}
const listEl = document.getElementById("prestList");
function formatMoney(v) {
  return Number(v).toLocaleString("fr-FR");
}
function loadPrest() {
  fetch(API + "?action=listPrestataires")
    .then((r) => r.json())
    .then((j) => {
      if (!j.ok) {
        listEl.textContent = "Erreur chargement";
        return;
      }
      listEl.innerHTML = j.data
        .map(
          (p) =>
            `<div class='prest-card'><div class='name'>${
              p.nom
            }</div><div class='montant'>${formatMoney(
              p.montant_origine
            )} CFA</div></div>`
        )
        .join("");
    });
}
loadPrest();

document.getElementById("formAddPrest").addEventListener("submit", (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append("action", "importPrestataire");
  fetch(API, { method: "POST", body: fd })
    .then((r) => r.json())
    .then((j) => {
      if (!j.ok) {
        toast(j.error || "Erreur", false);
        return;
      }
      toast("Prestataire ajouté");
      e.target.reset();
      loadPrest();
    });
});

// Drag & Drop / Import & Preview Excel
const dz = document.getElementById("dropZone");
const excelInput = document.getElementById("excelInput");
const importLog = document.getElementById("importLog");
const btnPreview = document.getElementById("btnPreview");
const btnImportDirect = document.getElementById("btnImportDirect");
const btnImportValids = document.getElementById("btnImportValids");
const btnHidePreview = document.getElementById("btnHidePreview");
const previewContainer = document.getElementById("previewContainer");
const previewSheets = document.getElementById("previewSheets");
let lastSelectedFile = null;
function preventDefaults(e) {
  e.preventDefault();
  e.stopPropagation();
}
if (dz) {
  ["dragenter", "dragover", "dragleave", "drop"].forEach((ev) =>
    dz.addEventListener(ev, preventDefaults)
  );
  ["dragenter", "dragover"].forEach((ev) =>
    dz.addEventListener(ev, () => dz.classList.add("drag"))
  );
  ["dragleave", "drop"].forEach((ev) =>
    dz.addEventListener(ev, () => dz.classList.remove("drag"))
  );
  dz.addEventListener("click", () => excelInput.click());
  dz.addEventListener("drop", (e) => {
    const files = e.dataTransfer.files;
    if (files && files[0]) handleExcel(files[0]);
  });
  excelInput.addEventListener("change", () => {
    if (excelInput.files[0]) handleExcel(excelInput.files[0]);
  });
}
function renderPreview(data) {
  previewSheets.innerHTML = data
    .map((sheet) => {
      const hasExtended = sheet.rows.some(
        (r) =>
          r.nb_voy ||
          r.cubage ||
          r.montant_origine ||
          r.frais_route ||
          r.carburant_montant ||
          r.carburant_litre ||
          r.reel_recu
      );
      const hasCalcReel = sheet.rows.some((r) => r.calc_reel);
      const rowsHtml = sheet.rows
        .slice(0, 300) // limiter l'affichage
        .map((r) => {
          const cls = r.valid ? "ok" : "err";
          const calcBadge = r.calc_reel
            ? '<span class="badge-calc">calc</span>'
            : "";
          return `<tr class="${cls}">
              <td>${r.row}</td>
              <td>${r.date_source || ""}</td>
              <td>${r.date_sql || ""}</td>
              <td>${r.camion || ""}</td>
              <td>${r.chauffeur || ""}</td>
              <td>${r.telephone || ""}</td>
              <td>${r.bon_normalize || r.bon_raw || ""}</td>
              ${
                hasExtended
                  ? `<td>${r.nb_voy ?? ""}</td><td>${r.cubage ?? ""}</td><td>${
                      r.montant_origine ?? ""
                    }</td><td>${r.frais_route ?? ""}</td><td>${
                      r.carburant_montant ?? ""
                    }</td><td>${r.carburant_litre ?? ""}</td><td>${
                      r.reel_recu ?? ""
                    } ${calcBadge}</td>`
                  : ""
              }
              <td>${r.errors.join(" | ")}</td>
            </tr>`;
        })
        .join("");
      return `<div class="sheet-block">
          <div class="sheet-head">
            <strong>${sheet.sheet}</strong>
            <span class="stat">Valides: ${sheet.rows_valid} / Erreurs: ${
        sheet.rows_errors
      }</span>
            <span class="map">Mapping: ${Object.entries(sheet.mapping)
              .map(([k, v]) => `${k}:${v || "-"}`)
              .join(", ")}</span>
          </div>
          <div class="sheet-table-wrap">
            <table class="preview-table">
              <thead><tr><th>#</th><th>Date Src</th><th>Date SQL</th><th>Camion</th><th>Chauffeur</th><th>Tél</th><th>Bon</th>${
                hasExtended
                  ? `<th>N.Voy</th><th>Cubage</th><th>Montant Orig</th><th>Frais</th><th>Carb</th><th>Litre</th><th>Réel${
                      hasCalcReel ? "<sup>*</sup>" : ""
                    }</th>`
                  : ""
              }<th>Erreurs</th></tr></thead>
              <tbody>${rowsHtml}</tbody>
            </table>
          </div>
        </div>`;
    })
    .join("");
  previewContainer.style.display = "block";
}

function previewExcel(file) {
  importLog.hidden = false;
  importLog.textContent = "Prévisualisation de " + file.name + "...\n";
  const fd = new FormData();
  fd.append("action", "importExcelPreview");
  fd.append("excel", file);
  const strictCb = document.getElementById("strictMode");
  if (strictCb && strictCb.checked) fd.append("strict", "1");
  fetch(API, { method: "POST", body: fd })
    .then((r) => r.json())
    .then((j) => {
      if (!j.ok) {
        importLog.textContent += "Erreur: " + (j.error || "inconnue");
        toast("Preview échouée", false);
        return;
      }
      importLog.textContent += "Prévisualisation prête.\n";
      renderPreview(j.preview);
      toast("Aperçu généré");
    })
    .catch(() => {
      importLog.textContent += "Erreur réseau";
      toast("Erreur réseau", false);
    });
}

function importExcel(file) {
  importLog.hidden = false;
  importLog.textContent = "Import de " + file.name + "...\n";
  const fd = new FormData();
  fd.append("action", "importExcel");
  fd.append("excel", file);
  const strictCb = document.getElementById("strictMode");
  if (strictCb && strictCb.checked) fd.append("strict", "1");
  fetch(API, { method: "POST", body: fd })
    .then((r) => r.json())
    .then((j) => {
      if (!j.ok) {
        importLog.textContent += "Erreur: " + (j.error || "inconnue");
        toast("Import échoué", false);
        return;
      }
      importLog.textContent += "Import terminé.\n";
      j.report.forEach((sheet) => {
        importLog.textContent += `Feuille: ${sheet.sheet} | Prestataire ${
          sheet.prestataire_created ? "créé" : "existant"
        } | Voyages OK: ${sheet.voyages_created} / Err: ${
          sheet.voyages_errors
        }\n`;
      });
      toast("Import terminé");
      loadPrest();
    })
    .catch(() => {
      importLog.textContent += "Erreur réseau";
      toast("Erreur réseau", false);
    });
}

function handleExcel(file) {
  lastSelectedFile = file;
  // si l'utilisateur a cliqué sur preview on ne lance rien ici.
  // On attend l'action explicite.
  toast("Fichier prêt. Choisissez Prévisualiser ou Importer.");
}

btnPreview?.addEventListener("click", () => {
  if (!lastSelectedFile) {
    if (excelInput.files[0]) lastSelectedFile = excelInput.files[0];
  }
  if (!lastSelectedFile)
    return toast("Veuillez d'abord choisir un fichier", false);
  previewExcel(lastSelectedFile);
});
btnImportDirect?.addEventListener("click", () => {
  if (!lastSelectedFile) {
    if (excelInput.files[0]) lastSelectedFile = excelInput.files[0];
  }
  if (!lastSelectedFile)
    return toast("Veuillez d'abord choisir un fichier", false);
  importExcel(lastSelectedFile);
});
btnImportValids?.addEventListener("click", () => {
  if (!lastSelectedFile) return toast("No fichier", false);
  // Pour cette première version on réimporte tout (filtrage côté DB évite doublons). Amélioration future: générer un fichier temporaire filtré.
  importExcel(lastSelectedFile);
});
btnHidePreview?.addEventListener("click", () => {
  previewContainer.style.display = "none";
});

// Styles dynamiques ajoutés pour preview tables
const style = document.createElement("style");
style.textContent = `.sheet-block{border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:.6rem .7rem;box-shadow:0 2px 5px -2px rgba(0,0,0,.05);} .sheet-head{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;font-size:.7rem;margin-bottom:.4rem} .sheet-head .stat{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-weight:600;} .sheet-head .map{color:#475569;font-size:.6rem;} .sheet-table-wrap{max-height:220px;overflow:auto;border:1px solid #e2e8f0;border-radius:6px;} .preview-table{width:100%;border-collapse:collapse;font-size:.65rem;} .preview-table th,.preview-table td{padding:.35rem .4rem;border-bottom:1px solid #e5e7eb;text-align:left;white-space:nowrap;} .preview-table th{background:#f8fafc;font-weight:600;position:sticky;top:0;z-index:1;} .preview-table tr.ok{background:#f0fdf4;} .preview-table tr.err{background:#fef2f2;} .preview-table tr.err td:last-child{color:#b91c1c;font-weight:600;}`;
document.head.appendChild(style);

// Export CSV
const exportBtn = document.getElementById("exportCsv");
exportBtn.addEventListener("click", () => {
  fetch(API + "?action=listVoyages&limit=1000")
    .then((r) => r.json())
    .then((j) => {
      if (!j.ok) {
        toast("Erreur export", false);
        return;
      }
      const rows = j.data;
      if (!rows.length) {
        toast("Aucun voyage", false);
        return;
      }
      const header = Object.keys(rows[0]);
      const csv = [header.join(";")]
        .concat(
          rows.map((o) =>
            header
              .map((h) => `"${(o[h] ?? "").toString().replace(/"/g, '""')}"`)
              .join(";")
          )
        )
        .join("\n");
      const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = "voyages.csv";
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    });
});
