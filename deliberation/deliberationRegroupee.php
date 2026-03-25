<?php
session_start();
$email = $_SESSION['email'] ?? 'example@uahb.sn';
$statutUtilisateur = $_SESSION['statutUtilisateur'] ?? 1;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>UAHB - Repêchage Global des UE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta charset="utf-8" />
    <link rel="canonical" href="https://ent.uahb.sn" />
    <link rel="shortcut icon" href="http://localhost/pedagogie/dist_assets/media/logos/1.png" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <link href="http://localhost/pedagogie/dist_assets/plugins/custom/fullcalendar/fullcalendar.bundle.css" rel="stylesheet" type="text/css" />
    <link href="http://localhost/pedagogie/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="http://localhost/pedagogie/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
    <link href="http://localhost/pedagogie/dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css" />
    <!-- <link href="http://localhost/pedagogie/dist_assets/css/style.css" rel="stylesheet" type="text/css" /> -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.0/dist/sweetalert2.min.css">
</head>

<body id="kt_body" class="header-fixed header-tablet-and-mobile-fixed toolbar-enabled toolbar-fixed aside-enabled aside-fixed" style="--kt-toolbar-height:55px;--kt-toolbar-height-tablet-and-mobile:55px">
    <div class="d-flex flex-column flex-root">
        <div class="page d-flex flex-row flex-column-fluid">
            <!-- Sidebar simplifiée -->
            <div id="kt_aside" class="aside aside-light aside-hoverable" data-kt-drawer="true" data-kt-drawer-name="aside" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_aside_mobile_toggle">
                <div class="aside-logo flex-column-auto " id="kt_aside_logo">
                    <a href="#">
                        <img alt="Logo" src="http://localhost/pedagogie/dist_assets/media/logos/1.png" class="h-50px logo " style="margin-left: 70px!important; margin-top: 5px;" />
                    </a>
                    <div id="kt_aside_toggle" class="btn btn-icon w-auto px-0 btn-active-color-primary aside-toggle" data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body" data-kt-toggle-name="aside-minimize">
                        <span class="svg-icon svg-icon-1 rotate-180">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path opacity="0.5" d="M14.2657 11.4343L18.45 7.25C18.8642 6.83579 18.8642 6.16421 18.45 5.75C18.0358 5.33579 17.3642 5.33579 16.95 5.75L11.4071 11.2929C11.0166 11.6834 11.0166 12.3166 11.4071 12.7071L16.95 18.25C17.3642 18.6642 18.0358 18.6642 18.45 18.25C18.8642 17.8358 18.8642 17.1642 18.45 16.75L14.2657 12.5657C13.9533 12.2533 13.9533 11.7467 14.2657 11.4343Z" fill="black" />
                                <path d="M8.2657 11.4343L12.45 7.25C12.8642 6.83579 12.8642 6.16421 12.45 5.75C12.0358 5.33579 11.3642 5.33579 10.95 5.75L5.40712 11.2929C5.01659 11.6834 5.01659 12.3166 5.40712 12.7071L10.95 18.25C11.3642 18.6642 12.0358 18.6642 12.45 18.25C12.8642 17.8358 12.8642 17.1642 12.45 16.75L8.2657 12.5657C7.95328 12.2533 7.95328 11.7467 8.2657 11.4343Z" fill="black" />
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="aside-menu flex-column-fluid">
                    <?php
                    if ($statutUtilisateur !== 1) {
                        $current_user = json_encode($_SESSION['current_user'][0]['statutPoste']);
                    } else {
                        $current_user = 0;
                    }
                    ?>

                    <div class="hover-scroll-overlay-y my-5 my-lg-5" id="kt_aside_menu_wrapper" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-height="auto" data-kt-scroll-dependencies="#kt_aside_logo, #kt_aside_footer" data-kt-scroll-wrappers="#kt_aside_menu" data-kt-scroll-offset="0">
                        <div class="menu menu-column menu-title-gray-800 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-500" id="#kt_aside_menu" data-kt-menu="true" id="menu">
                            <div class="menu-item">
                                <div class="menu-content pb-2">
                                    <span class="menu-section text-muted text-uppercase fs-8 ls-1">Dashboard</span>
                                    <div id="user"></div>
                                </div>
                            </div>

                            <div class="menu-item nav" id="menu">
                                <?php if ($current_user == 1) { ?>
                                    <div class="">
                                        <div class="menu-link " type="button" role="tab">
                                            <span class="menu-icon">
                                                <span class="svg-icon svg-icon-2">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                        <rect x="2" y="2" width="9" height="9" rx="2" fill="black" />
                                                        <rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2" fill="black" />
                                                        <rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2" fill="black" />
                                                        <rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2" fill="black" />
                                                    </svg>
                                                </span>
                                            </span>

                                            <a class="menu-title" href="http://localhost/pedagogie/personnel/gestionTache.php">Gestion des taches</a>

                                        </div>
                                    </div>
                                <?php } ?>
                                <div class="menu-item">
                                    <div class="menu-link">
                                        <span class="menu-icon"><span class="svg-icon svg-icon-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                    <path d="M21 7H3C2.4 7 2 6.6 2 6V4C2 3.4 2.4 3 3 3H21C21.6 3 22 3.4 22 4V6C22 6.6 21.6 7 21 7Z" fill="black" />
                                                    <path opacity="0.3" d="M21 14H3C2.4 14 2 13.6 2 13V11C2 10.4 2.4 10 3 10H21C21.6 10 22 10.4 22 11V13C22 13.6 21.6 14 21 14ZM22 20V18C22 17.4 21.6 17 21 17H3C2.4 17 2 17.4 2 18V20C2 20.6 2.4 21 3 21H21C21.6 21 22 20.6 22 20Z" fill="black" />
                                                </svg>
                                            </span></span>
                                        <a class="menu-title" href="http://localhost/pedagogie/deliberation/deliberationUe.php">Délibération par UE</a>
                                    </div>
                                </div>

                                <div class="menu-item">
                                    <div class="menu-link active">
                                        <span class="menu-icon"><span class="svg-icon svg-icon-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                    <path opacity="0.3" d="M3.5 21L20.5 21C21.3 21 22 20.3 22 19.5L22 8.5C22 7.7 21.3 7 20.5 7L10 7L7.4 4.4C7.2 4.2 6.8 4 6.4 4L3.5 4C2.7 4 2 4.7 2 5.5L2 19.5C2 20.3 2.7 21 3.5 21Z" fill="black" />
                                                </svg>
                                            </span></span>
                                        <a class="menu-title" href="http://localhost/pedagogie/deliberation/deliberationRegroupee.php">Délibération Regroupée</a>
                                    </div>
                                </div>

                                <div class="menu-item">
                                    <div class="menu-link">
                                        <span class="menu-icon"><span class="svg-icon svg-icon-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                        <polygon points="0 0 24 0 24 24 0 24" />
                                                        <path d="M5.85714286,2 L13.7364114,2 C14.0910962,2 14.4343066,2.12568431 14.7051108,2.35473959 L19.4686994,6.3839416 C19.8056532,6.66894833 20,7.08787823 20,7.52920201 L20,20.0833333 C20,21.8738751 19.9795521,22 18.1428571,22 L5.85714286,22 C4.02044787,22 4,21.8738751 4,20.0833333 L4,3.91666667 C4,2.12612489 4.02044787,2 5.85714286,2 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" />
                                                        <rect fill="#000000" x="6" y="11" width="9" height="2" rx="1" />
                                                        <rect fill="#000000" x="6" y="15" width="5" height="2" rx="1" />
                                                        <rect fill="#000000" x="6" y="7" width="3" height="2" rx="1" />
                                                    </g>
                                                </svg>
                                            </span></span>
                                        <a class="menu-title" href="http://localhost/pedagogie/deliberation/PV/UE/pvParUE.php">PV par UE</a>
                                    </div>
                                </div>

                                <div class="menu-item">
                                    <div class="menu-link">
                                        <span class="menu-icon"><span class="svg-icon svg-icon-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                    <path d="M3 3h18v4H3zM3 9h18v4H3zM3 15h18v4H3z" fill="black" opacity="0.3" />
                                                    <rect fill="black" x="3" y="3" width="18" height="2" rx="1" />
                                                </svg>
                                            </span></span>
                                        <a class="menu-title" href="http://localhost/pedagogie/deliberation/PV/semestre/pvParSemestre.php">PV par Semestre</a>
                                    </div>
                                </div>
                                <div class="menu-item">
                                    <div class="menu-link" type="button" role="tab">
                                        <span class="menu-icon">
                                            <span class="svg-icon svg-icon-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                    <path d="M2 11C2 10.4477 2.44772 10 3 10H5C5.55228 10 6 10.4477 6 11V19C6 19.5523 5.55228 20 5 20H3C2.44772 20 2 19.5523 2 19V11Z" fill="black" />
                                                    <path opacity="0.3" d="M11 5C11 4.44772 11.4477 4 12 4H14C14.5523 4 15 4.44772 15 5V19C15 19.5523 14.5523 20 14 20H12C11.4477 20 11 19.5523 11 19V5Z" fill="black" />
                                                    <path d="M18 14C18 13.4477 18.4477 13 19 13H21C21.5523 13 22 13.4477 22 14V19C22 19.5523 21.5523 20 21 20H19C18.4477 20 18 19.5523 18 19V14Z" fill="black" />
                                                </svg>
                                            </span>
                                        </span>
                                        <a class="menu-title" href="http://localhost/pedagogie/deliberation/resultat/ficheEtudiant.php">Statistique</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenu principal -->
            <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">
                <div id="kt_header" class="header align-items-stretch">
                    <div class="container-fluid d-flex align-items-stretch justify-content-between">
                        <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
                            <a href="#" class="d-lg-none">
                                <img alt="Logo" src="http://localhost/pedagogie/dist_assets/media/logos/1.png" class="h-30px" />
                            </a>
                        </div>
                        <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1">
                            <div class="d-flex align-items-stretch" id="kt_header_nav">
                                <div class="header-menu align-items-stretch">
                                    <div class="menu menu-lg-rounded menu-column menu-lg-row menu-state-bg menu-title-gray-700 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-400 fw-bold my-5 my-lg-0 align-items-stretch" id="#kt_header_menu" data-kt-menu="true">
                                        <div class="menu-item me-lg-1">
                                            <a class="menu-link py-3" href="#">
                                                <span class="menu-title">Repêchage Global des Unités d'Enseignement</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
                    <div class="post d-flex flex-column-fluid" id="kt_post">
                        <div id="kt_content_container" class="container-xxl">
                            <div class="mt-1 container-fluid card p-5">
                                <div class="card-header border-0 pt-5">
                                    <h1 class="mb-4">Repêchage Global des UE</h1>
                                    <div class="row g-3">
                                        <div class="col-md-6 col-lg-2">
                                            <label class="filter-label">
                                                <i class="fas fa-graduation-cap me-1"></i> Filière
                                            </label>
                                            <select id="filiterFiliere" class="form-select filter-select">
                                                <option value="">Sélectionner une filière</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6 col-lg-2">
                                            <label class="filter-label">
                                                <i class="fas fa-layer-group me-1"></i> Cycle
                                            </label>
                                            <select id="filterCycle" class="form-select filter-select">
                                                <option value="">Sélectionner un Cycle</option>
                                                <option value="1">Licence</option>
                                                <option value="2">Master</option>
                                                <option value="3">Doctorat</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6 col-lg-2">
                                            <label class="filter-label">
                                                <i class="fas fa-sliders-h me-1"></i> Niveau
                                            </label>
                                            <select id="filterNiveau" class="form-select filter-select">
                                                <option value="">Sélectionner un Niveau</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6 col-lg-2">
                                            <label class="filter-label">
                                                <i class="fas fa-calendar-alt me-1"></i> Semestre
                                            </label>
                                            <select id="filterSemester" class="form-select filter-select">
                                                <option value="">Sélectionner un Semestre</option>
                                                <option value="1">Semestre 1</option>
                                                <option value="2">Semestre 2</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6 col-lg-2">
                                            <label class="filter-label">
                                                <i class="fas fa-cogs me-1"></i> Option
                                            </label>
                                            <select id="filterOption" class="form-select filter-select">
                                                <option value="">Sélectionner une option</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6 col-lg-2 d-flex align-items-end">
                                            <button class="btn btn-modern btn-outline-primary w-100 btnAjouterUE" data-bs-toggle="modal" data-bs-target="#modalSelectionUE">
                                                <i class="fas fa-plus me-2"></i> Ajouter UE
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-body pt-4 bg-light px-5">
                                    <div class="row g-4">
                                        <!-- COLONNE UE SÉLECTIONNÉES -->
                                        <div class="col-lg-3">
                                            <div class="card h-100">
                                                <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                                                    <h2 class="card-title h5 mb-0">UE Sélectionnées</h2>
                                                    <span id="nbUESelectionnees" class="badge bg-primary">0</span>
                                                </div>
                                                <div class="card-body" id="ueSelectionContainer">
                                                    <div id="listeUESelectionnees" class="mb-3"></div>
                                                    <div class="text-center">
                                                        <button type="button" class="btn btn-outline-primary w-100 btnAjouterUE" data-bs-toggle="modal" data-bs-target="#modalSelectionUE">
                                                            <i class="fas fa-plus me-2"></i> Ajouter des UE
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="card-footer border-top bg-light">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted" id="infoUESelection"></small>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnViderSelection">
                                                            <i class="fas fa-trash me-1"></i> Vider
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- COLONNE CONFIGURATION ET STATISTIQUES -->
                                        <div class="col-lg-9">
                                            <div class="card h-100">
                                                <div class="card-header border-bottom pt-3">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h5 class="card-title mb-0">Configuration et Statistiques</h5>
                                                        <!-- <small class="text-muted">Paramètres et statistiques avant simulation</small> -->
                                                    </div>
                                                    <div class="col-md-4 d-flex align-items-end">
                                                        <button type="button" class="btn btn-primary w-100" id="btnSimulationGlobale">
                                                            <i class="fas fa-play me-2"></i> Lancer simulation
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="card-body">
                                                    <!-- PANEL CONTRÔLES SIMULATION -->
                                                    <div class="simulation-panel card mb-4" id="simulationPanel" style="display: none; max-height: 250px; overflow-y: auto;">


                                                    </div>

                                                    <!-- SECTION STATISTIQUES -->
                                                    <div id="statsUESection" class="mt-4">
                                                        <div class="card">
                                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                                <h6 class="card-title mb-0">
                                                                    <i class="fas fa-chart-bar me-2 text-primary"></i>
                                                                    Statistiques des UE sélectionnées
                                                                </h6>
                                                                <button class="btn btn-sm btn-outline-primary" id="btnRafraichirStats">
                                                                    <i class="fas fa-sync-alt me-1"></i> Rafraîchir
                                                                </button>
                                                            </div>
                                                            <div class="card-body p-0">
                                                                <div id="statsUEContainer">
                                                                    <div class="text-center py-5 text-muted" id="statsPlaceholder">
                                                                        <i class="fas fa-chart-line fa-2x mb-3"></i>
                                                                        <p>Sélectionnez des UE pour voir leurs statistiques</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- RÉSULTATS SIMULATION -->
                                                    <div id="resultatsGlobaux" class="mt-4"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- MODAL SÉLECTION UE -->
                                    <div class="modal fade" id="modalSelectionUE" tabindex="-1" aria-labelledby="modalSelectionUELabel" aria-hidden="true">
                                        <div class="modal-dialog modal-xl modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="modalSelectionUELabel">
                                                        <i class="fas fa-book me-2"></i>Sélection des UE
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body" style="max-height: 600px; overflow-y: auto;">
                                                    <div class="row g-3 mb-4">
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold">Rechercher</label>
                                                            <input type="text" class="form-control" id="rechercheUE" placeholder="Code ou nom...">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold">Trier par</label>
                                                            <select class="form-select" id="triUE">
                                                                <option value="code">Code UE</option>
                                                                <option value="moyenne">Moyenne (bas-haut)</option>
                                                                <option value="moyenne_desc">Moyenne (haut-bas)</option>
                                                                <option value="reussite">Taux réussite</option>
                                                                <option value="effectif">Effectif</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div id="listeUEDisponibles">
                                                        <div class="text-center py-4">
                                                            <div class="spinner-border text-primary" id="loadingUE" role="status">
                                                                <span class="visually-hidden">Chargement...</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <div class="d-flex justify-content-between w-100">
                                                        <div>
                                                            <span class="badge bg-primary" id="nbUESelectionneesModal">0</span>
                                                            <small class="text-muted ms-2">UE sélectionnée(s)</small>
                                                        </div>
                                                        <div>
                                                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Annuler</button>
                                                            <button type="button" class="btn btn-primary" id="btnValiderSelection">
                                                                <i class="fas fa-check me-2"></i>Valider
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->

    <!-- Core libs (jQuery first) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Global plugins and theme bundles -->
    <script src="http://localhost/pedagogie/dist_assets/plugins/global/plugins.bundle.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/js/scripts.bundle.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/js/script.js"></script>

    <!-- DataTables and other feature bundles -->
    <script src="http://localhost/pedagogie/dist_assets/plugins/custom/datatables/datatables.bundle.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/plugins/custom/fullcalendar/fullcalendar.bundle.js"></script>

    <!-- Optional / custom scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.0/dist/sweetalert2.min.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/js/custom/widgets.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/js/custom/apps/chat/chat.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/js/custom/modals/create-app.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/js/custom/modals/upgrade-plan.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/js/jquery.validate.min.js"></script>

    <script src="deliberationRegroupee.js"></script>
</body>

</html>