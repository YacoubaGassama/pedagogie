document.addEventListener('DOMContentLoaded', function () {
    initDataTableUEEtudiant();
});

function initDataTableUEEtudiant() {
    fetch('controllerECNote.php?action=listUEs')
        .then(response => response.json())
        .then(result => {
            const rows = Array.isArray(result) ? result : [];
            console.log(rows);
            
            if ($.fn.dataTable && $.fn.dataTable.isDataTable('#tableUEEtudiant')) {
                const table = $('#tableUEEtudiant').DataTable();
                table.clear();
                table.rows.add(rows);
                table.draw();
                updateFilters(rows);
            } else if ($.fn.dataTable) {
                $('#tableUEEtudiant').DataTable({
                    data: rows,
                    columns: [
                        { data: 'annee', title: '<strong>Année</strong>' },
                        // { data: 'dateSaisie', title: '<strong>Date Saisie</strong>' },
                        { data: 'codeEC', render: function(data) { return `<span class="badge text-primary">${data}</span>`; } },
                        { data: 'nomEC', render: function(data) { return `<strong>${data}</strong>`; } },
                        { data: 'semestre', render: function(data) { return `<span class="badge badge-light-info">Semestre ${data}</span>`; } },
                        { data: 'nomMaquette', render: function(data, type, row) {
                                const nomFormation = `${row.niveauFormation} - ${row.code_option}`;
                                return `<div>
                                    <div><span class="badge badge-light-primary">${nomFormation}</span></div>
                                </div>`;
                            } 
                        },
                        // { data: 'nombreEtudiantsTotal', render: function(data) { return `<div class="text-center"><span class="badge badge-light-primary">${data}</span></div>`; } },
                        { data: 'filiere', render: function(data) { return `<div class="text-center d-none"><span class="badge badge-light-success">${data}</span></div>`; } },
                        // { data: 'etudiantsNiveauDifferent', render: function(data) { return `<div class="text-center"><span class="badge badge-light-danger">${data}</span></div>`; } },
                        { data: 'idUE', render: function(data, type, row) {
                            return ` <div class="accordion-item">
                                <h2 class="accordion-header" id="heading${data}">
                                    <button class="accordion-button collapsed btn btn-sm btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#collapse${data}" aria-expanded="false" aria-controls="collapse${data}">
                                        Actions
                                    </button>
                                </h2>
                                <div id="collapse${data}" class="accordion-collapse collapse" aria-labelledby="heading${data}" data-bs-parent="#accordionUE${data}">
                                        <ul class="list-group">
                                            <li class="list-group-item" onclick="" style="cursor:pointer;" ><a href="saisieNote.php?idUE=${data}&nomEc=${row.nomEC}&action=consulter" class="link link-primary fw-bold">Consulter note</a></li>
                                            <li class="list-group-item" style="cursor:pointer;"><a href="#" onclick="redirectToSaisieNote('${data}', '${row.nomEC}')" class="link link-primary fw-bold">Saisir Note</a></li>
                                        </ul>
                                </div>

                            </div>
                            `;
                        }, orderable: false, searchable: false }
                    ],
                    paging: true,
                    searching: true,
                    ordering: true,
                    order: [[0, 'asc']],
                    responsive: true,
                    pageLength: 10,
                    lengthMenu: [5, 10, 20, 50],
                    language: {
                        lengthMenu: "Afficher _MENU_ entrées",
                        zeroRecords: "Aucune affectation trouvée",
                        info: "Affichage de _START_ à _END_ sur _TOTAL_ entrées",
                        infoEmpty: "Aucune entrée disponible",
                        infoFiltered: "(filtré à partir de _MAX_ entrées totales)",
                        search: `<!--begin::Svg Icon | path: assets/media/icons/duotune/general/gen004.svg-->
                            <span class="svg-icon svg-icon-muted svg-icon-2hx"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M21.7 18.9L18.6 15.8C17.9 16.9 16.9 17.9 15.8 18.6L18.9 21.7C19.3 22.1 19.9 22.1 20.3 21.7L21.7 20.3C22.1 19.9 22.1 19.3 21.7 18.9Z" fill="black"/>
                            <path opacity="0.3" d="M11 20C6 20 2 16 2 11C2 6 6 2 11 2C16 2 20 6 20 11C20 16 16 20 11 20ZM11 4C7.1 4 4 7.1 4 11C4 14.9 7.1 18 11 18C14.9 18 18 14.9 18 11C18 7.1 14.9 4 11 4ZM8 11C8 9.3 9.3 8 11 8C11.6 8 12 7.6 12 7C12 6.4 11.6 6 11 6C8.2 6 6 8.2 6 11C6 11.6 6.4 12 7 12C7.6 12 8 11.6 8 11Z" fill="black"/>
                            </svg></span>
                            <!--end::Svg Icon-->`,
                        searchPlaceholder: "Rechercher...",
                        searchBuilder: "Construire une recherche",
                        paginate: {
                            first: "Premier",
                            last: "Dernier",
                            next: "Suivant",
                            previous: "Précédent"
                        }
                    },
                    dom: 'fltip',
                    initComplete: function() {
                        updateFilters(rows);
                    }
                });
            } else {
                console.error('DataTables plugin is not available.');
            }
        })
        .catch(error => console.error('Error:', error));
}
function redirectToSaisieNote(idUE, nomEc) {
    Swal.fire({
    
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "rgb(51, 221, 74)",
        confirmButtonText: "Devoir",
        cancelButtonText: "Examen",
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `saisieNote.php?idUE=${idUE}&nomEc=${nomEc}&typeNote=devoir&action=saisie`;
        }else {
            window.location.href = `saisieNote.php?idUE=${idUE}&nomEc=${nomEc}&typeNote=examen&action=saisie`;
        }
    });
}
function updateFilters(rows) {
    $('#filterContainer').empty();

    // --- Fonctions cascade ---
    const getFilieresList = () =>
        [...new Set(rows.map(r => r.filiere).filter(Boolean))].sort();

    const getCyclesByFiliere = (filiere) => {
        const filtered = filiere ? rows.filter(r => r.filiere === filiere) : rows;
        // Extrait le premier mot du nomMaquette (Licence, Master, Doctorat...)
        return [...new Set(filtered.map(r => {
            const m = r.nomMaquette?.match(/^(\w+)/);
            return m ? m[1] : null;
        }).filter(Boolean))].sort();
    };

    const getNiveauxByFiliereCycle = (filiere, cycle) => {
        let filtered = rows;
        if (filiere) filtered = filtered.filter(r => r.filiere === filiere);
        if (cycle)   filtered = filtered.filter(r => r.nomMaquette?.startsWith(cycle));
        return [...new Set(filtered.map(r => r.niveauFormation).filter(Boolean))].sort();
    };

    const getOptionsByFiliereCycleNiveau = (filiere, cycle, niveau) => {
        let filtered = rows;
        if (filiere) filtered = filtered.filter(r => r.filiere === filiere);
        if (cycle)   filtered = filtered.filter(r => r.nomMaquette?.startsWith(cycle));
        if (niveau)  filtered = filtered.filter(r => r.niveauFormation === niveau);
        return [...new Set(filtered.map(r => r.option).filter(Boolean))].sort();
    };

    const getSemestresByFiliereCycleNiveauOption = (filiere, cycle, niveau, option) => {
        let filtered = rows;
        if (filiere) filtered = filtered.filter(r => r.filiere === filiere);
        if (cycle)   filtered = filtered.filter(r => r.nomMaquette?.startsWith(cycle));
        if (niveau)  filtered = filtered.filter(r => r.niveauFormation === niveau);
        if (option)  filtered = filtered.filter(r => r.option === option);
        return [...new Set(filtered.map(r => r.semestre).filter(s => s != null))].sort((a, b) => a - b);
    };

    // --- Rebuild helpers ---
    const rebuildCycles = (filiere) => {
        $('#filterCycle').empty().append('<option value="">Tous les cycles</option>');
        getCyclesByFiliere(filiere).forEach(c => {
            $('#filterCycle').append(`<option value="${c}">${c}</option>`);
        });
    };

    const rebuildNiveaux = (filiere, cycle) => {
        $('#filterNiveau').empty().append('<option value="">Tous les niveaux</option>');
        getNiveauxByFiliereCycle(filiere, cycle).forEach(n => {
            $('#filterNiveau').append(`<option value="${n}">${n}</option>`);
        });
    };

    const rebuildOptions = (filiere, cycle, niveau) => {
        $('#filterOption').empty().append('<option value="">Toutes les options</option>');
        getOptionsByFiliereCycleNiveau(filiere, cycle, niveau).forEach(o => {
            $('#filterOption').append(`<option value="${o}">${o}</option>`);
        });
    };

    const rebuildSemestres = (filiere, cycle, niveau, option) => {
        $('#filterSemestre').empty().append('<option value="">Tous les semestres</option>');
        getSemestresByFiliereCycleNiveauOption(filiere, cycle, niveau, option).forEach(s => {
            $('#filterSemestre').append(`<option value="${s}">Semestre ${s}</option>`);
        });
    };

    // --- Années (indépendant) ---
    const uniqueAnnees = [...new Set(rows.map(r => r.annee).filter(Boolean))].sort((a, b) => b.localeCompare(a));

    // --- Construction des selects ---
    const filterAnnee = $(`
        <div class="col-auto">
            <label class="me-2 fw-bold">Année</label>
            <select id="filterAnnee" class="form-select form-select-solid fw-bold w-auto">
                <option value="">Toutes les années</option>
            </select>
        </div>`);
    uniqueAnnees.forEach(a => filterAnnee.find('#filterAnnee').append(`<option value="${a}">${a}</option>`));

    const filterFiliere = $(`
        <div class="col-auto">
            <label class="me-2 fw-bold">Filière</label>
            <select id="filterFiliere" class="form-select form-select-solid fw-bold w-200px">
                <option value="">Toutes les filières</option>
            </select>
        </div>`);
    getFilieresList().forEach(f => filterFiliere.find('#filterFiliere').append(`<option value="${f}">${f}</option>`));

    const filterCycle = $(`
        <div class="col-auto">
            <label class="me-2 fw-bold">Cycle</label>
            <select id="filterCycle" class="form-select form-select-solid fw-bold w-auto">
                <option value="">Tous les cycles</option>
            </select>
        </div>`);
    getCyclesByFiliere('').forEach(c => filterCycle.find('#filterCycle').append(`<option value="${c}">${c}</option>`));

    const filterNiveau = $(`
        <div class="col-auto">
            <label class="me-2 fw-bold">Niveau</label>
            <select id="filterNiveau" class="form-select form-select-solid fw-bold w-auto">
                <option value="">Tous les niveaux</option>
            </select>
        </div>`);
    getNiveauxByFiliereCycle('', '').forEach(n => filterNiveau.find('#filterNiveau').append(`<option value="${n}">${n}</option>`));

    const filterOption = $(`
        <div class="col-auto">
            <label class="me-2 fw-bold">Option</label>
            <select id="filterOption" class="form-select form-select-solid fw-bold w-200px">
                <option value="">Toutes les options</option>
            </select>
        </div>`);
    getOptionsByFiliereCycleNiveau('', '', '').forEach(o => filterOption.find('#filterOption').append(`<option value="${o}">${o}</option>`));

    const filterSemestre = $(`
        <div class="col-auto">
            <label class="me-2 fw-bold">Semestre</label>
            <select id="filterSemestre" class="form-select form-select-solid fw-bold w-auto">
                <option value="">Tous les semestres</option>
            </select>
        </div>`);
    getSemestresByFiliereCycleNiveauOption('', '', '', '').forEach(s => filterSemestre.find('#filterSemestre').append(`<option value="${s}">Semestre ${s}</option>`));

    $('#filterContainer')
        .append(filterAnnee)
        .append(filterFiliere)
        .append(filterCycle)
        .append(filterNiveau)
        .append(filterOption)
        .append(filterSemestre);

    // --- Fonction centrale d'application des filtres ---
    const applyFilters = () => {
        const annee    = $('#filterAnnee').val();
        const filiere  = $('#filterFiliere').val();
        const cycle    = $('#filterCycle').val();
        const niveau   = $('#filterNiveau').val();
        const option   = $('#filterOption').val();
        const semestre = $('#filterSemestre').val();

        const filteredRows = rows.filter(row =>
            (!annee    || row.annee            === annee)                    &&
            (!filiere  || row.filiere          === filiere)                  &&
            (!cycle    || row.nomMaquette?.startsWith(cycle))                &&
            (!niveau   || row.niveauFormation  === niveau)                   &&
            (!option   || row.option           === option)                   &&
            (!semestre || String(row.semestre) === semestre)
        );

        const table = $('#tableUEEtudiant').DataTable();
        table.clear();
        table.rows.add(filteredRows);
        table.draw();
    };

    // --- Événements cascade ---
    $('#filterAnnee').off('change').on('change', applyFilters);

    $('#filterFiliere').off('change').on('change', function() {
        const filiere = $(this).val();
        rebuildCycles(filiere);
        rebuildNiveaux(filiere, '');
        rebuildOptions(filiere, '', '');
        rebuildSemestres(filiere, '', '', '');
        $('#filterCycle, #filterNiveau, #filterOption, #filterSemestre').val('');
        applyFilters();
    });

    $('#filterCycle').off('change').on('change', function() {
        const filiere = $('#filterFiliere').val();
        const cycle   = $(this).val();
        rebuildNiveaux(filiere, cycle);
        rebuildOptions(filiere, cycle, '');
        rebuildSemestres(filiere, cycle, '', '');
        $('#filterNiveau, #filterOption, #filterSemestre').val('');
        applyFilters();
    });

    $('#filterNiveau').off('change').on('change', function() {
        const filiere = $('#filterFiliere').val();
        const cycle   = $('#filterCycle').val();
        const niveau  = $(this).val();
        rebuildOptions(filiere, cycle, niveau);
        rebuildSemestres(filiere, cycle, niveau, '');
        $('#filterOption, #filterSemestre').val('');
        applyFilters();
    });

    $('#filterOption').off('change').on('change', function() {
        const filiere = $('#filterFiliere').val();
        const cycle   = $('#filterCycle').val();
        const niveau  = $('#filterNiveau').val();
        const option  = $(this).val();
        rebuildSemestres(filiere, cycle, niveau, option);
        $('#filterSemestre').val('');
        applyFilters();
    });

    $('#filterSemestre').off('change').on('change', applyFilters);
}
function loadEtudiantsUE(idUE, nomUE, idOption, idMaquette, idNiveauFormation) {
    document.getElementById('etudiantsUEModalLabel').textContent = `Étudiants inscrits à l'UE: ${nomUE}`;
    fetch(`controllerECNote.php?action=listEtudiantsByUE&idUE=${idUE}`)
        .then(response => response.json())
        .then(result => {
            const rows = Array.isArray(result) ? result : [];
            console.log(rows);
            $('#etudiantsUEModalBody').empty();
            if (rows.length === 0) {

                $('#etudiantsUEModalBody').append('<div class="text-center text-muted font-bold alert alert-danger">Aucun étudiant inscrit pour cette UE.</div>');
            } else {
                if ($.fn.dataTable && $.fn.dataTable.isDataTable('#etudiantsUETable')) {
                    const table = $('#etudiantsUETable').DataTable();
                    table.clear();
                    table.rows.add(rows);
                    table.draw();
                } else if ($.fn.dataTable) {
                    $('#etudiantsUEModalBody').append('<table id="etudiantsUETable" class="table table-striped"></table>');
                    $('#etudiantsUETable').DataTable({
                        data: rows,
                        columns: [
                            {data: 'photo', title: '<strong>#</strong>', render: function(data) {
                                return `<img src="${data}" alt="Photo" class="img-thumbnail" style="width: 50px; height: 50px;">`;
                            }},
                            { data: 'matricule', title: '<strong>Matricule</strong>' },
                            { data: 'nom', title: '<strong>Nom</strong>' },
                            { data: 'prenom', title: '<strong>Prénom</strong>' },
                            {data: 'niveau', title: '<strong>Classe</strong>', render: function(data, type, row) {
                                return `<span class="badge badge-light-primary">${row.niveau} ${row.option}</span>`;
                            }},
                            { data: 'nationalite', title: '<strong>Nationalité</strong>' },
                            // { data: 'sexe', title: '<strong>Sexe</strong>' },
                            { data: 'id', title: '<strong>Actions</strong>', render: function(data, type, row) {
                                return `<a href="http://localhost/centreCalcul/dist/views/profil1.php?matricule=${row.matricule}&idOpt=${idOption}&idN=${idNiveauFormation}&idMaq=${idMaquette}" class="link link-primary fw-bold">Voir le profil</a>`;
                            }, orderable: false, searchable: false }
                        ],
                        paging: true,
                        searching: true,
                        ordering: true,
                        order: [[1, 'asc']],
                        responsive: true,
                        pageLength: 10,
                        lengthMenu: [5, 10, 20, 50],
                        dom: 'fltip',
                        language: {
                            lengthMenu: "Afficher _MENU_ entrées",
                            zeroRecords: "Aucun étudiant trouvé",
                            info: "Affichage de _START_ à _END_ sur _TOTAL_ entrées",
                            infoEmpty: "Aucune entrée disponible",
                            infoFiltered: "(filtré à partir de _MAX_ entrées totales)",
                            search: "<strong class='text-muted'>Rechercher:</strong>",
                            paginate: {
                                first: "Premier",
                                last: "Dernier",
                                next: "Suivant",
                                previous: "Précédent"
                            }
                        },
                        initComplete: function() {
                            // Additional initialization if needed
                        }
                    });
                } else {
                    console.error('DataTables plugin is not available.');
                }
            }
            $('#etudiantsUEModal').modal('show');
        })
        .catch(error => console.error('Error:', error));
}