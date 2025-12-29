document.addEventListener('DOMContentLoaded', function () {
    initDataTableUEEtudiant();
});

function initDataTableUEEtudiant() {
    fetch('controllerUEEtudiant.php?action=listUEs')
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
                        { data: 'nomUE', render: function(data) { return `<strong>${data}</strong>`; } },
                        { data: 'code', render: function(data) { return `<span class="badge text-primary">${data}</span>`; } },
                        { data: 'nomMaquette', render: function(data) {
                                const nomMaquetteParts = data.split(' ');
                                return `<em>${nomMaquetteParts.slice(0, -1).join(' ')}</em>`;
                            } 
                        },
                        { data: 'nombreEtudiants', render: function(data) { return `<div class="text-center"><span class="badge badge-light-primary">${data}</span></div>`; } },
                        { data: 'id', render: function(data, type, row) {
                            return `<button class="btn btn-sm btn-primary" onclick="loadEtudiantsUE(${data}, '${row.nomUE}')">Voir les étudiants</button>`;
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

function updateFilters(rows) {
    $('#filterContainer').empty();
    
    // Parse cycle, niveau, and specialite from nomMaquette
    const parseNomMaquette = (nom) => {
        const match = nom.match(/^(\w+)\s+(\d+)\s+(.+?)\s+(\d{4})$/);
        if (match) {
            return { 
                cycle: match[1], 
                niveau: match[2], 
                specialite: match[3],
                full: nom 
            };
        }
        return { cycle: nom, niveau: '', specialite: '', full: nom };
    };
    
    const parsedRows = rows.map(row => ({
        ...row,
        parsed: parseNomMaquette(row.nomMaquette)
    }));
    
    const uniqueCycles = [...new Set(parsedRows.map(row => row.parsed.cycle))];
    const uniqueNiveaux = [...new Set(parsedRows.map(row => row.parsed.niveau).filter(n => n))];
    const uniqueSpecialites = [...new Set(parsedRows.map(row => row.parsed.specialite).filter(s => s))];
    
    const filterCycle = $('<select id="filterCycle" class="form-control m-2"><option value="">Tous les cycles</option></select>');
    uniqueCycles.forEach(cycle => {
        const nomNettoye = cycle.replace(/\s+\d{4}$/, "").trim();
        
        filterCycle.append(`<option value="${nomNettoye}">${nomNettoye}</option>`);
    });
    
    const filterNiveau = $('<select id="filterNiveau" class="form-control m-2"><option value="">Tous les niveaux</option></select>');
    uniqueNiveaux.forEach(niveau => {
        filterNiveau.append(`<option value="${niveau}">${niveau}</option>`);
    });
    
    const filterSpecialite = $('<select id="filterSpecialite" class="form-control m-2"><option value="">Toutes les spécialités</option></select>');
    uniqueSpecialites.forEach(specialite => {
        filterSpecialite.append(`<option value="${specialite}">${specialite}</option>`);
    });
    const filterLabel = $(`<label class="form-label me-2"><!--begin::Svg Icon | path: assets/media/icons/duotune/general/gen031.svg-->
<span class="svg-icon svg-icon-muted svg-icon-2hx"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
<path d="M19.0759 3H4.72777C3.95892 3 3.47768 3.83148 3.86067 4.49814L8.56967 12.6949C9.17923 13.7559 9.5 14.9582 9.5 16.1819V19.5072C9.5 20.2189 10.2223 20.7028 10.8805 20.432L13.8805 19.1977C14.2553 19.0435 14.5 18.6783 14.5 18.273V13.8372C14.5 12.8089 14.8171 11.8056 15.408 10.964L19.8943 4.57465C20.3596 3.912 19.8856 3 19.0759 3Z" fill="black"/>
</svg></span>
<!--end::Svg Icon--></label>`);
    $('#filterContainer').append(filterLabel);
    $('#filterContainer').append(filterCycle).append(filterNiveau).append(filterSpecialite);
    
    $('#filterCycle, #filterNiveau, #filterSpecialite').off('change').on('change', function() {
        const cycleFilter = $('#filterCycle').val();
        const niveauFilter = $('#filterNiveau').val();
        const specialiteFilter = $('#filterSpecialite').val();
        const table = $('#tableUEEtudiant').DataTable();
        
        const searchTerms = [cycleFilter, niveauFilter, specialiteFilter].filter(v => v).join(' ');
        table.column(2).search(searchTerms).draw();
    });
}

function loadEtudiantsUE(idUE, nomUE) {
    document.getElementById('etudiantsUEModalLabel').textContent = `Étudiants inscrits à l'UE: ${nomUE}`;
    fetch(`controllerUEEtudiant.php?action=listEtudiantsByUE&idUE=${idUE}`)
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
                            { data: 'nationalite', title: '<strong>Nationalité</strong>' },
                            { data: 'sexe', title: '<strong>Sexe</strong>' },
                            { data: 'id', title: '<strong>Actions</strong>', render: function(data, type, row) {
                                return `<a href="profileEtudiant.php?matricule=${row.matricule}" class="btn btn-sm btn-primary">Voir le profil</a>`;
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