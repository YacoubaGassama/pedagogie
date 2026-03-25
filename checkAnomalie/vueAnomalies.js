(function () {
    const btnCharger = document.getElementById('btnCharger');
    const btnReinitialiser = document.getElementById('btnReinitialiser');

    const statClasses = document.getElementById('statClasses');
    const statSaines = document.getElementById('statSaines');
    const statCritiques = document.getElementById('statCritiques');
    const statAvertissements = document.getElementById('statAvertissements');

    const zoneChargement = document.getElementById('zoneChargement');
    const rapportContainer = document.getElementById('rapportContainer');
    const tableClassesBody = document.querySelector('#tableClasses tbody');

    const FILTRES_URL = 'anomalies.php?action=getFiltres';
    const OPTIONS_URL = 'anomalies.php?action=getOptionsByFiltres';

    const filterFiliere = document.getElementById('filterFiliere');
    const filterNiveau = document.getElementById('filterNiveau');
    const filterOption = document.getElementById('filterOption');
    const filterSemestre = document.getElementById('filterSemestre');
    const filterSession = document.getElementById('filterSession');

    function safe(value, fallback = '') {
        return value === null || value === undefined ? fallback : value;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function badgeGravite(gravite) {
        if (gravite === 'critique') {
            return '<span class="badge badge-critique">Critique</span>';
        }
        if (gravite === 'avertissement') {
            return '<span class="badge badge-avertissement">Avertissement</span>';
        }
        return '<span class="badge badge-ok">OK</span>';
    }

    function badgeEtat(estComplete) {
        return estComplete
            ? '<span class="badge badge-ok">Complète</span>'
            : '<span class="badge badge-critique">Incomplète</span>';
    }

    function remplirSelect(select, items, valueKey, labelKey, placeholder) {
        if (!select) return;

        select.innerHTML = `<option value="">${placeholder}</option>`;

        items.forEach(item => {
            const option = document.createElement('option');
            option.value = item[valueKey];
            option.textContent = typeof labelKey === 'function' ? labelKey(item) : item[labelKey];
            select.appendChild(option);
        });
    }

    async function chargerFiltres() {
        const response = await fetch(FILTRES_URL, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        });

        const text = await response.text();
        let data;

        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error('Réponse invalide lors du chargement des filtres.');
        }

        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'Erreur de chargement des filtres');
        }

        remplirSelect(filterFiliere, data.filieres || [], 'id', 'filiere', 'Toutes les filières');
        remplirSelect(filterNiveau, data.niveaux || [], 'id', 'niveau', 'Tous les niveaux');
        remplirSelect(filterSemestre, data.semestres || [], 'id', 'semestre', 'Tous les semestres');

        if (filterOption) {
            filterOption.innerHTML = `<option value="">Toutes les options</option>`;
        }
    }

    async function chargerOptions() {
        const idFiliere = filterFiliere?.value || 0;
        const idNiveauFormation = filterNiveau?.value || '';

        const url = `${OPTIONS_URL}&idFiliere=${encodeURIComponent(idFiliere)}&idNiveauFormation=${encodeURIComponent(idNiveauFormation)}`;

        const response = await fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        });

        const text = await response.text();
        let data;

        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error('Réponse invalide lors du chargement des options.');
        }

        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'Erreur de chargement des options');
        }

        remplirSelect(
            filterOption,
            data.options || [],
            'id',
            (item) => `${item.option} (${item.code_option || ''})`,
            'Toutes les options'
        );
    }

    function buildQueryString() {
        const params = new URLSearchParams();
 
        params.append('action', 'verifierEvaluationsToutesUE');
        params.append('session_id', filterSession?.value || '1');

        if (filterOption && filterOption.value) params.append('idOption', filterOption.value);
        if (filterNiveau && filterNiveau.value) params.append('idNiveau', filterNiveau.value);
        if (filterFiliere && filterFiliere.value) params.append('idFiliere', filterFiliere.value);
        if (filterSemestre && filterSemestre.value) params.append('idSemestre', filterSemestre.value);

        return params.toString();
    }

    function updateStats(resume = {}, totalClasses = 0, rapport = []) {
        if (statClasses)        statClasses.textContent        = safe(resume.nb_classes ?? totalClasses, 0);
        if (statSaines)         statSaines.textContent         = safe(resume.nb_saines ?? 0, 0);
        if (statCritiques)      statCritiques.textContent      = safe(resume.nb_critiques ?? 0, 0);
        if (statAvertissements) statAvertissements.textContent = safe(resume.nb_avertissements ?? 0, 0);
    }

    function destroyTableIfNeeded() {
        try {
        if (window.jQuery && $.fn.DataTable && $.fn.DataTable.isDataTable('#tableClasses')) {
            $('#tableClasses').DataTable().destroy(true); // true = restore DOM
        }
    } catch (e) {
        // Ignorer si le wrapper n'existe plus
        console.warn('DataTable destroy skipped:', e.message);
    }
    // Vider le tbody manuellement dans tous les cas
    if (tableClassesBody) tableClassesBody.innerHTML = '';
    }

    function renderTableClasses(rapport = []) {
        destroyTableIfNeeded();
        if (!tableClassesBody) return;
        tableClassesBody.innerHTML = '';

        if (!Array.isArray(rapport) || rapport.length === 0) {
            // Ne PAS initialiser DataTable sur un tableau vide — cause _DT_CellIndex
            tableClassesBody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center text-muted py-10">Aucune donnée disponible.</td>
                </tr>
            `;
            return;
        }

        tableClassesBody.innerHTML = rapport.map(classe => {
            const saine = classe.saine ?? (classe.nb_critiques === 0 && classe.nb_avertissements === 0);
            const hasPlusieursExamens = (classe.anomalies || []).some(ue =>
                (ue.anomalies || []).some(a => a.type === 'plusieurs_examens')
            );
            return `
                <tr ${hasPlusieursExamens ? 'class="table-danger"' : ''}>
                    <td>${escapeHtml(classe.filiere || '-')}</td>
                    <td>${escapeHtml(classe.niveau || '-')}</td>
                    <td>${escapeHtml(classe.option || '-')} <small class="text-muted">(${escapeHtml(classe.code || '')})</small></td>
                    <td class="text-center">${escapeHtml(classe.nb_inscrits ?? 0)}</td>
                    <td class="text-center">${escapeHtml(classe.nb_ues_maquette ?? 0)}</td>
                    <td class="text-center">
                        ${classe.nb_critiques > 0 ? `<span class="badge badge-critique">${classe.nb_critiques}</span>` : '<span class="text-muted">0</span>'}
                        ${hasPlusieursExamens ? '<span class="badge bg-danger ms-1" title="Erreur de saisie"><i class="fas fa-exclamation-triangle"></i></span>' : ''}
                    </td>
                    <td class="text-center">
                        ${classe.nb_avertissements > 0 ? `<span class="badge badge-avertissement">${classe.nb_avertissements}</span>` : '<span class="text-muted">0</span>'}
                    </td>
                    <td>${badgeEtat(saine)}</td>
                </tr>
            `;
        }).join('');

        // Initialiser DataTable uniquement si le tableau a des lignes réelles
        if (window.jQuery && $.fn.DataTable) {
            setTimeout(() => {
                if (!$.fn.DataTable.isDataTable('#tableClasses')) {
                    $('#tableClasses').DataTable({
                        pageLength: 10,
                        ordering: true,
                        searching: true,
                        info: true,
                        language: {
                            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
                        }
                    });
                }
            }, 0);
        }
    }

    function renderEtudiants(etudiants = []) {
        if (!Array.isArray(etudiants) || etudiants.length === 0) {
            return '<div class="text-muted">Aucun étudiant affiché.</div>';
        }

        return etudiants.map(etu => `
            <div class="student-box">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="mini-label">Matricule</div>
                        <div class="mini-value">${escapeHtml(etu.matricule || '-')}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="mini-label">Nom</div>
                        <div class="mini-value">${escapeHtml(etu.nom || '-')}</div>
                    </div>
                    <div class="col-md-2">
                        <div class="mini-label">Moyenne UE</div>
                        <div class="mini-value">${escapeHtml(etu.moyenne_ue ?? '-')}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="mini-label">EC valides / attendus</div>
                        <div class="mini-value">${escapeHtml(etu.ec_valides ?? 0)} / ${escapeHtml(etu.ec_attendus ?? 0)}</div>
                    </div>
                </div>

                ${(etu.anomalies || []).map(a => `
                    <div class="anomaly-line ${a.bloquant === false ? 'warning' : ''}">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>${escapeHtml(getAnomalyLabel(a.type) || a.libelle || a.message || a.type || 'anomalie')}</strong>
                            ${a.bloquant === false ? badgeGravite('avertissement') : badgeGravite('critique')}
                        </div>
                        ${a.ec_nom ? `<div class="small text-muted"><strong>EC :</strong> ${escapeHtml(a.ec_nom)}</div>` : ''}
                        ${a.message && a.message !== getAnomalyLabel(a.type) ? `<div class="small text-muted">${escapeHtml(a.message)}</div>` : ''}
                        ${a.detail ? `<div class="small text-muted"><strong>Détail :</strong> ${escapeHtml(a.detail)}</div>` : ''}
                    </div>
                `).join('')}
            </div>
        `).join('');
    }

    function renderBlocStatsUE(stats = {}, completude = {}) {
        return `
            <div class="row g-3 mb-5">
                <div class="col-md-2">
                    <div class="mini-label">Inscrits</div>
                    <div class="mini-value">${escapeHtml(stats.total_inscrits ?? 0)}</div>
                </div>
                <div class="col-md-2">
                    <div class="mini-label">Ayant composé</div>
                    <div class="mini-value">${escapeHtml(stats.effectif ?? 0)}</div>
                </div>
                <div class="col-md-2">
                    <div class="mini-label">Réussite</div>
                    <div class="mini-value">${escapeHtml(stats.reussite ?? 0)}</div>
                </div>
                <div class="col-md-2">
                    <div class="mini-label">Échec</div>
                    <div class="mini-value">${escapeHtml(stats.echec ?? 0)}</div>
                </div>
                <div class="col-md-2">
                    <div class="mini-label">Taux réussite</div>
                    <div class="mini-value">${escapeHtml(stats.tauxReussite ?? 0)}%</div>
                </div>
                <div class="col-md-2">
                    <div class="mini-label">Moyenne</div>
                    <div class="mini-value">${escapeHtml(stats.moyenne ?? 0)}</div>
                </div>

                <div class="col-md-2">
                    <div class="mini-label">Min</div>
                    <div class="mini-value">${escapeHtml(stats.min ?? 0)}</div>
                </div>
                <div class="col-md-2">
                    <div class="mini-label">Max</div>
                    <div class="mini-value">${escapeHtml(stats.max ?? 0)}</div>
                </div>
                <div class="col-md-2">
                    <div class="mini-label">Non composés</div>
                    <div class="mini-value">${escapeHtml(stats.non_composes ?? 0)}</div>
                </div>
                <div class="col-md-2">
                    <div class="mini-label">Complets</div>
                    <div class="mini-value">${escapeHtml(completude.etudiants_complets ?? 0)}</div>
                </div>
                <div class="col-md-2">
                    <div class="mini-label">Incomplets</div>
                    <div class="mini-value">${escapeHtml(completude.etudiants_incomplets ?? 0)}</div>
                </div>
                <div class="col-md-2">
                    <div class="mini-label">Taux complétude</div>
                    <div class="mini-value">${escapeHtml(completude.pourcentage_complets ?? 0)}%</div>
                </div>
            </div>
        `;
    }

    function construireAnomaliesUE(ue) {
        // Structure back : ue.anomalies[] directement (verifierEvaluationsToutesUE)
        if (Array.isArray(ue.anomalies) && ue.anomalies.length > 0) {
            return ue.anomalies.map(a => ({
                type:      a.type,
                libelle:   getAnomalyLabel(a.type) || a.libelle || a.type,
                gravite:   a.gravite || (['non_compose', 'devoir_manquant', 'devoir_incomplet', 'examen_non_compose'].includes(a.type) ? 'avertissement' : 'critique'),
                detail:    a.detail || null,
                etudiants: a.etudiants || [],
                bloquant:  a.bloquant !== false
            }));
        }

        // Fallback : structure completude (verifierEvaluationsUE)
        const completude = ue.completude || {};
        const raisons    = completude.raisons_incompletude || {};
        const incomplets = completude.liste_etudiants_incomplets || [];

        return Object.keys(raisons).map(raison => {
            const etudiantsConcernes = incomplets.filter(etu =>
                (etu.anomalies || []).some(a => a.raison === raison)
            );
            return {
                type:      raison,
                libelle:   getAnomalyLabel(raison),
                gravite:   ['non_compose', 'devoir_manquant', 'devoir_incomplet', 'examen_non_compose'].includes(raison) ? 'avertissement' : 'critique',
                detail:    `${raisons[raison]} étudiant(s) concerné(s)`,
                etudiants: etudiantsConcernes,
                bloquant:  !['non_compose', 'devoir_manquant', 'devoir_incomplet', 'examen_non_compose'].includes(raison)
            };
        });
    }

    function renderUE(ue, index) {
        const completude = ue.completude || {};
        const stats = ue.statistiques || {};
        const estComplete = (completude.etudiants_incomplets ?? 0) === 0;
        const collapseId = `collapse-ue-${index}`;
        const anomalies = construireAnomaliesUE(ue);

        return `
            <div class="ue-card">
                <div class="ue-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <div class="fw-bolder fs-5">
                            ${escapeHtml(ue.code || 'UE')} - ${escapeHtml(ue.nom || '')}
                        </div>
                        <div class="text-muted">
                            Crédit: ${escapeHtml(ue.credit ?? 0)} |
                            Étudiants: ${escapeHtml(completude.total_etudiants ?? 0)} |
                            Complets: ${escapeHtml(completude.etudiants_complets ?? 0)} |
                            Incomplets: ${escapeHtml(completude.etudiants_incomplets ?? 0)} |
                            Taux: ${escapeHtml(completude.pourcentage_complets ?? 0)}%
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        ${badgeEtat(estComplete)}
                        <button class="btn btn-sm btn-light-primary" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}">
                            Voir détails
                        </button>
                    </div>
                </div>

                <div id="${collapseId}" class="collapse">
                    <div class="ue-body">
                        ${renderBlocStatsUE(stats, completude)}
                        ${
                            anomalies.length
                                ? anomalies.map((a, i) => `
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                            <div>
                                                <strong>${escapeHtml(a.libelle || a.type || 'Anomalie')}</strong>
                                                ${a.detail ? `<span class="text-muted ms-2">(${escapeHtml(a.detail)})</span>` : ''}
                                            </div>
                                            <div>${badgeGravite(a.gravite)}</div>
                                        </div>
                                        ${renderEtudiants(a.etudiants || [])}
                                        ${i < anomalies.length - 1 ? '<hr>' : ''}
                                    </div>
                                `).join('')
                                : '<div class="text-success fw-semibold">Aucune anomalie détectée pour cette UE.</div>'
                        }
                    </div>
                </div>
            </div>
        `;
    }

    function getAnomalyLabel(type) {
        const labels = {
            'aucune_evaluation':      'Aucune évaluation',
            'aucune_note':            'Aucune note pour un EC',
            'pas_examen':             "Note d'examen manquante",
            'note_non_calculable':    'Note finale non calculable',
            'moyenne_non_calculable': 'Moyenne UE non calculable',
            'non_compose':            "Non composé à l'examen",
            'devoir_manquant':        'Devoir manquant',
            'devoir_incomplet':       'Devoir incomplet',
            'plusieurs_examens':      "⚠ Plusieurs notes d'examen saisies",
            'aucun_inscrit':          'Aucun étudiant inscrit',
            'aucune_ue':              'Aucune UE trouvée',
            'examen_non_compose':     "Non composé à l'examen — note = 0",
            'aucune_evaluation':      'Aucune évaluation saisie',
        };
        return labels[type] || type;
    }

    function renderClasse(classe, index) {
        const collapseId = `collapse-classe-${index}`;
        const hasPlusieursExamens = (classe.anomalies || []).some(ue =>
            (ue.anomalies || []).some(a => a.type === 'plusieurs_examens')
        );

        const uesHtml = (classe.anomalies || []).map((ueAnomalie, j) => {
            const collapseUeId = `collapse-ue-${index}-${j}`;

            const anomaliesHtml = (ueAnomalie.anomalies || []).map(a => {
                const isPlusieursExamens = a.type === 'plusieurs_examens';
                const gravite = a.gravite || 'critique';
                const alertClass = isPlusieursExamens
                    ? 'anomaly-line border-danger bg-danger bg-opacity-10'
                    : `anomaly-line ${gravite === 'avertissement' ? 'warning' : ''}`;

                const etudiantsHtml = (a.etudiants || []).slice(0, 5).map(etu => `
                    <span class="badge bg-light text-dark me-1 mb-1" title="${escapeHtml(etu.nom || '')}">
                        ${escapeHtml(etu.matricule || '')}
                    </span>
                `).join('');

                return `
                    <div class="${alertClass} p-2 mb-2 rounded">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong>
                                ${escapeHtml(getAnomalyLabel(a.type))}
                                ${isPlusieursExamens ? '<i class="fas fa-exclamation-triangle text-danger ms-1"></i>' : ''}
                            </strong>
                            ${badgeGravite(gravite)}
                        </div>
                        ${a.detail ? `<div class="text-muted small mb-1">${escapeHtml(a.detail)}</div>` : ''}
                        ${etudiantsHtml ? `<div class="mt-1">${etudiantsHtml}</div>` : ''}
                    </div>
                `;
            }).join('');

            return `
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center py-2 px-3 bg-light rounded"
                         style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#${collapseUeId}">
                        <span class="fw-semibold">
                            <span class="badge bg-primary me-2">${escapeHtml(ueAnomalie.semestre || '')}</span>
                            ${escapeHtml(ueAnomalie.code_ue || '')} — ${escapeHtml(ueAnomalie.nom_ue || '')}
                        </span>
                        <span class="text-muted small">
                            ${escapeHtml(ueAnomalie.nb_etudiants ?? 0)} inscrits
                            ${ueAnomalie.nb_incomplets > 0 ? `<span class="badge bg-danger ms-2">${ueAnomalie.nb_incomplets} incomplet(s)</span>` : ''}
                            ${ueAnomalie.taux_completude != null ? `<span class="ms-1">${ueAnomalie.taux_completude}%</span>` : ''}
                        </span>
                    </div>
                    <div id="${collapseUeId}" class="collapse show px-3 pt-2">
                        ${anomaliesHtml || '<div class="text-success small">Aucune anomalie</div>'}
                    </div>
                </div>
            `;
        }).join('');

        return `
            <div class="mb-4 border rounded ${hasPlusieursExamens ? 'border-danger' : ''}">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3
                            ${hasPlusieursExamens ? 'bg-danger bg-opacity-10' : 'bg-light'} rounded-top"
                     style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#${collapseId}">
                    <div>
                        <span class="fw-bolder fs-6">
                            ${escapeHtml(classe.filiere || '')} — ${escapeHtml(classe.niveau || '')} — ${escapeHtml(classe.option || '')}
                            <small class="text-muted">(${escapeHtml(classe.code || '')})</small>
                        </span>
                        <div class="text-muted small mt-1">
                            ${escapeHtml(classe.nb_inscrits ?? 0)} inscrits |
                            ${escapeHtml(classe.nb_ues_maquette ?? 0)} UEs
                            ${hasPlusieursExamens ? '<span class="text-danger ms-2 fw-semibold"><i class="fas fa-exclamation-triangle"></i> Erreur de saisie</span>' : ''}
                        </div>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        ${classe.nb_critiques > 0 ? `<span class="badge badge-critique">${classe.nb_critiques} critique(s)</span>` : ''}
                        ${classe.nb_avertissements > 0 ? `<span class="badge badge-avertissement">${classe.nb_avertissements} avert.</span>` : ''}
                        ${badgeEtat(classe.saine)}
                    </div>
                </div>
                <div id="${collapseId}" class="collapse show p-3">
                    ${uesHtml || '<div class="text-success fw-semibold">Aucune anomalie détectée.</div>'}
                </div>
            </div>
        `;
    }

    function renderRapport(rapport = []) {
        if (!Array.isArray(rapport) || rapport.length === 0) {
            zoneChargement.style.display = 'block';
            zoneChargement.innerHTML = 'Aucune classe trouvée.';
            rapportContainer.style.display = 'none';
            rapportContainer.innerHTML = '';
            return;
        }

        const classesAvecAnomalie = rapport.filter(c =>
            !c.saine || (c.anomalies || []).length > 0 || c.nb_critiques > 0 || c.nb_avertissements > 0
        );

        if (classesAvecAnomalie.length === 0) {
            zoneChargement.style.display = 'block';
            zoneChargement.innerHTML = `
                <div class="alert alert-success mb-0">
                    <i class="fas fa-check-circle me-2"></i>
                    Aucune anomalie détectée. Toutes les classes sont complètes.
                </div>
            `;
            rapportContainer.style.display = 'none';
            rapportContainer.innerHTML = '';
            return;
        }

        zoneChargement.style.display = 'none';
        rapportContainer.style.display = 'block';
        rapportContainer.innerHTML = classesAvecAnomalie.map((classe, index) => renderClasse(classe, index)).join('');
    }

    function showLoading() {
        zoneChargement.style.display = 'block';
        rapportContainer.style.display = 'none';
        zoneChargement.innerHTML = `
            <div class="d-flex flex-column align-items-center justify-content-center py-10">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <div class="text-muted">Chargement des anomalies...</div>
            </div>
        `;
    }

    async function chargerAnomalies() {
        try {
            showLoading();

            const queryString = buildQueryString();
            const url = `anomalies.php?${queryString}`;

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            const text = await response.text();
            let data;

            try {
                data = JSON.parse(text);
            } catch (e) {
                throw new Error('La réponse n’est pas un JSON valide. Vérifie les warnings PHP.');
            }

            if (!response.ok || data.success === false) {
                throw new Error(data.message || data.error || 'Erreur lors du chargement des anomalies.');
            }

            const rapport = data.rapport || data.classes || data.ues || [];
            updateStats(data.resume || {}, rapport.length, rapport);
            renderTableClasses(rapport);
            renderRapport(rapport);
        } catch (error) {
            zoneChargement.style.display = 'block';
            rapportContainer.style.display = 'none';
            zoneChargement.innerHTML = `
                <div class="alert alert-danger">
                    <strong>Erreur :</strong> ${escapeHtml(error.message)}
                </div>
            `;

            if (window.Swal) {
                Swal.fire({
                    icon: 'error',
                    title: 'Chargement impossible',
                    text: error.message
                });
            }
        }
    }

    function resetFilters() {
        if (filterFiliere) filterFiliere.value = '';
        if (filterNiveau) filterNiveau.value = '';
        if (filterOption) filterOption.value = '';
        if (filterSemestre) filterSemestre.value = '';
        if (filterSession) filterSession.value = '1';
    }

    filterFiliere?.addEventListener('change', async function () {
        try {
            await chargerOptions();
        } catch (e) {
            console.error(e);
        }
    });

    filterNiveau?.addEventListener('change', async function () {
        try {
            await chargerOptions();
        } catch (e) {
            console.error(e);
        }
    });

    btnCharger?.addEventListener('click', chargerAnomalies);

    btnReinitialiser?.addEventListener('click', async () => {
        resetFilters();
        try {
            await chargerOptions();
        } catch (e) {
            console.error(e);
        }
        chargerAnomalies();
    });

    document.addEventListener('DOMContentLoaded', async function () {
        try {
            await chargerFiltres();
            await chargerOptions();
        } catch (e) {
            console.error(e);
        }
    });
})();