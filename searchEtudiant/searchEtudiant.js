// document.addEventListener('DOMContentLoaded', function() {
//     const searchForm = document.getElementById('searchForm');
//     searchForm.addEventListener('submit', function(event) {
//         event.preventDefault();
//         searchEtudiant();
//     });
// });

// function getEtudiantByMatricule(matricule) {
//     return fetch(`searchController.php?action=searchEtudiant&matricule=${encodeURIComponent(matricule)}`)
//         .then(async response => {
//             if (!response.ok) {
//                 throw new Error('Network response was not ok');
//             }
            
//             // return response.json();
//             const data = await response.json();
//             if (data.length === 0) {
//                 throw new Error('Aucun étudiant trouvé avec ce matricule');
//             }
//             return data;
//         })
//         .then(data => {
//             if (data.length === 0) {
//                 // throw new Error('Aucun étudiant trouvé avec ce matricule');
//                 swal.fire({
//                     icon: 'error',
//                     title: 'Erreur',
//                     text: 'Aucun étudiant trouvé avec ce matricule'
//                 });
//             }
//             return data[0];
//         });
// }

// async function searchEtudiant() {
//     const matriculeInput = document.getElementById('matriculeInput');
//     const matricule = matriculeInput.value.trim();
    
//     if (matricule === '') {
//         // alert('Veuillez entrer un matricule.');
//         swal.fire({
//             icon: 'warning',
//             title: 'Attention',
//             text: 'Veuillez entrer un matricule.',
//             confirmButton : 'OK'
//         });
//         return;
//     }

//     getEtudiantByMatricule(matricule)
//         .then(etudiant => {
//             const resultContainer = document.getElementById('resultContainer');
            
//             // Construction d'une interface plus riche
//             const etudiantCard = `
//             <div class="card mt-4 shadow-sm" style="border-radius: 15px; overflow: hidden;">
//                 <div class="card-header text-white">
//                     <h4 class="card-title">Détails du profil</h4>
//                 </div>
//                 <div class="card-body">
//                     <div class="row align-items-center">
//                         <div class="col-md-4 text-center border-end">
//                             <img src="${etudiant.photo}" 
//                                  alt="Photo de ${etudiant.prenom}" 
//                                  onerror="this.src='https://via.placeholder.com/150?text=Pas+de+Photo'"
//                                  style="width:160px; height:160px; object-fit:cover; border-radius:50%; border:4px solid #f8f9fa; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
//                             <div class="mt-3">
//                                 <span class="badge bg-primary text-white">${etudiant.matricule}</span>
//                             </div>
//                         </div>
                        
//                         <div class="col-md-8">
//                             <div class="ps-md-4">
//                                 <h2 class="text-uppercase mb-1" style="color: #2c3e50;">${etudiant.prenom + ' ' + etudiant.nom}</h2>
//                                 <h4 class="text-muted fw-bold mb-4"></h4>
                                
//                                 <div class="row g-3">
//                                     <div class="col-sm-6">
//                                         <small class="text-muted d-block">Sexe</small>
//                                         <strong>${etudiant.sexe}</strong>
//                                     </div>
//                                     <div class="col-sm-6">
//                                         <small class="text-muted d-block">Nationalité</small>
//                                         <strong>${etudiant.nationalite}</strong>
//                                     </div>
//                                     <div class="col-sm-6">
//                                         <small class="text-muted d-block">Né(e) le</small>
//                                         <strong>${new Date(etudiant.dateNaissance).toLocaleDateString()}</strong>
//                                     </div>
//                                     <div class="col-sm-6">
//                                         <small class="text-muted d-block">Lieu de naissance</small>
//                                         <strong>${etudiant.lieuNaissance}</strong>
//                                     </div>
//                                     <div class="col-sm-12">
//                                         <small class="text-muted d-block">Classe</small>
//                                         <strong>${etudiant.niveau + ' ' + etudiant.option}</strong>
//                                     </div>
//                                 </div>
//                             </div>
//                         </div>
//                         <div class="col-12 mt-4" id="ueContainer">
//                         <hr>
//                         </div>
//                     </div>
//                 </div>
//             </div>
//             `;
            
//             resultContainer.innerHTML = etudiantCard;
//             getUEsByEtudiant(matricule);
//         })
//         .catch(error => {
//             // alert("Erreur : " + error.message);
//             swal.fire({
//                 icon: 'error',
//                 title: 'Erreur',
//                 text: error.message
//             });
            
//         });
// }

// async function getUEsByEtudiant(matricule) {
//     try {
//         const response = await fetch(`searchController.php?action=getUEsByEtudiant&matricule=${encodeURIComponent(matricule)}`);
        
//         if (!response.ok) throw new Error('Erreur lors de la récupération des UE');
        
//         const data = await response.json();
//         const ueContainer = document.getElementById('ueContainer');
//         ueContainer.innerHTML = ''; // On vide le conteneur avant de remplir

//         if (data.length === 0) {
//             ueContainer.innerHTML = '<div class="alert alert-info">Aucune UE inscrite.</div>';
//             return data;
//         }

//         // 1. Groupement des UE par nomMaquette
//         const groupedUE = data.reduce((acc, ue) => {
//             if (!acc[ue.nomMaquette]) acc[ue.nomMaquette] = [];
//             acc[ue.nomMaquette].push(ue);
//             return acc;
//         }, {});

//         const maquettes = Object.keys(groupedUE);

//         // 2. Construction de la structure des onglets (Bootstrap 5)
//         let tabsHeader = '<ul class="nav nav-tabs" id="ueTab" role="tablist">';
//         let tabsContent = '<div class="tab-content border border-top-0 p-3 bg-white shadow-sm" id="ueTabContent" style="border-radius: 0 0 10px 10px;">';

//         maquettes.forEach((maquette, index) => {
//             const tabId = `tab-${index}`;
//             const isActive = index === 0 ? 'active' : '';
            
//             // Bouton de l'onglet
//             tabsHeader += `
//                 <li class="nav-item" role="presentation">
//                     <button class="nav-link ${isActive} fw-bold" id="${tabId}-tab" data-bs-toggle="tab" 
//                         data-bs-target="#${tabId}" type="button" role="tab">
//                         ${maquette}
//                     </button>
//                 </li>`;

//             // Contenu de l'onglet (Liste des UE)
//             tabsContent += `
//                 <div class="tab-pane fade show ${isActive}" id="${tabId}" role="tabpanel">
//                     <div class="table-responsive">
//                         <table class="table table-hover mb-0">
//                             <thead class="table-light">
//                                 <tr class="fw-bold fs-6 text-muted">
//                                     <th style="width: 20%">Code</th>
//                                     <th>Intitulé de l'Unité d'Enseignement</th>
//                                     <th>Nature</th>
//                                     <th>Credits</th>
//                                     <th>Action</th>
//                                 </tr>
//                             </thead>
//                             <tbody>
//                                 ${groupedUE[maquette].map(ue => `
//                                     <tr>
//                                         <td><span class="badge bg-primary">${ue.code || 'N/A'}</span></td>
//                                         <td class="text-dark fw-bold">${ue.nomUE}</td>
//                                         <td>${ue.id_nature === 1 ? '<span class="badge badge-light-success">Fondamentale</span>' : '<span class="badge badge-light-warning">Complémentaire</span>'}</td>
//                                         <td>
//                                             <span class="badge badge-light-info">${ue.credits || 'N/A'}</span>
//                                         </td>
//                                         <td>
//                                             <button class="btn btn-sm btn-outline-primary" 
//                                                 onclick="loadEC(${ue.id}, '${ue.nomUE}')">Voir les EC</button>
//                                         </td>
//                                     </tr>
//                                 `).join('')}
//                             </tbody>
//                         </table>
//                     </div>
//                 </div>`;
//         });

//         tabsHeader += '</ul>';
//         tabsContent += '</div>';

//         // 3. Injection dans le DOM
//         ueContainer.innerHTML = `
//             <div class="mt-4">
//                 <h5 class="mb-3 text-secondary"><i class="fas fa-layer-group me-2"></i>Inscriptions par Maquette</h5>
//                 ${tabsHeader}
//                 ${tabsContent}
//             </div>
//         `;

//         return data;

//     } catch (error) {
//         console.error(error);
//         document.getElementById('ueContainer').innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
//     }
// }
// function loadEC(ueId, ueName) {
//     // Logique pour charger les EC de l'UE
//     try {
//         fetch(`searchController.php?action=listECByUE&idUE=${encodeURIComponent(ueId)}`)
//             .then(response => {
//                 if (!response.ok) throw new Error('Erreur lors de la récupération des EC');
//                 return response.json();
//             })
//             .then(data => {
//                 let ecList = '<ul class="list-group">';
//                 data.forEach(ec => {
//                     ecList += `<li class="list-group-item d-flex justify-content-between align-items-center">
//                                     <p class="m-2 fw-bold"><span class="badge badge-light-primary">${ec.code || 'N/A'}</span> : ${ec.nomEC}</p>
//                                     <p class="m-2"> <span class="badge badge-light-info">${ec.credits || 'N/A'} crédits</span></p>
                                   
//                                </li>`;
//                 });
//                 ecList += '</ul>';
//                 // Afficher dans une modal ou une alerte
//                 const ecModalContent = `
//                     <div class="modal fade" id="ecModal" tabindex="-1" aria-labelledby="ecModalLabel" aria-hidden="true">
//                       <div class="modal-dialog modal-lg modal-dialog-centered">
//                         <div class="modal-content">
//                           <div class="modal-header">
//                             <h5 class="modal-title" id="ecModalLabel">Éléments Constitutifs de l'UE: ${ueName}</h5>
//                             <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
//                           </div>
//                           <div class="modal-body">
//                             ${ecList}
//                           </div>
//                             <div class="modal-footer">
//                                 <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
//                             </div>
//                         </div>
//                       </div>
//                     </div>
//                 `;
//                 document.body.insertAdjacentHTML('beforeend', ecModalContent);
//                 const ecModal = new bootstrap.Modal(document.getElementById('ecModal'));
//                 ecModal.show();
//                 document.getElementById('ecModal').addEventListener('hidden.bs.modal', function () {
//                     document.getElementById('ecModal').remove();
//                 });
//             })
//             .catch(error => {
//                 alert("Erreur : " + error.message);
//             });
//     } catch (error) {
//         alert("Erreur : " + error.message);
//     }
// }