document.addEventListener('DOMContentLoaded', function () {
    const filieresSelect = document.getElementById('filiterFiliere');
    const niveauxFormationSelect = document.getElementById('filterNiveau');
    const optionsSelect = document.getElementById('filterOption');
    const semestersSelect = document.getElementById('filterSemester');
    const ueTableBody = document.getElementById('ueBoutonContainer');
    // const id="ueBoutonContainer";
    const cycleSelect = document.getElementById('filterCycle');
    let selectedUEId = null;
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
        cycleSelect.addEventListener('change', function () {
            const selectedCycleId = this.value;
            loadNiveaux(selectedCycleId);
            // loadUEs();
        });

        // Écouteur pour la filière
        filieresSelect.addEventListener('change', function () {
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
        [semestersSelect, niveauxFormationSelect, filieresSelect, optionsSelect].forEach(select => {
            select.addEventListener('change', loadUEs);
        });
    }

    // fourchette repêchage des UEs
    const instervalleNote = [{ min: 0, max: 7, nbEtudiants: 0 }, { min: 7, max: 7.5, nbEtudiants: 0 }, { min: 7.5, max: 8, nbEtudiants: 0 }, { min: 8, max: 8.5, nbEtudiants: 0 }, { min: 8.5, max: 9, nbEtudiants: 0 }, { min: 9, max: 9.5, nbEtudiants: 0 }, { min: 9.5, max: 10, nbEtudiants: 0 },{ min: 10, max: 20, nbEtudiants: 0 }];
    const intervalleNotesContainer = document.getElementById('intervalleNotesContainer');
    instervalleNote.forEach(intervalle => {
        const intervalleNoteSubContainer = document.createElement('div');
        intervalleNoteSubContainer.id = 'intervalleNotesContainer'+intervalle.min;
        intervalleNoteSubContainer.className = 'd-flex flex-column align-items-center';
        const nbEtudiantsIntervalle = document.createElement('span');
        nbEtudiantsIntervalle.id = 'nbEtudiantsIntervalle'+intervalle.min;
        nbEtudiantsIntervalle.className = 'badge bg-light-info ms-2 text-dark';
        nbEtudiantsIntervalle.textContent = '0 Étudiants';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-secondary m-1 text-sm text-dark';
        button.textContent = `[${intervalle.min} ; ${intervalle.max}[`;
        button.addEventListener('click', () => {
    // 1. Récupération de la liste des étudiants concernés
    getEtudiantByUE(selectedUEId).then(etudiants => {
        const regroupement = {};
        etudiants.forEach(curr => {
            if (!regroupement[curr.matricule]) {
                regroupement[curr.matricule] = { 
                    nom: `${curr.prenom} ${curr.nomEtudiant}`, 
                    notes: [] 
                };
            }
            regroupement[curr.matricule].notes.push({
                valeur: parseFloat(curr.note) || 0,
                coef: parseFloat(curr.coef) || 1
            });
        });

        // Filtrer ceux qui sont dans l'intervalle [min ; 10[
        const eligibles = Object.values(regroupement).map(e => {
            const totalPoints = e.notes.reduce((acc, n) => acc + (n.valeur * n.coef), 0);
            const totalCoefs = e.notes.reduce((acc, n) => acc + n.coef, 0);
            const moyenne = totalCoefs > 0 ? (totalPoints / totalCoefs) : 0;
            return { ...e, moyenne: moyenne, pointsJury: (10 - moyenne).toFixed(2) };
        }).filter(e => e.moyenne >= intervalle.min && e.moyenne < 10);

        if (eligibles.length === 0) {
            Swal.fire('Info', 'Aucun étudiant ne se trouve dans cet intervalle.', 'info');
            return;
        }

        // 2. Construction de la liste pour l'affichage
        let listeHtml = `
            <div class="text-start mt-3" style="max-height: 200px; overflow-y: auto;">
                <table class="table table-sm">
                    <thead>
                        <tr><th>Étudiant</th><th>Moy.</th><th>Points Jury</th></tr>
                    </thead>
                    <tbody>
                        ${eligibles.map(e => `
                            <tr>
                                <td class="small">${e.nom}</td>
                                <td><span class="badge bg-secondary">${e.moyenne.toFixed(2)}</span></td>
                                <td><b class="text-danger">+${e.pointsJury}</b></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>`;

        // 3. Affichage de la confirmation
        Swal.fire({
            title: 'Confirmation du Repêchage',
            html: `Voulez-vous repêcher les <b>${eligibles.length} étudiants</b> suivants pour atteindre 10 ? ${listeHtml}`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Oui, repêcher',
            cancelButtonText: 'Annuler',
            width: '600px'
        }).then((result) => {
            if (result.isConfirmed) {
                loadECs(selectedUEId, intervalle.min);
                Swal.fire('Succès', 'Les notes ont été mises à jour pour les étudiants sélectionnés.', 'success');
            }
        });
    });
});
        intervalleNoteSubContainer.appendChild(button);
        intervalleNoteSubContainer.appendChild(nbEtudiantsIntervalle);
        intervalleNotesContainer.appendChild(intervalleNoteSubContainer);

    });

    // Fonction de chargement des UEs
    function loadUEs() {
        // Récupérer les valeurs actuelles des filtres
        if (cycleSelect.value == "" || niveauxFormationSelect.value == "" || filieresSelect.value == "" || optionsSelect.value == "" || semestersSelect.value == "") {
            ueTableBody.innerHTML = `
                <tr>
                    <td colspan="3" style="text-align: center; padding: 20px; color: #666;">
                        Sélectionnez des filtres pour afficher les UEs
                    </td>
                </tr>
            `;
            return;
        }
        console.log('Chargement des UEs avec les filtres :', {
            idcycle: cycleSelect.value,
            idNiveauFormation: niveauxFormationSelect.value,
            idFiliere: filieresSelect.value,
            idOption: optionsSelect.value,
            idSemestre: semestersSelect.value
        });
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
                       
                        const ueBoutonContainer = document.getElementById('ueBoutonContainer');
                        ueBoutonContainer.className = 'd-flex flex-wrap justify-content-start';
                        const ueButton = document.createElement('button');
                        ueButton.type = 'button';
                        ueButton.className = 'btn btn-sm btn-outline-primary m-1 ue-button border-secondary border-2';
                        ueButton.innerHTML = ue.code + ' : <span class="text-dark">' + ue.nomUE + '</span>';
                        ueButton.addEventListener('click', () => {
                            selectedUEId = ue.idUE;
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
    let nbReussite = 0;
    let nbEchec = 0;
    let minMoyenne = 0.0;
    let maxMoyenne = 0.0;
    let moyenneGenerale = 0;
    let effectif = 0;
    // Fonction de chargement des ECs
    function loadECs(ueId, minMoyenneForRepechage = null) {
    // 1. Réinitialisation des compteurs d'intervalles (UI)
    instervalleNote.forEach(intervalle => {
        intervalle.nbEtudiants = 0;
        const nbEtudiantsIntervalle = document.getElementById('nbEtudiantsIntervalle' + intervalle.min);
        if (nbEtudiantsIntervalle) {
            nbEtudiantsIntervalle.textContent = '0 Étudiants';
        }
    });

    // 2. Réinitialisation des variables globales de statistiques
    // Note: Assurez-vous que ces variables sont déclarées au début de votre script
    let minMoyenne = 0;
    let maxMoyenne = 0;
    let moyenneGenerale = 0;
    let effectif = 0;
    let nbReussite = 0;
    let nbEchec = 0;

    getEtudiantByUE(ueId)
        .then(etudiants => {
            ecTableBody.innerHTML = '';

            if (etudiants && etudiants.length > 0) {
                
                // --- LOGIQUE DE REPÊCHAGE ---
                if (minMoyenneForRepechage !== null) {
                    // On groupe d'abord par étudiant pour calculer leur moyenne actuelle
                    const tempGroups = {};
                    etudiants.forEach(curr => {
                        if (!tempGroups[curr.matricule]) tempGroups[curr.matricule] = [];
                        tempGroups[curr.matricule].push(curr);
                    });

                    // Pour chaque étudiant, si sa moyenne est dans la zone, on modifie ses notes
                    etudiants = etudiants.map(e => {
                        const sesNotes = tempGroups[e.matricule];
                        const totalPoints = sesNotes.reduce((acc, n) => acc + (parseFloat(n.note) || 0) * (parseFloat(n.coef) || 1), 0);
                        const totalCoefs = sesNotes.reduce((acc, n) => acc + (parseFloat(n.coef) || 1), 0);
                        const moyenneActuelle = totalCoefs > 0 ? (totalPoints / totalCoefs) : 0;

                        // Si l'étudiant est éligible au repêchage (entre seuil et 10)
                        if (moyenneActuelle >= minMoyenneForRepechage && moyenneActuelle < 10) {
                            return { ...e, note: 10 }; // On force la note individuelle à 10
                        }
                        return e;
                    });
                }
                console.log('Étudiants après application du repêchage (si applicable) :', etudiants);
                // -----------------------------

                // 3. Regroupement des données pour l'affichage
                const regroupement = {};
                etudiants.forEach(curr => {
                    const detailNote = {
                        nomEc: curr.nomEc,
                        valeur: parseFloat(curr.note) || 0,
                        coef: parseFloat(curr.coef) || 1
                    };

                    if (!regroupement[curr.matricule]) {
                        regroupement[curr.matricule] = {
                            matricule: curr.matricule,
                            prenom: curr.prenom,
                            nom: curr.nomEtudiant,
                            notes: [detailNote]
                        };
                    } else {
                        regroupement[curr.matricule].notes.push(detailNote);
                    }
                });

                // 4. Calcul final et Affichage
                Object.values(regroupement).forEach(etudiant => {
                    const row = document.createElement('tr');

                    const totalPoints = etudiant.notes.reduce((acc, n) => acc + (n.valeur * n.coef), 0);
                    const totalCoefs = etudiant.notes.reduce((acc, n) => acc + n.coef, 0);
                    const moyenneCalculée = totalCoefs > 0 ? (totalPoints / totalCoefs) : 0;
                    const moyenneFormattee = moyenneCalculée.toFixed(2);

                    // Mise à jour des stats globales
                    effectif++;
                    moyenneGenerale += moyenneCalculée;
                    
                    if (moyenneCalculée >= 10) {
                        nbReussite++;
                    } else {
                        nbEchec++;
                    }

                    // Calcul Min/Max
                    if (minMoyenne === 0 || moyenneCalculée < minMoyenne) minMoyenne = moyenneCalculée;
                    if (moyenneCalculée > maxMoyenne) maxMoyenne = moyenneCalculée;

                    // Mise à jour des badges d'intervalles
                    instervalleNote.forEach(intervalle => {
                        if (moyenneCalculée >= intervalle.min && moyenneCalculée < intervalle.max) {
                            intervalle.nbEtudiants++;
                            const badge = document.getElementById('nbEtudiantsIntervalle' + intervalle.min);
                            if (badge) badge.textContent = intervalle.nbEtudiants + ' Étudiants';
                        }
                    });

                    // Création de la ligne du tableau
                    row.innerHTML = `
                        <td>${etudiant.matricule || ''}</td>
                        <td>${etudiant.prenom || ''} ${etudiant.nom || ''}</td>
                        <td>
                            <span class="badge ${moyenneCalculée >= 10 ? 'bg-success' : 'bg-danger'}" style="padding: 8px;">
                                ${moyenneFormattee} / 20
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-info btn-voir-notes">
                                <i class="fas fa-eye"></i> Voir Détails (${etudiant.notes.length})
                            </button>
                        </td>
                    `;
                    row.querySelector('.btn-voir-notes').addEventListener('click', () => voirNotes(etudiant));
                    ecTableBody.appendChild(row);
                });

                // 5. Mise à jour de l'affichage des statistiques (Headers)
                document.getElementById('meilleureNoteUE').textContent = maxMoyenne.toFixed(2);
                document.getElementById('moinsBonneNoteUE').textContent = minMoyenne.toFixed(2);
                document.getElementById('moyenneUE').textContent = (moyenneGenerale / effectif).toFixed(2);
                document.getElementById('nombreEtudiants').textContent = effectif;
                document.getElementById('valideUE').textContent = ((nbReussite / effectif) * 100).toFixed(2) + '%';
                document.getElementById('nonValideUE').textContent = ((nbEchec / effectif) * 100).toFixed(2) + '%';
                document.getElementById('effectifReussite').textContent = nbReussite;
                document.getElementById('effectifEchec').textContent = nbEchec;

            } else {
                ecTableBody.innerHTML = `<tr><td colspan="4" class="text-center">Aucun étudiant trouvé</td></tr>`;
            }
        })
        .catch(error => console.error('Erreur:', error));
}

    function getStatUE(ueId) {
        // Implémentation de la récupération des statistiques pour une UE
        return fetch(`deliberationUeController.php?action=getStatUE&ueId=${ueId}`)
            .then(response => response.json());
    }
    // Initialiser la page
    initializePage();
});
function voirNotes(etudiant) {
    // 1. Vérification de sécurité pour éviter les erreurs si notes est indéfini
    const notes = etudiant.notes || [];

    // 2. Génération de la liste HTML
    // On utilise map pour transformer chaque note en élément de liste <li>
    // À mettre à jour dans ta fonction voirNotes :
    const notesList = etudiant.notes.map((n) =>
        `<li><strong>${n.nomEc}</strong> : ${n.valeur} (coef ${n.coef})</li>`
    ).join('');

    // 3. Affichage de la fenêtre modale SweetAlert2
    Swal.fire({
        title: `Notes de ${etudiant.prenom} ${etudiant.nom}`, // Utilise .nom comme défini précédemment
        html: `
            <div style="text-align: left; margin-top: 15px;">
                <p><strong>Matricule :</strong> ${etudiant.matricule}</p>
                <hr>
                <ul style="list-style: none; padding: 0;">
                    ${notesList.length > 0 ? notesList : '<li>Aucune note enregistrée.</li>'}
                </ul>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'Fermer',
        confirmButtonColor: '#3085d6',
        width: '400px'
    });
}
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