// Remplacer la partie complète du fichier deliberationUe.js par ce code corrigé :

document.addEventListener('DOMContentLoaded', function () {
    initializePage();
});

// Variables globales
const filieresSelect = document.getElementById('filiterFiliere');
const niveauxFormationSelect = document.getElementById('filterNiveau');
const optionsSelect = document.getElementById('filterOption');
const semestersSelect = document.getElementById('filterSemester');
const cycleSelect = document.getElementById('filterCycle');
const sessionSelect = document.getElementById('filterSession');
let selectedUEId = null;
let currentEligibles = [];
let currentIntervalle = {};
let allFilieres = [];
let allOptions = [];
// sessionSelect.addEventListener('change', () => {
//     if (!filieresSelect.value) {
//         showAlert('Filière');
//         sessionSelect.value = '';
//         return;
//     }

//     if (!niveauxFormationSelect.value) {
//         showAlert('Niveau de formation');
//         sessionSelect.value = '';
//         return;
//     }

//     if (!cycleSelect.value) {
//         showAlert('Cycle');
//         sessionSelect.value = '';
//         return;
//     }

//     if (!optionsSelect.value) {
//         showAlert('Option');
//         sessionSelect.value = '';
//         return;
//     }

//     if (!semestersSelect.value) {
//         showAlert('Semestre');
//         sessionSelect.value = '';
//         return;
//     }
//     if (!selectedUEId) {
//         showAlert('UE');
//         sessionSelect.value = '';
//         return;
//     }
//     loadECs(selectedUEId)
// })
function showAlert(champ) {
    Swal.fire({
        icon: 'warning',
        title: 'Champ manquant',
        text: `Veuillez sélectionner : ${champ}`,
        confirmButtonText: 'D’accord',
        confirmButtonColor: '#3085d6'
    });
}

// Intervalles de notes
const intervalleNote = [
    { min: 0, max: 7, nbEtudiants: 0 },
    { min: 7, max: 7.5, nbEtudiants: 0 },
    { min: 7.5, max: 8, nbEtudiants: 0 },
    { min: 8, max: 8.5, nbEtudiants: 0 },
    { min: 8.5, max: 9, nbEtudiants: 0 },
    { min: 9, max: 9.5, nbEtudiants: 0 },
    { min: 9.5, max: 10, nbEtudiants: 0 },
    { min: 10, max: 20, nbEtudiants: 0 }
];

// Initialisation des sélecteurs
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

function getEtudiantByUE(ueId) {
    const session_id = document.getElementById('filterSession').value;
    return fetch(`deliberationUeController.php?action=getEtudiantByUE&idUE=${ueId}&session_id=1`)
        .then(response => response.json());
}

function getStatUE(ueId) {
    return fetch(`deliberationUeController.php?action=getStatUE&ueId=${ueId}`)
        .then(response => response.json());
}

// Chargement des filières
function loadFilieres() {
    return getFilieres()
        .then(filieres => {
            allFilieres = filieres;
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

// Chargement des options
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

// Chargement des niveaux
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

// Initialisation de la page
function initializePage() {
    // Initialiser tous les sélecteurs
    // initializeSelect(cycleSelect, 'Sélectionner un Cycle');
    initializeSelect(niveauxFormationSelect, 'Sélectionner un Niveau');
    initializeSelect(filieresSelect, 'Sélectionner une Filière');
    initializeSelect(optionsSelect, 'Sélectionner une Option');
    // initializeSelect(semestersSelect, 'Sélectionner un Semestre');

    // Charger les données initiales
    Promise.all([
        loadFilieres(),
        // loadOptions()
    ]).then(() => {
        setupEventListeners();
        initialiserIntervallesNotes();
    }).catch(error => {
        console.error('Erreur lors de l\'initialisation:', error);
    });
}

// Configuration des écouteurs d'événements
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

    // Écouteurs pour les autres filtres
    [semestersSelect, niveauxFormationSelect, optionsSelect].forEach(select => {
        select.addEventListener('change', loadUEs);
    });
}

// Initialisation des intervalles de notes
function initialiserIntervallesNotes() {
    const intervalleNotesContainer = document.getElementById('intervalleNotesContainer');
    if (!intervalleNotesContainer) return;

    intervalleNotesContainer.innerHTML = '';

    intervalleNote.forEach(intervalle => {
        const intervalleNoteSubContainer = document.createElement('div');
        intervalleNoteSubContainer.className = 'd-flex flex-wrap align-items-center';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-primary btn-sm w-100 text-dark intervalle-button';
        button.innerHTML = `
            <span class="fw-bold small">[${intervalle.min} ; ${intervalle.max}[</span>
            <br>
            <span class="badge bg-light-info text-dark mt-1" id="badgeIntervalle${intervalle.min}">0</span>
        `;

        // Remplacer la fonction button.addEventListener('click') dans initialiserIntervallesNotes :

        button.addEventListener('click', () => {
            if (!selectedUEId) {
                Swal.fire('Information', 'Veuillez d\'abord sélectionner une UE', 'info');
                return;
            }
            const ecTableContainer = document.getElementById('ecTableContainer')
            ecTableContainer.classList.add('d-none')
            // Récupérer les étudiants de l'UE
            getEtudiantByUE(selectedUEId).then(etudiants => {
                currentIntervalle = intervalle;

                // Calculer les moyennes et filtrer les éligibles
                // IMPORTANT : repêcher tous les étudiants AU-DESSUS de l'intervalle.min
                const eligibles = etudiants.map(e => {
                    const totalPoints = e.ec.reduce((acc, n) =>
                        acc + (parseFloat(n.note) * parseFloat(n.coef)), 0);
                    const totalCoefs = e.ec.reduce((acc, n) =>
                        acc + parseFloat(n.coef), 0);
                    const moyenne = totalCoefs > 0 ? (totalPoints / totalCoefs) : 0;

                    return {
                        matricule: e.matricule,
                        prenom: e.prenom,
                        nom: e.nom,
                        moyenne: moyenne,
                        pointsJury: (10 - moyenne).toFixed(4),
                        // Ajout des EC pour la simulation
                        ec: e.ec
                    };
                }).filter(e => e.moyenne >= intervalle.min && e.moyenne < 10); // < 10 pour exclure ceux déjà à 10+

                // Gestion des cas particuliers
                if (intervalle.min >= 10) {
                    Swal.fire('Information', 'Ces étudiants ont déjà validé l\'UE.', 'info');
                    return;
                }

                if (eligibles.length === 0) {
                    Swal.fire('Information', `Aucun étudiant avec une moyenne ≥ ${intervalle.min} et < 10.`, 'info');
                    return;
                }
                // Mettre en surbrillance le bouton sélectionné
                document.querySelectorAll('.intervalle-button').forEach(btn => {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-primary');
                });
                button.classList.remove('btn-outline-primary');
                button.classList.add('btn-primary');

                currentEligibles = eligibles;
                afficherConfigurationRepêchage(eligibles, intervalle);
            }).catch(error => {
                console.error('Erreur lors du chargement des étudiants:', error);
                Swal.fire('Erreur', 'Impossible de charger les étudiants', 'error');
            });
        });

        intervalleNoteSubContainer.appendChild(button);
        intervalleNotesContainer.appendChild(intervalleNoteSubContainer);
    });
}
// Chargement des UEs
function loadUEs() {
    const ueBoutonContainer = document.getElementById('ueBoutonContainer');
    if (!ueBoutonContainer) return;

    // Vérification des filtres
    if (!cycleSelect.value || !niveauxFormationSelect.value || !filieresSelect.value ||
        !optionsSelect.value || !semestersSelect.value) {
        ueBoutonContainer.innerHTML = `
            <div class="alert alert-info m-3">
                <i class="fas fa-info-circle me-2"></i>
                Sélectionnez tous les filtres pour afficher les UEs
            </div>
        `;
        return;
    }

    // Préparation des filtres
    const filters = {
        idcycle: cycleSelect.value,
        idNiveauFormation: niveauxFormationSelect.value,
        idFiliere: filieresSelect.value,
        idOption: optionsSelect.value,
        idSemestre: semestersSelect.value
    };

    // Affichage du chargement
    ueBoutonContainer.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2 text-muted">Chargement des Unités d'Enseignement...</p>
        </div>
    `;

    // Appel API
    getMaquetteUEs(filters)
        .then(ues => {
            ueBoutonContainer.innerHTML = '';
            ueBoutonContainer.className = 'd-flex flex-wrap gap-2';
            const ndRepeche = document.getElementById('ndRepeche');
            let nbUERepechees = 0;

            if (ues && ues.length > 0) {
                ues.forEach(ue => {
                    const ueButton = document.createElement('button');
                    ueButton.type = 'button';
                    ueButton.className = 'btn btn-outline-primary ue-button';
                    ueButton.innerHTML = `
                        <div class="text-start">
                            <div class="fw-bold small d-flex justify-content-between">
                                ${ue.code} 
                                <span class="badge">${ue.repechage ? '<i class="fas fa-check text-success"></i>' : ''}</span>
                            </div>
                            <div class="small text-muted">
                                ${ue.nomUE.substring(0, 25)}${ue.nomUE.length > 25 ? '...' : ''}
                            </div>
                        </div>
                    `;

                    if (ue.repechage) {
                        nbUERepechees++;
                    }
                    ueButton.addEventListener('click', () => {
                        selectedUEId = ue.idUE;
                        
                        // Activer le bouton Voir délibération si l'UE a un repêchage
                        const btnVoirDeliberation = document.getElementById('btnVoirDeliberation');
                        if (btnVoirDeliberation) {
                            if (ue.repechage) {
                                btnVoirDeliberation.classList.remove('d-none');
                                btnVoirDeliberation.disabled = false;
                                // Stocker l'ID de l'UE pour le bouton
                                btnVoirDeliberation.dataset.ueId = ue.idUE;
                            } else {
                                btnVoirDeliberation.classList.add('d-none');
                                btnVoirDeliberation.disabled = true;
                            }
                        }

                        // Mettre en surbrillance le bouton sélectionné
                        document.querySelectorAll('.ue-button').forEach(btn => {
                            btn.classList.remove('btn-primary');
                            btn.classList.add('btn-outline-primary');
                        });
                        ueButton.classList.remove('btn-outline-primary');
                        ueButton.classList.add('btn-primary');

                        // Vérifier la complétude des évaluations
                        verifierEvaluationsUE(selectedUEId).then(stats => {
                            console.log('Statistiques de complétude:', stats);

                            const totalEtudiants = stats.total_etudiants || 0;
                            const etudiantsComplets = stats.etudiants_complets || 0;
                            const pourcentageComplets = stats.pourcentage_complets || 0;
                            const etudiantsIncomplets = stats.etudiants_incomplets || 0;

                            if (pourcentageComplets < 100) {
                                
                                Swal.fire({
                                    title: `${pourcentageComplets == 0 ? `<p class="text-danger text-center mb-2">Veuillez saisir les notes.</p>` : 'Notes incomplètes'}`,
                                    html: `
                                        <div class="text-start">
                                        ${pourcentageComplets == 0 ? `` : `
                                            <p class="fw-bold mb-2">Résumé des manques :</p>
                                                <div class="mb-3">
                                                <span class="badge bg-success me-2">Ayant composés : ${etudiantsComplets}</span>
                                                <span class="badge bg-warning">N'ayant pas composés : ${etudiantsIncomplets}</span>
                                                </div>
                                            `}
                                            ${etudiantsIncomplets > 0 && pourcentageComplets != 0 ? `<p class="text-danger mb-2">Il y a ${etudiantsIncomplets} étudiant(s) avec des notes d'examen manquantes.</p>` : ''}
                                            ${etudiantsComplets > 0 && pourcentageComplets != 0 ? `<p class="text-success mb-2">Il y a ${etudiantsComplets} étudiant(s) avec des notes d'examen complètes.</p>` : ''}
                                            ${totalEtudiants > 0  && pourcentageComplets != 0 ? `<p class="mb-2">Sur un total de ${totalEtudiants} étudiant(s) inscrits à cette UE.</p>` : ''}
                                            ${pourcentageComplets > 0 ? `<p class="mb-2">Ce qui représente <strong>${pourcentageComplets.toFixed(2)}%</strong> des étudiants.</p>` : ''}
                                            <div class="alert alert-info mt-3 mb-0">
                                                <small>
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Seuls les étudiants avec toutes les notes d'examen sont considérés.
                                                </small>
                                            </div>
                                        </div>
                                    `,
                                    icon: 'warning',
                                    confirmButtonText: 'Voir la liste',
                                    showCancelButton: true,
                                    cancelButtonText: 'Fermer',
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        afficherListeEtudiantsIncomplets(stats.liste_etudiants_incomplets);
                                    } else {
                                        // loadECs(ue.idUE);
                                    }
                                });
                            } else if (pourcentageComplets === 100) {
                                loadECs(ue.idUE, stats.noteEtudiantsParEC);
                            }
                        }).catch(error => {
                            console.error('Erreur vérification évaluations:', error);
                            loadECs(ue.idUE);
                        });
                    });

                    ueBoutonContainer.appendChild(ueButton);
                });
            } else {
                ueBoutonContainer.innerHTML = `
                    <div class="alert alert-warning w-100 m-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Aucune UE trouvée avec les filtres sélectionnés
                    </div>
                `;
            }

            if (ndRepeche) {
                ndRepeche.textContent = `${nbUERepechees} / ${ues.length} faites`;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            ueBoutonContainer.innerHTML = `
                <div class="alert alert-danger m-3">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Erreur lors du chargement des UEs
                </div>
            `;
        });
}
// Écouteur pour le bouton Voir délibération
document.addEventListener('DOMContentLoaded', function() {
    const btnVoirDeliberation = document.getElementById('btnVoirDeliberation');
    
    if (btnVoirDeliberation) {
        btnVoirDeliberation.addEventListener('click', function(e) {
            e.preventDefault();
            
            const ueId = this.dataset.ueId;
            if (!ueId) {
                Swal.fire('Information', 'Veuillez sélectionner une UE avec un repêchage', 'info');
                return;
            }
            
            // Initialiser et afficher la modal
            const modalElement = document.getElementById('etudiantsUEModal');
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
                getDeliberationDeUE(ueId);
            } else {
                console.error('Modal etudiantsUEModal non trouvée');
            }
        });
    }
});
// Fonction de récupération des repêchages pour une UE donnée
// Fonction de récupération des repêchages pour une UE donnée
function getDeliberationDeUE(ueId) {
    const container = document.getElementById('deliberationResultsContainer');

    if (!container) {
        console.error('Container introuvable');
        return;
    }

    // Afficher le loader
    container.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2">Chargement...</p>
        </div>
    `;

    fetch(`deliberationUeController.php?action=getDeliberationDeUE&idUE=${encodeURIComponent(ueId)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                container.innerHTML = `<div class="alert alert-danger">${data.message || 'Erreur inconnue'}</div>`;
                return;
            }

            const ue = data.ue || {};
            const etudiants = data.etudiants || [];

            /* =====================================================
                STATISTIQUES
            ===================================================== */
            
            // Filtrer les étudiants par catégorie
            const repeches = etudiants.filter(e => e.est_repeche === true);
            const valides = etudiants.filter(e => !e.est_repeche && e.moyenne_ue >= 10);
            const nonRepechesNonValides = etudiants.filter(e => !e.est_repeche && e.moyenne_ue < 10);
            
            // Compter les validés (ceux qui ont la moyenne sans repêchage)
            const nbValides = valides.length;
            
            // Compter les non-repêchés (ceux qui n'ont pas la moyenne ET ne sont pas repêchés)
            const nbNonRepeches = nonRepechesNonValides.length;
            
            // Compter les repêchés
            const nbRepeches = repeches.length;
            
            const total = etudiants.length;

            const stats = {
                total_etudiants: total,
                etudiants_repêchés: nbRepeches,
                etudiants_valides: nbValides,
                etudiants_non_repêchés: nbNonRepeches,
                pourcentage_repêchage: total ? ((nbRepeches / total) * 100).toFixed(2) : 0,
                pourcentage_validation: total ? ((nbValides / total) * 100).toFixed(2) : 0
            };

            /* =====================================================
                GÉNÉRATION HTML PAR CATÉGORIE
            ===================================================== */

            // Générer HTML pour les repêchés
            let repechesHtml = '';
            let totalPointsJury = 0;
            
            repeches.forEach(e => {
                const moyenne = e.moyenne_ue !== null && e.moyenne_ue !== undefined ? e.moyenne_ue.toFixed(2) : 'N/A';
                const pointsJury = e.moyenne_ue !== null && e.moyenne_ue !== undefined ? (10 - e.moyenne_ue).toFixed(2) : 'N/A';
                
                if (e.moyenne_ue !== null && e.moyenne_ue !== undefined) {
                    totalPointsJury += 10 - e.moyenne_ue;
                }
                
                repechesHtml += `
                    <tr class="table-success">
                        <td>${escapeHtml(e.matricule || '')}</td>
                        <td>${escapeHtml(e.nom || '')}</td>
                        <td><span class="badge bg-success">Repêché</span></td>
                        <td class="text-center">${moyenne}</td>
                        <td class="text-center"><span class="badge bg-primary">10.00</span></td>
                        <!-- <td class="text-center"><span class="badge bg-success">+ ${pointsJury}</span></td> -->
                    </tr>
                `;
            });

            if (repechesHtml === '') {
                repechesHtml = '<tr><td colspan="6" class="text-center">Aucun étudiant repêché</td></tr>';
            }

            // Générer HTML pour les non-repêchés non validés
            let nonRepechesHtml = '';
            nonRepechesNonValides.forEach(e => {
                const moyenne = e.moyenne_ue !== null && e.moyenne_ue !== undefined ? e.moyenne_ue.toFixed(2) : 'N/A';
                
                nonRepechesHtml += `
                    <tr class="table-danger">
                        <td>${escapeHtml(e.matricule || '')}</td>
                        <td>${escapeHtml(e.nom || '')}</td>
                        <td><span class="badge bg-danger">Non validé</span></td>
                        <td class="text-center">${moyenne}</td>
                        <!-- <td class="text-center">-</td> -->
                        <!-- <td class="text-center"><span class="badge bg-secondary">0.00</span></td> -->
                    </tr>
                `;
            });

            if (nonRepechesHtml === '') {
                nonRepechesHtml = '<tr><td colspan="6" class="text-center">Aucun étudiant non repêché</td></tr>';
            }

            // Générer HTML pour les validés
            let validesHtml = '';
            valides.forEach(e => {
                const moyenne = e.moyenne_ue !== null && e.moyenne_ue !== undefined ? e.moyenne_ue.toFixed(2) : 'N/A';
                
                validesHtml += `
                    <tr class="table-success">
                        <td>${escapeHtml(e.matricule || '')}</td>
                        <td>${escapeHtml(e.nom || '')}</td>
                        <td><span class="badge bg-success">Validé</span></td>
                        <td class="text-center">${moyenne}</td>
                        <!-- <td class="text-center">-</td> -->
                        <!-- <td class="text-center"><span class="badge bg-secondary">0.00</span></td> -->
                    </tr>
                `;
            });

            if (validesHtml === '') {
                validesHtml = '<tr><td colspan="6" class="text-center">Aucun étudiant validé</td></tr>';
            }

            /* =====================================================
                RENDU FINAL AVEC BOOTSTRAP 5
            ===================================================== */
            const etudiantsUEModalLabel = document.getElementById('etudiantsUEModalLabel');
            if (etudiantsUEModalLabel) {
                etudiantsUEModalLabel.textContent = `Délibération - ${ue.code || ''} - ${ue.nomUE || ''}`;
            }
            container.innerHTML = `
                <div class="card border-info">
                    <div class="card-body">
                        <!-- Statistiques -->
                        <div class="row mb-2">
                            <div class="col-md-2 col-6 mb-2">
                                <div class="border rounded p-3 text-center">
                                    <small class="text-muted">Total étudiants</small>
                                    <h3 class="mb-0">${stats.total_etudiants}</h3>
                                </div>
                            </div>
                            
                            <div class="col-md-2 col-6 mb-2">
                                <div class="border rounded p-3 text-center">
                                    <small class="text-muted">Repêchés</small>
                                    <h3 class="text-warning mb-0">${nbRepeches}</h3>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 mb-2">
                                <div class="border rounded p-3 text-center">
                                    <small class="text-muted">Non repêchés</small>
                                    <h3 class="text-danger mb-0">${nbNonRepeches}</h3>
                                </div>
                            </div>
                           
                            <div class="col-md-2 col-6 mb-2">
                                <div class="border rounded p-3 text-center">
                                    <small class="text-muted">Validés avant repêchage</small>
                                    <h3 class="text-success mb-0">${nbValides}</h3>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 mb-2">
                                <div class="border rounded p-3 text-center">
                                    <small class="text-muted">Total validés</small>
                                    <h3 class="text-info mb-0">${nbValides + nbRepeches}</h3>
                                </div>
                            </div>
                             <div class="col-md-2 col-6 mb-2">
                                <div class="border rounded p-3 text-center">
                                    <small class="text-muted">Barre de repêchage</small>
                                    <h3 class="text-danger mb-0">${ue.barre !== undefined ? parseFloat(ue.barre).toFixed(2) : 'N/A'}</h3>
                                </div>
                            </div>
                        </div>

                        <!-- Onglets -->
                        <ul class="nav nav-tabs" id="deliberationTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="repeches-tab" data-bs-toggle="tab" data-bs-target="#repeches" type="button" role="tab">
                                    Repêchés <span class="badge bg-warning ms-1">${nbRepeches}</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="nonrepeches-tab" data-bs-toggle="tab" data-bs-target="#nonrepeches" type="button" role="tab">
                                    Non repêchés <span class="badge bg-danger ms-1">${nbNonRepeches}</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="valides-tab" data-bs-toggle="tab" data-bs-target="#valides" type="button" role="tab">
                                    Validés avant repêchage <span class="badge bg-success ms-1">${nbValides}</span>
                                </button>
                            </li>
                        </ul>

                        <!-- Contenu des onglets -->
                        <div class="tab-content p-3 border border-top-0 rounded-bottom" id="deliberationTabContent">
                            <div class="tab-pane fade show active" id="repeches" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Matricule</th>
                                                <th>Nom</th>
                                                <th>Statut</th>
                                                <th class="text-center">Moyenne</th>
                                                <th class="text-center">Note retenue</th>
                                                <!-- <th class="text-center">Points</th> -->
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${repechesHtml}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="tab-pane fade" id="nonrepeches" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Matricule</th>
                                                <th>Nom</th>
                                                <th>Statut</th>
                                                <th class="text-center">Moyenne</th>
                                                <!-- <th class="text-center">Note jury</th> -->
                                                <!-- <th class="text-center">Points</th> -->
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${nonRepechesHtml}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="tab-pane fade" id="valides" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Matricule</th>
                                                <th>Nom</th>
                                                <th>Statut</th>
                                                <th class="text-center">Moyenne</th>
                                                <!-- <th class="text-center">Note jury</th> -->
                                                <!-- <th class="text-center">Points</th> -->
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${validesHtml}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        })
        .catch(err => {
            console.error('Erreur:', err);
            container.innerHTML = `
                <div class="alert alert-danger">
                    <strong>Erreur :</strong> ${escapeHtml(err.message)}
                </div>
            `;
        });
}

/**
 * Fonction utilitaire pour échapper les caractères HTML
 * (prévient les injections XSS)
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Nouvelle fonction pour afficher la liste des étudiants incomplets
function afficherListeEtudiantsIncomplets(etudiantsIncomplets) {
    if (!etudiantsIncomplets || etudiantsIncomplets.length === 0) {
        Swal.fire('Information', 'Aucune évolution n\'a été enregistrée', 'info');
        return;
    }

    let html = `
        <div class="table-responsive" style="max-height: 400px;">
            <table class="table table-sm table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Matricule</th>
                        <th>Nom</th>
                        <th>EC manquants</th>
                        <th>Détails</th>
                    </tr>
                </thead>
                <tbody>
    `;

    etudiantsIncomplets.forEach(etudiant => {
        const anomalies = etudiant.anomalies || etudiant.missing_evaluations || [];
        const detailsAnomalies = anomalies
            .map(a => a.ec_nom || a.ec_name)
            .filter(Boolean)
            .join(', ');

        html += `
            <tr>
                <td><small>${etudiant.matricule}</small></td>
                <td>${etudiant.nom}</td>
                
                <td class="text-center">
                    <span class="badge bg-danger">${etudiant.ec_manquants || 0}</span>
                </td>
                <td>
                    <small class="text-muted" title="${detailsAnomalies}">
                        ${detailsAnomalies.substring(0, 30)}${detailsAnomalies.length > 30 ? '...' : ''}
                    </small>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    Swal.fire({
        title: `Étudiants incomplets (${etudiantsIncomplets.length})`,
        html: html,
        icon: 'info',
        width: '800px',
        confirmButtonText: 'Fermer'
    });
}
// Configuration du repêchage
function afficherConfigurationRepêchage(eligibles, intervalle) {
    currentEligibles = eligibles;
    currentIntervalle = intervalle;

    const container = document.getElementById('resultats');
    if (!container) {
        console.error('Container #resultats non trouvé');
        return;
    }
    // Calculer la moyenne globale des éligibles
    const moyenneGlobale = eligibles.length > 0
        ? eligibles.reduce((sum, e) => sum + e.moyenne, 0) / eligibles.length
        : 0;

    const pointsTotaux = eligibles.reduce((sum, e) => sum + (10 - e.moyenne), 0);

    const html = `
        <div class="simulation-panel card">
            <div class="card-header bg-light-primary text-white">
                <h3 class="card-title h5 mb-0">
                    <i class="fas fa-life-ring me-2"></i>
                    Repêchage à partir de ${intervalle.min}/20
                </h3>
                <small class="text-white-80">Étudiants avec moyenne ≥ ${intervalle.min} et < 10</small>
            </div>
            
            <div class="card-body">
                <!-- Résumé -->
                <div class="alert alert-primary mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${eligibles.length} étudiant(s)</strong> concerné(s)
                        </div>
                        <div>
                            <strong>Moyenne globale :</strong> ${moyenneGlobale.toFixed(2)}/20
                        </div>
                        <div>
                            <strong>Points à distribuer :</strong> ${pointsTotaux.toFixed(2)}
                        </div>
                    </div>
                </div>

                <!-- Liste des étudiants concernés -->
                <div class="mb-4">
                    <h6 class="fw-bold text-muted mb-3">Étudiants concernés :</h6>
                    <div class="table-responsive" style="max-height: 250px;">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Étudiant</th>
                                    <th class="text-center">Moyenne actuelle</th>
                                    <th class="text-center">ÉC</th>
                                    <th class="text-center">Points nécessaires</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${eligibles.map(e => `
                                    <tr>
                                        <td class="small">${e.prenom} ${e.nom}</td>
                                        <td class="text-center">
                                            <span class="badge ${e.moyenne >= 7 ? 'bg-warning' : 'bg-danger'}">
                                                ${e.moyenne.toFixed(2)}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary">${e.ec.length}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-dark">+${(10 - e.moyenne).toFixed(2)}</span>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>                
            </div>
        </div>
    `;

    container.innerHTML = html;

}
// Gestionnaire d'événement pour le bouton de simulation
document.getElementById('btnRunSimu').addEventListener('click', () => {
    if (!selectedUEId || !currentIntervalle.min) {
        Swal.fire('Information', 'Veuillez sélectionner une UE et un intervalle de simulation', 'info');
        return;
    }
    console.log('Lancement de la simulation pour l\'UE ID:', selectedUEId, 'avec intervalle:', currentIntervalle);
    lancerSimulation(currentIntervalle.min, selectedUEId, currentIntervalle);
});
// Lancement de la simulation
function lancerSimulation(minMoy, ueId, intervalle) {
    const btnAction = document.getElementById('btnRunSimu');
    if (!btnAction) return;
    const strategy = document.getElementById('strategySelect')?.value || 'neutral';
    const rounding = document.getElementById('roundingSelect')?.value || '0.01';
    const lockGe10 = document.getElementById('lockGe10')?.checked ? 'true' : 'false';

    btnAction.disabled = true;
    btnAction.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Simulation en cours...';

    // Préparer les données à envoyer au serveur
    const dataToSend = {
        idUE: ueId,
        minMoyenne: minMoy,
        strategy: strategy,
        rounding_step: rounding,
        lock_ge10: lockGe10,
        // Envoyer les étudiants éligibles pour traitement
        etudiantsEligibles: currentEligibles.map(e => ({
            matricule: e.matricule,
            ec: e.ec
        }))
    };

    // Utiliser POST pour envoyer les données
    fetch('deliberationUeController.php?action=simulerRepechage', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(dataToSend)
    }).then(res => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }).then(data => {
        btnAction.disabled = false;
        btnAction.innerHTML = '<i class="fas fa-play me-2"></i>';

        if (data.success) {
            // Ajouter les noms complets aux résultats
            const simulationsAvecNoms = data.simulations.map(sim => {
                const etudiant = currentEligibles.find(e => e.matricule === sim.matricule);
                return {
                    ...sim,
                    nom: etudiant ? `${etudiant.prenom} ${etudiant.nom}` : sim.matricule
                };
            });

            afficherResultatsSimulation(simulationsAvecNoms, intervalle);
        } else {
            Swal.fire('Erreur', data.message || 'Erreur lors de la simulation', 'error');
        }
    }).catch(err => {
        console.error('Erreur:', err);
        btnAction.disabled = false;
        btnAction.innerHTML = '<i class="fas fa-play me-2"></i> Lancer la simulation';
        Swal.fire('Erreur', 'Erreur de connexion au serveur', 'error');
    });
}

// Affichage des résultats de simulation
function afficherResultatsSimulation(simulations, intervalle) {
    const container = document.getElementById('resultats');
    if (container.classList.contains('d-none')) {
        container.classList.remove('d-none')
    }
    if (!container) return;
    const ecTableContainer = document.getElementById('ecTableContainer')
    ecTableContainer.classList.add('d-none')

    // Calcul des statistiques de simulation
    const nbEtudiants = simulations.length;
    let totalPointsDistribues = 0;
    console.log(simulations)
    simulations.forEach(s => {
        s.details_ec.forEach(ec => {
            const diff = parseFloat(ec.note_affichage) - parseFloat(ec.note_initial);
            if (diff > 0) totalPointsDistribues += (diff * parseFloat(ec.coef));
        });
    });

    // Construction du HTML
    let html = `
        <div class="card border-success">
            <div class="card-header bg-success bg-opacity-10 border-success d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title text-success mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        Résultats de la simulation
                    </h4>
                    <small class="text-muted">${nbEtudiants} étudiant(s) repêché(s)</small>
                </div>
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-primary" onclick="revenirALaConfiguration()">
                        <i class="fas fa-edit me-1"></i> Modifier
                    </button>
                    <button class="btn btn-sm btn-success" id="btnAppliquerNotes">
                        <i class="fas fa-save me-1"></i> Appliquer
                    </button>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Statistiques rapides -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border-primary border">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Points distribués</h6>
                                <h4 class="card-title text-primary">+${totalPointsDistribues.toFixed(2)}</h4>
                                <p class="card-text small">Points jury nécessaires pour atteindre 10/20</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-success border">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Impact sur la promotion</h6>
                                <h4 class="card-title text-success">+${nbEtudiants} étudiant(s)</h4>
                                <p class="card-text small">Étudiants potentiellement repêchés</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tableau détaillé -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Étudiant</th>
                                <th>Élément Constitutif</th>
                                <th class="text-center">Note initiale</th>
                                <th class="text-center">Poids du jury</th>
                                <th class="text-center">Note ajustée</th>
                                <th class="text-center">Nouvelle moyenne</th>
                            </tr>
                        </thead>
                        <tbody>`;

    simulations.forEach((simu) => {
        const nbEc = simu.details_ec.length;

        simu.details_ec.forEach((ec, ecIndex) => {
            const hasIncrease = parseFloat(ec.note_affichage) > parseFloat(ec.note_initial);
            const pointsJury = ec.note_affichage - parseFloat(ec.note_initial).toFixed(2)
            html += `
                <tr class="${ecIndex === 0 ? 'border-top' : ''}">
                    ${ecIndex === 0 ? `
                        <td rowspan="${nbEc}" class="align-middle">
                            <div class="fw-bold">${simu.nom}</div>
                        </td>
                    ` : ''}
                    <td>
                        ${ec.name}
                        <span class="badge bg-light text-dark ms-2">coef ${ec.coef}</span>
                    </td>
                    <td class="text-center">${parseFloat(ec.note_initial).toFixed(2)}</td>
                    <td class="text-center"><span class="fw-bold ">
                    <i class="bi bi-plus ${pointsJury !== 0 ? 'text-success' : ''}">
                    ${pointsJury.toFixed(2)}
                    </i>
                    </span>
                    </td>
                    <td class="text-center">
                        <span class="fw-bold ${hasIncrease ? 'text-success' : ''}">
                            ${ec.note_affichage}
                            ${hasIncrease ? '<i class="fas fa-arrow-up text-success ms-1"></i>' : ''}
                        </span>
                    </td>
                    ${ecIndex === 0 ? `
                        <td rowspan="${nbEc}" class="text-center align-middle">
                            <span class="badge bg-primary fs-6">10.00</span>
                        </td>
                    ` : ''}
                </tr>`;
        });
    });

    html += `
                        </tbody>
                    </table>
                </div>
            </div>
        </div>`;

    container.innerHTML = html;

    // Gestionnaire pour le bouton d'application
    document.getElementById('btnAppliquerNotes').addEventListener('click', () => {
        sauvegarderNotesEnBase(simulations, intervalle)
    });
}
function sauvegarderNotesEnBase(simulations, intervalle) {
    // Récupérer l'idSemestre depuis les filtres
    const idSemestre = document.getElementById('filterSemester')?.value;

    Swal.fire({
        title: 'Confirmer l\'application du repêchage',
        html: `
            <div class="text-start">
                <p><strong>${simulations.length} étudiant(s)</strong> seront repêchés.</p>
                <p><strong>Seuil :</strong> À partir de ${intervalle.min}/20</p>
                <p><strong>Impact :</strong> Les notes seront définitivement modifiées.</p>
                <div class="alert alert-warning mt-2">
                    <small><i class="fas fa-exclamation-triangle me-1"></i> Cette action est irréversible.</small>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, appliquer le repêchage',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#d33',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const lockGe10 = document.getElementById('lockGe10')?.checked ? true : false;
            const strategy = document.getElementById('strategySelect')?.value || 'neutral';
            const rounding = document.getElementById('roundingSelect')?.value || '0.01';
            const data = {
                action: 'appliquerRepechage',
                idUE: selectedUEId,
                idSemestre: idSemestre,
                simulations: simulations,
                intervalle: intervalle,
                strategy: strategy,
                rounding_step: parseFloat(rounding),
                lock_ge10: lockGe10
            };

            return fetch('deliberationUeController.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Erreur lors de l\'application');
                    }
                    return data;
                });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Succès !',
                html: `
                    <div class="text-start">
                        <p>${result.value.message}</p>
                        <p><strong>Référence :</strong> Repêchage #${result.value.idRepechage}</p>
                        <div class="alert alert-success mt-2">
                            <small><i class="fas fa-check-circle me-1"></i> Les modifications ont été enregistrées.</small>
                        </div>
                    </div>
                `,
                icon: 'success',
                confirmButtonText: 'OK'
            }).then(() => {
                // Recharger les données pour voir les modifications
                if (selectedUEId) {
                    loadECs(selectedUEId);

                    // Mettre à jour les badges d'intervalles
                    intervalleNote.forEach(intervalle => {
                        const badge = document.getElementById(`badgeIntervalle${intervalle.min}`);
                        if (badge) {
                            badge.textContent = '0'; // Réinitialiser car les étudiants ont été repêchés
                        }
                    });
                }
            });
        }
    }).catch(error => {
        console.error('Erreur:', error);
        Swal.fire('Erreur', error.message || 'Erreur lors de l\'enregistrement', 'error');
    });
}
// Actualisation des statistiques UE
function actualiserStatsUE(stats) {
    const updateElement = (id, value, suffix = '') => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = typeof value === 'number' ? value.toFixed(2) + suffix : value;
        }
    };

    if (!stats) return;

    // Mise à jour des statistiques
    updateElement('meilleureNoteUE', stats.max);
    updateElement('moinsBonneNoteUE', stats.min);
    updateElement('moyenneUE', stats.moyenne);
    updateElement('nombreEtudiants', stats.effectif);

    // Calcul des pourcentages
    const tauxReussite = stats.effectif > 0 ? (stats.reussite / stats.effectif) * 100 : 0;
    const tauxEchec = stats.effectif > 0 ? (stats.echec / stats.effectif) * 100 : 0;

    updateElement('valideUE', tauxReussite, '%');
    updateElement('nonValideUE', tauxEchec, '%');
    updateElement('effectifReussite', stats.reussite);
    updateElement('effectifEchec', stats.echec);

    // Mise à jour des présences (si disponibles)
    if (stats.present !== undefined) {
        updateElement('presentUE', stats.present);
    }
    if (stats.absent !== undefined) {
        updateElement('absentUE', stats.absent);
    }
}

// Chargement des ECs
function loadECs(ueId, noteEtudiantsParEC = null) {
    // Réinitialiser les compteurs d'intervalles

    const ecTableContainer = document.getElementById('ecTableContainer')
    if (ecTableContainer.classList.contains('d-none')) {
        ecTableContainer.classList.remove('d-none')
    }
    // Variables de statistiques
    let minMoyenne = 20;
    let maxMoyenne = 0;
    let moyenneGenerale = 0;
    let effectif = 0;
    let nbReussite = 0;
    let nbEchec = 0;

    const promesseEtudiants = noteEtudiantsParEC 
    ? Promise.resolve(noteEtudiantsParEC) : 
    getEtudiantByUE(ueId);
    
    promesseEtudiants.then(etudiants => {
            const ecTableBody = document.getElementById('ecTableBody');
            if (!ecTableBody) return;
            // Dans loadECs, mettre à jour la logique des intervalles :

            // Mise à jour des intervalles - COMPTER TOUS les étudiants au-dessus du seuil
            intervalleNote.forEach(intervalle => {
                // Compter les étudiants dont la moyenne est >= intervalle.min
                let count = 0;
                etudiants.forEach(etudiant => {
                    const totalPoints = etudiant.ec.reduce((acc, n) => {
                        const note = parseFloat(n.note_affichage || n.note) || 0;
                        const coef = parseFloat(n.coef) || 1;
                        return acc + (note * coef);
                    }, 0);

                    const totalCoefs = etudiant.ec.reduce((acc, n) =>
                        acc + (parseFloat(n.coef) || 1), 0);

                    const moyenne = totalCoefs > 0 ? (totalPoints / totalCoefs) : 0;

                    if (moyenne >= intervalle.min && moyenne < intervalle.max) {
                        count++;
                    }
                });

                intervalle.nbEtudiants = count;
                const badge = document.getElementById(`badgeIntervalle${intervalle.min}`);
                if (badge) {
                    badge.textContent = count;
                    // Ajouter un tooltip pour plus d'informations
                    badge.title = `${count} étudiant(s) entre ${intervalle.min} et ${intervalle.max}`;
                }
            });
            ecTableBody.innerHTML = '';

            if (!etudiants || etudiants.length === 0) {
                ecTableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Aucun étudiant trouvé pour cette UE
                            </div>
                        </td>
                    </tr>`;

                // Mettre à jour les stats avec des valeurs nulles
                actualiserStatsUE({
                    min: 0,
                    max: 0,
                    moyenne: 0,
                    effectif: 0,
                    reussite: 0,
                    echec: 0
                });
                return;
            }

            // Traitement des étudiants
            etudiants.forEach(etudiant => {
                // Calcul de la moyenne
                const totalPoints = etudiant.ec.reduce((acc, n) => {
                    const note = parseFloat(n.note_affichage || n.note) || 0;
                    const coef = parseFloat(n.coef) || 1;
                    return acc + (note * coef);
                }, 0);

                const totalCoefs = etudiant.ec.reduce((acc, n) =>
                    acc + (parseFloat(n.coef) || 1), 0);

                const moyenne = totalCoefs > 0 ? (totalPoints / totalCoefs) : 0;

                // Mise à jour des statistiques
                effectif++;
                moyenneGenerale += moyenne;

                if (moyenne >= 9.99) {
                    nbReussite++;
                } else {
                    nbEchec++;
                }

                minMoyenne = Math.min(minMoyenne, moyenne);
                maxMoyenne = Math.max(maxMoyenne, moyenne);

                // Mise à jour des intervalles
                // Dans loadECs, mettre à jour la logique des intervalles :

                // Mise à jour des intervalles - COMPTER TOUS les étudiants au-dessus du seuil
                intervalleNote.forEach(intervalle => {
                    // Compter les étudiants dont la moyenne est >= intervalle.min
                    let count = 0;
                    etudiants.forEach(etudiant => {
                        const totalPoints = etudiant.ec.reduce((acc, n) => {
                            const note = parseFloat(n.note_affichage || n.note) || 0;
                            const coef = parseFloat(n.coef) || 1;
                            return acc + (note * coef);
                        }, 0);

                        const totalCoefs = etudiant.ec.reduce((acc, n) =>
                            acc + (parseFloat(n.coef) || 1), 0);

                        const moyenne = totalCoefs > 0 ? (totalPoints / totalCoefs) : 0;

                        if (moyenne >= intervalle.min && moyenne < intervalle.max) {
                            count++;
                        }
                    });

                    intervalle.nbEtudiants = count;
                    const badge = document.getElementById(`badgeIntervalle${intervalle.min}`);
                    if (badge) {
                        badge.textContent = count;
                        // Ajouter un tooltip pour plus d'informations
                        badge.title = `${count} étudiant(s) entre ${intervalle.min} et ${intervalle.max}`;
                    }
                });

                // Création de la ligne du tableau
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="fw-mono small">${etudiant.matricule || ''}</td>
                    <td>
                        <div class="fw-bold">${etudiant.prenom || ''} ${etudiant.nom || ''}</div>
                        <small class="text-muted">${etudiant.ec.length} EC(s)</small>
                    </td>
                    <td>
                        <span class="badge ${moyenne >= 9.99 ? 'bg-success' : 'bg-danger'} p-2">
                            ${moyenne.toFixed(2)}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-info btn-details">
                            <i class="fas fa-list me-1"></i> Détails
                        </button>
                    </td>
                `;

                // Gestionnaire pour le bouton de détails
                row.querySelector('.btn-details').addEventListener('click', () => {
                    afficherDetailsEtudiant(etudiant);
                });

                ecTableBody.appendChild(row);
            });

            // Mise à jour des statistiques dans l'interface
            const stats = {
                min: minMoyenne,
                max: maxMoyenne,
                moyenne: effectif > 0 ? (moyenneGenerale / effectif) : 0,
                effectif: effectif,
                reussite: nbReussite,
                echec: nbEchec
            };

            actualiserStatsUE(stats);

            // Récupérer les stats détaillées du serveur
            getStatUE(ueId).then(serverStats => {
                if (serverStats && serverStats.length > 0) {
                    const serverStat = serverStats[0];
                    const mergedStats = {
                        ...stats,
                        present: parseInt(serverStat.nombreComposes || 0),
                        absent: parseInt(serverStat.nombreNonComposes || 0)
                    };
                    actualiserStatsUE(mergedStats);
                }
            });
        })
        .catch(error => {
            console.error('Erreur:', error);
            const ecTableBody = document.getElementById('ecTableBody');
            if (ecTableBody) {
                ecTableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-danger py-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Erreur lors du chargement des données
                        </td>
                    </tr>`;
            }

            // Mettre à jour les stats avec des valeurs d'erreur
            actualiserStatsUE({
                min: 0,
                max: 0,
                moyenne: 0,
                effectif: 0,
                reussite: 0,
                echec: 0,
                present: 0,
                absent: 0
            });
        });
}

// Affichage des détails de l'étudiant
function afficherDetailsEtudiant(etudiant) {
    const notesHtml = etudiant.ec.map(ec => `
        <tr>
            <td>${ec.name || 'N/A'}</td>
            <td class="text-center">${ec.coef || '1'}</td>
            <td class="text-center">
                <span class="badge ${parseFloat(ec.note_affichage || ec.note) >= 10 ? 'bg-success' : 'bg-warning'}">
                    ${parseFloat(ec.note_affichage || ec.note).toFixed(2)}
                </span>
            </td>
        </tr>
    `).join('');

    Swal.fire({
        title: `Détails de ${etudiant.prenom} ${etudiant.nom}`,
        html: `
            <div class="text-start">
                <p><strong>Matricule :</strong> ${etudiant.matricule}</p>
                
                <div class="table-responsive mt-3">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Élément Constitutif</th>
                                <th class="text-center">Coefficient</th>
                                <th class="text-center">Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${notesHtml}
                        </tbody>
                    </table>
                </div>
            </div>
        `,
        width: '600px',
        showCloseButton: true,
        showConfirmButton: false
    });
}

// Fonction pour revenir à la configuration
function revenirALaConfiguration() {
    if (currentEligibles && currentIntervalle) {
        const resultats = document.getElementById('resultats')
        resultats.classList.remove('d-none')
        const ecTableContainer = document.getElementById('ecTableContainer')
        ecTableContainer.classList.add('d-none')
        afficherConfigurationRepêchage(currentEligibles, currentIntervalle);
    }
}
// Fonction pour vérifier si un repêchage existe déjà pour cette UE
function verifierRepêchageExistant(ueId) {
    return fetch(`deliberationUeController.php?action=verifierRepêchage&idUE=${ueId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.repechage) {
                return data.repechage;
            }
            return null;
        })
        .catch(() => null);
}


function afficherAlerteRepêchageExistant(repechage) {
    const container = document.getElementById('resultats');
    if (!container) return;

    const alertHtml = `
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <div class="d-flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle fa-2x"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <h5 class="alert-heading">Repêchage déjà effectué</h5>
                    <p>
                        Un repêchage a déjà été appliqué le ${repechage.dateCreation} 
                        avec un seuil à ${repechage.barre}/20.
                    </p>
                    <hr>
                    <p class="mb-0">
                        <small>
                            <strong>Référence :</strong> #${repechage.idRepechage} | 
                            <strong>Campagne :</strong> ${repechage.campagne}
                        </small>
                    </p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    // Insérer au début du container
    container.insertAdjacentHTML('afterbegin', alertHtml);
}
function verifierEvaluationsUE(ueId) {
    return fetch(`deliberationUeController.php?action=verifierEvaluationsUE&idUE=${ueId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                return data.stats;
            }
            throw new Error(data.message || 'Erreur de vérification');
        });
}

function getReasonLabel(reason) {
    const labels = {
        'aucune_note': 'Aucune note pour cet EC',
        'non_compose': 'Non composé',
        'pas_examen': 'Pas de note d\'examen (nature ≠ 2)'
    };
    return labels[reason] || reason;
}
// Exposer la fonction globalement
window.revenirALaConfiguration = revenirALaConfiguration;