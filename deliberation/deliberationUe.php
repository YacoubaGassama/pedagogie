<?php
session_start();
$strategy = $_POST["strategy"] ?? "neutral";
$displayStep = floatval($_POST["rounding_step"] ?? 0.01);
$allowedDisplay = [0.01, 0.25, 0.5, 1.0];
if (!in_array($displayStep, $allowedDisplay, true)) $displayStep = 0.01;
$lockGE10 = (isset($_POST["lock_ge10"]) && $_POST["lock_ge10"] == "1");
$email = $_SESSION['email'] ?? 'example@uahb.sn';
// $id_structure = $_SESSION['id_structure'];
$statutUtilisateur = $_SESSION['statutUtilisateur'] ?? 1;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>UAHB - Environnement Numérique de Travail</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta charset="utf-8" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="article" />
    <meta property="og:url" content="https://ent.uahb.sn" />
    <meta property="og:site_name" content="UAHB - ENT" />
    <link rel="canonical" href="https://ent.uahb.sn" />
    <link rel="shortcut icon" href="http://localhost/pedagogie/dist_assets/media/logos/1.png" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <link href="http://localhost/pedagogie/dist_assets/plugins/custom/fullcalendar/fullcalendar.bundle.css" rel="stylesheet" type="text/css" />
    <link href="http://localhost/pedagogie/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="http://localhost/pedagogie/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
    <link href="http://localhost/pedagogie/dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css" />
    <!-- <link href="http://localhost/pedagogie/dist_assets/css/style.css" rel="stylesheet" type="text/css" /> -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.0/dist/sweetalert2.min.css">
    <?php
    $tacheId = $_POST['tacheId'] ?? null;
    $url = $_POST['url'] ?? null;
    $autreRessource = $_POST['autreRessource'] ?? null;
    ?>
    <style>
        /* Styles pour la nouvelle structure */
        .stat-block {
            min-height: 120px;
        }

        .stat-item {
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-weight: 600;
            font-size: 0.85rem;
            margin-right: auto;
        }

        .stat-value {
            font-weight: 700;
            font-size: 1rem;
        }

        .simulation-panel {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stat-block {
                min-height: auto;
            }

            .stat-item {
                flex-direction: row;
                text-align: left;
                gap: 0.5rem;
            }
        }
    </style>

</head>

<body id="kt_body" class="header-fixed header-tablet-and-mobile-fixed toolbar-enabled toolbar-fixed aside-enabled aside-fixed" style="--kt-toolbar-height:55px;--kt-toolbar-height-tablet-and-mobile:55px">
    <div class="d-flex flex-column flex-root">
        <div class="page d-flex flex-row flex-column-fluid">
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
                                <div class="menu-link " type="button" role="tab">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <path d="M21.7 18.9L18.6 15.8C17.9 16.9 16.9 17.9 15.8 18.6L18.9 21.7C19.3 22.1 19.9 22.1 20.3 21.7L21.7 20.3C22.1 19.9 22.1 19.3 21.7 18.9Z" fill="black" />
                                                <path opacity="0.3" d="M11 20C6 20 2 16 2 11C2 6 6 2 11 2C16 2 20 6 20 11C20 16 16 20 11 20ZM11 4C7.1 4 4 7.1 4 11C4 14.9 7.1 18 11 18C14.9 18 18 14.9 18 11C18 7.1 14.9 4 11 4ZM8 11C8 9.3 9.3 8 11 8C11.6 8 12 7.6 12 7C12 6.4 11.6 6 11 6C8.2 6 6 8.2 6 11C6 11.6 6.4 12 7 12C7.6 12 8 11.6 8 11Z" fill="black" />
                                            </svg>
                                        </span>
                                    </span>

                                    <a class="menu-title" href="http://localhost/pedagogie/searchEtudiant/searchEtudiant.php">Recherche Etudiant</a>
                                </div>
                                <div class="menu-link" type="button" role="tab">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <path d="M16.0077 19.2901L12.9293 17.5311C12.3487 17.1993 11.6407 17.1796 11.0426 17.4787L6.89443 19.5528C5.56462 20.2177 4 19.2507 4 17.7639V5C4 3.89543 4.89543 3 6 3H17C18.1046 3 19 3.89543 19 5V17.5536C19 19.0893 17.341 20.052 16.0077 19.2901Z" fill="black" />
                                            </svg>
                                        </span>
                                    </span>

                                    <a class="menu-title" href="http://localhost/pedagogie/ueEtudiant/viewUEEtudiant.php">UE et Etudiants</a>
                                </div>
                                <div class="menu-link active" type="button" role="tab">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                    <polygon points="0 0 24 0 24 24 0 24" />
                                                    <path d="M5.85714286,2 L13.7364114,2 C14.0910962,2 14.4343066,2.12568431 14.7051108,2.35473959 L19.4686994,6.3839416 C19.8056532,6.66894833 20,7.08787823 20,7.52920201 L20,20.0833333 C20,21.8738751 19.9795521,22 18.1428571,22 L5.85714286,22 C4.02044787,22 4,21.8738751 4,20.0833333 L4,3.91666667 C4,2.12612489 4.02044787,2 5.85714286,2 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" />
                                                    <rect fill="#000000" opacity="0.3" transform="translate(8.984240, 12.127098) rotate(-45.000000) translate(-8.984240, -12.127098) " x="7.41281179" y="10.5556689" width="3.14285714" height="3.14285714" rx="0.75" />
                                                    <rect fill="#000000" opacity="0.3" transform="translate(15.269955, 12.127098) rotate(-45.000000) translate(-15.269955, -12.127098) " x="13.6985261" y="10.5556689" width="3.14285714" height="3.14285714" rx="0.75" />
                                                    <rect fill="#000000" transform="translate(12.127098, 15.269955) rotate(-45.000000) translate(-12.127098, -15.269955) " x="10.5556689" y="13.6985261" width="3.14285714" height="3.14285714" rx="0.75" />
                                                    <rect fill="#000000" transform="translate(12.127098, 8.984240) rotate(-45.000000) translate(-12.127098, -8.984240) " x="10.5556689" y="7.41281179" width="3.14285714" height="3.14285714" rx="0.75" />
                                                </g>
                                            </svg>
                                        </span>
                                    </span>

                                    <a class="menu-title" href="http://localhost/pedagogie/deliberation/deliberationUe.php">Délibération par UE</a>
                                </div>
                                <div class="menu-link" type="button" role="tab">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                    <rect x="0" y="0" width="24" height="24" />
                                                    <path d="M3.5,21 L20.5,21 C21.3284271,21 22,20.3284271 22,19.5 L22,8.5 C22,7.67157288 21.3284271,7 20.5,7 L10,7 L7.43933983,4.43933983 C7.15803526,4.15803526 6.77650439,4 6.37867966,4 L3.5,4 C2.67157288,4 2,4.67157288 2,5.5 L2,19.5 C2,20.3284271 2.67157288,21 3.5,21 Z" fill="#000000" opacity="0.3" />
                                                    <rect fill="#000000" opacity="0.3" transform="translate(8.984240, 14.127098) rotate(-45.000000) translate(-8.984240, -14.127098) " x="7.41281179" y="12.5556689" width="3.14285714" height="3.14285714" rx="0.75" />
                                                    <rect fill="#000000" opacity="0.3" transform="translate(15.269955, 14.127098) rotate(-45.000000) translate(-15.269955, -14.127098) " x="13.6985261" y="12.5556689" width="3.14285714" height="3.14285714" rx="0.75" />
                                                    <rect fill="#000000" transform="translate(12.127098, 17.269955) rotate(-45.000000) translate(-12.127098, -17.269955) " x="10.5556689" y="15.6985261" width="3.14285714" height="3.14285714" rx="0.75" />
                                                    <rect fill="#000000" transform="translate(12.127098, 10.984240) rotate(-45.000000) translate(-12.127098, -10.984240) " x="10.5556689" y="9.41281179" width="3.14285714" height="3.14285714" rx="0.75" />
                                                </g>
                                            </svg>
                                        </span>
                                    </span>

                                    <a class="menu-title" href="http://localhost/pedagogie/deliberation/deliberationRegroupee.php">Délibération par Regroupée</a>
                                </div>
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
                                <img alt="Logo" src="http://localhost/pedagogie/dist_assets/media/logos/1.png" class="h-30px" />
                            </a>
                        </div>
                        <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1">
                            <div class="d-flex align-items-stretch" id="kt_header_nav">
                                <div class="header-menu align-items-stretch" data-kt-drawer="true" data-kt-drawer-name="header-menu" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}" data-kt-drawer-direction="end" data-kt-drawer-toggle="#kt_header_menu_mobile_toggle" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_body', lg: '#kt_header_nav'}">
                                    <div class="menu menu-lg-rounded menu-column menu-lg-row menu-state-bg menu-title-gray-700 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-400 fw-bold my-5 my-lg-0 align-items-stretch" id="#kt_header_menu" data-kt-menu="true">
                                        <div class="menu-item me-lg-1">
                                            <a class="menu-link py-3" href="#">
                                                <span class="menu-title">Environnement Numérique de Travail</span>

                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-stretch flex-shrink-0">
                                <div class="d-flex align-items-stretch flex-shrink-0">
                                    <div class="d-flex align-items-center ms-1 ms-lg-3" id="kt_header_user_menu_toggle">
                                        <div class="cursor-pointer symbol symbol-30px symbol-md-40px" data-kt-menu-trigger="click" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">
                                            <img src="" id="photo1" />
                                        </div>
                                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-primary fw-bold py-4 fs-6 w-275px" data-kt-menu="true">
                                            <div class="menu-item px-3">
                                                <div class="menu-content d-flex align-items-center px-3">
                                                    <div class="symbol symbol-50px me-5">
                                                        <img src="" id="photo" />
                                                    </div>
                                                    <div class="d-flex flex-column">

                                                        <div class="fw-bolder d-flex align-items-center fs-5">
                                                            <!--<span id="prenom"></span>-->
                                                            <span id="nomAgent"></span>
                                                        </div>
                                                        <a href="#" class="fw-bold text-muted text-hover-primary fs-7"><?php echo htmlspecialchars($email); ?></a>
                                                        <a href="#" class="fw-bold text-muted text-hover-primary fs-7"><?php echo ($statutUtilisateur); ?></a>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="separator my-2"></div>
                                            <div class="menu-item px-5">
                                                <!-- <a href="javascript:void(0)" class="menu-link px-5">Mon Profil</a> -->
                                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#edit_utilisateur">modifier mot de pass</button>
                                            </div>
                                            <!-- <div class="modal fade" id="edit_utilisateur" tabindex="-1" aria-labelledby="utilisateur" aria-hidden="true"> -->

                                            <!-- </div> -->
                                            <div class="separator my-2"></div>
                                            <div class="menu-item px-5">
                                                <div class="menu-content px-5">
                                                    <label class="form-check form-switch form-check-custom form-check-solid pulse pulse-success" for="kt_user_menu_dark_mode_toggle">
                                                        <!-- <a href="deconnexion.php" class="btn btn-success "><i class="bi bi-box-arrow-left fs-1"></i>Logout</a> -->
                                                        <input class="form-check-input w-30px h-20px"
                                                            checked="checked" type="checkbox" value="1" name="mode"
                                                            id="kt_user_menu_dark_mode_toggle"
                                                            data-kt-url="http://localhost/pedagogie/deconnexion.php" />
                                                        <span class="pulse-ring ms-n1"></span>
                                                        <span class="form-check-label text-gray-600 fs-7">se
                                                            déconnecter</span>
                                                    </label>
                                                </div>
                                            </div>
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
                </div>
                <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
                    <div class="post d-flex flex-column-fluid" id="kt_post">
                        <div id="kt_content_container" class="container-xxl">
                            <div class="tab-pane w-100" id="nav-tachePost" role="tabpanel" aria-labelledby="nav-tachePost-tab">
                                <div class="mt-1 container-fluid card p-5">
                                    <div class="card-header border-0 pt-5" id="filterSession">
                                        <h1 class="mb-4">Liste des Unités d'Enseignement (UE)</h1>
                                        <div class="row g-3">
                                        <div class="col-md-6 col-lg-3">
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
                                        
                                        <div class="col-md-6 col-lg-3">
                                            <label class="filter-label">
                                                <i class="fas fa-cogs me-1"></i> Option
                                            </label>
                                            <select id="filterOption" class="form-select filter-select">
                                                <option value="">Sélectionner une option</option>
                                            </select>
                                        </div>
                                    </div>
                                    </div>
                                    <div class="card-body pt-4 bg-light px-5">
                                        <div class="row g-4"> <!-- Utiliser g-4 pour l'espacement entre colonnes -->

                                            <!-- COLONNE UE -->
                                            <div class="col-lg-3">
                                                <div class="card h-100"> <!-- h-100 pour hauteur égale -->
                                                    <div class="card-header border-bottom">
                                                        <h2 class="card-title h5 mb-0">Liste des UE</h2>
                                                        <div>
                                                            <span id="ndRepeche" class="mt-0 mr-0 badge fs-bold bg-primary"></span>
                                                        </div>
                                                    </div>
                                                    <div class="card-body" id="ueBoutonContainer">
                                                        <!-- Contenu dynamique -->
                                                    </div>
                                                    <div class="card-footer border-top bg-light">
                                                        <!-- Footer content -->
                                                        <div id="intervalleNotesContainer" class="d-flex flex-wrap m-0"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- COLONNE STATISTIQUES ET EC -->
                                            <div class="col-lg-9">
                                                <div class="card h-100">

                                                    <!-- EN-TÊTE STATISTIQUES -->
                                                    <!-- Remplacer la section EN-TÊTE STATISTIQUES dans deliberationUe.php -->

                                                    <div class="card-header border-bottom pt-4">
                                                        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-2 w-100">

                                                            <!-- BLOC NOTES -->
                                                            <div class="col">
                                                                <div class="stat-block">
                                                                    <h3 class="stat-title h6 text-muted mb-2">Notes</h3>
                                                                    <div class="stat-item bg-light-success">
                                                                        <i class="fas fa-award text-warning" aria-hidden="true"></i>
                                                                        <span class="stat-label">Max :</span>
                                                                        <span class="stat-value" id="meilleureNoteUE">0</span>
                                                                    </div>
                                                                    <div class="stat-item bg-light-danger mt-2">
                                                                        <i class="fas fa-arrow-down text-danger" aria-hidden="true"></i>
                                                                        <span class="stat-label">Min :</span>
                                                                        <span class="stat-value" id="moinsBonneNoteUE">0</span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- BLOC RÉUSSITE -->
                                                            <div class="col">
                                                                <div class="stat-block">
                                                                    <h3 class="stat-title h6 text-muted mb-2">Réussite</h3>
                                                                    <div class="stat-item bg-light-primary">
                                                                        <i class="fas fa-check-circle text-success" aria-hidden="true"></i>
                                                                        <span class="stat-label">Taux :</span>
                                                                        <span class="stat-value" id="valideUE">0%</span>
                                                                    </div>
                                                                    <div class="stat-item bg-light-warning mt-2">
                                                                        <i class="fas fa-check-circle text-success" aria-hidden="true"></i>
                                                                        <span class="stat-label">Effectif :</span>
                                                                        <span class="stat-value" id="effectifReussite">0</span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- BLOC ÉCHEC -->
                                                            <div class="col">
                                                                <div class="stat-block">
                                                                    <h3 class="stat-title h6 text-muted mb-2">Échec</h3>
                                                                    <div class="stat-item bg-light-primary">
                                                                        <i class="fas fa-times-circle text-danger" aria-hidden="true"></i>
                                                                        <span class="stat-label">Taux :</span>
                                                                        <span class="stat-value" id="nonValideUE">0%</span>
                                                                    </div>
                                                                    <div class="stat-item bg-light-warning mt-2">
                                                                        <i class="fas fa-times-circle text-danger" aria-hidden="true"></i>
                                                                        <span class="stat-label">Effectif :</span>
                                                                        <span class="stat-value" id="effectifEchec">0</span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- BLOC PRÉSENCE -->
                                                            <div class="col">
                                                                <div class="stat-block ">
                                                                    <h3 class="stat-title h6 text-muted mb-2">Présence</h3>
                                                                    <div class="stat-item bg-light-info">
                                                                        <i class="fas fa-user-check text-success" aria-hidden="true"></i>
                                                                        <span class="stat-label">Présents :</span>
                                                                        <span class="stat-value" id="presentUE">0</span>
                                                                    </div>
                                                                    <div class="stat-item bg-light-danger mt-2">
                                                                        <i class="fas fa-user-times text-danger" aria-hidden="true"></i>
                                                                        <span class="stat-label">Absents :</span>
                                                                        <span class="stat-value" id="absentUE">0</span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- BLOC GLOBAL -->
                                                            <div class="col">
                                                                <div class="stat-block">
                                                                    <h3 class="stat-title h6 text-muted mb-2">Global</h3>
                                                                    <div class="stat-item bg-light-info">
                                                                        <i class="fas fa-users text-primary" aria-hidden="true"></i>
                                                                        <span class="stat-label">Effectif :</span>
                                                                        <span class="stat-value" id="nombreEtudiants">0</span>
                                                                    </div>
                                                                    <div class="stat-item bg-light-primary mt-2">
                                                                        <i class="fas fa-calculator text-primary" aria-hidden="true"></i>
                                                                        <span class="stat-label">Moyenne :</span>
                                                                        <span class="stat-value" id="moyenneUE">0</span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                        </div>
                                                    </div>

                                                    <!-- CORPS AVEC CONTRÔLES ET TABLEAU -->
                                                    <div class="card-body">

                                                        <!-- PANEL CONTRÔLES SIMULATION -->
                                                        <div class="simulation-panel card mb-4">
                                                            <div class="card-body">
                                                                <div class="row g-3 align-items-end">

                                                                    <!-- Sélecteur Stratégie -->
                                                                    <div class="col-md-4">
                                                                        <label for="strategySelect" class="form-label fw-bold">
                                                                            Stratégie de calcul
                                                                        </label>
                                                                        <select id="strategySelect" class="form-select">
                                                                            <option value="neutral">Neutre (pondérée par coef)</option>
                                                                            <option value="favor_low">Avantager notes faibles</option>
                                                                            <option value="favor_high">Avantager notes fortes</option>
                                                                        </select>
                                                                    </div>

                                                                    <!-- Sélecteur Arrondi -->
                                                                    <div class="col-md-3">
                                                                        <label for="roundingSelect" class="form-label fw-bold">
                                                                            Précision d'affichage
                                                                        </label>
                                                                        <select id="roundingSelect" class="form-select">
                                                                            <option value="0.01">0.01 (précis)</option>
                                                                            <option value="0.25">0.25</option>
                                                                            <option value="0.5">0.5</option>
                                                                            <option value="1">1.0 (entier)</option>
                                                                        </select>
                                                                    </div>

                                                                    <!-- Checkbox -->
                                                                    <div class="col-md-3">
                                                                        <div class="form-check form-switch">
                                                                            <input class="form-check-input" type="checkbox" id="lockGe10">
                                                                            <label class="form-check-label fw-bold" for="lockGe10">
                                                                                Bloquer notes ≥ 10
                                                                            </label>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Bouton Action -->
                                                                    <div class="col-md-2">
                                                                        <button type="button" class="btn btn-primary w-100" id="btnRunSimu">
                                                                            <i class="fas fa-play me-2"></i>
                                                                        </button>
                                                                    </div>

                                                                    <!-- Message d'information -->
                                                                    <div class="col-12">
                                                                        <div class="alert alert-info py-2 mb-0" role="alert">
                                                                            <small>
                                                                                <i class="fas fa-info-circle me-1"></i>
                                                                                La simulation modifie temporairement les notes pour l'analyse.
                                                                            </small>
                                                                        </div>
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- TABLEAU DES ÉTUDIANTS -->
                                                        <div class="table-responsive" id="ecTableContainer">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <button class="btn btn-sm btn-outline-primary d-none" id="btnVoirDeliberation">
            <i class="fas fa-gavel me-1"></i> Voir la délibération
        </button>
    </div>
    
    <!-- Modal pour les résultats de délibération -->
    <div class="modal fade" id="etudiantsUEModal" tabindex="-1" aria-labelledby="etudiantsUEModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="etudiantsUEModalLabel">
                        <i class="fas fa-gavel me-2"></i>
                        Résultats de la délibération
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="deliberationResultsContainer">
                        <!-- Résultats chargés dynamiquement -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Fermer
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <table class="table table-hover align-middle" id="ecTable">
        <thead class="table-light">
            <tr>
                <th scope="col" width="120">Matricule</th>
                <th scope="col">Nom complet</th>
                <th scope="col" width="100">Note</th>
                <th scope="col" width="120">Actions</th>
            </tr>
        </thead>
        <tbody id="ecTableBody">
            <!-- Données dynamiques -->
        </tbody>
    </table>
</div>
                                                        <div id="resultats"></div>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- etudiantsUEModal -->
                                        <div class="modal fade" id="etudiantsUEModal" tabindex="-1" aria-labelledby="etudiantsUEModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-xl modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="etudiantsUEModalLabel">Étudiants inscrits à l'UE</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                                                        <div class="table-responsive">
                                                            <div id="etudiantsUEModalBody"></div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                    <!-- <script src="./script.js"></script> -->
                                    <!-- <script src="../script.js"></script> -->
                                </div>
                                <div class="toolbar" id="kt_toolbar">
                                    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack">
                                        <div data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', 'lg': '#kt_toolbar_container'}" class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0">
                                            <h1 class="d-flex align-items-center text-dark fw-bolder fs-3 my-1" id="structure">
                                            </h1>
                                            <span class="h-20px border-gray-200 border-start mx-4"></span>
                                            <ul class="breadcrumb breadcrumb-separatorless fw-bold fs-7 my-1">
                                                <a href="javascript:void(0)" class="text-dark text-hover-primary" id="service"></a>
                                                <span class="h-20px border-gray-200 border-start mx-4"></span>

                                                <li class="breadcrumb-item text-muted">
                                                    <a href="javascript:void(0)" class="text-muted text-hover-primary">acceuil</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
            </div>
        </div>

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

        <!-- Page script (after all libs) -->
        <script src="deliberationUe.js"></script>

        <!-- Utilities and third-party libs used by page -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.3.1/jspdf.umd.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.13/jspdf.plugin.autotable.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
        <script>
            $('.date-own').datepicker({
                minViewMode: 2,
                format: 'yyyy'
            });
        </script>

        <!-- <script src="./maquette/script.js"></script> -->
</body>

</html>