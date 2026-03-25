// script.js — Statistiques des résultats par semestre

document.addEventListener('DOMContentLoaded', function () {
    initializePage();
});

// ── Variables globales ────────────────────────────────────────────────────────
const filieresSelect = document.getElementById('filiterFiliere');
const niveauxFormationSelect = document.getElementById('filterNiveau');
const optionsSelect = document.getElementById('filterOption');
const semestersSelect = document.getElementById('filterSemester');
const cycleSelect = document.getElementById('filterCycle');
let allFilieres = [];
let allOptions = [];
let allUEs = [];
let statsData = null;   // données stats courantes pour l'export
let dataTableUEs = null;

function showAlert(champ) {
    Swal.fire({
        icon: 'warning',
        title: 'Champ manquant',
        text: `Veuillez sélectionner : ${champ}`,
        confirmButtonText: 'D\'accord',
        confirmButtonColor: '#3085d6'
    });
}

// ── Initialisation des sélecteurs ─────────────────────────────────────────────
function initializeSelect(selectElement, placeholder) {
    if (!placeholder) {
        placeholder = selectElement.id.includes('Cycle') ? 'Sélectionner un Cycle' :
            selectElement.id.includes('Niveau') ? 'Sélectionner un Niveau' :
                selectElement.id.includes('Option') ? 'Sélectionner une Option' :
                    selectElement.id.includes('Semester') ? 'Sélectionner un Semestre' :
                        selectElement.id.includes('Filiere') ? 'Sélectionner une Filière' : 'Sélectionner';
    }
    selectElement.innerHTML = '';
    const def = document.createElement('option');
    def.value = ''; def.textContent = placeholder;
    def.disabled = true; def.selected = true;
    selectElement.appendChild(def);
}

// ── API ───────────────────────────────────────────────────────────────────────
function getFilieres() {
    return fetch('controller.php?action=getFilieres').then(r => r.json());
}
function getNiveauxFormation(idCycleFormation = 0) {
    return fetch(`controller.php?action=getNiveauFormationByCycle&idCycleFormation=${idCycleFormation}`).then(r => r.json());
}
function getOptions(idFiliere = 0, idNiveauFormation = 0) {
    return fetch(`controller.php?action=getOptionsByFiliere&idFiliere=${idFiliere}&idNiveauFormation=${idNiveauFormation}`).then(r => r.json());
}
function getSemestres(idNiveauFormation = '') {
    return fetch(`controller.php?action=getSemestresByNiveau&idNiveauFormation=${idNiveauFormation}`).then(r => r.json());
}
function getMaquetteUEs(filters) {
    return fetch(`controller.php?action=getMaquetteUEs&${new URLSearchParams(filters)}`).then(r => r.json());
}
function getStatsSemestre(filters) {
    return fetch(`controller.php?action=getStatsSemestre&${new URLSearchParams(filters)}`).then(r => r.json());
}

// ── Chargements ───────────────────────────────────────────────────────────────
function loadFilieres() {
    return getFilieres().then(filieres => {
        allFilieres = filieres;
        initializeSelect(filieresSelect, 'Sélectionner une Filière');
        filieres.forEach(f => {
            const o = document.createElement('option');
            o.value = f.id; o.textContent = f.filiere;
            filieresSelect.appendChild(o);
        });
    });
}

function loadOptions(filiereId = null, niveauFormationId = null) {
    const fn = filiereId
        ? getOptions(filiereId, niveauFormationId)
        : getOptions();
    return fn.then(options => {
        if (!filiereId) allOptions = options;
        initializeSelect(optionsSelect, 'Sélectionner une Option');
        options.forEach(opt => {
            const o = document.createElement('option');
            o.value = opt.id; o.textContent = opt.option;
            optionsSelect.appendChild(o);
        });
    });
}
function loadSemestres(idNiveauFormation = '') {
    initializeSelect(semestersSelect, 'Sélectionner un Semestre');
    if (!idNiveauFormation) return Promise.resolve();
    return getSemestres(idNiveauFormation).then(semestres => {
        initializeSelect(semestersSelect, 'Sélectionner un Semestre');
        semestres.forEach(s => {
            const o = document.createElement('option');
            o.value = s.id;
            o.textContent = s.nom_semestre;
            semestersSelect.appendChild(o);
        });
    });
}
function loadNiveaux(cycleId) {
    if (!cycleId) { initializeSelect(niveauxFormationSelect, 'Sélectionner un Niveau'); return Promise.resolve(); }
    return getNiveauxFormation(cycleId).then(niveaux => {
        initializeSelect(niveauxFormationSelect, 'Sélectionner un Niveau');
        niveaux.forEach(n => {
            const o = document.createElement('option');
            o.value = n.id; o.textContent = n.niveau;
            niveauxFormationSelect.appendChild(o);
        });
    });
}

// ── Init ──────────────────────────────────────────────────────────────────────
function initializePage() {
    initializeSelect(niveauxFormationSelect, 'Sélectionner un Niveau');
    initializeSelect(filieresSelect, 'Sélectionner une Filière');
    initializeSelect(optionsSelect, 'Sélectionner une Option');

    Promise.all([loadFilieres()]).then(() => setupEventListeners());
}

function setupEventListeners() {
    cycleSelect.addEventListener('change', function () {
        loadNiveaux(this.value);
    });

    filieresSelect.addEventListener('change', function () {
        const fId = this.value;
        const nId = niveauxFormationSelect.value;
        fId ? loadOptions(fId, nId) : loadOptions();
    });

    niveauxFormationSelect.addEventListener('change', function () {
        const fId = filieresSelect.value;
        const nId = this.value;
        // Recharger options et semestres selon le niveau choisi
        fId ? loadOptions(fId, nId) : loadOptions();
        loadSemestres(nId);
    }); 

    // Tout changement de filtre recharge les UEs ET les stats
    [semestersSelect].forEach(sel => {
        sel.addEventListener('change', () => {
            // loadUEs();
            loadStats();
        });
    });
}

// ── Chargement tableau UEs (inchangé) ─────────────────────────────────────────
function loadUEs() {
    const idCycle = cycleSelect?.value;
    const idNiveauFormation = niveauxFormationSelect?.value;
    const idOption = optionsSelect?.value;
    const idSemestre = semestersSelect?.value;

    if (!idCycle && !idNiveauFormation && !idOption && !idSemestre) return;

    const params = {};
    if (idCycle) params.idcycle = idCycle;
    if (idNiveauFormation) params.idNiveauFormation = idNiveauFormation;
    if (idOption) params.idOption = idOption;
    if (idSemestre) params.idSemestre = idSemestre;

    document.getElementById('resultats').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">Chargement des UEs...</p>
        </div>`;

    getMaquetteUEs(params)
        .then(ues => {
            allUEs = Array.isArray(ues) ? ues : [];
            if (!allUEs.length) {
                document.getElementById('resultats').innerHTML = `
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-info-circle me-2"></i>
                        Aucune UE trouvée pour les filtres sélectionnés.
                    </div>`;
                return;
            }
            initDataTableUEs(allUEs);
        })
        .catch(() => {
            document.getElementById('resultats').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>Erreur lors du chargement des UEs.
                </div>`;
        });
}

function loadStats() {
    const idSemestre = semestersSelect?.value;
    const idOption = optionsSelect?.value;
    const idNiveauFormation = niveauxFormationSelect?.value;
    const idCycle = cycleSelect?.value;

    if (!idSemestre) return;

    const params = { idSemestre };
    if (idOption) params.idOption = idOption;
    if (idNiveauFormation) params.idNiveauFormation = idNiveauFormation;
    if (idCycle) params.idCycle = idCycle;

    // Afficher un loader dans la zone résultats
    const resultats = document.getElementById('resultats');
    if (resultats) {
        resultats.innerHTML = `
            <div class="d-flex justify-content-center align-items-center py-10">
                <div class="spinner-border text-primary me-3" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <span class="text-muted fw-semibold fs-6">Chargement des statistiques...</span>
            </div>`;
    }

    getStatsSemestre(params)
        .then(res => {
            if (!res.success) {
                if (resultats) {
                    resultats.innerHTML = `
                        <div class="alert alert-warning d-flex align-items-center p-5 mb-0">
                            <i class="fas fa-exclamation-triangle text-warning me-3 fs-3"></i>
                            <div class="fw-semibold">
                                ${res.message || 'Aucune donnée disponible pour ce semestre.'}
                            </div>
                        </div>`;
                }
                return;
            }
            resultats.innerHTML = ''
            statsData = res;
            renderStatsPanel(res);
        })
        .catch(err => {
            console.error('Erreur stats:', err);
            if (resultats) {
                resultats.innerHTML = `
                    <div class="alert alert-danger d-flex align-items-center p-5 mb-0">
                        <i class="fas fa-times-circle text-danger me-3 fs-3"></i>
                        <div class="fw-semibold">
                            Erreur lors du chargement des statistiques.
                        </div>
                    </div>`;
            }
        });
}

function renderStatsPanel(data) {
    const { ctx, annee, statsParUE, statsGlobales } = data;

    let panel = document.getElementById('statsPanel');
    if (!panel) {
        panel = document.createElement('div');
        panel.id = 'statsPanel';
        panel.className = 'mt-4';
        document.getElementById('resultats').after(panel);
    }

    const sg = statsGlobales || {};

    const lignes = statsParUE.map((ue, i) => `
        <tr>
            <td class="text-muted text-center fw-semibold">${i + 1}</td>
            <td><span class="badge badge-light-primary fw-bold fs-8">${ue.codeUE}</span></td>
            <td class="text-gray-700 fw-semibold" title="${ue.nomUE}">
                ${ue.nomUE.length > 45 ? ue.nomUE.substring(0, 45) + '…' : ue.nomUE}
            </td>
            <td class="text-center">
                <span class="badge badge-light-dark fw-bold">${ue.effectif}</span>
            </td>
            <td class="text-center">
                <span class="badge badge-light-dark fw-bold">${ue.absents ?? 0}</span>
                ${(ue.absents ?? 0) > 0 ? `<div class="text-muted fs-8 mt-1">${ue.tauxAbsence ?? 0}%</div>` : ''}
            </td>
            <td class="text-center">
                <span class="badge badge-light-danger me-1">${parseFloat(ue.note_min).toFixed(2)}</span>
                <span class="badge badge-light-success">${parseFloat(ue.note_max).toFixed(2)}</span>
            </td>
            <td class="text-center">
                <span class="badge badge-light-success fw-bold">${ue.reussite}</span>
                <div class="text-muted fs-8 mt-1">${ue.tauxReussite}%</div>
            </td>
            <td class="text-center">
                <span class="badge badge-light-danger fw-bold">${ue.echec}</span>
                <div class="text-muted fs-8 mt-1">${ue.tauxEchec}%</div>
            </td>
            <td class="text-center"><span class="badge badge-light-danger">${ue.intervalle_0_7}</span></td>
            <td class="text-center"><span class="badge badge-light-warning">${ue.intervalle_7_8}</span></td>
            <td class="text-center"><span class="badge badge-light-warning">${ue.intervalle_8_9}</span></td>
            <td class="text-center"><span class="badge badge-light-warning">${ue.intervalle_9_10}</span></td>
            <td class="text-center"><span class="badge badge-light-success">${ue.intervalle_10_20}</span></td>
        </tr>`).join('');

    panel.innerHTML = `
    <div class="card" id="statsDocument">

        <div class="card-header border-0 pt-5">
            <div class="card-title flex-column">
                <h3 class="fw-bolder mb-1">
                    Statistiques des résultats — Semestre ${ctx?.numInYear ?? ''}
                </h3>
                <div class="text-muted fw-semibold fs-7">
                    ${ctx?.filiere ?? ''} &nbsp;·&nbsp;
                    ${ctx?.niveau ?? ''} ${ctx?.specialite ?? ''} &nbsp;·&nbsp;
                    ${annee ?? ''} &nbsp;·&nbsp;
                    Session ${ctx?.idSession ?? 'I'}
                </div>
            </div>
            <div class="card-toolbar">
                <button class="btn btn-sm btn-light-danger fw-bold" onclick="exporterStatsPDF()">
                    <i class="fas fa-file-pdf me-1"></i> PDF
                </button>
            </div>
        </div>

        <div class="card-body py-3">

            <!-- Cards stats globales -->
            <div class="row g-4 mb-6">
                <div class="col">
                    <div class="border border-gray-300 border-dashed rounded py-3 px-4 text-center">
                        <div class="fs-2 fw-bolder text-gray-800">${sg.effectif ?? 0}</div>
                        <div class="fw-bold text-muted fs-7">Effectif total</div>
                    </div>
                </div>
                <div class="col">
                    <div class="border border-gray-300 border-dashed rounded py-3 px-4 text-center">
                        <div class="fs-2 fw-bolder text-gray-800">${sg.presents ?? sg.effectif ?? 0}</div>
                        <div class="fw-bold text-muted fs-7">Présents</div>
                    </div>
                </div>
                <div class="col">
                    <div class="border border-gray-400 border-dashed rounded py-3 px-4 text-center">
                        <div class="fs-2 fw-bolder text-gray-600">${sg.absents ?? 0}</div>
                        <div class="fw-bold text-muted fs-7">Absents</div>
                    </div>
                </div>
                <div class="col">
                    <div class="border border-success border-dashed rounded py-3 px-4 text-center">
                        <div class="fs-2 fw-bolder text-success">${sg.reussite ?? 0}</div>
                        <div class="fw-bold text-success fs-8">${sg.tauxReussite ?? 0}%</div>
                        <div class="fw-bold text-muted fs-7">Réussite</div>
                    </div>
                </div>
                <div class="col">
                    <div class="border border-danger border-dashed rounded py-3 px-4 text-center">
                        <div class="fs-2 fw-bolder text-danger">${sg.echec ?? 0}</div>
                        <div class="fw-bold text-danger fs-8">${sg.tauxEchec ?? 0}%</div>
                        <div class="fw-bold text-muted fs-7">Échec</div>
                    </div>
                </div>
                <div class="col">
                    <div class="border border-success border-dashed rounded py-3 px-4 text-center">
                        <div class="fs-2 fw-bolder text-success">${sg.max_sem ?? '—'}</div>
                        <div class="fw-bold text-muted fs-7">Moyenne max</div>
                    </div>
                </div>
                <div class="col">
                    <div class="border border-danger border-dashed rounded py-3 px-4 text-center">
                        <div class="fs-2 fw-bolder text-danger">${sg.min_sem ?? '—'}</div>
                        <div class="fw-bold text-muted fs-7">Moyenne min</div>
                    </div>
                </div>
            </div>

            <!-- Tableau -->
            <div class="table-responsive">
                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-3" id="tableStats">
                    <thead>
                        <tr class="fw-bolder text-muted bg-light">
                            <th class="text-center ps-3" style="width:40px">#</th>
                            <th style="width:100px">Code</th>
                            <th>Unité d'enseignement</th>
                            <th class="text-center">Effectif</th>
                            <th class="text-center">Absents</th>
                            <th class="text-center">Min / Max</th>
                            <th class="text-center">Réussite</th>
                            <th class="text-center">Échec</th>
                            <th class="text-center">[0–7[</th>
                            <th class="text-center">[7–8[</th>
                            <th class="text-center">[8–9[</th>
                            <th class="text-center">[9–10[</th>
                            <th class="text-center pe-3">[10–20]</th>
                        </tr>
                    </thead>
                    <tbody>${lignes}</tbody>
                </table>
            </div>

        </div>
    </div>`;
}
// ── Export PDF statistiques ───────────────────────────────────────────────────
function exporterStatsPDF() {
    if (!statsData) return;

    const { jsPDF } = window.jspdf;
    // Changement : 'p' pour portrait
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
    const W = doc.internal.pageSize.getWidth(); // Environ 297mm

    // Charger les deux logos
    const imgUAHB = new Image();
    const imgGSJLF = new Image();
    imgUAHB.crossOrigin = 'anonymous';
    imgGSJLF.crossOrigin = 'anonymous';
    imgUAHB.src = '../../dist_assets/media/logos/uahb.png';
    imgGSJLF.src = '../../dist_assets/media/logos/CMJLF.jpeg';

    let loaded = 0;
    const onLoad = () => {
        loaded++;
        if (loaded === 2) _genererStatsPDF(doc, W, imgUAHB, imgGSJLF);
    };
    imgUAHB.onload = onLoad;
    imgGSJLF.onload = onLoad;
    imgUAHB.onerror = onLoad;
    imgGSJLF.onerror = onLoad;
}

function _imgToBase64(img) {
    const c = document.createElement('canvas');
    c.width = img.naturalWidth; c.height = img.naturalHeight;
    c.getContext('2d').drawImage(img, 0, 0);
    return c.toDataURL('image/png');
}

function _genererStatsPDF(doc, W, imgUAHB, imgGSJLF) {
    const { ctx, annee, statsParUE } = statsData;
    const COLOR = [36, 103, 92];
    const cx = W / 2;
    const headerH = 44;
    const logoH = 22;
    const logoW = 22;
 
    // ── En-tête ───────────────────────────────────────────────────────────────
    doc.setDrawColor(200, 200, 200);
    doc.setLineWidth(0.3);
    doc.line(0, headerH, W, headerH);
 
    if (imgGSJLF && imgGSJLF.naturalWidth > 0) {
        doc.addImage(_imgToBase64(imgGSJLF), 'JPEG', W * 0.25, (headerH - logoH) / 2, logoW, logoH);
    }
    if (imgUAHB && imgUAHB.naturalWidth > 0) {
        doc.addImage(_imgToBase64(imgUAHB), 'PNG', W * 0.75 - logoW, (headerH - logoH) / 2, logoW, logoH);
    }
 
    // Groupe scolaire
    doc.setTextColor(100);
    doc.setFont('helvetica', 'italic');
    doc.setFontSize(9);
    doc.text('Groupe Scolaire Jean de la Fontaine', cx, 7, { align: 'center' });
 
    // Université
    doc.setTextColor(...COLOR);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(13);
    doc.text('UNIVERSITE AMADOU HAMPATE BA DE DAKAR', cx, 14, { align: 'center' });
 
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.setTextColor(100);
    doc.text('-=-=-=- UAHB -=-=-=-', cx, 19, { align: 'center' });
 
    doc.setDrawColor(180);
    doc.setLineDashPattern([1, 1], 0);
    doc.line(cx - 35, 22, cx + 35, 22);
    doc.setLineDashPattern([], 0);
 
    // Faculté
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10.5);
    doc.setTextColor(...COLOR);
    doc.text((ctx.faculte || '').toUpperCase(), cx, 29, { align: 'center' });
 
    doc.setDrawColor(180);
    doc.setLineDashPattern([1, 1], 0);
    doc.line(cx - 32, 32, cx + 32, 32);
    doc.setLineDashPattern([], 0);
 
    // Département
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9.5);
    doc.setTextColor(...COLOR);
    doc.text((ctx.departement || '').toUpperCase(), cx, 39, { align: 'center' });
 
    // ── Bandeau ───────────────────────────────────────────────────────────────
    const bandeauY = headerH + 5;
    const bandeauH = 26;
    const bandeauW = W * 0.70;
    const bandeauX = (W - bandeauW) / 2;
 
    doc.setFillColor(...COLOR);
    doc.roundedRect(bandeauX, bandeauY, bandeauW, bandeauH, 4, 4, 'F');
 
    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(13);
    doc.text('STATISTIQUE DES RÉSULTATS DU SEMESTRE', cx, bandeauY + 8, { align: 'center' });
 
    doc.setFontSize(9.5);
    doc.setFont('helvetica', 'normal');
    const ligne2 = [ctx?.filiere, ctx?.niveau, ctx?.specialite, ctx?.nom_semestre].filter(Boolean).join('  |  ');
    doc.text(ligne2, cx, bandeauY + 15, { align: 'center' });
 
    doc.setFontSize(9);
    doc.text(
        `${annee || ''}  —  Semestre ${ctx?.numInYear || ''}  —  Session ${ctx?.idSession || 'I'}`,
        cx, bandeauY + 22, { align: 'center' }
    );
 
    // ── infoY déclaré AVANT son utilisation ──────────────────────────────────
const infoY = bandeauY + bandeauH + 3;
 
// ── Cartes stats globales ─────────────────────────────────────────────────
const sg = statsData.statsGlobales || {};
const cards = [
    { label: 'Effectif total', val: String(sg.effectif ?? 0),   sub: null,                         color: [60,  60,  60] },
    { label: 'Absents',        val: String(sg.absents ?? 0),   sub: null,                         color: [114, 28,  36] },
    { label: 'Ayant composes', val: String(sg.presents ?? 0),   sub: null,                         color: [60,  60,  60] },
    { label: 'Réussite',       val: String(sg.reussite ?? 0),   sub: (sg.tauxReussite ?? 0) + '%', color: [21,  87,  36] },
    { label: 'Échec',          val: String(sg.echec    ?? 0),   sub: (sg.tauxEchec    ?? 0) + '%', color: [114, 28,  36] },
    { label: 'Moyenne Max',    val: String(sg.max_sem  ?? '-'), sub: null,                         color: [21,  87,  36] },
    { label: 'Moyenne Min',    val: String(sg.min_sem  ?? '-'), sub: null,                         color: [114, 28,  36] },
];
 
const bW = 35, bH = 18, bGap = 4;
const cardsY = infoY + 10;
let sx = (W - (cards.length * bW + (cards.length - 1) * bGap)) / 2;
 
cards.forEach(item => {
    doc.setFillColor(...item.color.map(c => Math.min(255, c + 170)));
    doc.roundedRect(sx, cardsY, bW, bH, 2, 2, 'F');
    doc.setDrawColor(...item.color);
    doc.setLineWidth(0.4);
    doc.roundedRect(sx, cardsY, bW, bH, 2, 2, 'S');
 
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(item.sub ? 11 : 12);
    doc.setTextColor(...item.color);
    doc.text(item.val, sx + bW / 2, cardsY + (item.sub ? 6 : 8), { align: 'center' });
 
    if (item.sub) {
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(...item.color);
        doc.text(item.sub, sx + bW / 2, cardsY + 11, { align: 'center' });
    }
 
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(6.5);
    doc.setTextColor(90, 90, 90);
    doc.text(item.label, sx + bW / 2, cardsY + bH - 1.5, { align: 'center' });
 
    sx += bW + bGap;
});
 
// ── "Détail par UE" + Date sur la même ligne ──────────────────────────────
const detailY = cardsY + bH + 5;
 
doc.setFont('helvetica', 'bold');
doc.setFontSize(8.5);
doc.setTextColor(...COLOR);
doc.text('Détail par UE', 12, detailY);
 
doc.setFont('helvetica', 'normal');
doc.setFontSize(8.5);
doc.setTextColor(60);
doc.text('Date : ' + new Date().toLocaleDateString('fr-FR'), W - 12, detailY, { align: 'right' });
 
// Ligne séparatrice
doc.setDrawColor(200, 200, 200);
doc.setLineWidth(0.3);
doc.line(12, detailY + 2, W - 12, detailY + 2);
 
// ── Tableau stats ─────────────────────────────────────────────────────────
const head = [[
    '#', 'Code UE', 'Libellé Unité d\'Enseignement',
    'Effectif', 'Moy min', 'Moy max',
    'Réussite', 'Échec',
    '[0–7[', '[7–8[', '[8–9[', '[9–10[', '[10–20]'
]];
 
const body = statsParUE.map((ue, i) => [
    i + 1,
    ue.codeUE,
    ue.nomUE,
    ue.effectif,
    parseFloat(ue.note_min).toFixed(2),
    parseFloat(ue.note_max).toFixed(2),
    ue.reussite + '\n' + ue.tauxReussite + '%',
    ue.echec    + '\n' + ue.tauxEchec    + '%',
    ue.intervalle_0_7,
    ue.intervalle_7_8,
    ue.intervalle_8_9,
    ue.intervalle_9_10,
    ue.intervalle_10_20,
]);
 
const totalTableWidth = 234; // 252 - 26 (2 cols %) + 8 (largeur réussite/echec augmentée)
const marginLeft = (W - totalTableWidth) / 2;
 
doc.autoTable({
    head,
    body,
    showHead: 'everyPage',
    startY: detailY + 5,
    tableWidth: totalTableWidth,
    margin: { left: marginLeft, right: marginLeft },
    styles:     { fontSize: 9, cellPadding: 2.2, overflow: 'linebreak', minCellHeight: 11 },
    headStyles: { fillColor: COLOR, fontSize: 9.5, fontStyle: 'bold', halign: 'center', valign: 'middle', cellPadding: 2.5 },
    alternateRowStyles: { fillColor: [245, 243, 238] },
    columnStyles: {
        0:  { halign: 'center', cellWidth: 8  },
        1:  { cellWidth: 22, fontStyle: 'bold' },
        2:  { cellWidth: 62 },
        3:  { halign: 'center', cellWidth: 14 },
        4:  { halign: 'center', cellWidth: 13 },
        5:  { halign: 'center', cellWidth: 13 },
        6:  { halign: 'center', cellWidth: 22 },
        7:  { halign: 'center', cellWidth: 22 },
        8:  { halign: 'center', cellWidth: 13 },
        9:  { halign: 'center', cellWidth: 13 },
        10: { halign: 'center', cellWidth: 13 },
        11: { halign: 'center', cellWidth: 13 },
        12: { halign: 'center', cellWidth: 14 },
    },
    didParseCell: (hookData) => {
        if (hookData.section !== 'body') return;
        const col = hookData.column.index;
        if (col === 6) {
            hookData.cell.styles.textColor = [21, 87, 36];
            hookData.cell.styles.fontStyle = 'bold';
            hookData.cell.styles.fontSize  = 9;
        }
        if (col === 7) {
            hookData.cell.styles.textColor = [114, 28, 36];
            hookData.cell.styles.fontStyle = 'bold';
            hookData.cell.styles.fontSize  = 9;
        }
    },
    didDrawPage: () => {
        const p = doc.internal.getCurrentPageInfo().pageNumber;
        doc.setFontSize(8);
        doc.setTextColor(120);
        doc.text(
            `Statistiques Semestre ${ctx?.numInYear || ''} — ${annee || ''} — Page ${p}`,
            W / 2, doc.internal.pageSize.height - 5, { align: 'center' }
        );
    }
});
 
    // ── Signature visa ────────────────────────────────────────────────────────
    const finalY = doc.lastAutoTable.finalY + 14;
    const sigCx = W - 12 - 35; // centre du bloc signature, aligné à droite (marge 12)
 
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9);
    doc.setTextColor(40, 40, 40);
    doc.text('Visa académique', sigCx, finalY, { align: 'center' });
 
    // const lineY = finalY + 20;
    // doc.setDrawColor(150, 150, 150);
    // doc.setLineWidth(0.4);
    // doc.line(sigCx - 35, lineY, sigCx + 35, lineY);
 
    // doc.setFont('helvetica', 'normal');
    // doc.setFontSize(7.5);
    // doc.setTextColor(150, 150, 150);
    // doc.text('Nom, Signature & cachet', sigCx, lineY + 5, { align: 'center' });
 
    const nomFichier = `STATS_SEM${ctx?.numInYear || ''}_${new Date().toISOString().slice(0, 10)}`;
    doc.save(`${nomFichier}.pdf`);
}
function bindTableEvents() {
    document.querySelectorAll('.btn-detail-ue').forEach(btn => {
        btn.removeEventListener('click', onDetailClick);
        btn.addEventListener('click', onDetailClick);
    });
}
function onDetailClick(e) {
    const btn = e.currentTarget;
    loadDetailEtudiantsUE(btn.dataset.id, btn.dataset.nom);
}

// ── Placeholder pour la modal étudiants ──────────────────────────────────────
function loadDetailEtudiantsUE(idUE, nom) {
    const modal = new bootstrap.Modal(document.getElementById('etudiantsUEModal'));
    document.getElementById('etudiantsUEModalLabel').textContent = `Étudiants — ${nom}`;
    document.getElementById('etudiantsUEModalBody').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
        </div>`;
    modal.show();
    fetch(`controller.php?action=getEtudiantByUE&idUE=${idUE}&session_id=1`)
        .then(r => r.json())
        .then(data => {
            if (!data || !data.length) {
                document.getElementById('etudiantsUEModalBody').innerHTML = '<p class="text-muted text-center">Aucun étudiant trouvé.</p>';
                return;
            }
            const lignes = data.map((e, i) => `
                <tr>
                    <td>${i + 1}</td>
                    <td>${e.matricule ?? '-'}</td>
                    <td>${e.prenom ?? '-'} ${e.nom ?? '-'}</td>
                    <td class="text-center">${e.note_final ?? '-'}</td>
                </tr>`).join('');
            document.getElementById('etudiantsUEModalBody').innerHTML = `
                <table class="table table-sm table-bordered">
                    <thead><tr><th>#</th><th>Matricule</th><th>Nom</th><th>Note</th></tr></thead>
                    <tbody>${lignes}</tbody>
                </table>`;
        })
        .catch(() => {
            document.getElementById('etudiantsUEModalBody').innerHTML = '<p class="text-danger text-center">Erreur de chargement.</p>';
        });
}