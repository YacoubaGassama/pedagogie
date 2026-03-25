/**
 * pvParSemestre.js
 * PV de Délibération par Semestre
 * Filtres : Filière → Cycle → Niveau → Option → Semestre
 * Tableau : colonnes UE + Moy. Semestre + Crédits validés + Statut
 */

const pvParSemestre = (() => {

    const CONTROLLER = 'pvParSemestreController.php';

    const state = {
        idFiliere: null,
        idCycle: null,
        idNiveau: null,
        idOption: null,
        idSemestre: null,
        pvData: null,
    };

    // ── Helpers ───────────────────────────────────────────────────────────────
    function get(action, params = {}) {
        const qs = new URLSearchParams({ action, ...params }).toString();
        return fetch(`${CONTROLLER}?${qs}`).then(r => r.json());
    }

    function disableSelect(id, placeholder = '—') {
        const sel = document.getElementById(id);
        sel.innerHTML = `<option value="">— ${placeholder} —</option>`;
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

    function esc(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"');
    }

    // ── Init ──────────────────────────────────────────────────────────────────
    async function init() {
        try {
            const res = await get('getFilieres');
            if (res.success && res.data.length) fillSelect('filterFiliere', res.data, 'id', 'nom', 'Filière');
        } catch (e) { console.error(e); }

        document.getElementById('filterFiliere').addEventListener('change', onFiliereChange);
        document.getElementById('filterCycle').addEventListener('change', onCycleChange);
        document.getElementById('filterNiveau').addEventListener('change', onNiveauChange);
        document.getElementById('filterOption').addEventListener('change', onOptionChange);
        document.getElementById('filterSemestre').addEventListener('change', onSemestreChange);
    }

    // ── Cascade ───────────────────────────────────────────────────────────────
    async function onFiliereChange(e) {
        state.idFiliere = e.target.value || null;
        state.idCycle = state.idNiveau = state.idOption = state.idSemestre = null;
        document.getElementById('filterCycle').value = '';
        document.getElementById('filterCycle').disabled = !state.idFiliere;
        disableSelect('filterNiveau', 'Niveau');
        disableSelect('filterOption', 'Option');
        disableSelect('filterSemestre', 'Semestre');
        showEmpty('Sélectionnez un semestre pour afficher le procès verbal.');
        if (!state.idFiliere) return;
        await chargerNiveaux();
    }

    async function onCycleChange(e) {
        state.idCycle = e.target.value || null;
        state.idNiveau = state.idOption = state.idSemestre = null;
        disableSelect('filterNiveau', 'Niveau');
        disableSelect('filterOption', 'Option');
        disableSelect('filterSemestre', 'Semestre');
        showEmpty('Sélectionnez un semestre pour afficher le procès verbal.');
        if (!state.idFiliere) return;
        await chargerNiveaux();
    }

    async function onNiveauChange(e) {
        state.idNiveau = e.target.value || null;
        state.idOption = state.idSemestre = null;
        disableSelect('filterOption', 'Option');
        disableSelect('filterSemestre', 'Semestre');
        showEmpty('Sélectionnez un semestre pour afficher le procès verbal.');
        if (!state.idNiveau) return;
        await chargerOptions();
        await chargerSemestres();
    }

    async function onOptionChange(e) {
        state.idOption = e.target.value || null;
        state.idSemestre = null;
        disableSelect('filterSemestre', 'Semestre');
        showEmpty('Sélectionnez un semestre pour afficher le procès verbal.');
        await chargerSemestres();
    }

    async function onSemestreChange(e) {
        state.idSemestre = e.target.value || null;
        if (!state.idSemestre) {
            showEmpty('Sélectionnez un semestre pour afficher le procès verbal.');
            return;
        }
        await chargerPV();
    }

    // ── Chargements ───────────────────────────────────────────────────────────
    async function chargerNiveaux() {
        const params = { idFiliere: state.idFiliere };
        if (state.idCycle) params.idCycle = state.idCycle;
        try {
            const res = await get('getNiveaux', params);
            if (res.success && res.data.length) fillSelect('filterNiveau', res.data, 'id', 'nom', 'Niveau');
            else disableSelect('filterNiveau', 'Aucun niveau');
        } catch (e) { console.error(e); }
    }

    async function chargerOptions() {
        if (!state.idFiliere || !state.idNiveau) return;
        try {
            const res = await get('getOptions', { idFiliere: state.idFiliere, idNiveau: state.idNiveau });
            if (res.success && res.data.length) fillSelect('filterOption', res.data, 'id', 'nom', 'Option');
            else disableSelect('filterOption', 'Aucune option');
        } catch (e) { console.error(e); }
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
        } catch (e) { console.error(e); }
    }

    // ── PV ────────────────────────────────────────────────────────────────────
    async function chargerPV() {
        showLoader('Chargement du procès verbal…');
        const params = { idSemestre: state.idSemestre };
        if (state.idOption) params.idOption = state.idOption;
        else if (state.idNiveau) params.idNiveau = state.idNiveau;

        try {
            const res = await get('getPV', params);

            if (!res.success && res.non_deliberees) {
                const lignesUE = res.ues_manquantes
                    .map(ue => `<li class="py-1"><span class="badge badge-light-warning me-2">${esc(ue.code_ue)}</span>${esc(ue.nom_ue)}</li>`)
                    .join('');

                document.getElementById('pvZone').innerHTML = `
                    <div class="card border-warning">
                        <div class="card-body py-8 px-8">
                            <div class="d-flex align-items-center mb-4">
                                <i class="fas fa-exclamation-triangle text-warning fs-2x me-3"></i>
                                <div>
                                    <h4 class="text-dark fw-bolder mb-1">Délibération incomplète</h4>
                                    <p class="text-muted mb-0">
                                        <strong>${res.nb_deliberees}</strong> UE(s) délibérée(s) sur
                                        <strong>${res.nb_maquette}</strong> dans la maquette.
                                        Le PV ne peut pas être généré tant que toutes les UEs n'ont pas été délibérées.
                                    </p>
                                </div>
                            </div>
                            <div class="separator separator-dashed mb-4"></div>
                            <p class="fw-bold text-danger mb-2">
                                <i class="fas fa-times-circle text-danger me-1"></i>
                                ${res.nb_manquantes} UE(s) non délibérée(s) :
                            </p>
                            <ul class="list-unstyled ps-3">${lignesUE}</ul>
                        </div>
                    </div>`;
                document.getElementById('exportBtns').style.display = 'none';
                return;
            }

            if (!res.success) { showEmpty(res.message || 'Aucune donnée.'); return; }
            state.pvData = res;
            renderPV(res);
            document.getElementById('exportBtns').style.removeProperty('display');
        } catch (e) {
            console.error(e);
            showEmpty('Erreur lors du chargement du PV.');
        }
    }
// ── Ajouter juste avant la fonction renderPV ──────────────────────────────────

let sortConfig = { key: null, dir: 'asc' };

function sortEtudiants(etudiants, key, dir) {
    return [...etudiants].sort((a, b) => {
        let va, vb;
        if (key === 'matricule') {
            va = a.matricule || '';
            vb = b.matricule || '';
            return dir === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
        }
        if (key === 'moyenne_sem') {
            va = a.est_enjambiste ? -1 : (a.moyenne_sem ?? 0);
            vb = b.est_enjambiste ? -1 : (b.moyenne_sem ?? 0);
            return dir === 'asc' ? va - vb : vb - va;
        }
        return 0;
    });
}
    // ── Rendu HTML ────────────────────────────────────────────────────────────
    function renderPV(data) {
        const { semInfo, annee, ues, totalCredits, etudiants, stats } = data;

        // En-têtes UE (bi-niveaux)
        const thUEs = ues.map(ue => `
            <th class="text-center" title="${esc(ue.nom_ue)}">
                ${esc(ue.code_ue)}
                <br><small class="text-muted fw-normal">(${ue.total_credits} cr.) ${ue.id_nature == 1 ? 'F' : 'C'}</small>
            </th>`).join('');

        // Lignes étudiants
        const lignes = etudiants.map((etudiant, i) => {
            const compensees = new Set(etudiant.ues_compensees || []);
            const invalides = new Set(
                Array.isArray(etudiant.ues_invalides)
                    ? etudiant.ues_invalides
                    : Object.keys(etudiant.ues_invalides || {}).map(Number)
            );
            let aUEManquante = false
            let creditEnjambiste = 0
            const moy = etudiant.moyenne_sem;
            const moyCls = moy >= 10 ? 'text-success fw-bold' : 'text-danger fw-bold';
            const tdUEs = ues.map(ue => {
                const moy = etudiant.moyennes_ue[ue.idUE];
                if (moy === null || moy === undefined) {
                    aUEManquante = true
                    return `<td class="text-center text-muted">—</td>`;
                }
                if (etudiant?.est_enjambiste && !invalides.has(ue.idUE)) {
                    const cls = moy >= 10 ? 'text-success fw-bold' : 'text-danger';

                    return `
                        <td class="text-center">
                            <div class="${moyCls} small">${moy.toFixed(2).replace('.', ',')}</div>
                            <div class="form-check d-flex justify-content-center mt-1" title="Activer la compensation pour cette UE">
                                <input class="form-check-input switch-vpc-enjambiste"
                                    type="checkbox"
                                    data-matricule="${etudiant.matricule}"
                                    data-id-ue="${ue.idUE}"
                                    ${etudiant.vpc_enjambiste ? 'checked' : ''}
                                    style="width:14px;height:14px;cursor:pointer;">
                            </div>
                        </td>`;
                }
                if (compensees.has(ue.idUE)) {
                    return `<td class="text-center fw-bold" style="color:#856404;background:#fffbe6;" title="Validée par compensation">${moy.toFixed(2).replace('.', ',')} <small>VPC</small></td>`;
                }
                const cls = moy >= 10 ? 'text-success fw-bold' : 'text-danger';
                return `<td class="text-center ${cls}">${moy.toFixed(2).replace('.', ',')}</td>`;
            }).join('');


            let statutBadge;
            const creditAffiche = etudiant.est_enjambiste ? (etudiant.enjambisteCredit + etudiant.creditsVPC) : etudiant.creditsVPC
            if (etudiant.statut === 'Invalide' && creditAffiche == 30) {
                statutBadge = `<span class="badge badge-light-danger fw-bold">Invalide</span>`;
            } else if (etudiant.statut === 'Semestre validé' || etudiant.statut === 'Semestre validé par compensation') {
                statutBadge = `<span class="badge badge-light-success fw-bold">${esc(etudiant.statut)}</span>`;
            } else if (etudiant.statut === 'Invalide' && creditAffiche < 30) {
                statutBadge = `<span class="badge badge-light-danger fw-bold">Semestre non validé</span>`;
            }
            //  else if (etudiant.est_enjambiste && creditAffiche == 30) {
            //     statutBadge = `
            //     <div class="form-check form-switch">
            //         <input class="form-check-input switch-vpc-enjambiste"
            //             type="checkbox"
            //             id="activeCompensation_${etudiant.matricule}"
            //             data-matricule="${etudiant.matricule}"
            //             data-id-session="${etudiant.idSession ?? ''}"

            //             ${etudiant.vpc_enjambiste ? 'checked' : ''}>
            //         <label class="form-check-label fw-bold" for="activeCompensation_${etudiant.matricule}">
            //             Activer la compensation
            //         </label>
            //     </div>`;
            // }
            else {
                statutBadge = `<span class="badge badge-light-danger fw-bold">${esc(etudiant.statut)}</span>`;
            }
            const repBadge = etudiant.est_repeche
                ? `<span class="badge badge-light-primary ms-1" style="font-size:0.65rem;">R</span>`
                : '';
            return `
            <tr>
                <td class="text-muted text-center">${i + 1}</td>
                <td class="text-muted" style="font-size:0.78rem;white-space:nowrap;">${esc(etudiant.matricule)}</td>
                <td style="white-space:nowrap;">${esc(etudiant.prenom)}</td>
                <td class="fw-bold" style="white-space:nowrap;">${esc(etudiant.nom)}</td>
                ${tdUEs}
                <td class="text-center ${moyCls}" style="font-size:1rem; background: #04683646;"> ${!etudiant.est_enjambiste ? moy.toFixed(2).replace('.', ',') : '—'}</td>
                <td class="text-center fw-bold">${creditAffiche} / ${totalCredits}</td>
                <td class="text-center">${statutBadge}</td>
            </tr>`;
        }).join('');

        // Badges stats
        const statBadges = `
            <span class="badge badge-light-dark me-2">Effectif Total : <strong>${stats.nbTotal}</strong></span>
            ${`<span class="badge badge-light-danger me-2">Absents : <strong>${stats.nbAbsents}</strong></span>`}
            ${`<span class="badge badge-light-danger me-2">Invalides : <strong>${stats.nbInvalide}</strong></span>`}
            <span class="badge badge-light-success me-2">Validés : <strong>${stats.nbValides}</strong></span>
            ${stats.nbVPC > 0 ? `<span class="badge fw-bold me-2" style="background:#fff3cd;color:#856404;">VPC : <strong>${stats.nbVPC}</strong></span>` : ''}
            <span class="badge badge-light-danger me-2">Non validés : <strong>${stats.nbNonValid}</strong></span>
            <span class="badge badge-light-info me-2">Taux réussite : <strong>${stats.tauxReuss}%</strong></span>
        `;

        const html = `
        <div class="card" id="pvDocument">
            <div class="card-header border-bottom pt-5 pb-4">
                <div class="d-flex flex-wrap gap-4 mt-3 mb-2">
                    <div class="fs-7"><strong class="text-dark">Filière :</strong> <span class="text-muted">${esc(semInfo.filiere || '')}</span></div>
                    <div class="fs-7"><strong class="text-dark">Classe :</strong> <span class="text-muted">${esc(semInfo.niveau || '')} ${esc(semInfo.option_etudiant || '')}</span></div>
                    <div class="fs-7"><strong class="text-dark">Semestre :</strong> <span class="text-muted">${esc(semInfo.nom_semestre || '')}</span></div>
                    <div class="fs-7"><strong class="text-dark">Total crédits :</strong> <span class="text-muted">${totalCredits} crédits</span></div>
                </div>
                <div class="d-flex flex-wrap gap-1">${statBadges}</div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0" id="pvTable">
                        <thead>
                            <tr>
                                <th rowspan="2" class="text-center text-muted fw-semibold" style="width:40px;">#</th>
                                <th rowspan="2" class="text-muted fw-semibold sort-header" data-sort="matricule"
                                    style="min-width:130px;cursor:pointer;user-select:none;"
                                    title="Trier par matricule">
                                    Matricule
                                    <span class="sort-icon ms-1" data-sort="matricule">⇅</span>
                                </th>
                                <th rowspan="2" class="fw-semibold" style="min-width:120px;">Prénoms</th>
                                <th rowspan="2" class="fw-semibold" style="min-width:120px;">Noms</th>
                                <th colspan="${ues.length}" class="text-center fw-bold" style="background:#1e706da7;color:white;">
                                    Récapitulatif du Semestre ${esc(String(semInfo.numInYear || ''))}
                                </th>
                                <th rowspan="2" class="text-center fw-bold sort-header" data-sort="moyenne_sem"
                                    style="background:#4cc98f;color:white;min-width:75px;cursor:pointer;user-select:none;"
                                    title="Trier par moyenne semestrielle">
                                    Moy. Sem.
                                    <span class="sort-icon ms-1" data-sort="moyenne_sem">⇅</span>
                                </th>
                                <th rowspan="2" class="text-center fw-semibold" style="min-width:90px;">Crédits</th>
                                <th rowspan="2" class="text-center fw-semibold" style="min-width:140px;">Statut</th>
                            </tr>
                            <tr>${thUEs}</tr>
                        </thead>
                        <tbody>${lignes}</tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between align-items-end flex-wrap gap-4 pt-4">
            <div>
            <div class="fs-7 text-muted">
            <strong>Effectif total :</strong> ${stats.nbTotal} &nbsp;|&nbsp;
            <strong>Absents :</strong> ${stats.nbAbsents} &nbsp;|&nbsp;
            <strong>Validés :</strong> ${stats.nbValides} &nbsp;|&nbsp;
            ${stats.nbVPC > 0 ? `<strong>VPC :</strong> ${stats.nbVPC} &nbsp;|&nbsp;` : ''}
            <strong>Non validés :</strong> ${stats.nbNonValid}
            ${stats.nbRepeches > 0 ? ` &nbsp;|&nbsp; <strong>Invalides :</strong> ${stats.nbInvalide}` : ''}
            </div>
            <div class="fs-8 text-muted mt-1">
            <span class="badge fw-bold me-1" style="background:#fff3cd;color:#856404;">VPC</span> = Validé par compensation
            </div>
            </div>
            <div class="d-flex justify-content-end">
                <button class="btn btn-primary" id="vlideLastDecision">Appliquer les VPC</button>
            </div>
                <!--<div class="d-flex gap-5 flex-wrap">
                    <div class="text-center">
                        <div class="text-muted fs-8 mb-1">Président du jury</div>
                        <div style="border-top:1px solid #999;padding-top:0.3rem;margin-top:3rem;font-size:0.75rem;font-weight:600;min-width:180px;">Signature & cachet</div>
                    </div>
                    <div class="text-center">
                        <div class="text-muted fs-8 mb-1">Visa académique</div>
                        <div class="text-muted fs-8">Dakar, le ${new Date().toLocaleDateString('fr-FR')}</div>
                        <div style="border-top:1px solid #999;padding-top:0.3rem;margin-top:2.5rem;font-size:0.75rem;font-weight:600;min-width:180px;">Signature & cachet</div>
                    </div>
                </div>-->
            </div>
        </div>`;

        document.getElementById('pvZone').innerHTML = html;
        // ── Tri des colonnes ──────────────────────────────────────────────────────────
document.querySelectorAll('.sort-header').forEach(th => {
    th.addEventListener('click', function () {
        const key = this.dataset.sort;

        // Basculer direction
        if (sortConfig.key === key) {
            sortConfig.dir = sortConfig.dir === 'asc' ? 'desc' : 'asc';
        } else {
            sortConfig.key = key;
            sortConfig.dir = 'asc';
        }

        // Mettre à jour les icônes
        document.querySelectorAll('.sort-icon').forEach(icon => {
            icon.textContent = '⇅';
            icon.style.opacity = '0.4';
        });
        const activeIcon = document.querySelector(`.sort-icon[data-sort="${key}"]`);
        if (activeIcon) {
            activeIcon.textContent = sortConfig.dir === 'asc' ? '↑' : '↓';
            activeIcon.style.opacity = '1';
        }

        // Re-render avec les étudiants triés
        const etudiantsTries = sortEtudiants(data.etudiants, sortConfig.key, sortConfig.dir);
        renderPV({ ...data, etudiants: etudiantsTries });
    });
});
        document.getElementById('vlideLastDecision')?.addEventListener('click', async function () {
            if (!state.pvData) return;

            // Collecter TOUTES les checkboxes (cochées ou non)
            const payload = [];
            document.querySelectorAll('.switch-vpc-enjambiste').forEach(sw => {
                payload.push({
                    matricule: sw.dataset.matricule,
                    idUE: parseInt(sw.dataset.idUe),
                    idSemestre: state.idSemestre,
                    actif: sw.checked ? 1 : 0  // toggle : coché=1, décoché=0
                });
            });

            if (payload.length === 0) {
                Swal.fire({ icon: 'info', title: 'Aucune sélection', text: 'Aucune UE enjambiste trouvée.' });
                return;
            }

            const cochees = payload.filter(p => p.actif === 1).length;
            const decochees = payload.filter(p => p.actif === 0).length;
            const matriculesConcernes = new Set(payload.map(p => p.matricule));

            const confirm = await Swal.fire({
                icon: 'question',
                title: 'Valider la décision finale',
                html: `
            <div class="text-start">
                <p>${matriculesConcernes.size} étudiant(s) concerné(s)</p>
                <p><span class="badge badge-light-success">${cochees} UE(s) activées (VPC)</span></p>
                ${decochees > 0 ? `<p><span class="badge badge-light-danger">${decochees} UE(s) désactivées</span></p>` : ''}
            </div>`,
                showCancelButton: true,
                confirmButtonText: 'Confirmer',
                cancelButtonText: 'Annuler',
            });
            if (!confirm.isConfirmed) return;

            try {
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>En cours...';

                const res = await fetch('pvParSemestreController.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'toggleVPCEnjambisteBulk', items: payload })
                }).then(r => r.json());

                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Décision validée', text: res.message });
                    await chargerPV();
                } else {
                    Swal.fire({ icon: 'error', title: 'Erreur', text: res.message });
                }
            } catch (e) {
                console.error(e);
                Swal.fire({ icon: 'error', title: 'Erreur réseau', text: 'Impossible de contacter le serveur.' });
            } finally {
                this.disabled = false;
                this.innerHTML = 'Valider la décision finale';
            }
        });
    }

    // ── Impression ────────────────────────────────────────────────────────────
    function imprimer() {
        const pvEl = document.getElementById('pvDocument');
        if (!pvEl) return;
        const printCSS = `<style>
            body { font-family: 'Poppins', sans-serif; font-size: 8pt; }
            .table { width:100%; border-collapse:collapse; }
            .table th, .table td { border:1px solid #ccc; padding:2pt 4pt; }
            .table thead th { background:#246758!important; color:white!important; -webkit-print-color-adjust:exact; print-color-adjust:exact; font-size:7pt; }
            .badge { display:inline-block; padding:1pt 4pt; border-radius:3pt; font-size:7pt; font-weight:600; }
            .badge-light-success { background:#d4edda; color:#155724; }
            .badge-light-danger  { background:#f8d7da; color:#721c24; }
            .badge-light-primary { background:#cce5ff; color:#004085; }
            .text-success { color:#155724!important; }
            .text-danger  { color:#721c24!important; }
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
        const { semInfo, annee, ues, totalCredits, etudiants, stats } = state.pvData;
        const wb = XLSX.utils.book_new();
        const wsData = [];

        wsData.push(['UNIVERSITÉ AMADOU HAMPÂTÉ BÂ DE DAKAR']);
        wsData.push(['Procès Verbal de Délibération par Semestre']);
        wsData.push(['Filière :', semInfo.filiere || '']);
        wsData.push(['Classe :', (semInfo.niveau || '') + ' ' + (semInfo.option_etudiant || '')]);
        wsData.push(['Semestre :', semInfo.nom_semestre || '']);
        wsData.push(['Année :', annee || '']);
        wsData.push(['Date :', new Date().toLocaleDateString('fr-FR')]);
        wsData.push([]);

        const hdr = ['#', 'Matricule', 'Prénoms', 'Noms',
            ...ues.map(ue => `${ue.code_ue} (${ue.total_credits} cr.)`),
            'Moy. Semestre', `Crédits / ${totalCredits}`, 'Statut'
        ];
        wsData.push(hdr);

        etudiants.forEach((etudiant, i) => {
            const notes = ues.map(ue => {
                const moy = etudiant.moyennes_ue[ue.idUE];
                return moy !== null && moy !== undefined ? moy : '';
            });
            wsData.push([
                i + 1,
                etudiant.matricule,
                etudiant.prenom,
                etudiant.nom,
                ...notes,
                etudiant.moyenne_sem,
                etudiant.creditsVPC,
                etudiant.statut === 'Semestre validé par compensation' ? 'Validé par compensation (VPC)' : etudiant.statut
            ]);
        });

        wsData.push([]);
        wsData.push([
            'Effectif total :', stats.nbTotal,
            'Validés :', stats.nbValides,
            'Non validés :', stats.nbNonValid,
            'Repêchés :', stats.nbRepeches,
            'Taux réussite :', `${stats.tauxReuss}%`
        ]);

        const ws = XLSX.utils.aoa_to_sheet(wsData);
        ws['!cols'] = [
            { wch: 5 }, { wch: 22 }, { wch: 22 }, { wch: 22 },
            ...ues.map(() => ({ wch: 12 })),
            { wch: 14 }, { wch: 14 }, { wch: 22 }
        ];
        const nomFichier = `PV_SEM${semInfo.numInYear || ''}_${new Date().toISOString().slice(0, 10)}`;
        XLSX.utils.book_append_sheet(wb, ws, 'PV Semestre');
        XLSX.writeFile(wb, `${nomFichier}.xlsx`);
    }

    // ── Export PDF ────────────────────────────────────────────────────────────
    function exporterPDF() {
        if (!state.pvData) return;
        const { semInfo, annee, ues, totalCredits, etudiants, stats } = state.pvData;
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
            if (loaded === 2) _genererPDF(doc, W, semInfo, annee, ues, totalCredits, etudiants, stats, imgGSJLF, imgUAHB);
        }
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

    function _genererPDF(doc, W, semInfo, annee, ues, totalCredits, etudiants, stats, imgGSJLF, imgUAHB) {
        const logoH = 22;
        const logoW = 22;
        const headerH = 44;
        const cx = W / 2;
        const COLOR = [36, 103, 92];

        // ── En-tête ───────────────────────────────────────────────────────────
        doc.setDrawColor(200, 200, 200);
        doc.setLineWidth(0.3);
        doc.line(0, headerH, W, headerH);

        if (imgGSJLF && imgGSJLF.naturalWidth > 0) {
            const b64Left = _imgToBase64(imgGSJLF);
            doc.addImage(b64Left, 'JPEG', W * 0.25, (headerH - logoH) / 2, logoW, logoH);
        }
        if (imgUAHB && imgUAHB.naturalWidth > 0) {
            const b64Right = _imgToBase64(imgUAHB);
            doc.addImage(b64Right, 'PNG', W * 0.75 - logoW, (headerH - logoH) / 2, logoW, logoH);
        }

        doc.setTextColor(100);
        doc.setFont('helvetica', 'italic');
        doc.setFontSize(10);       // ↑ was 9
        doc.text('Groupe Scolaire Jean de la Fontaine', cx, 7, { align: 'center' });

        doc.setTextColor(...COLOR);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(14);       // ↑ was 13
        doc.text('UNIVERSITE AMADOU HAMPATE BA DE DAKAR', cx, 14, { align: 'center' });

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(10);       // ↑ was 9
        doc.setTextColor(100);
        doc.text('-=-=-=- UAHB -=-=-=-', cx, 19, { align: 'center' });

        doc.setDrawColor(180);
        doc.setLineDashPattern([1, 1], 0);
        doc.line(cx - 35, 21, cx + 35, 21);
        doc.setLineDashPattern([], 0);

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(11);       // ↑ was 10
        doc.setTextColor(36, 103, 92);
        doc.text((semInfo.faculte || '').toUpperCase(), cx, 28, { align: 'center' });

        doc.setDrawColor(180);
        doc.setLineDashPattern([1, 1], 0);
        doc.line(cx - 32, 31, cx + 32, 31);
        doc.setLineDashPattern([], 0);

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(10);       // ↑ was 9
        doc.setTextColor(36, 103, 92);
        doc.text((semInfo.departement || '').toUpperCase(), cx, 37, { align: 'center' });

        // ── Bandeau ───────────────────────────────────────────────────────────
        const bandeauY = headerH + 5;
        const bandeauH = 26;       // ↑ was 24
        const bandeauW = W * 0.65;
        const bandeauX = (W - bandeauW) / 2;

        doc.setFillColor(...COLOR);
        doc.roundedRect(bandeauX, bandeauY, bandeauW, bandeauH, 4, 4, 'F');

        doc.setTextColor(255, 255, 255);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(14);       // ↑ was 13
        doc.text('PROCÈS VERBAL DE DÉLIBÉRATION SEMESTRIELLE', cx, bandeauY + 8, { align: 'center' });

        doc.setFontSize(11);       // ↑ was 10
        doc.text(
            'Semestre ' + (semInfo.numInYear || '') + '  —  Session ' + (semInfo.idSession || 'I'),
            cx, bandeauY + 16, { align: 'center' }
        );

        doc.setFontSize(10.5);     // ↑ was 9.5
        doc.text('Année Académique : ' + (annee || ''), cx, bandeauY + 23, { align: 'center' });

        // ── Méta sous le bandeau ──────────────────────────────────────────────
        const infoY = bandeauY + bandeauH + 8;

        doc.setFontSize(10);       // ↑ was 9
        doc.setTextColor(0);
        doc.text('Filière : ' + (semInfo.filiere || ''), 12, infoY);
        doc.text('Classe : ' + (semInfo.niveau || '') + ' ' + (semInfo.option_etudiant || ''), 12, infoY + 7);
        doc.text('Semestre : ' + (semInfo.numero || '') + '  |  Crédits totaux : ' + totalCredits, 12, infoY + 14);

        doc.setTextColor(60);
        doc.setFontSize(9.5);      // ↑ was 8.5
        doc.text('Date : ' + new Date().toLocaleDateString('fr-FR'), W - 12, infoY + 14, { align: 'right' });

        // ── Tableau ───────────────────────────────────────────────────────────
        const head = [[
            '#', 'Matricule', 'Prénom(s)', 'Nom(s)',
            ...ues.map(ue => `${ue.code_ue}\n(${ue.total_credits} cr.)`),
            'Moy.\nSem.', `Crédits\n/ ${totalCredits}`, 'Statut'
        ]];

        const body = [...etudiants]
            .sort((a, b) => (a.matricule || '').localeCompare(b.matricule || ''))
            .map((etudiant, i) => {
            const compensees = new Set(etudiant.ues_compensees || []);
            const notes = ues.map(ue => {
                const moy = etudiant.moyennes_ue[ue.idUE];
                if (moy === null || moy === undefined) return '—';
                const noteStr = moy.toFixed(2).replace('.', ',');
                if (compensees.has(ue.idUE)) return noteStr + '\nVPC';
                return noteStr + (moy >= 10 ? '\nV' : '\nNV');
            });
            return [
                i + 1,
                etudiant.matricule,
                etudiant.prenom,
                etudiant.nom,
                ...notes,
                !etudiant.est_enjambiste
                    ? (etudiant.moyenne_sem.toFixed(2).replace('.', ','))
                    : '—',
                etudiant.est_enjambiste
                    ? (etudiant.enjambisteCredit + etudiant.creditsVPC)
                    : etudiant.creditsVPC,
                etudiant.statut === 'Invalide' && (etudiant.enjambisteCredit + etudiant.creditsVPC) < 30
                    ? 'Semestre non validé'
                    : etudiant.statut
            ];
        });

        const lastCol = 4 + ues.length + 2;

        doc.autoTable({
            head,
            showHead: 'everyPage',
            body,
            startY: infoY + 20,
            styles: {
                fontSize: 9,           // ↑ was 8
                cellPadding: 2.5,
                overflow: 'linebreak',
                minCellHeight: 10,
            },
            headStyles: {
                fillColor: COLOR,
                fontSize: 9,           // ↑ was 8
                fontStyle: 'bold',
                cellPadding: 3,
            },
            alternateRowStyles: { fillColor: [245, 243, 238] },
            columnStyles: {
                0: { halign: 'center', cellWidth: 12 },
                1: { cellWidth: 22, fontSize: 8 },   // ↑ was 7
                2: { cellWidth: 30 },
                3: { cellWidth: 30 },
                [4 + ues.length]: {
                    halign: 'center',
                    fontStyle: 'bold',
                    fillColor: [209, 227, 224],
                    fontSize: 10,      // ↑ was 9 implicite
                },
                [4 + ues.length + 1]: { halign: 'center' },
                [lastCol]: { halign: 'center', cellWidth: 30 },  // ↑ was 28
            },
            didParseCell: (hookData) => {
                const colIdx = hookData.column.index;

                if (colIdx >= 4 && colIdx < 4 + ues.length && hookData.section === 'body') {
                    const raw = String(hookData.cell.raw);
                    const parts = raw.split('\n');
                    const note = parseFloat(parts[0].replace(',', '.'));
                    const label = parts[1] || '';
                    hookData.cell.text = [parts[0]];
                    hookData.cell.styles.halign = 'center';
                    if (label === 'VPC') {
                        hookData.cell.styles.textColor = [133, 100, 4];
                        hookData.cell.styles.fillColor = [255, 251, 230];
                    } else if (!isNaN(note)) {
                        hookData.cell.styles.textColor = note >= 10 ? [21, 87, 36] : [114, 28, 36];
                    }
                }

                if (colIdx === 4 + ues.length && hookData.section === 'body') {
                    const val = parseFloat(String(hookData.cell.raw).replace(',', '.'));
                    if (!isNaN(val)) {
                        hookData.cell.styles.textColor = val >= 10 ? [21, 87, 36] : [114, 28, 36];
                        hookData.cell.styles.fontStyle = 'bold';
                        hookData.cell.styles.fontSize = 10;   // ↑ was 9 implicite
                    }
                }

                if (colIdx === lastCol && hookData.section === 'body') {
                    const val = String(hookData.cell.raw).trim();
                    hookData.cell.styles.fontStyle = 'bold';
                    if (val === 'Semestre validé') {
                        hookData.cell.styles.textColor = [21, 87, 36];
                    } else if (val === 'VPC') {
                        hookData.cell.styles.textColor = [133, 100, 4];
                        hookData.cell.styles.fillColor = [255, 251, 230];
                    } else if (val === 'Invalide') {
                        hookData.cell.styles.textColor = [100, 100, 100];
                    } else {
                        hookData.cell.styles.textColor = [114, 28, 36];
                    }
                }
            },
            margin: { left: 10, right: 10, bottom: 20 },
            didDrawCell: (hookData) => {
                const colIdx = hookData.column.index;
                if (colIdx >= 4 && colIdx < 4 + ues.length && hookData.section === 'body') {
                    const raw = String(hookData.cell.raw);
                    const parts = raw.split('\n');
                    if (parts.length < 2) return;
                    const label = parts[1];
                    const x = hookData.cell.x + hookData.cell.width / 2;
                    const y = hookData.cell.y + hookData.cell.height - 1;
                    let color;
                    if (label === 'VPC') color = [133, 100, 4];
                    else if (label === 'V') color = [21, 87, 36];
                    else color = [114, 28, 36];
                    doc.setFontSize(6.5);      // ↑ was 6
                    doc.setFont('helvetica', 'bold');
                    doc.setTextColor(...color);
                    doc.text(label, x, y, { align: 'center' });
                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(9);
                    doc.setTextColor(0);
                }
            },
            didDrawPage: () => {
                const pageNum = doc.internal.getCurrentPageInfo().pageNumber;
                doc.setFontSize(8);    // ↑ was 7.5
                doc.setTextColor(120);
                doc.text(
                    `Effectif : ${stats.nbTotal} | Validés : ${stats.nbValides}${stats.nbVPC > 0 ? ' | VPC : ' + stats.nbVPC : ''} | Non validés : ${stats.nbNonValid} | Absents : ${stats.nbAbsents} — Page ${pageNum}`,
                    W / 2, doc.internal.pageSize.height - 5, { align: 'center' }
                );
            }
        });

        // ── Légende codes UE ──────────────────────────────────────────────────
        const legendeY = doc.lastAutoTable.finalY + 6;

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8.5);          // ↑ was 7.5
        doc.setTextColor(...COLOR);
        doc.text('Légende des UE :', 12, legendeY);

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);            // ↑ was 7
        doc.setTextColor(60, 60, 60);

        const colonnesParLigne = 3;
        const colLargeur = (W - 24) / colonnesParLigne;
        let lx = 12;
        let ly = legendeY + 5;

        ues.forEach((ue, i) => {
            if (i > 0 && i % colonnesParLigne === 0) { lx = 12; ly += 5; }
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(...COLOR);
            doc.text(`${ue.code_ue}`, lx, ly);
            const codeW = doc.getTextWidth(`${ue.code_ue}`) + 2;
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(60, 60, 60);
            const nomTronque = ue.nom_ue.length > 40 ? ue.nom_ue.substring(0, 40) + '…' : ue.nom_ue;
            doc.text(`: ${nomTronque} (${ue.total_credits} cr.)`, lx + codeW, ly);
            lx += colLargeur;
        });

        const sepY = ly + 4;
        doc.setDrawColor(200, 200, 200);
        doc.setLineWidth(0.3);
        doc.line(12, sepY, W - 12, sepY);

        // ── Cartes stats ──────────────────────────────────────────────────────
        const finalY = sepY + 8;
        const tauxEchec = (100 - stats.tauxReuss).toFixed(1) + '%';
        const tauxReuss = stats.tauxReuss + '%';

        const statItems = [
            { label: 'Effectif total', val: stats.nbTotal, color: [60, 60, 60], sublabel: null },
            { label: 'Absents', val: stats.nbAbsents, color: [180, 70, 70], sublabel: null },
            { label: 'Ayant composés', val: stats.nbTotal - stats.nbAbsents, color: [80, 80, 80], sublabel: null },
            //{ label: 'VPC',                  val: stats.nbVPC ?? 0,                          color: [180, 110, 0],   sublabel: null },
            //{ label: 'Validés',              val: stats.nbValides,                           color: [46,  160, 64],  sublabel: null },
            { label: 'Validés', val: stats.nbValides + (stats.nbVPC ?? 0), color: [20, 90, 30], sublabel: tauxReuss },
            { label: 'Non validés', val: stats.nbNonValid, color: [200, 60, 60], sublabel: tauxEchec },
            //{ label: 'Invalides',            val: stats.nbInvalides ?? 0,                     color: [150, 90,  110], sublabel: null },  // ← nouvelle card
        ];

        // Largeur dynamique pour que les 8 cards tiennent toujours
        const cardGap = 3;
        const cardW = (W - 24 - (statItems.length - 1) * cardGap) / statItems.length;
        const cardH = 22;        // ↑ was 18
        let cardX = 12;

        statItems.forEach(item => {
            // Fond teinté
            doc.setFillColor(...item.color.map(c => Math.min(255, c + 165)));
            doc.roundedRect(cardX, finalY, cardW, cardH, 2, 2, 'F');
            // Bordure colorée
            doc.setDrawColor(...item.color);
            doc.setLineWidth(0.5);
            doc.roundedRect(cardX, finalY, cardW, cardH, 2, 2, 'S');


            // Valeur principale
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(13);   // ↑ was 12
            doc.setTextColor(...item.color);
            doc.text(String(item.val), cardX + cardW / 2, finalY + 9, { align: 'center' });

            // Label (tronqué si trop long)
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(6.5);
            doc.setTextColor(70, 70, 70);
            const maxLabelW = cardW - 2;
            let label = item.label;
            while (doc.getTextWidth(label) > maxLabelW && label.length > 3) label = label.slice(0, -1);
            if (label !== item.label) label += '…';
            doc.text(label, cardX + cardW / 2, finalY + 15, { align: 'center' });

            // Sous-label (taux)
            if (item.sublabel) {
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(7);
                doc.setTextColor(...item.color);
                doc.text(item.sublabel, cardX + cardW / 2, finalY + 20, { align: 'center' });
            }

            cardX += cardW + cardGap;
        });

        // ── Signatures ────────────────────────────────────────────────────────
        const H = doc.internal.pageSize.getHeight();
        const visaY = finalY + cardH + 16;
        const membres = ['Président du jury', 'Membres du jury', 'Visa académique'];
        const sigW = W / membres.length;

        const renderSignatures = (startY) => {
            membres.forEach((titre, i) => {
                const sigCx = sigW * i + sigW / 2;

                doc.setFont('helvetica', 'bold');
                doc.setFontSize(9.5);  // ↑ was 8.5
                doc.setTextColor(40, 40, 40);
                doc.text(titre, sigCx, startY, { align: 'center' });

            });
        };

        if (visaY + 30 > H - 10) {
            doc.addPage();
            renderSignatures(20);
        } else {
            renderSignatures(visaY);
        }

        const nomFichier = `PV_SEM${semInfo.numInYear || ''}_${new Date().toISOString().slice(0, 10)}`;
        doc.save(`${nomFichier}.pdf`);
    }
    return { init, imprimer, exporterExcel, exporterPDF };

})();

document.addEventListener('DOMContentLoaded', () => pvParSemestre.init());