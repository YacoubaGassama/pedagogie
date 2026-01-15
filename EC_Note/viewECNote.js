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
                        { data: 'code', render: function(data) { return `<span class="badge text-primary">${data}</span>`; } },
                        { data: 'nomEC', render: function(data) { return `<strong>${data}</strong>`; } },
                        { data: 'semestre', render: function(data) { return `<span class="badge badge-light-info">Semestre ${data}</span>`; } },
                        { data: 'nomMaquette', render: function(data, type, row) {
                                const nomFormation = `${row.niveauFormation} - ${row.code_option}`;
                                return `<div>
                                    <div><span class="badge badge-light-primary">${nomFormation}</span></div>
                                </div>`;
                            } 
                        },
                        { data: 'nombreEtudiantsTotal', render: function(data) { return `<div class="text-center"><span class="badge badge-light-primary">${data}</span></div>`; } },
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
    const uniqueAnnees = [...new Set(rows.map(row => row.annee))].sort((a, b) => b - a);
    const uniqueDepartements = [...new Set(rows.map(row => row.filiere).filter(d => d))];
    const uniqueOptions = [...new Set(rows.map(row => row.code_option).filter(o => o))];
    const uniqueCycles = ['Licence', 'Master', 'Doctorat'];
    const uniqueSemestres = ['1', '2'];
    
    
    const filterAnnee = $('<div class="ms-4 col"><label for="filterAnnee" class="me-2 fw-bold">Année</label><select id="filterAnnee" class="form-select form-select-solid fw-bold w-auto"><option value="">Toutes les années</option></select></div>');
    uniqueAnnees.forEach(annee => filterAnnee.find('#filterAnnee').append(`<option value="${annee}">${annee}</option>`));
    
    const filterDepartement = $('<div class="ms-4 col"><label for="filterDepartement" class="me-2 fw-bold">Département</label><select id="filterDepartement" class="form-select form-select-solid fw-bold w-auto"><option value="">Tous les départements</option></select></div>');
    uniqueDepartements.forEach(dept => filterDepartement.find('#filterDepartement').append(`<option value="${dept}">${dept}</option>`));
    
    const filterOption = $('<div class="ms-4 col"><label for="filterOption" class="me-2 fw-bold">Option</label><select id="filterOption" class="form-select form-select-solid fw-bold"><option value="">Toutes les options</option></select></div>');
    uniqueOptions.forEach(option => filterOption.find('#filterOption').append(`<option value="${option}">${option}</option>`));

    $('#filterContainer').append(filterDepartement).append(filterOption);

    $('#filterAnnee, #filterDepartement, #filterOption, #filterSemestre, #niveau').off('change').on('change', function() {
        const anneeFilter = $('#filterAnnee').val();
        const departementFilter = $('#filterDepartement').val();
        const optionFilter = $('#filterOption').val();
        const cycleFilter = $('#filterCycle').val();
        const semestreFilter = $('#filterSemestre').val();
        const niveauFilter = $('#niveau').val();
        const table = $('#tableUEEtudiant').DataTable();
        
        const filteredRows = rows.filter(row => 
            (!anneeFilter || row.annee == anneeFilter) &&
            (!departementFilter || row.filiere === departementFilter) &&
            (!optionFilter || row.code_option === optionFilter) &&
            (!semestreFilter || row.semestre == semestreFilter) &&
            (!niveauFilter || row.niveauFormation == niveauFilter)

        );
        
        table.clear();
        table.rows.add(filteredRows);
        table.draw();
    });
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