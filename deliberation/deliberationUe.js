document.addEventListener('DOMContentLoaded', function () {
    const filieresSelect = document.getElementById('filiterFiliere');
    const niveauxFormationSelect = document.getElementById('filterNiveau');
    const optionsSelect = document.getElementById('filterOption');
    const semestersSelect = document.getElementById('filterSemester');
    const ueTableBody = document.getElementById('ueBoutonContainer');
    // const id="ueBoutonContainer";
    const cycleSelect = document.getElementById('filterCycle');

    // Stocker les données originales pour éviter de recharger
    let allFilieres = [];
    let allOptions = [];

    // Initialisation des sélecteurs avec une option par défaut
    function initializeSelect(selectElement, placeholder = '') {
        // Déterminer le placeholder en fonction de l'ID si non fourni
        if (!placeholder) {
            placeholder = selectElement.id.includes('Cycle') ? 'Sélectionner un Cycle' :
                          selectElement.id.includes('Niveau') ? 'Sélectionner un Niveau' :
                          selectElement.id.includes('Option') ? 'Sélectionner une Option' :
                          selectElement.id.includes('Semester') ? 'Sélectionner un Semestre' :
                          selectElement.id.includes('Filiere') ? 'Sélectionner une Filière' :
                          'Sélectionner';
        }

        // Vider le sélecteur et ajouter l'option par défaut
        selectElement.innerHTML = '';
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = placeholder;
        defaultOption.disabled = true;
        defaultOption.selected = true;
        selectElement.appendChild(defaultOption);
    }

    // Fonction pour charger les filières (sans appeler loadUEs)
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

    // Fonction pour charger les options (sans appeler loadUEs)
    function loadOptions(filiereId = null) {
        if (filiereId) {
            return getOptions(filiereId)
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
            // Si pas de filière, charger toutes les options
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

    // Fonction pour charger les niveaux (sans appeler loadUEs)
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

    // Initialiser tous les sélecteurs au chargement
    function initializeAllSelects() {
        // initializeSelect(cycleSelect, 'Sélectionner un Cycle');
        initializeSelect(niveauxFormationSelect, 'Sélectionner un Niveau');
        initializeSelect(filieresSelect, 'Sélectionner une Filière');
        initializeSelect(optionsSelect, 'Sélectionner une Option');
        // initializeSelect(semestersSelect, 'Sélectionner un Semestre');
        
        // Ajouter des options pour le semestre (statique)
        // const semesters = [
        //     { id: 1, name: 'Semestre 1' },
        //     { id: 2, name: 'Semestre 2' },
        //     { id: 3, name: 'Semestre 3' },
        //     { id: 4, name: 'Semestre 4' },
        //     { id: 5, name: 'Semestre 5' },
        //     { id: 6, name: 'Semestre 6' }
        // ];
        
        // semesters.forEach(semester => {
        //     const option = document.createElement('option');
        //     option.value = semester.id;
        //     option.textContent = semester.name;
        //     semestersSelect.appendChild(option);
        // });
    }

    // Chargement initial
    function initializePage() {
        initializeAllSelects();
        
        // Charger les données initiales
        Promise.all([
            loadFilieres(),
            loadOptions()
        ]).then(() => {
            // Ajouter les écouteurs d'événements après le chargement
            setupEventListeners();
        }).catch(error => {
            console.error('Erreur lors de l\'initialisation:', error);
        });
    }

    // Configuration des écouteurs d'événements
    function setupEventListeners() {
        // Écouteur pour le cycle
        cycleSelect.addEventListener('change', function() {
            const selectedCycleId = this.value;
            loadNiveaux(selectedCycleId);
            // loadUEs();
        });

        // Écouteur pour la filière
        filieresSelect.addEventListener('change', function() {
            const selectedFiliereId = this.value;
            if (selectedFiliereId) {
                loadOptions(selectedFiliereId);
            } else {
                // Si aucune filière n'est sélectionnée, charger toutes les options
                loadOptions();
            }
            // loadUEs();
        });

        // Écouteurs pour les autres filtres
        [semestersSelect].forEach(select => {
            select.addEventListener('change', loadUEs);
        });
    }

    // fourchette repêchage des UEs
    const instervalleNote = [{ min: 7, max: 7.5 },{ min: 7.5, max: 8 }, { min: 8, max: 8.5 }, { min: 8.5, max: 9 }, { min: 9, max: 9.5 }, { min: 9.5, max: 10 }];
    const intervalleNotesContainer = document.getElementById('intervalleNotesContainer');
    instervalleNote.forEach(intervalle => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-secondary m-1 text-sm text-dark';
        button.textContent = `${intervalle.min} - ${intervalle.max}`;
        button.addEventListener('click', () => {
            // Gérer le clic sur le bouton de l'intervalle de notes
            const noteJury = intervalle.min != 0 ? 10 - intervalle.min : 3;
            Swal.fire({
                title: 'Confirmation',
                text: ` Voulez-vous vraiment repêcher toutes étudiants ayant au moins ${intervalle.min} ?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Oui',
                cancelButtonText: 'Non'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Logique pour appliquer la fourchette de notes
                    Swal.fire('Succès', `La fourchette de notes ${intervalle.min} - ${intervalle.max} a été appliquée avec une note jury de ${noteJury}.`, 'success');
                }
            });
            // Vous pouvez ajouter une logique pour filtrer les UEs en fonction de cet intervalle
        });
        intervalleNotesContainer.appendChild(button);
    });

    // Fonction de chargement des UEs
    function loadUEs() {
        // Récupérer les valeurs actuelles des filtres
        if(cycleSelect.value==='' && niveauxFormationSelect.value===''&& filieresSelect.value===''&& optionsSelect.value===''&& semestersSelect.value===''){
            ueTableBody.innerHTML = `
                <tr>
                    <td colspan="3" style="text-align: center; padding: 20px; color: #666;">
                        Sélectionnez des filtres pour afficher les UEs
                    </td>
                </tr>
            `;
            return;
        }
        const filters = {
            idcycle: cycleSelect.value || null,
            idNiveauFormation: niveauxFormationSelect.value || null,
            idFiliere: filieresSelect.value || null,
            idOption: optionsSelect.value || null,
            idSemestre: semestersSelect.value || null
        };
        
        // Nettoyer les filtres (supprimer les valeurs vides)
        Object.keys(filters).forEach(key => {
            if (filters[key] === '' || filters[key] === null) {
                delete filters[key];
            }
        });
        
        // Si aucun filtre n'est actif, vider le tableau
        if (Object.keys(filters).length === 0) {
            ueTableBody.innerHTML = `
                <tr>
                    <td colspan="3" style="text-align: center; padding: 20px; color: #666;">
                        Sélectionnez des filtres pour afficher les UEs
                    </td>
                </tr>
            `;
            return;
        }
        
        // Afficher un indicateur de chargement
        ueTableBody.innerHTML = `
            <tr>
                <td colspan="3" style="text-align: center; padding: 20px;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-2">Chargement des UEs...</p>
                </td>
            </tr>
        `;
        
        // Appeler l'API pour récupérer les UEs
        getMaquetteUEs(filters)
            .then(ues => {
                ueTableBody.innerHTML = '';
                
                if (ues && ues.length > 0) {
                    ues.forEach(ue => {
                        // const row = document.createElement('tr');
                        // row.innerHTML = `
                        //     <td class="text-center">${ue.nomUE || ''}</td>
                        // `;
                        // ueTableBody.appendChild(row);
                        
                        // // Ajouter l'événement click si nécessaire
                        // if (ue.idUE) {
                        //     row.addEventListener('click', () => {
                        //         loadECs(ue.idUE);
                        //     });
                        //     row.style.cursor = 'pointer';
                        //     row.title = 'Cliquez pour voir les ECs';
                        //     row.classList.add('ue-row', 'hover','btn-outline-secondary');
                        // }
                        const ueBoutonContainer = document.getElementById('ueBoutonContainer');
                        ueBoutonContainer.className = 'd-flex flex-wrap justify-content-start';
                        const ueButton = document.createElement('button');
                        ueButton.type = 'button';
                        ueButton.className = 'btn btn-sm btn-outline-primary m-1 ue-button border-secondary border-2';
                        ueButton.innerHTML = ue.code+' : <span class="text-dark">'+ue.nomUE+'</span>';
                        ueButton.addEventListener('click', () => {
                            loadECs(ue.idUE);
                        });
                        ueBoutonContainer.appendChild(ueButton);
                    });
                } else {
                    // Afficher un message si aucun résultat
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td colspan="3" style="text-align: center; padding: 20px;">
                            Aucun résultat trouvé avec les filtres sélectionnés
                        </td>
                    `;
                    ueTableBody.appendChild(row);
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des UEs:', error);
                ueTableBody.innerHTML = `
                    <tr>
                        <td colspan="3" style="text-align: center; color: red; padding: 20px;">
                            Erreur lors du chargement des données
                        </td>
                    </tr>
                `;
            });
    }

    // Fonction de chargement des ECs
    function loadECs(ueId) {

        getStatUE(ueId)
            .then(stats => {
                const maxNoteHeader = document.getElementById('meilleureNoteUE');
                const minNoteHeader = document.getElementById('moinsBonneNoteUE');
                const moyenneHeader = document.getElementById('moyenneUE');
                const totalEtudiantsHeader = document.getElementById('nombreEtudiants');
                const valideHeader = document.getElementById('valideUE');
                const nonValideHeader = document.getElementById('nonValideUE');
                const presentUEHeader = document.getElementById('presentUE');
                const absentUEHeader = document.getElementById('absentUE');
                if (stats && stats.length > 0) {
                    const stat = stats[0];

                    absentUEHeader.textContent = stat.nombreNonComposes || 0;
                    presentUEHeader.textContent = stat.nombreComposes || 0;
                    maxNoteHeader.textContent = stat.meilleureNote || 0;
                    minNoteHeader.textContent = stat.moinsBonneNote || 0;
                    moyenneHeader.textContent = stat.moyenneGenerale || 0;
                    totalEtudiantsHeader.textContent = stat.totalEtudiants || 0;
                    valideHeader.textContent = ((stat.nombreReussites / stat.totalEtudiants) * 100)+'%' || '0%';
                    nonValideHeader.textContent = ((stat.nombreEchecs / stat.totalEtudiants) * 100) +'%' || '0%';
                }else{
                    maxNoteHeader.textContent = 0;
                    minNoteHeader.textContent = 0;
                    moyenneHeader.textContent = 0;
                    totalEtudiantsHeader.textContent = 0;
                    valideHeader.textContent = 0;
                    nonValideHeader.textContent = 0;
                    presentUEHeader.textContent = 0;
                    absentUEHeader.textContent = 0;
                }
            });

        getEtudiantByUE(ueId)
            .then(etudiants => {
                ecTableBody.innerHTML = '';
                if (etudiants && etudiants.length > 0) {

                    etudiants.forEach(etudiant => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${etudiant.matricule || ''}</td>
                            <td>${etudiant.prenom || ''} ${etudiant.nom || ''}</td>
                            <td>${etudiant.nom || ''}</td>
                            <td>${etudiant.note || '0'}</td>
                        `;
                        ecTableBody.appendChild(row);
                    });
                    
                } else {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td colspan="2" style="text-align: center; padding: 10px;">
                            Aucun étudiant trouvé pour cette UE
                        </td>
                    `;
                    ecTableBody.appendChild(row);
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des ECs:', error);
                ecTableBody.innerHTML = `
                    <tr>
                        <td colspan="2" style="text-align: center; color: red; padding: 10px;">
                            Erreur lors du chargement des ECs
                        </td>
                    </tr>
                `;
            });
    }

    function getStatUE(ueId) {
        // Implémentation de la récupération des statistiques pour une UE
        return fetch(`deliberationUeController.php?action=getStatUE&ueId=${ueId}`)
            .then(response => response.json());
    }
    // Initialiser la page
    initializePage();
});

// Fonctions API (inchangées)
function getFilieres() {
    return fetch('deliberationUeController.php?action=listFilieres')
        .then(response => response.json());
}

function getNiveauxFormation(idCycleFormation = 0) {
    return fetch(`deliberationUeController.php?action=getNiveauFormationByCycle&idCycleFormation=${idCycleFormation}`)
        .then(response => response.json());
}

function getOptions(idFiliere = 0) {
    return fetch(`deliberationUeController.php?action=listOptionsByFiliere&idFiliere=${idFiliere}`)
        .then(response => response.json());
}

function getMaquetteUEs(filters) {
    const params = new URLSearchParams(filters);
    return fetch(`deliberationUeController.php?action=getMaquetteUEs&${params.toString()}`)
        .then(response => response.json());
}

function getEtudiantByUE(ueId) {
    return fetch(`deliberationUeController.php?action=getEtudiantByUE&idUE=${ueId}`)
        .then(response => response.json());
}