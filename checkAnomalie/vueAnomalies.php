<?php
session_start();

$email = $_SESSION['email'] ?? 'example@uahb.sn';
$statutUtilisateur = $_SESSION['statutUtilisateur'] ?? 1;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <title>UAHB - Suivi des anomalies pédagogiques</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta charset="utf-8" />
    <meta property="og:locale" content="fr_FR" />
    <meta property="og:type" content="article" />
    <meta property="og:url" content="https://ent.uahb.sn" />
    <meta property="og:site_name" content="UAHB - ENT" />
    <link rel="canonical" href="https://ent.uahb.sn" />
    <link rel="shortcut icon" href="../dist_assets/media/logos/1.png" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <link href="../dist_assets/plugins/custom/fullcalendar/fullcalendar.bundle.css" rel="stylesheet" type="text/css" />
    <link href="../dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="../dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
    <link href="../dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.0/dist/sweetalert2.min.css">

    <style>
        .stat-card {
            border-radius: 12px;
            border: 1px solid #e4e6ef;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .04);
        }

        .stat-value {
            font-size: 1.7rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .badge-critique {
            background: #fff5f8;
            color: #d9214e;
        }

        .badge-avertissement {
            background: #fff8dd;
            color: #c59a00;
        }

        .badge-ok {
            background: #e8fff3;
            color: #0a7b44;
        }

        .class-card {
            border: 1px solid #e4e6ef;
            border-radius: 12px;
            background: #fff;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .class-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #eff2f5;
            background: #fafafa;
        }

        .class-body {
            padding: 1rem 1.25rem;
        }

        .ue-card {
            border: 1px solid #eff2f5;
            border-radius: 10px;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .ue-header {
            padding: .9rem 1rem;
            background: #f8f9fb;
            border-bottom: 1px solid #eff2f5;
        }

        .ue-body {
            padding: 1rem;
        }

        .student-box {
            border: 1px dashed #d8dbe2;
            border-radius: 8px;
            padding: .75rem;
            margin-bottom: .75rem;
            background: #fcfcfc;
        }

        .anomaly-line {
            border-left: 4px solid #d9214e;
            background: #fff5f8;
            padding: .65rem .75rem;
            border-radius: 6px;
            margin-bottom: .5rem;
        }

        .anomaly-line.warning {
            border-left-color: #c59a00;
            background: #fff8dd;
        }

        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
            color: #7e8299;
        }

        .filters-bar .form-select,
        .filters-bar .form-control {
            min-height: 44px;
        }

        .mini-label {
            font-size: .8rem;
            color: #7e8299;
        }

        .mini-value {
            font-size: 1rem;
            font-weight: 600;
            color: #181c32;
        }

        .toolbar-fixed-space {
            height: 10px;
        }
    </style>
</head>

<body id="kt_body" class="header-fixed header-tablet-and-mobile-fixed toolbar-enabled toolbar-fixed aside-enabled aside-fixed" style="--kt-toolbar-height:55px;--kt-toolbar-height-tablet-and-mobile:55px">
    <div class="d-flex flex-column flex-root">
        <div class="page d-flex flex-row flex-column-fluid">
            <div id="kt_aside" class="aside aside-light aside-hoverable"
                data-kt-drawer="true"
                data-kt-drawer-name="aside"
                data-kt-drawer-activate="{default: true, lg: false}"
                data-kt-drawer-overlay="true"
                data-kt-drawer-width="{default:'200px', '300px': '250px'}"
                data-kt-drawer-direction="start"
                data-kt-drawer-toggle="#kt_aside_mobile_toggle">

                <div class="aside-logo flex-column-auto" id="kt_aside_logo">
                    <a href="#">
                        <img alt="Logo" src="../dist_assets/media/logos/1.png" class="h-50px logo" style="margin-left: 70px!important; margin-top: 5px;" />
                    </a>
                    <div id="kt_aside_toggle" class="btn btn-icon w-auto px-0 btn-active-color-primary aside-toggle"
                        data-kt-toggle="true" data-kt-toggle-state="active"
                        data-kt-toggle-target="body" data-kt-toggle-name="aside-minimize">
                        <span class="svg-icon svg-icon-1 rotate-180">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path opacity="0.5" d="M14.2657 11.4343L18.45 7.25C18.8642 6.83579 18.8642 6.16421 18.45 5.75C18.0358 5.33579 17.3642 5.33579 16.95 5.75L11.4071 11.2929C11.0166 11.6834 11.0166 12.3166 11.4071 12.7071L16.95 18.25C17.3642 18.6642 18.0358 18.6642 18.45 18.25C18.8642 17.8358 18.8642 17.1642 18.45 16.75L14.2657 12.5657C13.9533 12.2533 13.9533 11.7467 14.2657 11.4343Z" fill="black" />
                                <path d="M8.2657 11.4343L12.45 7.25C12.8642 6.83579 12.8642 6.16421 12.45 5.75C12.0358 5.33579 11.3642 5.33579 10.95 5.75L5.40712 11.2929C5.01659 11.6834 5.01659 12.3166 5.40712 12.7071L10.95 18.25C11.3642 18.6642 12.0358 18.6642 12.45 18.25C12.8642 17.8358 12.8642 17.1642 12.45 16.75L8.2657 12.5657C7.95328 12.2533 7.95328 11.7467 8.2657 11.4343Z" fill="black" />
                            </svg>
                        </span>
                    </div>
                </div>

                <div class="aside-menu flex-column-fluid">
                    <div class="hover-scroll-overlay-y my-5 my-lg-5" id="kt_aside_menu_wrapper"
                        data-kt-scroll="true"
                        data-kt-scroll-activate="{default: false, lg: true}"
                        data-kt-scroll-height="auto"
                        data-kt-scroll-dependencies="#kt_aside_logo, #kt_aside_footer"
                        data-kt-scroll-wrappers="#kt_aside_menu"
                        data-kt-scroll-offset="0">
                        <div class="menu menu-column menu-title-gray-800 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-500" id="kt_aside_menu" data-kt-menu="true">
                            <div class="menu-item">
                                <div class="menu-content pb-2">
                                    <span class="menu-section text-muted text-uppercase fs-8 ls-1">Dashboard</span>
                                </div>
                            </div>

                            <div class="menu-item">
                                <a class="menu-link" href="#">
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
                                    <span class="menu-title">Suivi des anomalies</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">
                <div id="kt_header" class="header align-items-stretch">
                    <div class="container-fluid d-flex align-items-stretch justify-content-between">
                        <div class="d-flex align-items-center d-lg-none ms-n3 me-1" title="Show aside menu">
                            <div class="btn btn-icon btn-active-light-primary w-30px h-30px w-md-40px h-md-40px" id="kt_aside_mobile_toggle">
                                <span class="svg-icon svg-icon-2x mt-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path d="M21 7H3C2.4 7 2 6.6 2 6V4C2 3.4 2.4 3 3 3H21C21.6 3 22 3.4 22 4V6C22 6.6 21.6 7 21 7Z" fill="black" />
                                        <path opacity="0.3" d="M21 14H3C2.4 14 2 13.6 2 13V11C2 10.4 2.4 10 3 10H21C21.6 10 22 10.4 22 11V13C22 13.6 21.6 14 21 14ZM22 20V18C22 17.4 21.6 17 21 17H3C2.4 17 2 17.4 2 18V20C2 20.6 2.4 21 3 21H21C21.6 21 22 20.6 22 20Z" fill="black" />
                                    </svg>
                                </span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
                            <a href="#" class="d-lg-none">
                                <img alt="Logo" src="../dist_assets/media/logos/1.png" class="h-30px" />
                            </a>
                        </div>

                        <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1">
                            <div class="d-flex align-items-stretch" id="kt_header_nav">
                                <div class="header-menu align-items-stretch"
                                    data-kt-drawer="true"
                                    data-kt-drawer-name="header-menu"
                                    data-kt-drawer-activate="{default: true, lg: false}"
                                    data-kt-drawer-overlay="true"
                                    data-kt-drawer-width="{default:'200px', '300px': '250px'}"
                                    data-kt-drawer-direction="end"
                                    data-kt-drawer-toggle="#kt_header_menu_mobile_toggle"
                                    data-kt-swapper="true"
                                    data-kt-swapper-mode="prepend"
                                    data-kt-swapper-parent="{default: '#kt_body', lg: '#kt_header_nav'}">

                                    <div class="menu menu-lg-rounded menu-column menu-lg-row menu-state-bg menu-title-gray-700 fw-bold my-5 my-lg-0 align-items-stretch" id="kt_header_menu" data-kt-menu="true">
                                        <div class="menu-item me-lg-1">
                                            <a class="menu-link py-3" href="#">
                                                <span class="menu-title">Environnement Numérique de Travail</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex align-items-stretch flex-shrink-0">
                                <div class="d-flex align-items-center ms-1 ms-lg-3" id="kt_header_user_menu_toggle">
                                    <div class="cursor-pointer symbol symbol-30px symbol-md-40px">
                                        <img src="../dist_assets/media/avatars/blank.png" />
                                    </div>
                                    <div class="ms-3">
                                        <div class="fw-bolder fs-6"><?php echo htmlspecialchars($email); ?></div>
                                        <div class="text-muted fs-8">Statut : <?php echo htmlspecialchars((string)$statutUtilisateur); ?></div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center d-lg-none ms-2 me-n3" title="Show header menu">
                                    <div class="btn btn-icon btn-active-light-primary w-30px h-30px w-md-40px h-md-40px" id="kt_header_menu_mobile_toggle">
                                        <span class="svg-icon svg-icon-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <path d="M13 11H3C2.4 11 2 10.6 2 10V9C2 8.4 2.4 8 3 8H13C13.6 8 14 8.4 14 9V10C14 10.6 13.6 11 13 11ZM22 5V4C22 3.4 21.6 3 21 3H3C2.4 3 2 3.4 2 4V5C2 5.6 2.4 6 3 6H21C21.6 6 22 5.6 22 5Z" fill="black" />
                                                <path opacity="0.3" d="M21 16H3C2.4 16 2 15.6 2 15V14C2 13.4 2.4 13 3 13H21C21.6 13 22 13.4 22 14V15C22 15.6 21.6 16 21 16ZM14 20V19C14 18.4 13.6 18 13 18H3C2.4 18 2 18.4 2 19V20C2 20.6 2.4 21 3 21H13C13.6 21 14 20.6 14 20Z" fill="black" />
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="toolbar" id="kt_toolbar">
                    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack">
                        <div class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0">
                            <h1 class="d-flex align-items-center text-dark fw-bolder fs-3 my-1">
                                Anomalies pédagogiques
                            </h1>
                            <span class="h-20px border-gray-200 border-start mx-4"></span>
                            <ul class="breadcrumb breadcrumb-separatorless fw-bold fs-7 my-1">
                                <li class="breadcrumb-item text-muted">
                                    <a href="javascript:void(0)" class="text-muted text-hover-primary">Accueil</a>
                                </li>
                                <li class="breadcrumb-item">
                                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                                </li>
                                <li class="breadcrumb-item text-dark">Vue détaillée</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
                    <div class="post d-flex flex-column-fluid" id="kt_post">
                        <div id="kt_content_container" class="container-xxl">

                            <div class="toolbar-fixed-space"></div>

                            <div class="card mb-5">
                                <div class="card-body">
                                    <div class="row g-4 filters-bar">
                                        <div class="col-md-3">
    <label class="form-label fw-bold">Filière</label>
    <select id="filterFiliere" class="form-select">
        <option value="">Toutes les filières</option>
    </select>
</div>

<div class="col-md-3">
    <label class="form-label fw-bold">Niveau</label>
    <select id="filterNiveau" class="form-select">
        <option value="">Tous les niveaux</option>
    </select>
</div>

<div class="col-md-3">
    <label class="form-label fw-bold">Option</label>
    <select id="filterOption" class="form-select">
        <option value="">Toutes les options</option>
    </select>
</div>

<div class="col-md-3">
    <label class="form-label fw-bold">Semestre</label>
    <select id="filterSemestre" class="form-select">
        <option value="">Tous les semestres</option>
    </select>
</div>
                                        <div class="col-12 d-flex gap-3 mt-3">
                                            <button class="btn btn-primary" id="btnCharger">
                                                Charger les anomalies
                                            </button>
                                            <button class="btn btn-light-primary" id="btnReinitialiser">
                                                Réinitialiser
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-5 mb-5" id="resumeCards">
                                <div class="col-md-3">
                                    <div class="stat-card p-5">
                                        <div class="text-muted fw-semibold">Classes analysées</div>
                                        <div class="stat-value" id="statClasses">0</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card p-5">
                                        <div class="text-muted fw-semibold">Classes saines</div>
                                        <div class="stat-value text-success" id="statSaines">0</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card p-5">
                                        <div class="text-muted fw-semibold">Critiques</div>
                                        <div class="stat-value text-danger" id="statCritiques">0</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card p-5">
                                        <div class="text-muted fw-semibold">Avertissements</div>
                                        <div class="stat-value text-warning" id="statAvertissements">0</div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-5">
                                <div class="card-header border-0 pt-6">
                                    <div class="card-title">
                                        <h3 class="fw-bolder">Résumé par classe</h3>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="tableClasses" class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                            <thead>
                                                <tr class="fw-bolder text-muted bg-light">
                                                    <th>Filière</th>
                                                    <th>Niveau</th>
                                                    <th>Option</th>
                                                    <th>Inscrits</th>
                                                    <th>UE maquette</th>
                                                    <th>UE analysées</th>
                                                    <th>Critiques</th>
                                                    <th>Avertissements</th>
                                                    <th>État</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header border-0 pt-6">
                                    <div class="card-title">
                                        <h3 class="fw-bolder">Détail des anomalies</h3>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="zoneChargement" class="empty-state">
                                        Clique sur <strong>Charger les anomalies</strong> pour afficher la vue détaillée.
                                    </div>
                                    <div id="rapportContainer" style="display:none;"></div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.0/dist/sweetalert2.min.js"></script>
    <script src="../dist_assets/js/jquery-3.5.1.min.js"></script>
    <script src="../dist_assets/js/scripts.bundle.js"></script>
    <script src="../dist_assets/plugins/global/plugins.bundle.js"></script>
    <script src="../dist_assets/js/script.js"></script>
    <script src="../dist_assets/plugins/custom/datatables/datatables.bundle.js"></script>

        <script>
            const ANOMALIES_API_URL = 'anomalies.php?action=getAnomalies';
            const ANOMALIES_FILTRES_URL = 'anomalies.php?action=getFiltres';
            let filtresData = {
        filieres: [],
        niveaux: [],
        options: [],
        semestres: []
    };
        </script>
    <script src="vueAnomalies.js"></script>
</body>

</html>