/**
 * pvParUE.js
 * Procès Verbal de Délibération par UE
 * Filtres en cascade : Filière → Cycle → Niveau → Semestre → Option → UE
 * Puis chargement et rendu du PV + exports PDF / Excel / Impression
 */

const pvParUE = (() => {

    // ── État ──────────────────────────────────────────────────────────────────
    const state = {
        idFiliere: null,
        idCycle: null,
        idNiveau: null,
        idSemestre: null,
        idOption: null,
        idUE: null,
        pvData: null,   // dernière réponse getPV
    };

    const CONTROLLER = 'pvParUEController.php';

    // ── Helpers ───────────────────────────────────────────────────────────────
    function get(action, params = {}) {
        const qs = new URLSearchParams({ action, ...params }).toString();
        return fetch(`${CONTROLLER}?${qs}`).then(r => r.json());
    }

    function disableSelect(id, placeholder = '') {
        const sel = document.getElementById(id);
        sel.innerHTML = `<option value="">${placeholder || '—'}</option>`;
        sel.disabled = true;
    }

    function fillSelect(id, items, valKey, labelKey, placeholder) {
        const sel = document.getElementById(id);
        sel.innerHTML = `<option value="">— ${placeholder} —</option>`;
        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item[valKey];
            opt.textContent = item[labelKey];
            sel.appendChild(opt);
        });
        sel.disabled = false;
    }

    function showLoader(msg = 'Chargement…') {
        document.getElementById('pvZone').innerHTML = `
            <div class="card">
                <div class="card-body text-center py-10">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <p class="text-muted">${msg}</p>
                </div>
            </div>`;
    }

    function showEmpty(msg) {
        document.getElementById('pvZone').innerHTML = `
            <div class="card">
                <div class="card-body text-center py-10 text-muted">
                    <i class="fas fa-file-alt fs-3x mb-3 d-block opacity-50"></i>
                    <p class="fs-5">${msg}</p>
                </div>
            </div>`;
        document.getElementById('exportBtns').style.display = 'none';
    }

    // ── Initialisation ────────────────────────────────────────────────────────
    async function init() {
        // Charger les filières
        try {
            const res = await get('getFilieres');
            if (res.success && res.data.length) {
                fillSelect('filterFiliere', res.data, 'id', 'nom', 'Filière');
            }
        } catch (e) {
            console.error('Erreur chargement filières:', e);
        }

        // Listeners
        document.getElementById('filterFiliere').addEventListener('change', onFiliereChange);
        document.getElementById('filterCycle').addEventListener('change', onCycleChange);
        document.getElementById('filterNiveau').addEventListener('change', onNiveauChange);
        document.getElementById('filterSemestre').addEventListener('change', onSemestreChange);
        document.getElementById('filterOption').addEventListener('change', onOptionChange);
        document.getElementById('filterUE').addEventListener('change', onUEChange);
    }

    // ── Handlers en cascade ───────────────────────────────────────────────────

    async function onFiliereChange(e) {
        state.idFiliere = e.target.value || null;
        state.idCycle = null;
        state.idNiveau = null;
        state.idSemestre = null;
        state.idOption = null;
        state.idUE = null;

        // Réinitialiser les selects aval
        document.getElementById('filterCycle').value = '';
        document.getElementById('filterCycle').disabled = !state.idFiliere;
        disableSelect('filterNiveau', 'Niveau');
        disableSelect('filterSemestre', 'Semestre');
        disableSelect('filterOption', 'Option');
        disableSelect('filterUE', 'UE');
        showEmpty('Sélectionnez une UE pour afficher le procès verbal.');

        if (!state.idFiliere) return;

        // Charger niveaux immédiatement (sans cycle encore)
        await chargerNiveaux();
        // Charger UEs filtrées par filière (pour avoir le filtre UE dès que possible)
        // await chargerUEs();
    }

    async function onCycleChange(e) {
        state.idCycle = e.target.value || null;
        state.idNiveau = null;
        state.idSemestre = null;
        state.idOption = null;
        state.idUE = null;

        disableSelect('filterNiveau', 'Niveau');
        disableSelect('filterSemestre', 'Semestre');
        disableSelect('filterOption', 'Option');
        disableSelect('filterUE', 'UE');
        showEmpty('Sélectionnez une UE pour afficher le procès verbal.');

        if (!state.idFiliere) return;
        await chargerNiveaux();
        // await chargerUEs();
    }

    async function onNiveauChange(e) {
        state.idNiveau = e.target.value || null;
        state.idSemestre = null;
        state.idOption = null;
        state.idUE = null;

        disableSelect('filterSemestre', 'Semestre');
        disableSelect('filterOption', 'Option');
        disableSelect('filterUE', 'UE');
        showEmpty('Sélectionnez une UE pour afficher le procès verbal.');

        if (!state.idNiveau) return;
        // await chargerSemestres();
        await chargerOptions();
        // await chargerUEs();
    }

    async function onSemestreChange(e) {
        state.idSemestre = e.target.value || null;
        state.idUE = null;

        disableSelect('filterUE', 'UE');
        showEmpty('Sélectionnez une UE pour afficher le procès verbal.');

        await chargerUEs();
    }

    async function onOptionChange(e) {
        state.idOption = e.target.value || null;
        state.idSemestre = null;
        state.idUE = null;

        disableSelect('filterSemestre', 'Semestre');
        disableSelect('filterUE', 'UE');
        showEmpty('Sélectionnez une UE pour afficher le procès verbal.');

        if (state.idOption) {
            await chargerSemestres();
        }
        // await chargerUEs();
    }

    async function onUEChange(e) {
        state.idUE = e.target.value || null;
        if (!state.idUE) {
            showEmpty('Sélectionnez une UE pour afficher le procès verbal.');
            return;
        }
        await chargerPV();
    }

    // ── Chargements ───────────────────────────────────────────────────────────

    async function chargerNiveaux() {
        if (!state.idFiliere) return;
        const params = { idFiliere: state.idFiliere };
        if (state.idCycle) params.idCycle = state.idCycle;
        try {
            const res = await get('getNiveaux', params);
            if (res.success && res.data.length) {
                fillSelect('filterNiveau', res.data, 'id', 'nom', 'Niveau');
            } else {
                disableSelect('filterNiveau', 'Aucun niveau');
            }
        } catch (e) {
            console.error('Erreur niveaux:', e);
        }
    }

    async function chargerOptions() {
        if (!state.idFiliere || !state.idNiveau) return;
        try {
            const res = await get('getOptions', { idFiliere: state.idFiliere, idNiveau: state.idNiveau });
            if (res.success && res.data.length) {
                fillSelect('filterOption', res.data, 'id', 'nom', 'Option');
            } else {
                disableSelect('filterOption', 'Aucune option');
            }
        } catch (e) {
            console.error('Erreur options:', e);
        }
    }

    async function chargerSemestres() {
        const params = {};
        if (state.idOption) params.idOption = state.idOption;
        if (state.idNiveau) params.idNiveau = state.idNiveau;
        else return;

        try {
            const res = await get('getSemestres', params);
            if (res.success && res.data.length) {
                fillSelect('filterSemestre', res.data, 'id', 'nom', 'Semestre');
            } else {
                disableSelect('filterSemestre', 'Aucun semestre');
            }
        } catch (e) {
            console.error('Erreur semestres:', e);
        }
    }

    async function chargerUEs() {
        const params = {};
        if (state.idFiliere) params.idFiliere = state.idFiliere;
        if (state.idNiveau) params.idNiveau = state.idNiveau;
        if (state.idOption) params.idOption = state.idOption;
        if (state.idSemestre) params.idSemestre = state.idSemestre;

        if (!state.idFiliere) {
            disableSelect('filterUE', 'UE');
            return;
        }

        try {
            const res = await get('getUEs', params);

            // ── Alerte semestre ───────────────────────────────────────────────
            const alerteContainer = document.getElementById('alerteSemestreContainer');
            if (alerteContainer) {
                if (res.alerte_semestre?.has_alert) {
                    const liste = res.alerte_semestre.ues_non_deliberees
                        .map(u => `<li>${u.code_ue} — ${u.nom_ue}</li>`)
                        .join('');
                    alerteContainer.innerHTML = `
                    <div class="alert alert-warning d-flex align-items-start gap-2 py-2 px-3">
                        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                        <div>
                            <strong>${res.alerte_semestre.message}</strong>
                            <ul class="mb-0 mt-1 small">${liste}</ul>
                        </div>
                    </div>`;
                    disableSelect('filterUE', 'Deliberation incomplete');

                } else {
                    alerteContainer.innerHTML = ''
                    // ── Remplir le select UE ──────────────────────────────────────────
                    if (res.success && res.data.length) {
                        const sel = document.getElementById('filterUE');
                        sel.innerHTML = '<option value="">— Unité d\'Enseignement —</option>';
                        res.data.forEach(ue => {
                            const opt = document.createElement('option');
                            opt.value = ue.idUE;
                            opt.textContent = `${ue.code_ue} — ${ue.nom_ue} (${ue.nb_etudiants} étud.)`;
                            sel.appendChild(opt);
                        });
                        sel.disabled = false;
                    } else {
                        disableSelect('filterUE', 'Aucune UE disponible');
                    }
                }
            } else {
                console.warn('div alert manquante')
            }



        } catch (e) {
            console.error('Erreur UEs:', e);
        }
    }

    // ── Chargement et rendu du PV ──────────────────────────────────────────────
    async function chargerPV() {
        if (!state.idUE) return;
        showLoader('Chargement du procès verbal…');

        try {
            const res = await get('getPV', { idUE: state.idUE });
            if (!res.success) {
                showEmpty(res.message || 'Aucune donnée disponible pour cette UE.');
                return;
            }
            state.pvData = res;
            renderPV(res);
            document.getElementById('exportBtns').style.removeProperty('display');
        } catch (e) {
            console.error('Erreur getPV:', e);
            showEmpty('Erreur lors du chargement du PV.');
        }
    }

    function renderPV(data) {
        const { ueInfo, annee, colonnesEC, etudiants, stats, vpc } = data;

        // ── Header colonnes EC ────────────────────────────────────────────────
        const thEC = colonnesEC.map(ec => `
            <th class="text-center" title="${esc(ec.nom)}">
                ${esc(ec.code || ec.nom.substring(0, 12))}
                <br><small class="text-muted fw-normal">(${ec.coef})</small>
            </th>`).join('');

        const creditTotal = colonnesEC.reduce((s, ec) => s + ec.credit, 0);

        // ── Lignes étudiants ──────────────────────────────────────────────────
        const lignes = etudiants.map((etudiant, i) => {
            const rowNum = i + 1;

            const tdNotes = colonnesEC.map(col => {
                const ec = etudiant.ecs.find(e => e.idEC === col.idEC);
                if (!ec) return `<td class="text-center text-muted">—</td>`;
                const note = ec.note_final;
                let cls = note >= 10 ? 'text-success fw-bold' : (note === 0 ? 'text-muted' : 'text-danger');
                if (ec.source_note === 'repechage') cls = 'text-primary fst-italic fw-bold';
                return `<td class="text-center ${cls}">${note.toFixed(2).replace('.', ',')}</td>`;
            }).join('');

            const moy = etudiant.moyenne_ue;
            const moyCls = moy >= 10 ? 'text-success fw-bold' : 'text-danger fw-bold';
            const moyTxt = moy.toFixed(2).replace('.', ',');

            let obsBadge, matieresHtml = '';
            if (etudiant.obs === 'Validée') {
                obsBadge = `<span class="badge badge-light-success">Validée</span>`;
            } else if (etudiant.obs === 'VPC') {
                obsBadge = `<span class="badge badge-light-warning" title="Validé par compensation (moy. nature : ${etudiant.moy_compensation})">VPC</span>`;
            } else if (etudiant.obs === 'Invalide') {
                obsBadge = `<span class="badge badge-light-danger" >Invalide</span>`;
                matieresHtml = etudiant.matieres_reprendre
                    .map(m => `<span class="badge badge-light-danger me-1 mb-1 fw-normal">${esc(m)}</span>`)
                    .join('');
            } else if (etudiant.obs === 'Absent') {
                obsBadge = `<span class="badge badge-light-danger" >Absent</span>`;
                matieresHtml = etudiant.matieres_reprendre
                    .map(m => `<span class="badge badge-light-danger me-1 mb-1 fw-normal">${esc(m)}</span>`)
                    .join('');
            } else {
                obsBadge = `<span class="badge badge-light-danger">Non validée</span>`;
                matieresHtml = etudiant.matieres_reprendre
                    .map(m => `<span class="badge badge-light-danger me-1 mb-1 fw-normal">${esc(m)}</span>`)
                    .join('');
            }

            const repBadge = etudiant.est_repeche
                ? `<span class="badge badge-light-primary ms-1" style="font-size:0.65rem;" title="Repêché">R</span>`
                : '';

            return `
            <tr>
                <td class="text-muted text-center">${rowNum}</td>
                <td class="text-muted" style="font-size:0.78rem;white-space:nowrap;">${esc(etudiant.matricule)}</td>
                <td class="fw-bold text-dark" style="white-space:nowrap;">${esc(etudiant.nom)}${repBadge}</td>
                <td style="white-space:nowrap;">${esc(etudiant.prenom)}</td>
                ${tdNotes}
                <td class="text-center ${moyCls}" style="font-size:1rem; background: #04683646;">${moyTxt}</td>
                <td class="text-center">${obsBadge}</td>
                <td style="font-size:0.78rem;">${matieresHtml}</td>
            </tr>`;
        }).join('');

        // ── Badges statistiques ───────────────────────────────────────────────
        const statBadges = `
            <span class="badge badge-light-dark me-2">Effectif : <strong>${stats.nbTotal}</strong></span>
            <span class="badge badge-light-dark me-2">Absents : <strong>${stats.nbTotal}</strong></span>
            <span class="badge badge-light-dark me-2">Ayant composés : <strong>${stats.effectifAyantCompose}</strong></span>
            <span class="badge badge-light-success me-2">Validés : <strong>${stats.nbValides}</strong></span>
            ${stats.nbVPC > 0 ? `<span class="badge badge-light-warning me-2">VPC : <strong>${stats.nbVPC}</strong></span>` : ''}
            <span class="badge badge-light-danger me-2">Non validés : <strong>${stats.nbNonValid}</strong></span>
            ${stats.nbRepeches > 0 ? `<span class="badge badge-light-primary me-2">Repêchés : <strong>${stats.nbRepeches}</strong></span>` : ''}
            <span class="badge badge-light-info me-2">Taux réussite : <strong>${stats.tauxReuss}%</strong></span>
        `;

        // ── HTML complet du PV ────────────────────────────────────────────────
        const html = `
        <div class="card" id="pvDocument">
            <!-- En-tête document -->
            <div class="card-header border-bottom pt-5 pb-4">
                <!-- Méta filière / UE / stats -->
                <div class="d-flex flex-wrap gap-4 mt-3 mb-2">
                    <div class="fs-7"><strong class="text-dark">Filière :</strong> <span class="text-muted">${esc(ueInfo.filiere || '')}</span></div>
                    <div class="fs-7"><strong class="text-dark">Niveau :</strong> <span class="text-muted">${esc(ueInfo.niveau || '')}</span></div>
                    <div class="fs-7"><strong class="text-dark">UE :</strong> <span class="text-muted">${esc(ueInfo.code_ue)} — ${esc(ueInfo.nom_ue)}</span></div>
                    <div class="fs-7"><strong class="text-dark">Crédits :</strong> <span class="text-muted">${creditTotal} crédits</span></div>
                </div>
                <div class="d-flex flex-wrap gap-1">${statBadges}</div>
            </div>

            <!-- Tableau -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0" id="pvTable">
                        <thead>
                            <tr>
                                <th rowspan="2" class="text-center text-muted fw-semibold" style="width:40px;">#</th>
                                <th rowspan="2" class="text-muted fw-semibold" style="min-width:130px;">Matricule</th>
                                <th rowspan="2" class="fw-semibold" style="min-width:120px;">Noms</th>
                                <th rowspan="2" class="fw-semibold" style="min-width:120px;">Prénoms</th>
                                <th colspan="${colonnesEC.length + 1}" class="text-center fw-bold" style="background:#1e706da7;color:white;">
                                    ${esc(ueInfo.nom_ue)} (${creditTotal} crédits)
                                </th>
                                <th rowspan="2" class="text-center fw-semibold">Obs</th>
                                <th rowspan="2" class="fw-semibold" style="min-width:200px;">Matières à reprendre</th>
                            </tr>
                            <tr>
                                ${thEC}
                                <th class="text-center fw-bold" style="background:#4cc98f;color:white;min-width:70px;">Moy. UE</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${lignes}
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pied document -->
            <div class="card-footer d-flex justify-content-between align-items-end flex-wrap gap-4 pt-4">
                <div>
                    <div class="fs-7 text-muted">
                        <strong>Effectif total :</strong> ${stats.nbTotal} &nbsp;|&nbsp;
                        <strong>Ayant composés :</strong> ${stats.effectifAyantCompose} &nbsp;|&nbsp;
                        <strong>Validés :</strong> ${stats.nbValides} &nbsp;|&nbsp;
                        ${stats.nbVPC > 0 ? `<strong>VPC :</strong> ${stats.nbVPC} &nbsp;|&nbsp;` : ''}
                        <strong>Non validés :</strong> ${stats.nbNonValid}
                        ${stats.nbRepeches > 0 ? ` &nbsp;|&nbsp; <strong>Repêchés :</strong> ${stats.nbRepeches}` : ''}
                    </div>
                    <div class="fs-8 text-muted mt-1">
                        <span class="badge badge-light-primary me-1">R</span> = Repêché
                        ${vpc?.nb > 0 ? `&nbsp;&nbsp;<span class="badge badge-light-warning me-1">VPC</span> = Validé par compensation` : ''}
                    </div>
                </div>
                
            </div>
        </div>`;

        document.getElementById('pvZone').innerHTML = html;
    }

    // ── Utilitaire échappement HTML ───────────────────────────────────────────
    function esc(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&')
            .replace(/</g, '<')
            .replace(/>/g, '>')
            .replace(/"/g, '"');
    }

    // ── Export Impression ─────────────────────────────────────────────────────
    function imprimer() {
        const pvEl = document.getElementById('pvDocument');
        if (!pvEl) return;

        const printCSS = `
            <style>
                body { font-family: 'Poppins', sans-serif; font-size: 8pt; }
                .table { width:100%; border-collapse:collapse; }
                .table th, .table td { border:1px solid #ccc; padding:2pt 4pt; }
                .table thead th { background:#0d2240!important; color:white!important; -webkit-print-color-adjust:exact; print-color-adjust:exact; font-size:7pt; }
                .badge { display:inline-block; padding:1pt 4pt; border-radius:3pt; font-size:7pt; font-weight:600; }
                .badge-light-success { background:#d4edda; color:#155724; }
                .badge-light-danger  { background:#f8d7da; color:#721c24; }
                .badge-light-warning { background:#fff3cd; color:#856404; }
                .badge-light-primary { background:#cce5ff; color:#004085; }
                .text-success { color:#155724!important; }
                .text-danger  { color:#721c24!important; }
                .text-primary { color:#004085!important; }
                .rounded      { border-radius:4pt!important; }
            </style>`;

        const win = window.open('', '_blank');
        win.document.write(`<!DOCTYPE html><html><head>${printCSS}</head><body>${pvEl.outerHTML}</body></html>`);
        win.document.close();
        win.focus();
        setTimeout(() => { win.print(); win.close(); }, 600);
    }

    // ── Export Excel ──────────────────────────────────────────────────────────
    function exporterExcel() {
        if (!state.pvData) return;
        const { ueInfo, annee, colonnesEC, etudiants, stats } = state.pvData;

        const wb = XLSX.utils.book_new();
        const wsData = [];

        // Métadonnées
        wsData.push(['UNIVERSITÉ AMADOU HAMPÂTÉ BÂ DE DAKAR']);
        wsData.push(['Procès Verbal de Délibération']);
        wsData.push(['Filière :', ueInfo.filiere || '']);
        wsData.push(['Niveau :', ueInfo.niveau || '']);
        wsData.push(['UE :', `${ueInfo.code_ue} — ${ueInfo.nom_ue}`]);
        wsData.push(['Année :', annee || '']);
        wsData.push(['Date :', new Date().toLocaleDateString('fr-FR')]);
        wsData.push([]);

        // En-têtes
        const hdr1 = ['#', 'Matricule', 'Noms', 'Prénoms',
            ...colonnesEC.map(ec => `${ec.code || ec.nom} (coef:${ec.coef})`),
            'Moy. UE', 'Obs', 'Matières à reprendre'
        ];
        wsData.push(hdr1);

        // Lignes
        etudiants.forEach((etudiant, i) => {
            const notes = colonnesEC.map(col => {
                const ec = etudiant.ecs.find(e => e.idEC === col.idEC);
                return ec ? ec.note_final : '';
            });
            wsData.push([
                i + 1,
                etudiant.matricule,
                etudiant.nom + (etudiant.est_repeche ? ' [R]' : ''),
                etudiant.prenom,
                ...notes,
                etudiant.moyenne_ue,
                etudiant.obs,
                etudiant.matieres_reprendre.join(' ; ')
            ]);
        });

        // Stats finales
        wsData.push([]);
        wsData.push([
            'Effectif total :', stats.nbTotal,
            'Ayant compose :', stats.effectifAyantCompose,
            'Validés :', stats.nbValides,
            'Non validés :', stats.nbNonValid,
            'Repêchés :', stats.nbRepeches,
            'Taux réussite :', `${stats.tauxReuss}%`
        ]);

        const ws = XLSX.utils.aoa_to_sheet(wsData);
        ws['!cols'] = [
            { wch: 5 }, { wch: 22 }, { wch: 22 }, { wch: 22 },
            ...colonnesEC.map(() => ({ wch: 10 })),
            { wch: 10 }, { wch: 14 }, { wch: 40 }
        ];

        const nomFichier = `PV_${(ueInfo.code_ue || 'UE').replace(/[^a-zA-Z0-9]/g, '_')}_${new Date().toISOString().slice(0, 10)}`;
        XLSX.utils.book_append_sheet(wb, ws, 'PV Délibération');
        XLSX.writeFile(wb, `${nomFichier}.xlsx`);
    }

    // ── Export PDF ────────────────────────────────────────────────────────────
    // ── Export PDF ────────────────────────────────────────────────────────────
    // ── Export PDF ────────────────────────────────────────────────────────────
    function exporterPDF() {
        if (!state.pvData) return;
        const { ueInfo, annee, colonnesEC, etudiants, stats } = state.pvData;
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'p', unit: 'mm', format: 'a3' });
        const W = doc.internal.pageSize.width;

        // Charger les deux logos
        const imgUAHB = new Image();
        const imgGSJLF = new Image();
        imgUAHB.crossOrigin = 'anonymous';
        imgGSJLF.crossOrigin = 'anonymous';
        imgUAHB.src = '../../../dist_assets/media/logos/uahb.png';
        imgGSJLF.src = '../../../dist_assets/media/logos/CMJLF.jpeg';  // ← ton logo GSJLF

        let loaded = 0;
        const onLoad = () => {
            loaded++;
            if (loaded === 2) _genererPDF(doc, W, ueInfo, annee, colonnesEC, etudiants, stats, imgUAHB, imgGSJLF);
        };
        imgUAHB.onload = onLoad;
        imgGSJLF.onload = onLoad;
        imgUAHB.onerror = onLoad;  // continue même si un logo manque
        imgGSJLF.onerror = onLoad;
    }

    function _imgToBase64(imgEl) {
        const canvas = document.createElement('canvas');
        canvas.width = imgEl.naturalWidth;
        canvas.height = imgEl.naturalHeight;
        canvas.getContext('2d').drawImage(imgEl, 0, 0);
        return canvas.toDataURL('image/png');
    }

    function _genererPDF(doc, W, ueInfo, annee, colonnesEC, etudiants, stats, imgUAHB, imgGSJLF) {
        const logoH = 22;
        const logoW = 22;
        const headerH = 44;
        const cx = W / 2;
        const COLOR = [36, 103, 92];

        // ── En-tête ───────────────────────────────────────────────────────────
        doc.setDrawColor(200, 200, 200);
        doc.setLineWidth(0.3);
        doc.line(0, headerH, W, headerH);

        // Logos
        if (imgGSJLF && imgGSJLF.naturalWidth > 0) {
            const b64Left = _imgToBase64(imgGSJLF);
            doc.addImage(b64Left, 'JPEG', W * 0.22, (headerH - logoH) / 2, logoW, logoH);
        }
        if (imgUAHB && imgUAHB.naturalWidth > 0) {
            const b64Right = _imgToBase64(imgUAHB);
            doc.addImage(b64Right, 'PNG', W * 0.78 - logoW, (headerH - logoH) / 2, logoW, logoH);
        }

        // Groupe scolaire
        doc.setTextColor(120);
        doc.setFont('helvetica', 'italic');
        doc.setFontSize(9);
        doc.text('Groupe Scolaire Jean de la Fontaine', cx, 7, { align: 'center' });

        // Université
        doc.setTextColor(36, 103, 92);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(13);
        doc.text('UNIVERSITE AMADOU HAMPATE BA DE DAKAR', cx, 13, { align: 'center' });

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.setTextColor(120);
        doc.text('-=-=-=- UAHB -=-=-=-', cx, 19, { align: 'center' });

        doc.setDrawColor(180);
        doc.setLineDashPattern([1, 1], 0);
        doc.line(cx - 35, 22, cx + 35, 22);
        doc.setLineDashPattern([], 0);

        // Faculté
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(10);
        doc.setTextColor(36, 103, 92);
        doc.text((ueInfo.faculte || '').toUpperCase(), cx, 28, { align: 'center' });

        doc.setDrawColor(180);
        doc.setLineDashPattern([1, 1], 0);
        doc.line(cx - 32, 31, cx + 32, 31);
        doc.setLineDashPattern([], 0);

        // Département
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9);
        doc.setTextColor(36, 103, 92);
        doc.text((ueInfo.departement || '').toUpperCase(), cx, 37, { align: 'center' });

        // ── Bandeau ───────────────────────────────────────────────────────────
        const bandeauY = headerH + 5;
        const bandeauH = 24;
        const bandeauW = W * 0.68;
        const bandeauX = (W - bandeauW) / 2;

        doc.setFillColor(36, 103, 92);
        doc.roundedRect(bandeauX, bandeauY, bandeauW, bandeauH, 4, 4, 'F');

        doc.setTextColor(255, 255, 255);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(13);
        doc.text('PROCÈS VERBAL DE DÉLIBÉRATION PAR UE', cx, bandeauY + 7, { align: 'center' });

        doc.setFontSize(11);
        doc.text(
            'Semestre ' + (ueInfo.semestre || '') + '  —  Session ' + (ueInfo.idSession || ''),
            cx, bandeauY + 15, { align: 'center' }
        );

        doc.setFontSize(10);
        doc.text('Année Académique : ' + (annee || ''), cx, bandeauY + 22, { align: 'center' });

        // ── Méta sous le bandeau ──────────────────────────────────────────────
        const infoY = bandeauY + bandeauH + 7;

        doc.setFontSize(9);
        doc.setTextColor(0);
        doc.setFont('helvetica', 'normal');
        doc.text('Filière : ' + (ueInfo.filiere || ''), 12, infoY);
        doc.text('Classe  : ' + (ueInfo.niveau || '') + ' ' + (ueInfo.option_etudiant || '') , 12, infoY + 6);
        doc.text('Semestre ' + ueInfo.numero+' UE : ' + (ueInfo.code_ue || '') + ' — ' + (ueInfo.nom_ue || ''), 12, infoY + 12);

        doc.setTextColor(60);
        doc.setFontSize(8.5);
        doc.text('Date : ' + new Date().toLocaleDateString('fr-FR'), W - 12, infoY + 12, { align: 'right' });

        // ── Tableau ───────────────────────────────────────────────────────────
        const head = [[
            '#', 'Matricule', 'Prénom(s)', 'Nom(s)',
            ...colonnesEC.map(ec => `${ec.code || ec.nom}\n(coef ${ec.coef})`),
            'Moy.\nUE', 'Obs.', 'Matières à reprendre'
        ]];

        const body = etudiants.map((etudiant, i) => {
            const notes = colonnesEC.map(col => {
                const ec = etudiant.ecs.find(e => e.idEC === col.idEC);
                return ec ? ec.note_final.toFixed(2).replace('.', ',') : '—';
            });

            const matieresInvalide = (etudiant.matieres_invalide || []).map(ec => ec.nom_ec).join(' ; ');
            const matieresReprendre = (etudiant.matieres_reprendre || []).join(' ; ');
            const matieres = matieresReprendre || matieresInvalide;

            return [
                i + 1,
                etudiant.matricule,
			    etudiant.prenom,
                etudiant.nom,
                ...notes,
                etudiant.moyenne_ue.toFixed(2).replace('.', ','),
                etudiant.obs,
                matieres
            ];
        });

        const lastObsCol = 4 + colonnesEC.length + 1;
        const lastReprendreCol = 4 + colonnesEC.length + 2;

        doc.autoTable({
            head,
            showHead: 'everyPage', // ← ajouter
            body,
            startY: infoY + 18,
            styles: {
                fontSize: 8,
                cellPadding: 2,
                overflow: 'linebreak',
                minCellHeight: 9,
            },
            headStyles: {
                fillColor: [36, 103, 92],
                fontSize: 8,
                fontStyle: 'bold',
                halign: 'center',
                cellPadding: 2.5,
            },
            alternateRowStyles: { fillColor: [245, 243, 238] },
            columnStyles: {
                0: { halign: 'center', cellWidth: 9 },
                1: { cellWidth: 30, fontSize: 7.5 },
                2: { cellWidth: 38 },
                3: { cellWidth: 38 },
                [4 + colonnesEC.length]: {
                    halign: 'center',
                    fontStyle: 'bold',
                    fillColor: [209, 227, 224],
                    fontSize: 9,
                },
                [lastObsCol]: { halign: 'center', cellWidth: 18 },
                [lastReprendreCol]: { fontSize: 7 },
            },
			margin: { left: 10, right: 10, bottom: 20 },
            didParseCell: (hookData) => {
                const colIdx = hookData.column.index;

                // Notes EC
                if (colIdx >= 4 && colIdx < 4 + colonnesEC.length) {
                    const val = parseFloat(String(hookData.cell.raw).replace(',', '.'));
                    if (!isNaN(val)) {
                        hookData.cell.styles.textColor = val >= 10 ? [21, 87, 36] : [114, 28, 36];
                        hookData.cell.styles.halign = 'center';
                        hookData.cell.styles.fontSize = 8;
                    }
                }

                // Moy. UE
                if (colIdx === 4 + colonnesEC.length && hookData.section === 'body') {
                    const val = parseFloat(String(hookData.cell.raw).replace(',', '.'));
                    if (!isNaN(val)) {
                        hookData.cell.styles.textColor = val >= 10 ? [21, 87, 36] : [114, 28, 36];
                        hookData.cell.styles.fontStyle = 'bold';
                        hookData.cell.styles.fontSize = 9;
                    }
                }

                // Observation
                if (colIdx === lastObsCol && hookData.section === 'body') {
                    const val = String(hookData.cell.raw).trim();
                    hookData.cell.styles.fontStyle = 'bold';
                    hookData.cell.styles.halign = 'center';
                    hookData.cell.styles.fontSize = 8;
                    hookData.cell.styles.textColor = val === 'Validée' ? [21, 87, 36]
                        : val === 'VPC' ? [133, 100, 4] : [114, 28, 36];
                }
            },
            margin: { left: 10, right: 10 },
            didDrawPage: (data) => {
                const pageNum = doc.internal.getCurrentPageInfo().pageNumber;
                doc.setFontSize(8);
                doc.setTextColor(120);
                doc.text(
                    `Effectif : ${stats.nbTotal}  |  Ayant composés : ${stats.effectifAyantCompose}  |  Validés : ${stats.nbValides}  |  Non validés : ${stats.nbNonValid}  —  Page ${pageNum}`,
                    W / 2, doc.internal.pageSize.height - 5, { align: 'center' }
                );
            }
        });
        // ── Légende codes UE ─────────────────────────────────────────────────
        const legendeY = doc.lastAutoTable.finalY + 6;

        // Titre
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(7.5);
        doc.setTextColor(...COLOR);
        doc.text('Légende :', 12, legendeY);

        // Lignes de légende
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7);
        doc.setTextColor(60, 60, 60);

        const colonnesParLigne = 3;
        const colLargeur = (W - 24) / colonnesParLigne;
        let lx = 12;
        let ly = legendeY + 5;

        colonnesEC.forEach((ec, i) => {
            if (i > 0 && i % colonnesParLigne === 0) {
                lx = 12;
                ly += 5;
            }
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(...COLOR);
            doc.text(`${ec.code}`, lx, ly);
            const codeW = doc.getTextWidth(`${ec.code}`) + 2;
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(60, 60, 60);
            const nomTronqec = ec.nom.length > 40 ? ec.nom.substring(0, 40) + '…' : ec.nom;
            doc.text(`: ${nomTronqec}`, lx + codeW, ly);
            lx += colLargeur;
        });

        // Ligne séparatrice sous la légende
        const sepY = ly + 4;
        doc.setDrawColor(200, 200, 200);
        doc.setLineWidth(0.3);
        doc.line(12, sepY, W - 12, sepY);

        // Décaler finalY pour les stats
        const finalY = sepY + 6;
        // ── Cartes stats ──────────────────────────────────────────────────────
        // const finalY = doc.lastAutoTable.finalY + 8;

        const tauxEchec = (100 - stats.tauxReuss).toFixed(1) + '%';

const statItems = [
    { label: 'Effectif total',          val: stats.nbTotal,                        color: [70,  70,  80],   sublabel: null },
    { label: 'Absents',                 val: stats.nbAbsents ?? 0,                 color: [180, 70,  70],   sublabel: null },
    { label: 'Ayant composés',          val: stats.nbTotal - stats.nbAbsents,      color: [70,  70,  80],   sublabel: null },
    { label: 'Validés par compensation',val: stats.nbVPC ?? 0,                     color: [255, 165, 0],    sublabel: null },
    { label: 'Validées directement',                 val: stats.nbValides,                      color: [46,  204, 64],   sublabel: null },  // Vert clair vif
    { label: 'Totale validées',           val: (stats.nbValides + (stats.nbVPC ?? 0)), color: [20, 90, 30],  sublabel: stats.tauxReuss + '%' },  // Vert foncé
    { label: 'Non validées',             val: stats.nbNonValid,                     color: [200, 60,  60],   sublabel: tauxEchec },
    { label: 'Invalides',               val: stats.nbInvalide ?? 0,                color: [150, 90,  110],  sublabel: null },
];


        const boxW = 33;
        const boxH = 18; // légèrement augmenté pour loger le sublabel
        const gap = 2;
        const totalW = statItems.length * boxW + (statItems.length - 1) * gap;
        let startX = (W - totalW) / 2;

        statItems.forEach(item => {
            // Fond
            doc.setFillColor(...item.color.map(c => Math.min(255, c + 170)));
            doc.roundedRect(startX, finalY, boxW, boxH, 2, 2, 'F');
            // Bordure
            doc.setDrawColor(...item.color);
            doc.setLineWidth(0.4);
            doc.roundedRect(startX, finalY, boxW, boxH, 2, 2, 'S');
            // Valeur principale
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(12);
            doc.setTextColor(...item.color);
            doc.text(String(item.val), startX + boxW / 2, finalY + 7, { align: 'center' });
            // Label
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(7);
            doc.setTextColor(90, 90, 90);
            doc.text(item.label, startX + boxW / 2, finalY + 12, { align: 'center' });
            // Sublabel (taux) en bold coloré
            if (item.sublabel) {
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(6.5);
                doc.setTextColor(...item.color);
                doc.text(item.sublabel, startX + boxW / 2, finalY + 16.5, { align: 'center' });
            }
            startX += boxW + gap;
        });
                // ── Signatures ────────────────────────────────────────────────────────────────
const visaY    = finalY + boxH + 14;
const membres  = ['Président du jury', 'Membres du jury', 'Visa académique'];
const sigW     = W / membres.length;

// Vérifier si les signatures tiennent sur la page courante
// Si pas assez d'espace (30mm pour signatures + marge pied de page 10mm)
const H = doc.internal.pageSize.getHeight();
if (visaY + 30 > H - 10) {
    doc.addPage();
    const visaYNew = 20;
    membres.forEach((titre, i) => {
        const sigCx = sigW * i + sigW / 2;
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8.5);
        doc.setTextColor(40, 40, 40);
        doc.text(titre, sigCx, visaYNew, { align: 'center' });

        const lineY = visaYNew + 20;
        doc.setDrawColor(150, 150, 150);
        doc.setLineWidth(0.4);
        // doc.line(sigCx - 35, lineY, sigCx + 35, lineY);

        // doc.setFont('helvetica', 'normal');
        // doc.setFontSize(7);
        // doc.setTextColor(150, 150, 150);
        // doc.text('Nom, Signature & cachet', sigCx, lineY + 5, { align: 'center' });
    });
} else {
    membres.forEach((titre, i) => {
        const sigCx = sigW * i + sigW / 2;
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8.5);
        doc.setTextColor(40, 40, 40);
        doc.text(titre, sigCx, visaY, { align: 'center' });

        const lineY = visaY + 20;
        doc.setDrawColor(150, 150, 150);
        doc.setLineWidth(0.4);
        // doc.line(sigCx - 35, lineY, sigCx + 35, lineY);

        // doc.setFont('helvetica', 'normal');
        // doc.setFontSize(7);
        // doc.setTextColor(150, 150, 150);
        // doc.text('Nom, Signature & cachet', sigCx, lineY + 5, { align: 'center' });
    });
}
        const nomFichier = `PV_${(ueInfo.code_ue || 'UE').replace(/[^a-zA-Z0-9]/g, '_')}_${new Date().toISOString().slice(0, 10)}`;
        doc.save(`${nomFichier}.pdf`);
    }

    // ── API publique ──────────────────────────────────────────────────────────
    return { init, imprimer, exporterExcel, exporterPDF };

})();

// Démarrage
document.addEventListener('DOMContentLoaded', () => pvParUE.init());