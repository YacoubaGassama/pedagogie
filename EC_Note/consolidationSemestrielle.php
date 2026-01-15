<?php
session_start();

// Vérifier si l'utilisateur est connecté
// if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
//     header('Location: http://localhost/pedagogie/page-connexion');
//     exit;
// }

// // Vérifier si le statut de l'utilisateur est "Error"
// if (isset($_SESSION['current_user'][0]['statut']) && $_SESSION['current_user'][0]['statut'] === 'Error' && $_SESSION['statutUtilisateur'] == 0) {
//     $msg = $_SESSION['current_user'][0]['Message'];
//     $_SESSION['alert_message'] = json_encode([
//         'title' => 'Token expiré',
//         'text' => "$msg\nVous n'avez pas accés à cette page.",
//         'icon' => 'error',
//         'confirmButtonText' => 'OK'
//     ]);
//     header('Location: nonAccessPage.php');
//     exit;
// }

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
    <link rel="stylesheet" href="maquetteCss">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.0/dist/sweetalert2.min.css">
    <?php
    $tacheId = $_POST['tacheId'] ?? null;
    $url = $_POST['url'] ?? null;
    $autreRessource = $_POST['autreRessource'] ?? null;
    ?>
    <script>
        const postData = {
            tacheId: <?php echo json_encode($tacheId); ?>,
            url: <?php echo json_encode($url); ?>,
            autreRessource: <?php echo json_encode($autreRessource); ?>
        };
        console.log(postData)
    </script>

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
                            <div class="menu-link" type="button" role="tab">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <path d="M16.0077 19.2901L12.9293 17.5311C12.3487 17.1993 11.6407 17.1796 11.0426 17.4787L6.89443 19.5528C5.56462 20.2177 4 19.2507 4 17.7639V5C4 3.89543 4.89543 3 6 3H17C18.1046 3 19 3.89543 19 5V17.5536C19 19.0893 17.341 20.052 16.0077 19.2901Z" fill="black" />
                                            </svg>
                                        </span>
                                    </span>

                                    <a class="menu-title" href="http://localhost/pedagogie/EC_Note/viewECNote.php">Saisie et consultation</a>
                                </div>
                                <div class="menu-link" type="button" role="tab">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <path d="M16.0077 19.2901L12.9293 17.5311C12.3487 17.1993 11.6407 17.1796 11.0426 17.4787L6.89443 19.5528C5.56462 20.2177 4 19.2507 4 17.7639V5C4 3.89543 4.89543 3 6 3H17C18.1046 3 19 3.89543 19 5V17.5536C19 19.0893 17.341 20.052 16.0077 19.2901Z" fill="black" />
                                            </svg>
                                        </span>
                                    </span>

                                    <a class="menu-title" href="http://localhost/pedagogie/EC_Note/calculeMoyEc.php">Calcul Moyenne EC</a>
                                </div>
                                <div class="menu-link" type="button" role="tab">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <path d="M16.0077 19.2901L12.9293 17.5311C12.3487 17.1993 11.6407 17.1796 11.0426 17.4787L6.89443 19.5528C5.56462 20.2177 4 19.2507 4 17.7639V5C4 3.89543 4.89543 3 6 3H17C18.1046 3 19 3.89543 19 5V17.5536C19 19.0893 17.341 20.052 16.0077 19.2901Z" fill="black" />
                                            </svg>
                                        </span>
                                    </span>

                                    <a class="menu-title" href="http://localhost/pedagogie/EC_Note/calculMoyUE.php">Calcul Moyenne UE</a>
                                </div>
                                <div class="menu-link active" type="button" role="tab">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <path d="M16.0077 19.2901L12.9293 17.5311C12.3487 17.1993 11.6407 17.1796 11.0426 17.4787L6.89443 19.5528C5.56462 20.2177 4 19.2507 4 17.7639V5C4 3.89543 4.89543 3 6 3H17C18.1046 3 19 3.89543 19 5V17.5536C19 19.0893 17.341 20.052 16.0077 19.2901Z" fill="black" />
                                            </svg>
                                        </span>
                                    </span>

                                    <a class="menu-title" href="http://localhost/pedagogie/EC_Note/consolidationSemestrielle.php">Consolidation Semestrielle</a>
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

                                <div id="ue-form-container" class="form-container" style="display: none;">
                                </div>

                                <div class="mt-1 container-fluid  p-5">
                                    <div class="border-0">
                                        <div class="card shadow-sm mb-4">
    <div class="card-body">
        <!-- Filtres améliorés -->
        <div id="filterContainer" class="mb-4 row g-3 align-items-end">
            <div class="col-md-2">
                <label for="filterAnnee" class="form-label fw-semibold">Année Académique</label>
                <select id="filterAnnee" class="form-select form-select-sm">
                    <option value="">Toutes les années</option>
                    <option value="2025-2026">2025-2026</option>
                    <option value="2024-2025">2024-2025</option>
                    <option value="2023-2024">2023-2024</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="filterSemestre" class="form-label fw-semibold">Semestre</label>
                <select id="filterSemestre" class="form-select form-select-sm">
                    <option value="">Tous les semestres</option>
                    <option value="S1">Semestre 1</option>
                    <option value="S2">Semestre 2</option>
                    <option value="S3">Semestre 3</option>
                    <option value="S4">Semestre 4</option>
                    <option value="S5">Semestre 5</option>
                    <option value="S6">Semestre 6</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="filterCycle" class="form-label fw-semibold">Cycle</label>
                <select id="filterCycle" class="form-select form-select-sm">
                    <option value="">Tous les cycles</option>
                    <option value="Licence">Licence</option>
                    <option value="Master">Master</option>
                    <option value="Doctorat">Doctorat</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="filterNiveau" class="form-label fw-semibold">Niveau</label>
                <select id="filterNiveau" class="form-select form-select-sm" disabled>
                    <option value="">Tous les niveaux</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="filterFormation" class="form-label fw-semibold">Formation</label>
                <select id="filterFormation" class="form-select form-select-sm" disabled>
                    <option value="">Toutes les formations</option>
                </select>
            </div>
            
            <div class="col-md-2 d-flex align-items-center gap-2">
                <button id="resetFilters" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-clockwise"></i> Réinitialiser
                </button>
                <button id="exportBtn" class="btn btn-success btn-sm">
                    <i class="bi bi-download"></i> Exporter
                </button>
            </div>
        </div>

        <!-- Tableau avec UE horizontales -->
        <div class="table-responsive rounded border">
            <table id="tableResultats" class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="border-bottom-2">
                        <th rowspan="2" class="align-middle">Matricule</th>
                        <th rowspan="2" class="align-middle">Nom Complet</th>
                        <th rowspan="2" class="align-middle">Niveau</th>
                        <th rowspan="2" class="align-middle">Semestre</th>
                        <th rowspan="2" class="align-middle">Code Formation</th>
                        
                        <!-- UE 1 -->
                        <th colspan="2" class="text-center border-end bg-info bg-opacity-10">
                            <div>UE 1</div>
                            <small class="text-muted fw-normal">(6 crédits)</small>
                        </th>
                        
                        <!-- UE 2 -->
                        <th colspan="2" class="text-center border-end bg-warning bg-opacity-10">
                            <div>UE 2</div>
                            <small class="text-muted fw-normal">(5 crédits)</small>
                        </th>
                        
                        <!-- UE 3 -->
                        <th colspan="2" class="text-center border-end bg-success bg-opacity-10">
                            <div>UE 3</div>
                            <small class="text-muted fw-normal">(4 crédits)</small>
                        </th>
                        
                        <!-- UE 4 -->
                        <th colspan="2" class="text-center bg-primary bg-opacity-10">
                            <div>UE 4</div>
                            <small class="text-muted fw-normal">(5 crédits)</small>
                        </th>
                        
                        <th rowspan="2" class="align-middle text-center border-start">Crédits</th>
                        <th rowspan="2" class="align-middle text-center">Moy Gén</th>
                        <th rowspan="2" class="align-middle text-center">Observation</th>
                    </tr>
                    <tr class="border-bottom-2">
                        <!-- Sous-colonnes pour UE 1 -->
                        <th class="align-middle border-2 text-center text-muted fw-semibold bg-info bg-opacity-10">Moy Sem</th>
                        <th class="align-middle border-2 text-center text-muted fw-semibold bg-info bg-opacity-10 border-end">Etat</th>
                        
                        <!-- Sous-colonnes pour UE 2 -->
                        <th class="align-middle border-2 text-center text-muted fw-semibold bg-warning bg-opacity-10">Moy Sem</th>
                        <th class="align-middle border-2 text-center text-muted fw-semibold bg-warning bg-opacity-10 border-end">Etat</th>
                        
                        <!-- Sous-colonnes pour UE 3 -->
                        <th class="align-middle border-2 text-center text-muted fw-semibold bg-success bg-opacity-10">Moy Sem</th>
                        <th class="align-middle border-2 text-center text-muted fw-semibold bg-success bg-opacity-10 border-end">Etat</th>
                        
                        <!-- Sous-colonnes pour UE 4 -->
                        <th class="align-middle border-2 text-center text-muted fw-semibold bg-primary bg-opacity-10">Moy Sem</th>
                        <th class="align-middle border-2 text-center text-muted fw-semibold bg-primary bg-opacity-10">Etat</th>
                    </tr>
                </thead>
                <tbody id="tableResultatsBody" class="fw-semibold">
                    <!-- Les données seront insérées ici dynamiquement -->
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="5" class="text-end fw-bold">Moyennes par UE:</td>
                        <td id="moyUE1" class="text-center fw-bold ">-</td>
                        <td class="text-center">-</td>
                        <td id="moyUE2" class="text-center fw-bold">-</td>
                        <td class="text-center">-</td>
                        <td id="moyUE3" class="text-center fw-bold">-</td>
                        <td class="text-center">-</td>
                        <td id="moyUE4" class="text-center fw-bold">-</td>
                        <td class="text-center">-</td>
                        <td id="totalCredits" class="text-center fw-bold text-primary">0</td>
                        <td class="text-center">-</td>
                        <td id="moyenneGlobale" class="text-center fw-bold text-primary">-</td>
                    </tr>
                    <tr>
                        <td colspan="5" class="text-end fw-bold">Taux de réussite par UE:</td>
                        <td colspan="2" id="tauxUE1" class="text-center fw-bold">0%</td>
                        <td colspan="2" id="tauxUE2" class="text-center fw-bold">0%</td>
                        <td colspan="2" id="tauxUE3" class="text-center fw-bold">0%</td>
                        <td colspan="2" id="tauxUE4" class="text-center fw-bold">0%</td>
                        <td id="moyenneGenerale" class="text-center fw-bold text-primary">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Indicateur de chargement -->
        <div id="loadingIndicator" class="text-center py-4 d-none">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="text-muted mt-2">Chargement des données...</p>
        </div>
        
        <!-- Message vide -->
        <div id="emptyMessage" class="text-center py-5 d-none">
            <i class="bi bi-table display-4 text-muted"></i>
            <p class="text-muted mt-3">Aucune donnée à afficher</p>
        </div>
        
        <!-- Statistiques -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card bg-primary bg-opacity-10 border-primary">
                    <div class="card-body">
                        <h6 class="card-title text-primary">Étudiants</h6>
                        <h3 id="statsEtudiants" class="mb-0">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success bg-opacity-10 border-success">
                    <div class="card-body">
                        <h6 class="card-title text-success">Validé</h6>
                        <h3 id="statsAdmis" class="mb-0">0</h3>
                    </div>
                </div>
            </div>
            <!-- <div class="col-md-3">
                <div class="card bg-warning bg-opacity-10 border-warning">
                    <div class="card-body">
                        <h6 class="card-title text-warning">Rattrapage</h6>
                        <h3 id="statsRattrapage" class="mb-0">0</h3>
                    </div>
                </div>
            </div> -->
            <div class="col-md-3">
                <div class="card bg-danger bg-opacity-10 border-danger">
                    <div class="card-body">
                        <h6 class="card-title text-danger">Non validé</h6>
                        <h3 id="statsEchec" class="mb-0">0</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour les détails -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails des résultats</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBodyContent">
                <!-- Contenu dynamique -->
            </div>
        </div>
    </div>
</div>

<style>
    /* Styles améliorés */
    .table-hover tbody tr:hover {
        background-color: rgba(0, 123, 255, 0.05);
        cursor: pointer;
    }
    
    .table-active {
        background-color: rgba(0, 123, 255, 0.1) !important;
        border-left: 4px solid #0d6efd !important;
    }
    
    .badge-etat {
        padding: 0.35em 0.65em;
        font-size: 0.75em;
        font-weight: 600;
    }
    
    .etat-valide {
        background-color: #198754;
        color: white;
    }
    
    .etat-non-valide {
        background-color: #dc3545;
        color: white;
    }
    
    .etat-rattrapage {
        background-color: #ffc107;
        color: #000;
    }
    
    .etat-admis {
        background-color: #198754;
        color: white;
    }
    
    .etat-ajourne {
        background-color: #dc3545;
        color: white;
    }
    
    .moyenne-success {
        color: #198754;
        font-weight: bold;
    }
    
    .moyenne-danger {
        color: #dc3545;
        font-weight: bold;
    }
    
    .moyenne-warning {
        color: #ffc107;
        font-weight: bold;
    }
    
    .credits-cell {
        font-weight: bold;
        color: #0d6efd;
    }
    
    .formation-code {
        font-family: monospace;
        background-color: #f8f9fa;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.85em;
    }
    
    th {
        white-space: nowrap;
        background-color: #f8f9fa;
    }
    
    .stat-card {
        transition: transform 0.2s;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
    }
    
    .ue-header {
        font-size: 0.9em;
        font-weight: 600;
    }
    
    .moyenne-generale-cell {
        font-weight: bold;
        font-size: 1.05em;
        background-color: #f8f9fa;
    }
    
    .moyenne-generale-success {
        color: #198754;
        background-color: rgba(25, 135, 84, 0.1);
    }
    
    .moyenne-generale-danger {
        color: #dc3545;
        background-color: rgba(220, 53, 69, 0.1);
    }
    
    .moyenne-generale-warning {
        color: #ffc107;
        background-color: rgba(255, 193, 7, 0.1);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration des données
    const config = {
        niveauxParCycle: {
            "Licence": ["L1", "L2", "L3"],
            "Master": ["M1", "M2"],
            "Doctorat": ["D1", "D2", "D3"]
        },
        formationsParCycle: {
            "Licence": ["INFO-LIC", "MATH-LIC", "PHYS-LIC"],
            "Master": ["INFO-MAS", "MATH-MAS", "DATA-MAS"],
            "Doctorat": ["MATH-DOC", "PHYS-DOC"]
        },
        uesParFormation: {
            "INFO-LIC": [
                { code: "UE101", nom: "Algorithmique", credits: 6 },
                { code: "UE102", nom: "Bases de Données", credits: 5 },
                { code: "UE103", nom: "Réseaux", credits: 4 },
                { code: "UE104", nom: "Systèmes", credits: 5 }
            ],
            "MATH-LIC": [
                { code: "UE201", nom: "Analyse", credits: 6 },
                { code: "UE202", nom: "Algèbre", credits: 5 },
                { code: "UE203", nom: "Probabilités", credits: 4 },
                { code: "UE204", nom: "Géométrie", credits: 5 }
            ],
            "INFO-MAS": [
                { code: "UE301", nom: "IA", credits: 6 },
                { code: "UE302", nom: "Big Data", credits: 5 },
                { code: "UE303", nom: "Sécurité", credits: 4 },
                { code: "UE304", nom: "Cloud", credits: 5 }
            ]
        },
        semestresParNiveau: {
            "L1": ["S1", "S2"],
            "L2": ["S3", "S4"],
            "L3": ["S5", "S6"],
            "M1": ["S1", "S2"],
            "M2": ["S3", "S4"],
            "D1": ["S1", "S2"],
            "D2": ["S3", "S4"],
            "D3": ["S5", "S6"]
        }
    };
    
    // Éléments DOM
    const elements = {
        cycleSelect: document.getElementById('filterCycle'),
        niveauSelect: document.getElementById('filterNiveau'),
        formationSelect: document.getElementById('filterFormation'),
        semestreSelect: document.getElementById('filterSemestre'),
        tableBody: document.getElementById('tableResultatsBody'),
        loadingIndicator: document.getElementById('loadingIndicator'),
        emptyMessage: document.getElementById('emptyMessage'),
        resetBtn: document.getElementById('resetFilters'),
        exportBtn: document.getElementById('exportBtn'),
        totalCredits: document.getElementById('totalCredits'),
        moyenneGenerale: document.getElementById('moyenneGenerale'),
        moyenneGlobale: document.getElementById('moyenneGlobale'),
        statsEtudiants: document.getElementById('statsEtudiants'),
        statsAdmis: document.getElementById('statsAdmis'),
        statsRattrapage: document.getElementById('statsRattrapage') || null,
        statsEchec: document.getElementById('statsEchec'),
        moyennesUE: {
            ue1: document.getElementById('moyUE1'),
            ue2: document.getElementById('moyUE2'),
            ue3: document.getElementById('moyUE3'),
            ue4: document.getElementById('moyUE4')
        },
        tauxUE: {
            ue1: document.getElementById('tauxUE1'),
            ue2: document.getElementById('tauxUE2'),
            ue3: document.getElementById('tauxUE3'),
            ue4: document.getElementById('tauxUE4')
        }
    };
    
    // Données
    let etudiants = [];
    let filteredEtudiants = [];

    // Classe Etudiant pour gérer les résultats par étudiant
    class Etudiant {
        constructor(matricule, nomComplet, niveau, semestre, formation) {
            this.matricule = matricule;
            this.nomComplet = nomComplet;
            this.niveau = niveau;
            this.semestre = semestre;
            this.codeFormation = formation.code;
            this.nomFormation = formation.nom;
            this.ues = [];
            
            // Générer les résultats pour chaque UE
            this.genererResultatsUE();
            this.calculerCreditsTotaux();
            this.determinerEtatGlobal();
            this.calculerMoyenneGenerale();
        }
        
        genererResultatsUE() {
            const ues = config.uesParFormation[this.codeFormation] || config.uesParFormation["INFO-LIC"];
            
            this.ues = ues.map((ue, index) => {
                // Génération de notes aléatoires réalistes
                const cc = 5 + Math.random() * 15; // Entre 5 et 20
                const ex = 5 + Math.random() * 15;
                const pointJury = Math.random() > 0.7 ? (Math.random() > 0.5 ? 0.5 : -0.5) : 0;
                
                // Calcul de la moyenne avec pondération 40% CC, 60% Examen
                const moyenneBase = (cc * 0.4) + (ex * 0.6);
                const moyenneFinale = Math.min(20, Math.max(0, moyenneBase + pointJury));
                
                return {
                    code: ue.code,
                    nom: ue.nom,
                    credits: ue.credits,
                    moyenne: moyenneFinale,
                    notesDetail: { cc, ex, pointJury }
                };
            });
        }
        
        getEtatUE(moyenne) {
            if (moyenne >= 10) {
                return { texte: "V", classe: "etat-valide" };
            } else {
                return { texte: "NV", classe: "etat-non-valide" };
            }
        }
        
        getMoyenneClasse(moyenne) {
            if (moyenne >= 10) return "moyenne-success";
            if (moyenne >= 8) return "moyenne-warning";
            return "moyenne-danger";
        }
        
        getMoyenneGeneraleClasse(moyenne) {
            if (moyenne >= 10) return "moyenne-generale-success";
            if (moyenne >= 8) return "moyenne-generale-warning";
            return "moyenne-generale-danger";
        }
        
        calculerCreditsTotaux() {
            this.creditsTotaux = this.ues.reduce((total, ue) => {
                return total + (ue.moyenne >= 10 ? ue.credits : 0);
            }, 0);
        }
        
        calculerMoyenneGenerale() {
            // Calcul de la moyenne générale pondérée par les crédits
            let sommePonderee = 0;
            let totalCredits = 0;
            
            this.ues.forEach(ue => {
                sommePonderee += ue.moyenne * ue.credits;
                totalCredits += ue.credits;
            });
            
            this.moyenneGenerale = totalCredits > 0 ? sommePonderee / totalCredits : 0;
            
            // Arrondir à 2 décimales
            this.moyenneGenerale = Math.round(this.moyenneGenerale * 100) / 100;
            
            return this.moyenneGenerale;
        }
        
        determinerEtatGlobal() {
            // Critère : Toutes les UE doivent être validées (moyenne >= 10)
            const toutesUEValidees = this.ues.every(ue => ue.moyenne >= 10);
            if (this.creditsTotaux >= 12) {
                this.etatGlobal = { texte: `Validé `, classe: "etat-admis" };
            } else {
                this.etatGlobal = { texte: `Non validé`, classe: "etat-ajourne" };
            }
        }
        
        getMoyenneGeneraleSimple() {
            const somme = this.ues.reduce((total, ue) => total + ue.moyenne, 0);
            return somme / this.ues.length;
        }
        
        renderRow() {
            // Calculer la moyenne générale
            const moyenneGen = this.calculerMoyenneGenerale();
            const moyenneGenClasse = this.getMoyenneGeneraleClasse(moyenneGen);
            
            // Générer les colonnes UE
            let htmlUEs = "";
            this.ues.forEach((ue, index) => {
                const etat = this.getEtatUE(ue.moyenne);
                const moyenneClasse = this.getMoyenneClasse(ue.moyenne);
                
                htmlUEs += `
                    <td class="text-center fw-bold ${moyenneClasse}">${ue.moyenne.toFixed(2)}</td>
                    <td class="text-center">
                        <span class="badge badge-etat ${etat.classe}" title="${etat.texte === 'V' ? 'Validé' : 'Non validé'}">
                            ${etat.texte}
                        </span>
                    </td>
                `;
            });
            
            return `
                <tr data-id="${this.matricule}" onclick="selectionnerEtudiant('${this.matricule}')">
                    <td class="fw-bold">${this.matricule}</td>
                    <td>${this.nomComplet}</td>
                    <td><span class="badge bg-secondary">${this.niveau}</span></td>
                    <td>${this.semestre}</td>
                    <td><span class="formation-code">${this.codeFormation}</span></td>
                    ${htmlUEs}
                    <td class="text-center fw-bold credits-cell">${this.creditsTotaux}</td>
                    <td class="text-center moyenne-generale-cell ${moyenneGenClasse}">
                        ${moyenneGen.toFixed(2)}
                    </td>
                    <td class="text-center">
                        <span class="badge badge-etat ${this.etatGlobal.classe}">${this.etatGlobal.texte}</span>
                    </td>
                </tr>`;
        }
        
        renderDetails() {
            // Calculer les moyennes pour l'affichage
            const moyenneGen = this.calculerMoyenneGenerale();
            const moyenneGenClasse = this.getMoyenneGeneraleClasse(moyenneGen);
            const moyenneSimple = this.getMoyenneGeneraleSimple();
            
            let details = `
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>${this.nomComplet}</h5>
                        <p class="mb-1"><strong>Matricule:</strong> ${this.matricule}</p>
                        <p class="mb-1"><strong>Niveau:</strong> ${this.niveau}</p>
                        <p class="mb-1"><strong>Semestre:</strong> ${this.semestre}</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <h5>${this.nomFormation}</h5>
                        <p class="mb-1"><strong>Code Formation:</strong> ${this.codeFormation}</p>
                        <p class="mb-1"><strong>Crédits obtenus:</strong> ${this.creditsTotaux}</p>
                        <span class="badge ${this.etatGlobal.classe} fs-6 p-2">${this.etatGlobal.texte}</span>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card ${moyenneGenClasse}">
                            <div class="card-body text-center">
                                <h6 class="card-title">Moyenne Générale Pondérée</h6>
                                <h2 class="${moyenneGen >= 10 ? 'text-success' : 'text-danger'}">${moyenneGen.toFixed(2)}/20</h2>
                                <p class="mb-0">Pondérée par les crédits des UE</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title">Moyenne Générale Simple</h6>
                                <h2 class="${moyenneSimple >= 10 ? 'text-success' : 'text-danger'}">${moyenneSimple.toFixed(2)}/20</h2>
                                <p class="mb-0">Moyenne arithmétique simple</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Détail des Unités d'Enseignement</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>UE</th>
                                    <th>Intitulé</th>
                                    <th class="text-center">Crédits</th>
                                    <th class="text-center">Contrôle Continu</th>
                                    <th class="text-center">Examen</th>
                                    <th class="text-center">Point Jury</th>
                                    <th class="text-center">Moyenne</th>
                                    <th class="text-center">État</th>
                                    <th class="text-center">Crédits Obtenus</th>
                                    <th class="text-center">Pondération</th>
                                </tr>
                            </thead>
                            <tbody>`;
            
            this.ues.forEach((ue, index) => {
                const etat = this.getEtatUE(ue.moyenne);
                const etatTexte = etat.texte === 'V' ? 'Validé' : 'Non validé';
                const creditsObtenus = ue.moyenne >= 10 ? ue.credits : 0;
                const contribution = (ue.moyenne * ue.credits).toFixed(2);
                
                details += `
                    <tr>
                        <td>${ue.code}</td>
                        <td>${ue.nom}</td>
                        <td class="text-center">${ue.credits}</td>
                        <td class="text-center">${ue.notesDetail.cc.toFixed(2)}</td>
                        <td class="text-center">${ue.notesDetail.ex.toFixed(2)}</td>
                        <td class="text-center ${ue.notesDetail.pointJury > 0 ? 'text-success' : ue.notesDetail.pointJury < 0 ? 'text-danger' : 'text-muted'}">
                            ${ue.notesDetail.pointJury > 0 ? '+' : ''}${ue.notesDetail.pointJury.toFixed(1)}
                        </td>
                        <td class="text-center fw-bold ${this.getMoyenneClasse(ue.moyenne)}">${ue.moyenne.toFixed(2)}</td>
                        <td class="text-center"><span class="badge ${etat.classe}">${etatTexte}</span></td>
                        <td class="text-center fw-bold ${creditsObtenus > 0 ? 'text-success' : 'text-danger'}">${creditsObtenus}</td>
                        <td class="text-center">${contribution}</td>
                    </tr>`;
            });
            
            const totalCredits = this.ues.reduce((total, ue) => total + ue.credits, 0);
            const sommePonderee = this.ues.reduce((total, ue) => total + (ue.moyenne * ue.credits), 0);
            
            details += `
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="9" class="text-end fw-bold">Total des crédits:</td>
                                    <td class="text-center fw-bold">${totalCredits}</td>
                                </tr>
                                <tr>
                                    <td colspan="9" class="text-end fw-bold">Somme pondérée:</td>
                                    <td class="text-center fw-bold">${sommePonderee.toFixed(2)}</td>
                                </tr>
                                <tr>
                                    <td colspan="9" class="text-end fw-bold">Moyenne générale pondérée:</td>
                                    <td class="text-center fw-bold ${moyenneGen >= 10 ? 'text-success' : 'text-danger'}">${moyenneGen.toFixed(2)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
                <div class="alert ${this.etatGlobal.classe} bg-opacity-10">
                    <h6 class="alert-heading">Statut académique</h6>
                    <p class="mb-0">
                        ${this.etatGlobal.texte.includes('Validé') 
                            ? `Félicitations ! L'étudiant a validé ${this.creditsTotaux} crédits sur ${totalCredits} et est admis.` 
                            : `L'étudiant est ajourné. Il a obtenu ${this.creditsTotaux} crédits sur ${totalCredits} requis.`}
                    </p>
                </div>`;
            
            return details;
        }
    }

    // Initialisation des données
    function initialiserDonnees() {
        // Données des étudiants
        const formations = [
            { code: "INFO-LIC", nom: "Licence Informatique" },
            { code: "MATH-LIC", nom: "Licence Mathématiques" },
            { code: "INFO-MAS", nom: "Master Informatique" }
        ];
        
        etudiants = [];
        
        // Générer 20 étudiants avec des données variées
        for (let i = 1; i <= 20; i++) {
            const matricule = `MAT${i.toString().padStart(3, '0')}`;
            const noms = ["Diop", "Sow", "Ba", "Ndiaye", "Fall", "Sy", "Kane", "Diallo", "Traoré", "Cissé"];
            const prenoms = ["Ali", "Marie", "Fatou", "Jean", "Oumar", "Fatima", "Ibrahim", "Aïcha", "Moussa", "Aminata"];
            
            const nomComplet = `${noms[i % noms.length]} ${prenoms[i % prenoms.length]}`;
            const niveau = ["L1", "L2", "L3", "M1", "M2"][Math.floor(Math.random() * 5)];
            const semestre = config.semestresParNiveau[niveau] 
                ? config.semestresParNiveau[niveau][Math.floor(Math.random() * config.semestresParNiveau[niveau].length)]
                : "S1";
            const formation = formations[Math.floor(Math.random() * formations.length)];
            
            const etudiant = new Etudiant(matricule, nomComplet, niveau, semestre, formation);
            etudiants.push(etudiant);
        }
        
        filteredEtudiants = [...etudiants];
        afficherEtudiants();
        calculerStatistiques();
    }

    // Gestion des filtres
    function initialiserFiltres() {
        // Gestion du cycle
        elements.cycleSelect.addEventListener('change', function() {
            const cycle = this.value;
            
            // Réinitialiser les sélecteurs dépendants
            elements.niveauSelect.innerHTML = '<option value="">Tous les niveaux</option>';
            elements.formationSelect.innerHTML = '<option value="">Toutes les formations</option>';
            
            // Activer/désactiver les sélecteurs
            elements.niveauSelect.disabled = !cycle;
            elements.formationSelect.disabled = !cycle;
            
            if (cycle) {
                // Remplir les niveaux
                config.niveauxParCycle[cycle].forEach(niv => {
                    elements.niveauSelect.innerHTML += `<option value="${niv}">${niv}</option>`;
                });
                
                // Remplir les formations
                config.formationsParCycle[cycle]?.forEach(formation => {
                    elements.formationSelect.innerHTML += `<option value="${formation}">${formation}</option>`;
                });
            }
        });
        
        // Gestion des autres filtres
        ['filterNiveau', 'filterFormation', 'filterSemestre', 'filterAnnee'].forEach(id => {
            document.getElementById(id).addEventListener('change', function() {
                if (this.value) {
                    appliquerFiltres();
                }
            });
        });
        
        // Bouton de réinitialisation
        elements.resetBtn.addEventListener('click', function() {
            document.querySelectorAll('#filterContainer select').forEach(select => {
                select.value = "";
                if (!['filterCycle', 'filterSemestre', 'filterAnnee'].includes(select.id)) {
                    select.disabled = true;
                }
            });
            filteredEtudiants = [...etudiants];
            afficherEtudiants();
            calculerStatistiques();
        });
        
        // Bouton d'export
        elements.exportBtn.addEventListener('click', function() {
            exporterDonnees();
        });
    }

    function appliquerFiltres() {
        elements.loadingIndicator.classList.remove('d-none');
        
        setTimeout(() => {
            const filters = {
                cycle: elements.cycleSelect.value,
                niveau: elements.niveauSelect.value,
                formation: elements.formationSelect.value,
                semestre: elements.semestreSelect.value,
                annee: document.getElementById('filterAnnee').value
            };
            
            filteredEtudiants = etudiants.filter(etudiant => {
                return Object.entries(filters).every(([key, value]) => {
                    if (!value) return true;
                    
                    switch(key) {
                        case 'cycle':
                            const niveau = etudiant.niveau.charAt(0);
                            return (value === "Licence" && niveau === "L") ||
                                   (value === "Master" && niveau === "M") ||
                                   (value === "Doctorat" && niveau === "D");
                        case 'niveau':
                            return etudiant.niveau === value;
                        case 'formation':
                            return etudiant.codeFormation === value;
                        case 'semestre':
                            return etudiant.semestre === value;
                        default:
                            return true;
                    }
                });
            });
            
            afficherEtudiants();
            calculerStatistiques();
            elements.loadingIndicator.classList.add('d-none');
        }, 300);
    }

    // Affichage des étudiants
    function afficherEtudiants() {
        if (filteredEtudiants.length === 0) {
            elements.tableBody.innerHTML = '';
            elements.emptyMessage.classList.remove('d-none');
            return;
        }
        
        elements.emptyMessage.classList.add('d-none');
        elements.tableBody.innerHTML = filteredEtudiants.map(etudiant => etudiant.renderRow()).join('');
    }

    // Calcul des statistiques
    function calculerStatistiques() {
        if (filteredEtudiants.length === 0) {
            // Réinitialiser toutes les statistiques
            elements.totalCredits.textContent = "0";
            elements.moyenneGenerale.textContent = "-";
            elements.moyenneGlobale.textContent = "-";
            elements.statsEtudiants.textContent = "0";
            elements.statsAdmis.textContent = "0";
            elements.statsEchec.textContent = "0";
            
            // Réinitialiser les moyennes et taux par UE
            Object.values(elements.moyennesUE).forEach(el => el.textContent = "-");
            Object.values(elements.tauxUE).forEach(el => el.textContent = "0%");
            
            return;
        }
        
        // Total des crédits obtenus
        const totalCredits = filteredEtudiants.reduce((sum, etudiant) => sum + etudiant.creditsTotaux, 0);
        elements.totalCredits.textContent = totalCredits;
        
        // Moyenne générale pondérée de tous les étudiants
        let sommeMoyennesPonderees = 0;
        let totalEtudiants = filteredEtudiants.length;
        
        filteredEtudiants.forEach(etudiant => {
            sommeMoyennesPonderees += etudiant.calculerMoyenneGenerale();
        });
        
        const moyenneGlobale = sommeMoyennesPonderees / totalEtudiants;
        elements.moyenneGlobale.textContent = moyenneGlobale.toFixed(2);
        elements.moyenneGlobale.className = `text-center fw-bold ${moyenneGlobale >= 10 ? 'text-success' : 'text-danger'}`;
        
        // Moyenne générale simple (pour compatibilité)
        const moyenneGeneraleSimple = filteredEtudiants.reduce((sum, etudiant) => sum + etudiant.getMoyenneGeneraleSimple(), 0) / filteredEtudiants.length;
        elements.moyenneGenerale.textContent = moyenneGeneraleSimple.toFixed(2);
        
        // Statistiques des états globaux
        elements.statsEtudiants.textContent = filteredEtudiants.length;
        elements.statsAdmis.textContent = filteredEtudiants.filter(e => e.etatGlobal.texte.includes("Validé")).length;
        elements.statsEchec.textContent = filteredEtudiants.filter(e => e.etatGlobal.texte.includes("Non validé")).length;
        
        // Calcul des moyennes et taux par UE
        for (let i = 0; i < 4; i++) {
            const ueIndex = i;
            const moyennesUE = filteredEtudiants.map(e => e.ues[ueIndex]?.moyenne || 0).filter(m => m > 0);
            
            if (moyennesUE.length > 0) {
                // Moyenne de l'UE
                const moyenne = moyennesUE.reduce((sum, m) => sum + m, 0) / moyennesUE.length;
                elements.moyennesUE[`ue${ueIndex + 1}`].textContent = moyenne.toFixed(2);
                
                // Taux de réussite de l'UE
                const ueValidees = filteredEtudiants.filter(e => e.ues[ueIndex]?.moyenne >= 10).length;
                const taux = (ueValidees / filteredEtudiants.length * 100).toFixed(0);
                elements.tauxUE[`ue${ueIndex + 1}`].textContent = `${taux}%`;
            } else {
                elements.moyennesUE[`ue${ueIndex + 1}`].textContent = "-";
                elements.tauxUE[`ue${ueIndex + 1}`].textContent = "0%";
            }
        }
    }

    // Sélection d'un étudiant
    window.selectionnerEtudiant = function(matricule) {
        document.querySelectorAll('#tableResultatsBody tr').forEach(tr => {
            tr.classList.remove('table-active');
        });
        
        const ligne = document.querySelector(`tr[data-id="${matricule}"]`);
        if (ligne) {
            ligne.classList.add('table-active');
        }
        
        const etudiant = filteredEtudiants.find(e => e.matricule === matricule);
        if (etudiant) {
            document.getElementById('modalBodyContent').innerHTML = etudiant.renderDetails();
            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            modal.show();
        }
    };

    // Export des données
    function exporterDonnees() {
        if (filteredEtudiants.length === 0) {
            alert("Aucune donnée à exporter !");
            return;
        }
        
        // Création du contenu CSV
        let csvContent = "Matricule,Nom Complet,Niveau,Semestre,Code Formation";
        
        // Ajouter les colonnes UE
        for (let i = 1; i <= 4; i++) {
            csvContent += `,UE${i} Moyenne,UE${i} État`;
        }
        
        csvContent += ",Crédits Totaux Obtenus,Observation,Moyenne Générale\n";
        
        filteredEtudiants.forEach(etudiant => {
            let ligne = `"${etudiant.matricule}","${etudiant.nomComplet}","${etudiant.niveau}","${etudiant.semestre}","${etudiant.codeFormation}"`;
            
            // Ajouter les données UE
            for (let i = 0; i < 4; i++) {
                const ue = etudiant.ues[i] || { moyenne: 0 };
                const etat = etudiant.getEtatUE(ue.moyenne);
                const etatTexte = etat.texte === 'V' ? 'Validé' : 'Non validé';
                
                ligne += `,"${ue.moyenne.toFixed(2)}","${etatTexto}"`;
            }
            
            const moyenneGen = etudiant.calculerMoyenneGenerale();
            
            ligne += `,"${etudiant.creditsTotaux}","${etudiant.etatGlobal.texte}","${moyenneGen.toFixed(2)}"\n`;
            
            csvContent += ligne;
        });
        
        // Téléchargement
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        
        link.setAttribute("href", url);
        link.setAttribute("download", `resultats_semestriels_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        alert(`Export réussi ! ${filteredEtudiants.length} étudiants exportés.`);
    }

    // Initialiser l'application
    initialiserDonnees();
    initialiserFiltres();
});
</script>
                                        <!-- modal saisie note etudiant -->
                                        <div class="modal fade" id="saisieNoteModal" tabindex="-1" aria-labelledby="saisieNoteModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-xl modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="saisieNoteModalLabel">Saisie des Notes des Étudiants</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                                                        <div class="table-responsive">
                                                            <div id="saisieNoteModalBody"></div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                                        <button type="button" class="btn btn-primary" id="saveNotesButton">Enregistrer les Notes</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- modal cosultation note etudiants  -->
                                        <div class="modal fade" id="consultationNoteModal" tabindex="-1" aria-labelledby="consultationNoteModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-xl modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="consultationNoteModalLabel">Consultation des Notes des Étudiants</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                                                        <div class="table-responsive">
                                                            <div id="consultationNoteModalBody"></div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
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
        <!-- <script src="viewECNote.js"></script> -->

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
</body>

</html>