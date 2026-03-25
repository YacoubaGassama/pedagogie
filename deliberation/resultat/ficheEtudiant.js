// ── État global ───────────────────────────────────────────────────────────────
let ficheData = null;

// ── Utilitaires ───────────────────────────────────────────────────────────────
function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function get(action, params = {}) {
    const url = new URL('ficheEtudiantController.php', window.location.href);
    url.searchParams.set('action', action);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    return fetch(url).then(r => r.json());
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const inputMatricule = document.getElementById('inputMatricule');
    const btnRechercher  = document.getElementById('btnRechercher');
    const btnExport      = document.getElementById('btnExportPDF');

    btnRechercher?.addEventListener('click', () => rechercherEtudiant());

    inputMatricule?.addEventListener('keydown', e => {
        if (e.key === 'Enter') rechercherEtudiant();
    });

    btnExport?.addEventListener('click', exporterPDF);
});

// ── Recherche ─────────────────────────────────────────────────────────────────
async function rechercherEtudiant() {
    const matricule = document.getElementById('inputMatricule')?.value?.trim();
    if (!matricule) {
        Swal.fire({ icon: 'warning', title: 'Matricule requis', text: 'Veuillez saisir un matricule.', confirmButtonText: 'OK' });
        return;
    }

    showLoader();

    try {
        const res = await get('getFiche', { matricule });

        if (!res.success) {
            showError(res.message || 'Aucune donnée trouvée.');
            return;
        }

        ficheData = res;
        await enrichirInfosEtudiant(matricule);
        renderFiche(ficheData);
        document.getElementById('btnExportPDF').style.removeProperty('display');
    } catch (e) {
        console.error(e);
        showError('Erreur lors du chargement de la fiche.');
    }
}

// ── Rendu principal ───────────────────────────────────────────────────────────
function renderFiche(data) {
    const { semestres } = data;
    const etudiant = extraireInfoEtudiant(semestres);
    const zone = document.getElementById('ficheZone');

    const semestresHtml = semestres.map(sem => renderSemestre(sem)).join('');

    zone.innerHTML = `
    <div id="ficheDocument">

        <!-- Carte infos étudiant -->
        <div class="card mb-5">
            <div class="card-body py-4">
                <div class="d-flex align-items-center gap-4">
                    <div class="symbol symbol-80px symbol-circle bg-light-primary d-flex align-items-center justify-content-center p-2">
                        <span class="fs-2 fw-bolder text-primary">${esc(etudiant.initiales)}</span>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="fw-bolder text-dark mb-1">${esc(etudiant.prenom)} ${esc(etudiant.nom)}</h4>
                        <div class="d-flex flex-wrap gap-3 text-muted fs-7">
                            <span><i class="fas fa-id-card me-1"></i>${esc(etudiant.matricule)}</span>
                            <span><i class="fas fa-graduation-cap me-1"></i>${esc(etudiant.filiere)}</span>
                            <span><i class="fas fa-layer-group me-1"></i>${esc(etudiant.niveau)}</span>
                            ${etudiant.option ? `<span><i class="fas fa-cogs me-1"></i>${esc(etudiant.option)}</span>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Semestres -->
        ${semestresHtml}

    </div>`;
}

// ── Rendu semestre ────────────────────────────────────────────────────────────
function renderSemestre(sem) {
    const compensees = new Set(sem.ues_compensees || []);

    let statutCls, statutLabel;
    if (sem.statut === 'Semestre validé') {
        statutCls = 'badge-light-success'; statutLabel = 'Semestre validé';
    } else if (sem.statut === 'Semestre validé par compensation') {
        statutCls = ''; statutLabel = 'VPC';
    } else {
        statutCls = 'badge-light-danger'; statutLabel = sem.statut;
    }

    const statutBadge = sem.statut === 'Semestre validé par compensation'
        ? `<span class="badge fw-bold" style="background:#fff3cd;color:#856404;">${statutLabel}</span>`
        : `<span class="badge ${statutCls} fw-bold">${statutLabel}</span>`;

    const uesHtml = sem.ues.map(ue => renderUE(ue, compensees)).join('');

    return `
    <div class="card mb-4">
        <div class="card-header border-0 pt-5 pb-0">
            <div class="card-title flex-column">
                <h5 class="fw-bolder mb-1 text-dark">
                    <i class="fas fa-book-open me-2 text-primary"></i>
                    ${esc(sem.nomSemestre || 'Semestre ' + sem.numSemestre)}
                </h5>
            </div>
            <div class="card-toolbar d-flex align-items-center gap-3 flex-wrap">
                <span class="badge badge-light-dark fw-semibold">
                    Moy. sem : <strong>${sem.moyenne_sem?.toFixed(2).replace('.', ',')}</strong>
                </span>
                <span class="badge badge-light-info fw-semibold">
                    Crédits : <strong>${sem.credits_valides} / ${sem.total_credits}</strong>
                </span>
                ${statutBadge}
            </div>
        </div>
        <div class="card-body pt-3">
            <div class="accordion accordion-icon-toggle" id="accordionSem${sem.numSemestre}">
                ${uesHtml}
            </div>
        </div>
        ${sem.statut === 'Semestre validé par compensation' ? `
        <div class="card-footer py-2">
            <small class="text-muted">
                <span class="badge fw-bold me-1" style="background:#fff3cd;color:#856404;">VPC</span>
                = Validé par compensation
            </small>
        </div>` : ''}
    </div>`;
}

// ── Rendu UE (accordion) ──────────────────────────────────────────────────────
function renderUE(ue, compensees) {
    const estCompensee = compensees.has(ue.idUE);
    const moy = ue.moyenne_ue ?? 0;

    let moyCls, obsBadge;
    if (estCompensee) {
        moyCls   = 'text-warning fw-bold';
        obsBadge = `<span class="badge fw-bold" style="background:#fff3cd;color:#856404;">VPC</span>`;
    } else if (moy >= 10) {
        moyCls   = 'text-success fw-bold';
        obsBadge = `<span class="badge badge-light-success fw-bold">Validée</span>`;
    } else {
        moyCls   = 'text-danger fw-bold';
        obsBadge = `<span class="badge badge-light-danger fw-bold">Non validée</span>`;
    }

    const ecsHtml = ue.ecs.map(ec => {
        const noteCls = ec.note_final >= 10 ? 'text-success' : 'text-danger';
        const srcBadge = ec.source_note === 'repechage'
            ? `<span class="badge badge-light-primary ms-1" style="font-size:0.65rem;">R</span>` : '';
        return `
        <tr>
            <td class="text-muted fs-8 ps-4">${esc(ec.code_ec)}</td>
            <td class="fs-8">${esc(ec.nom_ec)}</td>
            <td class="text-center fs-8 text-muted">${ec.note_initial > 0 ? ec.note_initial.toFixed(2).replace('.', ',') : '—'}</td>
            <td class="text-center fs-8">${ec.point_jury}</td>
            <td class="text-center fs-8 ${noteCls} fw-bold">
            ${ec.note_final.toFixed(2).replace('.', ',')}${srcBadge}
            </td>
        </tr>`;
    }).join('');

    const collapseId = `ue_${ue.idUE}`;

    return `
    <div class="mb-2">
        <div class="d-flex align-items-center collapsible collapsed cursor-pointer"
             data-bs-toggle="collapse" data-bs-target="#${collapseId}">
            <div class="btn btn-sm btn-icon btn-active-color-primary me-3">
                <span class="svg-icon svg-icon-3 svg-icon-accordion">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M12.6343 12.5657L8.45001 16.75C8.0358 17.1642 8.0358 17.8358 8.45001 18.25C8.86423 18.6642 9.5358 18.6642 9.95001 18.25L15.4929 12.7071C15.8834 12.3166 15.8834 11.6834 15.4929 11.2929L9.95001 5.75C9.5358 5.33579 8.86423 5.33579 8.45001 5.75C8.0358 6.16421 8.0358 6.83579 8.45001 7.25L12.6343 11.4343C12.9467 11.7467 12.9467 12.2533 12.6343 12.5657Z" fill="black"/>
                    </svg>
                </span>
            </div>
            <div class="flex-grow-1 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <span class="fw-bolder text-dark me-2">${esc(ue.code_ue)}</span>
                    <span class="text-muted fs-7">${esc(ue.nom_ue.length > 50 ? ue.nom_ue.substring(0, 50) + '…' : ue.nom_ue)}</span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge badge-light-dark fs-8">${ue.total_credits} cr.</span>
                    <span class="${moyCls} fs-6">${moy.toFixed(2).replace('.', ',')}</span>
                    ${obsBadge}
                </div>
            </div>
        </div>
        <div id="${collapseId}" class="collapse">
            <div class="ps-10 pt-2 pb-1">
                <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-1 mb-0">
                    <thead>
                        <tr class="text-muted fw-semibold fs-8 bg-light">
                            <th class="ps-4">Code EC</th>
                            <th>Nom EC</th>
                            <th class="text-center">Moy initiale</th>
                            <th class="text-center">Point du Jury</th>
                            <th class="text-center">Note finale</th>
                        </tr>
                    </thead>
                    <tbody>${ecsHtml}</tbody>
                </table>
                <div class="text-end mt-1 pe-2">
                    <small class="text-muted">
                        Moyenne UE : <strong class="${moyCls}">${moy.toFixed(2).replace('.', ',')}</strong>
                    </small>
                </div>
            </div>
        </div>
    </div>`;
}

// ── Extraire infos étudiant depuis les semestres ───────────────────────────────
function extraireInfoEtudiant(semestres) {
    const premiere = semestres[0]?.ues[0]?.ecs[0] || {};
    // Les infos étudiant ne sont pas dans les ECs — on les récupère via une
    // requête séparée ou on les stocke dans ficheData si on les a
    const mat = document.getElementById('inputMatricule')?.value?.trim() || '';
    return {
        matricule : mat,
        prenom    : ficheData?.prenom    || '',
        nom       : ficheData?.nom       || '',
        filiere   : ficheData?.filiere   || semestres[0]?.filiere || '',
        niveau    : ficheData?.niveau    || '',
        option    : ficheData?.option    || '',
        initiales : getInitiales(ficheData?.prenom || '', ficheData?.nom || mat),
    };
}

function getInitiales(prenom, nom) {
    return ((prenom?.[0] || '') + (nom?.[0] || '')).toUpperCase() || '?';
}

// ── Infos étudiant enrichies (appel séparé) ───────────────────────────────────
async function enrichirInfosEtudiant(matricule) {
    try {
        const res = await get('getEtudiant', { matricule });
        if (res.success && ficheData) {
            ficheData.prenom  = res.etudiant.prenom;
            ficheData.nom     = res.etudiant.nom;
            ficheData.filiere = res.etudiant.filiere;
            ficheData.niveau  = res.etudiant.niveau;
            ficheData.option  = res.etudiant.option_etudiant;
        }
    } catch {}
}

// ── Loader / Erreur ───────────────────────────────────────────────────────────
function showLoader() {
    document.getElementById('ficheZone').innerHTML = `
        <div class="d-flex justify-content-center align-items-center py-15">
            <div class="spinner-border text-primary me-3" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <span class="text-muted fw-semibold fs-6">Chargement de la fiche...</span>
        </div>`;
    document.getElementById('btnExportPDF').style.display = 'none';
}

function showError(msg) {
    document.getElementById('ficheZone').innerHTML = `
        <div class="alert alert-danger d-flex align-items-center p-5">
            <i class="fas fa-times-circle text-danger me-3 fs-3"></i>
            <div class="fw-semibold">${esc(msg)}</div>
        </div>`;
}

// ── Export PDF ────────────────────────────────────────────────────────────────
function exporterPDF() {
    if (!ficheData) return;

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const W   = doc.internal.pageSize.getWidth();
    const COLOR = [36, 103, 92];
    const cx  = W / 2;

    // En-tête
    doc.setFillColor(...COLOR);
    doc.rect(0, 0, W, 30, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(14);
    doc.text('FICHE DE RÉSULTATS', cx, 12, { align: 'center' });
    doc.setFontSize(9);
    doc.setFont('helvetica', 'normal');
    doc.text('UNIVERSITÉ AMADOU HAMPÂTÉ BÂ DE DAKAR', cx, 19, { align: 'center' });
    doc.text('Année académique — ' + new Date().toLocaleDateString('fr-FR'), cx, 25, { align: 'center' });

    // Infos étudiant
    const mat     = document.getElementById('inputMatricule')?.value?.trim() || '';
    const prenom  = ficheData.prenom  || '';
    const nom     = ficheData.nom     || '';
    const filiere = ficheData.filiere || '';
    const niveau  = ficheData.niveau  || '';

    let y = 38;
    doc.setTextColor(0);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.text(`${prenom} ${nom}`, 14, y);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8.5);
    doc.setTextColor(80);
    doc.text(`Matricule : ${mat}  |  Filière : ${filiere}  |  Niveau : ${niveau}`, 14, y + 6);

    doc.setDrawColor(200);
    doc.setLineWidth(0.3);
    doc.line(14, y + 10, W - 14, y + 10);

    y += 16;

    // Semestres + UEs
    ficheData.semestres.forEach(sem => {
        // Titre semestre
        doc.setFillColor(...COLOR.map(c => Math.min(255, c + 170)));
        doc.roundedRect(14, y, W - 28, 8, 1, 1, 'F');
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8.5);
        doc.setTextColor(...COLOR);
        doc.text(sem.nomSemestre || `Semestre ${sem.numSemestre}`, 18, y + 5.5);
        const statutTxt = sem.statut === 'Semestre validé par compensation' ? 'VPC' : sem.statut;
        doc.text(`Moy: ${sem.moyenne_sem?.toFixed(2)} | Crédits: ${sem.credits_valides}/${sem.total_credits} | ${statutTxt}`, W - 16, y + 5.5, { align: 'right' });
        y += 11;

        // Tableau UEs
        const compensees = new Set(sem.ues_compensees || []);
        const head = [['Code UE', 'Nom UE', 'Crédits', 'Moy. UE', 'Observation']];
        const body = sem.ues.map(ue => {
            const moy = ue.moyenne_ue ?? 0;
            const obs = compensees.has(ue.idUE) ? 'VPC'
                : moy >= 10 ? 'Validée' : 'Non validée';
            return [ue.code_ue, ue.nom_ue.substring(0, 40), `${ue.total_credits} cr.`, moy.toFixed(2).replace('.', ','), obs];
        });

        doc.autoTable({
            head, body,
            startY: y,
            margin: { left: 14, right: 14 },
            styles: { fontSize: 7.5, cellPadding: 1.8 },
            headStyles: { fillColor: [220, 235, 232], textColor: COLOR, fontStyle: 'bold', fontSize: 7.5 },
            alternateRowStyles: { fillColor: [248, 248, 248] },
            columnStyles: {
                0: { cellWidth: 22, fontStyle: 'bold' },
                1: { cellWidth: 70 },
                2: { halign: 'center', cellWidth: 18 },
                3: { halign: 'center', cellWidth: 18, fontStyle: 'bold' },
                4: { halign: 'center', cellWidth: 25 },
            },
            didParseCell: (h) => {
                if (h.section !== 'body') return;
                if (h.column.index === 3) {
                    const v = parseFloat(h.cell.raw.replace(',', '.'));
                    h.cell.styles.textColor = v >= 10 ? [21, 87, 36] : [114, 28, 36];
                }
                if (h.column.index === 4) {
                    const v = String(h.cell.raw);
                    h.cell.styles.textColor = v === 'Validée' ? [21, 87, 36]
                        : v === 'VPC' ? [133, 100, 4] : [114, 28, 36];
                    h.cell.styles.fontStyle = 'bold';
                }
            },
        });

        y = doc.lastAutoTable.finalY + 6;
        if (y > 270) { doc.addPage(); y = 14; }
    });

    // Pied de page
    doc.setFontSize(7);
    doc.setTextColor(150);
    doc.text(`Document généré le ${new Date().toLocaleDateString('fr-FR')}`, W / 2, 287, { align: 'center' });

    doc.save(`Fiche_${mat}_${new Date().toISOString().slice(0, 10)}.pdf`);
}
