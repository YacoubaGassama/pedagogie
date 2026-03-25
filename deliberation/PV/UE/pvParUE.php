<?php
session_start();
$email             = $_SESSION['email']            ?? 'example@uahb.sn';
$statutUtilisateur = $_SESSION['statutUtilisateur'] ?? 1;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <title>UAHB - Procès Verbal de Délibération par UE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta charset="utf-8" />
    <link rel="shortcut icon" href="http://localhost/pedagogie/dist_assets/media/logos/1.png" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <link href="http://localhost/pedagogie/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="http://localhost/pedagogie/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
    <link href="http://localhost/pedagogie/dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.0/dist/sweetalert2.min.css">
</head>

<body id="kt_body" class="header-fixed header-tablet-and-mobile-fixed toolbar-enabled toolbar-fixed aside-enabled aside-fixed"
    style="--kt-toolbar-height:55px;--kt-toolbar-height-tablet-and-mobile:55px">

    <div class="d-flex flex-column flex-root">
        <div class="page d-flex flex-row flex-column-fluid">

            <!-- ═══ ASIDE ═══════════════════════════════════════════════════════ -->
            <div id="kt_aside" class="aside aside-light aside-hoverable"
                data-kt-drawer="true" data-kt-drawer-name="aside"
                data-kt-drawer-activate="{default: true, lg: false}"
                data-kt-drawer-overlay="true"
                data-kt-drawer-width="{default:'200px', '300px': '250px'}"
                data-kt-drawer-direction="start"
                data-kt-drawer-toggle="#kt_aside_mobile_toggle">

                <div class="aside-logo flex-column-auto" id="kt_aside_logo">
                    <a href="#">
                        <img alt="Logo" src="http://localhost/pedagogie/dist_assets/media/logos/1.png"
                            class="h-50px logo" style="margin-left:70px!important;margin-top:5px;" />
                    </a>
                    <div id="kt_aside_toggle"
                        class="btn btn-icon w-auto px-0 btn-active-color-primary aside-toggle"
                        data-kt-toggle="true" data-kt-toggle-state="active"
                        data-kt-toggle-target="body" data-kt-toggle-name="aside-minimize">
                        <span class="svg-icon svg-icon-1 rotate-180">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path opacity="0.5" d="M14.2657 11.4343L18.45 7.25C18.8642 6.83579 18.8642 6.16421 18.45 5.75C18.0358 5.33579 17.3642 5.33579 16.95 5.75L11.4071 11.2929C11.0166 11.6834 11.0166 12.3166 11.4071 12.7071L16.95 18.25C17.3642 18.6642 18.0358 18.6642 18.45 18.25C18.8642 17.8358 18.8642 17.1642 18.45 16.75L14.2657 12.5657C13.9533 12.2533 13.9533 11.7467 14.2657 11.4343Z" fill="black"/>
                                <path d="M8.2657 11.4343L12.45 7.25C12.8642 6.83579 12.8642 6.16421 12.45 5.75C12.0358 5.33579 11.3642 5.33579 10.95 5.75L5.40712 11.2929C5.01659 11.6834 5.01659 12.3166 5.40712 12.7071L10.95 18.25C11.3642 18.6642 12.0358 18.6642 12.45 18.25C12.8642 17.8358 12.8642 17.1642 12.45 16.75L8.2657 12.5657C7.95328 12.2533 7.95328 11.7467 8.2657 11.4343Z" fill="black"/>
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
                    <div class="hover-scroll-overlay-y my-5 my-lg-5" id="kt_aside_menu_wrapper"
                        data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}"
                        data-kt-scroll-height="auto"
                        data-kt-scroll-dependencies="#kt_aside_logo, #kt_aside_footer"
                        data-kt-scroll-wrappers="#kt_aside_menu" data-kt-scroll-offset="0">

                        <div class="menu menu-column menu-title-gray-800 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-500"
                            id="#kt_aside_menu" data-kt-menu="true">

                            <div class="menu-item">
                                <div class="menu-content pb-2">
                                    <span class="menu-section text-muted text-uppercase fs-8 ls-1">Navigation</span>
                                </div>
                            </div>

                            <div class="menu-item">
                            <div class="menu-link">
                                <span class="menu-icon"><span class="svg-icon svg-icon-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path d="M21 7H3C2.4 7 2 6.6 2 6V4C2 3.4 2.4 3 3 3H21C21.6 3 22 3.4 22 4V6C22 6.6 21.6 7 21 7Z" fill="black"/>
                                        <path opacity="0.3" d="M21 14H3C2.4 14 2 13.6 2 13V11C2 10.4 2.4 10 3 10H21C21.6 10 22 10.4 22 11V13C22 13.6 21.6 14 21 14ZM22 20V18C22 17.4 21.6 17 21 17H3C2.4 17 2 17.4 2 18V20C2 20.6 2.4 21 3 21H21C21.6 21 22 20.6 22 20Z" fill="black"/>
                                    </svg>
                                </span></span>
                                <a class="menu-title" href="http://localhost/pedagogie/deliberation/deliberationUe.php">Délibération par UE</a>
                            </div>
                        </div>

                        <div class="menu-item">
                            <div class="menu-link">
                                <span class="menu-icon"><span class="svg-icon svg-icon-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path opacity="0.3" d="M3.5 21L20.5 21C21.3 21 22 20.3 22 19.5L22 8.5C22 7.7 21.3 7 20.5 7L10 7L7.4 4.4C7.2 4.2 6.8 4 6.4 4L3.5 4C2.7 4 2 4.7 2 5.5L2 19.5C2 20.3 2.7 21 3.5 21Z" fill="black"/>
                                    </svg>
                                </span></span>
                                <a class="menu-title" href="http://localhost/pedagogie/deliberation/deliberationRegroupee.php">Délibération Regroupée</a>
                            </div>
                        </div>

                        <div class="menu-item">
                            <div class="menu-link active">
                                <span class="menu-icon"><span class="svg-icon svg-icon-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <polygon points="0 0 24 0 24 24 0 24"/>
                                            <path d="M5.85714286,2 L13.7364114,2 C14.0910962,2 14.4343066,2.12568431 14.7051108,2.35473959 L19.4686994,6.3839416 C19.8056532,6.66894833 20,7.08787823 20,7.52920201 L20,20.0833333 C20,21.8738751 19.9795521,22 18.1428571,22 L5.85714286,22 C4.02044787,22 4,21.8738751 4,20.0833333 L4,3.91666667 C4,2.12612489 4.02044787,2 5.85714286,2 Z" fill="#000000" fill-rule="nonzero" opacity="0.3"/>
                                            <rect fill="#000000" x="6" y="11" width="9" height="2" rx="1"/>
                                            <rect fill="#000000" x="6" y="15" width="5" height="2" rx="1"/>
                                            <rect fill="#000000" x="6" y="7" width="3" height="2" rx="1"/>
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
                                        <path d="M3 3h18v4H3zM3 9h18v4H3zM3 15h18v4H3z" fill="black" opacity="0.3"/>
                                        <rect fill="black" x="3" y="3" width="18" height="2" rx="1"/>
                                    </svg>
                                </span></span>
                                <a class="menu-title" href="http://localhost/pedagogie/deliberation/PV/semestre/pvParSemestre.php">PV par Semestre</a>
                            </div>
                        </div>
                        <div class="menu-item">
                                    <div class="menu-link">
                                        <span class="menu-icon"><span class="svg-icon svg-icon-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                    <path d="M18 2H6C4.9 2 4 2.9 4 4V20C4 21.1 4.9 22 6 22H18C19.1 22 20 21.1 20 20V4C20 2.9 19.1 2 18 2ZM12 6C13.7 6 15 7.3 15 9C15 10.7 13.7 12 12 12C10.3 12 9 10.7 9 9C9 7.3 10.3 6 12 6ZM17 18H7V17C7 15.3 9.2 14 12 14C14.8 14 17 15.3 17 17V18Z" fill="black" opacity="0.3" />
                                                </svg>
                                            </span></span>
                                        <a class="menu-title" href="../../resultat/">Fiche Étudiant</a>
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
            <!-- /ASIDE -->

            <!-- ═══ WRAPPER ═════════════════════════════════════════════════════ -->
            <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">

                <!-- ── HEADER ──────────────────────────────────────────────────── -->
                <div id="kt_header" class="header align-items-stretch">
                    <div class="header-brand">
                        <a href="#">
                            <img alt="Logo" src="http://localhost/pedagogie/dist_assets/media/logos/1.png" class="h-25px" />
                        </a>
                        <div class="d-flex align-items-center d-lg-none ms-n3 me-1" title="Show aside menu">
                            <div class="btn btn-icon btn-active-color-primary w-30px h-30px" id="kt_aside_mobile_toggle">
                                <span class="svg-icon svg-icon-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path opacity="0.3" d="M21 6H3C2.4 6 2 5.6 2 5V4C2 3.4 2.4 3 3 3H21C21.6 3 22 3.4 22 4V5C22 5.6 21.6 6 21 6Z" fill="black"/>
                                        <path opacity="0.3" d="M21 12H3C2.4 12 2 11.6 2 11V10C2 9.4 2.4 9 3 9H21C21.6 9 22 9.4 22 10V11C22 11.6 21.6 12 21 12Z" fill="black"/>
                                        <path d="M21 18H3C2.4 18 2 17.6 2 17V16C2 15.4 2.4 15 3 15H21C21.6 15 22 15.4 22 16V17C22 17.6 21.6 18 21 18Z" fill="black"/>
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="toolbar" id="kt_toolbar">
                        <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack">
                            <div class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0">
                                <h1 class="d-flex align-items-center text-dark fw-bolder fs-3 my-1">
                                    Procès Verbal de Délibération
                                </h1>
                                <span class="h-20px border-gray-200 border-start mx-4"></span>
                                <ul class="breadcrumb breadcrumb-separatorless fw-bold fs-7 my-1">
                                    <li class="breadcrumb-item text-muted">
                                        <a href="#" class="text-muted text-hover-primary">Délibération</a>
                                    </li>
                                    <li class="breadcrumb-item">
                                        <span class="bullet bg-gray-200 w-5px h-2px"></span>
                                    </li>
                                    <li class="breadcrumb-item text-dark">PV par UE</li>
                                </ul>
                            </div>
                            <!-- Boutons export (masqués jusqu'à chargement du PV) -->
                            <div class="d-flex gap-2" id="exportBtns" style="display:none!important;">
                                <button class="btn btn-sm btn-light-primary" onclick="pvParUE.imprimer()">
                                    <i class="fas fa-print me-1"></i> Imprimer
                                </button>
                                <button class="btn btn-sm btn-light-success" onclick="pvParUE.exporterExcel()">
                                    <i class="fas fa-file-excel me-1"></i> Excel
                                </button>
                                <button class="btn btn-sm btn-light-danger" onclick="pvParUE.exporterPDF()">
                                    <i class="fas fa-file-pdf me-1"></i> PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /HEADER -->

                <!-- ── CONTENT ─────────────────────────────────────────────────── -->
                <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
                    <div class="post d-flex flex-column-fluid" id="kt_post">
                        <div id="kt_content_container" class="container-fluid">

                            <!-- Filtres en cascade -->
                            <div class="mt-3 card">
                                <div class="card-header border-0 pt-5" id="filterZone">
                                    <h3 class="card-title fw-bolder text-dark mb-4">
                                        <i class="fas fa-filter me-2 text-primary"></i>
                                        Sélectionner une Unité d'Enseignement
                                    </h3>
                                    <div class="row g-3 w-100">

                                        <div class="col-md-6 col-lg-2">
                                            <label class="form-label fw-semibold fs-7 text-muted text-uppercase">
                                                <i class="fas fa-graduation-cap me-1"></i> Filière
                                            </label>
                                            <select id="filterFiliere" class="form-select form-select-sm">
                                                <option value="">— Filière —</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6 col-lg-2">
                                            <label class="form-label fw-semibold fs-7 text-muted text-uppercase">
                                                <i class="fas fa-layer-group me-1"></i> Cycle
                                            </label>
                                            <select id="filterCycle" class="form-select form-select-sm" disabled>
                                                <option value="">— Cycle —</option>
                                                <option value="1">Licence</option>
                                                <option value="2">Master</option>
                                                <option value="3">Doctorat</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6 col-lg-2">
                                            <label class="form-label fw-semibold fs-7 text-muted text-uppercase">
                                                <i class="fas fa-sliders-h me-1"></i> Niveau
                                            </label>
                                            <select id="filterNiveau" class="form-select form-select-sm" disabled>
                                                <option value="">— Niveau —</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6 col-lg-2">
                                            <label class="form-label fw-semibold fs-7 text-muted text-uppercase">
                                                <i class="fas fa-cogs me-1"></i> Option
                                            </label>
                                            <select id="filterOption" class="form-select form-select-sm" disabled>
                                                <option value="">— Option —</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 col-lg-2">
                                            <label class="form-label fw-semibold fs-7 text-muted text-uppercase">
                                                <i class="fas fa-calendar-alt me-1"></i> Semestre
                                            </label>
                                            <select id="filterSemestre" class="form-select form-select-sm" disabled>
                                                <option value="">— Semestre —</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 col-lg-2">
                                            <label class="form-label fw-semibold fs-7 text-muted text-uppercase">
                                                <i class="fas fa-book me-1"></i> Unité d'Enseignement
                                            </label>
                                            <select id="filterUE" class="form-select form-select-sm" disabled>
                                                <option value="">— UE —</option>
                                            </select>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            <!-- /Filtres -->

                            <!-- Zone de résultat -->
                            <div id="alerteSemestreContainer"></div>
                            <div class="mt-3" id="pvZone">
                                <!-- Le PV s'affiche ici dynamiquement -->
                                <div class="card">
                                    <div class="card-body text-center py-10 text-muted" id="pvPlaceholder">
                                        <i class="fas fa-file-alt fs-3x mb-3 d-block text-muted opacity-50"></i>
                                        <p class="fs-5">Sélectionnez une UE pour afficher le procès verbal de délibération.</p>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                <!-- /CONTENT -->

            </div>
            <!-- /WRAPPER -->
        </div>
    </div>

    <!-- ═══ Scripts ══════════════════════════════════════════════════════════════ -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/plugins/global/plugins.bundle.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/js/scripts.bundle.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/plugins/custom/datatables/datatables.bundle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.0/dist/sweetalert2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <script src="pvParUE.js"></script>

</body>
</html>
