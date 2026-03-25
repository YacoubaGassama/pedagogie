// Variables globales
const filieresSelect = document.getElementById('filiterFiliere');
const niveauxFormationSelect = document.getElementById('filterNiveau');
const optionsSelect = document.getElementById('filterOption');
const semestersSelect = document.getElementById('filterSemester');
const cycleSelect = document.getElementById('filterCycle');
let ueSelectionnees = [];
let ueDisponibles = [];
let statsUESelectionnees = {};



document.addEventListener('DOMContentLoaded', function () {
    initializePage();
    mettreAJourAffichageSelection();

    const modalSelectionUE = document.getElementById('modalSelectionUE');
    modalSelectionUE.addEventListener('show.bs.modal', function () {
        chargerUEDisponiblesAvecStatsCompletes();
    });

    const btnViderSelection = document.getElementById('btnViderSelection');
    if (btnViderSelection) {
        btnViderSelection.addEventListener('click', viderSelection);
    }

    const btnSimulationGlobale = document.getElementById('btnSimulationGlobale');
    if (btnSimulationGlobale) {
        btnSimulationGlobale.addEventListener('click', lancerSimulationGlobale);
    }

    const btnValiderSelection = document.getElementById('btnValiderSelection');
    if (btnValiderSelection) {
        btnValiderSelection.addEventListener('click', function () {
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalSelectionUE'));
            if (modal) modal.hide();
            mettreAJourAffichageSelection();
        });
    }
    // const btnAjouterUE = document.querySelector('.btnAjouterUE');
    //     btnAjouterUE.addEventListener('click', function() {
    //         chargerUEDisponiblesAvecStatsCompletes();
    //         console.log('Bouton Ajouter UE cliqué, chargement des UE disponibles avec statistiques...');
    //     });
    // Charger les UE avec stats quand le modal s'ouvre

    // Rafraîchir les statistiques
    const btnRafraichirStats = document.getElementById('btnRafraichirStats');
    if (btnRafraichirStats) {
        btnRafraichirStats.addEventListener('click', chargerStatsToutesUE);
    }

    // Écouter les changements de seuil pour mettre à jour les stats
    const seuilGlobal = document.getElementById('seuilGlobal');
    if (seuilGlobal) {
        seuilGlobal.addEventListener('change', chargerStatsToutesUE);
    }

    // Trier les UE dans le modal
    const triUE = document.getElementById('triUE');
    if (triUE) {
        triUE.addEventListener('change', function () {
            if (ueDisponibles.length > 0) {
                afficherUEDisponiblesAvecStats(ueDisponibles);
            }
        });
    }
});

// Initialisation de la page
function initializePage() {
    initializeSelect(niveauxFormationSelect, 'Sélectionner un Niveau');
    initializeSelect(filieresSelect, 'Sélectionner une Filière');
    initializeSelect(optionsSelect, 'Sélectionner une Option');

    Promise.all([
        loadFilieres(),
        // loadOptions()
    ]).then(() => {
        setupEventListeners();
        mettreAJourAffichageSelection();
    }).catch(error => {
        console.error('Erreur lors de l\'initialisation:', error);
    });
}

// Fonctions d'initialisation
function initializeSelect(selectElement, placeholder = '') {
    if (!placeholder) {
        placeholder = selectElement.id.includes('Cycle') ? 'Sélectionner un Cycle' :
            selectElement.id.includes('Niveau') ? 'Sélectionner un Niveau' :
                selectElement.id.includes('Option') ? 'Sélectionner une Option' :
                    selectElement.id.includes('Semester') ? 'Sélectionner un Semestre' :
                        selectElement.id.includes('Filiere') ? 'Sélectionner une Filière' :
                            'Sélectionner';
    }

    selectElement.innerHTML = '';
    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = placeholder;
    defaultOption.disabled = true;
    defaultOption.selected = true;
    selectElement.appendChild(defaultOption);
}


// Fonctions API
function getFilieres() {
    return fetch('deliberationUeController.php?action=listFilieres')
        .then(response => response.json());
}

function getNiveauxFormation(idCycleFormation = 0) {
    return fetch(`deliberationUeController.php?action=getNiveauFormationByCycle&idCycleFormation=${idCycleFormation}`)
        .then(response => response.json());
}

function getOptions(idFiliere = 0, idNiveauFormation = 0) {
    return fetch(`deliberationUeController.php?action=listOptionsByFiliere&idFiliere=${idFiliere}&idNiveauFormation=${idNiveauFormation}`)
        .then(response => response.json());
}

function getMaquetteUEs(filters) {
    const params = new URLSearchParams(filters);
    return fetch(`deliberationUeController.php?action=getMaquetteUEs&${params.toString()}`)
        .then(response => response.json());
}

function chargerStatsUE(ueId) {
    return fetch(`deliberationRegroupeeController.php?action=getStatistiquesUE&idUE=${ueId}`)
        .then(response => response.json())
        .then(data => ({
            stats: data.success ? data.stats : {},
            verification: data.verification ?? null
        }));
}

// Chargement des données
function loadFilieres() {
    return getFilieres()
        .then(filieres => {
            initializeSelect(filieresSelect, 'Sélectionner une Filière');
            filieres.forEach(filiere => {
                const option = document.createElement('option');
                option.value = filiere.id;
                option.textContent = filiere.filiere;
                filieresSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Erreur lors du chargement des filières:', error);
            initializeSelect(filieresSelect, 'Erreur de chargement');
        });
}

function loadOptions(filiereId = null, niveauFormationId = null) {
    if (filiereId) {
        return getOptions(filiereId, niveauFormationId)
            .then(options => {
                initializeSelect(optionsSelect, 'Sélectionner une Option');
                options.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt.id;
                    option.textContent = opt.option;
                    optionsSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Erreur lors du chargement des options:', error);
                initializeSelect(optionsSelect, 'Erreur de chargement');
            });
    } else {
        return getOptions()
            .then(options => {
                allOptions = options;
                initializeSelect(optionsSelect, 'Sélectionner une Option');
                options.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt.id;
                    option.textContent = opt.option;
                    optionsSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Erreur lors du chargement des options:', error);
                initializeSelect(optionsSelect, 'Erreur de chargement');
            });
    }
}

function loadNiveaux(cycleId) {
    if (cycleId) {
        return getNiveauxFormation(cycleId)
            .then(niveaux => {
                initializeSelect(niveauxFormationSelect, 'Sélectionner un Niveau');
                niveaux.forEach(niveau => {
                    const option = document.createElement('option');
                    option.value = niveau.id;
                    option.textContent = niveau.niveau;
                    niveauxFormationSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Erreur lors du chargement des niveaux:', error);
                initializeSelect(niveauxFormationSelect, 'Erreur de chargement');
            });
    } else {
        initializeSelect(niveauxFormationSelect, 'Sélectionner un Niveau');
        return Promise.resolve();
    }
}


// Configuration des écouteurs
function setupEventListeners() {
    // Écouteur pour le cycle
    cycleSelect.addEventListener('change', function () {
        const selectedCycleId = this.value;
        loadNiveaux(selectedCycleId);
    });

    // Écouteur pour la filière
    filieresSelect.addEventListener('change', function () {
        const selectedFiliereId = this.value;
        const selectedNiveauFormationId = niveauxFormationSelect.value;
        if (selectedFiliereId) {
            loadOptions(selectedFiliereId, selectedNiveauFormationId);
        } else {
            loadOptions();
        }
    });
    niveauxFormationSelect.addEventListener('change', function () {
        const selectedFiliereId = filieresSelect.value;
        const selectedNiveauFormationId = this.value;
        if (selectedFiliereId) {
            loadOptions(selectedFiliereId, selectedNiveauFormationId);
        } else {
            loadOptions();
        }
    });

    // // Écouteurs pour les autres filtres
    // [semestersSelect, niveauxFormationSelect, filieresSelect, optionsSelect, cycleSelect].forEach(select => {
    //     select.addEventListener('change', );
    // });
}

// Gestion de la sélection des UE
function mettreAJourAffichageSelection() {
    const listeUESelectionnees = document.getElementById('listeUESelectionnees');
    const nbUESelectionnees = document.getElementById('nbUESelectionnees');
    const countUESelectionnees = document.getElementById('countUESelectionnees');
    const infoUESelection = document.getElementById('infoUESelection');

    if (!listeUESelectionnees) return;

    const nbUE = ueSelectionnees.length;
    if (nbUESelectionnees) nbUESelectionnees.textContent = nbUE;
    if (countUESelectionnees) countUESelectionnees.textContent = nbUE;

    if (nbUE === 0) {
        listeUESelectionnees.innerHTML = `
            <div class="text-center py-4 text-muted">
                <i class="fas fa-book fa-2x mb-3"></i>
                <p>Aucune UE sélectionnée</p>
                <small>Cliquez sur "Ajouter des UE" pour commencer</small>
            </div>
        `;

        if (infoUESelection) infoUESelection.textContent = 'Aucune UE sélectionnée';

        const btnSimulation = document.getElementById('btnSimulationGlobale');
        if (btnSimulation) {
            btnSimulation.disabled = true;
            btnSimulation.title = 'Veuillez sélectionner au moins une UE';
        }

        afficherStatsUESelectionnees();
        return;
    }

    const btnSimulation = document.getElementById('btnSimulationGlobale');
    if (btnSimulation) {
        btnSimulation.disabled = false;
        btnSimulation.title = `Lancer la simulation sur ${nbUE} UE`;
    }

    let html = '<div class="row g-2">';
    ueSelectionnees.forEach((ue, index) => {
        html += `
            <div class="col-12">
                <div class="card border-primary border">
                    <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold">${ue.code}</div>
                            <small class="text-muted">${ue.nomUE.substring(0, 50)}${ue.nomUE.length > 50 ? '...' : ''}</small>
                            <div class="mt-1">
                                <span class="badge bg-primary">S${ue.semestre || '?'}</span>
                                <span class="badge">${ue.estDelibiere != null ? '<i class="fas fa-check text-success"></i>' : ''}</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-retirer-ue" data-index="${index}">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    listeUESelectionnees.innerHTML = html;
    chargerStatsToutesUE();

    document.querySelectorAll('.btn-retirer-ue').forEach(btn => {
        btn.addEventListener('click', function () {
            const index = parseInt(this.getAttribute('data-index'));
            retirerUE(index);
        });
    });

    if (infoUESelection) {
        const semestres = [...new Set(ueSelectionnees.map(ue => `S${ue.semestre}`))].join(', ');
        infoUESelection.textContent = `${nbUE} UE(s) - ${semestres}`;
    }
}

function retirerUE(index) {
    if (index >= 0 && index < ueSelectionnees.length) {
        ueSelectionnees.splice(index, 1);
        mettreAJourAffichageSelection();
        if (ueDisponibles.length > 0) {
            afficherUEDisponiblesAvecStats(ueDisponibles);
        }
    }
}

function viderSelection() {
    Swal.fire({
        title: 'Vider la sélection',
        text: `Voulez-vous vraiment retirer les ${ueSelectionnees.length} UE(s) sélectionnées ?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, vider',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            ueSelectionnees = [];
            mettreAJourAffichageSelection();
            if (ueDisponibles.length > 0) {
                afficherUEDisponiblesAvecStats(ueDisponibles);
            }
            Swal.fire('Succès', 'La sélection a été vidée', 'success');
        }
    });
}

// Chargement et affichage des UE avec statistiques
function chargerUEDisponiblesAvecStatsCompletes() {
    const listeUEDisponibles = document.getElementById('listeUEDisponibles');
    // const loadingUE = document.getElementById('loadingUE');

    console.log('Chargement des UE disponibles avec statistiques complètes...');
    // if (!listeUEDisponibles || !loadingUE) return;
    // loadingUE.classList.remove('d-none');
    listeUEDisponibles.innerHTML = `<div class="text-center py-4">
                                        <div class="spinner-border text-primary" id="loadingUE" role="status">
                                            <span class="visually-hidden">Chargement...</span>
                                        </div>
                                    </div>`;

    const filters = {
        idcycle: document.getElementById('filterCycle')?.value || '',
        idNiveauFormation: document.getElementById('filterNiveau')?.value || '',
        idFiliere: document.getElementById('filiterFiliere')?.value || '',
        idOption: document.getElementById('filterOption')?.value || '',
        idSemestre: document.getElementById('filterSemester')?.value || ''
    };

    getMaquetteUEs(filters)
        .then(ues => {
            if (!ues || ues.length === 0) {
                listeUEDisponibles.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Aucune UE trouvée avec les filtres sélectionnés
                        </div>
                    </div>
                `;
                return;
            }

            const promises = ues.map(ue =>
                chargerStatsUE(ue.idUE)
                    .then(({ stats, verification }) => ({ ...ue, stats, verification }))
                    .catch(() => ({ ...ue, stats: {}, verification: null }))
            );

            Promise.all(promises)
                .then(uesAvecStats => {
                    ueDisponibles = uesAvecStats;
                    afficherUEDisponiblesAvecStats(uesAvecStats);
                    // loadingUE.classList.add('d-none');
                })
                .catch(error => {
                    console.error('Erreur chargement stats:', error);
                    listeUEDisponibles.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Erreur lors du chargement des statistiques
                        </div>
                    `;
                    // loadingUE.classList.add('d-none');
                });
        })
        .catch(error => {
            console.error('Erreur chargement UE:', error);
            listeUEDisponibles.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Erreur lors du chargement des UE
                </div>
            `;
            // loadingUE.classList.add('d-none');
        });
}

function afficherUEDisponiblesAvecStats(ues) {
    const listeUEDisponibles = document.getElementById('listeUEDisponibles');
    if (!listeUEDisponibles) return;

    if (!ues || ues.length === 0) {
        listeUEDisponibles.innerHTML = `
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Aucune UE trouvée avec les filtres sélectionnés
                </div>
            </div>
        `;
        return;
    }

    const tri = document.getElementById('triUE')?.value || 'code';
    let uesTriees = [...ues];

    switch (tri) {
        case 'moyenne':
            uesTriees.sort((a, b) => (a.stats?.moyenne || 0) - (b.stats?.moyenne || 0));
            break;
        case 'moyenne_desc':
            uesTriees.sort((a, b) => (b.stats?.moyenne || 0) - (a.stats?.moyenne || 0));
            break;
        case 'reussite':
            uesTriees.sort((a, b) => (a.stats?.tauxReussite || 0) - (b.stats?.tauxReussite || 0));
            break;
        case 'effectif':
            uesTriees.sort((a, b) => (a.stats?.effectif || 0) - (b.stats?.effectif || 0));
            break;
        default:
            uesTriees.sort((a, b) => a.code.localeCompare(b.code));
    }

    let html = '<div class="row g-2">';

    uesTriees.forEach(ue => {
        console.log('code : ' + ue.code + ' \n effectifs : ' + ue)

        const estSelectionnee = ueSelectionnees.some(ueSel => ueSel.idUE === ue.idUE);
        const stats = ue.stats || {};
        const seuil = parseFloat(document.getElementById('seuilGlobal')?.value) || 8.0;
        const potentielRep = stats.effectif > 0 ? Math.round((stats.echec || 0) * ((10 - seuil) / 10)) : 0;
        const incomplets = ue.verification?.etudiants_incomplets > 0;
        const sansDonnees = ue.verification.total_etudiants === 0;
        const desactivee = sansDonnees || incomplets;
        const moyenne = stats.moyenne || 0;
        const moyenneCls = moyenne >= 10 ? 'text-success' : moyenne >= 8 ? 'text-warning' : 'text-danger';

        html += `
        <div class="col-md-6">
            <div class="card ue-card mb-2 ${estSelectionnee ? 'border-primary bg-primary bg-opacity-10' : 'border-0 shadow-sm'}
                                          ${desactivee ? 'opacity-50' : ''}">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="form-check flex-grow-1 m-0">
                            <input class="form-check-input ue-checkbox"
                                   type="checkbox"
                                   ${desactivee ? 'disabled' : ''}
                                   value="${ue.idUE}"
                                   id="ue_${ue.idUE}"
                                   ${estSelectionnee ? 'checked' : ''}
                                   data-ue='${JSON.stringify(ue).replace(/'/g, "&#39;")}'>
                            <label class="form-check-label w-100 ${desactivee ? 'text-muted' : ''}" for="ue_${ue.idUE}">

                                <!-- En-tête -->
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="fw-bold">${ue.code}</span>
                                    <div>
                                        <span class="badge bg-secondary">S${ue.numeroSemestre || '?'}</span>
                                        <span class="badge">${ue.estDelibiere != null ? '<i class="fas fa-check text-success"></i>' : ''}</span>
                                    </div>
                                </div>
                                <div class="text-muted small mb-2">
                                    ${ue.nomUE.length > 45 ? ue.nomUE.substring(0, 45) + '…' : ue.nomUE}
                                </div>

                                ${desactivee ? `
                                <!-- Message d'erreur carte grisée -->
                                <div class="alert alert-warning py-1 px-2 mb-0 small">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                    ${sansDonnees
                    ? 'Aucune donnée disponible pour cette UE.'
                    : `${ue.verification.etudiants_incomplets} étudiant(s) avec notes manquantes — délibération impossible.`
                }
                                    
                                </div>
                                ` : `
                                <!-- Stats en grille -->
                                <div class="row g-1 small mb-2">
                                    <div class="col-6">
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Effectif</span>
                                            <span class="fw-bold">${stats.effectif}</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Moyenne</span>
                                            <span class="fw-bold ${moyenneCls}">${moyenne.toFixed(2)}</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Réussite</span>
                                            <span class="fw-bold text-success">${stats.reussite || 0}</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Échec</span>
                                            <span class="fw-bold text-danger">${stats.echec || 0}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Potentiel repêchage -->
                                <!--<div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">Potentiel repêchage</small>
                                    <span class="badge ${stats > 0 ? 'bg-warning text-dark' : 'bg-secondary'}">
                                        ${potentielRep} étudiant(s)
                                    </span>
                                </div> -->

                                `}

                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    });

    html += '</div>';
    listeUEDisponibles.innerHTML = html;

    const nbUESelectionneesModal = document.getElementById('nbUESelectionneesModal');
    if (nbUESelectionneesModal) {
        nbUESelectionneesModal.textContent = ueSelectionnees.length;
    }

    document.querySelectorAll('.ue-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            const ueData = JSON.parse(this.getAttribute('data-ue').replace(/&#39;/g, "'"));

            if (this.checked) {
                if (!ueSelectionnees.some(ue => ue.idUE === ueData.idUE)) {
                    ueSelectionnees.push({
                        idUE: ueData.idUE,
                        code: ueData.code,
                        nomUE: ueData.nomUE,
                        semestre: ueData.numeroSemestre,
                        niveau: ueData.niveau,
                        stats: ueData.stats || {},   // ← ajouter
                        verification: ueData.verification ?? null  // ← ajouter
                    });
                }
            } else {
                ueSelectionnees = ueSelectionnees.filter(ue => ue.idUE !== ueData.idUE);
            }

            mettreAJourAffichageSelection();
        });
    });
}

// Statistiques des UE
function afficherStatsUESelectionnees() {
    const statsUEContainer = document.getElementById('statsUEContainer');
    const statsPlaceholder = document.getElementById('statsPlaceholder');
    const simulationPanel = document.getElementById('simulationPanel');

    if (!statsUEContainer) return;

    if (ueSelectionnees.length === 0) {
        statsUEContainer.innerHTML = `
            <div class="text-center py-5 text-muted" id="statsPlaceholder">
                <i class="fas fa-chart-line fa-2x mb-3"></i>
                <p>Sélectionnez des UE pour voir leurs statistiques</p>
            </div>
        `;
        if (simulationPanel) simulationPanel.style.display = 'none';
        return;
    }

    if (statsPlaceholder) statsPlaceholder.style.display = 'none';
    if (simulationPanel) simulationPanel.style.display = 'block';

    let html = `
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="120">Code UE</th>
                        <th>Nom</th>
                        <th class="text-center">Effectif</th>
                        <th class="text-center">Moyenne</th>
                        <th class="text-center">Réussite</th>
                        <th class="text-center">Échec</th>
                        <th class="text-center">Potentiel repêchage*</th>
                    </tr>
                </thead>
                <tbody>
    `;

    const uesTriees = [...ueSelectionnees].sort((a, b) => a.code.localeCompare(b.code));
    const seuilGlobal = parseFloat(document.getElementById('seuilGlobal')?.value) || 8.0;

    // Générer le panel de simulation UNE SEULE FOIS
    if (simulationPanel) {
        simulationPanel.innerHTML = ``;
    }

    uesTriees.forEach(ue => {
        simulationPanel.innerHTML += genererPanelSimulationParUE(ue); // Utiliser la UE courante
        const stats = ue.stats || {};
        const moyenne = parseFloat(stats.moyenne) || 0;
        const effectif = parseInt(stats.effectif) || 0;

        // Calcul plus précis du potentiel de repêchage
        let potentielRep = 0;

        if (stats.intervalle_9_10 !== undefined) {
            // Si on a les intervalles détaillés, on peut calculer précisément
            const intervalleSeuil = Math.floor(seuilGlobal);

            if (seuilGlobal >= 9 && seuilGlobal < 10) {
                // Pour un seuil entre 9 et 10, on prend l'intervalle 9_10
                potentielRep = parseInt(stats.intervalle_9_10) || 0;
            } else if (seuilGlobal >= 8 && seuilGlobal < 9) {
                // Pour un seuil entre 8 et 9, on prend 8_9 + 9_10
                potentielRep = (parseInt(stats.intervalle_8_9) || 0) + (parseInt(stats.intervalle_9_10) || 0);
            } else if (seuilGlobal >= 7 && seuilGlobal < 8) {
                // Pour un seuil entre 7 et 8, on prend 7_8 + 8_9 + 9_10
                potentielRep = (parseInt(stats.intervalle_7_8) || 0) +
                    (parseInt(stats.intervalle_8_9) || 0) +
                    (parseInt(stats.intervalle_9_10) || 0);
            } else {
                // Pour seuil < 7, on prend tout sauf les 0_7
                potentielRep = (parseInt(stats.intervalle_7_8) || 0) +
                    (parseInt(stats.intervalle_8_9) || 0) +
                    (parseInt(stats.intervalle_9_10) || 0) +
                    (parseInt(stats.intervalle_10_20) || 0);
            }
        } else {
            // Fallback : estimation basée sur le taux d'échec
            // On considère qu'une partie des étudiants en échec sont dans la zone repêchable
            const echec = parseInt(stats.echec) || 0;
            const proportionRep = Math.min(1, (10 - seuilGlobal) / 3); // Plus le seuil est bas, plus on peut repêcher
            potentielRep = Math.round(echec * proportionRep);
        }

        html += `
            <tr>
                <td class="fw-bold">${ue.code}</td>
                <td class="small" title="${ue.nomUE}">
                    ${ue.nomUE.substring(0, 30)}${ue.nomUE.length > 30 ? '...' : ''}
                </td>
                <td class="text-center">
                    <span class="badge bg-secondary">${effectif}</span>
                </td>
                <td class="text-center">
                    <span class="badge ${moyenne >= 10 ? 'bg-success' : moyenne >= 8 ? 'bg-warning' : 'bg-danger'}">
                        ${moyenne.toFixed(2)}
                    </span>
                </td>
                <td class="text-center">
                    <div class="d-flex flex-column align-items-center">
                        <span class="badge bg-success">${stats.reussite || 0}</span>
                        <small class="text-muted">${parseFloat(stats.tauxReussite || 0).toFixed(1)}%</small>
                    </div>
                </td>
                <td class="text-center">
                    <div class="d-flex flex-column align-items-center">
                        <span class="badge bg-danger">${stats.echec || 0}</span>
                        <small class="text-muted">${parseFloat(stats.tauxEchec || 0).toFixed(1)}%</small>
                    </div>
                </td>
                <td class="text-center">
                    <div class="d-flex flex-column align-items-center">
                        <span class="badge ${potentielRep > 0 ? 'bg-warning' : 'bg-secondary'}">
                            ${potentielRep}
                        </span>
                        <small class="text-muted">≥${seuilGlobal}/20</small>
                    </div>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-light">
            <small class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                *Potentiel repêchage: estimation basée sur le taux d'échec et le seuil de ${seuilGlobal}/20
            </small>
        </div>
    `;

    statsUEContainer.innerHTML = html;

    // Mettre à jour le compteur d'UE sélectionnées
    const countElement = document.getElementById('countUESelectionnees');
    if (countElement) {
        countElement.textContent = ueSelectionnees.length;
    }
}

function genererPanelSimulationParUE(ue) {
    const seuil = parseFloat(document.getElementById('seuilGlobal')?.value) || 9.0;

    // ID pour le conteneur des options à masquer
    const optionsContainerId = `optionsContainer_${ue.idUE}`;
    const checkboxId = `delibererAvecRepechage${ue.idUE}`;

    let html = `
    <div class="card mb-3 border-secondary p-3 border b-2">
        <h6 class="fw-bold mb-3">Paramètres de ${ue.code} (${ue.nomUE}) :</h6>
        
        <div class="col-md-4 mb-2">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="${checkboxId}" 
                       onchange="toggleOptionsRepechage('${ue.idUE}')">
                <label class="form-check-label fw-bold text-muted" for="${checkboxId}">
                    <i class="fas fa-redo me-1 text-primary"></i>
                    Activer le Repêchage
                </label>
            </div>
        </div>

        <div id="${optionsContainerId}" class="row g-3 align-items-end mt-2" style="display: none;">
            
            <div class="col-md-5">
                <label class="form-label fw-bold text-muted">
                    <i class="fas fa-balance-scale me-1 text-primary"></i>
                    Stratégie de distribution
                </label>
                <select id="strategySelect_${ue.idUE}" class="form-select">
                    <option value="neutral">Neutre (pondérée par coef)</option>
                    <option value="favor_low">Avantager notes faibles</option>
                    <option value="favor_high">Avantager notes fortes</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold text-muted">
                    <i class="fas fa-chart-line me-1 text-primary"></i>
                    Seuil de repêchage
                </label>
                <div class="input-group">
                    <input type="number" class="form-control" id="seuil_${ue.idUE}" min="0" max="20" step="0.1" value="${seuil}">
                    <span class="input-group-text">/20</span>
                </div>
            </div>
            
            <div class="col-md-4 d-flex justify-content-center align-items-center">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="lockGe10_${ue.idUE}" checked>
                    <label class="form-check-label fw-bold text-muted" for="lockGe10_${ue.idUE}">
                        <i class="fas fa-lock me-1 text-primary"></i>
                        Bloquer EC ≥ 10
                    </label>
                </div>
            </div>
        </div>
    </div>`;

    return html;
}

/**
 * Fonction pour masquer/afficher les options
 */
function toggleOptionsRepechage(ueId) {
    const isChecked = document.getElementById(`delibererAvecRepechage${ueId}`).checked;
    const container = document.getElementById(`optionsContainer_${ueId}`);

    // Si coché -> on affiche (block), sinon on cache (none)
    container.style.display = isChecked ? 'flex' : 'none';
}
function chargerStatsToutesUE() {
    if (ueSelectionnees.length === 0) {
        afficherStatsUESelectionnees();
        return;
    }

    const statsUEContainer = document.getElementById('statsUEContainer');
    if (statsUEContainer) {
        statsUEContainer.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement des statistiques...</span>
                </div>
                <p class="mt-2 text-muted">Chargement des statistiques des UE...</p>
            </div>
        `;
    }

    const promises = ueSelectionnees.map(ue =>
        chargerStatsUE(ue.idUE)
            .then(stats => {
                statsUESelectionnees[ue.idUE] = stats;
                return { ue, stats };
            })
            .catch(() => {
                statsUESelectionnees[ue.idUE] = {
                    effectif: 0,
                    moyenne: 0,
                    reussite: 0,
                    echec: 0,
                    tauxReussite: 0,
                    tauxEchec: 0
                };
                return { ue, stats: statsUESelectionnees[ue.idUE] };
            })
    );

    Promise.all(promises)
        .then(() => afficherStatsUESelectionnees())
        .catch(() => {
            if (statsUEContainer) {
                statsUEContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Erreur lors du chargement des statistiques
                    </div>
                `;
            }
        });
}

// Simulation globale
// Simulation globale
function lancerSimulationGlobale() {
    if (ueSelectionnees.length === 0) {
        Swal.fire('Information', 'Veuillez sélectionner au moins une UE', 'info');
        return;
    }

    // Récupérer les paramètres depuis le panel simulation (pour la première UE comme référence)
    const premierUE = ueSelectionnees[0];
    const seuil = parseFloat(document.getElementById(`seuil_${premierUE.idUE}`)?.value) || 8.0;
    const strategy = document.getElementById(`strategySelect_${premierUE.idUE}`)?.value || 'neutral';
    const rounding = document.getElementById(`roundingSelect_${premierUE.idUE}`)?.value || '0.01';
    const lockGe10 = document.getElementById(`lockGe10_${premierUE.idUE}`)?.checked ? true : false;
    const delibererAvecRepechage = document.getElementById(`delibererAvecRepechage${premierUE.idUE}`)?.checked ? true : false;

    const btnSimulation = document.getElementById('btnSimulationGlobale');
    const resultatsGlobaux = document.getElementById('resultatsGlobaux');

    if (!btnSimulation || !resultatsGlobaux) return;

    btnSimulation.disabled = true;
    const originalText = btnSimulation.innerHTML;
    btnSimulation.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Simulation en cours...';

    resultatsGlobaux.innerHTML = `
        <div class="card">
            <div class="card-body text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <h5 class="card-title">Simulation globale en cours</h5>
                <p class="text-muted">
                    Traitement de ${ueSelectionnees.length} UE(s) avec seuil à ${seuil}/20
                </p>
            </div>
        </div>
    `;


    function traiterUE(ue) {
        return new Promise((resolve, reject) => {
            const seuilUE = parseFloat(document.getElementById(`seuil_${ue.idUE}`)?.value) || seuil;
            const strategyUE = document.getElementById(`strategySelect_${ue.idUE}`)?.value || strategy;
            const roundingUE = document.getElementById(`roundingSelect_${ue.idUE}`)?.value || rounding;
            const lockGe10UE = document.getElementById(`lockGe10_${ue.idUE}`)?.checked ?? lockGe10;
            const sansRepechage = document.getElementById(`delibererAvecRepechage${ue.idUE}`)?.checked === false;

            fetch(`deliberationRegroupeeController.php?action=getEtudiantByUE&idUE=${ue.idUE}&session_id=1`)
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    return res.json();
                })
                .then(etudiants => {
                    if (!Array.isArray(etudiants)) throw new Error('Format de données invalide');

                    // Filtrer + mapper en conservant TOUS les champs nécessaires au back
                    const etudiantsEligibles = etudiants
                        .filter(e => {
                            if (!e.ec || !Array.isArray(e.ec)) return false;

                            // Exclure si un EC est non composé à l'examen
                            const aExamenNonCompose = e.ec.some(ec =>
                                ec.examen_non_compose === true
                            );
                            if (aExamenNonCompose) return false;

                            // Exclure si moyenne non calculable (EC avec note null)
                            const moyenneCalculable = e.ec.every(ec =>
                                ec.note_finale_ec !== null && ec.note_finale_ec !== undefined
                            );
                            if (!moyenneCalculable) return false;

                            // Vérifier l'éligibilité par le seuil
                            const moy = e.moyenne_ue ?? 0;
                            return moy >= seuilUE && moy < 10;
                        })
                        .map(e => ({
                            matricule: e.matricule,
                            prenom: e.prenom ?? '',
                            nom: e.nom ?? '',
                            moyenne_ue: e.moyenne_ue ?? 0,
                            ec: e.ec.map(ec => ({
                                id: ec.id,
                                name: ec.name || ec.nom || '',
                                note: parseFloat(ec.note_finale_ec ?? ec.note ?? 0),
                                note_finale_ec: parseFloat(ec.note_finale_ec ?? 0),
                                coef: parseFloat(ec.coef_ec ?? ec.coef ?? 1),
                                coef_ec: parseFloat(ec.coef_ec ?? ec.coef ?? 1),
                                note_initial: parseFloat(ec.note_finale_ec ?? ec.note ?? 0),
                                note_devoir: ec.note_devoir ?? null,
                                note_examen: ec.note_examen ?? null,
                                calcul_mode: ec.calcul_mode ?? null,
                                devoirs: ec.devoirs ?? [],
                                examens: ec.examens ?? []
                            }))
                        }));

                    return fetch('deliberationRegroupeeController.php?action=simulerRepechage', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            idUE: ue.idUE,
                            minMoyenne: seuilUE,
                            strategy: strategyUE,
                            rounding_step: roundingUE,
                            lock_ge10: lockGe10UE,
                            etudiantsEligibles: etudiantsEligibles,
                            sans_repechage: sansRepechage
                        })
                    });
                })
                .then(res => {
                    if (!res) return null;
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    return res.json();
                })
                .then(result => {
                    if (result?.success && result.simulations) {
                        resolve({
                            ue,
                            simulations: result.simulations,
                            nbEtudiants: result.simulations.length,
                            sans_repechage: sansRepechage,
                            parametres: {
                                seuil: seuilUE,
                                strategy: strategyUE,
                                rounding: roundingUE,
                                lockGe10: lockGe10UE,
                            }
                        });
                    } else {
                        resolve(null);
                    }
                })
                .catch(error => {
                    console.error(`Erreur UE ${ue.code}:`, error);
                    erreurs.push(`UE ${ue.code}: ${error.message}`);
                    resolve(null);
                });
        });
    }

    async function executerSimulations() {
        const resultatsFinaux = [];
        const erreursFinales = [];

        for (let i = 0; i < ueSelectionnees.length; i += 3) {
            const chunk = ueSelectionnees.slice(i, i + 3);
            await Promise.all(chunk.map(ue => {

                const delibererAvecRepechage = document.getElementById(`delibererAvecRepechage${ue.idUE}`)?.checked ?? false;
                const seuilUE = parseFloat(document.getElementById(`seuil_${ue.idUE}`)?.value) || seuil;
                const strategyUE = document.getElementById(`strategySelect_${ue.idUE}`)?.value || strategy;
                const roundingUE = document.getElementById(`roundingSelect_${ue.idUE}`)?.value || rounding;
                const lockGe10UE = document.getElementById(`lockGe10_${ue.idUE}`)?.checked ?? lockGe10;

                // Cas délibération sans repêchage — juste récupérer les étudiants pour aperçu
                if (!delibererAvecRepechage) {
                    return fetch(`deliberationRegroupeeController.php?action=getEtudiantByUE&idUE=${ue.idUE}&session_id=1`)
                        .then(res => {
                            if (!res.ok) throw new Error(`HTTP ${res.status}`);
                            return res.json();
                        })
                        .then(etudiants => {
                            if (!Array.isArray(etudiants)) throw new Error('Format invalide');

                            // Calculer les moyennes pour l'aperçu uniquement
                            const apercu = etudiants.map(e => {
                                const moyenne = e.moyenne_ue ?? 0;
                                return {
                                    matricule: e.matricule,
                                    nom: `${e.prenom} ${e.nom}`,
                                    moyenne,
                                    valide: moyenne >= 10
                                };
                            });

                            resultatsFinaux.push({
                                ue,
                                sans_repechage: true,
                                simulations: [],
                                apercu,
                                nbEtudiants: apercu.length,
                                nbValides: apercu.filter(e => e.valide).length,
                                nbNonValides: apercu.filter(e => !e.valide).length,
                                parametres: {
                                    seuil: seuilUE,
                                    strategy: 'none',
                                    rounding: roundingUE,
                                    lockGe10: false,
                                    delibererAvecRepecher: false
                                }
                            });
                        })
                        .catch(err => {
                            erreursFinales.push(`UE ${ue.codeUE} : ${err.message}`);
                        });
                }

                // Cas avec repêchage — simulation existante
                return traiterUE(ue)
                    .then(result => { if (result) resultatsFinaux.push(result); })
                    .catch(err => { erreursFinales.push(`UE ${ue.codeUE} : ${err.message}`); });
            }));
        }

        return { simulations: resultatsFinaux, erreurs: erreursFinales };
    }

    executerSimulations()
        .then(({ simulations, erreurs }) => {
            btnSimulation.disabled = false;
            btnSimulation.innerHTML = originalText;

            if (erreurs.length > 0) {
                console.warn('Erreurs rencontrées:', erreurs);
            }

            if (simulations.length === 0) {
                resultatsGlobaux.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Aucune simulation n'a pu être effectuée. ${erreurs.length > 0 ? 'Vérifiez la console pour plus de détails.' : ''}
                    </div>
                `;
                return;
            }

            afficherResultatsGlobaux(simulations, erreurs);
        })
        .catch(error => {
            console.error('Erreur simulation globale:', error);
            btnSimulation.disabled = false;
            btnSimulation.innerHTML = originalText;
            resultatsGlobaux.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Erreur lors de la simulation globale: ${error.message}
                </div>
            `;
        });
}

function afficherResultatsGlobaux(resultats, erreurs = []) {
    const resultatsGlobaux = document.getElementById('resultatsGlobaux');
    if (!resultatsGlobaux) return;

    const totalUE = resultats.length;
    // EPR = uniquement les UE avec repêchage (les UE sans repêchage n'ont pas d'étudiants repêchables)
    const totalEtudiants = resultats.reduce((sum, r) => {
        if (r.sans_repechage) return sum;
        return sum + (r.nbEtudiants || 0);
    }, 0);

    let html = `
        <div class="card border-success">
            <div class="card-header bg-success bg-opacity-10 border-success d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title text-success mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        Résultats de la simulation globale
                    </h4>
                    <small class="text-muted">${totalUE} UE(s) traitées</small>
                </div>
                <button class="btn btn-sm btn-success" id="btnAppliquerGlobal" ${totalUE === 0 ? 'disabled' : ''}>
                    <i class="fas fa-save me-1"></i> Appliquer tout
                </button>
            </div>
            
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card border-primary border">
                            <div class="card-body text-center">
                                <h6 class="card-subtitle mb-2 text-muted">UE concernées</h6>
                                <h2 class="card-title text-primary">${totalUE}</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-success border">
                            <div class="card-body text-center">
                                <h6 class="card-subtitle mb-2 text-muted">Étudiants Potentiellement Repechables (EPR)</h6>
                                <h2 class="card-title text-success">${totalEtudiants}</h2>
                            </div>
                        </div>
                    </div>
                    
                    <!--<div class="col-md-3">
                        <div class="card border-info border">
                            <div class="card-body text-center">
                                <h6 class="card-subtitle mb-2 text-muted">Taux de repêchage</h6>
                                <h2 class="card-title text-info">${totalUE > 0 && totalEtudiants > 0 ? ((totalEtudiants / (totalEtudiants + totalEtudiants)) * 100).toFixed(1) : 0}%</h2>
                            </div>
                        </div>
                    </div> -->
                </div>
                
                ${erreurs.length > 0 ? `
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Attention :</strong> ${erreurs.length} erreur(s) rencontrée(s) lors de la simulation.
                    <ul class="mt-2 mb-0">
                        ${erreurs.map(err => `<li class="small">${err}</li>`).join('')}
                    </ul>
                </div>
                ` : ''}
                
                <h6 class="fw-bold text-muted mb-3">Détails par UE :</h6>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>UE</th>
                                <th>Nom</th>
                                <th class="text-center">Paramètres</th>
                                <th class="text-center">EPR</th>
                            </tr>
                        </thead>
                        <tbody>
    `;

    resultats
        .filter(result => result && result.ue)
        .sort((a, b) => (a.ue.code || '').localeCompare(b.ue.code || ''))
        .forEach(result => {
            if (!result || !result.ue) return;

            const sanRepechage = result.sans_repechage === true;

            html += `
        <tr>
            <td class="fw-bold">${result.ue.code || 'N/A'}</td>
            <td class="small">
                ${result.ue.nomUE
                    ? result.ue.nomUE.substring(0, 40) + (result.ue.nomUE.length > 40 ? '...' : '')
                    : 'N/A'}
            </td>
            <td class="text-center">
                ${sanRepechage
                    ? '<span class="badge bg-secondary">Sans repêchage</span>'
                    : `<small>
                        <span class="badge bg-info">${result.parametres?.seuil || '?'}/20</span>
                        <span class="badge bg-secondary ms-1">
                            ${result.parametres?.strategy === 'favor_low' ? 'Faibles'
                        : result.parametres?.strategy === 'favor_high' ? 'Fortes'
                            : 'Neutre'}
                        </span>
                       </small>`
                }
            </td>
            <td class="text-center">
                ${sanRepechage
                    ? '<span class="badge bg-secondary">—</span>'
                    : `<span class="badge ${result.nbEtudiants > 0 ? 'bg-success' : 'bg-secondary'}">
                            ${result.nbEtudiants || 0}
                       </span>`
                }
            </td>
            
        </tr>
    `;
        });

    html += `
                        </tbody>
                    </table>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Légende des paramètres :</strong>
                                <span class="badge bg-info ms-2">Seuil/20</span> 
                                <span class="badge bg-secondary ms-1">Faibles = Avantager notes faibles</span>
                                <span class="badge bg-secondary ms-1">Fortes = Avantager notes fortes</span>
                                <span class="badge bg-secondary ms-1">Neutre = Pondérée par coef</span>
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-warning mt-2">
                    <small>
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Pour appliquer ces modifications, cliquez sur "Appliquer tout". Cette action est irréversible.
                    </small>
                </div>
            </div>
        </div>
    `;

    resultatsGlobaux.innerHTML = html;

    const btnAppliquer = document.getElementById('btnAppliquerGlobal');
    if (btnAppliquer) {
        btnAppliquer.addEventListener('click', () => {
            appliquerRepêchageGlobal(resultats);
        });
    }
}

async function appliquerRepêchageGlobal(resultats) {
    const confirmation = await Swal.fire({
        title: "Confirmer l'application",
        html: `Cette action va délibérer <strong>${resultats.length} UE(s)</strong>.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Appliquer',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#d33'
    });

    if (!confirmation.isConfirmed) return;

    const btnAppliquer = document.getElementById('btnAppliquerGlobal');
    if (btnAppliquer) {
        btnAppliquer.disabled = true;
        btnAppliquer.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Application en cours...';
    }

    Swal.fire({
        title: 'Délibération en cours...',
        text: 'Veuillez patienter quelques instants',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        // ── Séparer les deux cas en une seule passe ───────────────────────────
        const avecRepechage = resultats.filter(r => !r.sans_repechage );
        const sansRepechage = resultats.filter(r => r.sans_repechage);

        const promesses = [];

        // ── 1. UEs sans repêchage → une seule requête ─────────────────────────
        if (sansRepechage.length > 0) {
            promesses.push(
                fetch('deliberationRegroupeeController.php?action=delibererSansRepechage', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        idUEs: sansRepechage.map(r => r.ue.idUE)
                    })
                }).then(r => r.json())
            );
        }

        // ── 2. UEs avec repêchage → une seule requête ─────────────────────────
        if (avecRepechage.length > 0) {
            promesses.push(
                fetch('deliberationRegroupeeController.php?action=appliquerRepechageGlobal', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        applications: avecRepechage.map(r => ({
                            idUE: r.ue.idUE,
                            simulations: r.simulations,
                            intervalle: { min: r.parametres.seuil },
                            strategy: r.parametres.strategy,
                            rounding: r.parametres.rounding,
                            lockGe10: r.parametres.lockGe10
                        }))
                    })
                }).then(r => r.json())
            );
        }

        // ── 3. Exécuter les deux en parallèle ─────────────────────────────────
        const reponses = await Promise.all(promesses);

        const erreurs = [];
        let nbSucces = 0;

        reponses.forEach(data => {
            if (data.success) {
                nbSucces += data.stats?.ueTraitees ?? data.resultats?.length ?? 1;
            } else {
                erreurs.push(data.message || 'Erreur inconnue');
            }
            // Erreurs partielles (certaines UEs ont échoué dans le batch)
            if (data.erreurs?.length) {
                erreurs.push(...data.erreurs);
            }
        });

        // ── 4. Résumé ─────────────────────────────────────────────────────────
        if (erreurs.length === 0) {
            Swal.fire({
                icon: 'success',
                title: 'Délibération appliquée',
                html: `<strong>${nbSucces} UE(s)</strong> délibérée(s) avec succès.`
            });
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Application partielle',
                html: `
                    <p><strong>${nbSucces}</strong> UE(s) appliquée(s).</p>
                    <p><strong>${erreurs.length}</strong> erreur(s) :</p>
                    <ul class="text-start">
                        ${erreurs.map(e => `<li>${e}</li>`).join('')}
                    </ul>
                `
            });
        }

    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Erreur critique',
            text: err.message
        });
    } finally {
        if (btnAppliquer) {
            btnAppliquer.disabled = false;
            btnAppliquer.innerHTML = '<i class="fas fa-save me-1"></i> Appliquer tout';
        }
    }
}